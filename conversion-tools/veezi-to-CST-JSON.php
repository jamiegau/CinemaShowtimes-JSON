<?php
// User can edit this array to add/override missing cinema variables
$userCinemaOverrides = [
    // Example: Uncomment and fill in any missing fields
    // 'cinema_id' => 'EXC-AUS-CNAME',
    // 'name' => 'Example Cinemas Name',
    // 'timezone' => 'Australia/Melbourne',
    // 'lat' => -34.8720,
    // 'lon' => 150.6020,
    // 'address' => '123 Princes Hwy, Port Melboourne, VIC 3207, AU',
    // 'phone' => '+61 3 5555 1234',
    // 'website' => 'https://examplecinemas.com/',
    // 'google_place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
    // 'google_business_id' => '12345678901234567890'
];

// Veezi API endpoint URL (replace with actual endpoint)
$veeziUrl = 'https://api.veezi.com/v1/showtimes';
$veeziApiKey = 'YOUR_VEEZI_API_KEY'; // Replace with your actual API key

// Fetch Veezi data
$options = [
    'http' => [
        'header' => "X-API-KEY: $veeziApiKey\r\nAccept: application/json\r\n"
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($veeziUrl, false, $context);
if ($response === false) {
    die('Error fetching Veezi API data');
}
$veeziData = json_decode($response, true);

function veeziToCSTJSON($veeziData, $userCinemaOverrides) {
    $cinema = [
        'cinema_id' => $veeziData['cinema']['id'] ?? '',
        'name' => $veeziData['cinema']['name'] ?? '',
        'timezone' => $veeziData['cinema']['timezone'] ?? '',
        'lat' => $veeziData['cinema']['lat'] ?? null,
        'lon' => $veeziData['cinema']['lon'] ?? null,
        'address' => $veeziData['cinema']['address'] ?? '',
        'phone' => $veeziData['cinema']['phone'] ?? '',
        'website' => $veeziData['cinema']['website'] ?? '',
        'google_place_id' => $veeziData['cinema']['google_place_id'] ?? '',
        'google_business_id' => $veeziData['cinema']['google_business_id'] ?? ''
    ];
    // Merge user overrides
    $cinema = array_merge($cinema, $userCinemaOverrides);

    $cst = [
        'spec' => 'cinemashowtimes-json/1.0',
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'ttl_seconds' => 900,
        'cinema' => $cinema,
        'films' => [],
        'auditoria' => [],
        'sessions' => []
    ];

    // Map films
    foreach ($veeziData['films'] as $film) {
        $cst['films'][] = [
            'film_id' => $film['id'] ?? '',
            'title' => $film['title'] ?? '',
            'alt_titles' => $film['alt_titles'] ?? [],
            'identifiers' => [
                'eidr' => $film['eidr'] ?? null,
                'imdb' => $film['imdb'] ?? null,
                'isan' => $film['isan'] ?? null
            ],
            'runtime_minutes' => $film['runtime'] ?? null,
            'rating' => $film['rating'] ?? null,
            'languages' => $film['languages'] ?? [],
            'distributor' => $film['distributor'] ?? '',
            'assets' => $film['assets'] ?? []
        ];
    }

    // Map auditoria
    foreach ($veeziData['auditoria'] as $aud) {
        $cst['auditoria'][] = [
            'auditorium_id' => $aud['id'] ?? '',
            'name' => $aud['name'] ?? '',
            'attributes' => $aud['attributes'] ?? [],
            'seat_count' => $aud['seat_count'] ?? null,
            'seat_classes' => $aud['seat_classes'] ?? [],
            'seatmap_url' => $aud['seatmap_url'] ?? ''
        ];
    }

    // Map sessions
    foreach ($veeziData['sessions'] as $sess) {
        $cst['sessions'][] = [
            'session_id' => $sess['id'] ?? '',
            'film_id' => $sess['film_id'] ?? '',
            'auditorium_id' => $sess['auditorium_id'] ?? '',
            'preshow_start_time_local' => $sess['preshow_start_time_local'] ?? null,
            'advertised_start_time_local' => $sess['advertised_start_time_local'] ?? null,
            'feature_start_time_local' => $sess['feature_start_time_local'] ?? null,
            'credits_time_local' => $sess['credits_time_local'] ?? null,
            'end_time_local' => $sess['end_time_local'] ?? null,
            'start_time_local' => $sess['start_time_local'] ?? null,
            'start_time_utc' => $sess['start_time_utc'] ?? null,
            'attributes' => $sess['attributes'] ?? [],
            'pricing' => $sess['pricing'] ?? [],
            'availability_by_class' => $sess['availability_by_class'] ?? [],
            'booking_url' => $sess['booking_url'] ?? '',
            'checksum' => $sess['checksum'] ?? ''
        ];
    }
    return $cst;
}

// Convert and output as JSON
$cstJson = veeziToCSTJSON($veeziData, $userCinemaOverrides);
header('Content-Type: application/json');
echo json_encode($cstJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>
