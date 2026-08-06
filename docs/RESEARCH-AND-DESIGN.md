# Research and design notes for 1.1

Research reviewed on 1 and 6 August 2026. This document records why the
standard changed; [`SPECIFICATION.md`](../SPECIFICATION.md) is normative.

## Evidence from implementation patterns

The data models and operational patterns of cinema scheduling, presentation,
facility and content systems were reviewed without making any one product
normative.

## Existing repository implementation audit

The original 1.0 examples were descriptive rather than normative: there was no
machine validation, the JSON and commented JSON5 examples had drifted, time
field names were inconsistent, regional rating/release semantics were
ambiguous, seat maps were only opaque URLs, and freshness/provenance/canonical
identity were incomplete. Version 1.1 addresses those issues in new validated
examples while leaving the root 1.0 fixtures intact for migration testing.

The original Veezi and VenueMaster converters emitted incomplete 1.0
projections. They now use separate, fixture-tested mapping functions and emit
1.1 identity, canonical time, provenance, auditorium and availability data.
Veezi also uses its Screen and WebSession APIs; VenueMaster maps ratings,
cancelled sessions and valid ticket price groups. A Vista Digital Platform
converter was added because Vista publishes a sufficiently precise OCAPI
contract. Products without public or supplied contracts remain unimplemented.

The WordPress prototype previously modelled only a subset of films and sessions,
did not round-trip cinema/auditorium/feed metadata, exposed database columns on
export and discarded unknown objects. Version 0.2.0 preserves each raw 1.1
entity alongside indexed fields, overlays administrator edits on export, applies
delta tombstones, and uses proper REST permission callbacks. A fixture test
imports and re-exports the complete example before schema and semantic
validation.

Converter implementation was checked against the official
[Veezi API reference](https://api.us.veezi.com/help/), including its separate
Film, Session, Screen and WebSession resources, and Vista's
[Digital Platform OpenAPI](https://developer.vista.co/openapi/digital-platform/openapi)
and [authentication guidance](https://developer.vista.co/digital-platform/getting-started/security).
RTS publishes an [XML API contract](https://helpdesk.rts-solutions.com/portal/en/kb/articles/api-documentation-30-12-2022)
and Agile publishes [WebSales Feed return fields](https://support.agiletix.com/hc/en-us/articles/4442900831771-Feed-API-Return-Values-showslist-true),
but those converters were not added without sanitized deployment fixtures to
test vendor-specific endpoint and authentication behaviour.

### Projection and presentation systems

Digital-cinema systems commonly store package and Composition Playlist metadata:
stable UUIDs, content title text, language and territory data, picture and audio
formats, frame rate, resolution, stereoscopic/encrypted flags and credit
markers. This motivated the optional `presentation` object. Only
consumer-facing scheduling and presentation facts are included; encryption,
keys and infrastructure details are explicitly excluded.

### Scheduling and point-of-sale systems

Cinema scheduling and point-of-sale systems commonly distinguish advertised,
preshow, feature and cleanup times; allocated versus open seating;
public/private sessions; sales channels; price cards; operational status;
accessibility; and available, held, sold and house seats. This motivated precise
time semantics, `seating_mode`, `sales_channels`, `session_type`, session
status, offers and timestamped availability. Patron and internal financial data
remain outside the public profile.

### Facility and content services

Facility and content services commonly hold rich auditorium capabilities,
external IDs, regional releases and classifications, media hashes and technical
metadata, source scores and provenance. This motivated structured addresses,
regional arrays, auditorium presentation capabilities, `media`, rights, hashes
and provenance.

## Standards and platform findings

### Web entity and event semantics

- [Schema.org ScreeningEvent](https://schema.org/ScreeningEvent) connects a
  screening to a presented work, language and video format.
- [Schema.org MovieTheater](https://schema.org/MovieTheater) supplies venue,
  address, geo, opening-hours and event vocabulary.
- [Schema.org Seat](https://schema.org/Seat) supplies number, row, section and
  seating type, but does not define an auditorium geometry or live inventory
  protocol.
- [JSON Schema Draft 2020-12](https://json-schema.org/draft/2020-12) is the
  validation basis.
- [RFC 3339](https://www.rfc-editor.org/rfc/rfc3339) and
  [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110) underpin timestamps,
  validators and HTTP publication.
- [W3C DCAT 3](https://www.w3.org/TR/vocab-dcat-3/) and
  [PROV-O](https://www.w3.org/TR/prov-o/) influenced dataset metadata,
  distributions, version/freshness and provenance.

### Protected multi-cinema API patterns

The optional protected API profile was based on standards and first-party
multi-tenant APIs:

- [OAuth 2.0](https://www.rfc-editor.org/rfc/rfc6749) requires a resource
  server to validate that an access token is current and scoped for the
  requested resource.
- [OAuth bearer-token usage](https://www.rfc-editor.org/rfc/rfc6750) defines
  the `Authorization: Bearer` request pattern and its TLS and token-leakage
  safeguards.
- [OAuth 2.0 Security Best Current Practice](https://www.rfc-editor.org/rfc/rfc9700)
  updates the OAuth 2.0 security model for threats observed in deployed
  systems.
- [JWT access-token profile RFC 9068](https://www.rfc-editor.org/rfc/rfc9068)
  requires resource servers to validate token type, issuer, audience,
  signature and time claims. [RFC 7662](https://www.rfc-editor.org/rfc/rfc7662)
  supplies an introspection alternative for opaque tokens.
- [OAuth resource indicators](https://www.rfc-editor.org/rfc/rfc8707) recommend
  audience restriction and specifically discuss including tenant identity in a
  protected-resource URI for high-isolation multi-tenant services.
- [OpenAPI 3.1 security requirements](https://spec.openapis.org/oas/v3.1.1.html#security-requirement-object)
  use `{}` to represent anonymous access and alternative requirement objects to
  represent optional bearer authentication.
- Google's [resource-name guidance](https://google.aip.dev/122) uses explicit
  hierarchical resource names such as `publishers/{publisher}/books/{book}`.
  This supports `/cinemas/{cinema_id}/showtimes` where a hierarchy is useful.
- Stripe Connect makes the account context explicit per request using an
  [account/context header](https://docs.stripe.com/connect/authentication),
  separate from the API credential. This is a deployed example of keeping
  authentication and tenant selection distinct.
- GitHub Apps calculate effective access as the intersection of the user,
  application and selected-resource permissions:
  [authorizing GitHub Apps](https://docs.github.com/en/apps/using-github-apps/authorizing-github-apps).
- Auth0 advises APIs receiving an organization-bearing token to
  [validate the organization claim](https://auth0.com/docs/manage-users/organizations/using-tokens)
  against an expected, trusted entity rather than treating claim presence as
  sufficient.
- AWS's [multi-tenant API authorization guidance](https://docs.aws.amazon.com/prescriptive-guidance/latest/saas-multitenant-api-access-authorization/introduction.html)
  distinguishes authentication/authorization from tenant data isolation.
- OWASP identifies manipulated object IDs as
  [Broken Object Level Authorization](https://owasp.org/API-Security/editions/2023/en/0xa1-broken-object-level-authorization/)
  and requires permission checks at every endpoint that accepts an object ID.

These sources converge on one rule: a token establishes the principal and
permission ceiling, while the request explicitly identifies the cinema or
resource and the server authorizes that object on every access. A token limited
to one cinema can support an omission/default convenience, but a token covering
several cinemas must not cause an arbitrary cinema to be selected.

### Google Search, Maps and Knowledge Graph

- Google recommends JSON-LD and uses structured data as explicit clues about
  page meaning: [structured data introduction](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data).
- The [Event structured-data guide](https://developers.google.com/search/docs/appearance/structured-data/event)
  requires a unique leaf URL for each performance, recommends explicit timezone
  offsets, supports status and previous start time, and maps ticket price,
  currency, availability and URL through `Offer`.
- The [Movie structured-data guide](https://developers.google.com/search/docs/appearance/structured-data/movie)
  requires film imagery for its movie carousel and recommends detail pages and
  sitemaps.
- The [Local Business guide](https://developers.google.com/search/docs/appearance/structured-data/local-business)
  supports structured address, geo, hours, contact details and canonical venue
  pages.
- [General structured-data policies](https://developers.google.com/search/docs/appearance/structured-data/sd-policies)
  require markup to represent visible page content and do not guarantee a rich
  result.
- Google's [Knowledge Graph explanation](https://support.google.com/knowledgepanel/answer/9787176)
  says panels are created automatically from sufficient open-web information;
  local businesses should manage Business Profiles. The
  [Knowledge Graph Search API](https://developers.google.com/knowledge-graph)
  is a lookup surface, not a general ingestion API.
- [Place ID guidance](https://developers.google.com/maps/documentation/places/web-service/place-id)
  says IDs uniquely identify places for Maps APIs, may change, and stored IDs
  older than 12 months should be refreshed.
- A verified Business Profile connected to Maps exposes a Place ID:
  [Business Profile location resource](https://developers.google.com/my-business/reference/rest/v4/accounts.locations).
  The [Place Actions API](https://developers.google.com/my-business/reference/placeactions/rest)
  manages supported action links for eligible locations, but should not be
  represented as a general cinema-inventory submission route.
- The [Indexing API](https://developers.google.com/search/apis/indexing-api/v3/using-api)
  is restricted to `JobPosting` and livestream `BroadcastEvent` pages, so
  ordinary screenings must use crawlable links and sitemaps.
- Google's [AI search guidance](https://developers.google.com/search/docs/appearance/ai-features)
  says no special AI schema or file is required; ordinary crawl, index, content,
  preview and structured-data requirements still apply.

The design therefore focuses on resolvable canonical entities, cross-source
identity, correct event pages, visible/corroborated facts and rapid freshness.
It deliberately makes no promise that Google will import a feed or create a
knowledge panel.

### Social advertising and discovery

- The [Open Graph protocol](https://ogp.me/) establishes canonical URL, title,
  type and image plus structured image/video metadata for share previews.
- TikTok's [generic catalog field reference](https://ads.tiktok.com/help/article/available-fields-for-generic-catalog)
  requires stable item identity, title and image, and supports link, video,
  availability, price, address and campaign labels.
- Pinterest's [catalog requirements](https://help.pinterest.com/en/business/article/before-you-get-started-with-catalogs)
  similarly center stable IDs, title, description, link, image, price and
  availability.

The core standard now supplies the clean source facts for platform-specific
adapters, but does not embed one platform's feed vocabulary. Media rights,
territory and expiry were added because technical suitability does not grant
advertising permission.

### LLM and agent discovery

- OpenAI's [publisher guidance](https://help.openai.com/en/articles/12627856)
  describes allowing OAI-SearchBot so public pages can be discovered and cited.
- The [Model Context Protocol resource](https://modelcontextprotocol.io/specification/2025-06-18/server/resources)
  and [tool](https://modelcontextprotocol.io/specification/2025-06-18/server/tools)
  models separate readable, URI-addressed context from schema-described
  actions.
- The [Open Cinema Project developer interface](https://opencinema.app/developers)
  demonstrates useful cinema discovery patterns: geographic/date/format and
  accessibility filters, cursor pagination, incremental changes, webhooks,
  iCalendar and MCP.

This motivated `links`, query guidance, delta/tombstone support and the MCP
profile. It did not motivate publishing an AI-generated synopsis or speculative
`llms.txt`; authoritative facts and direct actions are more durable.

### Seats, accessibility and presentation

- Ticketmaster's [Availability API](https://developer.ticketmaster.com/products-and-docs/apis/partner/availability/)
  separates cached inventory from purchase-time truth and uses section/row/seat
  place IDs. AMC also separates
  [showtime, seating and order APIs](https://developers.amctheatres.com/).
  This supports a static layout plus a separate live endpoint.
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/), its
  [caption guidance](https://www.w3.org/WAI/WCAG22/Understanding/captions-prerecorded)
  and [audio-description guidance](https://www.w3.org/WAI/WCAG22/Understanding/audio-description-prerecorded)
  motivated precise service labels and accessible media.
- The [ISDCF Digital Cinema Naming Convention](https://registry-page.isdcf.com/)
  and [SMPTE ST 429-16](https://pub.smpte.org/doc/st429-16/20140815-pub/st0429-16-2014.pdf)
  support machine-readable content-version, territory, language, picture and
  audio metadata. The public schema carries a safe subset.

## Important non-goals

- No booking, payment, refund or seat-hold mutation protocol.
- No patron, reservation or audience-profile data.
- No entitlement to use a media asset merely because its URL is present.
- No claim of direct Google Knowledge Graph, Search, Maps or ad-platform
  ingestion.
- No replacement for DCP, CPL, KDM, POS or distributor delivery standards.
