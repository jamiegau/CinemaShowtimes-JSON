<?php
if (!defined('ABSPATH')) {
  exit;
}

trait WP_DCine_Settings_Trait
{
  public function register_settings()
  {
    register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
  }

  public function sanitize_settings($input)
  {
    $out = array();
    // Preserve existing cinema config if present
    $cinema_in = isset($input['cinema']) && is_array($input['cinema']) ? $input['cinema'] : array();
    $out['cinema'] = array(
      'cinema_id' => isset($cinema_in['cinema_id']) ? sanitize_text_field($cinema_in['cinema_id']) : '',
      'name' => isset($cinema_in['name']) ? sanitize_text_field($cinema_in['name']) : '',
      'timezone' => isset($cinema_in['timezone']) ? sanitize_text_field($cinema_in['timezone']) : '',
      'lat' => isset($cinema_in['lat']) ? floatval($cinema_in['lat']) : null,
      'lon' => isset($cinema_in['lon']) ? floatval($cinema_in['lon']) : null,
      'address' => isset($cinema_in['address']) ? sanitize_text_field($cinema_in['address']) : '',
      'phone' => isset($cinema_in['phone']) ? sanitize_text_field($cinema_in['phone']) : '',
      'website' => isset($cinema_in['website']) ? esc_url_raw($cinema_in['website']) : '',
      'google_place_id' => isset($cinema_in['google_place_id']) ? sanitize_text_field($cinema_in['google_place_id']) : '',
      'google_business_id' => isset($cinema_in['google_business_id']) ? sanitize_text_field($cinema_in['google_business_id']) : '',
    );

    $out['endpoint_url'] = isset($input['endpoint_url']) ? esc_url_raw($input['endpoint_url']) : '';
    $out['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
    $out['auto_mode'] = isset($input['auto_mode']) && $input['auto_mode'] ? '1' : '0';
    $mins = isset($input['interval_minutes']) ? intval($input['interval_minutes']) : 15;
    if ($mins < 1)
      $mins = 1;
    if ($mins > 60)
      $mins = 60;
    $out['interval_minutes'] = $mins;

    // re-schedule if needed
    wp_clear_scheduled_hook('wp_d_cine_scheduled_fetch');
    if ($out['auto_mode'] === '1') {
      add_filter('cron_schedules', function ($schedules) use ($mins) {
        $key = 'wp_d_cine_interval_' . $mins;
        if (!isset($schedules[$key])) {
          $schedules[$key] = array('interval' => $mins * 60, 'display' => "Every {$mins} minutes");
        }
        return $schedules;
      });
      if (!wp_next_scheduled('wp_d_cine_scheduled_fetch')) {
        wp_schedule_event(time(), 'wp_d_cine_interval_' . $mins, 'wp_d_cine_scheduled_fetch');
      }
    }

    return $out;
  }

  public function settings_page()
  {
    $opts = get_option($this->option_name, array('endpoint_url' => '', 'api_key' => '', 'auto_mode' => '0', 'interval_minutes' => 15));
    ?>
    <div class="wrap">
      <h1>Cine Sessiontimes Settings</h1>
      <form method="post" action="options.php">
        <?php settings_fields($this->option_name);
        do_settings_sections($this->option_name); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><label for="endpoint_url">Endpoint URL</label></th>
            <td><input name="<?php echo $this->option_name; ?>[endpoint_url]" type="url" id="endpoint_url"
                value="<?php echo esc_attr($opts['endpoint_url']); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="api_key">API Key</label></th>
            <td><input name="<?php echo $this->option_name; ?>[api_key]" type="text" id="api_key"
                value="<?php echo esc_attr($opts['api_key']); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row">Automatic fetch</th>
            <td>
              <label><input type="checkbox" name="<?php echo $this->option_name; ?>[auto_mode]" value="1" <?php checked($opts['auto_mode'], '1'); ?> /> Enable automatic fetch (WP-Cron)</label>
              <p class="description">When enabled the plugin will fetch the configured endpoint periodically.</p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="interval_minutes">Interval (minutes)</label></th>
            <td><input name="<?php echo $this->option_name; ?>[interval_minutes]" type="number" id="interval_minutes"
                value="<?php echo esc_attr($opts['interval_minutes']); ?>" min="1" max="60" /></td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>

      <h2>Manual fetch</h2>
      <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="wp_d_cine_manual_fetch" />
        <?php submit_button('Run manual fetch now'); ?>
      </form>
    </div>
    <?php
  }

}
