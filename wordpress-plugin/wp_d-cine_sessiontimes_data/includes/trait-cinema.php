<?php
if (!defined('ABSPATH')) {
  exit;
}

trait WP_DCine_Cinema_Trait
{
  public function cinema_page()
  {
    $opts = get_option($this->option_name, array());
    $cinema = isset($opts['cinema']) && is_array($opts['cinema']) ? $opts['cinema'] : array();
    $fields = array(
      'cinema_id' => array('Cinema ID', 'text'),
      'name' => array('Name', 'text'),
      'canonical_url' => array('Canonical public URL', 'url'),
      'timezone' => array('IANA timezone', 'text'),
      'lat' => array('Latitude', 'number'),
      'lon' => array('Longitude', 'number'),
      'street_address' => array('Street address', 'text'),
      'locality' => array('Locality / city', 'text'),
      'region' => array('Region / state', 'text'),
      'postal_code' => array('Postal code', 'text'),
      'country' => array('ISO country code', 'text'),
      'address' => array('Legacy formatted address', 'text'),
      'phone' => array('Phone', 'text'),
      'email' => array('Email', 'email'),
      'website' => array('Website', 'url'),
      'google_place_id' => array('Google Place ID', 'text'),
      'google_place_id_verified_at' => array('Place ID verified at (RFC 3339)', 'text'),
    );
    ?>
    <div class="wrap">
      <h1>Cinema Info</h1>
      <p>These values are used when the imported feed does not already provide richer cinema metadata.</p>
      <form method="post" action="options.php">
        <?php settings_fields($this->option_name); ?>
        <table class="form-table">
          <?php foreach ($fields as $key => $definition): ?>
            <tr>
              <th scope="row"><label for="cinema_<?php echo esc_attr($key); ?>"><?php echo esc_html($definition[0]); ?></label></th>
              <td><input
                name="<?php echo esc_attr($this->option_name); ?>[cinema][<?php echo esc_attr($key); ?>]"
                type="<?php echo esc_attr($definition[1]); ?>"
                id="cinema_<?php echo esc_attr($key); ?>"
                value="<?php echo esc_attr(isset($cinema[$key]) ? $cinema[$key] : ''); ?>"
                <?php echo in_array($key, array('lat', 'lon'), true) ? 'step="any"' : ''; ?>
                class="regular-text" /></td>
            </tr>
          <?php endforeach; ?>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }
}
