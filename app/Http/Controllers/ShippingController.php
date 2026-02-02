<?php
namespace App\Http\Controllers;

use FC\Shipping\Services\InertiaBridge;

class ShippingController {
    public function index() {
        global $wpdb;

        // 1. Fetch all shipping methods
        $shipping_methods = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}fct_shipping_methods");

        //2. Get current mode/method ID
        $current_mode = get_option('fc_restriction_mode', 'global');

        $allowed = [];
        $excluded = [];

        $table_restrictions = "{$wpdb->prefix}fc_shipping_method_restrictions";

        // Get data according to mode (same logic as handle_ajax_get_settings in plugin file)
        $search_id = ($current_mode === 'global') ? 0 : (int)$current_mode;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT allowed_countries, excluded_countries FROM $table_restrictions WHERE method_id = %d",
            $search_id
        ));

        if ($row) {
            $allowed = json_decode($row->allowed_countries, true) ?: [];
            $excluded = json_decode($row->excluded_countries, true) ?: [];
        }

// 3. Update the log formatted part like this
$table_meta = "{$wpdb->prefix}fct_order_meta";
$raw_logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT order_id, meta_value, created_at FROM $table_meta
        WHERE meta_key = %s
        ORDER BY created_at DESC LIMIT 50",
        '_fc_shipping_restrictions'
    )
);
$formatted_logs = [];
$methods_table = "{$wpdb->prefix}fct_shipping_methods";  // For fetching titles
foreach($raw_logs as $log) {
    $meta = json_decode($log->meta_value, true);

    // New: Compute method name based on mode
    $mode = $meta['mode'] ?? 'global';
    $method_name = 'Global';  // Default for global mode
    if ($mode !== 'global' && is_numeric($mode)) {
        $method_name = $wpdb->get_var($wpdb->prepare(
            "SELECT title FROM $methods_table WHERE id = %d",
            (int)$mode
        )) ?: 'Unknown Method';  // Fallback if no title found
    }

    $formatted_logs[] = [
        'id' => $log->order_id,
        'method' => $method_name,
        'allowed' => !empty($meta['allowed_countries']) ? implode(', ', (array)$meta['allowed_countries']) : 'All Countries',
        'excluded' => !empty($meta['excluded_countries']) ? implode(', ', (array)$meta['excluded_countries']) : 'None',
        'date' => $log->created_at

    ];
}


      // In the InertiaBridge render, update the props to match the new key
return InertiaBridge::render('Shipping/Restrictions', [
    'allowed' => (array) $allowed,
    'excluded' => (array) $excluded,
    'mode' => $current_mode,
    'shippingMethods' => $shipping_methods,
    'logs' => $formatted_logs,  // Now has 'method' instead of 'country'
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('fc_shipping_nonce')
]);
    }
}
