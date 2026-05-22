<?php
/**
 * Autonomous ASCII pet — enqueues assets and prints the container markup.
 *
 * Loaded via inc/bootstrap.php. Self-contained: nothing here writes to wp_options
 * or depends on theme sections beyond reading section offsets in JS at runtime.
 *
 * Toggle via FOSSCOMM → ASCII Pet. Default ON.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Single source of truth for the pet's enabled flag. */
function fc_pet_is_enabled(): bool {
    $opt = get_option('fc_pet_enabled', '1');
    return $opt === '1' || $opt === 1 || $opt === true;
}

add_action('wp_enqueue_scripts', 'fc_pet_enqueue_assets');
function fc_pet_enqueue_assets() {
    if (!fc_pet_is_enabled()) return;
    wp_enqueue_style(
        'fc-pet',
        FC_THEME_URI . '/assets/pet/pet.css',
        [],
        FC_THEME_VERSION
    );
    wp_enqueue_script(
        'fc-pet-engine',
        FC_THEME_URI . '/assets/pet/engine.js',
        [],
        FC_THEME_VERSION,
        true
    );
}

add_filter('script_loader_tag', 'fc_pet_module_tag', 10, 3);
function fc_pet_module_tag($tag, $handle, $src) {
    if ($handle === 'fc-pet-engine') {
        return '<script type="module" src="' . esc_url($src) . '"></script>';
    }
    return $tag;
}

add_action('wp_footer', 'fc_pet_render_container', 99);
function fc_pet_render_container() {
    if (!fc_pet_is_enabled()) return;
    echo '<div id="pet" aria-hidden="true"><pre id="petAscii"></pre></div>';
}
