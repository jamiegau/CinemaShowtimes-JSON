<?php
/**
 * Vista Digital Platform (OCAPI) to CinemaShowtimes-JSON 1.1 endpoint.
 *
 * Required environment:
 *   VISTA_API_BASE       e.g. https://tenant-digital-api.app.vista.co
 *   VISTA_ACCESS_TOKEN   bearer token obtained from Vista GAS
 *   VISTA_SITE_ID
 *
 * Optional:
 *   VISTA_REGION_CODE
 *   VISTA_DAYS=7
 */

require_once __DIR__ . '/vista-digital-mapper.php';

$config = array(
    'feed_id' => 'vista-site-showtimes',
    'cinema_id' => getenv('VISTA_SITE_ID') ?: 'VISTA-SITE',
    'name' => 'Vista Cinema',
    'timezone' => 'UTC',
    'language' => 'en',
    'territory' => 'AU',
    'currency' => 'AUD',
    'classification_system' => 'Australian Classification',
    'canonical_base_url' => getenv('CINEMA_CANONICAL_BASE_URL') ?: null,
    'canonical_feed_url' => getenv('CINEMA_FEED_URL') ?: null,
    'booking_url_template' => getenv('CINEMA_BOOKING_URL_TEMPLATE') ?: null,
    'publisher' => array(
        'name' => getenv('CINEMA_PUBLISHER_NAME') ?: 'Cinema operator',
        'url' => getenv('CINEMA_PUBLISHER_URL') ?: null,
    ),
    'ttl_seconds' => 900,
);

try {
    $apiBase = getenv('VISTA_API_BASE');
    $accessToken = getenv('VISTA_ACCESS_TOKEN');
    $siteId = getenv('VISTA_SITE_ID');
    if (!$apiBase || !$accessToken || !$siteId) {
        throw new RuntimeException('VISTA_API_BASE, VISTA_ACCESS_TOKEN and VISTA_SITE_ID are required');
    }
    $headers = array('Authorization' => 'Bearer ' . $accessToken);
    $region = getenv('VISTA_REGION_CODE');
    if ($region) {
        $headers['Connect-Region-Code'] = $region;
    }
    $base = rtrim($apiBase, '/') . '/ocapi/v1';
    $site = cst11_http_json($base . '/sites/' . rawurlencode($siteId), $headers);
    $films = cst11_http_json($base . '/sites/' . rawurlencode($siteId) . '/films', $headers);
    $days = min(31, max(1, (int) (getenv('VISTA_DAYS') ?: 7)));
    $showtimeResponses = array();
    for ($offset = 0; $offset < $days; $offset++) {
        $date = gmdate('Y-m-d', strtotime('+' . $offset . ' days'));
        $url = $base . '/showtimes/by-business-date/' . $date
            . '?siteIds=' . rawurlencode($siteId);
        $showtimeResponses[] = cst11_http_json($url, $headers);
    }
    cst11_emit(vistaDigitalToCSTJSON11($site, $films, $showtimeResponses, $config));
} catch (Throwable $error) {
    http_response_code(502);
    if (!headers_sent()) {
        header('Content-Type: application/problem+json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    error_log('Vista converter: ' . $error->getMessage());
    echo json_encode(array(
        'type' => 'about:blank',
        'title' => 'Vista conversion failed',
        'status' => 502,
        'detail' => 'The upstream cinema feed is unavailable or incorrectly configured.',
    ));
}
