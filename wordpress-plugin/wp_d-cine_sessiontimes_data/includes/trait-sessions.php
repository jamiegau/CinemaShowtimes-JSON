<?php
if (!defined('ABSPATH')) {
  exit;
}

trait WP_DCine_Sessions_Trait
{
  public function sessions_page()
  {
    global $wpdb;
    $table = $wpdb->prefix . 'cine_sessions';
    $films_table = $wpdb->prefix . 'cine_films';

    // detect action add/edit
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    $editing = false;
    $edit_row = null;
    if (($action === 'edit' || $action === 'add') && isset($_GET['session_id'])) {
      $editing = true;
      $sid = sanitize_text_field($_GET['session_id']);
      $edit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %s", $sid));
    } elseif ($action === 'add') {
      $editing = true;
      $edit_row = null;
    }

    echo '<div class="wrap"><h1>Cine Sessions <a class="page-title-action" href="' . esc_url(add_query_arg(array('page' => 'wp-d-cine-sessions', 'action' => 'add'))) . '">Add New</a></h1>';

    if ($editing) {
      $nonce = wp_create_nonce('wp_d_cine_save_session');
      $session_id_val = $edit_row ? esc_attr($edit_row->session_id) : '';
      $film_id_val = $edit_row ? esc_attr($edit_row->film_id) : '';
      $auditorium_val = $edit_row ? esc_attr($edit_row->auditorium_id) : '';
      // Format for HTML5 datetime-local input (YYYY-MM-DDTHH:MM)
      if ($edit_row && !empty($edit_row->start_time_local)) {
        $start_local_val = esc_attr(date('Y-m-d\TH:i', strtotime($edit_row->start_time_local)));
      } else {
        $start_local_val = '';
      }
      $start_utc_val = $edit_row ? esc_attr($edit_row->start_time_utc) : '';
      $seating_capacity_val = $edit_row ? esc_attr($edit_row->seating_capacity) : '';
      $seats_available_val = $edit_row ? esc_attr($edit_row->seats_available) : '';
      $read_only_checked = $edit_row && $edit_row->read_only ? 'checked' : '';

      // film options
      $film_rows = $wpdb->get_results("SELECT film_id, title FROM {$films_table} ORDER BY title ASC");

      echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
      echo '<input type="hidden" name="action" value="wp_d_cine_save_session" />';
      echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '" />';
      echo '<table class="form-table">';
      echo '<tr><th><label>Session ID</label></th><td><input name="session_id" type="text" value="' . $session_id_val . '" ' . ($edit_row ? 'readonly' : '') . ' required class="regular-text" /></td></tr>';
      echo '<tr><th><label>Film</label></th><td><select name="film_id">';
      foreach ($film_rows as $fr) {
        $sel = ($film_id_val && $film_id_val === $fr->film_id) ? 'selected' : '';
        printf('<option value="%s" %s>%s</option>', esc_attr($fr->film_id), $sel, esc_html($fr->title));
      }
      echo '</select></td></tr>';
      echo '<tr><th><label>Auditorium</label></th><td><input name="auditorium_id" type="text" value="' . $auditorium_val . '" class="regular-text" /></td></tr>';
      echo '<tr><th><label>Start Time (local)</label></th><td><input name="start_time_local" type="datetime-local" value="' . $start_local_val . '" class="regular-text" /></td></tr>';
      echo '<tr><th><label>Start Time (UTC)</label></th><td><input name="start_time_utc" type="text" value="' . $start_utc_val . '" class="regular-text" /></td></tr>';
      echo '<tr><th><label>Seating capacity</label></th><td><input name="seating_capacity" type="number" value="' . $seating_capacity_val . '" class="small-text" /></td></tr>';
      echo '<tr><th><label>Seats available</label></th><td><input name="seats_available" type="number" value="' . $seats_available_val . '" class="small-text" /></td></tr>';
      echo '<tr><th><label>Read Only</label></th><td><label><input type="checkbox" name="read_only" value="1" ' . $read_only_checked . ' /> Mark as read-only (import will not overwrite)</label></td></tr>';
      echo '</table>';
      submit_button($edit_row ? 'Save Session' : 'Create Session');
      if ($edit_row) {
        $del_nonce = wp_create_nonce('wp_d_cine_delete_session');
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin-post.php?action=wp_d_cine_delete_session&session_id=' . rawurlencode($edit_row->session_id) . '&_wpnonce=' . $del_nonce)) . '" onclick="return confirm(\'Delete this session?\')">Delete</a>';
      }
      echo '</form>';
    }

    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY start_time_local DESC LIMIT 200");
    echo '<table class="widefat"><thead><tr><th>ID</th><th>Session ID</th><th>Film ID</th><th>Start (local)</th><th>Read Only</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $r) {
      $edit_link = esc_url(add_query_arg(array('page' => 'wp-d-cine-sessions', 'action' => 'edit', 'session_id' => $r->session_id), admin_url('admin.php')));
      printf('<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">Edit</a></td></tr>', intval($r->id), esc_html($r->session_id), esc_html($r->film_id), esc_html($r->start_time_local), $r->read_only ? 'Yes' : 'No', $edit_link);
    }
    echo '</tbody></table></div>';
  }

  public function handle_save_session()
  {
    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_d_cine_save_session')) {
      wp_die('Nonce check failed');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'cine_sessions';

    $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
    $film_id = isset($_POST['film_id']) ? sanitize_text_field($_POST['film_id']) : '';
    $auditorium = isset($_POST['auditorium_id']) ? sanitize_text_field($_POST['auditorium_id']) : null;
    $start_local = isset($_POST['start_time_local']) ? sanitize_text_field($_POST['start_time_local']) : null;
    $start_utc = isset($_POST['start_time_utc']) ? sanitize_text_field($_POST['start_time_utc']) : null;
    $seating_capacity = isset($_POST['seating_capacity']) ? intval($_POST['seating_capacity']) : null;
    $seats_available = isset($_POST['seats_available']) ? intval($_POST['seats_available']) : null;
    $read_only = isset($_POST['read_only']) ? 1 : 0;

    if (!$session_id) {
      wp_redirect(admin_url('admin.php?page=wp-d-cine-sessions'));
      exit;
    }

    $exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %s", $session_id));
    $payload = array(
      'session_id' => $session_id,
      'film_id' => $film_id,
      'auditorium_id' => $auditorium,
      'start_time_local' => $start_local ? date('Y-m-d H:i:s', strtotime($start_local)) : null,
      'start_time_utc' => $start_utc ? date('Y-m-d H:i:s', strtotime($start_utc)) : null,
      'seating_capacity' => $seating_capacity,
      'seats_available' => $seats_available,
      'read_only' => $read_only,
    );
    if ($exists) {
      $wpdb->update($table, $payload, array('session_id' => $session_id));
    } else {
      $wpdb->insert($table, $payload);
    }

    wp_redirect(admin_url('admin.php?page=wp-d-cine-sessions'));
    exit;
  }

  public function handle_delete_session()
  {
    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    if (!wp_verify_nonce($nonce, 'wp_d_cine_delete_session')) {
      wp_die('Nonce check failed');
    }
    $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
    if (!$session_id) wp_die('Missing session id');
    global $wpdb;
    $table = $wpdb->prefix . 'cine_sessions';
    $wpdb->delete($table, array('session_id' => $session_id));
    wp_redirect(admin_url('admin.php?page=wp-d-cine-sessions'));
    exit;
  }

}
