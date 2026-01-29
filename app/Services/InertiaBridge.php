<?php
namespace FC\Shipping\Services;

class InertiaBridge {
    public static function render($component, $props = []) {
        $page = [
            'component' => $component,
            'props' => $props,
            'url' => $_SERVER['REQUEST_URI'] ?? '/',
            'version' => null,
        ];
        $json_data = json_encode($page);
        // Use WordPress esc_attr for safe data return
        if (function_exists('esc_attr')) {
            return '<div id="app" data-page="' . esc_attr($json_data) . '"></div>';
        }
        return '<div id="app" data-page=\'' . htmlspecialchars($json_data, ENT_QUOTES, 'UTF-8') . '\'></div>';
    }
}
