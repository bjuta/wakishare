<?php
/**
 * Plugin Name: Your Share Plugin
 * Description: Fast, configurable social share buttons with UTM tagging, floating layouts, and Web Share API support.
 * Version: 1.0.0
 * Author: WAKILISHA
 * Text Domain: your-share
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/activator.php';
require_once __DIR__ . '/includes/class-container.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-options.php';
require_once __DIR__ . '/includes/class-networks.php';
require_once __DIR__ . '/includes/class-icons.php';
require_once __DIR__ . '/includes/class-utm.php';
require_once __DIR__ . '/includes/class-reactions.php';
require_once __DIR__ . '/includes/class-counts.php';
require_once __DIR__ . '/includes/class-render.php';
require_once __DIR__ . '/includes/class-asset-loader.php';
require_once __DIR__ . '/includes/class-analytics.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-shortcode.php';
require_once __DIR__ . '/includes/rest.php';

use YourShare\Activator;
use YourShare\Plugin;

Activator::register(__FILE__);

function your_share_plugin(): Plugin
{
    static $plugin = null;

    if (null === $plugin) {
        $plugin = new Plugin(__FILE__);
    }

    return $plugin;
}

your_share_plugin()->boot();
