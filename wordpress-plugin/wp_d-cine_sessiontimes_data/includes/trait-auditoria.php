<?php
if (!defined('ABSPATH')) {
  exit;
}

trait WP_DCine_Auditoria_Trait
{
  public function auditoria_page()
  {
    global $wpdb;
    $table = $wpdb->prefix . 'cine_auditoria';

    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    $editing = false;
    $edit_row = null;
    if (($action === 'edit' || $action === 'add') && isset($_GET['auditorium_id'])) {
      $editing = true;
      $aid = sanitize_text_field($_GET['auditorium_id']);
      $edit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE auditorium_id = %s", $aid));
    } elseif ($action === 'add') {
      $editing = true;
      $edit_row = null;
    }

    echo '<div class="wrap"><h1>Auditoria <a class="page-title-action" href="' . esc_url(add_query_arg(array('page' => 'wp-d-cine-auditoria', 'action' => 'add'))) . '">Add New</a></h1>';

    if ($editing) {
      $nonce = wp_create_nonce('wp_d_cine_save_auditorium');
      $aud_id_val = $edit_row ? esc_attr($edit_row->auditorium_id) : '';
      $name_val = $edit_row ? esc_attr($edit_row->name) : '';
      $seat_count_val = $edit_row ? esc_attr($edit_row->seat_count) : '';
      $seatmap_val = $edit_row ? esc_attr($edit_row->seatmap_url) : '';
      $attributes_json = '';
      $seat_classes_json = '';
      if ($edit_row) {
        $attributes_json = !empty($edit_row->attributes) ? json_encode(json_decode($edit_row->attributes, true), JSON_PRETTY_PRINT) : '';
        $seat_classes_json = !empty($edit_row->seat_classes) ? json_encode(json_decode($edit_row->seat_classes, true), JSON_PRETTY_PRINT) : '';
      }
      $read_only_checked = $edit_row && $edit_row->read_only ? 'checked' : '';

      echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
      echo '<input type="hidden" name="action" value="wp_d_cine_save_auditorium" />';
      echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '" />';
      echo '<table class="form-table">';
      echo '<tr><th><label>Auditorium ID</label></th><td><input name="auditorium_id" type="text" value="' . $aud_id_val . '" ' . ($edit_row ? 'readonly' : '') . ' required class="regular-text" /></td></tr>';
      echo '<tr><th><label>Name</label></th><td><input name="name" type="text" value="' . $name_val . '" required class="regular-text" /></td></tr>';
      echo '<tr><th><label>Seat count</label></th><td><input name="seat_count" type="number" min="0" value="' . $seat_count_val . '" class="small-text" /></td></tr>';
      echo '<tr><th><label>Seatmap URL</label></th><td><input name="seatmap_url" type="url" value="' . $seatmap_val . '" class="regular-text" /></td></tr>';
      echo '<tr><th><label>Attributes (JSON)</label></th><td><textarea name="attributes_json" rows="6" class="large-text code">' . esc_textarea($attributes_json) . '</textarea><p class="description">Example: ["Laser","Dolby Atmos","Accessible"]</p></td></tr>';
      echo '<tr><th><label>Seat classes (JSON)</label></th><td><textarea name="seat_classes_json" rows="8" class="large-text code">' . esc_textarea($seat_classes_json) . '</textarea><p class="description">Provide an array of seat class objects. Example: [{"class_id":"standard","display_name":"Standard","features":["Padded"],"base_modifier":0}]</p></td></tr>';
      echo '<tr><th><label>Read Only</label></th><td><label><input type="checkbox" name="read_only" value="1" ' . $read_only_checked . ' /> Mark as read-only (import will not overwrite)</label></td></tr>';
      echo '</table>';
      submit_button($edit_row ? 'Save Auditorium' : 'Create Auditorium');
      if ($edit_row) {
        $del_nonce = wp_create_nonce('wp_d_cine_delete_auditorium');
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin-post.php?action=wp_d_cine_delete_auditorium&auditorium_id=' . rawurlencode($edit_row->auditorium_id) . '&_wpnonce=' . $del_nonce)) . '" onclick="return confirm(\'Delete this auditorium?\')">Delete</a>';
      }
      echo '</form>';
    }

    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 500");
    echo '<table class="widefat"><thead><tr><th>ID</th><th>Auditorium ID</th><th>Name</th><th>Seats</th><th>Read Only</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $r) {
      $edit_link = esc_url(add_query_arg(array('page' => 'wp-d-cine-auditoria', 'action' => 'edit', 'auditorium_id' => $r->auditorium_id), admin_url('admin.php')));
      printf('<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">Edit</a></td></tr>', intval($r->id), esc_html($r->auditorium_id), esc_html($r->name), esc_html($r->seat_count), $r->read_only ? 'Yes' : 'No', $edit_link);
    }
    echo '</tbody></table></div>';
  }

  public function handle_save_auditorium()
  {
    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_d_cine_save_auditorium')) {
      wp_die('Nonce check failed');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'cine_auditoria';

    $auditorium_id = isset($_POST['auditorium_id']) ? sanitize_text_field($_POST['auditorium_id']) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $seat_count = isset($_POST['seat_count']) ? intval($_POST['seat_count']) : null;
    $seatmap_url = isset($_POST['seatmap_url']) ? esc_url_raw($_POST['seatmap_url']) : null;
    $read_only = isset($_POST['read_only']) ? 1 : 0;

    // attributes_json and seat_classes_json: try to decode and re-encode to normalized JSON; if invalid, null
    $attributes_json = null;
    if (!empty($_POST['attributes_json'])) {
      $decoded = json_decode(wp_unslash($_POST['attributes_json']), true);
      if (is_array($decoded)) {
        $attributes_json = wp_json_encode($decoded);
      }
    }
    $seat_classes_json = null;
    if (!empty($_POST['seat_classes_json'])) {
      $decoded2 = json_decode(wp_unslash($_POST['seat_classes_json']), true);
      if (is_array($decoded2)) {
        $seat_classes_json = wp_json_encode($decoded2);
      }
    }

    if (!$auditorium_id) {
      wp_redirect(admin_url('admin.php?page=wp-d-cine-auditoria'));
      exit;
    }

    $exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE auditorium_id = %s", $auditorium_id));
    $payload = array(
      'auditorium_id' => $auditorium_id,
      'name' => $name,
      'seat_count' => $seat_count,
      'seatmap_url' => $seatmap_url,
      'attributes' => $attributes_json,
      'seat_classes' => $seat_classes_json,
      'read_only' => $read_only,
    );
    if ($exists) {
      $wpdb->update($table, $payload, array('auditorium_id' => $auditorium_id));
    } else {
      $wpdb->insert($table, $payload);
    }

    wp_redirect(admin_url('admin.php?page=wp-d-cine-auditoria'));
    exit;
  }

  public function handle_delete_auditorium()
  {
    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    if (!wp_verify_nonce($nonce, 'wp_d_cine_delete_auditorium')) {
      wp_die('Nonce check failed');
    }
    $auditorium_id = isset($_GET['auditorium_id']) ? sanitize_text_field($_GET['auditorium_id']) : '';
    if (!$auditorium_id) wp_die('Missing auditorium id');
    global $wpdb;
    $table = $wpdb->prefix . 'cine_auditoria';
    $wpdb->delete($table, array('auditorium_id' => $auditorium_id));
    wp_redirect(admin_url('admin.php?page=wp-d-cine-auditoria'));
    exit;
  }

}
