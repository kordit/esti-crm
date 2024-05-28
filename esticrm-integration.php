<?php
/*
Plugin Name: EstiCRM Integration
Description: Plugin to integrate with EstiCRM API.
Version: 1.0
Author: Your Name
*/

// Enqueue scripts and styles
function esticrm_enqueue_scripts($hook)
{
    if ($hook != 'toplevel_page_esticrm-integration') {
        return;
    }
    wp_enqueue_style('esticrm-style', plugin_dir_url(__FILE__) . 'css/esticrm-integration.css');
    wp_enqueue_script('esticrm-script', plugin_dir_url(__FILE__) . 'js/esticrm-integration.js', array('jquery'), null, true);
    wp_localize_script('esticrm-script', 'esticrm_ajax_obj', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('esticrm_nonce_action'),
        'mapping' => get_option('esticrm_field_mapping', array())  // Dodajemy tutaj zapisane mapowanie
    ));
}
add_action('admin_enqueue_scripts', 'esticrm_enqueue_scripts');

// Add menu item in admin panel
function esticrm_add_admin_menu()
{
    add_menu_page(
        'EstiCRM Integration',
        'EstiCRM Integration',
        'manage_options',
        'esticrm-integration',
        'esticrm_admin_page',
        'dashicons-admin-generic',
        6
    );
}
add_action('admin_menu', 'esticrm_add_admin_menu');

// Display admin page content
function esticrm_admin_page()
{
    $id = get_option('esticrm_id');
    $token = get_option('esticrm_token');
    $cpt = get_option('esticrm_cpt');
    $acf_fields = get_option('esticrm_acf_fields');
    $field_mapping = get_option('esticrm_field_mapping', array());
    $is_integrated = !empty($id) && !empty($token);

    echo '<div class="wrap">
            <h1>EstiCRM Integration</h1>
            <form id="esticrm-form">' .
        wp_nonce_field('esticrm_nonce_action', 'esticrm_nonce_field', true, false) . '
                <label for="esticrm-id">ID:</label>
                <input type="text" id="esticrm-id" name="esticrm-id" value="' . esc_attr($id) . '" ' . ($is_integrated ? 'disabled' : '') . '>
                <label for="esticrm-token">Token:</label>
                <input type="text" id="esticrm-token" name="esticrm-token" value="' . esc_attr($token) . '" ' . ($is_integrated ? 'disabled' : '') . '>
                <button type="submit" id="esticrm-save-btn">' . ($is_integrated ? 'Usuń integrację' : 'Zapisz integrację') . '</button>
            </form>
            <div id="esticrm-result"></div>';

    if ($is_integrated) {
        echo '<div id="cpt-selection">
                <label for="esticrm-cpt">Wybierz CPT:</label>
                <select id="esticrm-cpt" name="esticrm-cpt">
                    <option value="">Nie ustawiaj</option>
                </select>
                <button id="esticrm-save-cpt-btn">Zapisz CPT</button>
              </div>
              <div id="acf-fields"></div>
              <div id="field-mapping">
                    <button style="display:none;" id="esticrm-save-field-mapping-btn">Zapisz Mapowanie Pól</button>
                </div>
              <button id="esticrm-start-mapping-btn">Zacznij mapowanie</button>
              <div id="mapping-table"></div>
              <button id="esticrm-save-mapping-btn" style="display:none;">Zapisz mapowanie</button>
              <button id="start-integration-btn" style="display:none;">Rozpocznij integrację</button>';
    }

    echo '  <div id="acf-fields"></div>
            <input type="hidden" id="esticrm-selected-cpt" value="' . esc_attr($cpt) . '">
            <input type="hidden" id="esticrm-selected-acf-fields" value="' . esc_attr(json_encode($acf_fields)) . '">
          </div>';
}

function et_get_attachment($url)
{
    $path_info = pathinfo($url);
    $parts = explode('/', $path_info['dirname']);
    $file_name = $parts[4] . '_' . $path_info['filename'];

    $attachment = get_page_by_title($file_name, OBJECT, 'attachment');

    if ($attachment === NULL) {
        $thumbnail_url = $url;
        $thumbnail_data = file_get_contents($thumbnail_url);

        if ($thumbnail_data !== false) {
            $filename = wp_unique_filename(wp_upload_dir()['path'], basename($thumbnail_url));
            $file_path = wp_upload_dir()['path'] . '/' . $filename;

            $file_saved = file_put_contents($file_path, $thumbnail_data);

            if ($file_saved !== false) {
                $file_type = wp_check_filetype($file_path)['type'];

                $attachment = array(
                    'guid'           => wp_upload_dir()['url'] . '/' . $filename,
                    'post_mime_type' => $file_type,
                    'post_title'     => $file_name,
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                );

                $attachment_id = wp_insert_attachment($attachment, $file_path);
                wp_generate_attachment_metadata($attachment_id, $file_path);
            }
        }
    } else {
        $attachment_id = $attachment->ID;
    }

    return $attachment_id;
}

function esticrm_fetch_api_data()
{
    $id = get_option('esticrm_id');
    $token = get_option('esticrm_token');

    if (empty($id) || empty($token)) {
        return new WP_Error('missing_data', 'ID or token is missing');
    }

    $api_url = "https://app.esticrm.pl/apiClient/offer/list?company={$id}&token={$token}";
    $response = @file_get_contents($api_url);

    if ($response === FALSE) {
        $error = error_get_last();
        return new WP_Error('api_error', 'Failed to fetch data from API: ' . $error['message']);
    }

    $data = json_decode($response, true);

    if (!isset($data['data']) || !is_array($data['data'])) {
        return new WP_Error('invalid_data', 'Invalid data format from API');
    }

    // Przejście przez każdy element w odpowiedzi
    foreach ($data['data'] as &$offer) {
        if (isset($offer['main_picture']) && isset($offer['pictures']) && is_array($offer['pictures'])) {
            $main_picture_number = $offer['main_picture'];
            // Szukamy odpowiedniego URL dla main_picture
            foreach ($offer['pictures'] as $picture_url) {
                if (strpos($picture_url, $main_picture_number) !== false) {
                    $offer['main_picture'] = $picture_url;
                    break;
                }
            }
        }
    }

    return $data['data'];
}


function esticrm_start_integration()
{
    if (!isset($_POST['esticrm_nonce']) || !wp_verify_nonce($_POST['esticrm_nonce'], 'esticrm_nonce_action')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    $id = get_option('esticrm_id');
    $token = get_option('esticrm_token');
    $cpt = get_option('esticrm_cpt');
    $field_mapping = get_option('esticrm_field_mapping', array());

    if (empty($id) || empty($token) || empty($cpt) || empty($field_mapping)) {
        wp_send_json_error('Missing required data for integration');
        return;
    }

    $api_data = esticrm_fetch_api_data();
    if (is_wp_error($api_data)) {
        wp_send_json_error($api_data->get_error_message());
        return;
    }

    $logs = [];
    $api_titles = [];
    $existing_posts = get_posts(array(
        'post_type' => $cpt,
        'numberposts' => -1,
        'post_status' => 'publish',
    ));

    // Create a map of existing post titles to their IDs
    $existing_post_map = [];
    foreach ($existing_posts as $existing_post) {
        $existing_post_map[$existing_post->post_title] = $existing_post->ID;
    }

    foreach ($api_data as $record) {
        $title_field = array_search('wordpress_title', $field_mapping);
        $post_title = isset($record[$title_field]) ? $record[$title_field] : '';

        if (empty($post_title)) {
            $logs[] = "<div class='log-entry'>Pominęto wpis, brak tytułu.</div>";
            continue;
        }

        $api_titles[] = $post_title;
        $post_data = array(
            'post_type' => $cpt,
            'post_status' => 'publish',
            'post_title' => $post_title,
        );

        if (isset($existing_post_map[$post_title])) {
            $post_data['ID'] = $existing_post_map[$post_title];
            $post_id = wp_update_post($post_data);
            $action = "zaktualizowano";
        } else {
            $post_id = wp_insert_post($post_data);
            $action = "dodano";
        }

        if (!is_wp_error($post_id)) {
            $log_message = "<div class='log-entry'>Wpis '$post_title' został $action.";
            $log_message .= "<div class='fields-updated'>Pola uzupełnione: ";

            $taxonomy_terms = [];

            foreach ($field_mapping as $key => $mapped_field) {
                if (isset($record[$key])) {
                    if (strpos($mapped_field, 'wordpress_') === 0) {
                        $wp_field = str_replace('wordpress_', '', $mapped_field);
                        if ($wp_field !== 'title') { // Title is already set in post_data
                            update_post_meta($post_id, $wp_field, $record[$key]);
                            $log_message .= "<div class='field'><span class='field-name'>$wp_field</span>: <span class='field-value'>" . $record[$key] . "</span></div>";
                        }
                    } elseif (strpos($mapped_field, 'taxonomy_') === 0) {
                        $taxonomy = str_replace('taxonomy_', '', $mapped_field);
                        $term = term_exists($record[$key], $taxonomy);
                        if (!$term) {
                            $term = wp_insert_term($record[$key], $taxonomy);
                        }
                        if (!is_wp_error($term)) {
                            $taxonomy_terms[$taxonomy][] = (int)$term['term_id'];
                            $log_message .= "<div class='field'><span class='field-name'>$taxonomy</span>: <span class='field-value'>" . $record[$key] . "</span></div>";
                        }
                    } elseif (strpos($mapped_field, 'acf_') === 0) {
                        $acf_field = str_replace('acf_', '', $mapped_field);
                        update_field($acf_field, $record[$key], $post_id);
                        $log_message .= "<div class='field'><span class='field-name'>$acf_field</span>: <span class='field-value'>" . $record[$key] . "</span></div>";
                    }
                }
            }

            // Assign taxonomy terms
            foreach ($taxonomy_terms as $taxonomy => $terms) {
                wp_set_post_terms($post_id, $terms, $taxonomy);
            }

            // Set post thumbnail
            if (isset($record['main_picture'])) {
                $attachment_id = et_get_attachment($record['main_picture']);
                if (!is_wp_error($attachment_id)) {
                    set_post_thumbnail($post_id, $attachment_id);
                    $log_message .= "<div class='field'><span class='field-name'>post_thumbnail</span>: <span class='field-value'>$record[main_picture]</span></div>";
                }
            }

            $log_message .= "</div></div>";
            $logs[] = $log_message;
        } else {
            $logs[] = "<div class='log-entry'>Wpis '$post_title' nie został dodany, błąd: " . $post_id->get_error_message() . "</div>";
        }
    }

    foreach ($existing_post_map as $existing_title => $existing_id) {
        if (!in_array($existing_title, $api_titles)) {
            wp_delete_post($existing_id, true);
            $logs[] = "<div class='log-entry'>Wpis '$existing_title' został usunięty, ponieważ nie jest już obecny w danych API.</div>";
        }
    }

    wp_send_json_success(array('log' => $logs));
}
add_action('wp_ajax_esticrm_start_integration', 'esticrm_start_integration');

// AJAX handler to start mapping and fetch first record
// AJAX handler to start mapping and fetch first record
function esticrm_start_mapping()
{
    if (!isset($_POST['esticrm_nonce']) || !wp_verify_nonce($_POST['esticrm_nonce'], 'esticrm_nonce_action')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    $data = esticrm_fetch_api_data();

    if (is_wp_error($data)) {
        wp_send_json_error($data->get_error_message());
        return;
    }

    if (!empty($data)) {
        wp_send_json_success($data[0]);
    } else {
        wp_send_json_error('No data found in API response or invalid data format');
    }
}
add_action('wp_ajax_esticrm_start_mapping', 'esticrm_start_mapping');

// AJAX handler to save the mapping
function esticrm_save_mapping()
{
    check_ajax_referer('esticrm_nonce_action', 'esticrm_nonce');
    $mapping = isset($_POST['mapping']) ? array_map('sanitize_text_field', $_POST['mapping']) : array();
    update_option('esticrm_field_mapping', $mapping);
    wp_send_json_success('Mapowanie zostało zapisane.');
}
add_action('wp_ajax_esticrm_save_mapping', 'esticrm_save_mapping');


// AJAX handler to save or remove integration
function esticrm_save_integration()
{
    if (!isset($_POST['esticrm_nonce']) || !wp_verify_nonce($_POST['esticrm_nonce'], 'esticrm_nonce_action')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    if (isset($_POST['remove']) && $_POST['remove'] == '1') {
        delete_option('esticrm_id');
        delete_option('esticrm_token');
        delete_option('esticrm_cpt');
        delete_option('esticrm_acf_fields');
        delete_option('esticrm_field_mapping');
        wp_send_json_success('Integracja została usunięta.');
    } else {
        $id = sanitize_text_field($_POST['id']);
        $token = sanitize_text_field($_POST['token']);
        update_option('esticrm_id', $id);
        update_option('esticrm_token', $token);
        wp_send_json_success('Integracja została zapisana.');
    }
}
add_action('wp_ajax_esticrm_save_integration', 'esticrm_save_integration');

// AJAX handler to get CPTs
function esticrm_get_cpts()
{
    check_ajax_referer('esticrm_nonce_action', 'esticrm_nonce');
    $post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
    $cpts = array();
    foreach ($post_types as $post_type) {
        $cpts[] = array('name' => $post_type->label, 'value' => $post_type->name);
    }
    wp_send_json_success($cpts);
}
add_action('wp_ajax_esticrm_get_cpts', 'esticrm_get_cpts');

// AJAX handler to save CPT
function esticrm_save_cpt()
{
    check_ajax_referer('esticrm_nonce_action', 'esticrm_nonce');
    $cpt = sanitize_text_field($_POST['cpt']);
    if ($cpt === '') {
        delete_option('esticrm_cpt');
        delete_option('esticrm_acf_fields');
        delete_option('esticrm_field_mapping');
        wp_send_json_success('CPT zostało usunięte.');
    } else {
        update_option('esticrm_cpt', $cpt);
        wp_send_json_success('CPT zostało zapisane.');
    }
}
add_action('wp_ajax_esticrm_save_cpt', 'esticrm_save_cpt');

// AJAX handler to get fields and taxonomies for selected CPT
function esticrm_get_cpt_fields()
{
    check_ajax_referer('esticrm_nonce_action', 'esticrm_nonce');
    $cpt = sanitize_text_field($_POST['cpt']);
    $fields = array();

    if (!empty($cpt)) {
        $fields['title'] = 'Title';
        $fields['thumbnail'] = 'Thumbnail';
        $fields['excerpt'] = 'Excerpt';
        $fields['content'] = 'Content';

        $taxonomies = get_object_taxonomies($cpt, 'objects');
        foreach ($taxonomies as $taxonomy) {
            $fields['terms'][$taxonomy->name] = $taxonomy->label;
        }
    }

    wp_send_json_success($fields);
}
add_action('wp_ajax_esticrm_get_cpt_fields', 'esticrm_get_cpt_fields');

// AJAX handler to get ACF fields for selected CPT
function esticrm_get_acf_fields()
{
    check_ajax_referer('esticrm_nonce_action', 'esticrm_nonce');
    $cpt = sanitize_text_field($_POST['cpt']);
    $acf_fields = array();

    if (!empty($cpt)) {
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups(array('post_type' => $cpt));
            foreach ($field_groups as $group) {
                $fields = acf_get_fields($group['ID']);
                foreach ($fields as $field) {
                    $acf_fields[$field['name']] = $field['label'];
                }
            }
        }
    }

    wp_send_json_success($acf_fields);
}
add_action('wp_ajax_esticrm_get_acf_fields', 'esticrm_get_acf_fields');

// AJAX handler to save selected ACF fields
function esticrm_save_acf_fields()
{
    check_ajax_referer('esticrm_nonce_action', 'esticrm_nonce');
    $acf_fields = isset($_POST['acf_fields']) ? array_map('sanitize_text_field', $_POST['acf_fields']) : array();
    update_option('esticrm_acf_fields', $acf_fields);
    wp_send_json_success('Pola ACF zostały zapisane.');
}
add_action('wp_ajax_esticrm_save_acf_fields', 'esticrm_save_acf_fields');

// AJAX handler to save field mapping
function esticrm_save_field_mapping()
{
    check_ajax_referer('esticrm_nonce_action', 'esticrm_nonce');
    $fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : array();
    update_option('esticrm_field_mapping', $fields);
    wp_send_json_success('Mapowanie pól zostało zapisane.');
}
add_action('wp_ajax_esticrm_save_field_mapping', 'esticrm_save_field_mapping');
