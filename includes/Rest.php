<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Rest
{
    public static function boot()
    {
        // register REST routes here later
        \add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes()
    {
        // placeholder route so boot() doesn't fail
        \register_rest_route('scripgrab/v1', '/ping', [
            'methods'  => 'GET',
            'callback' => function () {
                return ['ok' => true, 'plugin' => 'ScripGrab'];
            },
            'permission_callback' => '__return_true',
        ]);
    }
}
