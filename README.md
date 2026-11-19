# Time Keeper

Een timesheet-app voor klassen: studenten houden met een timer en weekkalender hun uren per project bij. Admins beheren schooljaren, klassen, studenten, categorieën en klasprojecten en bekijken ieders rapport.

## Lokaal draaien (Herd)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
herd link ka-malle-time-keeper
```

De seeder maakt de rollen en permissies aan, het lopende schooljaar en een eerste admin uit `.env` (`TIME_KEEPER_ADMIN_NAME`, `TIME_KEEPER_ADMIN_EMAIL`, `TIME_KEEPER_ADMIN_PASSWORD`). Wijzig het wachtwoord na de eerste login.

## Rollen

Rollen en permissies lopen via `spatie/laravel-permission`. De rollen en hun permissies staan in `App\Enums\Role` en `App\Enums\Permission` en worden aangemaakt door `RoleSeeder`. Draai `php artisan db:seed --class=RoleSeeder` na een wijziging.

- **Admin**: schooljaren en klassen, gebruikers, alle rapporten, categorieën en klasprojecten.
- **Student**: eigen uren, eigen projecten en de projecten van zijn klas.

## Schooljaren en klassen

Een schooljaar (bijvoorbeeld `2026-2027`, 1 september tot 31 augustus) heeft klassen, een klas heeft studenten. Een student heeft één account voor al zijn jaren en zit per schooljaar in hoogstens één klas.

- **Klasprojecten**: een admin maakt projecten aan op de klaspagina. Alleen de studenten van die klas zien ze, zolang het schooljaar open is.
- **Overzetten**: op de klaspagina zet je alle studenten in één keer over naar een klas van een ander open schooljaar (5A van dit jaar naar 6A van volgend jaar).
- **Afsluiten**: een afgesloten schooljaar blijft volledig bewaard. Uren met een datum binnen dat jaar kunnen niet meer aangemaakt, gewijzigd of verwijderd worden. Rapporten en exports per schooljaar blijven beschikbaar. Een jaar kan heropend worden.
- **Plannen**: een student kan alleen uren registreren, wijzigen of verplaatsen op datums binnen een open schooljaar waarin die in een klas zit. Admins zijn niet beperkt.
- **Toegang**: een student die in geen enkele klas van een open schooljaar zit, kan niet inloggen. Wie al ingelogd is, wordt bij de volgende request uitgelogd. Admins hebben altijd toegang.

## Studentweergave

Met *Bekijk als student* onderaan de sidebar ziet een admin de app zoals een student, met zijn eigen account: alle beheerpermissies zijn uitgeschakeld en de beheermenu's verborgen, tot je de studentweergave verlaat. Dit geldt alleen voor de huidige sessie.

## Bekijken als een specifieke student

Een admin kan via *Gebruikers*, de klaspagina of het rapport van een student de app bekijken als die student. Een balk bovenaan toont wie je bekijkt, met een knop om terug te keren. Profiel- en beveiligingsinstellingen zijn dan geblokkeerd. Andere admins kan je niet bekijken.

## Huisstijl

Logo, favicon, kleuren (limoen `#bed001`, blauw `#3560aa`) en lettertype (Quicksand) komen van [atheneummalle.be](https://www.atheneummalle.be/web/). De kleuren staan als `--color-brand-*` en Flux-accentkleuren in `resources/css/app.css`, de logo's in `public/images`.

## Uitnodigingen

Een admin nodigt gebruikers uit via *Beheer > Gebruikers*, één voor één of een hele klas tegelijk (één regel per student: `Naam, e-mailadres` of enkel het e-mailadres). De student krijgt een mail met een getekende link (7 dagen geldig) om een wachtwoord in te stellen. Een verlopen uitnodiging kan opnieuw verstuurd worden.

Mails gaan via [Resend](https://resend.com). Zet in productie:

```
MAIL_MAILER=resend
RESEND_API_KEY=re_...
MAIL_FROM_ADDRESS=noreply@jouw-geverifieerd-domein.be
```

Lokaal staat `MAIL_MAILER=log`, de mail met de link komt dan in `storage/logs/laravel.log`.

## Tests

```bash
php artisan test
npm test
```

## Tijdzones

Alles wordt in UTC opgeslagen. De omzetting naar `Europe/Brussels` (`config/time-keeper.php`) gebeurt op één plek, in `App\Support\LocalTime` en `TimeEntry::localStartedAt()`.

## Hosting

Het project gebruikt geen Chrome voor PDF's (dompdf), dus het draait op shared hosting. Draai `npm run build` lokaal en upload `public/build`. Mails worden synchroon verstuurd, er is geen queue worker nodig.
