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

## API implementation options

The JSON format does not require authentication. A public discovery service may
serve `/showtimes` anonymously. A partner or private multi-cinema service may
instead use OAuth 2.0 bearer tokens while keeping cinema selection explicit:

```http
GET /showtimes?cinema_id=EXC-AUS-PMELB
Authorization: Bearer <access-token>
```

The token identifies the caller and limits which cinemas it may access;
`cinema_id` identifies what the caller is requesting. Implementations must
authorize every requested cinema or cinema-owned resource. A token restricted
to one cinema may support an omitted selector, but a token covering several
cinemas must never cause an arbitrary cinema to be chosen.

See the
[optional protected multi-cinema API profile](SPECIFICATION.md#optional-protected-multi-cinema-api-profile)
and the [OpenAPI 3.1 example](openapi/cinemashowtimes-api-1.1.yaml).

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
/wp-json/cinemashowtimes/v1/showtimes
```

Public discovery can be enabled or disabled. Protected imports and private
reads use the `X-CINEMASHOWTIMES-API` header rather than query-string credentials.

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

## Complete example

The following complete feed demonstrates the major 1.1 capabilities together:
stable identities, freshness and licensing, discovery links, Google Place
reconciliation, structured venue data, accessibility, regional film facts,
credits, media rights, social campaign metadata, auditorium capabilities, seat
maps, precise presentation and timing, ticket offers, booking links, aggregate
availability and provenance.

It is also available as the schema-validated
[`examples/CinemaShowtimes-1.1-example.json`](examples/CinemaShowtimes-1.1-example.json)
file. Delta feeds, tombstones, multi-cinema feeds and coming-soon feeds use the
same entities but are separate document modes described in the
[specification](SPECIFICATION.md).

```json
{
  "spec": "cinemashowtimes-json/1.1",
  "feed_id": "example-cinemas-port-melbourne",
  "mode": "full",
  "sequence": 1842,
  "generated_at": "2026-08-01T10:00:03Z",
  "expires_at": "2026-08-01T10:15:03Z",
  "ttl_seconds": 900,
  "language": "en-AU",
  "license": "https://creativecommons.org/licenses/by/4.0/",
  "canonical_url": "https://examplecinemas.com/showtimes.json",
  "publisher": {
    "organization_id": "EXAMPLE-CINEMAS",
    "name": "Example Cinemas",
    "url": "https://examplecinemas.com/",
    "logo_url": "https://img.examplecinemas.com/brand/logo.png",
    "same_as": [
      "https://www.instagram.com/examplecinemas/",
      "https://www.youtube.com/@examplecinemas"
    ]
  },
  "links": [
    {
      "rel": "self",
      "href": "https://examplecinemas.com/showtimes.json",
      "type": "application/json"
    },
    {
      "rel": "schema",
      "href": "https://cinemashowtimes.org/schema/cinemashowtimes-json-1.1.schema.json",
      "type": "application/schema+json"
    },
    {
      "rel": "delta",
      "href": "https://examplecinemas.com/showtimes/changes?since=1842",
      "type": "application/json"
    },
    {
      "rel": "openapi",
      "href": "https://examplecinemas.com/.well-known/openapi.json",
      "type": "application/vnd.oai.openapi+json;version=3.1"
    },
    {
      "rel": "mcp",
      "href": "https://examplecinemas.com/mcp",
      "type": "application/json"
    },
    {
      "rel": "calendar",
      "href": "https://examplecinemas.com/showtimes.ics",
      "type": "text/calendar"
    }
  ],
  "cinema": {
    "cinema_id": "EXC-AUS-PMELB",
    "name": "Example Cinemas Port Melbourne",
    "identifiers": {
      "operator_site_id": "PORT-MELBOURNE"
    },
    "canonical_url": "https://examplecinemas.com/cinemas/port-melbourne",
    "timezone": "Australia/Melbourne",
    "operator": {
      "organization_id": "EXAMPLE-CINEMAS",
      "name": "Example Cinemas",
      "url": "https://examplecinemas.com/"
    },
    "location": {
      "latitude": -37.833,
      "longitude": 144.933
    },
    "postal_address": {
      "street_address": "123 Example Street",
      "locality": "Port Melbourne",
      "region": "VIC",
      "postal_code": "3207",
      "country": "AU",
      "formatted": "123 Example Street, Port Melbourne VIC 3207, Australia"
    },
    "phone": "+61 3 5555 1234",
    "email": "cinema@examplecinemas.com",
    "website": "https://examplecinemas.com/cinemas/port-melbourne",
    "google_place_id": "ChIJN1t_tDeuEmsRUsoyG83frY4",
    "google_place_id_verified_at": "2026-07-15T00:00:00Z",
    "same_as": [
      "https://www.google.com/maps/search/?api=1&query_place_id=ChIJN1t_tDeuEmsRUsoyG83frY4&query=Example%20Cinemas"
    ],
    "opening_hours": [
      {
        "days": ["mo", "tu", "we", "th", "fr", "sa", "su"],
        "opens": "10:00",
        "closes": "23:59"
      }
    ],
    "special_hours": [
      {
        "date": "2026-12-25",
        "closed": true
      }
    ],
    "accessibility": {
      "wheelchair_spaces": true,
      "companion_seating": true,
      "step_free_access": true,
      "assistive_listening": true,
      "hearing_loop": true,
      "notes": "Contact the cinema before arrival to reserve accessibility equipment.",
      "confirmation_url": "https://examplecinemas.com/accessibility"
    },
    "updated_at": "2026-07-31T04:00:00Z",
    "provenance": {
      "source_id": "EXAMPLE-CINEMAS-POS",
      "source_name": "Example Cinemas",
      "method": "authoritative",
      "updated_at": "2026-07-31T04:00:00Z"
    }
  },
  "films": [
    {
      "film_id": "FILM-EXAMPLE-001",
      "title": "The Example Feature",
      "canonical_url": "https://examplecinemas.com/movies/the-example-feature",
      "description": "A fictional feature used to demonstrate portable cinema metadata.",
      "short_description": "A fictional feature for demonstrating the standard.",
      "alt_titles": ["Example Feature"],
      "same_as": [
        "https://www.imdb.com/title/tt12345678/"
      ],
      "identifiers": {
        "eidr": "10.5240/ABCD-1234-EF56-7890-0000-X",
        "imdb": "tt12345678",
        "tmdb": "123456"
      },
      "runtime_minutes": 118,
      "status": "now_showing",
      "original_language": "en",
      "languages": ["en"],
      "localized": {
        "fr": {
          "title": "Le long métrage exemple",
          "short_description": "Un long métrage fictif illustrant la norme."
        }
      },
      "genres": ["Drama", "Adventure"],
      "keywords": ["Australian cinema", "adventure"],
      "classifications": [
        {
          "territory": "AU",
          "system": "Australian Classification",
          "code": "M",
          "consumer_advice": "Moderate themes",
          "minimum_age": 15,
          "source_url": "https://classification.example/films/FILM-EXAMPLE-001"
        }
      ],
      "releases": [
        {
          "territory": "AU",
          "date": "2026-07-30",
          "type": "theatrical",
          "distributor": "Example Distribution"
        }
      ],
      "credits": {
        "directors": [
          {
            "name": "Jane Example",
            "role": "Director",
            "identifiers": { "imdb": "nm0000001" },
            "same_as": ["https://www.imdb.com/name/nm0000001/"]
          }
        ],
        "cast": [
          {
            "name": "Alex Example",
            "character": "Sam",
            "order": 0
          }
        ]
      },
      "media": [
        {
          "media_id": "FILM-EXAMPLE-001-POSTER",
          "kind": "image",
          "role": "poster",
          "url": "https://img.examplecinemas.com/movies/example-poster-1000x1500.jpg",
          "mime_type": "image/jpeg",
          "width": 1000,
          "height": 1500,
          "sha256": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
          "alt_text": "Poster for The Example Feature",
          "primary": true,
          "platform_hints": ["web", "google", "instagram", "pinterest"],
          "updated_at": "2026-07-31T23:40:00Z",
          "rights": {
            "holder": "Example Distribution",
            "credit": "Courtesy of Example Distribution",
            "license_url": "https://example-distribution.test/licences/FILM-EXAMPLE-001",
            "allowed_uses": ["web", "search", "social", "paid_social"],
            "territories": ["AU"],
            "valid_from": "2026-07-01T00:00:00Z",
            "valid_until": "2027-01-31T13:00:00Z"
          }
        },
        {
          "media_id": "FILM-EXAMPLE-001-TRAILER",
          "kind": "video",
          "role": "trailer",
          "url": "https://video.examplecinemas.com/example-trailer.mp4",
          "mime_type": "video/mp4",
          "width": 1920,
          "height": 1080,
          "duration_seconds": 128,
          "language": "en",
          "caption_url": "https://video.examplecinemas.com/example-trailer-en.vtt",
          "transcript_url": "https://examplecinemas.com/movies/the-example-feature/trailer-transcript",
          "platform_hints": ["web", "google", "facebook", "instagram", "tiktok", "youtube"],
          "updated_at": "2026-07-31T23:40:00Z",
          "rights": {
            "holder": "Example Distribution",
            "allowed_uses": ["web", "search", "social", "paid_social"],
            "territories": ["AU"]
          }
        }
      ],
      "marketing": {
        "headline": "See it on the big screen",
        "call_to_action": "Book tickets",
        "hashtags": ["TheExampleFeature", "ExampleCinemas"],
        "audience_tags": ["adventure", "Australian film"],
        "campaign_labels": {
          "tiktok_catalog_id": "FILM-EXAMPLE-001",
          "meta_content_id": "FILM-EXAMPLE-001"
        }
      },
      "updated_at": "2026-07-31T23:40:00Z",
      "provenance": {
        "source_name": "Example Distribution",
        "source_url": "https://distributor.example/movies/example-feature",
        "retrieved_at": "2026-07-31T23:45:00Z",
        "method": "licensed"
      }
    }
  ],
  "auditoria": [
    {
      "auditorium_id": "EXC-PMELB-S1",
      "name": "Cinema 1",
      "seat_count": 210,
      "seating_mode": "reserved",
      "attributes": ["Laser projection", "Premium seating"],
      "accessibility": {
        "wheelchair_spaces": true,
        "companion_seating": true,
        "closed_captions": true,
        "open_captions": true,
        "audio_description": true,
        "assistive_listening": true,
        "hearing_loop": true,
        "sign_language": false,
        "step_free_access": true,
        "notes": "Wheelchair positions are shown in the linked seat map."
      },
      "screen": {
        "width": 14.2,
        "unit": "m",
        "native_aspect_ratio": "2.39:1",
        "masking": "side"
      },
      "presentation_capabilities": {
        "video": ["2K", "4K", "2D", "3D", "HFR"],
        "audio": ["5.1", "7.1", "Dolby Atmos"],
        "three_d": true,
        "high_frame_rate": true,
        "immersive_audio": true
      },
      "seat_classes": [
        {
          "class_id": "STD",
          "display_name": "Standard",
          "features": ["Padded"]
        },
        {
          "class_id": "PRM",
          "display_name": "Premium Recliner",
          "features": ["Recliner", "Extra legroom"]
        }
      ],
      "seatmap": {
        "layout_url": "https://examplecinemas.com/api/auditoria/EXC-PMELB-S1/seatmap",
        "image_url": "https://img.examplecinemas.com/seatmaps/EXC-PMELB-S1-v3.svg",
        "version": "3",
        "updated_at": "2026-06-10T00:00:00Z"
      },
      "updated_at": "2026-06-10T00:00:00Z"
    }
  ],
  "sessions": [
    {
      "session_id": "EXC-PMELB-20260801-1930-S1-FILM001",
      "cinema_id": "EXC-AUS-PMELB",
      "film_id": "FILM-EXAMPLE-001",
      "auditorium_id": "EXC-PMELB-S1",
      "canonical_url": "https://examplecinemas.com/sessions/EXC-PMELB-20260801-1930-S1-FILM001",
      "status": "scheduled",
      "start_time": "2026-08-01T19:30:00+10:00",
      "door_time": "2026-08-01T19:00:00+10:00",
      "advertised_start_time": "2026-08-01T19:30:00+10:00",
      "preshow_start_time": "2026-08-01T19:20:00+10:00",
      "feature_start_time": "2026-08-01T19:45:00+10:00",
      "credits_start_time": "2026-08-01T21:28:00+10:00",
      "end_time": "2026-08-01T21:43:00+10:00",
      "session_type": "public",
      "seating_mode": "reserved",
      "sales_channels": ["box_office", "web", "app", "kiosk", "partner"],
      "special_events": ["opening_week"],
      "presentation": {
        "content_version_id": "urn:uuid:00000000-0000-0000-0000-000000000001",
        "cpl_id": "urn:uuid:00000000-0000-0000-0000-000000000002",
        "dcnc_title": "ExampleFeature_FTR-2D_S_EN-XX_AU-M_51_2K_EXM_20260731_EXM_IOP_OV",
        "release_territory": "AU",
        "video_format": "2D",
        "marketing_name": "Laser",
        "aspect_ratio": "2.39:1",
        "resolution": "2K",
        "frame_rate": 24,
        "three_d": false,
        "audio_format": "5.1",
        "sound_configuration": "L,C,R,LFE,Ls,Rs",
        "immersive_audio": false,
        "language": "en",
        "subtitle_languages": [],
        "caption_languages": ["en"]
      },
      "accessibility": {
        "wheelchair_spaces": true,
        "companion_seating": true,
        "closed_captions": true,
        "open_captions": false,
        "audio_description": true,
        "assistive_listening": true,
        "caption_languages": ["en"],
        "audio_description_languages": ["en"],
        "confirmation_url": "https://examplecinemas.com/sessions/EXC-PMELB-20260801-1930-S1-FILM001#accessibility"
      },
      "offers": [
        {
          "offer_id": "EXC-PMELB-20260801-1930-S1-FILM001-ADULT-STD",
          "name": "Adult standard",
          "category": "Adult",
          "class_id": "STD",
          "price": 19.5,
          "currency": "AUD",
          "fees_included": true,
          "availability": "in_stock",
          "eligibility": "Adults aged 18 and over",
          "valid_from": "2026-07-20T09:00:00+10:00",
          "valid_until": "2026-08-01T19:30:00+10:00",
          "minimum_quantity": 1,
          "maximum_quantity": 10,
          "booking_url": "https://tickets.examplecinemas.com/checkout?session=EXC-PMELB-20260801-1930-S1-FILM001"
        }
      ],
      "availability": {
        "status": "on_sale",
        "updated_at": "2026-08-01T09:59:40Z",
        "seats_total": 210,
        "seats_available": 88,
        "availability_url": "https://examplecinemas.com/api/sessions/EXC-PMELB-20260801-1930-S1-FILM001/availability"
      },
      "booking_url": "https://tickets.examplecinemas.com/checkout?session=EXC-PMELB-20260801-1930-S1-FILM001",
      "updated_at": "2026-08-01T09:59:40Z",
      "provenance": {
        "source_id": "EXAMPLE-CINEMAS-POS",
        "source_name": "Example Cinemas point of sale",
        "retrieved_at": "2026-08-01T10:00:01Z",
        "method": "authoritative"
      }
    }
  ],
  "extensions": {
    "example": {
      "source_profile": "public-discovery",
      "notes": "Publisher-specific data belongs in a namespaced extension."
    }
  }
}
```

## Proponents

CinemaShowtimes-JSON is supported and promoted by organizations in the cinema
technology and exhibition space.

Current proponents:

- [d-cine.net](https://d-cine.net/)
- [Digital Cinema Network](https://dcnaustralia.com.au/)
