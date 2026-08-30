<?php
/**
 * FOSSCOMM 2026 — theme bootstrap.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Keep this in step with `Version:` in style.css. It is not decorative: it is
// the $ver argument on all 18 wp_enqueue_* calls, so it is the only thing
// busting browser caches for the theme's CSS and JS. It had drifted to 0.5.3
// while style.css advertised 3.0.0, which meant every stylesheet and script
// shipped in between was still being served from visitors' caches.
define('FC_THEME_VERSION', '3.0.0');
define('FC_THEME_DIR', get_template_directory());
define('FC_THEME_URI', get_template_directory_uri());

require_once FC_THEME_DIR . '/inc/bootstrap.php';
