<?php

/**
 * Plugin Name: FluentCart Shipping Restriction
 */

namespace FC\Shipping;

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'app/Services/InertiaBridge.php';
require_once plugin_dir_path(__FILE__) . 'app/Http/Controllers/ShippingController.php';

use App\Http\Controllers\ShippingController;

class Plugin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu'], 100);
        add_action('admin_enqueue_scripts', [$this, 'load_assets']);
        add_action('wp_ajax_fc_save_shipping_settings', [$this, 'handle_ajax_save']);
        add_action('wp_ajax_fc_get_method_settings', [$this, 'handle_ajax_get_settings']);

        // Hooks
        add_filter('fluent_cart/validate_checkout_data', [$this, 'secure_server_validation'], 10, 2);
        add_filter('fluent_cart/shipping/available_methods', [$this, 'filter_shipping_methods'], 999, 2);
        add_action('fluent_cart/order_created', [$this, 'log_order_restriction'], 20, 1);
        add_action('wp_footer', [$this, 'inject_checkout_logic'], 999);
    }

    private function get_active_rules() {
        global $wpdb;
        $mode = get_option('fc_restriction_mode', 'global');

        if ($mode === 'global' || empty($mode)) {
            return [
                'mode'     => 'global',
                'allowed'  => (array) get_option('fc_allowed_countries', []),
                'excluded' => (array) get_option('fc_excluded_countries', [])
            ];
        }

        $method_id = is_numeric($mode) ? (int)$mode : 0;
        $table = $wpdb->prefix . 'fc_shipping_method_restrictions';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT allowed_countries, excluded_countries
             FROM $table
             WHERE method_id = %d",
            $method_id
        ));

        $allowed  = $row ? json_decode($row->allowed_countries, true) ?: [] : [];
        $excluded = $row ? json_decode($row->excluded_countries, true) ?: [] : [];

        return [
            'mode'     => $mode,
            'allowed'  => $allowed,
            'excluded' => $excluded
        ];
    }

    public function add_menu() {
        add_submenu_page(
            'fluent-cart',
            'Shipping Rules',
            'FC Shipping',
            'manage_options',
            'fc-shipping-restrictions',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        $controller = new ShippingController();
        echo $controller->index();
    }

    public function load_assets($hook) {
        if (strpos($hook, 'fc-shipping-restrictions') === false) return;

        wp_enqueue_script('fc-js', 'http://localhost:5173/resources/js/app.js', [], null, true);

        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle === 'fc-js') return str_replace('<script ', '<script type="module" ', $tag);
            return $tag;
        }, 10, 2);
    }

    public function handle_ajax_save() {
        global $wpdb;
        check_ajax_referer('fc_shipping_nonce', 'nonce');

        $mode     = sanitize_text_field($_POST['mode'] ?? '');
        $allowed  = json_decode(stripslashes($_POST['allowed'] ?? '[]'), true);
        $excluded = json_decode(stripslashes($_POST['excluded'] ?? '[]'), true);

        $allowed  = is_array($allowed) ? array_values(array_unique($allowed)) : [];
        $excluded = is_array($excluded) ? array_values(array_unique($excluded)) : [];

        if ($mode === 'global') {
            update_option('fc_restriction_mode', 'global');
            update_option('fc_allowed_countries', $allowed);
            update_option('fc_excluded_countries', $excluded);
            wp_send_json_success(['mode' => 'global']);
            return;
        }

        $method_id = (int) $mode;
        $table = $wpdb->prefix . 'fc_shipping_method_restrictions';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE method_id=%d", $method_id));

        $data = [
            'method_id' => $method_id,
            'allowed_countries' => wp_json_encode($allowed),
            'excluded_countries'=> wp_json_encode($excluded),
            'updated_at' => current_time('mysql')
        ];

        if ($exists) {
            $wpdb->update($table, $data, ['id' => $exists]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }

        update_option('fc_restriction_mode', $method_id);
        wp_send_json_success(['mode' => $method_id]);
    }

    public function handle_ajax_get_settings() {
        global $wpdb;
        $mode = sanitize_text_field($_GET['mode'] ?? '');

        if ($mode === 'global') {
            wp_send_json_success([
                'data' => [
                    'allowed'  => (array) get_option('fc_allowed_countries', []),
                    'excluded' => (array) get_option('fc_excluded_countries', [])
                ]
            ]);
            return;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT allowed_countries, excluded_countries
             FROM {$wpdb->prefix}fc_shipping_method_restrictions
             WHERE method_id = %d",
            (int) $mode
        ), ARRAY_A);

        wp_send_json_success([
            'data' => [
                'allowed'  => $row ? json_decode($row['allowed_countries'], true) : [],
                'excluded' => $row ? json_decode($row['excluded_countries'], true) : []
            ]
        ]);
    }

    public function inject_checkout_logic() {
        $rules = $this->get_active_rules();
        ?>
        <script>
        (function () {
            const rules = <?php echo json_encode($rules); ?>;
            function getButton() {
                return document.querySelector('button[type="submit"], #place_order, .fct-checkout-submit');
            }

            function runValidation() {
                const countryEl = document.querySelector('select[name="billing_country"]');
                const btn = getButton();
                if (!countryEl || !btn || !countryEl.value) return;

                const country = countryEl.value.toUpperCase().trim();
                const blocked = rules.excluded.includes(country) || (rules.allowed.length > 0 && !rules.allowed.includes(country));

                let msgDiv = document.getElementById('fc-restriction-msg');

                if (blocked) {
                    if (!msgDiv) {
                        msgDiv = document.createElement('div');
                        msgDiv.id = 'fc-restriction-msg';
                        msgDiv.style.cssText = 'background:#111;color:#ff4d4d;padding:14px;border-radius:6px;margin:12px 0;text-align:center;font-weight:600;';
                        btn.before(msgDiv);
                    }
                    msgDiv.innerHTML = rules.excluded.includes(country) 
                        ? `🚫 We do not ship to ${country}.` 
                        : `⚠️ Shipping to ${country} is not allowed.`;

                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.pointerEvents = 'none';
                } else {
                    if (msgDiv) msgDiv.remove();
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                }
            }

            setInterval(runValidation, 800);
            document.addEventListener('change', runValidation);
        })();
        </script>
        <?php
    }

    public function log_order_restriction($data) {
        global $wpdb;
        if (!isset($data['order'])) return;
        $order = $data['order'];
        $rules = $this->get_active_rules();
        $country = strtoupper(trim($order->billing_address['country'] ?? ''));

        $status = 'Passed';
        if (in_array($country, $rules['excluded'])) $status = 'Flagged: Excluded';
        elseif (!empty($rules['allowed']) && !in_array($country, $rules['allowed'])) $status = 'Flagged: Unauthorized';

        $wpdb->insert($wpdb->prefix . 'fct_order_meta', [
            'order_id'   => $order->id,
            'meta_key'   => '_fc_shipping_restrictions',
            'meta_value' => json_encode([
                'order_country' => $country,
                'validation_status' => $status,
                'mode' => $rules['mode']
            ]),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
    }

    public function filter_shipping_methods($methods, $cart) {
        global $wpdb;
        $active_methods = $wpdb->get_col("SELECT type FROM {$wpdb->prefix}fct_shipping_methods WHERE is_enabled=1");
        return array_filter($methods, fn($m) => in_array(strtolower($m['type']), array_map('strtolower', $active_methods)));
    }

    public function secure_server_validation($errors, $data) {
        $country = strtoupper(trim($data['billing_address']['country'] ?? ''));
        $rules = $this->get_active_rules();
        $is_blocked = in_array($country, $rules['excluded']) || (!empty($rules['allowed']) && !in_array($country, $rules['allowed']));

        if ($is_blocked) {
            $errors['billing_address.country'] = '🚫 Shipping restricted for this location.';
        }
        return $errors;
    }
}

new Plugin();