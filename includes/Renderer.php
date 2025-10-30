<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Renderer
{
    /**
     * Placeholder capture.
     * Later this will call a real screenshot API.
     */
    public static function capture(string $url, string $device='desktop', bool $full=true): array
    {
        // just return a dummy payload so callers don't break
        return [
            'ok'     => true,
            'binary' => '',   // empty for now
        ];
    }
}
