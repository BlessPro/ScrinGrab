<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Storage
{
    public static function save_capture(string $url, string $device, string $binary, string $mime, bool $full = true): array
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $target_id = Targets::get_or_create($url, $device, $full);
        if ($target_id <= 0) {
            return ['ok' => false, 'error' => 'target_failed'];
        }

        $filename = self::filename_for($url, $device, $mime);
        $upload = \wp_upload_bits($filename, null, $binary);
        if (!empty($upload['error']) || empty($upload['file'])) {
            return ['ok' => false, 'error' => 'upload_failed'];
        }

        $file = $upload['file'];
        $filetype = \wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $filetype['type'] ?: $mime,
            'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id = \wp_insert_attachment($attachment, $file);
        if (!$attach_id) {
            @\unlink($file);
            return ['ok' => false, 'error' => 'attachment_failed'];
        }

        $attach_data = \wp_generate_attachment_metadata($attach_id, $file);
        \wp_update_attachment_metadata($attach_id, $attach_data);

        global $wpdb;
        $table = $wpdb->prefix . 'sg_captures';
        $wpdb->insert($table, [
            'target_id'     => $target_id,
            'attachment_id' => $attach_id,
            'status'        => 'ok',
            'message'       => null,
            'created_at'    => \current_time('mysql'),
        ]);

        self::enforce_retention($target_id);

        return ['ok' => true, 'target_id' => $target_id, 'attachment_id' => $attach_id, 'capture_id' => (int) $wpdb->insert_id];
    }

    protected static function enforce_retention(int $target_id): void
    {
        $settings = \get_option('sg_settings', []);
        $keep = isset($settings['retention']) ? (int) $settings['retention'] : 3;
        $keep = max(1, min(20, $keep));

        global $wpdb;
        $table = $wpdb->prefix . 'sg_captures';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, attachment_id FROM {$table} WHERE target_id = %d ORDER BY created_at DESC, id DESC",
            $target_id
        ), ARRAY_A) ?: [];

        if (count($rows) <= $keep) return;

        $to_remove = array_slice($rows, $keep);
        foreach ($to_remove as $row) {
            $att = isset($row['attachment_id']) ? (int) $row['attachment_id'] : 0;
            if ($att > 0) {
                \wp_delete_attachment($att, true);
            }
            $wpdb->delete($table, ['id' => (int) $row['id']]);
        }
    }

    protected static function filename_for(string $url, string $device, string $mime): string
    {
        $stamp = gmdate('Ymd-His');
        $host = \wp_parse_url($url, PHP_URL_HOST) ?: 'site';
        $path = \wp_parse_url($url, PHP_URL_PATH) ?: '/';
        $slug = trim($path, '/') ?: 'home';
        $ext = (stripos($mime, 'png') !== false) ? 'png' : 'jpg';
        return \sanitize_file_name("scringrab-{$stamp}-{$device}-{$host}-" . str_replace('/', '-', $slug) . ".{$ext}");
    }
}
