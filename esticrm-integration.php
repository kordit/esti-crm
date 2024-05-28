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

    // Construct the API URL
    $api_url = "https://app.esticrm.pl/apiClient/offer/list?company={$id}&token={$token}";

    // Fetch the API response using file_get_contents
    $json = @file_get_contents($api_url);

    if ($json === FALSE) {
        $error = error_get_last();
        error_log('API request failed: ' . $error['message']);
        wp_send_json_error('Failed to fetch data from API: ' . $error['message']);
        return;
    }

    $result = json_decode($json, true);

    if (!isset($result['data']) || !is_array($result['data'])) {
        wp_send_json_error('Invalid data format from API');
        return;
    }

    $logs = [];

    foreach ($result['data'] as $record) {
        $title_field = array_search('wordpress_title', $field_mapping);
        $post_title = isset($record[$title_field]) ? $record[$title_field] : '';

        if (empty($post_title)) {
            $logs[] = "Pominęto wpis, brak tytułu.";
            continue;
        }

        // Check for existing post
        $existing_post = get_page_by_title($post_title, OBJECT, $cpt);
        $post_data = array(
            'post_type' => $cpt,
            'post_status' => 'publish',
            'post_title' => $post_title,
        );

        if ($existing_post) {
            $post_data['ID'] = $existing_post->ID;
            $post_id = wp_update_post($post_data);
            $action = "zaktualizowano";
        } else {
            $post_id = wp_insert_post($post_data);
            $action = "dodano";
        }

        if (!is_wp_error($post_id)) {
            $log_message = "Wpis '$post_title' został $action.";
            $log_message .= " Pola uzupełnione: ";

            $taxonomy_terms = [];

            foreach ($field_mapping as $key => $mapped_field) {
                if (isset($record[$key])) {
                    if (strpos($mapped_field, 'wordpress_') === 0) {
                        $wp_field = str_replace('wordpress_', '', $mapped_field);
                        if ($wp_field !== 'title') { // Title is already set in post_data
                            update_post_meta($post_id, $wp_field, $record[$key]);
                            $log_message .= "$wp_field, ";
                        }
                    } elseif (strpos($mapped_field, 'taxonomy_') === 0) {
                        $taxonomy = str_replace('taxonomy_', '', $mapped_field);
                        $taxonomy_terms[$taxonomy][] = $record[$key]; // Collect terms for each taxonomy
                        $log_message .= "$taxonomy, ";
                    } elseif (strpos($mapped_field, 'acf_') === 0) {
                        $acf_field = str_replace('acf_', '', $mapped_field);
                        update_field($acf_field, $record[$key], $post_id);
                        $log_message .= "$acf_field, ";
                    }
                }
            }

            // Assign taxonomy terms
            foreach ($taxonomy_terms as $taxonomy => $terms) {
                wp_set_post_terms($post_id, $terms, $taxonomy);
            }

            $logs[] = rtrim($log_message, ', ');
        } else {
            $logs[] = "Wpis '$post_title' nie został dodany, błąd: " . $post_id->get_error_message();
        }
    }

    wp_send_json_success($logs);
}
add_action('wp_ajax_esticrm_start_integration', 'esticrm_start_integration');


// AJAX handler to start mapping and fetch first record
// AJAX handler to start mapping and fetch first record
function esticrm_start_mapping()
{
    if (!isset($_POST['esticrm_nonce']) || !wp_verify_nonce($_POST['esticrm_nonce'], 'esticrm_nonce_action')) {
        error_log('Nonce verification failed');
        wp_send_json_error('Invalid nonce');
        return;
    }

    $id = get_option('esticrm_id');
    $token = get_option('esticrm_token');

    if (empty($id) || empty($token)) {
        wp_send_json_error('ID or token is missing');
        return;
    }

    // Construct the API URL
    $api_url = "https://app.esticrm.pl/apiClient/offer/list?company={$id}&token={$token}";

    // Fetch the API response using file_get_contents
    $json = @file_get_contents($api_url);

    if ($json === FALSE) {
        $error = error_get_last();
        error_log('API request failed: ' . $error['message']);
        wp_send_json_error('Failed to fetch data from API: ' . $error['message']);
        return;
    }

    $result = json_decode($json, true);

    // Logowanie odpowiedzi z API
    error_log('Full API Response: ' . print_r($result, true));

    if (isset($result['data']) && is_array($result['data']) && !empty($result['data'])) {
        $first_record = $result['data'][0];
        wp_send_json_success($first_record);
    } else {
        error_log('No data found or invalid format. Response body: ' . $json);
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
