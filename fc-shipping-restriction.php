<?php
/**
 * Plugin Name: FluentCart Shipping Restriction
 */

namespace FC\Shipping;

// ফাইলগুলো লোড করা হচ্ছে
$base_path = plugin_dir_path(__FILE__);
if (file_exists($base_path . 'app/Services/InertiaBridge.php')) {
    require_once $base_path . 'app/Services/InertiaBridge.php';
}
if (file_exists($base_path . 'app/Http/Controllers/ShippingController.php')) {
    require_once $base_path . 'app/Http/Controllers/ShippingController.php';
}

use App\Http\Controllers\ShippingController;

if (!defined('ABSPATH')) exit;

class Plugin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'load_assets']);

        // AJAX Save Handler
        add_action('wp_ajax_fc_save_shipping_settings', [$this, 'handle_ajax_save']);
    }

    public function add_menu() {
        add_menu_page('Shipping Rules', 'FC Shipping', 'manage_options', 'fc-shipping-restrictions', [$this, 'render_page'], 'dashicons-admin-site', 56);
    }

    public function render_page() {
        if (class_exists('App\Http\Controllers\ShippingController')) {
            $controller = new ShippingController();
            echo $controller->index();
        } else {
            echo '<div class="notice notice-error"><p>ShippingController not found!</p></div>';
        }
    }

    public function load_assets($hook) {
        if (strpos($hook, 'fc-shipping-restrictions') === false) return;

        // Vite Server Check
        $is_dev = false;
        $fp = @fsockopen('localhost', 5173, $errno, $errstr, 0.1);
        if ($fp) { $is_dev = true; fclose($fp); }

        if ($is_dev) {
            wp_enqueue_script('vite-client', 'http://localhost:5173/@vite/client', [], null, true);
            wp_enqueue_script('fc-js', 'http://localhost:5173/resources/js/app.js', ['vite-client'], null, true);
        } else {
            wp_enqueue_script('fc-js', plugins_url('assets/dist/app.js', __FILE__), [], '1.0', true);
            wp_enqueue_style('fc-css', plugins_url('assets/dist/app.css', __FILE__));
        }

        // Script Type="module" Filter
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'fc-js' || $handle === 'vite-client') {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    public function handle_ajax_save() {
        check_ajax_referer('fc_shipping_nonce', 'nonce');
        if (isset($_POST['allowed'])) update_option('fc_allowed_countries', json_decode(stripslashes($_POST['allowed']), true));
        if (isset($_POST['excluded'])) update_option('fc_excluded_countries', json_decode(stripslashes($_POST['excluded']), true));
        if (isset($_POST['mode'])) update_option('fc_restriction_mode', sanitize_text_field($_POST['mode']));
        wp_send_json_success(['message' => 'Settings Updated']);
    }
}

new Plugin();
