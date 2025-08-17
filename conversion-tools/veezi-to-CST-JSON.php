<?php
// User can edit this array to add/override missing cinema variables
$userCinemaOverrides = [
    'cinema_id' => 'EXC-AUS-FORBESCINEMA',
    'name' => 'Forbes Cinema',
    'timezone' => 'Australia/Sydney',
    'lat' => -33.3860526,
    'lon' => 148.0098134,
    'address' => '41 Templar Street, Forbes, NSW 2871, AU',
    'phone' => '+61 2 6852 14884',
    'website' => 'https://forbescinema.com.au/',
    'google_place_id' => 'ChIJVafZGiuNGmsRBy1AkGRlFNw',
    'google_business_id' => '16389629735730666012'
];

$veeziUrl_session = 'https://api.oz.veezi.com/v1/session';
$veeziUrl_film = 'https://api.oz.veezi.com/v4/film';
$veeziApiKey = 'b88cvh3rhcdmye5380t297cn5g';

$options = [
    'http' => [
        'header' => "VeeziAccessToken: $veeziApiKey\r\nAccept: application/json\r\n"
    ]
];
$context = stream_context_create($options);
$veezi_session = file_get_contents($veeziUrl_session, false, $context);
if ($veezi_session === false) {
    error_log('Error fetching Veezi API session data');
    die('Error fetching Veezi API session data');
}
$veezi_session_data = json_decode($veezi_session, true);

$veezi_film = file_get_contents($veeziUrl_film, false, $context);
if ($veezi_film === false) {
    error_log('Error fetching Veezi API film data');
    die('Error fetching Veezi API film data');
}
$veezi_film_data = json_decode($veezi_film, true);

function veeziToCSTJSON($films, $sessions, $userCinemaOverrides) {
    $cinema = $userCinemaOverrides;
    $cst = [
        'spec' => 'cinemashowtimes-json/1.0',
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'ttl_seconds' => 900,
        'cinema' => $cinema,
        'films' => [],
        'auditoria' => [], // Placeholder, as Veezi does not provide this directly
        'sessions' => []
    ];

    // Map films
    foreach ($films as $film) {
        // Build credits from People array
        $credits = [
            'directors' => [],
            'producers' => [],
            'writers' => [],
            'cast' => []
        ];
        if (!empty($film['People'])) {
            foreach ($film['People'] as $person) {
                $entry = [
                    'name' => trim(($person['FirstName'] ?? '') . ' ' . ($person['LastName'] ?? '')),
                    'identifiers' => [
                        // Add more mappings if available
                    ]
                ];
                switch (strtolower($person['Role'] ?? '')) {
                    case 'director':
                        $credits['directors'][] = $entry;
                        break;
                    case 'producer':
                        $credits['producers'][] = $entry;
                        break;
                    case 'writer':
                    case 'screenwriter':
                    case 'screenplay':
                        $entry['role'] = $person['Role'];
                        $credits['writers'][] = $entry;
                        break;
                    case 'actor':
                        $credits['cast'][] = $entry;
                        break;
                    default:
                        // Ignore or add to a misc group if needed
                        break;
                }
            }
        }
        $cst['films'][] = [
            'film_id' => $film['Id'] ?? '',
            'title' => $film['Title'] ?? '',
            'alt_titles' => [$film['ShortName'] ?? ''],
            'identifiers' => [
                'eidr' => $film['EIDR'] ?? null,
                'imdb' => $film['IMDB'] ?? null,
                'isan' => $film['ISAN'] ?? null
            ],
            'runtime_minutes' => $film['Duration'] ?? null,
            'rating' => $film['Rating'] ?? null,
            'content' => $film['Content'] ?? '',
            'languages' => [$film['AudioLanguage'] ?? ''],
            'distributor' => $film['Distributor'] ?? '',
            'assets' => [
                'poster' => [
                    'small' => $film['FilmPosterThumbnailUrl'] ?? '',
                    'large' => $film['FilmPosterUrl'] ?? ''
                ],
                'banner' => [
                    'wide' => $film['BannerImageUrl'] ?? ''
                ],
                'thumbnail' => [
                    'square' => $film['FilmPosterThumbnailUrl'] ?? ''
                ],
                'backdrop' => [
                    'large' => $film['BackdropImageUrl'] ?? ''
                ],
                'logo' => [
                    'transparent' => $film['LogoImageUrl'] ?? ''
                ],
                'trailers' => [
                    [ 'type' => 'official', 'url' => $film['FilmTrailerUrl'] ?? '', 'primary' => true ]
                ]
            ],
            'credits' => $credits,
            'synopsis' => $film['Synopsis'] ?? '',
            'genre' => $film['Genre'] ?? '',
            'release_date' => $film['OpeningDate'] ?? '',
            'status' => 'released',
            'format' => $film['Format'] ?? ''
        ];
    }

    // Map sessions
    foreach ($sessions as $sess) {
        $cst['sessions'][] = [
            'session_id' => $sess['Id'] ?? '',
            'film_id' => $sess['FilmId'] ?? '',
            'auditorium_id' => $sess['ScreenId'] ?? '',
            'preshow_start_time_local' => $sess['PreShowStartTime'] ?? '',
            'advertised_start_time_local' => $sess['FeatureStartTime'] ?? '',
            'feature_start_time_local' => $sess['FeatureStartTime'] ?? '',
            'credits_time_local' => $sess['FeatureEndTime'] ?? '',
            'end_time_local' => $sess['CleanupEndTime'] ?? '',
            'start_time_local' => $sess['FeatureStartTime'] ?? '',
            'start_time_utc' => '', // Veezi does not provide UTC, conversion needed if required
            'attributes' => [
                'video_format' => $sess['FilmFormat'] ?? '',
                'audio_format' => '', // Not provided by Veezi
                'accessibility' => [
                    'ccap' => false,
                    'ccap_languages' => [],
                    'ocap' => false,
                    'descriptive_audio' => false,
                    'wheelchair' => false
                ],
                'language' => '', // Not provided by Veezi
                'subtitle_languages' => []
            ],
            'pricing' => [], // Not provided by Veezi
            'availability_by_class' => [], // Not provided by Veezi
            'booking_url' => '', // Not provided by Veezi
            'checksum' => ''
        ];
    }
    return $cst;
}

$cstJson = veeziToCSTJSON($veezi_film_data, $veezi_session_data, $userCinemaOverrides);
header('Content-Type: application/json');
echo json_encode($cstJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>