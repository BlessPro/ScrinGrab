<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Jobs
{
    public static function boot()
    {
        // make sure our cron hook exists
        \add_action('sg_run_due_jobs', [__CLASS__, 'run']);
        \add_filter('cron_schedules', [__CLASS__, 'cron_schedules']);
    }

    public static function cron_schedules(array $schedules): array
    {
        if (!isset($schedules['sg_monthly'])) {
            $schedules['sg_monthly'] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __('Once Monthly (ScrinGrab)', 'scripgrab'),
            ];
        }
        return $schedules;
    }

    public static function reschedule_from_settings(): void
    {
        $settings = \get_option('sg_settings', []);
        $frequency = isset($settings['frequency']) ? sanitize_key($settings['frequency']) : 'manual';

        \wp_clear_scheduled_hook('sg_run_due_jobs');

        $map = [
            'daily'   => 'daily',
            'weekly'  => 'weekly',
            'monthly' => 'sg_monthly',
        ];

        if (!isset($map[$frequency])) {
            return;
        }

        \wp_schedule_event(time() + 60, $map[$frequency], 'sg_run_due_jobs');
    }

    public static function run()
    {
        $pages = \get_option('sg_schedule_pages', []);
        if (!is_array($pages) || empty($pages)) {
            return;
        }

        $pages = array_values(array_filter(array_map('esc_url_raw', $pages)));
        if (empty($pages)) {
            return;
        }

        $site_host = wp_parse_url(\home_url('/'), PHP_URL_HOST);
        $normalize = static function ($host): string {
            $host = strtolower((string) $host);
            if (strpos($host, 'www.') === 0) {
                $host = substr($host, 4);
            }
            return $host;
        };
        $site_host = $normalize($site_host);

        foreach ($pages as $url) {
            $host = $normalize(wp_parse_url($url, PHP_URL_HOST));
            if (!$host || !$site_host || $host !== $site_host) {
                self::log_failure($url, 'desktop', 'forbidden_host');
                continue;
            }

            $shot = \ScripGrab\Renderer::capture($url, 'desktop', true, ['format' => 'jpg', 'force' => true]);
            if (empty($shot['ok'])) {
                self::log_failure($url, 'desktop', (string) ($shot['error'] ?? 'capture_failed'));
                continue;
            }

            $save = \ScripGrab\Storage::save_capture($url, 'desktop', $shot['binary'], $shot['mime'] ?? 'image/jpeg', true);
            if (empty($save['ok'])) {
                self::log_failure($url, 'desktop', (string) ($save['error'] ?? 'save_failed'));
            }
        }
    }

    protected static function log_failure(string $url, string $device, string $message): void
    {
        $target_id = \ScripGrab\Targets::get_or_create($url, $device, true);
        if ($target_id <= 0) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sg_captures';
        $wpdb->insert($table, [
            'target_id'     => $target_id,
            'attachment_id' => null,
            'status'        => 'error',
            'message'       => sanitize_text_field($message),
            'created_at'    => \current_time('mysql'),
        ]);
    }
}
