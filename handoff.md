# handoff.md — pricesSystem

State as of 2026-10-09. Read this first when resuming work on this repo.

## What this is

Webman (Workerman) PHP API backend for a bilingual (中文/EN) product price-comparison system: GTIN-keyed goods catalog, brands, shops, per-shop prices, coupons, product images via CDN, shelf-rack locations — plus an IP network-statistics (RIR/REX delegated data) ingest pipeline. Full details in `README.md`; agent conventions in `AGENTS.md` / `CLAUDE.md`; compact reference in `llms.txt`.

## Critical

- `app/helpers/ItemBarcode.php` is **missing from the repo** (verified absent on disk;
  `app/helpers/` contains only `Ipv4.php`/`Ipv6.php`). It is custom project code —
  `ItemBarcode::ean13($gtin)` is called by `GoodsController::infoPost()`/`infoEdit()`
  and returns the GS1 country name or `false`. Goods create/edit will fatal without it.
  Recover it from the production copy; it cannot be regenerated from the framework.
- `vendor/` is absent in this checkout — run `composer install` before `php start.php start`.
- (Correction, 2026-10-09: an earlier note claimed `support/Model.php`,
  `support/bootstrap/*`, and `support/exception/Handler.php` were missing. That was
  wrong — in webman 1.x those classes ship inside the `workerman/webman-framework`
  composer package (`"support\\": "./src/support"`), not the project skeleton.
  No action needed.)

## Done on 2026-10-09

- Replaced the stock Webman-scaffold `README.md` with a real one: stack, quick start, config, full API endpoint tables (`/api/v1`, `/api/app`, `/api/network`), DB schema map, directory map, caveats.
- Added `CLAUDE.md` (code conventions, search behavior, sync pipeline, known gaps).
- Added `llms.txt` (compact LLM reference).
- Added `AGENTS.md` (operating conventions + boundaries for agents).
- Added `.env.example` (the repo previously had no env template) and `.gitignore`
  (excludes `.env`, `vendor/`, `runtime/`, `public/goods/` uploads).
- Corrected table names in docs: `goods_rackinfo`, `network_rir_rex`, `network_rir_log`;
  `prices_log` is written by the `Prices::booted()` hook (earlier note was wrong).

## Code review + fixes (2026-10-09)

All 56 PHP files + configs reviewed (with a helper agent).

### Fixed
- 500-causing undefined array-key accesses (the bootstrap error handler turns PHP
  warnings into 500s): `GoodsController::cate()` missing `?id=`; `likes()` with only
  `ch_name` (+ unguarded `orWhere`/`keywords` uses); `info()` single-category goods;
  `goodsPost()`/`infoEdit()`/`infoPost()` missing POST fields; `BrandsController::search()`
  missing `keyword`; `IndexController::config()` dangling parent;
  `GoodsController::cate()` dangling brand; `NetWorkController::sync()` missing
  `registry` / malformed delegated filename (now a clean 403).
  All now use `??` defaults or explicit guards; bad input falls through to the
  intended 403 validation errors.
- `ShopController::infoEdit()` returned 403 on missing shop — now 404.
- DB charset `utf8` → `utf8mb4` (matches the dump; was breaking emoji/CJK input).
- `process/Task.php` comment corrected (06:00, not 07:50); `likes()` EN error
  message corrected to match the `< 6` check.
- All 8 edited files pass `php -l` (checked with PHP 8.3).

### Found, not changed (needs owner decision)
1. `app/helpers/ItemBarcode.php` missing (custom code) — recover from production;
   goods create/edit fatals without it.
2. No auth on any route; `POST /api/network/sync` lets anyone make the server
   fetch arbitrary URLs (SSRF); `debug: true` leaks file paths; CORS wide open.
3. Network sync deletes existing REX rows *before* the download finishes (failed
   download = data loss); parse errors are swallowed yet marked "done"; re-syncs
   insert duplicate rows (random hash instead of content hash).
4. `priceSystem.sql` still missing DDL for 6 tables (`network_rir_statistics`,
   `network_rir_rex`, `network_rir_log`, `allcode`, `good_rack`, `goods_rackinfo`).
5. `app/events/PricesEvents.php` is dead duplicate code — deletion declined by owner
   on 2026-10-09; left in place.
6. Minor: `composer.json` still stock webman skeleton (name/author, `php >= 7.2`
   while code needs 7.4+); Redis hardcoded to 127.0.0.1:6379; `typeAwsBgp` is a stub;
   `Coupons`/`Test` models have no routes. No test suite.

### Verified non-issues
- `Validator::Digit()`/`Url()`/`Alpha()`/`IntType()`/`FloatType()` casing is fine
  (the factory does `ucfirst()` before resolving the rule class).
- `Validator::input($in, $rules)` returns only the rule keys; missing input falls
  back to the rule default.

## How to verify

```bash
composer install && cp .env.example .env
# import priceSystem.sql into MySQL
php start.php start
curl http://127.0.0.1:8787/api/v1/config
```
