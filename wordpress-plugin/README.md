# CinemaShowtimes-JSON 1.1 WordPress plugin

`cinemashowtimes-json` imports, stores, edits and exports
`cinemashowtimes-json/1.1`.

## What changed in 0.3.0

- Uses the neutral CinemaShowtimes-JSON name for the plugin directory, PHP
  types, WordPress hooks, settings, menus, REST namespace and API-key header.
- Removes deployment-specific host and filesystem assumptions from the example
  deployment script.

Version 0.2.0 added the 1.1 data model:

- Strictly accepts 1.1 full and delta feeds.
- Preserves the complete raw JSON for every film, auditorium and session while
  indexing useful fields in WordPress tables.
- Round-trips canonical URLs, regional classifications/releases, media,
  provenance, presentation, accessibility, offers, availability and extensions.
- Imports cinema/feed/publisher/licence/freshness metadata.
- Supports multi-cinema feed metadata and per-session `cinema_id`.
- Reconciles collections present in a full feed while retaining records marked
  read-only; omitted optional collections are left untouched.
- Applies delta tombstones without deleting records marked read-only.
- Exports a schema-valid full feed rather than database columns.
- Provides public or key-protected REST reads with ETag and cache headers.
- Uses header-only API keys and WordPress REST permission callbacks.
- Adds nonce protection to manual fetch and corrects custom WP-Cron intervals.

## Installation and database upgrade

Copy `cinemashowtimes-json` to `wp-content/plugins` and activate it. A
database-version check uses `dbDelta()` to add the 1.1 columns and lossless
`raw_payload` columns without removing the original indexed fields, including
when an already-active installation is upgraded.

Version 0.3.0 uses new neutral WordPress option, hook and route identifiers.
After replacing an earlier experimental build, reactivate the plugin, review
its settings and update clients to the endpoints and header below. Existing
indexed feed tables are retained. Back up the WordPress database before
upgrading an existing installation.

## REST endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/wp-json/cinemashowtimes/v1/import` | Import a 1.1 full or delta feed; requires the import `X-CINEMASHOWTIMES-API` key |
| `GET` | `/wp-json/cinemashowtimes/v1/showtimes` | Canonical local 1.1 feed |
| `GET` | `/wp-json/cinemashowtimes/v1/export` | Compatibility alias for the local feed |
| `GET` | `/wp-json/cinemashowtimes/v1/films/{film_id}` | One film entity |
| `GET` | `/wp-json/cinemashowtimes/v1/sessions/{session_id}` | One session entity |

Public discovery reads are enabled by default and can be disabled in Settings.
When disabled, send the configured local key in `X-CINEMASHOWTIMES-API`. API
keys are not accepted in query strings, to reduce leakage through logs and
referrers.

The included `deploy_to_staging.sh` is optional. It requires
`WP_DEPLOY_USER`, `WP_DEPLOY_HOST` and `WP_DEPLOY_PATH`; it contains no
project-specific host, account or filesystem defaults.

Example import:

```sh
curl --request POST \
  --header 'Content-Type: application/json' \
  --header 'X-CINEMASHOWTIMES-API: replace-with-import-key' \
  --data-binary @examples/CinemaShowtimes-1.1-example.json \
  'https://cinema.example.com/wp-json/cinemashowtimes/v1/import'
```

## Storage model

The database uses two complementary representations:

1. `raw_payload` retains the complete validated entity for lossless future
   compatibility.
2. Indexed columns support admin lists and common edits.

During export, indexed edits are overlaid on the original entity. WordPress
primary keys, timestamps and read-only flags are never exposed in the feed.
Legacy 1.0 JSON stored in older indexed columns is exported under its legacy
alias rather than being mislabelled as a 1.1 object.

Optional motion posters are preserved in `film.media[]` using
`kind: "video"` and `role: "motion_poster"`, including their URL, dimensions,
duration and rights. This uses the existing lossless media storage and does
not require a database migration or an asset to be present. The reference
plugin does not generate, download or play the video. Its round-trip harness
checks that the motion-poster entry survives import/export unchanged.

## Validation

The repository's test harness imports the complete 1.1 example into a minimal
WordPress-compatible in-memory store and validates the exported round trip:

```sh
roundtrip="$(mktemp)"
php tests/php/wordpress-roundtrip.php "$roundtrip"
python3 tools/validate_examples.py --feed "$roundtrip"
```

The harness is not a replacement for testing against the exact WordPress, PHP
and database versions used in production.
