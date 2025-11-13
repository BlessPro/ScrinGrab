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
                return ['ok' => true, 'plugin' => 'ScrinGrab'];
            },
            'permission_callback' => '__return_true',
        ]);

        \register_rest_route('scripgrab/v1', '/preview', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'preview'],
            'permission_callback' => function () {
                return \current_user_can('manage_options');
            },
            'args' => [
                'url' => [
                    'required' => true,
                    'type'     => 'string',
                    'description' => 'The absolute URL of the page to preview.',
                ],
                'device' => [
                    'required' => false,
                    'type'     => 'string',
                    'description' => 'Device to emulate (desktop, tablet, mobile).',
                ],
            ],
        ]);

        // Save captures for selected pages
        \register_rest_route('scripgrab/v1', '/capture', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'capture'],
            'permission_callback' => function () {
                return \current_user_can('manage_options');
            },
            'args' => [
                'urls' => [
                    'required' => true,
                    'type'     => 'array',
                    'description' => 'Array of absolute URLs to capture.',
                ],
                'device' => [
                    'required' => false,
                    'type'     => 'string',
                ],
                'force' => [
                    'required' => false,
                    'type'     => 'boolean',
                ],
            ],
        ]);
    }

    public static function preview(\WP_REST_Request $request)
    {
        $url = esc_url_raw($request->get_param('url'));
        if (!$url) {
            return new \WP_Error('invalid_url', __('A valid page URL is required.', 'scripgrab'), ['status' => 400]);
        }

        // Restrict previews to this site to avoid abuse/SSRF via third-party hosts.
        $target_host = wp_parse_url($url, PHP_URL_HOST);
        $site_host = wp_parse_url(\home_url('/'), PHP_URL_HOST);
        if (!$target_host || !$site_host || !hash_equals(strtolower($site_host), strtolower($target_host))) {
            return new \WP_Error('forbidden_host', __('Previews are limited to this site only.', 'scripgrab'), ['status' => 403]);
        }

        $device = sanitize_key($request->get_param('device') ?: 'desktop');
        $device = in_array($device, ['desktop', 'tablet', 'mobile'], true) ? $device : 'desktop';

        return [
            'ok'        => true,
            'device'    => $device,
            'image'     => self::mshots_url($url, $device),
            'placeholder' => false,
        ];
    }

    protected static function placeholder_image(string $url, string $device): string
    {
        $sizes = [
            'desktop' => [1280, 720],
            'tablet'  => [900, 1200],
            'mobile'  => [480, 960],
        ];

        $size = $sizes[$device] ?? $sizes['desktop'];
        $innerWidth = max(180, $size[0] - 96);
        $innerHeight = max(180, $size[1] - 160);

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host) {
            $host = $url;
        }
        $host_text = htmlspecialchars($host, ENT_QUOTES, 'UTF-8');
        $device_label = strtoupper($device);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d">
                <defs>
                    <linearGradient id="grad" x1="0%%" y1="0%%" x2="100%%" y2="100%%">
                        <stop offset="0%%" stop-color="#0f172a"/>
                        <stop offset="100%%" stop-color="#1e293b"/>
                    </linearGradient>
                </defs>
                <rect width="100%%" height="100%%" fill="url(#grad)"/>
                <rect x="48" y="48" width="%3$d" height="%4$d" rx="24" fill="rgba(15, 23, 42, 0.65)" stroke="rgba(148, 163, 184, 0.45)" stroke-width="4" stroke-dasharray="14 12"/>
                <text x="50%%" y="45%%" text-anchor="middle" fill="#93c5fd" font-family="Segoe UI, Arial, sans-serif" font-size="42" font-weight="600">%5$s PREVIEW</text>
                <text x="50%%" y="59%%" text-anchor="middle" fill="#cbd5f5" font-family="Segoe UI, Arial, sans-serif" font-size="26">%6$s</text>
                <text x="50%%" y="72%%" text-anchor="middle" fill="#64748b" font-family="Segoe UI, Arial, sans-serif" font-size="20">Placeholder — capture not generated yet</text>
            </svg>',
            $size[0],
            $size[1],
            $innerWidth,
            $innerHeight,
            $device_label,
            $host_text
        );

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }

    protected static function mshots_url(string $url, string $device): string
    {
        $widths = [
            'desktop' => 1280,
            'tablet'  => 900,
            'mobile'  => 480,
        ];
        $w = $widths[$device] ?? $widths['desktop'];
        // WordPress.com mShots service. See https://developer.wordpress.com/docs/mshots/
        // Note: External service caches screenshots; freshness is not guaranteed.
        return 'https://s.wordpress.com/mshots/v1/' . rawurlencode($url) . '?w=' . (int) $w;
    }

    public static function capture(\WP_REST_Request $request)
    {
        $urls = (array) $request->get_param('urls');
        $urls = array_values(array_filter(array_map('esc_url_raw', $urls)));
        if (empty($urls)) {
            return new \WP_Error('invalid_urls', __('Provide one or more page URLs.', 'scripgrab'), ['status' => 400]);
        }

        $site_host = wp_parse_url(\home_url('/'), PHP_URL_HOST);
        $device = sanitize_key($request->get_param('device') ?: 'desktop');
        $device = in_array($device, ['desktop','tablet','mobile'], true) ? $device : 'desktop';
        $force = (bool) $request->get_param('force');

        $results = [];
        $saved = 0;

        foreach ($urls as $url) {
            $host = wp_parse_url($url, PHP_URL_HOST);
            // Normalize hosts: treat www.example.com and example.com as the same
            $normalize = function($h) {
                $h = strtolower((string) $h);
                if (strpos($h, 'www.') === 0) $h = substr($h, 4);
                return $h;
            };
            $h1 = $normalize($host);
            $h2 = $normalize($site_host);
            if (!$h1 || !$h2 || $h1 !== $h2) {
                $results[] = ['url' => $url, 'ok' => false, 'message' => 'forbidden_host'];
                continue;
            }

            $shot = \ScripGrab\Renderer::capture($url, $device, true, ['format' => 'jpg', 'force' => $force]);
            if (empty($shot['ok'])) {
                $results[] = ['url' => $url, 'ok' => false, 'message' => $shot['error'] ?? 'capture_failed'];
                continue;
            }

            $save = \ScripGrab\Storage::save_capture($url, $device, $shot['binary'], $shot['mime'] ?? 'image/jpeg', true);
            if (empty($save['ok'])) {
                $results[] = ['url' => $url, 'ok' => false, 'message' => $save['error'] ?? 'save_failed'];
                continue;
            }

            $saved++;
            $results[] = ['url' => $url, 'ok' => true, 'attachment_id' => $save['attachment_id'], 'target_id' => $save['target_id']];
        }

        return ['ok' => true, 'saved' => $saved, 'results' => $results];
    }
}

