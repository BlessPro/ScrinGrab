<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Auth
{
    const OPTION_KEY = 'sg_current_user'; // stores array: ['name'=>..., 'email'=>..., 'picture'=>...]

    public static function boot()
    {
        // listen for login/logout actions coming from admin page
        \add_action('admin_init', [__CLASS__, 'maybe_handle_auth_actions']);
    }

    public static function is_logged_in(): bool
    {
        $user = \get_option(self::OPTION_KEY);
        return is_array($user) && !empty($user['email']);
    }

    public static function current_user(): ?array
    {
        $user = \get_option(self::OPTION_KEY);
        return is_array($user) ? $user : null;
    }

    public static function maybe_handle_auth_actions()
    {
        if (!is_admin()) return;
        if (!isset($_GET['page']) || $_GET['page'] !== 'scripgrab') return;

        // logout
        if (isset($_GET['sg_action']) && $_GET['sg_action'] === 'logout') {
            \delete_option(self::OPTION_KEY);
            \wp_redirect(\admin_url('admin.php?page=scripgrab'));
            exit;
        }

        // switch account = same as logout for now
        if (isset($_GET['sg_action']) && $_GET['sg_action'] === 'switch') {
            \delete_option(self::OPTION_KEY);
            \wp_redirect(\admin_url('admin.php?page=scripgrab'));
            exit;
        }

        // fake login (this is what we'll replace with real Google OAuth later)
        if (isset($_GET['sg_action']) && $_GET['sg_action'] === 'mock_login') {
            $fake = [
                'name'    => 'ScripGrab User',
                'email'   => 'user@example.com',
                'picture' => 'https://www.gravatar.com/avatar/' . md5('user@example.com') . '?s=80&d=identicon',
            ];
            \update_option(self::OPTION_KEY, $fake);
            \wp_redirect(\admin_url('admin.php?page=scripgrab'));
            exit;
        }

        // later:
        // if sg_action === 'oauth_callback' -> verify Google token -> save -> redirect
    }
}
