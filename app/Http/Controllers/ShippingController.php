<?php
namespace App\Http\Controllers;

use FC\Shipping\Services\InertiaBridge;
use App\Models\ShippingTask;
use App\Models\OrderMeta;
use Illuminate\Database\Capsule\Manager as Capsule;

class ShippingController {

    public function index() {
        // 1.. Shipping Methods
        $shipping_methods = Capsule::table('fct_shipping_methods')->select('id', 'title')->get()->toArray();

        // 2. Current Mode fetch
        $current_mode = get_option('fc_restriction_mode', 'global');
        $search_id = ($current_mode === 'global') ? 0 : (int)$current_mode;

        // ShippingTask Model use
        $restriction = ShippingTask::where('method_id', $search_id)->first();

        // 3. Order Logs (OrderMeta Model use)
        $raw_logs = OrderMeta::where('meta_key', '_fc_shipping_restrictions')
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->get();

        // Collection map data format
        $formatted_logs = $raw_logs->map(function($log) use ($shipping_methods) {
            $meta = $log->meta_value;
            $mode = $meta['mode'] ?? 'global';
            $method_name = 'Global';

            if ($mode !== 'global' && is_numeric($mode)) {
                //find array
                $method = array_filter($shipping_methods, fn($m) => $m->id == $mode);
                $method_name = !empty($method) ? reset($method)->title : 'Unknown Method';
            }

            return [
                'id'       => $log->order_id,
                'method'   => $method_name,
                'allowed'  => !empty($meta['allowed_countries']) ? implode(', ', (array)$meta['allowed_countries']) : 'All Countries',
                'excluded' => !empty($meta['excluded_countries']) ? implode(', ', (array)$meta['excluded_countries']) : 'None',
                'date'     => $log->created_at
            ];
        });

        return InertiaBridge::render('Shipping/Restrictions', [
            'allowed'         => $restriction ? $restriction->allowed_countries : [],
            'excluded'        => $restriction ? $restriction->excluded_countries : [],
            'mode'            => $current_mode,
            'shippingMethods' => $shipping_methods,
            'logs'            => $formatted_logs,
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('fc_shipping_nonce')
        ]);
    }
}
