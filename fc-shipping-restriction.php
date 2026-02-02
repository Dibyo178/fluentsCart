<?php

/**
 * Plugin Name: FluentCart Shipping Restriction
 * Description: Restrict shipping by country/method with professional Vue.js UI and high-accuracy Excel reporting.
 * Version: 1.1.0
 * Author: Sourov Purkayastha
 */

namespace FC\Shipping;

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'app/Services/InertiaBridge.php';
require_once plugin_dir_path(__FILE__) . 'app/Http/Controllers/ShippingController.php';

use App\Http\Controllers\ShippingController;

class Plugin
{

  private $is_dev_mode = true;
    public function __construct()
    {
         // actions  Hooks
        add_action('admin_menu', [$this, 'add_menu'], 100);
        add_action('admin_enqueue_scripts', [$this, 'load_assets']);
        add_action('wp_ajax_fc_save_shipping_settings', [$this, 'handle_ajax_save']);
        add_action('wp_ajax_fc_get_method_settings', [$this, 'handle_ajax_get_settings']);

        // filters Hooks
        add_filter('fluent_cart/validate_checkout_data', [$this, 'secure_server_validation'], 10, 2);
        add_filter('fluent_cart/shipping/available_methods', [$this, 'filter_shipping_methods'], 999, 2);
        add_action('fluent_cart/order_created', [$this, 'log_order_restriction'], 20, 1);
        add_action('wp_footer', [$this, 'inject_checkout_logic'], 999);
    }

    private function get_active_rules() {
        global $wpdb;
        $mode = get_option('fc_restriction_mode', 'global');

        if ($mode === 'global' || empty($mode)) {
            $method_id = 0;
        } else {
            $method_id = (int)$mode;
        }

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

    public function load_assets($hook)
    {
        if (strpos($hook, 'fc-shipping-restrictions') === false) {
            return;
        }

        $plugin_dir_url = plugin_dir_url(__FILE__);
        $dist_path      = plugin_dir_path(__FILE__) . 'dist/';

        if ($this->is_dev_mode) {
            // Local Vite dev server (exposes ngrok or localhost:5173)
            wp_enqueue_script('fc-js', 'http://localhost:5173/resources/js/app.js', [], null, true);
            // Or ngrok URL: wp_enqueue_script('fc-js', 'https://abc123.ngrok.io/resources/js/app.js', [], null, true);
        } else {
            // Production - Vite build
            $manifest_path = $dist_path . '.vite/manifest.json';

            if (file_exists($manifest_path)) {
                $manifest = json_decode(file_get_contents($manifest_path), true);

                if (isset($manifest['resources/js/app.js'])) {
                    $entry = $manifest['resources/js/app.js'];

                    // JS enqueue
                    wp_enqueue_script(
                        'fc-js',
                        $plugin_dir_url . 'dist/' . $entry['file'],
                        [], // dependencies
                        null,
                        true
                    );

                    // CSS enqueue (Vite auto generates)
                    if (!empty($entry['css'])) {
                        foreach ($entry['css'] as $css_file) {
                            wp_enqueue_style(
                                'fc-css-' . md5($css_file),
                                $plugin_dir_url . 'dist/' . $css_file,
                                [],
                                null
                            );
                        }
                    }
                }
            } else {
                //fallback - if manifest is not found
                wp_enqueue_script('fc-js', $plugin_dir_url . 'dist/assets/app.js', [], '1.0', true);
            }
        }

        // Adding module type (required for Vite)
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle === 'fc-js') {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);

        // wp_localize_script if nonce / other data is needed for Inertia/Vue
        wp_localize_script('fc-js', 'fcShippingData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('fc_shipping_nonce'),
        ]);
    }
public function handle_ajax_save() {
    global $wpdb;
    check_ajax_referer('fc_shipping_nonce', 'nonce');

    $mode = sanitize_text_field($_POST['mode'] ?? 'global');
    $allowed = json_decode(stripslashes($_POST['allowed'] ?? '[]'), true);
    $excluded = json_decode(stripslashes($_POST['excluded'] ?? '[]'), true);

    $method_id = ($mode === 'global') ? 0 : (int)$mode;
    $table = $wpdb->prefix . 'fc_shipping_method_restrictions';

    // Save restrictions
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE method_id = %d", $method_id));
    $data = [
        'method_id'         => $method_id,
        'allowed_countries' => wp_json_encode($allowed),
        'excluded_countries'=> wp_json_encode($excluded),
        'updated_at'        => current_time('mysql')
    ];

    if ($exists) {
        $wpdb->update($table, $data, ['id' => $exists]);
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table, $data);
    }

    // Method table update (is_enabled logic)
    $methods_table = $wpdb->prefix . 'fct_shipping_methods';
    if ($mode === 'global') {
        $wpdb->query("UPDATE $methods_table SET is_enabled = 1");
    } else {
        $wpdb->query("UPDATE $methods_table SET is_enabled = 0"); // সব বন্ধ
        $wpdb->update($methods_table, ['is_enabled' => 1], ['id' => (int)$mode]); // শুধু নির্দিষ্টটি চালু
    }

    update_option('fc_restriction_mode', $mode);
    wp_send_json_success(['mode' => $mode]);
}

public function handle_ajax_get_settings() {
    global $wpdb;
    $mode = sanitize_text_field($_GET['mode'] ?? 'global');
    $search_id = ($mode === 'global') ? 0 : (int)$mode;

    $table = $wpdb->prefix . 'fc_shipping_method_restrictions';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT allowed_countries, excluded_countries FROM $table WHERE method_id = %d",
        $search_id
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

        function runValidation() {
            const countryEl = document.querySelector('select[name="billing_country"], select[name="shipping_country"]');
            const btn = document.querySelector('button[type="submit"], #place_order, .fct-checkout-submit');

            if (!countryEl || !btn) return;

            const country = countryEl.value ? countryEl.value.toUpperCase().trim() : '';
            if(!country) return;

            const isExcluded = rules.excluded.includes(country);
            const isNotAllowed = rules.allowed.length > 0 && !rules.allowed.includes(country);
            const blocked = isExcluded || isNotAllowed;

            let msgDiv = document.getElementById('fc-restriction-msg');

            if (blocked) {
                if (!msgDiv) {
                    msgDiv = document.createElement('div');
                    msgDiv.id = 'fc-restriction-msg';
                    msgDiv.style.cssText = 'background:#fff5f5;color:#e53e3e;padding:15px;border-radius:8px;margin:15px 0;text-align:center;font-weight:bold;border:1px solid #feb2b2;';
                    btn.before(msgDiv);
                }
                msgDiv.innerHTML = isExcluded ? `🚫 We do not ship to ${country}.` : `⚠️ Shipping to ${country} is not allowed.`;

                // button lock
                btn.disabled = true;
                btn.style.setProperty('opacity', '0.5', 'important');
                btn.style.setProperty('pointer-events', 'none', 'important');
            } else {
                if (msgDiv) msgDiv.remove();
                // button unlock
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
            }
        }

    
        document.addEventListener('change', (e) => {
            if (e.target.name === 'billing_country' || e.target.name === 'shipping_country') runValidation();
        });

        // For dynamic loading scenarios
        setInterval(runValidation, 1000);
    })();
    </script>
    <?php
}

public function log_order_restriction($data) {
    global $wpdb;

    // Early return if data is invalid
    if (!is_array($data) || empty($data['order'])) {
        error_log('FC Restriction Error: Invalid data structure passed to order_created hook. Data: ' . print_r($data, true));
        return;
    }

    // Extract order ID from nested structure
    $order_id = $data['order']['id'] ?? null;

    if (!$order_id) {
        error_log('FC Restriction Error: Order ID not found in data. Data: ' . print_r($data, true));
        return;
    }

    $rules = $this->get_active_rules();

    // Extract country from nested billing_address
    $country = '';
    if (isset($data['order']['billing_address']) && is_array($data['order']['billing_address'])) {
        $country = strtoupper(trim($data['order']['billing_address']['country'] ?? ''));
    } elseif (isset($data['customer']['country'])) {  // Fallback to customer data if available
        $country = strtoupper(trim($data['customer']['country'] ?? ''));
    }

    if (empty($country)) {
        error_log('[FC Restriction] Could not extract country for order ' . $order_id . '. Data: ' . print_r($data, true));
        $country = 'Unknown';
    }

    // Extract shipping method title (adjust key if different in FluentCart order structure)
    $shipping_method = $data['order']['shipping_method_title'] ?? 'N/A';

    // Validation logic
    $is_allowed = empty($rules['allowed']) || in_array($country, $rules['allowed']);
    $is_excluded = in_array($country, $rules['excluded']);

    $status = 'Passed';
    if ($is_excluded || !$is_allowed) {
        $status = 'Blocked';
    }

    // Meta value (matches controller keys)
    $meta_value = [
        'order_country'      => $country,
        'allowed_countries'  => $rules['allowed'],
        'excluded_countries' => $rules['excluded'],
        'validation_status'  => $status,
        'mode'               => $rules['mode'] ?? 'global',  // For extra debugging
        'shipping_method'    => $shipping_method  // New: Store shipping method for display in logs
    ];

    // Debugging: Log what will be inserted
    error_log('FC Restriction Meta for Order #' . $order_id . ': ' . wp_json_encode($meta_value, JSON_PRETTY_PRINT));

    // Insert into database
    $inserted = $wpdb->insert($wpdb->prefix . 'fct_order_meta', [
        'order_id'   => $order_id,
        'meta_key'   => '_fc_shipping_restrictions',
        'meta_value' => wp_json_encode($meta_value),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);

    if ($inserted === false) {
        error_log('FC DB Insert Error: ' . $wpdb->last_error . ' | Last Query: ' . $wpdb->last_query);
    } else {
        error_log('FC Restriction Log Inserted Successfully for Order #' . $order_id);
    }
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
