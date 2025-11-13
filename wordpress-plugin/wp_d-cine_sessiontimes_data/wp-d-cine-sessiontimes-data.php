<?php
/**
 * Plugin Name: d-cine.com Sessiontimes Data
 * Description: Import/export CinemaShowtimes JSON feeds into local DB tables and provide admin CRUD pages.
 * Version: 0.1.0
 * Author: James Gardiner
 * Text Domain: wp-d-cine-sessiontimes-data
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

// include trait files
require_once plugin_dir_path(__FILE__) . 'includes/trait-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-films.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-sessions.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-cinema.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-auditoria.php';

class WP_DCine_Sessiontimes_Data
{
  private static $instance = null;
  private $option_name = 'wp_d_cine_settings';

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

    add_action('admin_menu', array($this, 'admin_menu'));
    add_action('admin_init', array($this, 'register_settings'));

    // Admin save/delete handlers for CRUD
    add_action('admin_post_wp_d_cine_save_film', array($this, 'handle_save_film'));
    add_action('admin_post_wp_d_cine_delete_film', array($this, 'handle_delete_film'));
    add_action('admin_post_wp_d_cine_save_session', array($this, 'handle_save_session'));
    add_action('admin_post_wp_d_cine_delete_session', array($this, 'handle_delete_session'));

    // Auditoria save/delete handlers
    add_action('admin_post_wp_d_cine_save_auditorium', array($this, 'handle_save_auditorium'));
    add_action('admin_post_wp_d_cine_delete_auditorium', array($this, 'handle_delete_auditorium'));

    // REST endpoints
    add_action('rest_api_init', array($this, 'register_rest_routes'));

    // WP Cron scheduled fetch
    add_action('wp_d_cine_scheduled_fetch', array($this, 'scheduled_fetch'));

    // manual fetch via admin-post
    add_action('admin_post_wp_d_cine_manual_fetch', array($this, 'manual_fetch'));
  }

  public function activate()
  {
    $this->create_tables();
    $this->ensure_cron();
  }

  public function deactivate()
  {
    // Clear scheduled hook
    wp_clear_scheduled_hook('wp_d_cine_scheduled_fetch');
  }

  private function ensure_cron()
  {
    $opts = get_option($this->option_name, array());
    $enabled = isset($opts['auto_mode']) && $opts['auto_mode'] === '1';
    $minutes = isset($opts['interval_minutes']) ? intval($opts['interval_minutes']) : 15;
    if ($enabled) {
      if (!wp_next_scheduled('wp_d_cine_scheduled_fetch')) {
        wp_schedule_event(time(), 'wp_d_cine_interval_' . $minutes, 'wp_d_cine_scheduled_fetch');
      }
      // register dynamic interval filter
      add_filter('cron_schedules', function ($schedules) use ($minutes) {
        $key = 'wp_d_cine_interval_' . $minutes;
        if (!isset($schedules[$key])) {
          $schedules[$key] = array('interval' => $minutes * 60, 'display' => "Every {$minutes} minutes");
        }
        return $schedules;
      });
    }
  }

  private function create_tables()
  {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $films_table = $wpdb->prefix . 'cine_films';
    $sessions_table = $wpdb->prefix . 'cine_sessions';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql1 = "CREATE TABLE IF NOT EXISTS {$films_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            film_id VARCHAR(191) NOT NULL,
            title TEXT NOT NULL,
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
            read_only TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (film_id)
        ) {$charset_collate}";

    $sql2 = "CREATE TABLE IF NOT EXISTS {$sessions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(191) NOT NULL,
            film_id VARCHAR(191) NOT NULL,
            auditorium_id VARCHAR(191) NULL,
            start_time_local DATETIME NULL,
            start_time_utc DATETIME NULL,
            attributes LONGTEXT NULL,
            pricing LONGTEXT NULL,
            availability LONGTEXT NULL,
            booking_url TEXT NULL,
            checksum VARCHAR(255) NULL,
            seating_capacity INT NULL,
            seats_available INT NULL,
            read_only TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (session_id)
        ) {$charset_collate}";

    dbDelta($sql1);
    dbDelta($sql2);

    // Auditoria table
    $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cine_auditoria (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      auditorium_id VARCHAR(191) NOT NULL,
      name VARCHAR(255) NULL,
      attributes LONGTEXT NULL,
      seat_count INT NULL,
      seat_classes LONGTEXT NULL,
      seatmap_url TEXT NULL,
      read_only TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY (auditorium_id)
    ) {$charset_collate}";
    dbDelta($sql3);
  }

  public function admin_menu()
  {
    // Root top-level menu for the plugin
    $root_slug = 'wp-d-cine-root';
    add_menu_page('d-cine Sessiontimes', 'd-cine Sessiontimes', 'manage_options', $root_slug, array($this, 'settings_page'), 'dashicons-video-alt2', 26);

    // Submenus under the root menu
    // Make the "Settings" submenu use the same slug as the root so the top-level menu
    // and the Settings submenu resolve to the same single settings page (no duplicate)
    add_submenu_page($root_slug, 'Settings', 'Settings', 'manage_options', $root_slug, array($this, 'settings_page'));
    add_submenu_page($root_slug, 'Cinema Info', 'Cinema Info', 'manage_options', 'wp-d-cine-cinema', array($this, 'cinema_page'));
    add_submenu_page($root_slug, 'Auditoria', 'Auditoria', 'manage_options', 'wp-d-cine-auditoria', array($this, 'auditoria_page'));
    add_submenu_page($root_slug, 'Films', 'Films', 'manage_options', 'wp-d-cine-films', array($this, 'films_page'));
    add_submenu_page($root_slug, 'Sessions', 'Sessions', 'manage_options', 'wp-d-cine-sessions', array($this, 'sessions_page'));
  }

  public function register_rest_routes()
  {
    register_rest_route('cine/v1', '/import', array(
      'methods' => 'POST',
      'callback' => array($this, 'rest_import'),
      'permission_callback' => '__return_true',
    ));

    register_rest_route('cine/v1', '/export', array(
      'methods' => 'GET',
      'callback' => array($this, 'rest_export'),
      'permission_callback' => '__return_true',
    ));
  }

  private function check_api_key($provided)
  {
    $opts = get_option($this->option_name, array());
    $expected = isset($opts['api_key']) ? $opts['api_key'] : '';
    return $expected !== '' && hash_equals($expected, $provided);
  }

  public function rest_import($request)
  {
    $api_key = $request->get_header('X-CINE-API') ?: $request->get_param('api_key');
    if (!$this->check_api_key($api_key)) {
      return new WP_REST_Response(array('error' => 'Invalid API key'), 401);
    }

    $body = $request->get_body();
    $data = json_decode($body, true);
    if ($data === null) {
      return new WP_REST_Response(array('error' => 'Invalid JSON payload'), 400);
    }

    $this->import_data($data);
    return new WP_REST_Response(array('status' => 'ok'), 200);
  }

  public function rest_export($request)
  {
    $api_key = $request->get_header('X-CINE-API') ?: $request->get_param('api_key');
    if (!$this->check_api_key($api_key)) {
      return new WP_REST_Response(array('error' => 'Invalid API key'), 401);
    }

    $data = $this->export_data();
    return rest_ensure_response($data);
  }

  public function manual_fetch()
  {
    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }
    $this->scheduled_fetch();
    wp_redirect(admin_url('options-general.php?page=wp-d-cine-settings'));
    exit;
  }

  public function scheduled_fetch()
  {
    $opts = get_option($this->option_name, array());
    if (empty($opts['endpoint_url'])) {
      return;
    }
    $url = $opts['endpoint_url'];
    $api_key = isset($opts['api_key']) ? $opts['api_key'] : '';

    $args = array('timeout' => 20);
    if ($api_key) {
      $args['headers'] = array('X-CINE-API' => $api_key);
    }

    $resp = wp_remote_get($url, $args);
    if (is_wp_error($resp)) {
      error_log('wp_d_cine fetch error: ' . $resp->get_error_message());
      return;
    }
    $body = wp_remote_retrieve_body($resp);
    $data = json_decode($body, true);
    if ($data === null) {
      // Could not decode JSON. For now, abort. If your feed uses JSON5 please provide JSON.
      error_log('wp_d_cine: invalid JSON from endpoint');
      return;
    }
    $this->import_data($data);
  }

  private function import_data($data)
  {
    global $wpdb;
    $films_table = $wpdb->prefix . 'cine_films';
    $sessions_table = $wpdb->prefix . 'cine_sessions';

    if (isset($data['films']) && is_array($data['films'])) {
      foreach ($data['films'] as $film) {
        $film_id = isset($film['film_id']) ? $film['film_id'] : null;
        if (!$film_id)
          continue;

        // check existing and read_only
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$films_table} WHERE film_id = %s", $film_id));
        if ($existing && $existing->read_only)
          continue;

        $payload = array(
          'film_id' => $film_id,
          'title' => isset($film['title']) ? $film['title'] : '',
          'runtime_minutes' => isset($film['runtime_minutes']) ? intval($film['runtime_minutes']) : null,
          'rating' => isset($film['rating']) ? $film['rating'] : null,
          'synopsis' => isset($film['synopsis']) ? $film['synopsis'] : null,
          'identifiers' => isset($film['identifiers']) ? wp_json_encode($film['identifiers']) : null,
          'assets' => isset($film['assets']) ? wp_json_encode($film['assets']) : null,
          'credits' => isset($film['credits']) ? wp_json_encode($film['credits']) : null,
          'scores' => isset($film['scores']) ? wp_json_encode($film['scores']) : null,
          'status' => isset($film['status']) ? $film['status'] : null,
          'release_date' => isset($film['release_date']) ? $film['release_date'] : null,
          'pre_sales_start' => isset($film['pre_sales_start']) ? $film['pre_sales_start'] : null,
        );

        if ($existing) {
          $wpdb->update($films_table, $payload, array('film_id' => $film_id));
        } else {
          $wpdb->insert($films_table, $payload);
        }
      }
    }

    if (isset($data['sessions']) && is_array($data['sessions'])) {
      foreach ($data['sessions'] as $sess) {
        $session_id = isset($sess['session_id']) ? $sess['session_id'] : null;
        if (!$session_id)
          continue;

        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sessions_table} WHERE session_id = %s", $session_id));
        if ($existing && $existing->read_only)
          continue;

        $payload = array(
          'session_id' => $session_id,
          'film_id' => isset($sess['film_id']) ? $sess['film_id'] : null,
          'auditorium_id' => isset($sess['auditorium_id']) ? $sess['auditorium_id'] : null,
          'start_time_local' => isset($sess['start_time_local']) ? date('Y-m-d H:i:s', strtotime($sess['start_time_local'])) : null,
          'start_time_utc' => isset($sess['start_time_utc']) ? date('Y-m-d H:i:s', strtotime($sess['start_time_utc'])) : null,
          'attributes' => isset($sess['attributes']) ? wp_json_encode($sess['attributes']) : null,
          'pricing' => isset($sess['pricing']) ? wp_json_encode($sess['pricing']) : null,
          'availability' => isset($sess['availability_by_class']) ? wp_json_encode($sess['availability_by_class']) : null,
          'booking_url' => isset($sess['booking_url']) ? $sess['booking_url'] : null,
          'checksum' => isset($sess['checksum']) ? $sess['checksum'] : null,
          'seating_capacity' => isset($sess['seating_capacity']) ? intval($sess['seating_capacity']) : null,
          'seats_available' => isset($sess['seats_available']) ? intval($sess['seats_available']) : null,
        );

        if ($existing) {
          $wpdb->update($sessions_table, $payload, array('session_id' => $session_id));
        } else {
          $wpdb->insert($sessions_table, $payload);
        }
      }
    }
  }

  private function export_data()
  {
    global $wpdb;
    $films_table = $wpdb->prefix . 'cine_films';
    $sessions_table = $wpdb->prefix . 'cine_sessions';

    $films = $wpdb->get_results("SELECT * FROM {$films_table} ORDER BY id DESC LIMIT 1000", ARRAY_A);
    $sessions = $wpdb->get_results("SELECT * FROM {$sessions_table} ORDER BY start_time_local DESC LIMIT 2000", ARRAY_A);

    // Decode stored json fields where applicable to return structured data
    foreach ($films as &$f) {
      if (!empty($f['identifiers']))
        $f['identifiers'] = json_decode($f['identifiers'], true);
      if (!empty($f['assets']))
        $f['assets'] = json_decode($f['assets'], true);
      if (!empty($f['credits']))
        $f['credits'] = json_decode($f['credits'], true);
      if (!empty($f['scores']))
        $f['scores'] = json_decode($f['scores'], true);
    }
    foreach ($sessions as &$s) {
      if (!empty($s['attributes']))
        $s['attributes'] = json_decode($s['attributes'], true);
      if (!empty($s['pricing']))
        $s['pricing'] = json_decode($s['pricing'], true);
      if (!empty($s['availability']))
        $s['availability'] = json_decode($s['availability'], true);
    }

    $out = array('spec' => 'cinemashowtimes-json/1.0', 'generated_at' => gmdate('c'), 'films' => $films, 'sessions' => $sessions);
    return $out;
  }
}

WP_DCine_Sessiontimes_Data::instance();

// End of plugin file
