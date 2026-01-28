<?php
namespace App\Http\Controllers;

use FC\Shipping\Services\InertiaBridge;

class ShippingController {
    public function index() {
        global $wpdb;

        // ১. ডাটাবেস থেকে ডেটা ফেচ করা
        $shipping_methods = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}fct_shipping_methods");
        $allowed = get_option('fc_allowed_countries', []);
        $excluded = get_option('fc_excluded_countries', []);
        $current_mode = get_option('fc_restriction_mode', '');

        // ২. লগ ফরমেট করা
        $table_name = $wpdb->prefix . 'fct_order_meta';
        $raw_logs = $wpdb->get_results("SELECT order_id, meta_value, created_at FROM $table_name WHERE meta_key = '_fc_shipping_restrictions' ORDER BY created_at DESC LIMIT 100");

        $formatted_logs = [];
        foreach($raw_logs as $log) {
            $meta = json_decode($log->meta_value, true);
            $formatted_logs[] = [
                'id' => $log->order_id,
                'country' => $meta['order_country'] ?? 'N/A',
                'allowed' => implode(', ', (array)($meta['allowed_countries'] ?? [])),
                'excluded' => implode(', ', (array)($meta['excluded_countries'] ?? [])),
                'status' => str_replace('●', '', $meta['validation_status'] ?? 'N/A'),
                'date' => $log->created_at
            ];
        }

        // ৩. ইনর্শিয়া ব্রিজ দিয়ে Vue পেজে ডেটা পাঠানো
        return InertiaBridge::render('Shipping/Restrictions', [
            'allowed' => $allowed,
            'excluded' => $excluded,
            'mode' => $current_mode,
            'shippingMethods' => $shipping_methods,
            'logs' => $formatted_logs,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fc_shipping_nonce')
        ]);
    }
}
