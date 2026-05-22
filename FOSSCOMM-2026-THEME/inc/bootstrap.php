<?php
/**
 * Boots the theme. Loads all subsystems in dependency order.
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once FC_THEME_DIR . '/inc/helpers.php';
require_once FC_THEME_DIR . '/inc/i18n/lang.php';
require_once FC_THEME_DIR . '/inc/i18n/strings.php';
require_once FC_THEME_DIR . '/inc/sections/registry.php';
require_once FC_THEME_DIR . '/inc/sections/store.php';
require_once FC_THEME_DIR . '/inc/sections/render.php';
require_once FC_THEME_DIR . '/inc/seed.php';
require_once FC_THEME_DIR . '/inc/news.php';
require_once FC_THEME_DIR . '/inc/conduct.php';
require_once FC_THEME_DIR . '/assets/pet/pet.php';

if (is_admin()) {
    require_once FC_THEME_DIR . '/inc/admin/menu.php';
    require_once FC_THEME_DIR . '/inc/admin/bilingual-field.php';
    require_once FC_THEME_DIR . '/inc/admin/repeater-field.php';
    require_once FC_THEME_DIR . '/inc/admin/sections-page.php';

    foreach (glob(FC_THEME_DIR . '/inc/admin/pages/*.php') as $page_file) {
        require_once $page_file;
    }
}

add_action('after_setup_theme', 'fc_theme_setup');
function fc_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
}

add_action('wp_enqueue_scripts', 'fc_enqueue_assets');
function fc_enqueue_assets() {
    // Tailwind v4 browser build — fine for development/preview. Replace with a compiled
    // assets/dist/fc.css in production (see tools/build.mjs, future phase).
    wp_enqueue_script(
        'fc-tailwind',
        'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4',
        [],
        null,
        false
    );
    wp_enqueue_style(
        'fc-app',
        FC_THEME_URI . '/assets/dist/fc.css',
        [],
        FC_THEME_VERSION
    );
    wp_enqueue_style(
        'fc-site',
        FC_THEME_URI . '/assets/site.css',
        ['fc-app'],
        FC_THEME_VERSION
    );
    wp_enqueue_script(
        'fc-coastlines',
        FC_THEME_URI . '/assets/dist/fc-coastlines.js',
        [],
        FC_THEME_VERSION,
        true
    );
    wp_enqueue_script(
        'fc-app',
        FC_THEME_URI . '/assets/dist/fc.js',
        ['fc-coastlines'],
        FC_THEME_VERSION,
        true
    );
    wp_enqueue_script(
        'fc-section-nav',
        FC_THEME_URI . '/assets/section-nav.js',
        [],
        FC_THEME_VERSION,
        true
    );
    wp_enqueue_script(
        'fc-countdown',
        FC_THEME_URI . '/assets/countdown.js',
        ['fc-app'],
        FC_THEME_VERSION,
        true
    );
    // Reusable port of the hero's glyph-scramble: exposes window.fcScramble().
    wp_enqueue_script(
        'fc-scramble',
        FC_THEME_URI . '/assets/scramble.js',
        [],
        FC_THEME_VERSION,
        true
    );
    // FAQ scramble-swap behaviour (depends on window.fcScramble).
    wp_enqueue_script(
        'fc-faq',
        FC_THEME_URI . '/assets/faq.js',
        ['fc-scramble'],
        FC_THEME_VERSION,
        true
    );
    // Admin-driven hover-text scramble for CTA links (Home, Get Involved,
    // Sponsor CTA, Footer). Inert on mobile and on links without hover text.
    wp_enqueue_script(
        'fc-hover-scramble',
        FC_THEME_URI . '/assets/hover-scramble.js',
        ['fc-scramble'],
        FC_THEME_VERSION,
        true
    );
    // CFP submission countdown + funding-bar shake (Get Involved section).
    wp_enqueue_script(
        'fc-cfp',
        FC_THEME_URI . '/assets/cfp.js',
        [],
        FC_THEME_VERSION,
        true
    );
    // Global animated wave background (replaces the old static dot pattern).
    // Injects a fixed full-viewport canvas behind every section; visible only
    // through sections that don't carry .bg-paper (.fc-section-dots opts in).
    wp_enqueue_script(
        'fc-wave-bg',
        FC_THEME_URI . '/assets/wave-bg.js',
        [],
        FC_THEME_VERSION,
        true
    );
    wp_localize_script('fc-app', 'FC_DATA', [
        'home'        => home_url('/'),
        'eventStart'  => fc_get_event_start_iso(),
    ]);
}

add_action('wp_head', 'fc_inline_theme_config', 5);
function fc_inline_theme_config() {
    ?>
<style type="text/tailwindcss">
@theme {
    --color-paper: #FAFAF7;
    --color-ink: #0A0A0A;
    --color-ink-muted: #6B6B66;
    --color-ink-faint: #C9C7BF;
    --color-accent: #0033FF;
    --color-border: color-mix(in oklab, #0A0A0A 12%, transparent);
    --font-display: "Space Grotesk", "Inter", ui-sans-serif, system-ui, sans-serif;
    --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
    --font-mono: "JetBrains Mono", ui-monospace, "SF Mono", Menlo, monospace;
    --radius-sm: 0px;
    --radius-md: 0px;
    --radius-lg: 0px;
}
</style>
    <?php
}

add_action('after_switch_theme', 'fc_on_activate');
function fc_on_activate() {
    if (function_exists('fc_seed_initial_content')) {
        fc_seed_initial_content();
    }
}
