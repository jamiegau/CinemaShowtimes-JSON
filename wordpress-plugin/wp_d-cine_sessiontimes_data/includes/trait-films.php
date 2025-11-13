<?php
if (!defined('ABSPATH')) {
  exit;
}

trait WP_DCine_Films_Trait
{
  public function films_page()
  {
    global $wpdb;
    $table = $wpdb->prefix . 'cine_films';

    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    $editing = false;
    $edit_row = null;
    if (($action === 'edit' || $action === 'add') && isset($_GET['film_id'])) {
      $editing = true;
      $fid = sanitize_text_field($_GET['film_id']);
      $edit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE film_id = %s", $fid));
    } elseif ($action === 'add') {
      $editing = true;
      $edit_row = null;
    }

    echo '<div class="wrap"><h1>Cine Films <a class="page-title-action" href="' . esc_url(add_query_arg(array('page' => 'wp-d-cine-films', 'action' => 'add'))) . '">Add New</a></h1>';

    if ($editing) {
      $nonce = wp_create_nonce('wp_d_cine_save_film');
      // When editing, use the existing film_id. When adding, suggest the next likely numeric ID
      if ($edit_row) {
        $film_id_val = esc_attr($edit_row->film_id);
      } else {
        // Suggest next numeric ID based on existing numeric film_id values
        $suggested_id = '';
        try {
          $max_id = $wpdb->get_var("SELECT film_id FROM {$table} WHERE film_id REGEXP '^[0-9]+$' ORDER BY CAST(film_id AS UNSIGNED) DESC LIMIT 1");
        } catch (\Exception $e) {
          $max_id = null;
        }
        if ($max_id !== null && $max_id !== '') {
          $next = intval($max_id) + 1;
          $suggested_id = (string) $next;
        } else {
          $suggested_id = '1';
        }
        $film_id_val = esc_attr($suggested_id);
      }
      $title_val = $edit_row ? esc_attr($edit_row->title) : '';
      $runtime_val = $edit_row ? esc_attr($edit_row->runtime_minutes) : '';
      $rating_val = $edit_row ? esc_attr($edit_row->rating) : '';
      $synopsis_val = $edit_row ? esc_textarea($edit_row->synopsis) : '';
      $read_only_checked = $edit_row && $edit_row->read_only ? 'checked' : '';

      echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
      echo '<input type="hidden" name="action" value="wp_d_cine_save_film" />';
      echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '" />';
      echo '<table class="form-table">';
      echo '<tr><th><label>Film ID</label></th><td><input name="film_id" type="text" value="' . $film_id_val . '" ' . ($edit_row ? 'readonly' : '') . ' required class="regular-text" /></td></tr>';
      echo '<tr><th><label>Title</label></th><td><input name="title" type="text" value="' . $title_val . '" required class="regular-text" /></td></tr>';
      echo '<tr><th><label>Runtime (min)</label></th><td><input name="runtime_minutes" type="number" min="0" value="' . $runtime_val . '" class="small-text" /></td></tr>';
      echo '<tr><th><label>Rating</label></th><td><input name="rating" type="text" value="' . $rating_val . '" class="regular-text" /></td></tr>';
      echo '<tr><th><label>Synopsis</label></th><td><textarea name="synopsis" class="large-text">' . $synopsis_val . '</textarea></td></tr>';
      echo '<tr><th><label>Read Only</label></th><td><label><input type="checkbox" name="read_only" value="1" ' . $read_only_checked . ' /> Mark as read-only (import will not overwrite)</label></td></tr>';
      echo '</table>';
      submit_button($edit_row ? 'Save Film' : 'Create Film');
      if ($edit_row) {
        $del_nonce = wp_create_nonce('wp_d_cine_delete_film');
        echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin-post.php?action=wp_d_cine_delete_film&film_id=' . rawurlencode($edit_row->film_id) . '&_wpnonce=' . $del_nonce)) . '" onclick="return confirm(\'Delete this film?\')">Delete</a>';
      }
      echo '</form>';
    }

    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT 200");
    echo '<table class="widefat"><thead><tr><th>ID</th><th>Film ID</th><th>Title</th><th>Status</th><th>Read Only</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $r) {
      $edit_link = esc_url(add_query_arg(array('page' => 'wp-d-cine-films', 'action' => 'edit', 'film_id' => $r->film_id), admin_url('admin.php')));
      printf('<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">Edit</a></td></tr>', intval($r->id), esc_html($r->film_id), esc_html($r->title), esc_html($r->status), $r->read_only ? 'Yes' : 'No', $edit_link);
    }
    echo '</tbody></table></div>';
  }

  public function handle_save_film()
  {
    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_d_cine_save_film')) {
      wp_die('Nonce check failed');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'cine_films';

    $film_id = isset($_POST['film_id']) ? sanitize_text_field($_POST['film_id']) : '';
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $runtime = isset($_POST['runtime_minutes']) ? intval($_POST['runtime_minutes']) : null;
    $rating = isset($_POST['rating']) ? sanitize_text_field($_POST['rating']) : null;
    $synopsis = isset($_POST['synopsis']) ? wp_kses_post($_POST['synopsis']) : null;
    $read_only = isset($_POST['read_only']) ? 1 : 0;

    if (!$film_id) {
      wp_redirect(admin_url('admin.php?page=wp-d-cine-films'));
      exit;
    }

    $exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE film_id = %s", $film_id));
    $payload = array(
      'film_id' => $film_id,
      'title' => $title,
      'runtime_minutes' => $runtime,
      'rating' => $rating,
      'synopsis' => $synopsis,
      'read_only' => $read_only,
    );
    if ($exists) {
      $wpdb->update($table, $payload, array('film_id' => $film_id));
    } else {
      $wpdb->insert($table, $payload);
    }

    wp_redirect(admin_url('admin.php?page=wp-d-cine-films'));
    exit;
  }

  public function handle_delete_film()
  {
    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    if (!wp_verify_nonce($nonce, 'wp_d_cine_delete_film')) {
      wp_die('Nonce check failed');
    }
    $film_id = isset($_GET['film_id']) ? sanitize_text_field($_GET['film_id']) : '';
    if (!$film_id) wp_die('Missing film id');
    global $wpdb;
    $table = $wpdb->prefix . 'cine_films';
    $wpdb->delete($table, array('film_id' => $film_id));
    wp_redirect(admin_url('admin.php?page=wp-d-cine-films'));
    exit;
  }

}
