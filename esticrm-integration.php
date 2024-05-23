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
        'nonce' => wp_create_nonce('esticrm_nonce_action')
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
    $is_integrated = !empty($id) && !empty($token);

    echo '<div class="wrap">
            <h1>EstiCRM Integration</h1>
            <form id="esticrm-form">
                ' . wp_nonce_field('esticrm_nonce_action', 'esticrm_nonce_field') . '
                <label for="esticrm-id">ID:</label>
                <input type="text" id="esticrm-id" name="esticrm-id" value="' . esc_attr($id) . '" ' . ($is_integrated ? 'disabled' : '') . '>
                <label for="esticrm-token">Token:</label>
                <input type="text" id="esticrm-token" name="esticrm-token" value="' . esc_attr($token) . '" ' . ($is_integrated ? 'disabled' : '') . '>
                <button type="submit" id="esticrm-save-btn">' . ($is_integrated ? 'Usuń integrację' : 'Zapisz integrację') . '</button>
            </form>
            <div id="esticrm-result"></div>
            ' . ($is_integrated ? '<button id="esticrm-run-btn">Uruchom integrację</button>' : '') . '
          </div>';
}

// AJAX handler to save or remove integration
function esticrm_save_integration()
{
    if (!isset($_POST['esticrm_nonce']) || !wp_verify_nonce($_POST['esticrm_nonce'], 'esticrm_nonce_action')) {
        error_log('Nonce verification failed');
        wp_send_json_error('Invalid nonce');
        return;
    }

    if (isset($_POST['remove']) && $_POST['remove'] == '1') {
        delete_option('esticrm_id');
        delete_option('esticrm_token');
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

// AJAX handler to run integration
function esticrm_run_integration()
{
    if (!isset($_POST['esticrm_nonce']) || !wp_verify_nonce($_POST['esticrm_nonce'], 'esticrm_nonce_action')) {
        error_log('Nonce verification failed');
        wp_send_json_error('Invalid nonce');
        return;
    }

    $id = get_option('esticrm_id');
    $token = get_option('esticrm_token');

    // Construct the API URL
    $api_url = "https://app.esticrm.pl/apiClient/offer/list?company={$id}&token={$token}";

    // Fetch the API response using file_get_contents
    $json = file_get_contents($api_url);

    if ($json === FALSE) {
        wp_send_json_error('Failed to fetch data from API');
    } else {
        $result = json_decode($json, true);
        wp_send_json_success($result);
    }
}
add_action('wp_ajax_esticrm_run_integration', 'esticrm_run_integration');
