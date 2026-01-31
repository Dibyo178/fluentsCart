<?php
namespace App\Http\Controllers;

use FC\Shipping\Services\InertiaBridge;

class ShippingController {
    public function index() {
        global $wpdb;

        // ১. সব শিপিং মেথড ফেচ করা (ড্রপডাউনের জন্য)
        $shipping_methods = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}fct_shipping_methods");

        // ২. বর্তমান মোড/মেথড আইডি গেট করা
        $current_mode = get_option('fc_restriction_mode', 'global');


// ShippingController.php এর ভেতর এই অংশটুকু পরিবর্তন করুন
$allowed = [];
$excluded = [];

if ($current_mode === 'global') {
    // গ্লোবাল মোড হলে অপশন টেবিল থেকে ডেটা নিন
    $allowed = (array) get_option('fc_allowed_countries', []);
    $excluded = (array) get_option('fc_excluded_countries', []);
} else {
    // নির্দিষ্ট মেথড হলে কাস্টম টেবিল থেকে নিন
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT allowed_countries, excluded_countries FROM {$wpdb->prefix}fc_shipping_method_restrictions WHERE method_id = %s",
        $current_mode
    ));
    if ($row) {
        $allowed = json_decode($row->allowed_countries, true) ?: [];
        $excluded = json_decode($row->excluded_countries, true) ?: [];
    }
}

        // ৩. লগ ফরমেট করা
        $table_name = $wpdb->prefix . 'fct_order_meta';
        $raw_logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT order_id, meta_value, created_at FROM $table_name
                WHERE meta_key = %s
                ORDER BY created_at DESC LIMIT 100",
                '_fc_shipping_restrictions'
            )
        );

        $formatted_logs = [];
        foreach($raw_logs as $log) {
            $meta = json_decode($log->meta_value, true);
            $formatted_logs[] = [
                'id'       => $log->order_id,
                'country'  => $meta['order_country'] ?? 'N/A',
                'allowed'  => implode(', ', (array)($meta['allowed_countries'] ?? [])),
                'excluded' => implode(', ', (array)($meta['excluded_countries'] ?? [])),
                'status'   => str_replace('●', '', $meta['validation_status'] ?? 'N/A'),
                'date'     => $log->created_at
            ];
        }

        // ৪. ইনর্শিয়া ব্রিজ দিয়ে Vue পেজে ডেটা পাঠানো
        return InertiaBridge::render('Shipping/Restrictions', [
            'allowed'         => (array) $allowed,
            'excluded'        => (array) $excluded,
            'mode'            => $current_mode,
            'shippingMethods' => $shipping_methods,
            'logs'            => $formatted_logs,
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('fc_shipping_nonce')
        ]);
    }
}
