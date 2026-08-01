<?php
/**
 * Plugin Name: d-cine.com Sessiontimes Data
 * Description: Lossless CinemaShowtimes-JSON 1.1 import, local storage, admin CRUD and feed export.
 * Version: 0.2.0
 * Author: James Gardiner
 * Text Domain: wp-d-cine-sessiontimes-data
 */

if (!defined('ABSPATH')) {
  exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/trait-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-films.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-sessions.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-cinema.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-auditoria.php';

class WP_DCine_Sessiontimes_Data
{
  private static $instance = null;
  private $option_name = 'wp_d_cine_settings';
  private $feed_option_name = 'wp_d_cine_feed_metadata_11';

  use WP_DCine_Settings_Trait;
  use WP_DCine_Films_Trait;
  use WP_DCine_Sessions_Trait;
  use WP_DCine_Cinema_Trait;
  use WP_DCine_Auditoria_Trait;

  public static function instance()
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function __construct()
  {
    register_activation_hook(__FILE__, array($this, 'activate'));
    register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    add_filter('cron_schedules', array($this, 'register_cron_intervals'));
    add_action('admin_menu', array($this, 'admin_menu'));
    add_action('admin_init', array($this, 'register_settings'));
    add_action('admin_post_wp_d_cine_save_film', array($this, 'handle_save_film'));
    add_action('admin_post_wp_d_cine_delete_film', array($this, 'handle_delete_film'));
    add_action('admin_post_wp_d_cine_save_session', array($this, 'handle_save_session'));
    add_action('admin_post_wp_d_cine_delete_session', array($this, 'handle_delete_session'));
    add_action('admin_post_wp_d_cine_save_auditorium', array($this, 'handle_save_auditorium'));
    add_action('admin_post_wp_d_cine_delete_auditorium', array($this, 'handle_delete_auditorium'));
    add_action('rest_api_init', array($this, 'register_rest_routes'));
    add_action('wp_d_cine_scheduled_fetch', array($this, 'scheduled_fetch'));
    add_action('admin_post_wp_d_cine_manual_fetch', array($this, 'manual_fetch'));
  }

  public function activate()
  {
    if (get_option($this->option_name, false) === false) {
      add_option($this->option_name, array(
        'endpoint_url' => '',
        'api_key' => '',
        'local_api_key' => '',
        'public_feed' => '1',
        'auto_mode' => '0',
        'interval_minutes' => 15,
      ), '', false);
    }
    $this->create_tables();
    $this->ensure_cron();
  }

  public function deactivate()
  {
    wp_clear_scheduled_hook('wp_d_cine_scheduled_fetch');
  }

  public function register_cron_intervals($schedules)
  {
    for ($minutes = 1; $minutes <= 60; $minutes++) {
      $key = 'wp_d_cine_interval_' . $minutes;
      $schedules[$key] = array(
        'interval' => $minutes * 60,
        'display' => sprintf('Every %d minutes', $minutes),
      );
    }
    return $schedules;
  }

  private function ensure_cron()
  {
    $opts = get_option($this->option_name, array());
    $enabled = isset($opts['auto_mode']) && $opts['auto_mode'] === '1';
    $minutes = isset($opts['interval_minutes']) ? max(1, min(60, intval($opts['interval_minutes']))) : 15;
    wp_clear_scheduled_hook('wp_d_cine_scheduled_fetch');
    if ($enabled) {
      wp_schedule_event(time(), 'wp_d_cine_interval_' . $minutes, 'wp_d_cine_scheduled_fetch');
    }
  }

  private function create_tables()
  {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $films_table = $wpdb->prefix . 'cine_films';
    dbDelta("CREATE TABLE {$films_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      film_id VARCHAR(191) NOT NULL,
      title TEXT NOT NULL,
      canonical_url TEXT NULL,
      runtime_minutes INT NULL,
      rating VARCHAR(32) NULL,
      synopsis LONGTEXT NULL,
      identifiers LONGTEXT NULL,
      assets LONGTEXT NULL,
      credits LONGTEXT NULL,
      scores LONGTEXT NULL,
      status VARCHAR(32) NULL,
      release_date DATE NULL,
      pre_sales_start DATE NULL,
      presales LONGTEXT NULL,
      raw_payload LONGTEXT NULL,
      read_only TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY film_id (film_id)
    ) {$charset_collate}");

    $sessions_table = $wpdb->prefix . 'cine_sessions';
    dbDelta("CREATE TABLE {$sessions_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      session_id VARCHAR(191) NOT NULL,
      cinema_id VARCHAR(191) NULL,
      film_id VARCHAR(191) NOT NULL,
      auditorium_id VARCHAR(191) NULL,
      canonical_url TEXT NULL,
      status VARCHAR(32) NULL,
      start_time TEXT NULL,
      start_time_local DATETIME NULL,
      start_time_utc DATETIME NULL,
      attributes LONGTEXT NULL,
      pricing LONGTEXT NULL,
      availability LONGTEXT NULL,
      booking_url TEXT NULL,
      checksum VARCHAR(255) NULL,
      seating_capacity INT NULL,
      seats_available INT NULL,
      raw_payload LONGTEXT NULL,
      read_only TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY session_id (session_id),
      KEY film_id (film_id),
      KEY auditorium_id (auditorium_id)
    ) {$charset_collate}");

    $auditoria_table = $wpdb->prefix . 'cine_auditoria';
    dbDelta("CREATE TABLE {$auditoria_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      auditorium_id VARCHAR(191) NOT NULL,
      name VARCHAR(255) NULL,
      attributes LONGTEXT NULL,
      seat_count INT NULL,
      seat_classes LONGTEXT NULL,
      seatmap_url TEXT NULL,
      raw_payload LONGTEXT NULL,
      read_only TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY auditorium_id (auditorium_id)
    ) {$charset_collate}");
  }

  public function admin_menu()
  {
    $root_slug = 'wp-d-cine-root';
    add_menu_page('d-cine Sessiontimes', 'd-cine Sessiontimes', 'manage_options', $root_slug, array($this, 'settings_page'), 'dashicons-video-alt2', 26);
    add_submenu_page($root_slug, 'Settings', 'Settings', 'manage_options', $root_slug, array($this, 'settings_page'));
    add_submenu_page($root_slug, 'Cinema Info', 'Cinema Info', 'manage_options', 'wp-d-cine-cinema', array($this, 'cinema_page'));
    add_submenu_page($root_slug, 'Auditoria', 'Auditoria', 'manage_options', 'wp-d-cine-auditoria', array($this, 'auditoria_page'));
    add_submenu_page($root_slug, 'Films', 'Films', 'manage_options', 'wp-d-cine-films', array($this, 'films_page'));
    add_submenu_page($root_slug, 'Sessions', 'Sessions', 'manage_options', 'wp-d-cine-sessions', array($this, 'sessions_page'));
  }

  public function register_rest_routes()
  {
    register_rest_route('cine/v1', '/import', array(
      'methods' => WP_REST_Server::CREATABLE,
      'callback' => array($this, 'rest_import'),
      'permission_callback' => array($this, 'rest_can_import'),
    ));
    register_rest_route('cine/v1', '/export', array(
      'methods' => WP_REST_Server::READABLE,
      'callback' => array($this, 'rest_feed'),
      'permission_callback' => array($this, 'rest_can_read'),
    ));
    register_rest_route('cine/v1', '/showtimes', array(
      'methods' => WP_REST_Server::READABLE,
      'callback' => array($this, 'rest_feed'),
      'permission_callback' => array($this, 'rest_can_read'),
    ));
    register_rest_route('cine/v1', '/films/(?P<film_id>[A-Za-z0-9._~-]+)', array(
      'methods' => WP_REST_Server::READABLE,
      'callback' => array($this, 'rest_film'),
      'permission_callback' => array($this, 'rest_can_read'),
    ));
    register_rest_route('cine/v1', '/sessions/(?P<session_id>[A-Za-z0-9._~-]+)', array(
      'methods' => WP_REST_Server::READABLE,
      'callback' => array($this, 'rest_session'),
      'permission_callback' => array($this, 'rest_can_read'),
    ));
  }

  private function request_api_key($request)
  {
    $header = $request->get_header('X-CINE-API');
    return is_string($header) ? $header : '';
  }

  private function key_matches($provided, $option)
  {
    $opts = get_option($this->option_name, array());
    $expected = isset($opts[$option]) ? (string) $opts[$option] : '';
    return $expected !== '' && is_string($provided) && hash_equals($expected, $provided);
  }

  public function rest_can_import($request)
  {
    return $this->key_matches($this->request_api_key($request), 'api_key')
      ? true
      : new WP_Error('cine_forbidden', 'A valid X-CINE-API import key is required.', array('status' => 401));
  }

  public function rest_can_read($request)
  {
    $opts = get_option($this->option_name, array());
    if (!empty($opts['public_feed'])) {
      return true;
    }
    return $this->key_matches($this->request_api_key($request), 'local_api_key')
      ? true
      : new WP_Error('cine_forbidden', 'A valid X-CINE-API feed key is required.', array('status' => 401));
  }

  private function valid_rfc3339($value)
  {
    if (!is_string($value) || !preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $value)) {
      return false;
    }
    try {
      new DateTimeImmutable($value);
      return true;
    } catch (Exception $error) {
      return false;
    }
  }

  private function validate_feed_11($data)
  {
    if (!is_array($data) || (isset($data['spec']) ? $data['spec'] : '') !== 'cinemashowtimes-json/1.1') {
      return new WP_Error('cine_invalid_spec', 'Only cinemashowtimes-json/1.1 feeds are accepted.');
    }
    foreach (array('feed_id', 'mode', 'generated_at', 'publisher') as $field) {
      if (!isset($data[$field]) || $data[$field] === '') {
        return new WP_Error('cine_missing_field', 'Required feed field is missing: ' . $field);
      }
    }
    if (!in_array($data['mode'], array('full', 'delta'), true)) {
      return new WP_Error('cine_invalid_mode', 'Feed mode must be full or delta.');
    }
    if (!$this->valid_rfc3339($data['generated_at'])) {
      return new WP_Error('cine_invalid_generated_at', 'generated_at must be RFC 3339 with a UTC offset.');
    }
    if (!is_array($data['publisher']) || empty($data['publisher']['name'])) {
      return new WP_Error('cine_invalid_publisher', 'publisher.name is required.');
    }
    if ($data['mode'] === 'full') {
      $hasCinema = isset($data['cinema']) && is_array($data['cinema']);
      $hasCinemas = isset($data['cinemas']) && is_array($data['cinemas']) && !empty($data['cinemas']);
      if ($hasCinema === $hasCinemas) {
        return new WP_Error('cine_invalid_cinemas', 'A full feed must contain exactly one of cinema or cinemas.');
      }
      if (!isset($data['films']) || !is_array($data['films'])) {
        return new WP_Error('cine_missing_films', 'A full feed must contain a films array.');
      }
      $cinemaItems = $hasCinema ? array($data['cinema']) : $data['cinemas'];
      foreach ($cinemaItems as $cinema) {
        if (empty($cinema['cinema_id']) || empty($cinema['name']) || empty($cinema['timezone'])) {
          return new WP_Error('cine_invalid_cinema', 'Every cinema requires cinema_id, name and timezone.');
        }
      }
    } elseif (!isset($data['sequence'])) {
      return new WP_Error('cine_missing_sequence', 'A delta feed must contain sequence.');
    }
    foreach ((array) (isset($data['films']) ? $data['films'] : array()) as $film) {
      if (empty($film['film_id']) || empty($film['title'])) {
        return new WP_Error('cine_invalid_film', 'Every film requires film_id and title.');
      }
      if (!empty($film['canonical_url']) && !filter_var($film['canonical_url'], FILTER_VALIDATE_URL)) {
        return new WP_Error('cine_invalid_film_url', 'Film canonical_url must be an absolute URL.');
      }
    }
    foreach ((array) (isset($data['auditoria']) ? $data['auditoria'] : array()) as $auditorium) {
      if (empty($auditorium['auditorium_id']) || empty($auditorium['name'])) {
        return new WP_Error('cine_invalid_auditorium', 'Every auditorium requires auditorium_id and name.');
      }
    }
    foreach ((array) (isset($data['sessions']) ? $data['sessions'] : array()) as $session) {
      foreach (array('session_id', 'film_id', 'canonical_url', 'status', 'start_time') as $field) {
        if (empty($session[$field])) {
          return new WP_Error('cine_invalid_session', 'Every session requires ' . $field . '.');
        }
      }
      if (!in_array($session['status'], array('scheduled', 'cancelled', 'postponed', 'rescheduled'), true)) {
        return new WP_Error('cine_invalid_session_status', 'Session status is invalid.');
      }
      if (!filter_var($session['canonical_url'], FILTER_VALIDATE_URL)
        || !$this->valid_rfc3339($session['start_time'])) {
        return new WP_Error('cine_invalid_session_format', 'Session URL or start_time is invalid.');
      }
      if (isset($session['availability'])) {
        if (empty($session['availability']['status'])
          || empty($session['availability']['updated_at'])
          || !$this->valid_rfc3339($session['availability']['updated_at'])) {
          return new WP_Error('cine_invalid_availability', 'Availability requires status and RFC 3339 updated_at.');
        }
      }
      foreach ((array) (isset($session['offers']) ? $session['offers'] : array()) as $offer) {
        foreach (array('offer_id', 'price', 'currency', 'booking_url') as $field) {
          if (!isset($offer[$field]) || $offer[$field] === '') {
            return new WP_Error('cine_invalid_offer', 'Every offer requires ' . $field . '.');
          }
        }
      }
    }
    return true;
  }

  public function rest_import($request)
  {
    $data = json_decode($request->get_body(), true);
    if (!is_array($data)) {
      return new WP_Error('cine_invalid_json', 'Invalid JSON payload.', array('status' => 400));
    }
    $valid = $this->validate_feed_11($data);
    if (is_wp_error($valid)) {
      $valid->add_data(array('status' => 422));
      return $valid;
    }
    $result = $this->import_data($data);
    if (is_wp_error($result)) {
      return $result;
    }
    return new WP_REST_Response(array(
      'status' => 'ok',
      'spec' => 'cinemashowtimes-json/1.1',
      'feed_id' => $data['feed_id'],
      'mode' => $data['mode'],
    ), 200);
  }

  public function rest_feed($request)
  {
    $data = $this->export_data();
    $encoded = wp_json_encode($data);
    $etag = '"' . hash('sha256', $encoded) . '"';
    $headers = array(
      'ETag' => $etag,
      'Cache-Control' => 'public, max-age=' . intval(isset($data['ttl_seconds']) ? $data['ttl_seconds'] : 0),
      'Content-Type' => 'application/json; charset=utf-8',
    );
    if (trim((string) $request->get_header('If-None-Match')) === $etag) {
      return new WP_REST_Response(null, 304, $headers);
    }
    return new WP_REST_Response($data, 200, $headers);
  }

  public function rest_film($request)
  {
    $feed = $this->export_data();
    foreach ($feed['films'] as $film) {
      if ($film['film_id'] === $request['film_id']) {
        return rest_ensure_response($film);
      }
    }
    return new WP_Error('cine_not_found', 'Film not found.', array('status' => 404));
  }

  public function rest_session($request)
  {
    $feed = $this->export_data();
    foreach ($feed['sessions'] as $session) {
      if ($session['session_id'] === $request['session_id']) {
        return rest_ensure_response($session);
      }
    }
    return new WP_Error('cine_not_found', 'Session not found.', array('status' => 404));
  }

  public function manual_fetch()
  {
    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }
    check_admin_referer('wp_d_cine_manual_fetch');
    $this->scheduled_fetch();
    wp_safe_redirect(admin_url('admin.php?page=wp-d-cine-root'));
    exit;
  }

  public function scheduled_fetch()
  {
    $opts = get_option($this->option_name, array());
    if (empty($opts['endpoint_url'])) {
      return;
    }
    $args = array('timeout' => 30, 'headers' => array('Accept' => 'application/json'));
    if (!empty($opts['api_key'])) {
      $args['headers']['X-CINE-API'] = $opts['api_key'];
    }
    $resp = wp_remote_get($opts['endpoint_url'], $args);
    if (is_wp_error($resp)) {
      error_log('wp_d_cine fetch error: ' . $resp->get_error_message());
      return;
    }
    $status = wp_remote_retrieve_response_code($resp);
    if ($status < 200 || $status >= 300) {
      error_log('wp_d_cine fetch HTTP status: ' . $status);
      return;
    }
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $valid = $this->validate_feed_11($data);
    if (is_wp_error($valid)) {
      error_log('wp_d_cine rejected feed: ' . $valid->get_error_message());
      return;
    }
    $result = $this->import_data($data);
    if (is_wp_error($result)) {
      error_log('wp_d_cine import failed: ' . $result->get_error_message());
    }
  }

  private function json_or_null($value)
  {
    return isset($value) ? wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
  }

  private function mysql_datetime($value)
  {
    $timestamp = $value ? strtotime($value) : false;
    return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
  }

  private function import_data($data)
  {
    global $wpdb;
    $films_table = $wpdb->prefix . 'cine_films';
    $sessions_table = $wpdb->prefix . 'cine_sessions';
    $auditoria_table = $wpdb->prefix . 'cine_auditoria';

    $metadata = $data;
    unset($metadata['films'], $metadata['auditoria'], $metadata['sessions'], $metadata['deleted']);
    $existingMetadata = get_option($this->feed_option_name, array());
    update_option($this->feed_option_name, array_replace_recursive($existingMetadata, $metadata), false);

    foreach ((array) (isset($data['films']) ? $data['films'] : array()) as $film) {
      $film_id = $film['film_id'];
      $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$films_table} WHERE film_id = %s", $film_id));
      if ($existing && $existing->read_only) {
        continue;
      }
      $classification = !empty($film['classifications'][0]) ? $film['classifications'][0] : array();
      $release = !empty($film['releases'][0]) ? $film['releases'][0] : array();
      $payload = array(
        'film_id' => $film_id,
        'title' => $film['title'],
        'canonical_url' => isset($film['canonical_url']) ? esc_url_raw($film['canonical_url']) : null,
        'runtime_minutes' => isset($film['runtime_minutes']) ? intval($film['runtime_minutes']) : null,
        'rating' => isset($classification['code']) ? $classification['code'] : null,
        'synopsis' => isset($film['description']) ? $film['description'] : null,
        'identifiers' => $this->json_or_null(isset($film['identifiers']) ? $film['identifiers'] : null),
        'assets' => $this->json_or_null(isset($film['media']) ? $film['media'] : null),
        'credits' => $this->json_or_null(isset($film['credits']) ? $film['credits'] : null),
        'scores' => $this->json_or_null(isset($film['scores']) ? $film['scores'] : null),
        'status' => isset($film['status']) ? $film['status'] : null,
        'release_date' => isset($release['date']) ? $release['date'] : null,
        'pre_sales_start' => isset($film['pre_sales_start']) ? $film['pre_sales_start'] : null,
        'raw_payload' => $this->json_or_null($film),
      );
      $existing
        ? $wpdb->update($films_table, $payload, array('film_id' => $film_id))
        : $wpdb->insert($films_table, $payload);
    }

    foreach ((array) (isset($data['auditoria']) ? $data['auditoria'] : array()) as $auditorium) {
      if (empty($auditorium['auditorium_id'])) {
        continue;
      }
      $id = $auditorium['auditorium_id'];
      $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$auditoria_table} WHERE auditorium_id = %s", $id));
      if ($existing && $existing->read_only) {
        continue;
      }
      $seatmap = isset($auditorium['seatmap']) ? $auditorium['seatmap'] : array();
      $payload = array(
        'auditorium_id' => $id,
        'name' => isset($auditorium['name']) ? $auditorium['name'] : '',
        'attributes' => $this->json_or_null(isset($auditorium['attributes']) ? $auditorium['attributes'] : null),
        'seat_count' => isset($auditorium['seat_count']) ? intval($auditorium['seat_count']) : null,
        'seat_classes' => $this->json_or_null(isset($auditorium['seat_classes']) ? $auditorium['seat_classes'] : null),
        'seatmap_url' => isset($seatmap['layout_url']) ? esc_url_raw($seatmap['layout_url']) : null,
        'raw_payload' => $this->json_or_null($auditorium),
      );
      $existing
        ? $wpdb->update($auditoria_table, $payload, array('auditorium_id' => $id))
        : $wpdb->insert($auditoria_table, $payload);
    }

    foreach ((array) (isset($data['sessions']) ? $data['sessions'] : array()) as $session) {
      $session_id = $session['session_id'];
      $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sessions_table} WHERE session_id = %s", $session_id));
      if ($existing && $existing->read_only) {
        continue;
      }
      $availability = isset($session['availability']) ? $session['availability'] : array();
      $payload = array(
        'session_id' => $session_id,
        'cinema_id' => isset($session['cinema_id']) ? $session['cinema_id'] : null,
        'film_id' => $session['film_id'],
        'auditorium_id' => isset($session['auditorium_id']) ? $session['auditorium_id'] : null,
        'canonical_url' => esc_url_raw($session['canonical_url']),
        'status' => $session['status'],
        'start_time' => $session['start_time'],
        'start_time_local' => $this->mysql_datetime($session['start_time']),
        'start_time_utc' => $this->mysql_datetime($session['start_time']),
        'attributes' => $this->json_or_null(isset($session['presentation']) ? $session['presentation'] : null),
        'pricing' => $this->json_or_null(isset($session['offers']) ? $session['offers'] : null),
        'availability' => $this->json_or_null($availability),
        'booking_url' => isset($session['booking_url']) ? esc_url_raw($session['booking_url']) : null,
        'seating_capacity' => isset($availability['seats_total']) ? intval($availability['seats_total']) : null,
        'seats_available' => isset($availability['seats_available']) ? intval($availability['seats_available']) : null,
        'raw_payload' => $this->json_or_null($session),
      );
      $existing
        ? $wpdb->update($sessions_table, $payload, array('session_id' => $session_id))
        : $wpdb->insert($sessions_table, $payload);
    }

    if ($data['mode'] === 'full') {
      if (array_key_exists('films', $data)) {
        $this->prune_missing_entities($films_table, 'film_id', wp_list_pluck($data['films'], 'film_id'));
      }
      if (array_key_exists('auditoria', $data)) {
        $this->prune_missing_entities($auditoria_table, 'auditorium_id', wp_list_pluck($data['auditoria'], 'auditorium_id'));
      }
      if (array_key_exists('sessions', $data)) {
        $this->prune_missing_entities($sessions_table, 'session_id', wp_list_pluck($data['sessions'], 'session_id'));
      }
    }

    foreach ((array) (isset($data['deleted']) ? $data['deleted'] : array()) as $deleted) {
      $id = isset($deleted['entity_id']) ? $deleted['entity_id'] : null;
      if (!$id) {
        continue;
      }
      if ($deleted['entity_type'] === 'film') {
        $wpdb->delete($films_table, array('film_id' => $id, 'read_only' => 0));
      } elseif ($deleted['entity_type'] === 'auditorium') {
        $wpdb->delete($auditoria_table, array('auditorium_id' => $id, 'read_only' => 0));
      } elseif ($deleted['entity_type'] === 'session') {
        $wpdb->delete($sessions_table, array('session_id' => $id, 'read_only' => 0));
      }
    }
    return true;
  }

  private function prune_missing_entities($table, $idField, $presentIds)
  {
    global $wpdb;
    $present = array_fill_keys(array_map('strval', array_filter((array) $presentIds)), true);
    foreach ($wpdb->get_results("SELECT * FROM {$table}") as $row) {
      $id = isset($row->{$idField}) ? (string) $row->{$idField} : '';
      if ($id !== '' && empty($row->read_only) && !isset($present[$id])) {
        $wpdb->delete($table, array($idField => $id, 'read_only' => 0));
      }
    }
  }

  private function decoded_payload($value)
  {
    $decoded = $value ? json_decode($value, true) : array();
    return is_array($decoded) ? $decoded : array();
  }

  private function first_territory($metadata)
  {
    $cinema = isset($metadata['cinema']) ? $metadata['cinema'] : array();
    if (!$cinema && !empty($metadata['cinemas'][0])) {
      $cinema = $metadata['cinemas'][0];
    }
    return isset($cinema['postal_address']['country'])
      ? $cinema['postal_address']['country']
      : 'AU';
  }

  private function export_data()
  {
    global $wpdb;
    $metadata = get_option($this->feed_option_name, array());
    $opts = get_option($this->option_name, array());
    $ttl = isset($metadata['ttl_seconds']) ? intval($metadata['ttl_seconds']) : 900;
    $cinema = isset($metadata['cinema']) ? $metadata['cinema'] : (isset($opts['cinema']) ? $opts['cinema'] : array());
    if (empty($cinema) && empty($metadata['cinemas'])) {
      $cinema = array(
        'cinema_id' => 'wordpress-' . get_current_blog_id(),
        'name' => get_bloginfo('name') ?: 'Cinema',
        'timezone' => wp_timezone_string() ?: 'UTC',
        'canonical_url' => home_url('/'),
        'website' => home_url('/'),
      );
    }
    if (!empty($cinema)) {
      $cinema = $this->normalise_cinema_for_export($cinema);
    }
    $publisher = isset($metadata['publisher']) ? $metadata['publisher'] : array(
      'name' => get_bloginfo('name'),
      'url' => home_url('/'),
    );
    $out = $metadata;
    $out['spec'] = 'cinemashowtimes-json/1.1';
    $out['feed_id'] = isset($metadata['feed_id'])
      ? $metadata['feed_id']
      : ((isset($cinema['cinema_id']) ? $cinema['cinema_id'] : 'wordpress-cinema') . '-showtimes');
    $out['mode'] = 'full';
    $out['generated_at'] = gmdate('Y-m-d\\TH:i:s\\Z');
    $out['expires_at'] = gmdate('Y-m-d\\TH:i:s\\Z', time() + $ttl);
    $out['ttl_seconds'] = $ttl;
    $out['canonical_url'] = rest_url('cine/v1/showtimes');
    $out['publisher'] = $publisher;
    $links = array();
    foreach ((array) (isset($out['links']) ? $out['links'] : array()) as $link) {
      if (!isset($link['rel']) || !in_array($link['rel'], array('self', 'canonical'), true)) {
        $links[] = $link;
      }
    }
    $links[] = array(
      'rel' => 'self',
      'href' => rest_url('cine/v1/showtimes'),
      'type' => 'application/json',
    );
    $links[] = array(
      'rel' => 'schema',
      'href' => 'https://cinemashowtimes.org/schema/cinemashowtimes-json-1.1.schema.json',
      'type' => 'application/schema+json',
    );
    $out['links'] = $links;
    if (!isset($out['cinemas'])) {
      $out['cinema'] = $cinema;
    }
    unset($out['sequence']);

    $territory = $this->first_territory($out);
    $films = array();
    foreach ($wpdb->get_results("SELECT * FROM {$wpdb->prefix}cine_films ORDER BY id DESC LIMIT 5000", ARRAY_A) as $row) {
      if (!$row['film_id'] || !$row['title']) {
        continue;
      }
      $film = $this->decoded_payload($row['raw_payload']);
      $film['film_id'] = $row['film_id'];
      $film['title'] = $row['title'];
      $film['canonical_url'] = $row['canonical_url'] ?: rest_url('cine/v1/films/' . rawurlencode($row['film_id']));
      if ($row['runtime_minutes'] !== null) {
        $film['runtime_minutes'] = intval($row['runtime_minutes']);
      }
      if ($row['synopsis'] !== null) {
        $film['description'] = $row['synopsis'];
      }
      if ($row['status']) {
        $film['status'] = $row['status'];
      }
      foreach (array('identifiers', 'credits', 'scores') as $field) {
        if ($row[$field]) {
          $film[$field] = $this->decoded_payload($row[$field]);
        }
      }
      if ($row['assets']) {
        $storedAssets = $this->decoded_payload($row['assets']);
        if (isset($storedAssets[0]['media_id'])) {
          $film['media'] = $storedAssets;
        } else {
          $film['assets'] = $storedAssets;
        }
      }
      if ($row['rating'] && empty($film['classifications'])) {
        $film['classifications'] = array(array('territory' => $territory, 'code' => $row['rating']));
      }
      if ($row['release_date'] && empty($film['releases'])) {
        $film['releases'] = array(array(
          'territory' => $territory,
          'date' => $row['release_date'],
          'type' => 'theatrical',
        ));
      }
      $films[] = $film;
    }
    $out['films'] = $films;

    $auditoria = array();
    foreach ($wpdb->get_results("SELECT * FROM {$wpdb->prefix}cine_auditoria ORDER BY id ASC LIMIT 2000", ARRAY_A) as $row) {
      $auditorium = $this->decoded_payload($row['raw_payload']);
      $auditorium['auditorium_id'] = $row['auditorium_id'];
      $auditorium['name'] = $row['name'];
      if ($row['seat_count'] !== null) {
        $auditorium['seat_count'] = intval($row['seat_count']);
      }
      if ($row['attributes']) {
        $auditorium['attributes'] = $this->decoded_payload($row['attributes']);
      }
      if ($row['seat_classes']) {
        $auditorium['seat_classes'] = $this->decoded_payload($row['seat_classes']);
      }
      if ($row['seatmap_url']) {
        if (!isset($auditorium['seatmap'])) {
          $auditorium['seatmap'] = array();
        }
        $auditorium['seatmap']['layout_url'] = $row['seatmap_url'];
      }
      $auditoria[] = $auditorium;
    }
    $out['auditoria'] = $auditoria;

    $sessions = array();
    foreach ($wpdb->get_results("SELECT * FROM {$wpdb->prefix}cine_sessions ORDER BY start_time_local ASC LIMIT 10000", ARRAY_A) as $row) {
      $session = $this->decoded_payload($row['raw_payload']);
      $startTime = $row['start_time'] ?: (isset($session['start_time']) ? $session['start_time'] : null);
      if (!$row['session_id'] || !$row['film_id'] || !$startTime) {
        continue;
      }
      $session['session_id'] = $row['session_id'];
      if ($row['cinema_id']) {
        $session['cinema_id'] = $row['cinema_id'];
      } elseif (isset($cinema['cinema_id'])) {
        $session['cinema_id'] = $cinema['cinema_id'];
      }
      $session['film_id'] = $row['film_id'];
      if ($row['auditorium_id']) {
        $session['auditorium_id'] = $row['auditorium_id'];
      }
      $session['canonical_url'] = $row['canonical_url']
        ?: rest_url('cine/v1/sessions/' . rawurlencode($row['session_id']));
      $session['status'] = $row['status'] ?: (isset($session['status']) ? $session['status'] : 'scheduled');
      $session['start_time'] = $startTime;
      if ($row['attributes']) {
        $session['presentation'] = $this->decoded_payload($row['attributes']);
      }
      if ($row['pricing']) {
        $storedPricing = $this->decoded_payload($row['pricing']);
        if (isset($storedPricing[0]['offer_id']) && isset($storedPricing[0]['booking_url'])) {
          $session['offers'] = $storedPricing;
        } else {
          $session['pricing'] = $storedPricing;
        }
      }
      if ($row['availability']) {
        $storedAvailability = $this->decoded_payload($row['availability']);
        if (isset($storedAvailability['status']) && isset($storedAvailability['updated_at'])) {
          $session['availability'] = $storedAvailability;
        } else {
          $session['availability_by_class'] = $storedAvailability;
        }
      }
      if ($row['booking_url']) {
        $session['booking_url'] = $row['booking_url'];
      }
      $sessions[] = $session;
    }
    $out['sessions'] = $sessions;
    return $out;
  }

  private function normalise_cinema_for_export($cinema)
  {
    if (isset($cinema['lat']) && isset($cinema['lon']) && !isset($cinema['location'])) {
      $cinema['location'] = array(
        'latitude' => floatval($cinema['lat']),
        'longitude' => floatval($cinema['lon']),
      );
    }
    if (!isset($cinema['postal_address'])) {
      $postal = array(
        'street_address' => isset($cinema['street_address']) ? $cinema['street_address'] : null,
        'locality' => isset($cinema['locality']) ? $cinema['locality'] : null,
        'region' => isset($cinema['region']) ? $cinema['region'] : null,
        'postal_code' => isset($cinema['postal_code']) ? $cinema['postal_code'] : null,
        'country' => isset($cinema['country']) ? strtoupper($cinema['country']) : null,
      );
      $postal = array_filter($postal, function ($value) {
        return $value !== null && $value !== '';
      });
      if (!empty($postal['country'])) {
        $cinema['postal_address'] = $postal;
      }
    }
    unset(
      $cinema['street_address'],
      $cinema['locality'],
      $cinema['region'],
      $cinema['postal_code'],
      $cinema['country']
    );
    unset($cinema['lat'], $cinema['lon'], $cinema['google_business_id']);
    if (empty($cinema['canonical_url']) && !empty($cinema['website'])) {
      $cinema['canonical_url'] = $cinema['website'];
    }
    return $cinema;
  }
}

WP_DCine_Sessiontimes_Data::instance();
