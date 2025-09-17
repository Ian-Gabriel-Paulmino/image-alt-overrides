<?php

/**
 *  Plugin Name: Image Alt Overrides
 *  Description: Allows per-page alt text overrides for images, improving SEO acceessibility 
 *  Version: 1.0.0
 *  Author: Isiah
 *  Text Domain: image-alt-overrides
 * 
 * 
 */

// Prevents direct access
if ( ! defined('ABSPATH') ) exit;

// Autoloader class init
require_once plugin_dir_path( __FILE__ ) . 'includes/class-iao-loader.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-iao-activator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-iao-deactivator.php';

// Activator and Deactivator class init
register_activation_hook( __FILE__, ['IAO_Activator', 'activate']);
register_deactivation_hook( __FILE__,['IAO_Deactivator', 'deactivate']);

function run_image_alt_overrides() {
    $plugin = new IAO_Loader();
    $plugin->run();
}

run_image_alt_overrides();