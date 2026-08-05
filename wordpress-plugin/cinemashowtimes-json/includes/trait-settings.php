<?php
if (!defined('ABSPATH')) {
  exit;
}

trait CinemaShowtimes_JSON_Settings_Trait
{
  public function register_settings()
  {
    register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
  }

  public function sanitize_settings($input)
  {
    $existing = get_option($this->option_name, array());
    $out = is_array($existing) ? $existing : array();
    $input = is_array($input) ? $input : array();

    if (isset($input['cinema']) && is_array($input['cinema'])) {
      $cinemaExisting = isset($out['cinema']) && is_array($out['cinema']) ? $out['cinema'] : array();
      $cinemaInput = $input['cinema'];
      $textFields = array(
        'cinema_id', 'name', 'timezone', 'phone', 'email', 'address',
        'street_address', 'locality', 'region', 'postal_code', 'country',
        'google_place_id', 'google_place_id_verified_at',
      );
      foreach ($textFields as $field) {
        if (array_key_exists($field, $cinemaInput)) {
          $cinemaExisting[$field] = sanitize_text_field($cinemaInput[$field]);
        }
      }
      foreach (array('website', 'canonical_url') as $field) {
        if (array_key_exists($field, $cinemaInput)) {
          $cinemaExisting[$field] = esc_url_raw($cinemaInput[$field]);
        }
      }
      foreach (array('lat', 'lon') as $field) {
        if (array_key_exists($field, $cinemaInput)) {
          $cinemaExisting[$field] = $cinemaInput[$field] === '' ? null : floatval($cinemaInput[$field]);
        }
      }
      if (!empty($cinemaExisting['country'])) {
        $cinemaExisting['country'] = strtoupper($cinemaExisting['country']);
      }
      $out['cinema'] = $cinemaExisting;
    }

    if (array_key_exists('endpoint_url', $input)) {
      $out['endpoint_url'] = esc_url_raw($input['endpoint_url']);
    }
    if (array_key_exists('api_key', $input)) {
      $out['api_key'] = sanitize_text_field($input['api_key']);
    }
    if (array_key_exists('local_api_key', $input)) {
      $out['local_api_key'] = sanitize_text_field($input['local_api_key']);
    }
    if (array_key_exists('interval_minutes', $input)) {
      $out['interval_minutes'] = max(1, min(60, intval($input['interval_minutes'])));
    }
    if (array_key_exists('settings_form', $input)) {
      $out['auto_mode'] = !empty($input['auto_mode']) ? '1' : '0';
      $out['public_feed'] = !empty($input['public_feed']) ? '1' : '0';
    }

    wp_clear_scheduled_hook('cinemashowtimes_json_scheduled_fetch');
    if (!empty($out['auto_mode'])) {
      $minutes = isset($out['interval_minutes']) ? intval($out['interval_minutes']) : 15;
      wp_schedule_event(time(), 'cinemashowtimes_json_interval_' . $minutes, 'cinemashowtimes_json_scheduled_fetch');
    }
    return $out;
  }

  public function settings_page()
  {
    $defaults = array(
      'endpoint_url' => '',
      'api_key' => '',
      'local_api_key' => '',
      'public_feed' => '1',
      'auto_mode' => '0',
      'interval_minutes' => 15,
    );
    $opts = wp_parse_args(get_option($this->option_name, array()), $defaults);
    ?>
    <div class="wrap">
      <h1>CinemaShowtimes-JSON Settings</h1>
      <p>This plugin imports and exports <code>cinemashowtimes-json/1.1</code>. Imported entities are preserved losslessly while selected fields remain editable.</p>
      <form method="post" action="options.php">
        <?php settings_fields($this->option_name); ?>
        <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[settings_form]" value="1" />
        <h2>Remote source</h2>
        <table class="form-table">
          <tr>
            <th scope="row"><label for="endpoint_url">1.1 feed URL</label></th>
            <td><input name="<?php echo esc_attr($this->option_name); ?>[endpoint_url]" type="url" id="endpoint_url"
                value="<?php echo esc_attr($opts['endpoint_url']); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="api_key">Import/source key</label></th>
            <td><input name="<?php echo esc_attr($this->option_name); ?>[api_key]" type="password" id="api_key"
                value="<?php echo esc_attr($opts['api_key']); ?>" autocomplete="new-password" class="regular-text" />
              <p class="description">Sent only in the <code>X-CINEMASHOWTIMES-API</code> header.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Automatic fetch</th>
            <td><label><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[auto_mode]" value="1" <?php checked($opts['auto_mode'], '1'); ?> /> Enable WP-Cron fetching</label></td>
          </tr>
          <tr>
            <th scope="row"><label for="interval_minutes">Interval (minutes)</label></th>
            <td><input name="<?php echo esc_attr($this->option_name); ?>[interval_minutes]" type="number" id="interval_minutes"
                value="<?php echo esc_attr($opts['interval_minutes']); ?>" min="1" max="60" /></td>
          </tr>
        </table>

        <h2>Local 1.1 feed</h2>
        <table class="form-table">
          <tr>
            <th scope="row">Canonical endpoint</th>
            <td><code><?php echo esc_html(rest_url('cinemashowtimes/v1/showtimes')); ?></code></td>
          </tr>
          <tr>
            <th scope="row">Public discovery</th>
            <td><label><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[public_feed]" value="1" <?php checked($opts['public_feed'], '1'); ?> /> Allow unauthenticated reads</label>
              <p class="description">Recommended for public Search, social and agent discovery feeds.</p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="local_api_key">Private feed key</label></th>
            <td><input name="<?php echo esc_attr($this->option_name); ?>[local_api_key]" type="password" id="local_api_key"
                value="<?php echo esc_attr($opts['local_api_key']); ?>" autocomplete="new-password" class="regular-text" />
              <p class="description">Required in <code>X-CINEMASHOWTIMES-API</code> when public discovery is disabled.</p>
            </td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>

      <h2>Manual fetch</h2>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="cinemashowtimes_json_manual_fetch" />
        <?php wp_nonce_field('cinemashowtimes_json_manual_fetch'); ?>
        <?php submit_button('Run manual fetch now'); ?>
      </form>
    </div>
    <?php
  }
}
