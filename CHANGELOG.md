# Changelog

## 1.1 implementation update - 2026-08-01

- Upgraded the Veezi converter to 1.1 using the documented Film, Session,
  Screen and WebSession APIs.
- Upgraded VenueMaster to 1.1 with correct timezone-aware timestamps,
  cancellations, auditorium accessibility, price-group offers and safe
  availability handling.
- Added a Vista Digital Platform OCAPI converter for sites, localized films,
  screens and business-date showtimes.
- Added shared dependency-free PHP mapping and HTTP helpers.
- Upgraded the WordPress plugin to 0.2.0 with strict 1.1 imports, lossless raw
  entity storage, delta tombstones, clean exports, ETag/cache support and
  protected REST permissions.
- Added sanitized Veezi and Vista fixtures plus converter and WordPress
  round-trip compliance tests.
- Extended GitHub Actions to syntax-check PHP and schema-validate every
  generated feed.

## 1.1 - 2026-08-01

- Added a formal JSON Schema Draft 2020-12 definition.
- Added feed identity, publisher, licensing, discovery links, expiration,
  sequence, delta mode and tombstones.
- Added `cinemas` and per-session `cinema_id` for multi-venue aggregators while
  retaining singular `cinema` for simple feeds.
- Added canonical URLs, external identity, provenance and update timestamps.
- Added structured venue address, hours, accessibility and refreshed Place ID
  metadata.
- Added regional film releases/classifications, localization, rich media,
  rights and reusable marketing metadata.
- Added precise screening status/time fields, content presentation, sales
  channels, ticket offers and timestamped live-availability links.
- Added a versioned static seat-map schema with accessible and companion seating.
- Added a read-only OpenAPI 3.1 discovery, delta and availability profile.
- Added Google/Schema.org JSON-LD mapping and search/LLM publishing guidance.
- Retained 1.0 aliases in the 1.1 schema for migration.
- Removed the credential literal from the Veezi example; it now requires the
  `VEEZI_API_KEY` environment variable.

The original root examples remain as 1.0 migration fixtures.
