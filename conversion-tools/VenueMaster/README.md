# Implementing the VenueMaster converter

This directory contains a dependency-free PHP endpoint that converts a
VenueMaster VenueSchedule 7.5 document into a
[`cinemashowtimes-json/1.1`](../../SPECIFICATION.md) full feed.

The implementation has two parts:

- `venue-to-CST-JSON.php` loads the schedule and holds cinema configuration.
- `venue-master-mapper.php` converts VenueMaster entities into the standard.

Keep those files beside each other and preserve the relative path to
`../lib/cinemashowtimes-1.1.php`.

## What the converter publishes

The converter maps the installation, movies, ratings, auditoria, accessibility,
sessions, cancellations, booking URLs, availability and ticket offers.

Offers are created from a session's price group, valid price-list rows and
web-enabled ticket definitions. Per-ticket fees are included in their price.
When VenueMaster supplies a transaction-level fee, `fees_included` is false
because that fee cannot truthfully be assigned to one ticket.

Cancelled sessions remain in the feed with `status: "cancelled"`. Movies marked
to suppress results and sessions marked to suppress external publication are
omitted.

## 1. Prerequisites

You need:

- PHP 7.4 or newer;
- PHP's JSON, OpenSSL and stream-wrapper support;
- `allow_url_fopen=On` for an HTTPS schedule source;
- access to the cinema's VenueSchedule JSON output; and
- an integration host that can reach that output securely.

The supplied `Location JSON Programming Guide v7.5 2018-12-19.pdf`, extracted
text and example schedule files document the payload used by this mapper.
Confirm the cinema's VenueMaster export version before assuming a newer or
customized feed has identical fields.

## 2. Configure the cinema

Open `venue-to-CST-JSON.php` and replace the example `$config` values:

- use stable, unique `feed_id` and `cinema_id` values;
- set the correct IANA timezone, language, territory, currency and
  classification system;
- provide the verified address, coordinates, phone and website;
- set `canonical_url` to the cinema's permanent venue page;
- set `canonical_base_url` to the base for public entity pages;
- set `canonical_feed_url` to the final HTTPS address of this feed;
- add a verified Google Place ID, if available; and
- identify the operator in `publisher`.

Do not publish the sample cinema's Place ID, coordinates or URLs for another
site. Search engines and agents use these values to reconcile the venue with
other public sources.

The default `ttl_seconds` is 900 seconds. Configure downstream caching so it
does not serve the response beyond the freshness declared in the feed.

## 3. Provide the VenueSchedule source

Set `VENUEMASTER_SCHEDULE_URL` to either:

- an HTTPS URL reachable by the PHP server; or
- a local filename relative to this directory, for testing or a controlled
  file-transfer integration.

For HTTPS:

```sh
export VENUEMASTER_SCHEDULE_URL='https://integration.example/VenueSchedule.json'
```

For the supplied local fixture:

```sh
VENUEMASTER_SCHEDULE_URL=venue-schedule-example_pretty.json \
php venue-to-CST-JSON.php
```

The remote loader verifies TLS certificates and accepts only a valid URL. The
local loader resolves the filename inside this directory; it does not accept an
arbitrary absolute path. Its input must be UTF-8 JSON. The supplied
`venue-schedule-example.json` preserves the original UTF-16 sample, while
`venue-schedule-example_pretty.json` is the UTF-8 fixture intended for testing.

Do not expose the cinema POS by broad router port forwarding or by disabling
TLS verification. Prefer one of these deployment patterns:

1. have VenueMaster export to a restricted integration host;
2. copy the file on a schedule over an authenticated private channel; or
3. place a tightly controlled HTTPS reverse proxy between PHP and the POS
   network.

Keep the POS network private. If the schedule URL itself requires additional
authentication, add a header-based credential in a protected deployment rather
than putting a secret in the query string.

For a web deployment, define the source in the PHP-FPM pool, container or
service environment, for example:

```ini
env[VENUEMASTER_SCHEDULE_URL] = https://integration.example/VenueSchedule.json
```

Reload PHP-FPM after changing its environment.

## 4. Test with the supplied schedule

From this directory:

```sh
VENUEMASTER_SCHEDULE_URL=venue-schedule-example_pretty.json \
php venue-to-CST-JSON.php > /tmp/venuemaster-showtimes.json
python3 -m json.tool /tmp/venuemaster-showtimes.json >/dev/null
```

From the repository root:

```sh
python3 -m pip install -r requirements-dev.txt
python3 tools/validate_examples.py --feed /tmp/venuemaster-showtimes.json
```

The fixture includes behaviour that is worth checking in a real integration:

- at least one cancelled session;
- ticket offers derived from a price group;
- auditorium and accessibility data; and
- suppressed entities that must not be advertised.

Run the complete sanitized converter fixture suite with:

```sh
output_dir="$(mktemp -d)"
php tests/php/generate-converter-fixtures.php "$output_dir"
python3 tools/validate_examples.py \
  --feed "$output_dir/venuemaster-1.1.json"
```

Before production, compare a sample period against the POS:

- confirm film, session and auditorium counts;
- check timezone offsets on both sides of daylight-saving transitions;
- compare each ticket type, amount, currency and fee treatment;
- verify cancelled and suppressed sessions;
- open public booking and canonical URLs; and
- confirm wheelchair/accessibility values with the venue.

## 5. Publish the endpoint

Deploy this directory and `../lib` behind PHP on an HTTPS integration host.
Expose a stable public route, for example:

```text
https://cinema.example/showtimes.json
```

Use a server-side cache or trusted reverse proxy. The public endpoint should
never fetch directly from a sensitive POS network for every visitor. If the
VenueSchedule is staged to a local file, write it atomically so PHP never reads
a partially transferred document.

After deployment:

```sh
curl -fsS https://cinema.example/showtimes.json \
  -o /tmp/venuemaster-production.json
python3 tools/validate_examples.py --feed /tmp/venuemaster-production.json
```

Monitor HTTP status, JSON parsing, schema validation, `generated_at`,
`expires_at`, session count and source-file age. Alert when the schedule becomes
stale even if the endpoint still returns HTTP 200.

## Customising the mapping

Site identity belongs in `$config`. Change `venue-master-mapper.php` only for a
documented VenueSchedule version or site-specific payload extension.

When extending the mapper:

1. retain a sanitized vendor fixture that demonstrates the field;
2. remove customer, credential and commercially sensitive information;
3. add assertions to `tests/php/generate-converter-fixtures.php`;
4. regenerate and schema-validate the output; and
5. document any lossy or ambiguous translation.

Do not guess a field's meaning from its name when the VenueSchedule guide or
vendor can confirm it. Put vendor-specific facts with no standard equivalent
under a namespaced `extensions` object.

## Troubleshooting

The public endpoint returns a generic `502` problem response and records the
specific cause in the PHP error log.

- `VENUEMASTER_SCHEDULE_URL is not configured`: set it in the PHP runtime, not
  only in an unrelated login shell.
- `VenueMaster fixture path is invalid`: local filenames are resolved relative
  to this directory; confirm the file exists and is readable.
- `Unable to fetch source URL`: check DNS, routing, CA certificates,
  `allow_url_fopen` and the staging/reverse-proxy service.
- `Source returned HTTP 401` or `403`: configure the integration's
  authentication in a header-aware protected wrapper.
- `VenueMaster fixture is invalid JSON` or `Source returned invalid JSON`:
  ensure the export is complete and atomically transferred.
- Missing offers: check the session price group, price-list validity window,
  ticket web-enabled flag and transaction/per-ticket fee configuration.
- Incorrect session time: correct the IANA timezone in `$config`; do not
  compensate by altering session strings.

Do not log complete private schedules if they contain operational or commercial
data that the cinema has not approved for redistribution.
