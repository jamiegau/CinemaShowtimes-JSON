<?php

require_once dirname(__DIR__) . '/lib/cinemashowtimes-1.1.php';

function veezi11_status($status)
{
    switch (strtolower((string) $status)) {
        case 'active':
            return 'now_showing';
        case 'deleted':
        case 'inactive':
            return 'ended';
        default:
            return 'announced';
    }
}

function veezi11_video_format($format)
{
    $format = strtolower((string) $format);
    if (strpos($format, '3d') !== false) {
        return '3D';
    }
    if (strpos($format, '2d') !== false) {
        return '2D';
    }
    return cst11_non_empty($format) ? (string) $format : null;
}

function veezi11_film($film, $config, $generatedAt)
{
    $filmId = (string) cst11_value($film, 'Id', '');
    $media = array();
    foreach (array(
        array('image', 'poster', cst11_value($film, 'FilmPosterUrl'), true),
        array('image', 'thumbnail', cst11_value($film, 'FilmPosterThumbnailUrl'), false),
        array('image', 'backdrop', cst11_value($film, 'BackdropImageUrl'), false),
        array('video', 'trailer', cst11_value($film, 'FilmTrailerUrl'), true),
    ) as $candidate) {
        $item = cst11_media($filmId, $candidate[0], $candidate[1], $candidate[2], $candidate[3]);
        if ($item) {
            $media[] = $item;
        }
    }

    $credits = array('directors' => array(), 'producers' => array(), 'cast' => array());
    foreach ((array) cst11_value($film, 'People', array()) as $person) {
        $entry = cst11_compact(array(
            'name' => trim(
                cst11_value($person, 'FirstName', '') . ' ' . cst11_value($person, 'LastName', '')
            ),
            'identifiers' => array('veezi' => cst11_value($person, 'Id')),
        ));
        $role = strtolower((string) cst11_value($person, 'Role'));
        if ($role === 'director' || $role === 'dicrector') {
            $credits['directors'][] = $entry;
        } elseif ($role === 'producer') {
            $credits['producers'][] = $entry;
        } elseif ($role === 'actor') {
            $entry['order'] = count($credits['cast']);
            $credits['cast'][] = $entry;
        }
    }

    $genres = preg_split('/\s*[,;|]\s*/', (string) cst11_value($film, 'Genre', ''), -1, PREG_SPLIT_NO_EMPTY);
    $language = cst11_language(cst11_value($film, 'AudioLanguage'));
    $territory = strtoupper((string) cst11_value($config, 'territory', ''));
    $rating = cst11_value($film, 'Rating');
    $openingDate = cst11_date(cst11_value($film, 'OpeningDate'), cst11_value($config, 'timezone', 'UTC'));

    return cst11_compact(array(
        'film_id' => $filmId,
        'title' => (string) cst11_value($film, 'Title', ''),
        'canonical_url' => cst11_url(cst11_value($config, 'canonical_base_url'), 'movies', $filmId),
        'description' => cst11_value($film, 'Synopsis'),
        'short_description' => cst11_value($film, 'SignageText'),
        'alt_titles' => array_filter(array(cst11_value($film, 'ShortName'))),
        'identifiers' => array(
            'veezi' => $filmId,
            'national_code' => cst11_value($film, 'NationalCode'),
        ),
        'runtime_minutes' => cst11_value($film, 'Duration'),
        'status' => veezi11_status(cst11_value($film, 'Status')),
        'original_language' => $language,
        'languages' => $language ? array($language) : array(),
        'genres' => $genres ?: array(),
        'classifications' => ($territory && $rating) ? array(array(
            'territory' => $territory,
            'system' => cst11_value($config, 'classification_system'),
            'code' => (string) $rating,
            'consumer_advice' => cst11_value($film, 'Content'),
        )) : array(),
        'releases' => ($territory && $openingDate) ? array(array(
            'territory' => $territory,
            'date' => $openingDate,
            'type' => 'theatrical',
            'distributor' => cst11_value($film, 'Distributor'),
        )) : array(),
        'credits' => $credits,
        'media' => $media,
        'updated_at' => $generatedAt,
        'provenance' => array(
            'source_id' => 'veezi-film-' . $filmId,
            'source_name' => 'Veezi Film API',
            'retrieved_at' => $generatedAt,
            'method' => 'authoritative',
        ),
        'extensions' => array(
            'veezi' => array(
                'format' => cst11_value($film, 'Format'),
                'is_restricted' => cst11_value($film, 'IsRestricted'),
                'government_film_title' => cst11_value($film, 'GovernmentFilmTitle'),
            ),
        ),
    ));
}

function veezi11_auditorium($screen, $generatedAt)
{
    $id = (string) cst11_value($screen, 'Id', '');
    $wheelchairSeats = (int) cst11_value($screen, 'WheelChairSeats', 0);
    return cst11_compact(array(
        'auditorium_id' => $id,
        'name' => (string) cst11_value($screen, 'Name', 'Screen ' . $id),
        'seat_count' => (int) cst11_value($screen, 'TotalSeats', 0),
        'seating_mode' => cst11_value($screen, 'HasCustomLayout') ? 'reserved' : 'general_admission',
        'accessibility' => array(
            'wheelchair_spaces' => $wheelchairSeats > 0,
        ),
        'seat_classes' => array(array(
            'class_id' => 'STANDARD',
            'display_name' => 'Standard',
            'features' => array(),
        )),
        'updated_at' => $generatedAt,
        'extensions' => array(
            'veezi' => array(
                'screen_number' => cst11_value($screen, 'ScreenNumber'),
                'house_seats' => cst11_value($screen, 'HouseSeats'),
                'wheelchair_spaces_count' => $wheelchairSeats,
            ),
        ),
    ));
}

function veezi11_session($session, $config, $bookingUrls, $generatedAt)
{
    $sessionId = (string) cst11_value($session, 'Id', '');
    $timezone = cst11_value($config, 'timezone', 'UTC');
    $salesChannels = array();
    foreach ((array) cst11_value($session, 'SalesVia', array()) as $channel) {
        if (strtoupper($channel) === 'WWW') {
            $salesChannels[] = 'web';
        } elseif (strtoupper($channel) === 'POS') {
            $salesChannels[] = 'box_office';
        }
    }
    $seating = strtolower((string) cst11_value($session, 'Seating'));
    $seatingMode = $seating === 'open' ? 'general_admission' : 'reserved';
    $soldOut = (bool) cst11_value($session, 'TicketsSoldOut', false);
    $fewLeft = (bool) cst11_value($session, 'FewTicketsLeft', false);
    $sourceStatus = strtolower((string) cst11_value($session, 'Status'));
    $availabilityStatus = $soldOut
        ? 'sold_out'
        : ($sourceStatus === 'open' ? ($fewLeft ? 'limited' : 'on_sale') : 'off_sale');

    $available = cst11_value($session, 'SeatsAvailable');
    $held = cst11_value($session, 'SeatsHeld');
    $house = cst11_value($session, 'SeatsHouse');
    $sold = cst11_value($session, 'SeatsSold');
    $total = null;
    if (is_numeric($available) && is_numeric($held) && is_numeric($house) && is_numeric($sold)) {
        $total = (int) $available + (int) $held + (int) $house + (int) $sold;
    }
    $bookingUrl = cst11_value($bookingUrls, $sessionId);

    return cst11_compact(array(
        'session_id' => $sessionId,
        'cinema_id' => (string) cst11_value($config, 'cinema_id', ''),
        'film_id' => (string) cst11_value($session, 'FilmId', ''),
        'auditorium_id' => (string) cst11_value($session, 'ScreenId', ''),
        'canonical_url' => cst11_url(cst11_value($config, 'canonical_base_url'), 'sessions', $sessionId),
        'status' => 'scheduled',
        'start_time' => cst11_datetime(
            cst11_value($session, 'PreShowStartTime', cst11_value($session, 'FeatureStartTime')),
            $timezone
        ),
        'advertised_start_time' => cst11_datetime(
            cst11_value($session, 'PreShowStartTime', cst11_value($session, 'FeatureStartTime')),
            $timezone
        ),
        'preshow_start_time' => cst11_datetime(cst11_value($session, 'PreShowStartTime'), $timezone),
        'feature_start_time' => cst11_datetime(cst11_value($session, 'FeatureStartTime'), $timezone),
        'end_time' => cst11_datetime(cst11_value($session, 'FeatureEndTime'), $timezone),
        'session_type' => strtolower((string) cst11_value($session, 'ShowType')) === 'private'
            ? 'private'
            : 'public',
        'seating_mode' => $seatingMode,
        'sales_channels' => array_values(array_unique($salesChannels)),
        'presentation' => array(
            'video_format' => veezi11_video_format(cst11_value($session, 'FilmFormat')),
            'marketing_name' => cst11_value($session, 'FilmFormat'),
            'three_d' => veezi11_video_format(cst11_value($session, 'FilmFormat')) === '3D',
            'language' => cst11_language(cst11_value($session, 'AudioLanguage')),
        ),
        'availability' => array(
            'status' => $availabilityStatus,
            'updated_at' => $generatedAt,
            'seats_total' => $total,
            'seats_available' => is_numeric($available) ? (int) $available : null,
        ),
        'booking_url' => $bookingUrl,
        'updated_at' => $generatedAt,
        'provenance' => array(
            'source_id' => 'veezi-session-' . $sessionId,
            'source_name' => 'Veezi Session API',
            'retrieved_at' => $generatedAt,
            'method' => 'authoritative',
        ),
        'extensions' => array(
            'veezi' => array(
                'source_status' => cst11_value($session, 'Status'),
                'price_card_name' => cst11_value($session, 'PriceCardName'),
                'sales_cutoff_time' => cst11_datetime(cst11_value($session, 'SalesCutOffTime'), $timezone),
                'cleanup_end_time' => cst11_datetime(cst11_value($session, 'CleanupEndTime'), $timezone),
                'complimentaries_allowed' => cst11_value($session, 'AreComplimentariesAllowed'),
            ),
        ),
    ));
}

function veeziToCSTJSON11($films, $sessions, $screens, $webSessions, $config)
{
    $generatedAt = gmdate('Y-m-d\\TH:i:s\\Z');
    $cinema = cst11_cinema($config);
    $bookingUrls = array();
    foreach ($webSessions as $webSession) {
        if (cst11_non_empty(cst11_value($webSession, 'Id'))
            && filter_var(cst11_value($webSession, 'URL'), FILTER_VALIDATE_URL)) {
            $bookingUrls[(string) $webSession['Id']] = $webSession['URL'];
        }
    }
    $mappedFilms = array();
    foreach ($films as $film) {
        if (cst11_non_empty(cst11_value($film, 'Id')) && cst11_non_empty(cst11_value($film, 'Title'))) {
            $mappedFilms[] = veezi11_film($film, $config, $generatedAt);
        }
    }
    $mappedAuditoria = array();
    foreach ($screens as $screen) {
        if (cst11_non_empty(cst11_value($screen, 'Id'))) {
            $mappedAuditoria[] = veezi11_auditorium($screen, $generatedAt);
        }
    }
    $mappedSessions = array();
    foreach ($sessions as $session) {
        if (cst11_non_empty(cst11_value($session, 'Id'))
            && cst11_non_empty(cst11_value($session, 'FilmId'))
            && cst11_non_empty(cst11_value($session, 'PreShowStartTime', cst11_value($session, 'FeatureStartTime')))) {
            $mappedSessions[] = veezi11_session($session, $config, $bookingUrls, $generatedAt);
        }
    }
    return cst11_feed($config, $cinema, $mappedFilms, $mappedAuditoria, $mappedSessions, 'Veezi');
}
