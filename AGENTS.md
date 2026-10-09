# AGENTS.md — pricesSystem

Operating conventions for AI agents working in this repo. Read `README.md` for the overview, `CLAUDE.md` for code-level notes, `llms.txt` for the compact reference.

## Environment

- PHP >= 7.2 with `imagick`, `gmp` (or `bcmath`) and `event` extensions recommended. Composer for deps.
- Local run: `composer install` → `.env` from `.env.example` → import `priceSystem.sql` → `php start.php start` (http://127.0.0.1:8787).
- There is no test suite. After a change, verify with `curl` and check `runtime/logs/`; the `monitor` process auto-reloads workers on file change.

## Code conventions

- Controllers live in `app/controller/`, extend `support\Controller`, and return only via `dataJson()` / `messageJson()` / `listJson()` — the `{"code","data","count","msg"}` envelope is a hard contract.
- Validate with `Respect\Validation\Validator::input()` + `->setName()`; catch `ValidationException` → `messageJson(403, …)`, `ModelNotFoundException` → 404 JSON. Keep this exact pattern.
- Models live in `app/model/` and declare explicit `$table` names (several are non-standard: `allcode`, `good_rack`, `network_rir_statistics`). Check the model before writing any query.
- User-facing text is bilingual: always handle `*_chi` and `*_en` together.
- HTTP verb convention: GET reads, POST creates, PUT edits, `POST /…/delete/{id}` deletes. Don't invent new shapes.
- GTIN is the product identity for reads; numeric `id` for writes. `prices` rows are per (`goods_id`, `shop_id`, `sku`); keep `goods.low_price` / `goods.high_price` in sync on every price write (see `PricesController::goodsPost`).
- Image URLs are built on `https://img.goods.acghx.net/`; uploads go to `public/goods/YYYY/Mon/` via `FilesController` (jpg/jpeg/png, ≤600px height).

## Boundaries

- Don't add authentication, change the response envelope, or rename tables/columns without the owner's explicit yes — external clients depend on all three.
- Don't commit `.env`, `vendor/`, `runtime/`, or `public/goods/` uploads.
- The `priceSystem.sql` dump is missing DDL for the network tables, `allcode`, `good_rack`, `goods_rack_info` — never claim a fresh install is complete from the dump alone.
- `POST /api/network/sync` with `type=awsbgp` is a stub; don't build clients against it.
- Keep fixes minimal and bilingual-safe: Chinese search tokenization in `GoodsController::autoMerge()` is deliberate behavior, not a bug.

## When in doubt

Ask the owner before: exposing the service publicly (no auth + `debug: true` as shipped), changing price/GTIN semantics, or touching `NetWorkController`'s ingest statuses.
