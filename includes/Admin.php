<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Admin
{
    public static function boot()
    {
        \add_action('admin_menu', [__CLASS__, 'menu']);
        \add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        \add_action('admin_post_sg_save_settings', [__CLASS__, 'save_settings']);
        \add_action('admin_post_sg_save_schedule', [__CLASS__, 'save_schedule']);
        \add_action('admin_post_sg_export', [__CLASS__, 'export_captures']);
        \add_action('admin_notices', [__CLASS__, 'maybe_notice']);
    }

    public static function menu()
    {
        \add_menu_page(
            'ScrinGrab',
            'ScrinGrab',
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
        $capture_selection = self::capture_selection_map();

        $scheduled = \get_option('sg_schedule_pages', []);
        if (!is_array($scheduled)) {
            $scheduled = [];
        }
        $scheduled = array_values(array_filter(array_map('esc_url_raw', $scheduled)));

        $tab = isset($_GET['sg_tab']) ? sanitize_key($_GET['sg_tab']) : 'capture';
        if (!in_array($tab, ['capture', 'settings', 'remote'], true)) {
            $tab = 'capture';
        }

        \wp_localize_script('scripgrab-admin', 'SCRIPGRAB', [
            'rest'              => \esc_url_raw(\rest_url('scripgrab/v1')),
            'nonce'             => \wp_create_nonce('wp_rest'),
            'captureSelection'  => $capture_selection,
            'schedulePages'     => $scheduled,
            'activeTab'         => $tab,
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

    public static function save_settings()
    {
        if (!\current_user_can('manage_options')) {
            \wp_die(
                \__('You do not have permission to update these settings.', 'scripgrab'),
                \__('Permission denied', 'scripgrab'),
                ['response' => 403]
            );
        }

        \check_admin_referer('sg_save_settings');

        $retention = isset($_POST['retention']) ? (int) $_POST['retention'] : 3;
        $retention = max(1, min(4, $retention));

        $frequency = isset($_POST['frequency']) ? sanitize_key($_POST['frequency']) : 'manual';
        $allowed_frequency = ['manual', 'daily', 'weekly', 'monthly'];
        if (!in_array($frequency, $allowed_frequency, true)) {
            $frequency = 'manual';
        }

        $storage = isset($_POST['storage']) ? sanitize_key($_POST['storage']) : 'local';
        $allowed_storage = ['local', 'drive'];
        if (!in_array($storage, $allowed_storage, true)) {
            $storage = 'local';
        }

        \update_option('sg_settings', [
            'retention' => $retention,
            'frequency' => $frequency,
            'storage'   => $storage,
        ]);
        \ScripGrab\Jobs::reschedule_from_settings();

        // Testing-stage BYOK: allow admins to store their own ScreenshotMachine key.
        $screenshot_key = isset($_POST['screenshot_key']) ? sanitize_text_field(wp_unslash($_POST['screenshot_key'])) : '';
        \update_option('sg_screenshot_key', $screenshot_key);

        $redirect = \add_query_arg([
            'page'      => 'scripgrab',
            'sg_tab'    => 'settings',
            'sg_notice' => 'settings_saved',
        ], \admin_url('admin.php'));

        \wp_safe_redirect($redirect);
        exit;
    }

    public static function save_schedule()
    {
        if (!\current_user_can('manage_options')) {
            \wp_die(
                \__('You do not have permission to update these settings.', 'scripgrab'),
                \__('Permission denied', 'scripgrab'),
                ['response' => 403]
            );
        }

        \check_admin_referer('sg_save_schedule');

        $pages = isset($_POST['pages']) ? (array) $_POST['pages'] : [];
        $pages = array_filter(array_map('esc_url_raw', $pages));
        $pages = array_values(array_unique($pages));

        \update_option('sg_schedule_pages', $pages);

        $redirect = \add_query_arg([
            'page'      => 'scripgrab',
            'sg_tab'    => 'settings',
            'sg_notice' => 'schedule_saved',
        ], \admin_url('admin.php'));

        \wp_safe_redirect($redirect);
        exit;
    }

    public static function export_captures()
    {
        if (!\current_user_can('manage_options')) {
            \wp_die(
                \__('You do not have permission to export captures.', 'scripgrab'),
                \__('Permission denied', 'scripgrab'),
                ['response' => 403]
            );
        }

        \check_admin_referer('sg_export');

        if (!class_exists(\ZipArchive::class)) {
            \wp_die(\__('The ZipArchive PHP extension is required to export captures.', 'scripgrab'));
        }

        global $wpdb;
        $captures = $wpdb->prefix . 'sg_captures';
        $targets = $wpdb->prefix . 'sg_targets';

        $urls = isset($_POST['urls']) ? (array) $_POST['urls'] : [];
        $urls = array_values(array_unique(array_filter(array_map(static function ($url) {
            return esc_url_raw(wp_unslash((string) $url));
        }, $urls))));

        $device = isset($_POST['device']) ? sanitize_key(wp_unslash($_POST['device'])) : '';
        if (!in_array($device, ['desktop', 'tablet', 'mobile'], true)) {
            $device = '';
        }

        $sql = "SELECT c.id, c.attachment_id, c.created_at
                FROM {$captures} c
                LEFT JOIN {$targets} t ON t.id = c.target_id";
        $where = [];
        $params = [];

        if (!empty($urls)) {
            $placeholders = implode(',', array_fill(0, count($urls), '%s'));
            $where[] = "t.absolute_url IN ({$placeholders})";
            $params = array_merge($params, $urls);
        }
        if ($device) {
            $where[] = "t.device = %s";
            $params[] = $device;
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY c.created_at DESC';

        if (!empty($params)) {
            $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($sql, ARRAY_A);
        }

        $files = [];
        foreach ($rows as $row) {
            $attachment_id = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
            if ($attachment_id <= 0) continue;
            $file_path = \get_attached_file($attachment_id);
            if (!$file_path || !file_exists($file_path)) continue;

            $files[] = [
                'path' => $file_path,
                'name' => self::export_filename($row, $file_path),
            ];
        }

        if (empty($files)) {
            $redirect = \add_query_arg([
                'page'      => 'scripgrab',
                'sg_tab'    => 'capture',
                'sg_notice' => 'export_empty',
            ], \admin_url('admin.php'));
            \wp_safe_redirect($redirect);
            exit;
        }

        $tmp = \wp_tempnam('scringrab-export');
        if (!$tmp) {
            \wp_die(\__('Unable to create a temporary file for the export.', 'scripgrab'));
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($tmp, \ZipArchive::OVERWRITE)) {
            \wp_die(\__('Could not initialize the export archive.', 'scripgrab'));
        }

        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['name']);
        }
        $zip->close();

        if (!file_exists($tmp)) {
            \wp_die(\__('Export failed: archive could not be created.', 'scripgrab'));
        }

        \nocache_headers();
        $filename = 'scringrab-captures-' . gmdate('Ymd-His') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));

        \readfile($tmp);
        @\unlink($tmp);
        exit;
    }

    public static function maybe_notice()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'scripgrab') return;
        if (empty($_GET['sg_notice'])) return;

        $notice_key = sanitize_key($_GET['sg_notice']);
        $messages = [
            'settings_saved' => [
                'class' => 'updated',
                'text'  => __('Backup settings saved.', 'scripgrab'),
            ],
            'schedule_saved' => [
                'class' => 'updated',
                'text'  => __('Scheduled pages updated.', 'scripgrab'),
            ],
            'export_empty' => [
                'class' => 'notice-warning',
                'text'  => __('No captured screenshots were found to export. Run a capture first.', 'scripgrab'),
            ],
        ];

        if (!isset($messages[$notice_key])) return;

        $message = $messages[$notice_key];
        $class = $message['class'];
        $text = $message['text'];

        echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($text) . '</p></div>';
    }

    public static function capture_selection_map(): array
    {
        $map = [
            'desktop' => [],
            'tablet'  => [],
            'mobile'  => [],
        ];

        $targets = Targets::all();
        foreach ($targets as $target) {
            $device = isset($target['device']) ? sanitize_key($target['device']) : 'desktop';
            if (!isset($map[$device])) {
                $map[$device] = [];
            }
            $absolute = isset($target['absolute_url']) ? esc_url_raw($target['absolute_url']) : '';
            if ($absolute) {
                $map[$device][] = $absolute;
            }
        }

        return $map;
    }

    protected static function export_filename(array $row, string $path): string
    {
        $base = basename($path);
        $timestamp = isset($row['created_at']) ? strtotime($row['created_at']) : false;
        $stamp = $timestamp ? gmdate('Ymd-His', $timestamp) : gmdate('Ymd-His');
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $clean = sanitize_file_name($base);
        return 'capture-' . $stamp . '-' . $id . '-' . $clean;
    }
}


