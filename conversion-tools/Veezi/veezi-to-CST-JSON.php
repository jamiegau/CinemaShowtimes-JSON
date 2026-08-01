<?php
/**
 * Veezi to CinemaShowtimes-JSON 1.1 endpoint.
 *
 * Required environment:
 *   VEEZI_API_KEY
 *
 * Optional environment:
 *   VEEZI_API_BASE=https://api.oz.veezi.com
 */

require_once __DIR__ . '/veezi-mapper.php';

$config = array(
    'feed_id' => 'EXC-AUS-FORBESCINEMA-showtimes',
    'cinema_id' => 'EXC-AUS-FORBESCINEMA',
    'name' => 'Forbes Cinema',
    'timezone' => 'Australia/Sydney',
    'language' => 'en-AU',
    'territory' => 'AU',
    'classification_system' => 'Australian Classification',
    'latitude' => -33.3860526,
    'longitude' => 148.0098134,
    'postal_address' => array(
        'street_address' => '41 Templar Street',
        'locality' => 'Forbes',
        'region' => 'NSW',
        'postal_code' => '2871',
        'country' => 'AU',
        'formatted' => '41 Templar Street, Forbes NSW 2871, Australia',
    ),
    'phone' => '+61 2 6852 1488',
    'website' => 'https://forbescinema.com.au/',
    'canonical_url' => 'https://forbescinema.com.au/',
    'canonical_base_url' => 'https://forbescinema.com.au/',
    'canonical_feed_url' => 'https://forbescinema.com.au/showtimes.json',
    'google_place_id' => 'ChIJVafZGiuNGmsRBy1AkGRlFNw',
    'publisher' => array(
        'name' => 'Forbes Cinema',
        'url' => 'https://forbescinema.com.au/',
    ),
    'ttl_seconds' => 900,
);

try {
    $apiKey = getenv('VEEZI_API_KEY');
    if ($apiKey === false || trim($apiKey) === '') {
        throw new RuntimeException('VEEZI_API_KEY is not configured');
    }
    $apiBase = getenv('VEEZI_API_BASE') ?: 'https://api.oz.veezi.com';
    $headers = array('VeeziAccessToken' => $apiKey);

    $films = cst11_http_json(rtrim($apiBase, '/') . '/v4/film', $headers);
    $sessions = cst11_http_json(rtrim($apiBase, '/') . '/v1/session', $headers);
    $screens = cst11_http_json(rtrim($apiBase, '/') . '/v1/screen', $headers);
    $webSessions = cst11_http_json(rtrim($apiBase, '/') . '/v1/websession', $headers);

    cst11_emit(veeziToCSTJSON11($films, $sessions, $screens, $webSessions, $config));
} catch (Throwable $error) {
    http_response_code(502);
    if (!headers_sent()) {
        header('Content-Type: application/problem+json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    error_log('Veezi converter: ' . $error->getMessage());
    echo json_encode(array(
        'type' => 'about:blank',
        'title' => 'Veezi conversion failed',
        'status' => 502,
        'detail' => 'The upstream cinema feed is unavailable or incorrectly configured.',
    ));
}
