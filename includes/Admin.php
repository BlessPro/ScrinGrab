<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Admin
{
    public static function boot()
    {
        \add_action('admin_menu', [__CLASS__, 'menu']);
        \add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function menu()
    {
        \add_menu_page(
            'ScripGrab',
            'ScripGrab',
            'manage_options',
            'scripgrab',
            [__CLASS__, 'page'],
            'dashicons-camera',
            56
        );
    }

    public static function assets($hook)
    {
        if (strpos($hook, 'scripgrab') === false) return;

        \wp_enqueue_style('scripgrab-admin', SG_URL . 'assets/admin.css', [], SG_VER);
        \wp_enqueue_script('scripgrab-admin', SG_URL . 'assets/admin.js', ['wp-api-fetch'], SG_VER, true);
        \wp_localize_script('scripgrab-admin', 'SCRIPGRAB', [
            'rest'  => \esc_url_raw(\rest_url('scripgrab/v1')),
            'nonce' => \wp_create_nonce('wp_rest'),
        ]);
    }

    public static function page()
    {
        $is_logged = Auth::is_logged_in();

        if (!$is_logged) {
            include SG_PATH . 'templates/overlay-auth.php';
        } else {
            $user = Auth::current_user();
            include SG_PATH . 'templates/dashboard.php';
        }
    }
}
