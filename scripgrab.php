<?php
/**
 * Plugin Name: ScripGrab
 * Plugin URI: https://github.com/BlessPro/wp-screenshot
 * Description: Capture/backup screenshots of selected pages and manage them from one place.
 * Version: 0.1.0
 * Author: Bless Doe (aka BlessPro)
 * Author URI: https://github.com/BlessPro
 * License: GPLv2 or later
 * Text Domain: scripgrab
 */


if (!defined('ABSPATH')) exit;

define('SG_FILE', __FILE__);
define('SG_PATH', plugin_dir_path(__FILE__));
define('SG_URL', plugin_dir_url(__FILE__));
define('SG_VER', '0.1.0');

require_once SG_PATH . 'includes/Installer.php';
require_once SG_PATH . 'includes/Auth.php';
require_once SG_PATH . 'includes/Admin.php';
require_once SG_PATH . 'includes/Targets.php';
require_once SG_PATH . 'includes/Jobs.php';
require_once SG_PATH . 'includes/Renderer.php';
require_once SG_PATH . 'includes/Storage.php';
require_once SG_PATH . 'includes/Rest.php';
require_once SG_PATH . 'includes/Hash.php';

register_activation_hook(__FILE__, ['ScripGrab\\Installer', 'activate']);
register_deactivation_hook(__FILE__, ['ScripGrab\\Installer', 'deactivate']);

add_action('plugins_loaded', function () {
    ScripGrab\Auth::boot();
    ScripGrab\Admin::boot();
    ScripGrab\Jobs::boot();
    ScripGrab\Rest::boot();
});
