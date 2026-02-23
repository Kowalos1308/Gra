# Gra przeglądarkowa (baza)

Minimalna baza gry webowej w PHP + MySQL (pod import przez phpMyAdmin):

## Co jest gotowe
- Rejestracja postaci (`nick + hasło`)
- Logowanie / wylogowanie
- Panel po zalogowaniu z układem:
  - góra: dane postaci, pasek EXP, poziom
  - prawa strona topbara: pieniądze + punkty rozwoju
  - lewy sidebar: menu gry
  - prawa część: podgląd gracza
- Podgląd gracza:
  - avatar (upload własnego pliku)
  - sekcja statystyk (AIM, Refleks, Mobilność, Kontrola Odrzutu, Granaty, Szczęście)
- Progresja:
  - start: 10 w każdej statystyce
  - level up daje +1 punkt rozwoju do rozdania
  - automatyczny bonus: co 10 poziomów +1 do wszystkich statystyk (wyliczany przez mechanikę puli)
  - formuła EXP: `100 * (lvl ^ 1.8)`
  - cap aktualnie do lvl 100

## Integracja z phpMyAdmin
1. Otwórz phpMyAdmin i utwórz bazę lub zaimportuj plik:
   - `sql/schema.sql`
2. Ustaw dane połączenia przez zmienne środowiskowe (lub domyślne wartości w `src/config.php`):
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`

## Uruchomienie lokalne
```bash
cd /workspace/Gra
php -S 0.0.0.0:8000 -t .
```

Aplikacja:
- `http://localhost:8000/public/index.php`

## Struktura
- `public/index.php` – UI + akcje formularzy
- `public/style.css` – style panelu
- `src/auth.php` – logowanie/rejestracja/sesja
- `src/game.php` – logika leveli, exp i punktów rozwoju
- `src/db.php` – połączenie PDO MySQL
- `sql/schema.sql` – schemat MySQL do importu przez phpMyAdmin

## Uwaga
W panelu jest przycisk testowy `+250 EXP (test)` tylko na etap budowy bazy, żeby łatwo sprawdzać progresję.
