/*!
 * APLINE Simple Slider Banner for PrestaShop 9 — vanilla JS slider.
 *
 * Class-based, no external dependencies (no Swiper, no Glide, no jQuery
 * on the front-end). Reads config from data-assb-* attributes on the
 * .assb-slider root element (server-rendered by the Smarty template
 * from `getRenderConfig()`).
 *
 * Features:
 *   - Autoplay with configurable speed (clamped 500-30000 ms server-side)
 *   - Pause on mouseenter / resume on mouseleave (configurable)
 *   - Loop forever, or stop after the last slide
 *   - Slide (translateX) or fade (opacity + .assb-active class) transitions
 *   - Dynamic dot generation (1 per slide, role=tab + aria-selected)
 *   - Arrow handlers (prev/next, configurable visibility via dots/arrows/both/none)
 *   - Touch swipe on mobile (50 px threshold, passive listeners)
 *   - Keyboard a11y on dots (Enter/Space activate)
 *   - Multiple sliders on one page supported (each gets its own instance)
 *
 * @author Arkadiusz Pielechowski
 */
(function () {
    'use strict';

    var SWIPE_THRESHOLD_PX = 50;

    function AssbSlider(root) {
        this.root = root;
        this.track = root.querySelector('.assb-track');
        this.slides = Array.prototype.slice.call(root.querySelectorAll('.assb-slide'));
        this.dotsContainer = root.querySelector('.assb-dots');
        this.arrowPrev = root.querySelector('.assb-arrow-prev');
        this.arrowNext = root.querySelector('.assb-arrow-next');
        this.dots = [];

        this.current = 0;
        this.timer = null;
        this.touchStartX = null;

        // Read server-rendered config from data-assb-* attributes.
        this.speed = parseInt(root.getAttribute('data-assb-speed'), 10) || 5000;
        this.autoplay = root.getAttribute('data-assb-autoplay') === '1';
        this.loop = root.getAttribute('data-assb-loop') === '1';
        this.pauseHover = root.getAttribute('data-assb-pause-hover') === '1';
        this.navigation = root.getAttribute('data-assb-navigation') || 'dots';
        this.transition = root.getAttribute('data-assb-transition') || 'slide';

        this.init();
    }

    AssbSlider.prototype.init = function () {
        if (this.slides.length === 0) {
            return;
        }

        // For slide mode, the track needs to be wider than container so
        // translateX shifts can reveal the next slide. Set explicitly so
        // we don't rely on CSS guessing the count.
        if (this.transition === 'slide' && this.track) {
            this.track.style.width = (this.slides.length * 100) + '%';
            for (var i = 0; i < this.slides.length; i++) {
                this.slides[i].style.flex = '0 0 ' + (100 / this.slides.length) + '%';
                this.slides[i].style.width = (100 / this.slides.length) + '%';
            }
        }

        if (this.navigation === 'dots' || this.navigation === 'both') {
            this.generateDots();
        }

        // Marker for the CSS: from here on the JS slider drives visibility,
        // so the no-JS fallback CSS rule (first slide always visible in
        // fade mode) should switch off — only .assb-active wins.
        this.root.classList.add('assb-js-ready');

        // Show the first slide without animation (avoids visible "jump"
        // on page load if the browser cached the previous state).
        this.goto(0, false);
        this.attachHandlers();

        if (this.autoplay && this.slides.length > 1) {
            this.start();
        }
    };

    AssbSlider.prototype.generateDots = function () {
        if (!this.dotsContainer) {
            return;
        }
        this.dotsContainer.innerHTML = '';
        this.dots = [];
        for (var i = 0; i < this.slides.length; i++) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('role', 'tab');
            btn.setAttribute('aria-label', 'Przejdź do slajdu ' + (i + 1));
            btn.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
            btn.setAttribute('data-assb-index', String(i));
            this.dotsContainer.appendChild(btn);
            this.dots.push(btn);
        }
    };

    AssbSlider.prototype.updateDots = function () {
        for (var i = 0; i < this.dots.length; i++) {
            this.dots[i].setAttribute('aria-selected', i === this.current ? 'true' : 'false');
        }
    };

    /**
     * Move to the slide at `index`. Normalises out-of-range values:
     * loop mode wraps around, no-loop mode clamps to [0, N-1].
     *
     * @param {number} index
     * @param {boolean} animate  set false to skip CSS transition (used at init)
     */
    AssbSlider.prototype.goto = function (index, animate) {
        if (this.slides.length === 0) {
            return;
        }

        if (this.loop) {
            // JS modulo can yield negatives — coerce to non-negative.
            index = ((index % this.slides.length) + this.slides.length) % this.slides.length;
        } else {
            if (index < 0) { index = 0; }
            if (index > this.slides.length - 1) { index = this.slides.length - 1; }
        }

        this.current = index;

        if (this.transition === 'fade') {
            for (var i = 0; i < this.slides.length; i++) {
                if (i === index) {
                    this.slides[i].classList.add('assb-active');
                } else {
                    this.slides[i].classList.remove('assb-active');
                }
            }
        } else {
            // Slide mode: translateX = -index * (100/N)% on the wider track.
            var offset = -index * (100 / this.slides.length);
            if (animate === false && this.track) {
                var prevTransition = this.track.style.transition;
                this.track.style.transition = 'none';
                this.track.style.transform = 'translateX(' + offset + '%)';
                // Force reflow so the next change re-enables the transition.
                /* eslint-disable no-unused-expressions */
                this.track.offsetHeight;
                /* eslint-enable no-unused-expressions */
                this.track.style.transition = prevTransition;
            } else if (this.track) {
                this.track.style.transform = 'translateX(' + offset + '%)';
            }
        }

        this.updateDots();

        // For non-loop sliders, autoplay stops once we've shown the last
        // slide — the user can still navigate manually with arrows/dots.
        if (!this.loop && index === this.slides.length - 1) {
            this.stop();
        }
    };

    AssbSlider.prototype.next = function () {
        this.goto(this.current + 1);
        this.restart();
    };

    AssbSlider.prototype.prev = function () {
        this.goto(this.current - 1);
        this.restart();
    };

    AssbSlider.prototype.start = function () {
        this.stop();
        // Don't bother starting on a 1-slide list or at the end of a
        // non-loop slider — both are stable states with nothing to do.
        if (this.slides.length <= 1) {
            return;
        }
        if (!this.loop && this.current === this.slides.length - 1) {
            return;
        }

        var self = this;
        this.timer = window.setInterval(function () {
            self.goto(self.current + 1);
        }, this.speed);
    };

    AssbSlider.prototype.stop = function () {
        if (this.timer !== null) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    };

    /**
     * Restart the autoplay timer after a user interaction (arrow / dot
     * click / swipe). Respects: autoplay disabled = stay stopped;
     * non-loop slider at last position = stay stopped; otherwise reset
     * the timer so the user gets a full `speed` interval to read the
     * slide they just navigated to.
     */
    AssbSlider.prototype.restart = function () {
        if (!this.autoplay) {
            return;
        }
        if (!this.loop && this.current === this.slides.length - 1) {
            return;
        }
        this.stop();
        this.start();
    };

    AssbSlider.prototype.attachHandlers = function () {
        var self = this;

        // Pause on hover (configurable).
        if (this.pauseHover) {
            this.root.addEventListener('mouseenter', function () {
                self.stop();
            });
            this.root.addEventListener('mouseleave', function () {
                if (self.autoplay) {
                    self.start();
                }
            });
        }

        // Arrows.
        if (this.arrowPrev) {
            this.arrowPrev.addEventListener('click', function (e) {
                e.preventDefault();
                self.prev();
            });
        }
        if (this.arrowNext) {
            this.arrowNext.addEventListener('click', function (e) {
                e.preventDefault();
                self.next();
            });
        }

        // Dots (click + keyboard).
        for (var i = 0; i < this.dots.length; i++) {
            (function (index, btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.goto(index);
                    self.restart();
                });
                btn.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32) {
                        e.preventDefault();
                        self.goto(index);
                        self.restart();
                    }
                });
            })(i, this.dots[i]);
        }

        // Touch swipe (passive listeners so the browser keeps native scroll perf).
        if (this.track) {
            this.track.addEventListener('touchstart', function (e) {
                if (e.touches && e.touches.length === 1) {
                    self.touchStartX = e.touches[0].clientX;
                }
            }, { passive: true });

            this.track.addEventListener('touchend', function (e) {
                if (self.touchStartX === null) {
                    return;
                }
                var endX = e.changedTouches && e.changedTouches.length
                    ? e.changedTouches[0].clientX
                    : self.touchStartX;
                var deltaX = endX - self.touchStartX;
                self.touchStartX = null;

                if (Math.abs(deltaX) > SWIPE_THRESHOLD_PX) {
                    if (deltaX > 0) {
                        self.prev();
                    } else {
                        self.next();
                    }
                }
            }, { passive: true });

            // Cancel a pending swipe if the touch was interrupted (e.g. by a
            // scroll gesture) — avoids ghost-navigating on next touchend.
            this.track.addEventListener('touchcancel', function () {
                self.touchStartX = null;
            }, { passive: true });
        }
    };

    // ----------------------------------------------------------------
    // Bootstrap: instantiate one slider per .assb-slider on the page.
    // Idempotent — re-running on the same root is a no-op (handy if a
    // theme re-injects markup via AJAX).
    // ----------------------------------------------------------------
    function initAll() {
        var roots = document.querySelectorAll('.assb-slider');
        for (var i = 0; i < roots.length; i++) {
            if (!roots[i].assbInstance) {
                roots[i].assbInstance = new AssbSlider(roots[i]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Expose for debugging / programmatic control from the page console
    // (e.g. `window.AssbSlider` lets a developer instantiate ad-hoc sliders).
    window.AssbSlider = AssbSlider;
})();
