# CinemaShowtimes-JSON

CinemaShowtimes-JSON is an open, vendor-neutral format for publishing cinema
venues, films, auditoria, screening sessions, ticket offers, accessibility and
availability as consistent machine-readable JSON.

Version 1.1 adds a formal JSON Schema, canonical web identity, provenance and
freshness, media/advertising rights, regional film metadata, precise digital
cinema presentation, a static seat-map format, live-availability links and
guidance for Google, social catalogs and LLM/agent discovery.

- [1.1 specification](SPECIFICATION.md)
- [1.1 JSON Schema](schema/cinemashowtimes-json-1.1.schema.json)
- [1.1 worked example](examples/CinemaShowtimes-1.1-example.json)
- [Seat-map schema](schema/cinemashowtimes-seatmap-1.0.schema.json)
- [Google/Schema.org JSON-LD example](examples/google-screening-event.jsonld)
- [Recommended OpenAPI 3.1 profile](openapi/cinemashowtimes-api-1.1.yaml)
- [Research and design rationale](docs/RESEARCH-AND-DESIGN.md)
- [Downstream adoption guide](docs/DOWNSTREAM-ADOPTION.md)

## Purpose
Cinema operators, distributors, POS vendors, and third-party aggregators all need access to reliable session data — what films are playing, when, where, in what format, and with what seat availability.
Today, this information is often shared through proprietary feeds, custom APIs, or web scraping, leading to inconsistency and integration overhead.

CinemaShowtimes-JSON provides a simple, open, and extensible format for representing:

* Cinema metadata (identity, address, geo, timezone, contact details and hours)
* Film metadata (titles, identifiers, regional releases/classifications and media)
* Auditoria and seat classes (capacity, accessibility and presentation capabilities)
* Screening details (precise times, presentation, offers and live availability)
* Media assets (renditions, captions, transcripts, hashes, rights and social hints)
* Discovery metadata (canonical pages, JSON-LD, OpenAPI, MCP, calendar and deltas)

## Goals
* Interoperability → a neutral, vendor-agnostic schema that works across Vista, Radiant, RTS, Veezi, and custom systems.
* Simplicity → designed to be human-readable, easily parsed by developers, and quick to implement.
* Extensibility → supports additional fields like preshow times, feature start times, or premium seat classes without breaking compatibility.
* Openness → free to use, adapt, and extend under a permissive license (MIT).
* Search readiness → stable public entities that map cleanly to Schema.org and
  can be reconciled with Google Business Profile and Place IDs.

CinemaShowtimes-JSON cannot directly write to Google's Knowledge Graph and does
not guarantee a rich result. It supplies the source facts needed to generate
accurate, crawlable `MovieTheater`, `Movie` and per-session `ScreeningEvent`
JSON-LD.

## Who is it for
* Cinemas & Exhibitors → publish your session data once in a clean, portable format.
* POS Vendors → provide consistent output to your cinema clients and their partners.
* Aggregators & Apps → consume showtimes without guessing at feed structures or scraping HTML.
* Researchers / Analysts → integrate cinema schedules into business intelligence workflows.

## Example Usage
* Powering a cinema website’s showtimes page.
* Generating Google/Schema.org event markup and platform-specific partner feeds.
* Providing feeds to community apps, newsletters, or kiosks.
* Synchronizing schedules between exhibitor POS and third-party services.
* Enabling websites, kiosks, signage, social campaigns and agent search to use a
  unified source.

## Coming Soon support

The same 1.1 schema supports coming-soon feeds containing feed metadata,
`cinema` and `films` while omitting `auditoria` and `sessions`. Use
`status: "coming_soon"`, regional `releases`, and media with explicit usage
rights. Advance-sale sessions and offers should be published normally once they
exist rather than duplicated in a proprietary presales object.

For example, alongside `https://cinemaname.com/showtimes.json`, a publisher can
offer `https://cinemaname.com/comingsoon.json`.

## What you will find in this repository

1. A normative specification, JSON Schemas and validated examples.
2. Tested PHP converters for Veezi, VenueMaster and Vista Digital Platform.
3. A WordPress plugin that losslessly imports, edits and exports 1.1 feeds.
4. Fixture-based schema, semantic, converter and WordPress round-trip tests.


## Basic example

The `examples` directory contains the current 1.1 examples. The two
`CinemaShowtimes-full-example*` files at the repository root document the legacy
1.0 format.

A minimal 1.1 example is below.

```json
{
  "spec": "cinemashowtimes-json/1.1",
  "feed_id": "example-cinema",
  "mode": "full",
  "generated_at": "2026-08-01T12:00:00Z",
  "expires_at": "2026-08-01T12:15:00Z",
  "publisher": {
    "name": "Example Cinemas",
    "url": "https://examplecinemas.com/"
  },
  "cinema": {
    "cinema_id": "EXC-AUS-NOWRA",
    "name": "Example Cinemas Name",
    "timezone": "Australia/Sydney",
    "canonical_url": "https://examplecinemas.com/cinemas/nowra"
  },
  "films": [
    {
      "film_id": "FILM-AVATAR3",
      "title": "Avatar: Fire and Ash",
      "canonical_url": "https://examplecinemas.com/movies/avatar-fire-and-ash",
      "runtime_minutes": 190,
      "classifications": [
        {"territory": "AU", "system": "Australian Classification", "code": "M"}
      ]
    }
  ],
  "sessions": [
    {
      "session_id": "EXC-NOWRA-2026-08-18-1930-S1-AVATAR3-2D",
      "cinema_id": "EXC-AUS-NOWRA",
      "film_id": "FILM-AVATAR3",
      "canonical_url": "https://examplecinemas.com/sessions/EXC-NOWRA-2026-08-18-1930-S1-AVATAR3-2D",
      "status": "scheduled",
      "start_time": "2026-08-18T19:30:00+10:00",
      "presentation": {"video_format": "2D", "language": "en-AU"},
      "availability": {
        "status": "on_sale",
        "seats_available": 128,
        "updated_at": "2026-08-01T11:59:40Z"
      }
    }
  ]
}
```

## Conversion tools

The [conversion guide](conversion-tools/README.md) covers:

- [Veezi implementation](conversion-tools/Veezi/README.md), using `v4/film`,
  `v1/session`, `v1/screen` and `v1/websession`;
- [VenueMaster implementation](conversion-tools/VenueMaster/README.md), using
  VenueSchedule 7.5 including ticket price groups; and
- [Vista Digital Platform implementation](conversion-tools/Vista/README.md),
  using OCAPI sites, films, screens and business-date showtimes.

All three emit 1.1, use environment-based credentials and have sanitized
fixtures that are generated and validated in CI. Additional POS converters are
only marked supported when an official contract and test fixture are available.

## WordPress

The [WordPress plugin](wordpress-plugin/README.md) now strictly imports 1.1,
preserves complete entities in `raw_payload`, indexes common editing fields,
applies delta tombstones and exports a validated full feed at:

```text
/wp-json/cine/v1/showtimes
```

Public discovery can be enabled or disabled. Protected imports and private
reads use the `X-CINE-API` header rather than query-string credentials.

## Validation

Install `jsonschema`, then validate the shipped examples:

```sh
python3 -m pip install -r requirements-dev.txt
python3 tools/validate_examples.py

output_dir="$(mktemp -d)"
php tests/php/generate-converter-fixtures.php "$output_dir"
php tests/php/wordpress-roundtrip.php "$output_dir/wordpress-1.1.json"
python3 tools/validate_examples.py \
  --feed "$output_dir/veezi-1.1.json" \
  --feed "$output_dir/venuemaster-1.1.json" \
  --feed "$output_dir/vista-1.1.json" \
  --feed "$output_dir/wordpress-1.1.json"
```

## Proponents

CinemaShowtimes-JSON is supported and promoted by individuals and organizations in the cinema technology and exhibition space.

**Current proponents include:**
- **Digital Cinema Network Pty Ltd** (https://dcnaustralia.com.au)
- **James Gardiner** (Director, Digital Cinema Network Pty Ltd, owner of repository)
