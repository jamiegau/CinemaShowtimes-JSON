# CinemaShowtimes-JSON 1.1 conversion tools

These dependency-free PHP examples convert supported cinema POS responses into
validated `cinemashowtimes-json/1.1` full feeds. Mapping logic is separated from
HTTP wrappers so it can be tested without live credentials.

## Implemented converters

| POS | Source | Entry point | Coverage |
| --- | --- | --- | --- |
| Veezi | [Official Veezi API](https://api.us.veezi.com/help/) | `Veezi/veezi-to-CST-JSON.php` | Films, people, media, screens, exact session times, seating mode, sales channels, booking URLs and aggregate availability |
| VenueMaster | Supplied VenueSchedule 7.5 guide/feed | `VenueMaster/venue-to-CST-JSON.php` | Installation, films, ratings, auditoria, accessibility, cancellations, price groups/offers, booking URLs and availability |
| Vista Digital Platform | [Official OCAPI](https://developer.vista.co/openapi/digital-platform/openapi) | `Vista/vista-digital-to-CST-JSON.php` | Site, localized films, ratings, genres, people, showtimes, screens, presentation attributes and sold-out state |

The common helpers are in `lib/cinemashowtimes-1.1.php`. Pure mapping functions
are in each vendor's `*-mapper.php` file.

## Security and deployment

Never commit POS credentials or API keys. Configure secrets in the PHP runtime
or a secret manager, keep converters outside a public document root where
possible, and put authentication or a trusted cache in front of feeds that are
not licensed for unrestricted redistribution.

Wrappers:

- require HTTPS source URLs;
- verify TLS certificates;
- use bounded request timeouts;
- send credentials in headers, never query strings;
- return generic RFC 9457-style problem responses without leaking upstream
  bodies or credentials;
- emit cache lifetime consistent with the feed TTL.

## Veezi

Edit the public cinema metadata in `Veezi/veezi-to-CST-JSON.php`, then configure:

```sh
export VEEZI_API_KEY='provided-by-the-cinema'
export VEEZI_API_BASE='https://api.oz.veezi.com'
```

Veezi access tokens belong to one site. The converter calls:

- `GET /v4/film`
- `GET /v1/session`
- `GET /v1/screen`
- `GET /v1/websession`

The WebSession call supplies direct public booking URLs. Veezi's public API does
not expose complete ticket prices, seat geometry or per-seat live state, so the
converter does not invent them. Multi-film packages are omitted until the
optional FilmPackage resource is joined to a stable public film-package model.
See the official
[authentication](https://api.us.veezi.com/help/Authentication),
[film](https://api.us.veezi.com/help/Film),
[session](https://api.us.veezi.com/help/Sessions),
[screen](https://api.us.veezi.com/help/Screen) and
[web-session](https://api.us.veezi.com/help/WebSession) references.

## VenueMaster

Edit the cinema metadata in `VenueMaster/venue-to-CST-JSON.php`, then configure
the HTTPS VenueSchedule endpoint:

```sh
export VENUEMASTER_SCHEDULE_URL='https://pos.example.test/VenueSchedule.json'
```

For local testing:

```sh
cd conversion-tools/VenueMaster
VENUEMASTER_SCHEDULE_URL=venue-schedule-example.json php venue-to-CST-JSON.php
```

TLS verification is always enabled. Do not expose a POS server directly by
disabling certificate checks or broad router port forwarding; publish through a
secured reverse proxy or copy the schedule to a controlled integration host.

Ticket offers are created from the session price group, valid price-list rows
and web-enabled ticket definitions. Per-ticket fees are included. If a
transaction-level fee exists, `fees_included` is false because it cannot be
truthfully allocated to one ticket. Cancelled sessions remain in the feed with
`status: "cancelled"`. Films marked to suppress results and sessions marked to
suppress external publication are omitted.

## Vista Digital Platform

The Vista example targets the documented Digital Platform OCAPI, not the legacy
on-premises Vista Web Client APIs. Obtain an access token from Vista GAS as
described in Vista's [security guide](https://developer.vista.co/digital-platform/getting-started/security).

```sh
export VISTA_API_BASE='https://tenant-digital-api.app.vista.co'
export VISTA_ACCESS_TOKEN='short-lived-bearer-token'
export VISTA_SITE_ID='0001'
export VISTA_REGION_CODE='AU'
export VISTA_DAYS='7'
export CINEMA_CANONICAL_BASE_URL='https://cinema.example.com'
export CINEMA_FEED_URL='https://cinema.example.com/showtimes.json'
export CINEMA_BOOKING_URL_TEMPLATE='https://cinema.example.com/book/{session_id}'
export CINEMA_PUBLISHER_NAME='Example Cinemas'
export CINEMA_PUBLISHER_URL='https://cinema.example.com'
```

The converter reads the site, site films and showtimes for each requested
business date. It deliberately does not call the per-showtime ticket-price or
seat-availability APIs in a loop. Production deployments should expose those as
separately cached live endpoints, consistent with the 1.1 API profile.

## Other POS systems

Many cinema POS APIs require vendor agreements and do not publish stable request
and response contracts. A converter is marked implemented only when it can be
tested against official documentation or a vendor-supplied fixture.

| Vendor | Status |
| --- | --- |
| Admit One / Showtime Solutions | Needs official API contract and fixture |
| Agile Ticketing | Official feed fields found; needs a sanitized licensed fixture |
| Haxlen | API requested |
| Jack Roe / TAPOS | Needs official API contract and fixture |
| Omniterm | Needs official API contract and fixture |
| POSitive Cinema | Needs official API contract and fixture |
| RTS | Official XML contract found; needs a sanitized fixture and endpoint/auth test |
| Savoy / Oscar | Needs official API contract and fixture |

Contributions should include sanitized vendor fixtures, mapping tests and a link
to the exact API version used. Do not infer fields from scraped cinema pages.

## Tests

From the repository root:

```sh
output_dir="$(mktemp -d)"
php tests/php/generate-converter-fixtures.php "$output_dir"
python tools/validate_examples.py \
  --feed "$output_dir/veezi-1.1.json" \
  --feed "$output_dir/venuemaster-1.1.json" \
  --feed "$output_dir/vista-1.1.json"
```

GitHub Actions performs the same conversion and validation on every push.
