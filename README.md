# CinemaShowtimes-JSON
CinemaShowtimes-JSON is an open standard schema for sharing cinema showtime and session data in a consistent, machine-readable JSON format.  

## Purpose
Cinema operators, distributors, POS vendors, and third-party aggregators all need access to reliable session data — what films are playing, when, where, in what format, and with what seat availability.
Today, this information is often shared through proprietary feeds, custom APIs, or web scraping, leading to inconsistency and integration overhead.

CinemaShowtimes-JSON provides a simple, open, and extensible format for representing:

* Cinema metadata (location, timezone, contact details, Google IDs)
* Film metadata (titles, identifiers, runtime, ratings, media assets)
* Auditoria and seat classes (capacity, accessibility, premium seating)
* Session details (times, attributes, pricing, availability)
* Media assets (posters, banners, trailers, logos, backdrops, thumbnails)

## Goals
* Interoperability → a neutral, vendor-agnostic schema that works across Vista, Radiant, RTS, Veezi, and custom systems.
* Simplicity → designed to be human-readable, easily parsed by developers, and quick to implement.
* Extensibility → supports additional fields like preshow times, feature start times, or premium seat classes without breaking compatibility.
* Openness → free to use, adapt, and extend under a permissive license (MIT).
* __Improved integration with the Google Maps API, giving cinema operators greater control over the showtime data displayed in Google Search__

## Who is it for
* Cinemas & Exhibitors → publish your session data once in a clean, portable format.
* POS Vendors → provide consistent output to your cinema clients and their partners.
* Aggregators & Apps → consume showtimes without guessing at feed structures or scraping HTML.
* Researchers / Analysts → integrate cinema schedules into business intelligence workflows.

## Example Usage
* Powering a cinema website’s showtimes page.
* Supplying Google, Apple, or Fandango with session data.
* Providing feeds to community apps, newsletters, or kiosks.
* Synchronizing schedules between exhibitor POS and third-party services.
* enabling custom website, kiosk, and digital signage development to target a unified API, with conversion tools ensuring compatibility across most major vendors.

## Coming Soon support
The CinemaShowtimes-JSON format also supports “coming soon” films using the same schema as sessiontime.json. A comingsoon.json file may be published alongside regular showtimes, containing only the cinema and films objects, while omitting auditoria and sessions. Each film entry can include additional fields such as status: "coming_soon", release_date, pre_sales_start, and an optional presales block with booking links and earliest available showtimes. This ensures distributors, aggregators, and apps can provide a seamless experience by displaying both currently playing titles and upcoming films within a single standardized format.

For example, along with http://cinemaname.com/showtimes.json, you can have a seperate endpoing http://cinemaname.com/comingsoon.json

## What you will find in this repository

1. Definition of the JSON file with examples.
2. Tools to convert vendor-specific feeds (e.g. Vista, Radiant, Veezi) into CinemaShowtimes-JSON.
Located in the conversion-tools directory, these will be implemented as simple, single-file PHP scripts that can be deployed on a standard web server. Each script can be pointed at a vendor’s data source and will return the equivalent CinemaShowtimes-JSON output by proxying the vendor endpoint, converting the data, and delivering it in the standardized format.
3. Example wordpress plugin to read and render the session times for a cinema website.


## Basic example
A full example can be foudn in the file `CinemaShowtimes-full-example.json`.  The same example but in JSON5
that includes a description as comments can be found in `CinemaShowtimes-full-example-with-description.json5`

A minima example is below.

```json
{
  "spec": "cinemashowtimes/1.0",
  "generated_at": "2025-08-17T12:00:00Z",
  "cinema": {
    "cinema_id": "EXC-AUS-NOWRA",
    "name": "Example Cinemas Name",
    "timezone": "Australia/Melbourne"
  },
  "films": [
    {
      "film_id": "FILM-AVATAR3",
      "title": "Avatar: Fire and Ash",
      "runtime_minutes": 190,
      "rating": "M"
    }
  ],
  "sessions": [
    {
      "session_id": "EXC-NOWRA-2025-08-18-1930-S1-AVATAR3-2D",
      "film_id": "FILM-AVATAR3",
      "start_time": "2025-08-18T19:30:00+10:00",
      "video_format": "2D",
      "availability": {
        "status": "on_sale",
        "seats_available": 128
      }
    }
  ]
}
```

## Conversion Example
An example of this plugin can be found at https://forbescinema.com.au/example-showtimes-json.php which is based on the file `conversions-tools/veezi-to-CST-JSOM.php`.

## Proponents

CinemaShowtimes-JSON is supported and promoted by individuals and organizations in the cinema technology and exhibition space.

**Current proponents include:**
- **Digital Cinema Network Pty Ltd** (https://dcnaustralia.com.au)
- **James Gardiner** (Director, Digital Cinema Network Pty Ltd, owner of repository)
