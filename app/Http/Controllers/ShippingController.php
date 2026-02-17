<?php
namespace App\Http\Controllers;

use FC\Shipping\Services\InertiaBridge;
use App\Models\ShippingTask; // Model import
use Illuminate\Database\Capsule\Manager as Capsule; // FluentCart core table-er jonno logic

class ShippingController {

    public function index() {
        global $wpdb;

        // 1. Fetch all shipping methods (FluentCart core table)
        $shipping_methods = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}fct_shipping_methods");

        // 2. Get current mode/method ID
        $current_mode = get_option('fc_restriction_mode', 'global');
        $search_id = ($current_mode === 'global') ? 0 : (int)$current_mode;

        /**
         * Logic: Eloquent Model (ShippingTask) use kore data fetch
         * Array casting automatic hobe model settings er karone
         */
        $restriction = ShippingTask::where('method_id', $search_id)->first();

        $allowed = $restriction ? $restriction->allowed_countries : [];
        $excluded = $restriction ? $restriction->excluded_countries : [];

        // 3. Log data fetch using $wpdb (Jehetu OrderMeta-r Eloquent model nei)
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
        $methods_table = "{$wpdb->prefix}fct_shipping_methods";

        foreach($raw_logs as $log) {
            $meta = json_decode($log->meta_value, true);
            $mode = $meta['mode'] ?? 'global';
            $method_name = 'Global';

            if ($mode !== 'global' && is_numeric($mode)) {
                $method_name = $wpdb->get_var($wpdb->prepare(
                    "SELECT title FROM $methods_table WHERE id = %d",
                    (int)$mode
                )) ?: 'Unknown Method';
            }

            $formatted_logs[] = [
                'id'       => $log->order_id,
                'method'   => $method_name,
                'allowed'  => !empty($meta['allowed_countries']) ? implode(', ', (array)$meta['allowed_countries']) : 'All Countries',
                'excluded' => !empty($meta['excluded_countries']) ? implode(', ', (array)$meta['excluded_countries']) : 'None',
                'date'     => $log->created_at
            ];
        }

        // Render via Inertia
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
