# pricesSystem

Bilingual (中文 / EN) product price-comparison API backend: a goods catalog keyed by GTIN barcode, with brands, shops, per-shop prices, coupons, product images and shelf-rack locations — plus an IP network-statistics (RIR / REX) data pipeline. Built on [Webman](https://www.workerman.net/webman) (Workerman, event-driven PHP).

## Stack

| Layer | Choice |
|---|---|
| Runtime | PHP >= 7.2, Webman ^1.5 (Workerman HTTP server, multi-process) |
| Database | MySQL / MariaDB via `illuminate/database` (Eloquent ORM) |
| Cache/queue | Redis via `illuminate/redis` (currently hardcoded to 127.0.0.1:6379 in `config/redis.php`) |
| Images | `intervention/image` (imagick driver) — upload + resize |
| Search | `binaryoung/jieba-php` + `yurunsoft/chinese-util` for Chinese tokenization |
| Validation | `workerman/validation` (Respect-style `Validator::input`) |
| HTTP client | `workerman/http-client` (async downloads in the network sync) |
| Cron | `workerman/crontab` |
| CORS | `webman/cors` |
| Config | `vlucas/phpdotenv` (`.env` file) |

## Quick start

```bash
composer install
cp .env.example .env        # then set your DB credentials
# import priceSystem.sql into MySQL (see "Database" below)
php start.php start          # foreground
php start.php start -d       # daemon
# open http://127.0.0.1:8787
```

On Windows: `php windows.php` (or double-click `windows.bat`).

Stop: `php start.php stop`, restart: `php start.php restart`, reload: `php start.php reload`, status: `php start.php status`.

## Configuration

- `.env` — database credentials: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SOCKET`. Defaults in `config/database.php` point at `127.0.0.1:3306`, database/user `forge`, empty password.
- `config/server.php` — listens on `http://0.0.0.0:8787`, worker count `cpu_count() * 4`.
- `config/app.php` — `debug: true`, default timezone `Asia/Shanghai`. Set `debug` to `false` before any public deployment.
- `config/process.php` — `monitor` process auto-reloads workers when files under `app/`, `config/`, `process/`, `support/` or `.env` change (dev); `task` process runs the daily cleanup cron.

## API reference

All JSON responses share one envelope (see `support/Controller.php`):

```json
{ "code": 200, "data": { ... }, "count": 1, "msg": "ok" }
```

Errors use the same shape with a non-200 `code` and a `msg` (e.g. `404` / `"Goods not found"`, `403` for validation failures).

### Public API — `/api/v1`

| Method | Path | Description |
|---|---|---|
| GET | `/api/v1/config` | Category tree (`types` level 0/1) |
| GET | `/api/v1/goods/cate?id=1,2` | Goods filtered by category type ids |
| GET | `/api/v1/goods/price/{gtin}` | Price comparison for one GTIN across shops |
| PUT | `/api/v1/goods/price/{id}` | Upsert price (`id` = goods id; body: `shop_id`, `prices`, `sku`). Refreshes `goods.low_price` / `high_price` |
| GET | `/api/v1/goods/rack/{gtin}` | Shelf-rack locations for one GTIN |
| GET | `/api/v1/goods/{sn}` | Goods detail by GTIN (`sn` = barcode) |
| POST | `/api/v1/goods/{sn}` | Create goods, GTIN taken from the path |
| PUT | `/api/v1/goods/{id}` | Edit goods by id |
| GET | `/api/v1/likes/goods` | Keyword search: `gtin=`, `en_name=` (min 6 chars), `ch_name=` (min 2 chars). 12/13-digit input is treated as a barcode |
| GET | `/api/v1/brand/search?keyword=` | Brand prefix search (Chinese/English name) |
| GET | `/api/v1/brand/{name}` | Brand info |
| POST | `/api/v1/brand` | Create brand |
| PUT | `/api/v1/brand/{id}` | Edit brand |
| GET | `/api/v1/shop/{name}` | Fuzzy shop search by name |
| POST | `/api/v1/shop` | Create shop |
| PUT | `/api/v1/shop/{id}` | Edit shop |

### App/admin API — `/api/app`

| Method | Path | Description |
|---|---|---|
| GET | `/api/app/sync/goods` | One-off normalization: strips the `GS1 ` prefix from `goods.country` |
| GET | `/api/app/check/goods/sn?code=` | Check whether a barcode exists in the `allcode` staging table |
| GET | `/api/app/goods/list?page=&limit=` | Paginated goods with brand names, image dimensions and CDN URLs (default limit 25) |
| POST | `/api/app/goods/delete/{id}` | Delete goods |
| GET | `/api/app/brand/list`, `/api/app/brand/search?keyword=` | Paginated brands / keyword search |
| POST | `/api/app/brand/delete/{id}` | Delete brand |
| GET | `/api/app/shop/list` | Paginated shops |
| POST | `/api/app/shop/delete/{id}` | Delete shop |
| POST | `/api/app/files` | Image upload (`jpg`/`jpeg`/`png` only). Resized to max 600px height (imagick), stored under `public/goods/YYYY/Mon/`, recorded in `files`, returns CDN URL + id |

### Network statistics — `/api/network`

| Method | Path | Description |
|---|---|---|
| POST | `/api/network/sync` | Async ingest of IP allocation data. Body: `url`, `type` (`delegated` \| `rex` \| `awsbgp`), `registry`. See `app/controller/NetWorkController.php` |

`type=delegated` parses RIR delegated-stats text files (filename must look like `delegated-<registry>-<date>`); `type=rex` parses REX JSON (`data.items`) and wipes existing rows for that registry first; `type=awsbgp` is currently a stub. IPv4/IPv6 ranges are converted to integer start/end via `ip2long_int()` (`app/functions.php`) using `app/helpers/Ipv4.php` / `Ipv6.php`. Ingest progress is tracked in the sync-log table with statuses: `0` pending (a second request while pending returns `"the mission is on the road"`), `1` done, `2` download failed, `3` parse error, `5` exception.

> Image CDN: requests to `/backend/{path}` redirect to `https://img.goods.acghx.net/{path}`. Uploaded product images live in `public/goods/` and are expected to be served through that CDN.

## Database

Import `priceSystem.sql` (phpMyAdmin dump of the `priceSystem` database, MariaDB 10.11, generated 2024-11-06). Tables included:

`goods` (GTIN unique key), `prices`, `prices_log`, `shops`, `brand`, `types` (category tree, seeded), `coupons`, `files`.

⚠️ The dump is **incomplete** relative to the code. These tables are used by the models but have no DDL in the dump: `network_rir_statistics` (model `NetRir`), `net_rex` (model `NetRex`), the network sync-log table (model `NetRirLog`), `allcode` (model `CodeCheck`), `good_rack` (model `GoodRack`), `goods_rack_info` (model `GoodsRackInfo`). Re-export from the production database before a fresh install, or create them manually to match the models.

Key relationships:

- `goods` ↔ `brand` via `goods.brand`; GTIN is the canonical product key (`sn` unique index).
- `prices` is keyed by (`goods_id`, `shop_id`, `sku`); `goods.low_price` / `goods.high_price` are denormalized min/max across all shops and refreshed on every price upsert.
- Text fields are bilingual pairs: `name_chi` / `name_en`, `descText_chi` / `descText_en`, `address_chi` / `address_en`, etc.
- `goods.type` stores a comma-wrapped category id list (e.g. `,1,2,`); `types.level` 0 = top level, 1 = sub.

## Directory map

```
start.php / windows.php / windows.bat   entry points
config/          server, database, redis, route, process, middleware, …
app/controller/  Index, Goods, Prices, Brands, Shop, Api, Files, NetWork
app/model/       Eloquent models (explicit $table names)
app/helpers/     Ipv4.php, Ipv6.php (IP range math)
app/events/      PricesEvents.php
app/middleware/  StaticFile.php
support/         Controller.php (JSON envelope), bootstrap, helpers, Request/Response
process/         Task.php (daily 06:00 cleanup of orphan uploads), Monitor.php
public/          web root; public/goods/YYYY/Mon/ product uploads; 404.html
priceSystem.sql  database dump (see caveat above)
```

## Background tasks

- `process/Task.php` — every day at 06:00, deletes `files` rows older than 2 days with `use_id = 0` and their orphaned files under `public/goods/`.
- `process/Monitor.php` — watches source dirs and reloads workers on change (dev convenience; disabled with `-d` on some platforms).

## Notes & caveats

- There is **no authentication middleware** on any route. Do not expose this publicly without adding auth.
- `config/app.php` ships with `debug: true` — disable for production.
- The image CDN host `img.goods.acghx.net` is hardcoded in controllers and routes; the search behavior (Jieba tokenization, English word-splitting, GTIN fast path) is documented in `AGENTS.md`.
- `Test.php` model and `Coupons.php` model exist but have no routes yet.
- Price update uses `PUT`, deletes use `POST /…/delete/{id}` — follow the existing convention when adding endpoints.

## License

MIT (see `LICENSE`).
