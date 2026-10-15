# CinemaShowtimes-JSON 1.1

Status: proposed, backwards-conscious minor revision
Schema: [`schema/cinemashowtimes-json-1.1.schema.json`](schema/cinemashowtimes-json-1.1.schema.json)

CinemaShowtimes-JSON is a portable interchange format. It is not a point-of-sale
transaction protocol and it does not replace an exhibitor's booking system.

The key words MUST, MUST NOT, SHOULD, SHOULD NOT and MAY are to be interpreted as
described by RFC 2119 and RFC 8174.

## Design principles

1. **Stable identity.** `feed_id`, `cinema_id`, `film_id`, `auditorium_id`,
   `session_id`, `offer_id` and `media_id` MUST remain stable across refreshes.
   A title, time or URL change MUST NOT create a new identity unless the source
   system considers it a different entity.
2. **Canonical public pages.** Cinemas, films and public sessions SHOULD have
   crawlable `canonical_url` pages. A session URL SHOULD describe one screening,
   stay available after cancellation, and contain information consistent with
   the feed.
3. **Time is explicit.** Session timestamps MUST be RFC 3339 date-times with a
   UTC offset. The cinema MUST publish an IANA `timezone`. `start_time` is the
   public advertised start unless a publisher clearly supplies
   `advertised_start_time`.
4. **Freshness is observable.** A feed MUST have `generated_at` and SHOULD have
   `expires_at` or `ttl_seconds`. Volatile entities SHOULD have `updated_at`.
   Consumers MUST NOT treat an expired snapshot as live availability.
5. **Facts and rights travel together.** Aggregators SHOULD identify source,
   retrieval time and derivation method. Media intended for advertising SHOULD
   include dimensions, accessibility text and usage rights.
6. **Public data only.** Feeds MUST NOT contain API secrets, KDMs, device or
   network details, patron identities, reservation identities, or seat-hold
   ownership. Internal revenue and sales data are outside the public profile.
7. **Extensions do not replace core facts.** Namespaced publisher extensions
   MAY be placed under `extensions`; consumers MUST ignore extensions they do
   not understand.

## Document and HTTP profile

A complete document uses `mode: "full"`. A single-venue feed uses `cinema`; a
multi-venue or aggregator feed uses `cinemas`. Sessions in a multi-venue feed
MUST carry `cinema_id`. Cinema and auditorium IDs MUST be globally unambiguous
within the feed.

A delta document uses `mode: "delta"`, has a monotonically increasing
`sequence`, contains changed entities and may use `deleted` tombstones. It need
not repeat unchanged cinemas or films. A consumer that misses sequences SHOULD
refetch the full document.

Servers SHOULD:

- use HTTPS and `Content-Type: application/json`;
- support `ETag` and/or `Last-Modified`, conditional GET, gzip or Brotli;
- publish a permanent full-feed URL and advertise related resources with
  `links`;
- return cache directives consistent with `expires_at` or `ttl_seconds`;
- provide pagination for query APIs with stable opaque cursors;
- support filters for cinema, geographic bounds or radius, date range, film,
  format, accessibility, status and `updated_since`;
- publish an OpenAPI 3.1 description and, when useful, an MCP adapter;
- offer webhooks or a delta endpoint for schedule, cancellation and availability
  changes.

Recommended link relations are `self`, `schema`, `documentation`, `delta`,
`openapi`, `mcp`, `calendar`, `jsonld`, `booking`, `seatmap` and `availability`.
The feed remains usable when optional linked services do not exist.

## Optional protected multi-cinema API profile

Authentication is an implementation choice, not a requirement of the
CinemaShowtimes-JSON document format. Public discovery feeds MAY remain
anonymous. Partner, licensed, private or operational deployments MAY protect
the same read-only resources with OAuth 2.0 bearer access tokens.

This optional profile separates three decisions:

1. **Authentication:** which user, application or service is calling.
2. **Authorization:** which operations and cinemas that principal may access.
3. **Resource selection:** which cinema or cinemas the request asks to read.

A bearer token SHOULD establish the principal and its permissions; it SHOULD
NOT be the only way a multi-cinema request selects a cinema. Keeping selection
explicit makes requests auditable, cacheable and unambiguous and prevents a
token that covers several cinemas from silently selecting the wrong one.

### Cinema selection

Implementers MAY use either of these equivalent resource styles:

```http
GET /cinemas/EXC-AUS-PMELB/showtimes
Authorization: Bearer <access-token>
```

```http
GET /showtimes?cinema_id=EXC-AUS-PMELB
Authorization: Bearer <access-token>
```

The hierarchical form makes ownership visible in the URI. The filtered
collection form is convenient for aggregators and MAY accept repeated
`cinema_id` parameters:

```http
GET /showtimes?cinema_id=EXC-AUS-PMELB&cinema_id=EXC-AUS-CARLTON
```

Tenant-specific hostnames or a documented cinema-context header MAY also be
used, but the resolved stable `cinema_id` MUST still appear in the response.
Context headers MUST be included in cache keys and request signing where those
features are used.

When no cinema selector is supplied, a server MUST document and consistently
apply one of these behaviours:

- return all public cinemas for an anonymous discovery service;
- return all cinemas authorized for the principal;
- infer the cinema only when the principal is authorized for exactly one; or
- return `400 Bad Request` because explicit selection is required.

A server MUST NOT arbitrarily choose one cinema when the token permits more
than one.

For a single selected cinema, a full response SHOULD use the singular `cinema`
object. For several selected cinemas, it MUST use `cinemas`, and every returned
session MUST include `cinema_id`. Collections and linked resources MUST be
filtered so the response does not disclose an unauthorized cinema, auditorium,
session, offer or availability record.

Flat resource endpoints such as `/sessions/{session_id}` are safe only when the
identifier is unique across the API and the server resolves and authorizes its
owning cinema. Otherwise, use hierarchical paths such as
`/cinemas/{cinema_id}/sessions/{session_id}` and
`/cinemas/{cinema_id}/auditoria/{auditorium_id}/seatmap`.

Pagination cursors, ETags and delta sequences MUST have documented scope. They
MAY be global to the service or scoped to the selected/authorized cinema set,
but a client MUST NOT reuse a selection-scoped cursor or `since` sequence after
changing its cinema selection. A server MUST reject an incompatible cursor
rather than returning an incomplete or cross-cinema page.

### Bearer-token option

Protected implementations SHOULD follow
[OAuth 2.0 Bearer Token Usage](https://www.rfc-editor.org/rfc/rfc6750) and
[OAuth 2.0 Security Best Current Practice](https://www.rfc-editor.org/rfc/rfc9700):

- accept the token in `Authorization: Bearer`, only over HTTPS;
- never place access tokens in query parameters, feed documents, booking URLs
  or logs;
- use short-lived, least-privilege tokens and define revocation behaviour;
- use scopes appropriate to the deployed surface, for example
  `showtimes:read`, `availability:read` and `seatmaps:read`.

The access token MAY be an opaque reference token validated using
[OAuth Token Introspection](https://www.rfc-editor.org/rfc/rfc7662), or a JWT.
A JWT access token SHOULD follow
[RFC 9068](https://www.rfc-editor.org/rfc/rfc9068). The resource server MUST
validate the signature, issuer, intended audience, expiry and not-before time
where present, and MUST confirm that the required scope covers the requested
operation.

Cinema entitlements MAY be carried in a private token claim, attached to an
organization/installation represented by the token, or retrieved from a
server-side authorization store. CinemaShowtimes-JSON does not define a token
claim name. An implementer defining one MUST document its issuer, type,
semantics and revocation/staleness behaviour. Large or frequently changing
cinema lists SHOULD be resolved server-side rather than copied into long-lived
tokens.

Audience restriction identifies the API that may accept a token; it does not
replace object-level cinema authorization. High-isolation deployments MAY use
tenant-specific resource indicators or audiences as described by
[RFC 8707](https://www.rfc-editor.org/rfc/rfc8707), in addition to checking the
requested cinema.

### Authorization decision

For every protected request, the server MUST:

1. authenticate the token and determine the principal;
2. verify the token permits the requested operation;
3. resolve every requested cinema, session or auditorium to its owning cinema;
4. check that ownership against current principal entitlements; and
5. apply the same restriction in the data query, not only after serialization.

Possession of a valid token, knowledge of a `cinema_id`, or a matching
`cinema_id` claim alone MUST NOT bypass the object-level authorization check.
This is the control recommended for preventing
[Broken Object Level Authorization](https://owasp.org/API-Security/editions/2023/en/0xa1-broken-object-level-authorization/).

Suggested failure responses are:

- `400 Bad Request` for a missing or ambiguous selector when selection is
  required;
- `401 Unauthorized` with `WWW-Authenticate: Bearer` for a missing, invalid or
  expired required token;
- `403 Forbidden` for a valid principal lacking scope or cinema entitlement;
- `404 Not Found` instead of `403` when the service intentionally conceals
  whether a private cinema or resource exists.

Authorization failures MUST use a generic problem response and MUST NOT expose
tokens, claims, entitlement lists or cross-cinema data.

### Caching, logging and OpenAPI

Anonymous public responses MAY use shared caching. A response filtered by token
entitlements SHOULD use `Cache-Control: private` or `no-store` unless a trusted
gateway partitions its internal cache by every principal, entitlement,
cinema-selection and query input. A custom cinema-context header that changes a
cacheable representation MUST appear in `Vary`. `Vary: Authorization` alone
MUST NOT be treated as sufficient proof that a shared cache is safely isolated.

Audit logs SHOULD record a request/correlation ID, principal or client ID,
requested cinema IDs, resolved cinema IDs, operation, decision and policy
version. Logs MUST NOT contain bearer tokens. Rate limits SHOULD consider both
the client/principal and the selected tenant or cinema so one tenant cannot
consume another tenant's allocation.

The supplied OpenAPI document models anonymous access or optional bearer
authentication. OpenAPI 3.1 represents that alternative with an empty security
requirement plus a bearer security requirement. A deployment that requires
authentication MUST remove the empty requirement. An implementation's published
OpenAPI description MUST state its actual security requirement and cinema
selection behaviour rather than copying the example unchanged.

## Venue identity

`postal_address` is the canonical structured address; the 1.0 `address` string
is retained as a legacy display value. Coordinates SHOULD be accurate enough to
distinguish nearby venues. `same_as` SHOULD contain official public profiles,
not search-result URLs.

`google_place_id` is an identity reconciliation hint. It does not submit or
claim a business in Google. Publishers SHOULD record
`google_place_id_verified_at` and periodically refresh old Place IDs. Business
Profile resource names or internal account IDs SHOULD only be published where
there is a concrete integration need.

Opening hours describe venue access, not session times. Special closures belong
in `special_hours`.

## Film identity and regional facts

An exhibitor-stable `film_id` is mandatory. `identifiers` SHOULD carry available
industry identifiers such as EIDR and ISAN and widely used discovery identifiers
such as IMDb or TMDB. `same_as` links public entity pages. These identifiers
reduce duplicate entities but do not prove that two cuts or versions are equal.

Classification and release facts are regional arrays. The ambiguous 1.0
`rating` and `release_date` fields may be read for compatibility, but 1.1
publishers SHOULD use `classifications` and `releases`.

`localized` contains language-specific display overrides keyed by BCP 47 tag.
Content that differs by territory should use regional fields, not language
overrides.

## Media and social advertising

The 1.0 `assets` object remains readable. New producers SHOULD publish `media`,
where every rendition has a stable identity, role, URL and kind. Publishers
SHOULD include:

- MIME type, width and height for images and video;
- duration for video or audio;
- `alt_text`, and caption and transcript URLs where applicable;
- a cryptographic hash when downstream systems cache or approve assets;
- holder, credit, permitted uses, territory and validity window in `rights`;
- `platform_hints` as advisory routing hints, never as evidence of permission.

`marketing` supplies reusable editorial metadata and stable campaign mappings.
It MUST NOT contain inferred sensitive traits or patron-level targeting data.
Platform catalog adapters can map the stable film/session/offer IDs to their
content IDs while adding platform-specific fields outside the core feed.
Tracking parameters SHOULD be added at delivery time so `canonical_url` remains
stable.

### Motion posters

A motion poster is a finished promotional video combining animated poster
artwork, or trailer footage and campaign artwork, typically for a portrait
display. Publish it as a film `media[]` entry with `role: "motion_poster"` and
`kind: "video"`. It is a separate media role from a static `poster` and a
`trailer`; publishers MUST NOT substitute a static image for a motion poster or
infer one from an ordinary trailer URL.

Motion posters are OPTIONAL. If no asset is available, omit that entry rather
than publishing a placeholder or an empty URL. Existing feeds do not need to
change. The October 2026 update extends the proposed 1.1 role enumeration;
validators using an earlier schema copy must update before accepting the new
role. Consumers SHOULD skip unsupported media roles and retain the other film
data. A display without a usable motion poster SHOULD fall back to an available
static poster.

For a portrait signage rendition, publishers SHOULD supply a direct video asset
URL, `mime_type`, `width`, `height` and `duration_seconds`. MP4/H.264 at
1080 x 1920 (9:16) is a recommended interchange target, not a mandatory size or
container. Other renditions are permitted; consumers MUST use the supplied
dimensions rather than infer orientation from the role or filename. An HTML
watch page is not a direct video asset URL.

Existing `media_id`, `sha256`, `language`, `updated_at` and `rights` fields apply.
When authorised for lobby playback, record `signage` in `rights.allowed_uses`
with the applicable territories and validity dates. A role alone grants no
usage permission. Publish static artwork as a separate entry when available.

The role does not promise a seamless loop or silent audio. Playback timing,
looping, audio and composition of a trailer window inside artwork are consumer
features, not implied by this asset declaration. No provider is required to
create or automatically discover motion posters.

## Sessions, offers and availability

Every public performance is a separate session. A changed performance retains
its `session_id`; a rescheduled session uses `status: "rescheduled"` and
`previous_start_time`. Cancelled sessions SHOULD remain published long enough
for consumers and crawlers to observe the cancellation.

`preshow_start_time`, `feature_start_time`, `credits_start_time` and `end_time`
have distinct meanings. They MUST NOT be substituted for `start_time`.

`presentation` describes the actual content version and room presentation.
Where available it may carry safe, consumer-relevant metadata derived from the
SMPTE Composition Playlist and ISDCF naming convention. KDM, certificate,
server and key information MUST NOT be exposed.

An offer is a purchasable ticket category. Its price SHOULD include mandatory
fees when `fees_included` is true. `booking_url` MUST lead as directly as
practical to the chosen session or offer. `availability` is a timestamped
snapshot, not a booking guarantee. Consumers requiring a purchase decision
SHOULD follow `availability_url` or the booking flow.

The 1.0 `pricing`, `availability_by_class` and session `attributes` fields remain
accepted as legacy aliases.

## Accessibility

Accessibility is modeled separately at cinema, auditorium and session level:

- the cinema describes access to the premises;
- the auditorium describes installed facilities and seating;
- the session describes services available for that performance.

Publishers SHOULD distinguish open captions, personal closed-caption devices,
subtitles, audio description, assistive listening, hearing loops, sign-language
presentations, wheelchair spaces, companion seating and step-free access.
`confirmation_url` SHOULD explain how a patron confirms or requests equipment.

## Seat maps

`cinemashowtimes-seatmap/1.0` describes a versioned physical layout, using stable
section, row and seat IDs. It supports a coordinate system, screen position,
wheelchair spaces, companion pairing, seat classes and physical features.

A layout MUST NOT embed volatile sale state. Session-specific availability is a
separate endpoint because holds and purchases change much faster than the
auditorium layout. A live endpoint may return place IDs and states such as
`available`, `unavailable`, `held`, `selected` or `unknown`, but it MUST include
an observation timestamp and SHOULD use the same seat IDs as the static map.
Public feeds SHOULD aggregate or omit hold details and MUST NOT reveal who holds
a seat.

## Search, Google and LLM discovery

The feed is a source for publishing surfaces; it is not itself guaranteed to be
crawled or imported by a search engine. Publishers SHOULD generate:

1. a crawlable cinema page with `MovieTheater` JSON-LD;
2. a canonical film page with `Movie` JSON-LD and resolvable external identity;
3. one crawlable page per public screening, with `ScreeningEvent`, full timezone
   offsets, status, location, `workPresented`, image and ticket `Offer`;
4. XML sitemaps containing accurate `lastmod` values;
5. visible page content that matches every structured-data claim;
6. Business Profile updates for owner-managed venue facts.

There is no public general-purpose API for writing entities to Google's
Knowledge Graph. The Knowledge Graph Search API is for lookup. Place IDs,
Business Profile, canonical URLs, structured data and consistent corroborating
public sources improve entity reconciliation, but do not guarantee a knowledge
panel or rich result.

Google's Indexing API MUST NOT be used for ordinary cinema sessions; it is
restricted to job postings and livestream `BroadcastEvent` pages. Use sitemaps
and normal crawl discovery.

No special AI-only markup is required for Google AI search features. LLM and
agent consumers benefit from the same fundamentals: stable IDs, explicit
constraints, canonical sources, provenance, fresh price/availability, accessible
media and direct actions. An MCP adapter SHOULD expose read-only resources and
bounded tools such as:

- `search_screenings(location, date_range, film, accessibility, format)`;
- `get_screening(session_id)`;
- `get_live_availability(session_id)`;
- `get_booking_link(session_id, offer_id)`.

Purchase or reservation tools require a separate authenticated transactional
API, explicit user confirmation and idempotency. They are not part of this
public feed.

See [`examples/google-screening-event.jsonld`](examples/google-screening-event.jsonld)
for a mapping example. JSON-LD MUST appear on the matching public page, rather
than only in an unrelated feed download.

## 1.0 migration

1. Change `spec` only after the new minimum requirements are emitted.
2. Add feed identity, freshness, license, publisher and discovery links.
3. Add canonical URLs and entity-level update/provenance data.
4. Replace scalar address, rating and release date with structured equivalents.
5. Add `media`; retain `assets` during a transition.
6. Add canonical session times, status, presentation, offers and availability.
7. Publish the static seat map separately and link live availability.
8. Keep legacy aliases while consumers migrate; do not emit conflicting values.
