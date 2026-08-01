# Implementing the Veezi converter

This directory contains a dependency-free PHP endpoint that reads one Veezi
site and publishes a
[`cinemashowtimes-json/1.1`](../../SPECIFICATION.md) full feed.

The implementation has two parts:

- `veezi-to-CST-JSON.php` is the HTTP entry point and site configuration.
- `veezi-mapper.php` converts Veezi responses into CinemaShowtimes-JSON.

Keep those files beside each other and preserve the relative path to
`../lib/cinemashowtimes-1.1.php`.

## What the converter publishes

The converter joins Veezi films, sessions, screens and web sessions. It
publishes films and media, auditoria, exact session times, presentation and
seating modes, sales channels, public booking URLs and the aggregate
availability supplied by Veezi.

Veezi's public API does not supply complete ticket prices, seat geometry or
per-seat live state through these resources. The converter leaves those fields
out rather than manufacturing data. Multi-film packages are also omitted until
the optional FilmPackage resource can be joined reliably.

## 1. Prerequisites

You need:

- PHP 7.4 or newer;
- PHP's JSON, OpenSSL and stream-wrapper support;
- `allow_url_fopen=On`, because the example uses `file_get_contents()` for
  HTTPS requests;
- a Veezi API access token for the cinema site; and
- outbound HTTPS access to the Veezi API.

Veezi tokens are site-specific. Publish a separate configured endpoint for each
site rather than combining credentials in a public request parameter.

The relevant vendor references are Veezi's
[API overview](https://api.us.veezi.com/help/),
[authentication](https://api.us.veezi.com/help/Authentication),
[film](https://api.us.veezi.com/help/Film),
[session](https://api.us.veezi.com/help/Sessions),
[screen](https://api.us.veezi.com/help/Screen) and
[web-session](https://api.us.veezi.com/help/WebSession) documentation.

## 2. Configure the cinema

Open `veezi-to-CST-JSON.php` and replace the example `$config` values with the
cinema's real public identity:

- use stable, unique `feed_id` and `cinema_id` values;
- set the IANA timezone, such as `Australia/Sydney`;
- set the language, territory and classification system for the market;
- provide the verified address, coordinates, phone and website;
- make `canonical_url` the cinema's permanent public venue page;
- make `canonical_base_url` the base used for public film and session pages;
- make `canonical_feed_url` the final public URL of this feed;
- add a verified Google Place ID, if known; and
- identify the cinema operator in `publisher`.

Do not copy the sample cinema's Place ID, coordinates or canonical URLs. Stable,
truthful identity data is important for search engines and downstream entity
matching.

The default `ttl_seconds` is 900 seconds. If the endpoint is cached, use the
same or a shorter cache lifetime so the HTTP response never outlives the
freshness declared in the JSON.

## 3. Set the Veezi environment

The API key is required. The API base is optional and defaults to the
Australia/New Zealand endpoint:

```sh
export VEEZI_API_KEY='provided-by-the-cinema'
export VEEZI_API_BASE='https://api.oz.veezi.com'
```

Choose the Veezi API host for the site's region. Do not add the access token to
the PHP file, a URL, a Git repository or browser-side JavaScript. The wrapper
sends it in the `VeeziAccessToken` request header.

For a web deployment, define the variables in the PHP-FPM pool, container or
secret manager that runs the endpoint. For example, a private PHP-FPM pool can
contain:

```ini
env[VEEZI_API_KEY] = provided-by-the-cinema
env[VEEZI_API_BASE] = https://api.oz.veezi.com
```

Reload PHP-FPM after changing its environment. Confirm how your host passes
environment variables to PHP; shell `export` commands do not automatically
apply to an already-running web server.

## 4. Test from the command line

From this directory, run:

```sh
VEEZI_API_KEY='provided-by-the-cinema' \
VEEZI_API_BASE='https://api.oz.veezi.com' \
php veezi-to-CST-JSON.php > /tmp/veezi-showtimes.json
```

Inspect the result:

```sh
python3 -m json.tool /tmp/veezi-showtimes.json >/dev/null
```

Then, from the repository root, install the validator and validate the feed:

```sh
python3 -m pip install -r requirements-dev.txt
python3 tools/validate_examples.py --feed /tmp/veezi-showtimes.json
```

Also check that:

- every session has the intended local UTC offset;
- Veezi screens became the correct auditoria;
- each public `booking_url` opens the intended session;
- canonical film, cinema and session URLs resolve publicly; and
- film and session counts agree with the Veezi site for the same period.

You can test the mapper without live credentials by running the repository's
sanitized fixture suite:

```sh
output_dir="$(mktemp -d)"
php tests/php/generate-converter-fixtures.php "$output_dir"
python3 tools/validate_examples.py --feed "$output_dir/veezi-1.1.json"
```

## 5. Publish the endpoint

Deploy this directory and `../lib` behind PHP on an HTTPS site. The public route
may point directly to `veezi-to-CST-JSON.php`, or a web-server rewrite may expose
it as a stable URL such as:

```text
https://cinema.example/showtimes.json
```

The endpoint makes four upstream requests on every uncached invocation:

```text
GET /v4/film
GET /v1/session
GET /v1/screen
GET /v1/websession
```

Put a server-side cache or trusted reverse proxy in front of it in production.
Do not let browser traffic multiply calls to Veezi. Keep credentials and the
uncached origin inaccessible to untrusted users if the vendor agreement limits
redistribution.

After deployment:

```sh
curl -fsS https://cinema.example/showtimes.json \
  -o /tmp/veezi-production.json
python3 tools/validate_examples.py --feed /tmp/veezi-production.json
```

Schedule an external monitor to check HTTP status, JSON parsing, schema
validation, `generated_at`, `expires_at`, session count and the earliest/latest
session dates.

## Customising the mapping

Site identity belongs in `$config`; do not modify the mapper for ordinary
cinema metadata. Change `veezi-mapper.php` only when a documented Veezi payload
variant needs a new field mapping.

When extending the mapper:

1. save a sanitized example of the vendor response under `tests/fixtures`;
2. remove credentials, customer details and commercially sensitive values;
3. add assertions to `tests/php/generate-converter-fixtures.php`;
4. regenerate and validate the 1.1 output; and
5. document any field that cannot be represented losslessly.

Live seat state and price calls should normally be exposed through separately
cached endpoints instead of adding one upstream request per session to this
discovery feed.

## Troubleshooting

The endpoint returns a generic `502` problem response to clients and writes the
specific cause to the PHP error log.

- `VEEZI_API_KEY is not configured`: set the variable in the runtime that
  actually executes PHP.
- `Source returned HTTP 401` or `403`: verify the token, site and regional API
  host.
- `Unable to fetch source URL`: check outbound networking, DNS, CA
  certificates, `allow_url_fopen` and the selected API host.
- `Source returned invalid JSON`: capture the upstream status in a protected
  environment and confirm that the account still has access to the documented
  resource.
- Empty or shifted sessions: verify the site's timezone and inspect whether the
  Veezi site is publishing sessions for the requested period.

Never log request headers or the Veezi access token.
