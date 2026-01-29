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
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'load_assets']);
        add_action('wp_ajax_fc_save_shipping_settings', [$this, 'handle_ajax_save']);

        // ১. সার্ভার সাইড সিকিউরিটি (Bypass Protection)
        add_filter('fluent_cart/validate_checkout_data', [$this, 'secure_server_validation'], 10, 2);

        // ২. শিপিং মেথড ফিল্টারিং (ডকুমেন্টেশন অনুযায়ী টাইপ এবং টাইটেল ম্যাচিং)
        add_filter('fluent_cart/shipping/available_methods', [$this, 'filter_shipping_methods'], 999, 2);

        // ৩. ফ্রন্টএন্ড UI কন্ট্রোল ও আলাদা মেসেজ লজিক
        add_action('wp_footer', [$this, 'inject_checkout_logic']);
    }

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

public function filter_shipping_methods($methods, $cart) {
    // আপনার সেভ করা ভ্যালু (যেমন: Free বা Standered)
    $mode = get_option('fc_restriction_mode', 'global');
    
    if ($mode === 'global' || empty($mode)) return $methods;

    // Map user-selected modes to FluentCart internal types for better matching
    $mode_map = [
        'free' => 'free_shipping',
        'standard' => 'flat_rate',
        'standered' => 'flat_rate', // Handle potential typo
        'local' => 'local_pickup',
        // Add more mappings if needed, e.g., 'express' => 'custom_express_type'
    ];
    $search_term = strtolower(trim($mode));
    $search_term = $mode_map[$search_term] ?? $search_term;

    $filtered_methods = [];

    // Log for debugging (view in wp-content/debug.log; enable WP_DEBUG_LOG in wp-config.php if needed)
    error_log('FC Shipping Filter: Mode = ' . $mode . ', Search Term = ' . $search_term);
    error_log('FC Shipping Filter: Available Methods = ' . json_encode($methods));

    foreach ($methods as $key => $method) {
        // ডাটাবেস টাইটেল এবং ইন্টারনাল টাইপ চেক করা হচ্ছে
        $m_title = strtolower(trim($method['title'] ?? ''));
        $m_type = strtolower(trim($method['type'] ?? '')); // ডকুমেন্টেশন অনুযায়ী flat_rate, free_shipping ইত্যাদি
        $m_id = (string)($method['id'] ?? $key);

        // ম্যাচিং লজিক: Partial match for flexibility (handles spaces/variations) or exact ID
        if (
            stripos($m_title, $search_term) !== false ||
            stripos($m_type, $search_term) !== false ||
            $m_id === $search_term
        ) {
            $filtered_methods[$key] = $method;
        }
    }

    // যদি ফিল্টার করা লিস্ট ফাঁকা না থাকে তবে সেটিই দেখাবে, অন্যথায় সব (ডেফল্ট, কিন্তু আপনি চাইলে empty array রিটার্ন করতে পারেন যাতে কোনো মেথড না দেখায়)
    return !empty($filtered_methods) ? $filtered_methods : $methods;
}

    public function inject_checkout_logic() {
        $allowed = get_option('fc_allowed_countries', []);
        $excluded = get_option('fc_excluded_countries', []);
        ?>
        <script>
        (function() {
            console.log("FC Shipping Logic Loaded Successfully");

            const rules = {
                allowed: <?php echo json_encode($allowed); ?>,
                excluded: <?php echo json_encode($excluded); ?>
            };

            function runValidation() {
                const countryEl = document.querySelector('select[name*="country"], .fc_country_select, #billing_country');
                const btn = document.querySelector('.fct-checkout-submit, #place_order, .fc_place_order, button[type="submit"]');
                const messageId = 'fc-restriction-msg';

                if (!countryEl || !btn) return;

                const country = countryEl.value.toUpperCase();
                
                // আলাদা মেসেজ লজিক
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

                    let displayMsg = "";
                    if (isExcluded) {
                        displayMsg = `🚫 We do not ship to ${country}. This country is excluded.`;
                    } else if (isNotAllowed) {
                        displayMsg = `⚠️ This country (${country}) is not allowed for shipping.`;
                    }

                    msgDiv.innerHTML = `
                        <div style="background:#000; color:#fff; padding:15px; border-radius:10px; margin-bottom:15px; text-align:center; font-weight:bold; border: 2px solid #ef4444;">
                            ${displayMsg}
                        </div>`;

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

    public function add_menu() {
        add_menu_page('Shipping Rules', 'FC Shipping', 'manage_options', 'fc-shipping-restrictions', [$this, 'render_page'], 'dashicons-admin-site', 56);
    }

    public function render_page() {
        $controller = new ShippingController();
        echo $controller->index();
    }

    public function load_assets($hook) {
        if (strpos($hook, 'fc-shipping-restrictions') === false) return;
        wp_enqueue_script('fc-js', 'http://localhost:5173/resources/js/app.js', [], null, true);
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'fc-js') return str_replace('<script ', '<script type="module" ', $tag);
            return $tag;
        }, 10, 2);
    }

    public function handle_ajax_save() {
        check_ajax_referer('fc_shipping_nonce', 'nonce');
        update_option('fc_allowed_countries', json_decode(stripslashes($_POST['allowed']), true));
        update_option('fc_excluded_countries', json_decode(stripslashes($_POST['excluded']), true));
        update_option('fc_restriction_mode', sanitize_text_field($_POST['mode']));
        wp_send_json_success();
    }
}
new Plugin();