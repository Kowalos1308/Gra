# Gra przeglądarkowa (baza)

Baza gry webowej w PHP + MySQL (pod import przez phpMyAdmin), rozszerzona o tryb **KLANÓWKI**.

## Co jest gotowe
- Rejestracja postaci (`nick + hasło`)
- Logowanie / wylogowanie
- Panel po zalogowaniu z układem:
  - góra: dane postaci, pasek EXP, poziom
  - prawa strona topbara: pieniądze + zmęczenie + punkty rozwoju
  - lewy sidebar: menu gry
  - prawa część: podgląd gracza + sekcja KLANÓWKI
- Podgląd gracza:
  - avatar (upload własnego pliku)
  - sekcja statystyk (AIM, Refleks, Mobilność, Kontrola Odrzutu, Granaty, Szczęście)
- Progresja:
  - start: 10 w każdej statystyce
  - level up daje +1 punkt rozwoju do rozdania
  - automatyczny bonus: co 10 poziomów +1 do wszystkich statystyk (liczony przez mechanikę puli)
  - formuła EXP: `100 * (lvl ^ 1.8)`
  - cap: lvl 100

## KLANÓWKI
- 5 rang: Silver → Gold → Kałach → Supreme → Global
- Każda ranga ma stałą pulę 10 map
- Przycisk „Szukaj meczu” losuje mapę z aktualnej puli i symuluje mecz BO1 do 13 rund (max 24, możliwy remis 12:12)
- Moc gracza: `AIM + Refleks + Mobilność + Kontrola Odrzutu + Granaty`
- Moc oponenta: `baza_rangi + losowo(-12..+18)`
- Szansa wygrania rundy:
  - `0.5 + (playerPower - oppPower) * 0.004`
  - clamp do `0.23..0.77`
- Nagrody EXP:
  - Przegrana: 0
  - Remis / Wygrana: zakresy zgodne ze specyfikacją per ranga
- Postęp map:
  - wygrana na czerwonej mapie oznacza ją jako zieloną
  - 10/10 zielonych map = automatyczny awans do kolejnej rangi
- Zmęczenie (stamina):
  - +1 za każdy mecz, max 6
  - przy 6/6 blokada „Szukaj meczu”
  - regeneracja: -1 co 60 minut (real-time)

## Integracja z phpMyAdmin
1. Otwórz phpMyAdmin i zaimportuj plik:
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
- `src/game.php` – logika leveli, exp, punktów rozwoju i klanówek
- `src/db.php` – połączenie PDO MySQL
- `sql/schema.sql` – schemat MySQL do importu przez phpMyAdmin
