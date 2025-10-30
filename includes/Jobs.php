<?php
namespace ScripGrab;

if (!defined('ABSPATH')) exit;

class Jobs
{
    public static function boot()
    {
        // make sure our cron hook exists
        \add_action('sg_run_due_jobs', [__CLASS__, 'run']);
    }

    public static function run()
    {
        // later we'll loop through targets and capture
        // for now, do nothing
    }
}
