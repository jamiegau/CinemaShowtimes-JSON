<?php
/**
 * VenueMaster VenueSchedule to CinemaShowtimes-JSON 1.1 endpoint.
 *
 * Required environment:
 *   VENUEMASTER_SCHEDULE_URL
 *
 * For a local fixture:
 *   VENUEMASTER_SCHEDULE_URL=venue-schedule-example_pretty.json
 */

require_once __DIR__ . '/venue-master-mapper.php';

$config = array(
    'feed_id' => 'BORONA-showtimes',
    'cinema_id' => 'BORONA',
    'name' => 'Metro Cinemas Boronia',
    'timezone' => 'Australia/Melbourne',
    'language' => 'en-AU',
    'territory' => 'AU',
    'currency' => 'AUD',
    'classification_system' => 'Australian Classification',
    'latitude' => -37.861874,
    'longitude' => 145.286216,
    'postal_address' => array(
        'locality' => 'Boronia',
        'region' => 'VIC',
        'country' => 'AU',
    ),
    'phone' => '+61 3 9762 7199',
    'website' => 'https://metrocinemas.com.au/',
    'canonical_url' => 'https://metrocinemas.com.au/boronia',
    'canonical_base_url' => 'https://metrocinemas.com.au/boronia',
    'canonical_feed_url' => 'https://metrocinemas.com.au/boronia/showtimes.json',
    'google_place_id' => 'ChIJifMJ6GM71moR9uoZ7VFEjeI',
    'publisher' => array(
        'name' => 'Metro Cinemas',
        'url' => 'https://metrocinemas.com.au/',
    ),
    'ttl_seconds' => 900,
);

try {
    $source = getenv('VENUEMASTER_SCHEDULE_URL');
    if ($source === false || trim($source) === '') {
        throw new RuntimeException('VENUEMASTER_SCHEDULE_URL is not configured');
    }
    if (filter_var($source, FILTER_VALIDATE_URL)) {
        $venueData = cst11_http_json($source);
    } else {
        $path = realpath(__DIR__ . '/' . $source);
        if (!$path || !is_file($path)) {
            throw new RuntimeException('VenueMaster fixture path is invalid');
        }
        $venueData = json_decode(file_get_contents($path), true);
        if (!is_array($venueData)) {
            throw new RuntimeException('VenueMaster fixture is invalid JSON');
        }
    }
    cst11_emit(venueMasterToCSTJSON11($venueData, $config));
} catch (Throwable $error) {
    http_response_code(502);
    if (!headers_sent()) {
        header('Content-Type: application/problem+json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    error_log('VenueMaster converter: ' . $error->getMessage());
    echo json_encode(array(
        'type' => 'about:blank',
        'title' => 'VenueMaster conversion failed',
        'status' => 502,
        'detail' => 'The upstream cinema feed is unavailable or incorrectly configured.',
    ));
}
