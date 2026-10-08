# Laravel app (the "after")

The modernized order system: legacy-compatible **v1** API, new **v2** API, and the **Filament** back office.
See the [main README](../README.md) and the [case study](../docs/case-study.md).

```bash
composer install
cp .env.example .env && php artisan key:generate   # set DB_*, LEGACY_API_KEY, ADMIN_PASSWORD (quote values containing #)
php artisan migrate && php artisan db:seed           # schema + back-office login; data comes from ../migrator
php artisan serve                                    # http://localhost:8000  (/admin, /docs, /api, /api/v2)
vendor/bin/pest                                      # needs a PostgreSQL test DB (phpunit.xml: modernized_orders_test)
```

Where things are:
- `app/Http/Legacy`, `app/Http/Controllers/LegacyV1`, `app/Http/Requests/LegacyV1`: the frozen v1 contract (deletable later)
- `app/Http/Controllers/V2`, `app/Http/Resources/V2`: the new API
- `app/Services`: business rules shared by v1, v2 and the admin panel
- `app/Filament`: back office
