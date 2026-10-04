# MediCore Pharmacy ERP: notes for coding agents

Laravel 13 + Blade + Tailwind 4 + Alpine. See README.md for setup.

- Run `php artisan test` (in-memory SQLite) and `./vendor/bin/pint` before finishing any change.
- Stock may only change through `App\Services\InventoryService`. Never write `batches.quantity` or `medicines.stock_quantity` directly.
- Money maths goes through `App\Support\Money` (integer cents).
- Sales, purchases, returns and payments are created only by their services, inside DB transactions. Transactional models use `$guarded = ['*']` and `forceFill`.
- New business tables need `pharmacy_id` and the `BelongsToPharmacy` trait. Validation of foreign ids uses `App\Support\TenantRule`.
- New permissions go in `config/pharmacy.php` and are enforced with `can:` middleware on routes. Then run `php artisan db:seed --class=PermissionSeeder`.
- Never delete completed transactions. Cancel, reverse or soft-delete instead, and log important actions through `ActivityLogger`.
- In Blade, embed JSON in attributes with `{{ json_encode([...]) }}`, not `@json([...])`: Blade splits `@json` arguments on commas.
