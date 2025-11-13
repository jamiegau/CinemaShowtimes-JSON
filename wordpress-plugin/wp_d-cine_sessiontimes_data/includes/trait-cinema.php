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
    ?>
    <div class="wrap">
      <h1>Cinema Info</h1>
      <form method="post" action="options.php">
        <?php settings_fields($this->option_name);
        do_settings_sections($this->option_name); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><label for="cinema_id">Cinema ID</label></th>
            <td><input name="<?php echo $this->option_name; ?>[cinema][cinema_id]" type="text" id="cinema_id" value="<?php echo esc_attr(isset($cinema['cinema_id']) ? $cinema['cinema_id'] : ''); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="cinema_name">Name</label></th>
            <td><input name="<?php echo $this->option_name; ?>[cinema][name]" type="text" id="cinema_name" value="<?php echo esc_attr(isset($cinema['name']) ? $cinema['name'] : ''); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="cinema_timezone">Timezone</label></th>
            <td><input name="<?php echo $this->option_name; ?>[cinema][timezone]" type="text" id="cinema_timezone" value="<?php echo esc_attr(isset($cinema['timezone']) ? $cinema['timezone'] : ''); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="cinema_lat">Latitude</label></th>
            <td><input name="<?php echo $this->option_name; ?>[cinema][lat]" type="text" id="cinema_lat" value="<?php echo esc_attr(isset($cinema['lat']) ? $cinema['lat'] : ''); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="cinema_lon">Longitude</label></th>
            <td><input name="<?php echo $this->option_name; ?>[cinema][lon]" type="text" id="cinema_lon" value="<?php echo esc_attr(isset($cinema['lon']) ? $cinema['lon'] : ''); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="cinema_address">Address</label></th>
            <td><input name="<?php echo $this->option_name; ?>[cinema][address]" type="text" id="cinema_address" value="<?php echo esc_attr(isset($cinema['address']) ? $cinema['address'] : ''); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="cinema_phone">Phone</label></th>
            <td><input name="<?php echo $this->option_name; ?>[cinema][phone]" type="text" id="cinema_phone" value="<?php echo esc_attr(isset($cinema['phone']) ? $cinema['phone'] : ''); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="cinema_website">Website</label></th>
            <td><input name="<?php echo $this->option_name; ?>[cinema][website]" type="url" id="cinema_website" value="<?php echo esc_attr(isset($cinema['website']) ? $cinema['website'] : ''); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="cinema_google_place_id">Google Place ID</label></th>
            <td><input name="<?php echo $this->option_name; ?>[cinema][google_place_id]" type="text" id="cinema_google_place_id" value="<?php echo esc_attr(isset($cinema['google_place_id']) ? $cinema['google_place_id'] : ''); ?>" class="regular-text" /></td>
          </tr>
          <tr>
            <th scope="row"><label for="cinema_google_business_id">Google Business ID</label></th>
            <td><input name="<?php echo $this->option_name; ?>[cinema][google_business_id]" type="text" id="cinema_google_business_id" value="<?php echo esc_attr(isset($cinema['google_business_id']) ? $cinema['google_business_id'] : ''); ?>" class="regular-text" /></td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

}
