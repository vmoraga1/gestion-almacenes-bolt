<?php
if (!defined('ABSPATH')) {
    exit;
}

class Ventas_Messages {
    private static $instance = null;
    private $messages = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add_message($message, $type = 'success') {
        if (!isset($_SESSION['ventas_messages'])) {
            $_SESSION['ventas_messages'] = [];
        }
        $_SESSION['ventas_messages'][] = [
            'message' => $message,
            'type' => $type
        ];
    }

    public function display_messages() {
        if (isset($_SESSION['ventas_messages']) && !empty($_SESSION['ventas_messages'])) {
            foreach ($_SESSION['ventas_messages'] as $message) {
                $class = 'notice notice-' . esc_attr($message['type']) . ' is-dismissible';
                printf('<div class="%1$s"><p>%2$s</p></div>', $class, esc_html($message['message']));
            }
            unset($_SESSION['ventas_messages']);
        }
    }
}