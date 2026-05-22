<?php
/**
 * FOSSCOMM 2026 — theme bootstrap.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FC_THEME_VERSION', '0.1.0');
define('FC_THEME_DIR', get_template_directory());
define('FC_THEME_URI', get_template_directory_uri());

require_once FC_THEME_DIR . '/inc/bootstrap.php';
