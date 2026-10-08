# APLINE — slider banerów dla PrestaShop 9

Lekki slider na stronę główną z osobnymi obrazami na komputer i telefon. Każdy slajd może prowadzić do wybranego adresu. Moduł działa bez zewnętrznych bibliotek JavaScript i ma polski panel konfiguracji.

## Funkcje

- Osobne obrazy oraz widoczność dla komputera (od 768 px) i telefonu (do 767 px), bez zastępowania brakującego obrazu drugim wariantem.
- JPG, PNG i WebP, maksymalnie 4 MB na obraz; teksty alternatywne i opcjonalne linki.
- Kolejność slajdów, włączanie i wyłączanie, kropki, strzałki, gest przesunięcia na telefonie.
- Automatyczne przewijanie, pauza po najechaniu, zapętlenie i przejście przez przesunięcie lub przenikanie.
- Naturalna wysokość lub stałe proporcje, osobne rozmiary i kontenery dla obu ekranów.
- Duży przycisk **Zarządzaj slajdami** i trzy neutralne slajdy przykładowe na świeżej instalacji (jeśli dostępne jest GD).

## Wymagania

PrestaShop 9, PHP 8.1 lub nowszy (zgodny z wymaganiami użytej wersji PrestaShop), prawo zapisu w `views/img/`. GD jest potrzebne do generowania przykładowych obrazów; bez niego można dodać własne. Kontenery `.container` i `.container-fluid` wymagają Bootstrap w motywie.

## Instalacja

1. Pobierz ZIP modułu z [Releases](https://github.com/piele9/apline_simple_slider_banner/releases).
2. W panelu PrestaShop wybierz **Moduły → Menedżer modułów → Prześlij moduł** i wskaż ZIP.
3. Przy instalacji ręcznej rozpakuj folder `apline_simple_slider_banner` do `modules/` — nazwa folderu musi pozostać dokładnie taka.
4. Zainstaluj moduł i otwórz jego konfigurację.

## Konfiguracja

1. Kliknij **Zarządzaj slajdami**. Dodaj slajd lub zastąp obrazy przykładowe.
2. Wpisz tytuł wewnętrzny, prześlij obrazy i uzupełnij teksty alternatywne. Dla każdego włączonego ekranu wymagany jest odpowiadający mu obraz.
3. Opcjonalnie ustaw link; wyłącz widoczność tam, gdzie slajd nie ma być pokazywany. Zapisz i ustaw kolejność na liście.
4. W konfiguracji ustaw czas zmiany (500–30000 ms), automatyczne przewijanie, zapętlenie, nawigację i przejście.
5. Wybierz naturalną wysokość lub stały rozmiar. Stały rozmiar zachowuje proporcje na wąskich ekranach; sposób dopasowania to przycięcie, rozciągnięcie lub cały obraz.
6. Jeśli motyw ładuje Bootstrap, włącz tę opcję i wybierz kontener osobno dla komputera i telefonu. Własna klasa CSS pozwala dopasować wygląd do sklepu.

Domyślnie slider jest w `displayHome`. Można go też osadzić w szablonie Smarty przez `{widget name='apline_simple_slider_banner'}`. Na każdym ekranie wyświetlane są tylko aktywne slajdy z właściwym obrazem i włączoną widocznością.

## Aktualizacja

Wykonaj kopię bazy oraz katalogu modułu z przesłanymi obrazami. Prześlij nowy ZIP i uruchom aktualizację modułu w menedżerze. Wersja 1.3.0 zmienia nazwę zakładki na polską, zachowując obrazy, slajdy i konfigurację. Nie odinstalowuj modułu w celu aktualizacji. Następnie wyczyść cache PrestaShop i sprawdź oba warianty ekranów.

## Odinstalowanie

Odinstalowanie usuwa tabelę slajdów, konfigurację i obrazy przesłane przez moduł. Przed usunięciem wykonaj kopię danych, jeśli chcesz je zachować.

## Historia zmian i licencja

Zmiany wersji: [CHANGELOG.md](CHANGELOG.md). Licencja MIT — pełny tekst w [LICENSE.md](LICENSE.md). Moduł możesz używać, zmieniać i rozpowszechniać, także komercyjnie; zachowaj tylko informację o prawach autorskich i licencji.

Autor: **Arkadiusz Pielechowski** — [pielechowski.pl](https://pielechowski.pl).
