<?php

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php generate-converter-fixtures.php OUTPUT_DIR\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$output = $argv[1];
if (!is_dir($output) && !mkdir($output, 0777, true)) {
    fwrite(STDERR, "Unable to create output directory\n");
    exit(2);
}

require_once $root . '/conversion-tools/Veezi/veezi-mapper.php';
require_once $root . '/conversion-tools/VenueMaster/venue-master-mapper.php';
require_once $root . '/conversion-tools/Vista/vista-digital-mapper.php';

$baseConfig = array(
    'timezone' => 'Australia/Melbourne',
    'language' => 'en-AU',
    'territory' => 'AU',
    'currency' => 'AUD',
    'classification_system' => 'Australian Classification',
    'canonical_base_url' => 'https://cinema.example.test',
    'canonical_feed_url' => 'https://cinema.example.test/showtimes.json',
    'publisher' => array(
        'name' => 'Example Cinema',
        'url' => 'https://cinema.example.test',
    ),
);

$veeziFixture = json_decode(file_get_contents($root . '/tests/fixtures/veezi.json'), true);
$veeziConfig = array_merge($baseConfig, array(
    'feed_id' => 'veezi-example',
    'cinema_id' => 'VEEZI-CINEMA',
    'name' => 'Example Veezi Cinema',
    'latitude' => -37.833,
    'longitude' => 144.933,
    'postal_address' => array(
        'street_address' => '123 Example Street',
        'locality' => 'Melbourne',
        'region' => 'VIC',
        'postal_code' => '3000',
        'country' => 'AU',
    ),
));
$veezi = veeziToCSTJSON11(
    $veeziFixture['films'],
    $veeziFixture['sessions'],
    $veeziFixture['screens'],
    $veeziFixture['websessions'],
    $veeziConfig
);
if ($veezi['spec'] !== 'cinemashowtimes-json/1.1'
    || empty($veezi['auditoria'])
    || $veezi['sessions'][0]['booking_url'] !== 'https://tickets.example.test/session/702') {
    fwrite(STDERR, "Veezi mapping assertions failed\n");
    exit(1);
}
file_put_contents($output . '/veezi-1.1.json', json_encode($veezi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$venueFixture = json_decode(
    file_get_contents($root . '/conversion-tools/VenueMaster/venue-schedule-example_pretty.json'),
    true
);
$venueConfig = array_merge($baseConfig, array(
    'feed_id' => 'venuemaster-example',
    'cinema_id' => 'BORONA',
    'name' => 'Example VenueMaster Cinema',
));
$venue = venueMasterToCSTJSON11($venueFixture, $venueConfig);
$hasCancelled = false;
$hasOffers = false;
foreach ($venue['sessions'] as $session) {
    $hasCancelled = $hasCancelled || $session['status'] === 'cancelled';
    $hasOffers = $hasOffers || !empty($session['offers']);
}
if (!$hasCancelled || !$hasOffers) {
    fwrite(STDERR, "VenueMaster cancellation/offer mapping assertions failed\n");
    exit(1);
}
file_put_contents($output . '/venuemaster-1.1.json', json_encode($venue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$vistaFixture = json_decode(file_get_contents($root . '/tests/fixtures/vista.json'), true);
$vistaConfig = array_merge($baseConfig, array(
    'feed_id' => 'vista-example',
    'cinema_id' => 'SITE-1',
    'name' => 'Example Vista Cinema',
    'booking_url_template' => 'https://tickets.example.test/showtime/{session_id}',
));
$vista = vistaDigitalToCSTJSON11(
    $vistaFixture['site'],
    $vistaFixture['films'],
    $vistaFixture['showtimes'],
    $vistaConfig
);
if (empty($vista['films'][0]['localized']['fr']['title'])
    || empty($vista['sessions'])
    || empty($vista['auditoria'])) {
    fwrite(STDERR, "Vista localized/showtime/screen mapping assertions failed\n");
    exit(1);
}
file_put_contents($output . '/vista-1.1.json', json_encode($vista, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Generated Veezi, VenueMaster and Vista 1.1 fixtures\n";
