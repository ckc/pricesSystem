# Changelog

All notable changes to this project are recorded here, newest first.
Rule: every change to the repo — code, config, or docs — gets an entry below.
No exceptions.

## 2026-10-09

### Added
- `README.md` rewritten from the stock Webman scaffold: stack, quick start,
  `.env` config, full API tables (`/api/v1`, `/api/app`, `/api/network`),
  database schema map, directory map, caveats.
- `CLAUDE.md`: agent working notes (conventions, search behavior, sync pipeline, known gaps).
- `llms.txt`: compact LLM reference.
- `AGENTS.md`: operating conventions and boundaries for AI agents.
- `handoff.md`: project state, code-review record, open items.
- `.env.example`: DB env template (the repo had none).
- `.gitignore`: excludes `.env`, `vendor/`, `runtime/`, `public/goods/` uploads, IDE/OS files.
- This `CHANGELOG.md`, and the rule that every change gets logged here.

### Fixed
- 500s from undefined array keys (the bootstrap error handler turns PHP warnings
  into 500s) — all now `??`-guarded so bad input reaches the intended 403/404:
  - `GoodsController::cate()` with missing `?id=`.
  - `GoodsController::likes()` with only `ch_name` (plus unguarded `orWhere`/`keywords` uses).
  - `GoodsController::info()` for goods with a single category id.
  - `PricesController::goodsPost()`, `GoodsController::infoEdit()`/`infoPost()`
    with missing POST fields.
  - `BrandsController::search()` with missing `keyword`.
  - `IndexController::config()` with a dangling parent type.
  - `GoodsController::cate()` with a dangling brand id.
  - `NetWorkController::sync()` with missing `registry` / malformed delegated filename.
- `ShopController::infoEdit()` returned 403 for a missing shop — now 404.
- DB charset `utf8` → `utf8mb4` in `config/database.php` (matches the dump).
- `process/Task.php` comment corrected (cron runs 06:00, not 07:50).
- `GoodsController::likes()` English error message corrected to match the `< 6` check.
- Docs corrections: `goods_rackinfo`, `network_rir_rex`, `network_rir_log` table names;
  `prices_log` is written by the `Prices::booted()` hook.

### Not changed (owner decision pending)
- `app/helpers/ItemBarcode.php` still missing from the repo (custom code) — recover
  from production; goods create/edit fatals without it.
- `app/events/PricesEvents.php` (dead duplicate code) — deletion declined 2026-10-09,
  left in place.
- No auth on any route; `POST /api/network/sync` SSRF surface; `debug: true`;
  network sync deletes REX rows before download completes; `priceSystem.sql` still
  missing DDL for 6 tables.
