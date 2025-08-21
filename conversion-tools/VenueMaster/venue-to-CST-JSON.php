<?php
//
// Notes on how to use this conversion tool.
// typically the venue POS system makes this JSON file available.
// The Cinema is expected to enable port forwardsing to a custom port on the
//  venue POS system from the internet ROUTER.
// for example.  Static IP of router on internet: 123.1.2.3
// port forward port:  43210
// The url: https://123.1.2.3:43210/VenueSchedule.json would then return the 
// venue server JSON file.
// You can then point edit this file below to the URL required.
// Place this file on any php capable web server.
//
// Use a browser to goto that URL where you placed the php file
// Example:  https://My-server/venue-to-CST-JSON.php
// And you well get the converted CinemaShowtimes-JSON version of the data.
//
// User can edit this array to add/override missing cinema variables
$userCinemaOverrides = [
  // 'cinema_id' => 'VENUE-001',  // available in data file
  // 'name' => 'Example Cinema',  // available in data file
  'timezone' => 'Australia/Melbourne',
  'lat' => -37.861874,
  'lon' => 145.286216,
  // 'address' => '123 Cinema Street, Sydney, NSW 2000, AU',  // available in data file
  'phone' => '+61 2 1234 5678',
  'website' => 'https://metroboronia.com.au/',
  'google_place_id' => 'ChIJifMJ6GM71moR9uoZ7VFEjeI',
  // 'google_business_id' => ''    // owner of business on google must look up
];

// Path to the VenueMaster JSON local example of what is expected to come back
//$venueJsonFile = 'venue-schedule-example.json';

// Read the JSON file from https://103.21.156.203:4026/VenueSchedule.json
$venueJsonFile = 'https://103.21.156.203:4026/VenueSchedule.json';

// Use file_get_contents with SSL context to handle HTTPS
$sslOptions = [
  "ssl" => [
    "verify_peer" => false,
    "verify_peer_name" => false
  ]
];
$context = stream_context_create($sslOptions);
$venueJson = file_get_contents($venueJsonFile, false, $context);
if ($venueJson === false) {
  error_log('Error reading VenueMaster JSON file: ' . $venueJsonFile);
  die('Error reading VenueMaster JSON file');
}
// Clean up the JSON string before parsing
$venueJson = preg_replace('/[\x00-\x1F\x7F]/', '', $venueJson); // Remove control characters
$venueJson = trim($venueJson);

$venueData = json_decode($venueJson, true);
if ($venueData === null) {
  $jsonError = json_last_error_msg();
  error_log('Error parsing VenueMaster JSON file: ' . $jsonError);
  die('Error parsing VenueMaster JSON file: ' . $jsonError);
}

function venueMasterToCSTJSON($venueData, $userCinemaOverrides)
{
  // Setup $cinema variable, using Installation->Installation_Name for name
  $cinema = $userCinemaOverrides;
  if (isset($venueData['Installation']['Installation_Name'])) {
    $cinema['name'] = $venueData['Installation']['Installation_Name'];
  }
  if (isset($venueData['Installation']['Installation_Code'])) {
    $cinema['cinema_id'] = $venueData['Installation']['Installation_Code'];
  }
  if (isset($venueData['Installation']['Installation_Customer_Address'])) {
    $cinema['address'] = $venueData['Installation']['Installation_Customer_Address'];
  }


  $cst = [
    'spec' => 'cinemashowtimes-json/1.0',
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'ttl_seconds' => 900,
    'cinema' => $cinema,
    'films' => [],
    'auditoria' => [],
    'sessions' => []
  ];

  // Venues in the JSON file equate to Auditoria
  if (isset($venueData['Venues'])) {
    foreach ($venueData['Venues'] as $venue) {
      $auditorium = [];
      // attributes first
      $attributes = [];
      if ($venue['Venue_Hearing'] == true) {
        $attributes[] = 'Assistive Listening';
      }
      if ($venue['Venue_Premium'] == true) {
        $attributes[] = 'Premium Seating';
      } if ($venue['Venue_Digital_Projection'] == true) {
        $attributes[] = '2D';
      }
      if ($venue['Venue_Digital_Sound'] == true) {
        $attributes[] = '5.1';
      }
      $auditorium['attributes'] = $attributes;

      if ($venue['Venue_Normal_Capacity']) {
        $auditorium['seat_count'] = $venue['Venue_Normal_Capacity'];
      }

      if ($venue['Venue_Code']) {
        $auditorium['auditorium_id'] = $venue['Venue_Code'];
      }

      if ($venue['Venue_Name']) {
        $auditorium['name'] = $venue['Venue_Name'];
      }

      $seat_classes = [];
      if ($venue['Venue_Premium'] == true) {
        $seat_classes['class_id'] = 'premium';
        $seat_classes['display_name'] = 'Premium';
        $seat_classes['features'] = ['Recliner'];
      } else {
        $seat_classes['class_id'] = 'standard';
        $seat_classes['display_name'] = 'Standard';
        $seat_classes['features'] = [];
      };

      $auditorium['seat_classes'] = [$seat_classes];

      $cst['auditoria'][] = $auditorium;
    }
  }

  // Map movies to films
  if (isset($venueData['Movies'])) {
    foreach ($venueData['Movies'] as $movie) {
      $film = [];
      $film['film_id'] = $movie['Movie_Code'] ?? '';
      $film['title'] = $movie['Movie_Name'] ?? '';
      
      $identifiers = [];
      if ($movie['Movie_IMDB_ID'] != '') {
        $identifiers['imdb'] = $movie['Movie_IMDB_ID'];
      }
      if ($movie['Movie_EIDR'] != '') {
        $identifiers['eidr'] = $movie['Movie_EIDR'];
      }
      if ($movie['Movie_TMDb_ID'] != '') {
        $identifiers['tmdb'] = $movie['Movie_TMDb_ID'];
      }

      $film['identifiers'] = $identifiers;

      if ($movie['Movie_Length']) {
        $film['runtime_minutes'] = $movie['Movie_Length'];
      }

      if ($movie['Movie_Rating_Code'] != "") {
        $film['rating'] = $movie['Movie_Rating_Code'];
      }

      if ($movie['Movie_Consumer_Advice'] != "") {
        $film['content'] = $movie['Movie_Consumer_Advice'];
      }

      if ($movie['Movie_Distributor_Code']) {
        $film['distributor'] = $movie['Movie_Distributor_Code'];
      }

      if ($movie['Movie_Synopsis'] != "") {
        $film['synopsis'] = $movie['Movie_Synopsis'];
      }

      if ($movie['Movie_Genre'] != "") {
        $genre = [];
        // split up $movie['Movie_Genre'] by commas, then trim and add them to $genre
        $genres = explode(' ', $movie['Movie_Genre']);
        foreach ($genres as $g) {
          $genre[] = trim($g);
        }
        $film['genre'] = $genre;
      }

      if ($movie['Movie_Talent'] != "") {
        $cast = [];
        // split up $movie['Movie_Genre'] by commas, then trim and add them to $genre
        $cast_names = explode(',', $movie['Movie_Talent']);
        $order = 0;
        foreach ($cast_names as $c) {
          $cast[] = [
            'name' => trim($c),
            'order' => $order++
          ];
        }
        $film['credits'] = $cast;
      }

      if ($movie['Movie_Release_Date'] != "") {
        $rel_date = substr($movie['Movie_Release_Date'], 0 , 8);
        // convert to date object
        $rel_date_date = DateTime::createFromFormat('Ymd', $rel_date);

        $now = new DateTime();
        if ($rel_date_date < $now) {
          $film['status'] = 'released';
        } else {
          $film['status'] = 'coming_soon';
        }
        // create YYYY-MM-DD version of date
        $film['release_date'] = $rel_date_date->format('Y-m-d');
      }

      // Add trailer if available
      if (!empty($movie['TrailerUrl'])) {
        $film['assets']['trailers'][] = [
          'type' => 'official',
          'url' => $movie['TrailerUrl'],
          'primary' => true
        ];
      }

      $cst['films'][] = $film;
    }
  }

  // Map sessions
  if (isset($venueData['Sessions'])) {
    foreach ($venueData['Sessions'] as $session) {
      $sessionData = [];

      if ($session['Session_Cancelled']) {
        continue;
      }

      if ($session['Session_Index']) {
        $sessionData['session_id'] = (string)$session['Session_Index'];
      }

      if ($session['Session_Movie_Code'] != "") {
        $sessionData['film_id'] = $session['Session_Movie_Code'];
      }

      if ($session['Session_Venue_Code'] != "") {
        $sessionData['auditorium_id'] = $session['Session_Venue_Code'];
      }
      //
      // start times
      if ($session['Session_Date_Time'] != "") {
        $t = $session['Session_Date_Time'];
        $t_date = DateTime::createFromFormat('Ymd', substr($t, 0, 8));
        // as 2025-08-18T19:20:00
        $t_str = $t_date->format('Y-m-d\TH:i:s');
        $sessionData['advertised_start_time_local'] = $t_str;
        $sessionData['start_time_local'] = $t_str;
        // start time as start_time_utc
        // convert to UTC
        $t_date->setTimezone(new DateTimeZone('UTC'));
        $sessionData['start_time_utc'] = $t_date->format('Y-m-d\TH:i:s');
      }

      if ($session['Session_End_Time'] != "") {
        $t = $session['Session_End_Time'];
        $t_date = DateTime::createFromFormat('Ymd', substr($t, 0, 8));
        // as 2025-08-18T19:20:00
        $t_str = $t_date->format('Y-m-d\TH:i:s');
        $sessionData['end_time_local'] = $t_str;
      }
      //
      // attributes, just basic ones for now
      $attributes = [];
      $attributes['video_format'] = '2D';
      $attributes['audio_format'] = '5.1';
            // accessibility
      $accessibility = [];
      $accessibility['ccap'] = $session['Session_Open_Caption'] ?? false;
      $accessibility['ocap'] = $session['Session_Open_Caption'] ?? false;
      $accessibility['descriptive_audio'] = $session['Session_Audio_Description'] ?? false;
      $accessibility['hearing_assisted_loop'] = $session['Session_Hearing_Assist_Loop'] ?? false;
      $attributes['accessibility'] = $accessibility;
      //
      $sessionData['attributes'] = $attributes;

      // purchase ticket URL
      if ($session['Session_URL'] != '') {
        $sessionData['booking_url'] = $session['Session_URL'];
      }

      // capacity
      // NOTE, Session_Initial_Seats fund in the session is NOT accurate due to moves move screens.
      // Lookup the Auditorium based on the Session_Venue_Code, and use that to find the correct seating capacity.
      if ($session['Session_Venue_Code']) {
        $auditorium = array_filter($venueData['Venues'], function($a) use ($session) {
          return $a['Venue_Code'] === $session['Session_Venue_Code'];
        });
        if (!empty($auditorium)) {
          $sessionData['seating_capacity'] = reset($auditorium)['Venue_Normal_Capacity'];
        }
      }
      //
      if ($session['Session_Seats_Remaining']) {
        $sessionData['seats_available'] = $session['Session_Seats_Remaining'];
      }
      if ($session['Session_Initial_Seats'] && $session['Session_Seats_Remaining']) {
        $availability_by_class = [];
        $availability_by_class['class_id'] = 'standard';
        $availability_by_class['seats_total'] = $sessionData['seating_capacity'];
        $availability_by_class['seats_available'] = $sessionData['seats_available'];
      }
      $sessionData['availability_by_class'] = $availability_by_class;

      $cst['sessions'][] = $sessionData;
    }
  }

  return $cst;
}

$cstJson = venueMasterToCSTJSON($venueData, $userCinemaOverrides);
header('Content-Type: application/json');
echo json_encode($cstJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>