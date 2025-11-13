<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Renderer
{
    /**
     * Capture a screenshot via ScreenshotMachine and return raw binary and mime.
     */
    public static function capture(string $url, string $device='desktop', bool $full=true, array $opts = []): array
    {
        $key = \get_option('sg_screenshot_key');
        if (!$key) {
            return ['ok' => false, 'error' => 'missing_key'];
        }

        $dimension = self::dimension_for($device, $full);
        $format = (isset($opts['format']) && $opts['format'] === 'png') ? 'png' : 'jpg';

        $params = [
            'key'       => $key,
            'url'       => $url,
            'dimension' => $dimension,
            'format'    => $format,
        ];
        if (!empty($opts['force'])) {
            $params['cacheLimit'] = 0; // force refresh
        }

        $endpoint = \add_query_arg($params, 'https://api.screenshotmachine.com');

        $response = \wp_remote_get($endpoint, [
            'timeout'     => 30,
            'redirection' => 3,
        ]);
        if (\is_wp_error($response)) {
            return ['ok' => false, 'error' => 'http_error'];
        }
        $code = (int) \wp_remote_retrieve_response_code($response);
        $body = (string) \wp_remote_retrieve_body($response);
        if ($code !== 200 || !$body) {
            return ['ok' => false, 'error' => 'bad_response', 'status' => $code];
        }

        $ctype = \wp_remote_retrieve_header($response, 'content-type');
        $mime = (is_string($ctype) && stripos($ctype, 'png') !== false) ? 'image/png' : 'image/jpeg';

        return [
            'ok'     => true,
            'binary' => $body,
            'mime'   => $mime,
        ];
    }

    protected static function dimension_for(string $device, bool $full): string
    {
        $map = [
            'desktop' => [1280, $full ? 'full' : 800],
            'tablet'  => [900,  $full ? 'full' : 1200],
            'mobile'  => [480,  $full ? 'full' : 960],
        ];
        $device = in_array($device, ['desktop', 'tablet', 'mobile'], true) ? $device : 'desktop';
        [$w, $h] = $map[$device];
        return $w . 'x' . $h;
    }
}
