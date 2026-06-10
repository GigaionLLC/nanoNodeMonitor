# AGENT.md — Nano Node Monitor

Guidance for AI coding agents and human contributors working on this repository.
This file is the single source of truth for project conventions; `CLAUDE.md` simply points here.

## What this project is

Nano Node Monitor is a small, dependency-free, server-side PHP web app that monitors a
[Nano](https://nano.org) (or Banano / PAW) node. It talks to the node's JSON-RPC interface
over localhost, aggregates status data, and serves it as JSON to a self-refreshing,
Handlebars-rendered status page. Because all RPC calls happen server-side, the node's RPC
port is never exposed to the public.

This is the GigaionLLC fork of the archived NanoTools/nanoNodeMonitor, modernized for
PHP 8.1–8.5.

## Stack and constraints

- **PHP 8.1–8.5**, plain procedural PHP. No framework, no Composer, no autoloader —
  files are pulled in with `require_once` via `modules/includes.php`. Keep it that way
  unless explicitly asked otherwise.
- **php-curl** is the only required extension (checked at startup in `includes.php`).
- Frontend is static, vendored JS (axios, Handlebars 4.7.7, bootstrap-native, clipboard.js)
  with no build step. Do not introduce npm/bundlers.
- Deployed either on a plain Apache/PHP webroot or via the Docker image
  (`php:8.5-apache`, see `Dockerfile` + `entry.sh`).

## Repository layout

```
index.php               Status page shell (PHP-rendered header/banner/footer)
api.php                 JSON API: queries the node RPC, caches, returns one data object
modules/
  includes.php          Bootstrap: constants -> defaults -> config.php -> functions -> cache
  constants.php         PROJECT_VERSION, project URLs, timeouts, display constants
  defaults.php          Default values for every config variable (do not edit per-install)
  config.sample.php     Template for the operator's config.php (config.php is gitignored)
  functions.php         Helpers: formatting, escaping (e()), GitHub/uptimerobot lookups
  functions_rpc.php     One thin wrapper per node RPC action (postCurl + action name)
  Cache.php             Abstract Cache + factory ('apc(u)', 'files', 'redis', default null)
  cache/                ApcuCache, FileCache (JSON on disk), RedisCache, NullCache
  header.php/navbar.php/footer.php/widget.php   PHP-rendered page fragments
templates/index.hbs     Handlebars template rendered client-side with api.php data
static/                 Vendored CSS/JS, themes, images (no build step)
Dockerfile, entry.sh    Docker image; entry.sh symlinks /opt/nanoNodeMonitor/config.php
```

## Architecture / request flow

1. **Page load** — `index.php` includes `modules/includes.php` (which layers
   `constants.php` → `defaults.php` → operator `config.php`), then renders the static
   page shell. No node data is fetched here.
2. **Polling** — `static/js/index.js` fetches `templates/index.hbs`, compiles it, then
   polls `api.php` every `$autoRefreshInSeconds` (exposed to JS as `GLOBAL_REFRESH`)
   and re-renders `#content` with the JSON response.
3. **API** — `api.php` builds one `stdClass` payload inside a `Cache::fetch()` closure:
   node version, block counts, peers, confirmation-time percentiles, account
   balance/representative/weight, host system load/memory/uptime, telemetry, and sync
   percentage. Cache TTL (default 30 s) shields the node from request bursts.
4. **RPC layer** — `functions_rpc.php` has one function per RPC action; all go through
   `postCurl()`, which POSTs JSON to `http://$nanoNodeRPCIP:$nanoNodeRPCPort` and dies
   with an HTTP 503 (`myError()`) when the node is unreachable.
5. **External lookups** (each cached in a 10-minute `FileCache`): latest monitor release
   and latest node release from the GitHub API; optional uptime ratio from uptimerobot.

### Configuration model

Every setting has a default in `defaults.php`; operators override variables in
`modules/config.php` (gitignored, created from `config.sample.php`). When adding a
setting: add the default to `defaults.php`, document it commented-out in
`config.sample.php`, and never read it before `includes.php` ran.

## Security conventions

These were deliberate hardening decisions — do not regress them:

- **Never use `unserialize()` on cache or request data.** `FileCache` stores JSON
  (a malicious serialized payload in a predictable cache path would otherwise allow
  PHP object injection). `RedisCache` also uses JSON.
- **Escape all config/host-derived values in templates** with the `e()` helper
  (`htmlspecialchars`, ENT_QUOTES). Account values embedded in third-party image URLs
  are additionally `rawurlencode()`d. Values echoed into inline JS use `(int)` casts or
  `json_encode()`. Exception: `$welcomeMsg` is intentionally raw HTML (operator-trusted).
- Client-side rendering must stay on escaped Handlebars `{{ }}` expressions — no `{{{ }}}`.
- `api.php` sends `Access-Control-Allow-Origin: *` **by design** (public monitor data,
  consumed by community aggregators) plus `X-Content-Type-Options: nosniff`.
- RPC reads from the node must tolerate error payloads: always `?? default` when reading
  response properties; `postCurl()` may also return `null` on invalid JSON.
- Raw amounts (1 nano = 10^30 raw) exceed `PHP_INT_MAX` — keep them as **strings**,
  never cast to int.

## PHP 8.5 compatibility notes

- Do not call `curl_close()` (no-op since 8.0, deprecated in 8.5 — its notice breaks
  later `header()` calls).
- No `array_key_exists()` on objects, no `property_exists()` on possibly-null values,
  no string functions on possibly-null arguments, no float array indexes.
- System helpers (`getSystemLoadAvg`, `getSystemMemInfo`, `getSystemUptime`) must return
  zeroed values, not `NULL`, on non-Linux hosts.

## Verifying changes

There is no test suite. Lint and smoke-test against a mock node RPC:

```sh
# lint everything
find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
```

Smoke test: write a small PHP script that returns canned JSON for the RPC actions used in
`api.php` (`version`, `block_count`, `peers`, `confirmation_history`, `account_balance`,
`account_info`, `account_weight`, `stats`, `uptime`, `active_difficulty`, `telemetry`),
serve it with `php -S 127.0.0.1:17076`, point a local `modules/config.php` at it, serve
the repo with `php -S 127.0.0.1:18080 -t .` and check:

- `GET /api.php` returns valid JSON with **no** `Warning`/`Deprecated` output,
- `GET /index.php` renders without PHP notices,
- with the mock stopped, `/api.php` returns a clean HTTP 503 ("Node is not running"),
- with `$cache = ["engine" => "files", ...]`, a second request is served from cache.

## Release conventions

- Bump `PROJECT_VERSION` in `modules/constants.php`; it is also the cache-busting query
  string for all static assets.
- Keep `README.md` (user-facing install/config docs) in sync with any config changes.
- One logical change per commit, imperative subject line.
