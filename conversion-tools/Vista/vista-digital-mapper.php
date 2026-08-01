<?php

require_once dirname(__DIR__) . '/lib/cinemashowtimes-1.1.php';

function vista11_text($value)
{
    if (is_string($value)) {
        return $value;
    }
    return is_array($value) ? (string) cst11_value($value, 'text', '') : '';
}

function vista11_localized($title, $description, $shortDescription)
{
    $localized = array();
    foreach (array(
        'title' => $title,
        'description' => $description,
        'short_description' => $shortDescription,
    ) as $field => $value) {
        foreach ((array) cst11_value($value, 'translations', array()) as $translation) {
            $tag = cst11_value($translation, 'languageTag');
            if ($tag && cst11_non_empty(cst11_value($translation, 'text'))) {
                $localized[$tag][$field] = $translation['text'];
            }
        }
    }
    return $localized;
}

function vista11_site($siteResponse, $config)
{
    $site = cst11_value($siteResponse, 'site', $siteResponse);
    $contact = cst11_value($site, 'contactDetails', array());
    $address = cst11_value($contact, 'address', array());
    $addressV2 = cst11_value($contact, 'addressV2', array());
    $phones = cst11_value($contact, 'phoneNumbers', array());
    $config['cinema_id'] = (string) cst11_value($site, 'id', cst11_value($config, 'cinema_id'));
    $config['name'] = vista11_text(cst11_value($site, 'name')) ?: cst11_value($config, 'name');
    $config['timezone'] = cst11_value($site, 'ianaTimeZoneName', cst11_value($config, 'timezone', 'UTC'));
    $config['latitude'] = cst11_value(cst11_value($site, 'location', array()), 'latitude');
    $config['longitude'] = cst11_value(cst11_value($site, 'location', array()), 'longitude');
    $config['email'] = cst11_value($contact, 'email');
    if (!empty($phones)) {
        $config['phone'] = cst11_value($phones[0], 'number');
    }
    $line1 = vista11_text(cst11_value($addressV2, 'line1')) ?: cst11_value($address, 'line1');
    $line2 = vista11_text(cst11_value($addressV2, 'line2')) ?: cst11_value($address, 'line2');
    $line3 = vista11_text(cst11_value($addressV2, 'line3'));
    $line4 = vista11_text(cst11_value($addressV2, 'line4'));
    $city = cst11_value($address, 'city');
    $config['postal_address'] = cst11_compact(array(
        'street_address' => trim(implode(', ', array_filter(array($line1, $line2)))),
        'locality' => $city ?: $line3,
        'region' => cst11_value($config, 'region', $line4),
        'postal_code' => cst11_value($config, 'postal_code'),
        'country' => strtoupper((string) cst11_value($config, 'territory', '')),
    ));
    return cst11_cinema($config);
}

function vista11_people($items, $peopleMap)
{
    $result = array();
    foreach ((array) $items as $index => $item) {
        $person = cst11_value($peopleMap, (string) cst11_value($item, 'castAndCrewMemberId'), array());
        $name = cst11_value($person, 'name', array());
        $display = trim(implode(' ', array_filter(array(
            cst11_value($name, 'givenName'),
            cst11_value($name, 'middleName'),
            cst11_value($name, 'familyName'),
        ))));
        if ($display) {
            $result[] = array(
                'name' => $display,
                'order' => $index,
                'identifiers' => array('vista' => cst11_value($person, 'id')),
            );
        }
    }
    return $result;
}

function vista11_film($film, $related, $config, $generatedAt)
{
    $filmId = (string) cst11_value($film, 'id', '');
    $genreMap = vista11_index_by(cst11_value($related, 'genres', array()), 'id');
    $ratingMap = vista11_index_by(cst11_value($related, 'censorRatings', array()), 'id');
    $peopleMap = vista11_index_by(cst11_value($related, 'castAndCrew', array()), 'id');
    $genres = array();
    foreach ((array) cst11_value($film, 'genreIds', array()) as $id) {
        $name = vista11_text(cst11_value(cst11_value($genreMap, (string) $id, array()), 'name'));
        if ($name) {
            $genres[] = $name;
        }
    }
    $rating = cst11_value($ratingMap, (string) cst11_value($film, 'censorRatingId'), array());
    $territory = strtoupper((string) cst11_value($config, 'territory', ''));
    $release = cst11_date(cst11_value($film, 'releaseDate'), cst11_value($config, 'timezone', 'UTC'));
    $media = array();
    foreach ((array) cst11_value($film, 'trailers', array()) as $trailer) {
        $item = cst11_media($filmId, 'video', 'trailer', cst11_value($trailer, 'uri'), empty($media));
        if ($item) {
            $media[] = $item;
        }
    }
    $trailer = cst11_media($filmId, 'video', 'trailer', cst11_value($film, 'trailerUrl'), empty($media));
    if ($trailer) {
        $media[] = $trailer;
    }
    $externalIds = cst11_value($film, 'externalIds', array());

    return cst11_compact(array(
        'film_id' => $filmId,
        'title' => vista11_text(cst11_value($film, 'title')),
        'canonical_url' => cst11_url(cst11_value($config, 'canonical_base_url'), 'movies', $filmId),
        'description' => vista11_text(cst11_value($film, 'synopsis')),
        'short_description' => vista11_text(cst11_value($film, 'shortSynopsis')),
        'identifiers' => array(
            'vista' => $filmId,
            'moviexchange_release_id' => cst11_value($externalIds, 'moviexchangeReleaseId'),
            'corporate_id' => cst11_value($externalIds, 'corporateId'),
            'head_office_code' => cst11_value($film, 'hoCode'),
        ),
        'runtime_minutes' => cst11_value($film, 'runtimeInMinutes'),
        'status' => $release && $release > gmdate('Y-m-d') ? 'coming_soon' : 'now_showing',
        'genres' => $genres,
        'classifications' => ($territory && $rating) ? array(array(
            'territory' => $territory,
            'system' => cst11_value($config, 'classification_system'),
            'code' => vista11_text(cst11_value($rating, 'name')) ?: (string) cst11_value($rating, 'id'),
            'consumer_advice' => vista11_text(cst11_value($film, 'censorRatingNote')),
        )) : array(),
        'releases' => ($territory && $release) ? array(array(
            'territory' => $territory,
            'date' => $release,
            'type' => 'theatrical',
            'distributor' => cst11_value($film, 'distributorName'),
        )) : array(),
        'credits' => array(
            'directors' => vista11_people(cst11_value($film, 'directors', array()), $peopleMap),
            'producers' => vista11_people(cst11_value($film, 'producers', array()), $peopleMap),
            'cast' => vista11_people(cst11_value($film, 'actors', array()), $peopleMap),
        ),
        'media' => $media,
        'localized' => vista11_localized(
            cst11_value($film, 'title', array()),
            cst11_value($film, 'synopsis', array()),
            cst11_value($film, 'shortSynopsis', array())
        ),
        'updated_at' => $generatedAt,
        'provenance' => array(
            'source_id' => 'vista-film-' . $filmId,
            'source_name' => 'Vista Digital Platform',
            'retrieved_at' => $generatedAt,
            'method' => 'authoritative',
        ),
    ));
}

function vista11_index_by($items, $field)
{
    $result = array();
    foreach ((array) $items as $item) {
        if (cst11_non_empty(cst11_value($item, $field))) {
            $result[(string) $item[$field]] = $item;
        }
    }
    return $result;
}

function vista11_auditorium($screen, $generatedAt)
{
    $id = (string) cst11_value($screen, 'id', '');
    return cst11_compact(array(
        'auditorium_id' => $id,
        'name' => vista11_text(cst11_value($screen, 'name')) ?: ('Screen ' . $id),
        'seat_count' => cst11_value($screen, 'seatCount'),
        'seating_mode' => 'reserved',
        'seat_classes' => array(array(
            'class_id' => 'STANDARD',
            'display_name' => 'Standard',
            'features' => array(),
        )),
        'updated_at' => $generatedAt,
        'extensions' => array('vista' => array('screen_number' => cst11_value($screen, 'number'))),
    ));
}

function vista11_session($showtime, $attributes, $config, $generatedAt)
{
    $id = (string) cst11_value($showtime, 'id', '');
    $schedule = cst11_value($showtime, 'schedule', array());
    $attributeNames = array();
    foreach ((array) cst11_value($showtime, 'attributeIds', array()) as $attributeId) {
        $attribute = cst11_value($attributes, (string) $attributeId, array());
        $name = vista11_text(cst11_value($attribute, 'name'));
        if ($name) {
            $attributeNames[] = $name;
        }
    }
    $bookingTemplate = cst11_value($config, 'booking_url_template');
    $bookingUrl = $bookingTemplate
        ? str_replace('{session_id}', rawurlencode($id), $bookingTemplate)
        : null;
    $soldOut = (bool) cst11_value($showtime, 'isSoldOut', false);

    return cst11_compact(array(
        'session_id' => $id,
        'cinema_id' => (string) cst11_value($showtime, 'siteId', cst11_value($config, 'cinema_id')),
        'film_id' => (string) cst11_value($showtime, 'filmId', ''),
        'auditorium_id' => (string) cst11_value($showtime, 'screenId', ''),
        'canonical_url' => cst11_url(cst11_value($config, 'canonical_base_url'), 'sessions', $id),
        'status' => 'scheduled',
        'start_time' => cst11_datetime(cst11_value($schedule, 'startsAt'), cst11_value($config, 'timezone', 'UTC')),
        'advertised_start_time' => cst11_datetime(cst11_value($schedule, 'startsAt'), cst11_value($config, 'timezone', 'UTC')),
        'feature_start_time' => cst11_datetime(cst11_value($schedule, 'filmStartsAt'), cst11_value($config, 'timezone', 'UTC')),
        'end_time' => cst11_datetime(cst11_value($schedule, 'endsAt'), cst11_value($config, 'timezone', 'UTC')),
        'session_type' => cst11_value($showtime, 'eventId') ? 'other' : 'public',
        'seating_mode' => cst11_value($showtime, 'isAllocatedSeating') ? 'reserved' : 'general_admission',
        'sales_channels' => array('web', 'box_office'),
        'presentation' => array(
            'video_format' => cst11_value($showtime, 'requires3dGlasses') ? '3D' : '2D',
            'three_d' => (bool) cst11_value($showtime, 'requires3dGlasses', false),
            'marketing_name' => implode(', ', $attributeNames),
        ),
        'availability' => array(
            'status' => $soldOut ? 'sold_out' : 'on_sale',
            'updated_at' => $generatedAt,
        ),
        'booking_url' => $bookingUrl,
        'updated_at' => $generatedAt,
        'provenance' => array(
            'source_id' => 'vista-showtime-' . $id,
            'source_name' => 'Vista Digital Platform',
            'retrieved_at' => $generatedAt,
            'method' => 'authoritative',
        ),
        'extensions' => array(
            'vista' => array(
                'seat_layout_id' => cst11_value($showtime, 'seatLayoutId'),
                'attribute_ids' => cst11_value($showtime, 'attributeIds', array()),
                'restrictions' => cst11_value($showtime, 'restrictions', array()),
            ),
        ),
    ));
}

function vistaDigitalToCSTJSON11($siteResponse, $filmResponse, $showtimeResponses, $config)
{
    $generatedAt = gmdate('Y-m-d\\TH:i:s\\Z');
    $cinema = vista11_site($siteResponse, $config);
    $config['cinema_id'] = $cinema['cinema_id'];
    $config['timezone'] = $cinema['timezone'];
    $relatedFilms = cst11_value($filmResponse, 'relatedData', array());
    $films = array();
    foreach ((array) cst11_value($filmResponse, 'films', array()) as $film) {
        if (cst11_non_empty(cst11_value($film, 'id')) && vista11_text(cst11_value($film, 'title'))) {
            $films[] = vista11_film($film, $relatedFilms, $config, $generatedAt);
        }
    }

    $showtimes = array();
    $screens = array();
    $attributes = array();
    foreach ($showtimeResponses as $response) {
        foreach ((array) cst11_value($response, 'showtimes', array()) as $showtime) {
            $showtimes[(string) cst11_value($showtime, 'id')] = $showtime;
        }
        $related = cst11_value($response, 'relatedData', array());
        foreach ((array) cst11_value($related, 'screens', array()) as $screen) {
            $screens[(string) cst11_value($screen, 'id')] = $screen;
        }
        foreach ((array) cst11_value($related, 'attributes', array()) as $attribute) {
            $attributes[(string) cst11_value($attribute, 'id')] = $attribute;
        }
    }
    $auditoria = array();
    foreach ($screens as $screen) {
        $auditoria[] = vista11_auditorium($screen, $generatedAt);
    }
    $sessions = array();
    foreach ($showtimes as $showtime) {
        if (cst11_non_empty(cst11_value($showtime, 'id'))
            && cst11_non_empty(cst11_value($showtime, 'filmId'))
            && cst11_non_empty(cst11_value(cst11_value($showtime, 'schedule', array()), 'startsAt'))) {
            $sessions[] = vista11_session($showtime, $attributes, $config, $generatedAt);
        }
    }
    return cst11_feed($config, $cinema, $films, $auditoria, $sessions, 'Vista Digital Platform');
}
