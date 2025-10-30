<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Installer
{
    public static function activate()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $targets = $wpdb->prefix . 'sg_targets';
        $captures = $wpdb->prefix . 'sg_captures';

        $sql1 = "CREATE TABLE $targets (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          absolute_url TEXT NOT NULL,
          device VARCHAR(32) NOT NULL DEFAULT 'desktop',
          full_page TINYINT(1) NOT NULL DEFAULT 1,
          enabled TINYINT(1) NOT NULL DEFAULT 1,
          retention INT NOT NULL DEFAULT 3,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id)
        ) $charset;";

        $sql2 = "CREATE TABLE $captures (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          target_id BIGINT UNSIGNED NOT NULL,
          attachment_id BIGINT UNSIGNED NULL,
          status VARCHAR(32) NOT NULL DEFAULT 'ok',
          message TEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY target_idx (target_id)
        ) $charset;";

        \dbDelta($sql1);
        \dbDelta($sql2);

        if (!\wp_next_scheduled('sg_run_due_jobs')) {
            \wp_schedule_event(time() + 60, 'hourly', 'sg_run_due_jobs');
        }
    }

    public static function deactivate()
    {
        $ts = \wp_next_scheduled('sg_run_due_jobs');
        if ($ts) \wp_unschedule_event($ts, 'sg_run_due_jobs');
    }
}
