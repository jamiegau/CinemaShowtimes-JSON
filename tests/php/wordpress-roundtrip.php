<?php
/**
 * Minimal WordPress compatibility harness for a lossless 1.1 import/export test.
 *
 * This does not replace WordPress integration testing. It exercises the plugin's
 * feed validation, persistence mapping and export overlay without a web server.
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php wordpress-roundtrip.php OUTPUT_FILE\n");
    exit(2);
}

define('ABSPATH', __DIR__ . '/fake-wordpress/');
define('ARRAY_A', 'ARRAY_A');

$wp_test_options = array();

function plugin_dir_path($file) { return dirname($file) . '/'; }
function register_activation_hook() {}
function register_deactivation_hook() {}
function add_filter() {}
function add_action() {}
function register_rest_route() {}
function get_option($name, $default = false) {
    global $wp_test_options;
    return array_key_exists($name, $wp_test_options) ? $wp_test_options[$name] : $default;
}
function update_option($name, $value) {
    global $wp_test_options;
    $wp_test_options[$name] = $value;
    return true;
}
function add_option($name, $value) { return update_option($name, $value); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function esc_url_raw($value) { return $value; }
function rest_url($path = '') { return 'https://wordpress.example.test/wp-json/' . ltrim($path, '/'); }
function home_url($path = '') { return 'https://wordpress.example.test/' . ltrim($path, '/'); }
function get_bloginfo($key) { return $key === 'name' ? 'WordPress Example Cinema' : ''; }
function get_current_blog_id() { return 1; }
function wp_timezone_string() { return 'Australia/Melbourne'; }
function wp_clear_scheduled_hook() {}
function wp_schedule_event() {}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_list_pluck($items, $field) {
    return array_values(array_map(function ($item) use ($field) {
        return is_array($item) ? (isset($item[$field]) ? $item[$field] : null) : $item->{$field};
    }, $items));
}

class WP_Error
{
    private $code;
    private $message;
    private $data;
    public function __construct($code, $message, $data = array()) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_message() { return $this->message; }
    public function add_data($data) { $this->data = $data; }
}

class WP_REST_Server
{
    const CREATABLE = 'POST';
    const READABLE = 'GET';
}

class FakeWpdb
{
    public $prefix = 'wp_';
    private $tables = array();

    public function get_charset_collate() { return ''; }

    private function defaults($table)
    {
        if ($table === 'wp_cine_films') {
            return array_fill_keys(array(
                'id', 'film_id', 'title', 'canonical_url', 'runtime_minutes', 'rating',
                'synopsis', 'identifiers', 'assets', 'credits', 'scores', 'status',
                'release_date', 'pre_sales_start', 'presales', 'raw_payload',
                'read_only', 'created_at', 'updated_at',
            ), null);
        }
        if ($table === 'wp_cine_sessions') {
            return array_fill_keys(array(
                'id', 'session_id', 'cinema_id', 'film_id', 'auditorium_id',
                'canonical_url', 'status', 'start_time', 'start_time_local',
                'start_time_utc', 'attributes', 'pricing', 'availability',
                'booking_url', 'checksum', 'seating_capacity', 'seats_available',
                'raw_payload', 'read_only', 'created_at', 'updated_at',
            ), null);
        }
        return array_fill_keys(array(
            'id', 'auditorium_id', 'name', 'attributes', 'seat_count',
            'seat_classes', 'seatmap_url', 'raw_payload', 'read_only',
            'created_at', 'updated_at',
        ), null);
    }

    public function prepare($query, $value)
    {
        return preg_replace('/%s/', "'" . str_replace("'", "''", $value) . "'", $query, 1);
    }

    public function get_row($query)
    {
        if (!preg_match("/FROM ([A-Za-z0-9_]+) WHERE ([A-Za-z0-9_]+) = '([^']*)'/", $query, $match)) {
            return null;
        }
        foreach (isset($this->tables[$match[1]]) ? $this->tables[$match[1]] : array() as $row) {
            if ((string) $row[$match[2]] === $match[3]) {
                return (object) $row;
            }
        }
        return null;
    }

    public function insert($table, $payload)
    {
        $rows = isset($this->tables[$table]) ? $this->tables[$table] : array();
        $row = array_merge($this->defaults($table), $payload);
        $row['id'] = count($rows) + 1;
        $row['read_only'] = isset($row['read_only']) ? $row['read_only'] : 0;
        $row['created_at'] = '2026-08-01 00:00:00';
        $row['updated_at'] = '2026-08-01 00:00:00';
        $rows[] = $row;
        $this->tables[$table] = $rows;
        return true;
    }

    public function update($table, $payload, $where)
    {
        foreach ($this->tables[$table] as &$row) {
            $matches = true;
            foreach ($where as $field => $value) {
                $matches = $matches && (string) $row[$field] === (string) $value;
            }
            if ($matches) {
                $row = array_merge($row, $payload);
            }
        }
        return true;
    }

    public function delete($table, $where)
    {
        $this->tables[$table] = array_values(array_filter(
            isset($this->tables[$table]) ? $this->tables[$table] : array(),
            function ($row) use ($where) {
                foreach ($where as $field => $value) {
                    if ((string) $row[$field] !== (string) $value) {
                        return true;
                    }
                }
                return false;
            }
        ));
        return true;
    }

    public function get_results($query, $format = null)
    {
        if (!preg_match('/FROM ([A-Za-z0-9_]+)/', $query, $match)) {
            return array();
        }
        $rows = array_values(isset($this->tables[$match[1]]) ? $this->tables[$match[1]] : array());
        return $format === ARRAY_A ? $rows : array_map(function ($row) { return (object) $row; }, $rows);
    }
}

$wpdb = new FakeWpdb();
$root = dirname(__DIR__, 2);
require_once $root . '/wordpress-plugin/wp_d-cine_sessiontimes_data/wp-d-cine-sessiontimes-data.php';

$plugin = WP_DCine_Sessiontimes_Data::instance();
$feed = json_decode(file_get_contents($root . '/examples/CinemaShowtimes-1.1-example.json'), true);

$validate = new ReflectionMethod($plugin, 'validate_feed_11');
$validate->setAccessible(true);
$valid = $validate->invoke($plugin, $feed);
if ($valid !== true) {
    fwrite(STDERR, "Plugin rejected the valid 1.1 example\n");
    exit(1);
}
$invalidFeed = $feed;
$invalidFeed['sessions'][0]['start_time'] = '2026-08-01T19:30:00';
if (!is_wp_error($validate->invoke($plugin, $invalidFeed))) {
    fwrite(STDERR, "Plugin accepted a session timestamp without a UTC offset\n");
    exit(1);
}

$import = new ReflectionMethod($plugin, 'import_data');
$import->setAccessible(true);
$result = $import->invoke($plugin, $feed);
if ($result !== true) {
    fwrite(STDERR, "Plugin import failed\n");
    exit(1);
}

$export = new ReflectionMethod($plugin, 'export_data');
$export->setAccessible(true);
$delta = array(
    'spec' => 'cinemashowtimes-json/1.1',
    'feed_id' => $feed['feed_id'],
    'mode' => 'delta',
    'sequence' => 1843,
    'generated_at' => '2026-08-01T10:05:00Z',
    'publisher' => $feed['publisher'],
    'deleted' => array(array(
        'entity_type' => 'session',
        'entity_id' => $feed['sessions'][0]['session_id'],
        'deleted_at' => '2026-08-01T10:04:00Z',
    )),
);
$result = $import->invoke($plugin, $delta);
if ($result !== true || count($export->invoke($plugin)['sessions']) !== 0) {
    fwrite(STDERR, "Plugin delta tombstone test failed\n");
    exit(1);
}

$result = $import->invoke($plugin, $feed);
if ($result !== true) {
    fwrite(STDERR, "Plugin full-feed restore failed\n");
    exit(1);
}
$roundtrip = $export->invoke($plugin);
if (count($roundtrip['sessions']) !== 1 || empty($roundtrip['films'][0]['media'])) {
    fwrite(STDERR, "Plugin lossless round-trip assertions failed\n");
    exit(1);
}
file_put_contents(
    $argv[1],
    json_encode($roundtrip, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);
echo "WordPress 1.1 round-trip generated\n";
