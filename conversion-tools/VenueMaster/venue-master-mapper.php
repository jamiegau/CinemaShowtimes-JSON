<?php

require_once dirname(__DIR__) . '/lib/cinemashowtimes-1.1.php';

function venue11_index_by($items, $field)
{
    $result = array();
    foreach ((array) $items as $item) {
        if (cst11_non_empty(cst11_value($item, $field))) {
            $result[(string) $item[$field]] = $item;
        }
    }
    return $result;
}

function venue11_film($movie, $config, $ratingMap, $generatedAt)
{
    $filmId = (string) cst11_value($movie, 'Movie_Code', '');
    $timezone = cst11_value($config, 'timezone', 'UTC');
    $territory = strtoupper((string) cst11_value($config, 'territory', ''));
    $releaseDate = cst11_compact_datetime(cst11_value($movie, 'Movie_Release_Date'), $timezone);
    $releaseDay = $releaseDate ? substr($releaseDate, 0, 10) : null;
    $ratingCode = cst11_value($movie, 'Movie_Rating_Code');
    $rating = cst11_value($ratingMap, (string) $ratingCode, array());

    $cast = array();
    foreach (preg_split('/\s*,\s*/', (string) cst11_value($movie, 'Movie_Talent', ''), -1, PREG_SPLIT_NO_EMPTY) as $name) {
        $cast[] = array('name' => $name, 'order' => count($cast));
    }
    $genres = preg_split('/\s*[,;|]\s*/', (string) cst11_value($movie, 'Movie_Genre', ''), -1, PREG_SPLIT_NO_EMPTY);
    $media = array();
    $trailer = cst11_media($filmId, 'video', 'trailer', cst11_value($movie, 'TrailerUrl'), true);
    if ($trailer) {
        $media[] = $trailer;
    }
    $externalUrl = cst11_value($movie, 'Movie_URL');
    $canonical = cst11_url(cst11_value($config, 'canonical_base_url'), 'movies', $filmId);
    if (!$canonical && filter_var($externalUrl, FILTER_VALIDATE_URL)) {
        $canonical = $externalUrl;
    }

    $status = 'announced';
    if ($releaseDay) {
        $status = $releaseDay > gmdate('Y-m-d') ? 'coming_soon' : 'now_showing';
    }
    return cst11_compact(array(
        'film_id' => $filmId,
        'title' => (string) cst11_value($movie, 'Movie_Name', ''),
        'canonical_url' => $canonical,
        'description' => cst11_value($movie, 'Movie_Synopsis'),
        'short_description' => cst11_value($movie, 'Movie_Memo_Title'),
        'alt_titles' => array_filter(array(cst11_value($movie, 'Movie_English_Title'))),
        'same_as' => ($externalUrl && $externalUrl !== $canonical) ? array($externalUrl) : array(),
        'identifiers' => array(
            'venuemaster' => $filmId,
            'numero' => cst11_value($movie, 'Movie_Numero_Code'),
            'imdb' => cst11_value($movie, 'Movie_IMDB_ID'),
            'tmdb' => cst11_value($movie, 'Movie_TMDb_ID'),
            'eidr' => cst11_value($movie, 'Movie_EIDR'),
        ),
        'runtime_minutes' => cst11_value($movie, 'Movie_Length'),
        'status' => $status,
        'genres' => $genres ?: array(),
        'classifications' => ($territory && $ratingCode) ? array(array(
            'territory' => $territory,
            'system' => cst11_value($config, 'classification_system'),
            'code' => (string) $ratingCode,
            'consumer_advice' => cst11_value(
                $movie,
                'Movie_Consumer_Advice',
                cst11_value($rating, 'Rating_Synopsis')
            ),
        )) : array(),
        'releases' => ($territory && $releaseDay) ? array(array(
            'territory' => $territory,
            'date' => $releaseDay,
            'type' => 'theatrical',
            'distributor' => cst11_value($movie, 'Movie_Distributor_Code'),
        )) : array(),
        'credits' => array('cast' => $cast),
        'media' => $media,
        'updated_at' => $generatedAt,
        'provenance' => array(
            'source_id' => 'venuemaster-movie-' . $filmId,
            'source_name' => 'VenueMaster VenueSchedule',
            'retrieved_at' => $generatedAt,
            'method' => 'authoritative',
        ),
        'extensions' => array(
            'venuemaster' => array(
                'has_open_captions' => cst11_value($movie, 'Movie_Has_Open_Caption'),
                'has_closed_captions' => cst11_value($movie, 'Movie_Has_Closed_Caption'),
                'has_audio_description' => cst11_value($movie, 'Movie_Has_Audio_Description'),
                'has_hearing_assistance' => cst11_value($movie, 'Movie_Has_Hearing_Assistance_And_Loop'),
                'suppress_external' => cst11_value($movie, 'Movie_Suppress_Results'),
            ),
        ),
    ));
}

function venue11_auditorium($venue, $generatedAt)
{
    $premium = (bool) cst11_value($venue, 'Venue_Premium', false);
    $hearing = (bool) cst11_value($venue, 'Venue_Hearing', false);
    $attributes = array();
    if (cst11_value($venue, 'Venue_Digital_Projection')) {
        $attributes[] = 'Digital projection';
    }
    if (cst11_value($venue, 'Venue_Digital_Sound')) {
        $attributes[] = 'Digital sound';
    }
    if ($premium) {
        $attributes[] = 'Premium seating';
    }
    return cst11_compact(array(
        'auditorium_id' => (string) cst11_value($venue, 'Venue_Code', ''),
        'name' => (string) cst11_value($venue, 'Venue_Name', ''),
        'seat_count' => (int) cst11_value($venue, 'Venue_Normal_Capacity', 0),
        'seating_mode' => ((int) cst11_value($venue, 'Venue_Map_Type', 0)) > 0
            ? 'reserved'
            : 'general_admission',
        'attributes' => $attributes,
        'accessibility' => array(
            'assistive_listening' => $hearing,
            'hearing_loop' => $hearing,
        ),
        'presentation_capabilities' => array(
            'video' => cst11_value($venue, 'Venue_Digital_Projection') ? array('2D') : array(),
            'audio' => cst11_value($venue, 'Venue_Digital_Sound') ? array('5.1') : array(),
        ),
        'seat_classes' => array(array(
            'class_id' => $premium ? 'PREMIUM' : 'STANDARD',
            'display_name' => $premium ? 'Premium' : 'Standard',
            'features' => $premium ? array('Premium seat') : array(),
        )),
        'updated_at' => $generatedAt,
        'extensions' => array(
            'venuemaster' => array(
                'enterprise_id' => cst11_value($venue, 'Venue_Enterprise_ID'),
                'house_capacity' => cst11_value($venue, 'Venue_House_Capacity'),
                'plan_code' => cst11_value($venue, 'Venue_Default_Plan_Code'),
            ),
        ),
    ));
}

function venue11_offers($session, $tickets, $priceList, $priceGroups, $classId)
{
    $groupCode = (string) cst11_value($session, 'Session_Price_Group_Code', '');
    $bookingUrl = cst11_value($session, 'Session_URL');
    if (!$groupCode || !filter_var($bookingUrl, FILTER_VALIDATE_URL)) {
        return array();
    }
    $group = cst11_value($priceGroups, $groupCode, array());
    $perTicketFee = (float) cst11_value($group, 'Price_Group_Fee_Per_Ticket', 0);
    $transactionFee = (float) cst11_value($group, 'Price_Group_Fee_Per_Transaction', 0);
    $offers = array();
    foreach ($priceList as $price) {
        if ((string) cst11_value($price, 'Price_PriceGroup_Code') !== $groupCode
            || !cst11_value($price, 'Price_Valid', false)) {
            continue;
        }
        $ticketCode = (string) cst11_value($price, 'Price_Ticket_Code', '');
        $ticket = cst11_value($tickets, $ticketCode, array());
        if ($ticket && !cst11_value($ticket, 'Ticket_Allowed_For_Web', true)) {
            continue;
        }
        $amount = (float) cst11_value($price, 'Price_Price', 0)
            + (float) cst11_value($price, 'Price_Surcharge', 0)
            + $perTicketFee;
        $maximum = (int) cst11_value($ticket, 'Ticket_Maximum_Sale_Qty', 0);
        $offers[] = cst11_compact(array(
            'offer_id' => cst11_slug(cst11_value($session, 'Session_Index') . '-' . $ticketCode . '-' . $classId),
            'name' => cst11_value($ticket, 'Ticket_Name', $ticketCode),
            'category' => cst11_value($ticket, 'Ticket_Name', $ticketCode),
            'class_id' => $classId,
            'price' => $amount,
            'currency' => cst11_value($GLOBALS, 'venue11_currency', 'AUD'),
            'fees_included' => $transactionFee <= 0,
            'availability' => cst11_value($session, 'Session_Cancelled') ? 'sold_out' : 'in_stock',
            'minimum_quantity' => max(1, (int) cst11_value($ticket, 'Ticket_Minimum_Sale_Qty', 1)),
            'maximum_quantity' => $maximum > 0 ? $maximum : null,
            'booking_url' => $bookingUrl,
            'eligibility' => cst11_value($ticket, 'Ticket_Comment'),
        ));
    }
    return $offers;
}

function venue11_session(
    $session,
    $config,
    $venueMap,
    $tickets,
    $priceList,
    $priceGroups,
    $generatedAt
) {
    $sessionId = (string) cst11_value($session, 'Session_Index', '');
    $timezone = cst11_value($config, 'timezone', 'UTC');
    $auditoriumId = (string) cst11_value($session, 'Session_Venue_Code', '');
    $venue = cst11_value($venueMap, $auditoriumId, array());
    $premium = (bool) cst11_value($venue, 'Venue_Premium', false);
    $classId = $premium ? 'PREMIUM' : 'STANDARD';
    $cancelled = (bool) cst11_value($session, 'Session_Cancelled', false);
    $start = cst11_compact_datetime(cst11_value($session, 'Session_Date_Time'), $timezone);
    $featureStart = $start;
    $trailers = (int) cst11_value($session, 'Session_Trailers', 0);
    if ($start && $trailers > 0) {
        $featureStart = (new DateTimeImmutable($start))->modify('+' . $trailers . ' minutes')
            ->format(DateTimeInterface::RFC3339);
    }
    $end = cst11_compact_datetime(cst11_value($session, 'Session_End_Time'), $timezone);
    $capacity = (int) cst11_value($venue, 'Venue_Normal_Capacity', 0);
    $remainingRaw = cst11_value($session, 'Session_Seats_Remaining');
    $remaining = is_numeric($remainingRaw) ? max(0, (int) $remainingRaw) : null;
    if ($capacity > 0 && $remaining !== null) {
        $remaining = min($capacity, $remaining);
    }
    $bookingUrl = cst11_value($session, 'Session_URL');
    $canonical = cst11_url(cst11_value($config, 'canonical_base_url'), 'sessions', $sessionId);
    if (!$canonical && filter_var($bookingUrl, FILTER_VALIDATE_URL)) {
        $canonical = $bookingUrl;
    }
    $salesChannels = array('box_office');
    if (filter_var($bookingUrl, FILTER_VALIDATE_URL)) {
        $salesChannels[] = 'web';
    }

    return cst11_compact(array(
        'session_id' => $sessionId,
        'cinema_id' => (string) cst11_value($config, 'cinema_id', ''),
        'film_id' => (string) cst11_value($session, 'Session_Movie_Code', ''),
        'auditorium_id' => $auditoriumId,
        'canonical_url' => $canonical,
        'status' => $cancelled ? 'cancelled' : 'scheduled',
        'start_time' => $start,
        'advertised_start_time' => $start,
        'preshow_start_time' => $start,
        'feature_start_time' => $featureStart,
        'end_time' => $end,
        'session_type' => 'public',
        'seating_mode' => ((int) cst11_value($venue, 'Venue_Map_Type', 0)) > 0
            ? 'reserved'
            : 'general_admission',
        'sales_channels' => $salesChannels,
        'presentation' => array(
            'video_format' => '2D',
            'audio_format' => cst11_value($venue, 'Venue_Digital_Sound') ? '5.1' : null,
        ),
        'accessibility' => array(
            'open_captions' => (bool) cst11_value($session, 'Session_Open_Caption', false),
            'closed_captions' => (bool) cst11_value($session, 'Session_Closed_Caption', false),
            'audio_description' => (bool) cst11_value($session, 'Session_Audio_Description', false),
            'assistive_listening' => (bool) cst11_value($session, 'Session_Hearing_Assist_Loop', false),
            'hearing_loop' => (bool) cst11_value($session, 'Session_Hearing_Assist_Loop', false),
        ),
        'offers' => venue11_offers($session, $tickets, $priceList, $priceGroups, $classId),
        'availability' => array(
            'status' => $cancelled
                ? 'cancelled'
                : ($remaining === 0 ? 'sold_out' : 'on_sale'),
            'updated_at' => $generatedAt,
            'seats_total' => $cancelled ? null : ($capacity ?: null),
            'seats_available' => $cancelled ? null : $remaining,
        ),
        'booking_url' => filter_var($bookingUrl, FILTER_VALIDATE_URL) ? $bookingUrl : null,
        'updated_at' => $generatedAt,
        'provenance' => array(
            'source_id' => 'venuemaster-session-' . $sessionId,
            'source_name' => 'VenueMaster VenueSchedule',
            'retrieved_at' => $generatedAt,
            'method' => 'authoritative',
        ),
        'extensions' => array(
            'venuemaster' => array(
                'price_group_code' => cst11_value($session, 'Session_Price_Group_Code'),
                'source_seats_remaining' => $remainingRaw,
                'suppress_signage' => cst11_value($session, 'Session_Suppress_Signage'),
                'suppress_external' => cst11_value($session, 'Session_Suppress_External'),
                'suppress_advertising' => cst11_value($session, 'Session_Suppress_Advertising'),
            ),
        ),
    ));
}

function venueMasterToCSTJSON11($venueData, $config)
{
    $generatedAt = gmdate('Y-m-d\\TH:i:s\\Z');
    $installation = cst11_value($venueData, 'Installation', array());
    $config['name'] = cst11_value($installation, 'Installation_Name', cst11_value($config, 'name'));
    $config['cinema_id'] = cst11_value(
        $installation,
        'Installation_Code',
        cst11_value($config, 'cinema_id')
    );
    $config['address'] = cst11_value(
        $installation,
        'Installation_Customer_Address',
        cst11_value($config, 'address')
    );
    $GLOBALS['venue11_currency'] = cst11_value($config, 'currency', 'AUD');
    $cinema = cst11_cinema($config);

    $venues = (array) cst11_value($venueData, 'Venues', array());
    $venueMap = venue11_index_by($venues, 'Venue_Code');
    $ratingMap = venue11_index_by(cst11_value($venueData, 'Ratings', array()), 'Rating_Code');
    $ticketMap = venue11_index_by(cst11_value($venueData, 'Tickets', array()), 'Ticket_Code');
    $groupMap = venue11_index_by(cst11_value($venueData, 'Price_Groups', array()), 'Price_Group_Code');
    $priceList = (array) cst11_value($venueData, 'Price_List', array());

    $movies = (array) cst11_value($venueData, 'Movies', array());
    $suppressedFilmIds = array();
    foreach ($movies as $movie) {
        if (cst11_value($movie, 'Movie_Suppress_Results', false)) {
            $suppressedFilmIds[(string) cst11_value($movie, 'Movie_Code')] = true;
        }
    }
    $films = array();
    foreach ($movies as $movie) {
        if (cst11_non_empty(cst11_value($movie, 'Movie_Code'))
            && cst11_non_empty(cst11_value($movie, 'Movie_Name'))
            && !isset($suppressedFilmIds[(string) cst11_value($movie, 'Movie_Code')])) {
            $films[] = venue11_film($movie, $config, $ratingMap, $generatedAt);
        }
    }
    $auditoria = array();
    foreach ($venues as $venue) {
        if (cst11_non_empty(cst11_value($venue, 'Venue_Code'))) {
            $auditoria[] = venue11_auditorium($venue, $generatedAt);
        }
    }
    $sessions = array();
    foreach ((array) cst11_value($venueData, 'Sessions', array()) as $session) {
        if (cst11_non_empty(cst11_value($session, 'Session_Index'))
            && cst11_non_empty(cst11_value($session, 'Session_Movie_Code'))
            && cst11_non_empty(cst11_value($session, 'Session_Date_Time'))
            && !cst11_value($session, 'Session_Suppress_External', false)
            && !isset($suppressedFilmIds[(string) cst11_value($session, 'Session_Movie_Code')])) {
            $sessions[] = venue11_session(
                $session,
                $config,
                $venueMap,
                $ticketMap,
                $priceList,
                $groupMap,
                $generatedAt
            );
        }
    }
    return cst11_feed($config, $cinema, $films, $auditoria, $sessions, 'VenueMaster');
}
