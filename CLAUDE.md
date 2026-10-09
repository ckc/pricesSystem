# CLAUDE.md — working notes for AI agents

This repo is a Webman (Workerman) PHP API backend: a bilingual (中文/EN) goods price-comparison system plus an IP network-statistics pipeline. Read this before changing code.

## Run / verify

```bash
composer install
cp .env.example .env   # set DB_* to match your MySQL
# import priceSystem.sql (see caveat below)
php start.php start    # http://127.0.0.1:8787
```

- No test suite exists. Verify changes with `curl` against the local server and by watching `runtime/logs/`.
- Dev auto-reload: the `monitor` process restarts workers when `app/`, `config/`, `process/`, `support/` or `.env` change. After editing, just re-hit the endpoint.
- Windows dev entry: `php windows.php`.

## Conventions you must follow

- **JSON envelope is fixed.** All controllers extend `support\Controller` and return via `dataJson()` / `messageJson()` / `listJson()`: `{"code":200,"data":{…},"count":1,"msg":"ok"}`. Never invent a different shape.
- **Bilingual fields stay paired.** Columns come as `name_chi`/`name_en`, `descText_chi`/`descText_en`, `address_chi`/`address_en`, `ares_chi`/`ares_en`. When adding a user-facing text field, add both.
- **Validation style:** `Respect\Validation\Validator::input($input, [field => Validator::…->setName(…)])`, wrapped in try/catch for `ValidationException` (→ 403 via `messageJson`) and `ModelNotFoundException` (→ 404). Follow it exactly. `Validator::input()` returns only the rule keys — always read raw `$post`/`$get` with `??` defaults *before* validating, because the bootstrap error handler turns undefined-key warnings into 500s.
- **GTIN is the canonical goods key.** `goods.gtin` has a unique index; most read endpoints address goods by GTIN in the path, writes by numeric `id`.
- **Price semantics:** `prices` rows are unique per (`goods_id`, `shop_id`, `sku`); `sku` defaults to `'default'`. Every price upsert must refresh `goods.low_price` / `goods.high_price` (see `PricesController::goodsPost`).
- **Models declare non-standard table names** — always check `$table` in `app/model/*` before writing queries: `allcode` (CodeCheck), `good_rack` (GoodRack), `goods_rackinfo` (GoodsRackInfo), `network_rir_statistics` (NetRir, string PK `hashcode`, non-incrementing), `network_rir_rex` (NetRex), `network_rir_log` (NetRirLog).
- **Images:** uploads land in `public/goods/YYYY/Mon/`, are resized to ≤600px height via imagick, and recorded in `files`. Public URLs use the CDN host `https://img.goods.acghx.net/` — keep building URLs that way; `/backend/{path}` redirects there.
- **HTTP verbs:** reads are GET, creates are POST, edits are PUT, deletes are `POST /…/delete/{id}`. Keep the convention.

## Search behavior (don't "fix" this)

`GoodsController::likes` and `cate` implement the product search: English input is word-split (≥6 chars), Chinese input goes through `Chinese::toSimplified` → `Jieba::cut` → back to Traditional (≥2 chars, top 3 tokens, each >2 chars used as `%LIKE%`), and any 12/13-digit input is treated as a GTIN. `autoMerge()` strips digits first. This is deliberate bilingual behavior — preserve it.

## Network sync pipeline

- `POST /api/network/sync` with `url`, `type` (`delegated`|`rex`|`awsbgp`), `registry`.
- `delegated`: filename must be `delegated-<registry>-<date>`; parses `|`-delimited RIR lines, skipping `*` and `asn` rows.
- `rex`: JSON `data.items` with `allocation_type` ipv4/ipv6; deletes that registry's rows first.
- `awsbgp`: stub (`typeAwsBgp` is empty) — don't wire clients to it yet.
- IP math: `app/functions.php::ip2long_int()` (needs gmp or bcmath for IPv6) + `app/helpers/Ipv4.php` / `Ipv6.php::getAddressRange()`.
- Sync-log statuses: 0 pending, 1 done, 2 download failed, 3 parse error, 5 exception.

## Known gaps (do not silently work around)

- `priceSystem.sql` is missing DDL for `network_rir_statistics`, `network_rir_rex`, `network_rir_log`, `allcode`, `good_rack`, `goods_rackinfo`. Flag before fresh installs.
- No auth middleware anywhere; `config/app.php` has `debug: true`. Raise both before any public deploy.
- `POST /api/network/sync` fetches an arbitrary user-supplied URL server-side (SSRF) — combined with no auth, restrict to an allowlist of RIR/REX hosts before exposing.
- The network sync deletes existing REX rows *before* the download completes (a failed download = data loss); parse errors are swallowed but marked done; re-syncs insert duplicate rows (the hash is random, not content-derived).
- `app/helpers/ItemBarcode.php` is missing from the repo (custom code: `ItemBarcode::ean13($gtin)` → GS1 country name or `false`) — recover from production; goods create/edit fatals without it.
- Redis config is hardcoded to `127.0.0.1:6379` (no env override).
- `Coupons` and `Test` models exist with no routes; `app/events/PricesEvents.php` duplicates the `Prices::booted()` hook but is never wired up (dead code — owner declined deletion on 2026-10-09, leave it).
- Fixed 2026-10-09: undefined-key 500s across controllers (now `??`-guarded), `ShopController::infoEdit` 404, DB charset `utf8mb4`, Task cron comment, `likes()` message.

## Do / don't

- DO keep `.env` out of git; the repo ships `.env.example` only.
- DON'T rename tables/columns — external clients and the CDN depend on them.
- DON'T add auth or change the response envelope without asking the owner first.
- DON'T commit `vendor/`, `runtime/`, or `public/goods/` uploads.
