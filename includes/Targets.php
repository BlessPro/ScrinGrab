<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Targets
{
    protected static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'sg_targets';
    }

    /**
     * Get all targets (for now we keep it simple).
     */
    public static function all(): array
    {
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC", ARRAY_A);
        return $rows ?: [];
    }

    /**
     * Create a new target.
     * $data = [
     *   'absolute_url' => 'https://example.com/page/',
     *   'device'       => 'desktop',
     *   'full_page'    => 1,
     *   'retention'    => 3,
     *   'enabled'      => 1,
     * ];
     */
    public static function create(array $data): int
    {
        global $wpdb;
        $table = self::table();

        $defaults = [
            'absolute_url' => '',
            'device'       => 'desktop',
            'full_page'    => 1,
            'enabled'      => 1,
            'retention'    => 3,
        ];
        $row = array_merge($defaults, $data);

        $wpdb->insert($table, [
            'absolute_url' => $row['absolute_url'],
            'device'       => $row['device'],
            'full_page'    => (int) $row['full_page'],
            'enabled'      => (int) $row['enabled'],
            'retention'    => (int) $row['retention'],
            'created_at'   => \current_time('mysql'),
            'updated_at'   => \current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Find one target by ID.
     */
    public static function find(int $id): ?array
    {
        global $wpdb;
        $table = self::table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Update a target.
     */
    public static function update(int $id, array $data): bool
    {
        global $wpdb;
        $table = self::table();
        $data['updated_at'] = \current_time('mysql');
        $result = $wpdb->update($table, $data, ['id' => $id]);
        return $result !== false;
    }

    /**
     * Delete a target (and later: related captures).
     */
    public static function delete(int $id): bool
    {
        global $wpdb;
        $table = self::table();
        $deleted = $wpdb->delete($table, ['id' => $id]);
        return (bool) $deleted;
    }

    /**
     * Find by URL + device.
     */
    public static function find_by(string $url, string $device): ?array
    {
        global $wpdb;
        $table = self::table();
        $device = sanitize_key($device);
        $url = esc_url_raw($url);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE absolute_url = %s AND device = %s LIMIT 1", $url, $device), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Get target id or create a new one.
     */
    public static function get_or_create(string $url, string $device, bool $full = true): int
    {
        $existing = self::find_by($url, $device);
        if ($existing && isset($existing['id'])) {
            return (int) $existing['id'];
        }
        return self::create([
            'absolute_url' => esc_url_raw($url),
            'device'       => sanitize_key($device),
            'full_page'    => $full ? 1 : 0,
            'enabled'      => 1,
            'retention'    => 3,
        ]);
    }
}
