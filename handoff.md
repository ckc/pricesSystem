# handoff.md — pricesSystem

State as of 2026-10-09. Read this first when resuming work on this repo.

## What this is

Webman (Workerman) PHP API backend for a bilingual (中文/EN) product price-comparison system: GTIN-keyed goods catalog, brands, shops, per-shop prices, coupons, product images via CDN, shelf-rack locations — plus an IP network-statistics (RIR/REX delegated data) ingest pipeline. Full details in `README.md`; agent conventions in `AGENTS.md` / `CLAUDE.md`; compact reference in `llms.txt`.

## Done on 2026-10-09

- Replaced the stock Webman-scaffold `README.md` with a real one: stack, quick start, config, full API endpoint tables (`/api/v1`, `/api/app`, `/api/network`), DB schema map, directory map, caveats.
- Added `CLAUDE.md` (code conventions, search behavior, sync pipeline, known gaps).
- Added `llms.txt` (compact LLM reference).
- Added `AGENTS.md` (operating conventions + boundaries for agents).
- Added `.env.example` (the repo previously had no env template).
- Added this `handoff.md`.

## Open items (not started)

1. **SQL dump is incomplete.** `priceSystem.sql` has no DDL for `network_rir_statistics`, `net_rex`, the network sync-log table, `allcode`, `good_rack`, `goods_rack_info` — all referenced by models/controllers. Re-export from the production DB before any fresh install.
2. **No auth, `debug: true`.** Every route is open and `config/app.php` ships debug on. Add auth middleware + flip debug before any public exposure.
3. **Dead/placeholder code:** `NetWorkController::typeAwsBgp()` is an empty stub; `Coupons`/`Test` models have no routes; nothing writes to `prices_log` despite the model existing.
4. **Minor code/message mismatches:** `process/Task.php` comment says 07:50 but cron is `0 6 * * *`; `GoodsController::likes` English error says "no less 12 letter" while the check is `< 6`.
5. **Redis is hardcoded** to `127.0.0.1:6379` in `config/redis.php` — no env override.
6. `.gitignore` added 2026-10-09 (excludes `.env`, `vendor/`, `runtime/`, `public/goods/` uploads). No test suite.

## How to verify

```bash
composer install && cp .env.example .env
# import priceSystem.sql into MySQL
php start.php start
curl http://127.0.0.1:8787/api/v1/config
```
