# Time Keeper

Een eenvoudige timesheet-app voor één gebruiker: timer, weekkalender, projecten en rapporten.

## Lokaal draaien (Herd)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
herd link kdg-time-keeper
```

De gebruiker komt uit `.env` (`TIME_KEEPER_USER_NAME`, `TIME_KEEPER_USER_EMAIL`, `TIME_KEEPER_USER_PASSWORD`). Wijzig het wachtwoord na de eerste login.

## Tests

```bash
php artisan test
npm test
```

## Tijdzones

Alles wordt in UTC opgeslagen. De omzetting naar `Europe/Brussels` (`config/time-keeper.php`) gebeurt op één plek, in `App\Support\LocalTime` en `TimeEntry::localStartedAt()`.

## Hosting

Het project gebruikt geen Chrome voor PDF's (dompdf), dus het draait op shared hosting. Draai `npm run build` lokaal en upload `public/build`.
