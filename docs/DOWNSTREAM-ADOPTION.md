# Downstream adoption guide

This guide shows how common cinema systems can project their public data into
CinemaShowtimes-JSON 1.1 without coupling the standard to a particular product
or deployment.

## Shared rules

- Allocate stable public IDs independently of database primary keys.
- Treat the scheduling/POS source as authoritative for sessions, offers and
  availability.
- Treat the facility system as authoritative for cinema, auditorium,
  accessibility and seat layouts.
- Treat licensed distributor metadata as authoritative for film artwork,
  classifications, release dates and advertising rights.
- Resolve conflicts by field-level provenance and update time, not by replacing
  the entire entity from whichever source ran last.
- Publish only the public projection of each model. Never serialize a database
  row wholesale.

## Projection and presentation systems

Systems that inspect or manage presented content should publish only safe,
consumer-relevant facts through the session `presentation` object:

| Source concept | CinemaShowtimes field |
| --- | --- |
| CPL UUID | `presentation.cpl_id` |
| Content title / DCNC | `presentation.dcnc_title` |
| Content version | `presentation.content_version_id` |
| Territory | `presentation.release_territory` |
| Edit rate | `presentation.frame_rate` |
| Resolution / aspect | `presentation.resolution`, `aspect_ratio` |
| Stereoscopic | `presentation.three_d` |
| Sound configuration | `presentation.sound_configuration` |
| Language/caption data | `presentation.language`, subtitle/caption arrays |

Do not export encryption state, KDMs, certificates, device addresses, storage
locations or operational diagnostics.

## Scheduling and point-of-sale systems

Recommended ownership:

| Source concept | CinemaShowtimes field |
| --- | --- |
| Facility and Google Place ID | `cinema` |
| Screen and seat count | `auditoria[]` |
| Film IDs, synopsis and credits | `films[]` |
| Advertised/preshow/feature/cleanup | canonical session time fields |
| Allocated/open seating | `session.seating_mode` |
| Public/private show type | `session.session_type` |
| POS/web channels | `session.sales_channels` |
| Price card rows | `session.offers[]` |
| Open/closed/planned | `session.status` plus `availability.status` |
| Available/held/sold/house | public aggregate `availability`; detailed live endpoint only where safe |

`gross`, `net`, refunds, comps and patron-linked sales are internal analytics,
not public feed data.

## Facility and content services

Recommended ownership:

| Source concept | CinemaShowtimes field |
| --- | --- |
| Facility aliases and contact data | `cinema.identifiers`, address/contact fields |
| Auditorium geometry/capabilities | `auditoria[].screen`, `presentation_capabilities` |
| Accessibility equipment | cinema/auditorium `accessibility` |
| Regional rating and release | `film.classifications[]`, `releases[]` |
| Poster/trailer/motion-poster renditions | `film.media[]` with distinct roles |
| MIME, bytes, hash, duration | media technical fields and extensions |
| Approval/licence state | `media.rights` |
| Source and retrieval time | entity `provenance` and `updated_at` |

Motion posters use `kind: "video"` and `role: "motion_poster"`. Use the existing
URL, MIME type, dimensions, duration, hash and rights fields. Omit the entry when
no asset is available; do not relabel a static poster or assume a trailer is a
motion poster. Keep the static poster available for consumers that cannot play
the video. See the [motion-poster contract](../SPECIFICATION.md#motion-posters).

## Suggested rollout

1. Create a shared ID registry and field-level source-of-truth table.
2. Build read-only 1.1 projections in each system with fixture tests.
3. Join them at a clearly owned aggregation boundary.
4. Validate every produced feed against the schema.
5. Publish canonical cinema, film and session pages plus JSON-LD.
6. Add conditional GET, full and delta feeds, then live availability.
7. Add seat maps after stable seat IDs are agreed across POS and facility data.
8. Add social-catalog and MCP/OpenAPI adapters as separate projections.
