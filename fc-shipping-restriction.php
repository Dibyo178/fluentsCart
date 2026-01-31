<?php
/**
 * Plugin Name: FluentCart Shipping Restriction
 */

namespace FC\Shipping;

if (!defined('ABSPATH')) exit;

$base_path = plugin_dir_path(__FILE__);
require_once $base_path . 'app/Services/InertiaBridge.php';
require_once $base_path . 'app/Http/Controllers/ShippingController.php';

use App\Http\Controllers\ShippingController;

class Plugin {

    public function __construct() {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'load_assets']);
        add_action('wp_ajax_fc_save_shipping_settings', [$this, 'handle_ajax_save']);

        // FluentCart hooks
        add_filter('fluent_cart/validate_checkout_data', [$this, 'secure_server_validation'], 10, 2);
        add_filter('fluent_cart/shipping/available_methods', [$this, 'filter_shipping_methods'], 999, 2);
        add_action('fluent_cart/before_order_create', [$this, 'prevent_restricted_orders'], 10, 1);
        add_action('fluent_cart/order_created', [$this, 'log_order_restriction'], 20, 1);

        // Frontend hooks
        add_action('wp_footer', [$this, 'inject_checkout_logic'], 999);
        add_action('wp_footer', [$this, 'enqueue_frontend_debug'], 100);
    }

    // Server-side validation for checkout
    public function secure_server_validation($errors, $data) {
        $country = strtoupper(trim($data['billing_address']['country'] ?? ''));
        $allowed = (array)get_option('fc_allowed_countries', []);
        $excluded = (array)get_option('fc_excluded_countries', []);

        $is_blocked = in_array($country, $excluded) || (!empty($allowed) && !in_array($country, $allowed));

        if ($is_blocked) {
            $errors['billing_address.country'] = '🚫 Shipping restricted for this location.';
        }

        return $errors;
    }

<<<<<<< Updated upstream
    // Filter shipping methods
    public function filter_shipping_methods($methods, $cart) {
        global $wpdb;

        $normalize = fn($str) => strtolower(preg_replace('/[^a-z0-9]/', '', $str ?? ''));
=======
public function filter_shipping_methods($methods, $cart) {
    $mode = get_option('fc_restriction_mode', 'global');
    
    if ($mode === 'global' || empty($mode)) return $methods;

    // Map based on FluentCart docs: user-selected mode to internal type slugs
    $mode_map = [
        'free' => 'free_shipping',
        'standard' => 'flat_rate',
        'standered' => 'flat_rate', // Handle typo in selection or title
        'local' => 'local_pickup',
        // Add more if your admin has other options, e.g., 'express' => 'express_shipping'
    ];
    $search_term = strtolower(trim($mode));
    $search_term = $mode_map[$search_term] ?? $search_term;
>>>>>>> Stashed changes

        $active_methods = $wpdb->get_results(
            "SELECT type, title FROM {$wpdb->prefix}fct_shipping_methods WHERE is_enabled = 1",
            OBJECT_K
        );

<<<<<<< Updated upstream
        if (empty($active_methods)) return [];

        $filtered = [];
        foreach ($methods as $key => $method) {
            $type = $normalize($method['type'] ?? '');
            if (isset($active_methods[$type])) {
                $method['title'] = $active_methods[$type]->title;
                $filtered[$key] = $method;
            }
=======
    // Enhanced logging for debugging (enable WP_DEBUG_LOG in wp-config.php)
    error_log('FC Shipping Filter: Mode = ' . $mode . ', Search Term = ' . $search_term);
    error_log('FC Shipping Filter: Cart Data = ' . json_encode($cart));
    error_log('FC Shipping Filter: Available Methods = ' . json_encode($methods, JSON_PRETTY_PRINT));

    foreach ($methods as $key => $method) {
        $m_title = strtolower(trim($method['title'] ?? ''));
        $m_type = strtolower(trim($method['type'] ?? ''));
        $m_id = (string)($method['id'] ?? $key);

        // Matching logic: Partial match OR fuzzy (levenshtein <= 2 for typos like 'standered' vs 'standard') OR exact ID
        $title_distance = levenshtein($m_title, $search_term);
        $type_distance = levenshtein($m_type, $search_term);
        if (
            stripos($m_title, $search_term) !== false ||
            stripos($m_type, $search_term) !== false ||
            $m_id === $search_term ||
            $title_distance <= 2 ||
            $type_distance <= 2
        ) {
            $filtered_methods[$key] = $method;
            error_log('FC Shipping Filter: Matched Method = ' . json_encode($method));
        } else {
            error_log('FC Shipping Filter: Non-Matched Method = ' . json_encode($method));
>>>>>>> Stashed changes
        }
        return $filtered;
    }

<<<<<<< Updated upstream
    // Frontend debug
    public function enqueue_frontend_debug() {
        if (!function_exists('is_checkout') || !is_checkout()) return;
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const interval = setInterval(() => {
                if (typeof fct_checkout !== 'undefined' && Array.isArray(fct_checkout.shipping_methods)) {
                    clearInterval(interval);
                    console.log("🟢 Active Shipping Methods:", fct_checkout.shipping_methods);
                }
            }, 500);
        });
        </script>
        <?php
    }
=======
    // Strict restriction: Hide all if no matches (change to : $methods if you want fallback)
    $result = !empty($filtered_methods) ? $filtered_methods : [];
    error_log('FC Shipping Filter: Final Filtered Methods = ' . json_encode($result));
    return $result;
}
>>>>>>> Stashed changes

    // Frontend JS checkout restriction
    public function inject_checkout_logic() {
        $allowed = (array)get_option('fc_allowed_countries', []);
        $excluded = (array)get_option('fc_excluded_countries', []);
        ?>
        <script>
        (function() {
            const rules = { allowed: <?php echo json_encode($allowed); ?>, excluded: <?php echo json_encode($excluded); ?> };
            function runValidation() {
                const countryEl = document.querySelector('select[name*="country"], .fc_country_select, #billing_country');
                const btn = document.querySelector('.fct-checkout-submit, #place_order, .fc_place_order, button[type="submit"]');
                if (!countryEl || !btn) return;

                const messageId = 'fc-restriction-msg';
                const country = countryEl.value.toUpperCase();
                let isBlocked = rules.excluded.includes(country) || (rules.allowed.length && !rules.allowed.includes(country));
                let msgDiv = document.getElementById(messageId);

                if (isBlocked) {
                    if (!msgDiv) {
                        msgDiv = document.createElement('div');
                        msgDiv.id = messageId;
                        btn.parentNode.insertBefore(msgDiv, btn);
                    }
                    let displayMsg = rules.excluded.includes(country)
                        ? `🚫 We do not ship to ${country}. This country is excluded.`
                        : `⚠️ This country (${country}) is not allowed for shipping.`;
                    msgDiv.innerHTML = `<div style="background:#000;color:#fff;padding:15px;border-radius:10px;margin-bottom:15px;text-align:center;font-weight:bold;border:2px solid #ef4444;">${displayMsg}</div>`;
                    btn.disabled = true; btn.style.opacity='0.4'; btn.style.pointerEvents='none';
                } else {
                    if (msgDiv) msgDiv.remove();
                    btn.disabled = false; btn.style.opacity='1'; btn.style.pointerEvents='auto';
                }
            }
            document.addEventListener('change', runValidation);
            setInterval(runValidation, 1500);
        })();
        </script>
        <?php
    }

    // Admin menu
    public function add_menu() {
        add_menu_page('Shipping Rules', 'FC Shipping', 'manage_options', 'fc-shipping-restrictions', [$this, 'render_page'], 'dashicons-admin-site', 56);
    }

    public function render_page() {
        $controller = new ShippingController();
        echo $controller->index();
    }

    // Load admin assets
    public function load_assets($hook) {
        if (strpos($hook, 'fc-shipping-restrictions') === false) return;
        wp_enqueue_script('fc-js', 'http://localhost:5173/resources/js/app.js', [], null, true);
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'fc-js') return str_replace('<script ', '<script type="module" ', $tag);
            return $tag;
        }, 10, 2);
    }

    // AJAX save
    public function handle_ajax_save() {
        global $wpdb;
        check_ajax_referer('fc_shipping_nonce', 'nonce');

        $mode = sanitize_text_field($_POST['mode']);
        $allowed = json_decode(stripslashes($_POST['allowed']), true);
        $excluded = json_decode(stripslashes($_POST['excluded']), true);

        update_option('fc_allowed_countries', $allowed);
        update_option('fc_excluded_countries', $excluded);
        update_option('fc_restriction_mode', $mode);

        $table = $wpdb->prefix . 'fct_shipping_methods';

        if ($mode === 'global') {
            $wpdb->query("UPDATE {$table} SET is_enabled = 1");
        } else {
            $method_id = intval($mode);
            $wpdb->query("UPDATE {$table} SET is_enabled = 0");
            $wpdb->update($table, ['is_enabled'=>1], ['id'=>$method_id], ['%d'], ['%d']);
        }

        wp_send_json_success(['message' => 'Shipping settings saved successfully.']);
    }

    // Backend: prevent order creation for restricted countries
    public function prevent_restricted_orders($orderData) {
        $order = $orderData['order'] ?? null;
        if (!$order) return;

        $country = strtoupper(trim($order->billing_address['country'] ?? ''));
        $allowed = (array)get_option('fc_allowed_countries', []);
        $excluded = (array)get_option('fc_excluded_countries', []);

        $blocked = in_array($country, $excluded) || (!empty($allowed) && !in_array($country, $allowed));

        if ($blocked) {
            wp_die('🚫 Shipping restricted for this country.', 'Order Blocked', ['response'=>403]);
        }
    }

    // Backend: log order restrictions
    public function log_order_restriction($data) {
        global $wpdb;
        $order = $data['order'] ?? null;
        if (!$order) return;

        $order_id = intval($order->id);
        $country = strtoupper(trim($order->billing_address['country'] ?? ''));
        $allowed = (array)get_option('fc_allowed_countries', []);
        $excluded = (array)get_option('fc_excluded_countries', []);
        $mode = get_option('fc_restriction_mode', '');

        $status = 'Passed';
        if (in_array($country, $excluded)) $status='Flagged: Excluded';
        elseif (!empty($allowed) && !in_array($country, $allowed)) $status='Flagged: Unauthorized';

        $wpdb->insert($wpdb->prefix.'fct_order_meta', [
            'order_id'=>$order_id,
            'meta_key'=>'_fc_shipping_restrictions',
            'meta_value'=>json_encode([
                'order_country'=>$country,
                'validation_status'=>$status,
                'applied_method_name'=>is_numeric($mode)?'Method ID: '.$mode: strtoupper($mode),
                'allowed_countries'=>$allowed,
                'excluded_countries'=>$excluded
            ]),
            'created_at'=>current_time('mysql'),
            'updated_at'=>current_time('mysql')
        ]);
    }
}

new Plugin();
