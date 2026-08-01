<?php
/**
 * Shared helpers for CinemaShowtimes-JSON 1.1 example converters.
 *
 * This file deliberately has no WordPress or Composer dependency.
 */

function cst11_value($array, $key, $default = null)
{
    return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
}

function cst11_non_empty($value)
{
    return $value !== null && $value !== '' && $value !== array();
}

function cst11_compact($value)
{
    if (!is_array($value)) {
        return $value;
    }
    $isList = array_keys($value) === range(0, count($value) - 1);
    $result = array();
    foreach ($value as $key => $item) {
        $item = cst11_compact($item);
        if (cst11_non_empty($item)) {
            if ($isList) {
                $result[] = $item;
            } else {
                $result[$key] = $item;
            }
        }
    }
    return $result;
}

function cst11_slug($value)
{
    $value = preg_replace('/[^A-Za-z0-9._~-]+/', '-', trim((string) $value));
    return trim($value, '-');
}

function cst11_url($base, $path, $id)
{
    if (!cst11_non_empty($base)) {
        return null;
    }
    return rtrim($base, '/') . '/' . trim($path, '/') . '/' . rawurlencode((string) $id);
}

function cst11_datetime($value, $timezone)
{
    if (!cst11_non_empty($value)) {
        return null;
    }
    try {
        $hasOffset = preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/i', (string) $value);
        $zone = new DateTimeZone($timezone ?: 'UTC');
        $date = $hasOffset
            ? new DateTimeImmutable((string) $value)
            : new DateTimeImmutable((string) $value, $zone);
        return $date->format(DateTimeInterface::RFC3339);
    } catch (Exception $error) {
        return null;
    }
}

function cst11_compact_datetime($value, $timezone)
{
    if (!cst11_non_empty($value)) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat(
        '!Ymd\\THis',
        substr((string) $value, 0, 15),
        new DateTimeZone($timezone ?: 'UTC')
    );
    return $date ? $date->format(DateTimeInterface::RFC3339) : null;
}

function cst11_date($value, $timezone = 'UTC')
{
    $dateTime = cst11_datetime($value, $timezone);
    return $dateTime ? substr($dateTime, 0, 10) : null;
}

function cst11_language($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $known = array(
        'english' => 'en',
        'french' => 'fr',
        'german' => 'de',
        'italian' => 'it',
        'spanish' => 'es',
        'japanese' => 'ja',
        'korean' => 'ko',
        'mandarin' => 'zh',
        'cantonese' => 'yue',
        'hindi' => 'hi',
        'arabic' => 'ar',
    );
    $lower = strtolower($value);
    if (isset($known[$lower])) {
        return $known[$lower];
    }
    if (preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/', $value)) {
        return $value;
    }
    return 'und';
}

function cst11_media($filmId, $kind, $role, $url, $primary = false)
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    return cst11_compact(array(
        'media_id' => cst11_slug($filmId . '-' . $role . '-' . substr(hash('sha256', $url), 0, 12)),
        'kind' => $kind,
        'role' => $role,
        'url' => $url,
        'primary' => $primary,
        'platform_hints' => $kind === 'image'
            ? array('web', 'google', 'facebook', 'instagram', 'pinterest')
            : array('web', 'google', 'facebook', 'instagram', 'tiktok', 'youtube'),
    ));
}

function cst11_publisher($config)
{
    $publisher = cst11_value($config, 'publisher', array());
    if (!is_array($publisher)) {
        $publisher = array('name' => (string) $publisher);
    }
    if (empty($publisher['name'])) {
        $publisher['name'] = cst11_value($config, 'name', 'Cinema feed publisher');
    }
    return cst11_compact($publisher);
}

function cst11_cinema($config)
{
    $cinema = array(
        'cinema_id' => (string) cst11_value($config, 'cinema_id', ''),
        'name' => (string) cst11_value($config, 'name', ''),
        'canonical_url' => cst11_value($config, 'canonical_url', cst11_value($config, 'website')),
        'timezone' => (string) cst11_value($config, 'timezone', 'UTC'),
        'location' => array(
            'latitude' => cst11_value($config, 'latitude', cst11_value($config, 'lat')),
            'longitude' => cst11_value($config, 'longitude', cst11_value($config, 'lon')),
        ),
        'postal_address' => cst11_value($config, 'postal_address'),
        'address' => cst11_value($config, 'address'),
        'phone' => cst11_value($config, 'phone'),
        'email' => cst11_value($config, 'email'),
        'website' => cst11_value($config, 'website'),
        'google_place_id' => cst11_value($config, 'google_place_id'),
        'google_place_id_verified_at' => cst11_value($config, 'google_place_id_verified_at'),
        'same_as' => cst11_value($config, 'same_as', array()),
        'updated_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
    );
    if (!cst11_non_empty(cst11_value($cinema, 'postal_address'))) {
        unset($cinema['postal_address']);
    }
    if (!is_numeric(cst11_value($cinema['location'], 'latitude'))
        || !is_numeric(cst11_value($cinema['location'], 'longitude'))) {
        unset($cinema['location']);
    } else {
        $cinema['location']['latitude'] = (float) $cinema['location']['latitude'];
        $cinema['location']['longitude'] = (float) $cinema['location']['longitude'];
    }
    return cst11_compact($cinema);
}

function cst11_feed($config, $cinema, $films, $auditoria, $sessions, $sourceName)
{
    $generatedAt = gmdate('Y-m-d\\TH:i:s\\Z');
    $ttl = max(0, (int) cst11_value($config, 'ttl_seconds', 900));
    $feedId = cst11_value($config, 'feed_id');
    if (!cst11_non_empty($feedId)) {
        $feedId = cst11_value($cinema, 'cinema_id') . '-showtimes';
    }
    $feed = array(
        'spec' => 'cinemashowtimes-json/1.1',
        'feed_id' => (string) $feedId,
        'mode' => 'full',
        'generated_at' => $generatedAt,
        'expires_at' => gmdate('Y-m-d\\TH:i:s\\Z', time() + $ttl),
        'ttl_seconds' => $ttl,
        'language' => cst11_value($config, 'language', 'en'),
        'license' => cst11_value($config, 'license'),
        'canonical_url' => cst11_value($config, 'canonical_feed_url'),
        'publisher' => cst11_publisher($config),
        'links' => cst11_value($config, 'links', array()),
        'cinema' => $cinema,
        'films' => array_values($films),
        'auditoria' => array_values($auditoria),
        'sessions' => array_values($sessions),
        'extensions' => array(
            'converter' => array(
                'source_system' => $sourceName,
                'profile' => 'public-discovery',
            ),
        ),
    );
    return cst11_compact($feed);
}

function cst11_http_json($url, $headers = array(), $verifyTls = true)
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Invalid source URL');
    }
    $headerLines = array('Accept: application/json');
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }
    $context = stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'timeout' => 30,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headerLines) . "\r\n",
        ),
        'ssl' => array(
            'verify_peer' => $verifyTls,
            'verify_peer_name' => $verifyTls,
        ),
    ));
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException('Unable to fetch source URL');
    }
    $status = 0;
    if (isset($http_response_header[0])
        && preg_match('/\s([0-9]{3})\s/', $http_response_header[0], $match)) {
        $status = (int) $match[1];
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Source returned HTTP ' . $status);
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Source returned invalid JSON');
    }
    return $decoded;
}

function cst11_emit($feed)
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=' . (int) cst11_value($feed, 'ttl_seconds', 0));
    }
    echo json_encode(
        $feed,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}
