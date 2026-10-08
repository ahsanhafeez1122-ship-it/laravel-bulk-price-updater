# Bulk Price Updater

[![Tests](https://github.com/ahsanhafeez1122-ship-it/laravel-bulk-price-updater/actions/workflows/tests.yml/badge.svg)](https://github.com/ahsanhafeez1122-ship-it/laravel-bulk-price-updater/actions/workflows/tests.yml)
![Laravel 13](https://img.shields.io/badge/Laravel-13-ff2d20)
![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777bb4)
![License: MIT](https://img.shields.io/badge/license-MIT-green)

A Laravel tool for e-commerce teams to change hundreds of prices from a spreadsheet **safely**:
upload a CSV, review exactly what will change, apply it in one transaction, and roll it back if
something was wrong.

Built for the way pricing work actually goes wrong: a supplier sheet with a typo that drops a
£699 mattress to £69.90, the same SKU twice, a price someone edited by hand ten minutes ago, or
a file saved from European Excel with semicolons.

## How it works

```
Upload CSV ──► Preview (nothing changes yet) ──► Apply (one transaction) ──► Roll back (if needed)
                 │                                 │                           │
                 ├ Will change                     ├ locks the products        ├ restores old prices
                 ├ Large change → needs confirm    ├ skips prices edited       ├ leaves prices that were
                 ├ No change                       │   since the preview       │   changed again alone
                 └ Error (with the reason)         └ writes price history      └ writes price history
```

| Preview status | Meaning |
|---|---|
| **Will change** | Valid new price, within the review threshold |
| **Large change** | Moves more than 30% (configurable) up or down. Must be ticked off before Apply |
| **No change** | Same as the current price. Skipped |
| **Error** | Unknown SKU, unreadable price, duplicate SKU, or compare-at ≤ price, with a plain message |

## Features

- **Preview before anything changes**, with a filter per status and the % change on every row
- **Large-change guard**: changes above the threshold need explicit confirmation (web checkbox,
  `confirm_flagged` in the API, `--confirm-flagged` on the CLI)
- **Stale-data protection**: if a price was edited after the preview, Apply skips it rather than
  overwriting someone's work, and says so on the row
- **All-or-nothing apply** in a DB transaction with `SELECT … FOR UPDATE` on the affected products
- **One-click rollback** that won't undo newer edits made after the import
- **Price history per product**: every change records old/new price, compare-at price and source
- **Tolerant CSV reader**: UTF-8 BOM, `,` `;` or tab delimiters, `£1,299.00` style prices, and
  header aliases (`sku` / `Variant SKU` / `product_code`, `price` / `Variant Price`, `compare_at_price` / `RRP` / `was_price`)
- **Errors as CSV**: download the failed rows, fix them in Excel, upload again
- Money stored as **integer pence**, so there are no floating-point rounding errors
- **Three ways in**: web UI, REST API (Sanctum tokens), and an Artisan command for scheduled feeds

## Quick start

```bash
git clone https://github.com/ahsanhafeez1122-ship-it/laravel-bulk-price-updater.git
cd laravel-bulk-price-updater
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Open http://localhost:8000 and log in with **admin@example.com / password**.
Upload `storage/samples/prices-sample.csv`: it contains every kind of change and error, so the
preview shows all four statuses.

## REST API

```bash
php artisan prices:token admin@example.com        # prints a Sanctum token
```

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/imports` | Upload `file` (multipart), optional `flag_threshold_pct`. Returns the preview summary (201) |
| `GET` | `/api/imports/{id}` | Status and counts |
| `GET` | `/api/imports/{id}/rows?status=error` | Rows, paginated, filterable by status |
| `POST` | `/api/imports/{id}/apply` | Apply. Send `confirm_flagged=true` when there are large changes |
| `POST` | `/api/imports/{id}/rollback` | Roll back an applied import |

```bash
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
     -F file=@prices.csv http://localhost:8000/api/imports
```

```json
{
  "data": {
    "id": 7,
    "status": "previewed",
    "needs_confirmation": true,
    "counts": { "total": 13, "changed": 7, "flagged": 1, "unchanged": 1, "error": 4, "applied": 0, "skipped": 0 }
  }
}
```

Wrong-state actions (applying twice, or applying without confirming large changes) return
`409` with a readable `message`. Bad files return `422`.

## Scheduled supplier feeds

```bash
php artisan prices:import storage/feeds/supplier.csv            # preview only
php artisan prices:import storage/feeds/supplier.csv --apply    # preview + apply
```

```
+------+-------------+---------------+-----------+--------+
| Rows | Will change | Large changes | No change | Errors |
+------+-------------+---------------+-----------+--------+
| 13   | 7           | 1             | 1         | 4      |
+------+-------------+---------------+-----------+--------+
Preview saved as import #1.
```

The command exits non-zero if any row had an error, so a cron job or CI can alert someone.

## Code tour

```
app/Services/PriceImportService.php   preview / apply / rollback / discard: the core logic
app/Services/Csv/PriceCsvReader.php   delimiter + BOM + header alias handling, row limit
app/Support/Money.php                 "£1,299.00" → 129900 pence and back
app/Models/                           Product, PriceImport, PriceImportRow, PriceChange (history)
app/Http/Controllers/                 web UI + Api/ImportController (JSON)
app/Console/Commands/                 prices:import, prices:token
resources/views/                      Blade UI (no front-end build step)
tests/                                34 tests: unit (money, CSV) and feature (flow, API, auth)
```

## Tests

```bash
php artisan test
```

They cover the full preview → apply → rollback flow, the large-change guard, stale-price
protection, applying twice, rollback conflicts, the web UI, the API with Sanctum, error CSV export
and CSV edge cases. CI runs them on PHP 8.4 and 8.5.

## License

MIT, © Ahsan Hafeez
