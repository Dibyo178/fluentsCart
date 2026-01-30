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

        // Frontend hooks
        add_action('wp_footer', [$this, 'inject_checkout_logic'], 999);
        add_action('wp_footer', [$this, 'enqueue_frontend_debug'], 100);

        // 🔒 Backend enforcement hook (prevent bypass via API)
        add_action('fluent_cart/before_order_create', [$this, 'prevent_restricted_orders'], 10, 1);
    }

    // Server-side validation for checkout form
    public function secure_server_validation($errors, $data) {
        $country = strtoupper($data['billing_address']['country'] ?? '');
        $allowed = (array)get_option('fc_allowed_countries', []);
        $excluded = (array)get_option('fc_excluded_countries', []);

        $is_blocked = in_array($country, $excluded) || (!empty($allowed) && !in_array($country, $allowed));

        if ($is_blocked) {
            $errors['billing_address.country'] = '🚫 Shipping restricted for this location.';
        }
        return $errors;
    }

    // Filter shipping methods based on is_enabled
    public function filter_shipping_methods($methods, $cart) {
        global $wpdb;

        $normalize = fn($str) => strtolower(preg_replace('/[^a-z0-9]/', '', $str ?? ''));

        $active_methods = $wpdb->get_results(
            "SELECT type, title FROM {$wpdb->prefix}fct_shipping_methods WHERE is_enabled = 1",
            OBJECT_K
        );

        if (empty($active_methods)) return [];

        $filtered = [];
        foreach ($methods as $key => $method) {
            $type = $normalize($method['type'] ?? '');
            if (isset($active_methods[$type])) {
                $method['title'] = $active_methods[$type]->title;
                $filtered[$key] = $method;
            }
        }

        return $filtered;
    }

    // Frontend debug JS
    public function enqueue_frontend_debug() {
        if (!\function_exists('is_checkout') || !\is_checkout()) return;

        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const interval = setInterval(() => {
                if (typeof fct_checkout !== 'undefined' && Array.isArray(fct_checkout.shipping_methods)) {
                    clearInterval(interval);
                    console.log("🟢 Active Shipping Methods from FluentCart:", fct_checkout.shipping_methods);
                    if (fct_checkout.shipping_methods.length > 0) {
                        alert("Active Shipping Method: " + JSON.stringify(fct_checkout.shipping_methods[0]));
                    }
                }
            }, 500);
        });
        </script>
        <?php
    }

    // Checkout country restriction logic
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
                const messageId = 'fc-restriction-msg';

                if (!countryEl || !btn) return;

                const country = countryEl.value.toUpperCase();
                let isExcluded = rules.excluded.includes(country);
                let isNotAllowed = rules.allowed.length > 0 && !rules.allowed.includes(country);
                let isBlocked = isExcluded || isNotAllowed;
                let msgDiv = document.getElementById(messageId);

                if (isBlocked) {
                    if (!msgDiv) {
                        msgDiv = document.createElement('div');
                        msgDiv.id = messageId;
                        btn.parentNode.insertBefore(msgDiv, btn);
                    }

                    let displayMsg = isExcluded
                        ? `🚫 We do not ship to ${country}. This country is excluded.`
                        : `⚠️ This country (${country}) is not allowed for shipping.`;

                    msgDiv.innerHTML = `<div style="background:#000; color:#fff; padding:15px; border-radius:10px; margin-bottom:15px; text-align:center; font-weight:bold; border: 2px solid #ef4444;">${displayMsg}</div>`;

                    btn.disabled = true;
                    btn.style.opacity = '0.4';
                    btn.style.pointerEvents = 'none';
                } else {
                    if (msgDiv) msgDiv.remove();
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
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

    // Handle AJAX save
    public function handle_ajax_save() {
        global $wpdb;
        check_ajax_referer('fc_shipping_nonce', 'nonce');

        $mode = sanitize_text_field($_POST['mode']); // 'global' or specific ID
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
            $wpdb->update($table, ['is_enabled' => 1], ['id' => $method_id], ['%d'], ['%d']);
        }

        wp_send_json_success(['message' => 'Shipping settings saved successfully.']);
    }

    // 🔒 Backend order restriction to prevent API bypass
    public function prevent_restricted_orders($orderData) {
        $country = strtoupper($orderData['billing_address']['country'] ?? '');
        $allowed = (array)get_option('fc_allowed_countries', []);
        $excluded = (array)get_option('fc_excluded_countries', []);

        $blocked = in_array($country, $excluded) || (!empty($allowed) && !in_array($country, $allowed));

        if ($blocked) {
            wp_die('Shipping restricted for this country.', 'Order Blocked', ['response' => 403]);
        }
    }

}

new Plugin();
