# Implementing the Vista Digital Platform converter

This directory contains a dependency-free PHP endpoint that reads the Vista
Digital Platform OCAPI and publishes a
[`cinemashowtimes-json/1.1`](../../SPECIFICATION.md) full feed.

It targets Vista Digital Platform, not the legacy on-premises Vista Web Client
APIs.

The implementation has two parts:

- `vista-digital-to-CST-JSON.php` is the HTTP entry point and site
  configuration.
- `vista-digital-mapper.php` converts OCAPI responses into the standard.

Keep those files beside each other and preserve the relative path to
`../lib/cinemashowtimes-1.1.php`.

## What the converter publishes

The converter maps the site, localized film details, classifications, genres,
people, media, showtimes, screens, presentation attributes and sold-out state.
It reads one Vista site and one to 31 business dates per response.

It deliberately does not call ticket-price or seat-availability endpoints once
per showtime. That request pattern is expensive and makes a discovery feed
fragile. Production systems should provide price and seat state through
separately cached live endpoints and link to them using the 1.1 API profile.

## 1. Prerequisites

You need:

- PHP 7.4 or newer;
- PHP's JSON, OpenSSL and stream-wrapper support;
- `allow_url_fopen=On`;
- a Vista Digital Platform tenant and site ID;
- a valid bearer access token obtained through Vista GAS; and
- outbound HTTPS access to the tenant's Digital API.

Start with Vista's
[Digital Platform OpenAPI reference](https://developer.vista.co/openapi/digital-platform/openapi)
and [security guide](https://developer.vista.co/digital-platform/getting-started/security).
Use the URLs, credentials, scopes and region assigned to the cinema operator.

Access tokens are short-lived. This example consumes a token; it does not obtain
or refresh one. In production, an authorized server-side service should refresh
the token through the documented GAS flow and supply it to PHP through a secret
manager or service environment.

## 2. Configure the cinema and market

Open `vista-digital-to-CST-JSON.php` and replace the example `$config` values.
In particular, the shipped language, territory, currency and classification
system are Australian examples and must be changed for another market.

Configure:

- stable, unique `feed_id` and `cinema_id` values;
- the site's correct IANA timezone;
- language, territory, currency and classification system;
- `canonical_base_url` for public entity pages;
- `canonical_feed_url` for the final feed address;
- `booking_url_template`, using `{session_id}` where the Vista session ID
  belongs; and
- the public operator identity under `publisher`.

Some public URL and publisher values are already read from optional environment
variables:

```sh
export CINEMA_CANONICAL_BASE_URL='https://cinema.example'
export CINEMA_FEED_URL='https://cinema.example/showtimes.json'
export CINEMA_BOOKING_URL_TEMPLATE='https://cinema.example/book/{session_id}'
export CINEMA_PUBLISHER_NAME='Example Cinemas'
export CINEMA_PUBLISHER_URL='https://cinema.example'
```

If you need verified address, coordinates, Google Place ID or social identity
links beyond those returned by the site resource, add the corresponding
supported keys to `$config`. Never reuse another site's sample identity.

The default `ttl_seconds` is 900 seconds. Keep any HTTP or application cache
within the freshness declared by the generated feed.

## 3. Configure Vista access

Three environment variables are required:

```sh
export VISTA_API_BASE='https://tenant-digital-api.app.vista.co'
export VISTA_ACCESS_TOKEN='short-lived-bearer-token'
export VISTA_SITE_ID='0001'
```

Two vendor options control the request:

```sh
export VISTA_REGION_CODE='AU'
export VISTA_DAYS='7'
```

`VISTA_REGION_CODE` is sent as `Connect-Region-Code` when present. `VISTA_DAYS`
defaults to 7 and is constrained to 1–31.

Do not put the bearer token in source code, URLs, Git, front-end JavaScript or a
public error message. For a web deployment, inject it through the PHP-FPM pool,
container or a secret-aware service. A PHP-FPM pool can define variables such
as:

```ini
env[VISTA_API_BASE] = https://tenant-digital-api.app.vista.co
env[VISTA_SITE_ID] = 0001
env[VISTA_REGION_CODE] = AU
env[VISTA_DAYS] = 7
env[VISTA_ACCESS_TOKEN] = short-lived-bearer-token
```

A static pool file is suitable for an initial test, but an automated
short-lived-token handoff is preferable in production. Reload PHP-FPM after
environment changes.

## 4. Test from the command line

From this directory:

```sh
VISTA_API_BASE='https://tenant-digital-api.app.vista.co' \
VISTA_ACCESS_TOKEN='short-lived-bearer-token' \
VISTA_SITE_ID='0001' \
VISTA_REGION_CODE='AU' \
VISTA_DAYS='2' \
php vista-digital-to-CST-JSON.php > /tmp/vista-showtimes.json
```

Confirm that the response is JSON:

```sh
python3 -m json.tool /tmp/vista-showtimes.json >/dev/null
```

Then, from the repository root:

```sh
python3 -m pip install -r requirements-dev.txt
python3 tools/validate_examples.py --feed /tmp/vista-showtimes.json
```

Verify the data against Vista:

- site and film IDs remain stable;
- film titles and classifications use the intended locale;
- each showtime has the correct local UTC offset and business date;
- screens and presentation attributes are correct;
- sold-out state agrees with the sales channel;
- booking URLs open the intended session; and
- canonical URLs are permanent public pages.

You can exercise the mapper without a tenant or live token using the sanitized
fixture suite:

```sh
output_dir="$(mktemp -d)"
php tests/php/generate-converter-fixtures.php "$output_dir"
python3 tools/validate_examples.py --feed "$output_dir/vista-1.1.json"
```

## 5. Publish the endpoint

Deploy this directory and `../lib` behind PHP on an HTTPS site. A rewrite can
give the endpoint a stable public address such as:

```text
https://cinema.example/showtimes.json
```

For each uncached invocation, the wrapper calls:

```text
GET /ocapi/v1/sites/{siteId}
GET /ocapi/v1/sites/{siteId}/films
GET /ocapi/v1/showtimes/by-business-date/{date}?siteIds={siteId}
```

The last request is repeated for every day in `VISTA_DAYS`. Put a server-side
cache or trusted reverse proxy in front of the endpoint, and avoid a long date
window unless consumers require it. Do not expose the access token or an
uncached origin to browser clients.

After deployment:

```sh
curl -fsS https://cinema.example/showtimes.json \
  -o /tmp/vista-production.json
python3 tools/validate_examples.py --feed /tmp/vista-production.json
```

Monitor HTTP status, JSON and schema validity, token-refresh failures,
`generated_at`, `expires_at`, session count and the business-date range.

## Customising the mapping

Site and market identity belongs in `$config`. Change
`vista-digital-mapper.php` only when a documented OCAPI response version
requires a mapping change.

When extending it:

1. add a sanitized example response under `tests/fixtures`;
2. remove bearer tokens, customer data and commercial details;
3. add assertions to `tests/php/generate-converter-fixtures.php`;
4. regenerate and validate the 1.1 feed; and
5. document any ambiguous or lossy translation.

Use a namespaced `extensions` object for vendor facts with no standard
equivalent. If adding ticket or seat availability, use bounded concurrency and
a dedicated cache rather than making an unbounded request for every showtime.

## Troubleshooting

The endpoint returns a generic `502` problem response and writes the specific
cause to the PHP error log.

- Required variables error: set `VISTA_API_BASE`, `VISTA_ACCESS_TOKEN` and
  `VISTA_SITE_ID` in the runtime executing PHP.
- `Source returned HTTP 401`: obtain a fresh token and check its audience and
  scopes.
- `Source returned HTTP 403`: check tenant access, site permission and
  `Connect-Region-Code`.
- `Source returned HTTP 404`: verify the Digital API base, site ID and that
  `/ocapi/v1` has not been duplicated in `VISTA_API_BASE`.
- `Unable to fetch source URL`: check DNS, outbound routing, CA certificates and
  `allow_url_fopen`.
- Empty showtimes: confirm the site's business dates, schedule publication and
  `VISTA_DAYS`.
- Incorrect times: set the site's actual IANA timezone in `$config`; do not
  offset source timestamps manually.

Never log the `Authorization` header or bearer token.
