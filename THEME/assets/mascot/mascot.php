<?php
/**
 * Sticky pixel-art mascot — enqueues assets and prints the container markup.
 *
 * Loaded via inc/bootstrap.php. Mirrors assets/pet/pet.php (enqueue on
 * wp_enqueue_scripts, mount on wp_footer), but ships plain (non-module) JS so
 * it needs no script_loader_tag filter.
 *
 * Sprite art lives in assets/mascot/ (idle, before_sleep, sleeping,
 * walking_me_xeria, and surprise once that sheet is drawn).
 *
 * ── Runtime config ───────────────────────────────────────────────────────────
 * Everything tunable (on/off, scale, corner, debug panel, triggers) lives in the
 * single option `fc_mascot_settings`, edited at FOSSCOMM → Mascot
 * (inc/admin/pages/mascot.php) exactly like every other section's settings.
 *
 * This file only ever READS that option and merges it over the defaults below,
 * so a site that has never opened the admin page still gets sane behaviour.
 * Nothing here writes to wp_options.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical defaults — the single source of truth, used both as the front-end
 * fallback and as the admin form's initial state.
 *
 * Booleans are real bools because the admin page declares those fields as the
 * `bool` schema type, and fc_sanitize_fields() stores that as true/false.
 */
function fc_mascot_default_settings(): array {
    return [
        'enabled'           => true,       // master switch: no enqueue, no mount when off
        'debug_panel'       => false,      // floating .fcm-debug panel on the front end
        'scale'             => 3,          // 2–5, integer pixel upscale
        'corner'            => 'br',       // bl | br | tl | tr
        'start_state'       => 'idle',     // idle | sleeping
        'sponsors_surprise' => true,       // arm the scroll-into-view surprise trigger
        'surprise_target'   => '#sponsors',// what that trigger watches
        'wander'            => true,       // small periodic steps so he looks alive
        'drag'              => true,       // grab him, swing him, drop him
        'scroll_flee'       => true,       // dash right on scroll so he stops covering text
        'bubble'            => true,       // speech bubble on hover
        'auto_sleep'        => true,       // the 20s inactivity → sleep sequence
        'keyboard_walk'     => true,       // arrow-key hold-to-walk
    ];
}

/** Allowed values for the two enumerated settings — shared with the admin page. */
function fc_mascot_corners(): array {
    return [
        'bl' => 'Bottom left',
        'br' => 'Bottom right',
        'tl' => 'Top left',
        'tr' => 'Top right',
    ];
}
function fc_mascot_start_states(): array {
    return [
        'idle'     => 'Idle (awake)',
        'sleeping' => 'Sleeping',
    ];
}

/** Saved settings merged over the defaults. Safe when the option is missing. */
function fc_mascot_settings(): array {
    $saved = get_option('fc_mascot_settings', []);
    if (!is_array($saved)) {
        $saved = [];
    }
    return array_merge(fc_mascot_default_settings(), $saved);
}

/** Single source of truth for the mascot's enabled flag. */
function fc_mascot_is_enabled(): bool {
    $s = fc_mascot_settings();
    return !empty($s['enabled']);
}

/** Viewport width (px) below which he is considered a phone and suppressed. */
const FC_MASCOT_MOBILE_BREAKPOINT = 768;

/**
 * Desktop only.
 *
 * He is a flourish, not content: on a phone he covers the text he's meant to sit
 * beside, the grab-and-throw interaction competes with scrolling for the same
 * gestures, and the sprite sheets are dead weight on mobile data. Bailing out here
 * means none of it — script, stylesheet or art — is even requested.
 *
 * wp_is_mobile() is user-agent based, so a page cache can hand a phone the desktop
 * HTML and defeat it. mascot.js re-checks the real viewport width, and mascot.css
 * hides the mount outright, so all three would have to fail for him to appear.
 */
function fc_mascot_is_mobile(): bool {
    return function_exists('wp_is_mobile') && wp_is_mobile();
}

/**
 * Sprite catalog: state name → { png, json } URLs.
 *
 * Shared with the plugin's admin preview so both sides resolve identical URLs.
 *
 * Optional poses are registered only when their art is on disk. That guard is
 * load bearing: mascot.js fetches the JSON of every state it knows about, and a
 * 404 would reject the boot promise and take the whole mascot down with it.
 * Missing art therefore means "that pose is silently inert", never "mascot
 * broken" — every caller of setState() already no-ops on an unknown state.
 */
function fc_mascot_sprite_catalog(): array {
    $dir  = FC_THEME_DIR . '/assets/mascot';
    $base = FC_THEME_URI . '/assets/mascot';

    $sprites = [
        'idle' => [
            'png'  => $base . '/idle.png',
            'json' => $base . '/idle.json',
        ],
        'before_sleep' => [
            'png'  => $base . '/before_sleep.png',
            'json' => $base . '/before_sleep.json',
        ],
        'sleeping' => [
            'png'  => $base . '/sleeping.png',
            'json' => null, // single held frame, no atlas
        ],
        'walk' => [
            'png'  => $base . '/walking_me_xeria.png',
            'json' => $base . '/walking_me_xeria.json',
        ],
    ];

    // Surprise. Accepts an animated strip (png + json) if one is ever drawn, and
    // falls back to a single frame otherwise. Both spellings are honoured because
    // the shipped file is "suprise.png" — keeping the typo working costs one array
    // entry and avoids a silently missing reaction if it's re-exported that way.
    foreach (['surprise', 'suprise'] as $name) {
        if (isset($sprites['surprise'])) break;
        if (file_exists($dir . '/' . $name . '.png') && file_exists($dir . '/' . $name . '.json')) {
            $sprites['surprise'] = [
                'png'  => $base . '/' . $name . '.png',
                'json' => $base . '/' . $name . '.json',
            ];
        } elseif (file_exists($dir . '/' . $name . '.png')) {
            $sprites['surprise'] = [
                'png'  => $base . '/' . $name . '.png',
                'json' => null, // single held frame
            ];
        }
    }

    // Drag poses: single 32×32 cells with no atlas, same as sleeping.png.
    foreach (['held', 'falling', 'dizzy'] as $pose) {
        if (file_exists($dir . '/' . $pose . '.png')) {
            $sprites[$pose] = [
                'png'  => $base . '/' . $pose . '.png',
                'json' => null, // single held frame
            ];
        }
    }

    return $sprites;
}

/**
 * The hover speech bubble. Not a sprite — a standalone image with its own
 * dimensions, so it lives outside the 32px-cell catalog.
 */
function fc_mascot_bubble_url() {
    $dir = FC_THEME_DIR . '/assets/mascot/pixel-speech-bubble.png';
    return file_exists($dir) ? FC_THEME_URI . '/assets/mascot/pixel-speech-bubble.png' : null;
}

/** Whether the surprise pose has actually been drawn yet (the admin page reports this). */
function fc_mascot_has_surprise_art(): bool {
    $dir = FC_THEME_DIR . '/assets/mascot';
    return file_exists($dir . '/surprise.png') || file_exists($dir . '/suprise.png');
}

/** Which of the three drag poses are missing, if any — surfaced on the admin page. */
function fc_mascot_missing_drag_art(): array {
    $dir = FC_THEME_DIR . '/assets/mascot';
    $missing = [];
    foreach (['held', 'falling', 'dizzy'] as $pose) {
        if (!file_exists($dir . '/' . $pose . '.png')) {
            $missing[] = $pose . '.png';
        }
    }
    return $missing;
}

/** The settings mascot.js consumes, in its own camelCase shape. */
function fc_mascot_js_config(): array {
    $s = fc_mascot_settings();
    return [
        'debug'            => !empty($s['debug_panel']),
        'scale'            => max(2, min(5, (int) $s['scale'])),
        'corner'           => (string) $s['corner'],
        'startState'       => (string) $s['start_state'],
        'sponsorsSurprise' => !empty($s['sponsors_surprise']),
        'surpriseTarget'   => (string) $s['surprise_target'],
        'mobileBreakpoint' => FC_MASCOT_MOBILE_BREAKPOINT,
        'wander'           => !empty($s['wander']),
        'drag'             => !empty($s['drag']),
        'scrollFlee'       => !empty($s['scroll_flee']),
        'bubble'           => !empty($s['bubble']),
        'autoSleep'        => !empty($s['auto_sleep']),
        'keyboardWalk'     => !empty($s['keyboard_walk']),
    ];
}

add_action('wp_enqueue_scripts', 'fc_mascot_enqueue_assets');
function fc_mascot_enqueue_assets() {
    if (!fc_mascot_is_enabled() || fc_mascot_is_mobile()) return;

    wp_enqueue_style(
        'fc-mascot',
        FC_THEME_URI . '/assets/mascot/mascot.css',
        [],
        FC_THEME_VERSION
    );
    wp_enqueue_script(
        'fc-mascot',
        FC_THEME_URI . '/assets/mascot/mascot.js',
        [],
        FC_THEME_VERSION,
        true
    );

    wp_localize_script('fc-mascot', 'FC_MASCOT', [
        'sprites' => fc_mascot_sprite_catalog(),
        'bubble'  => fc_mascot_bubble_url(),
        'config'  => fc_mascot_js_config(),
    ]);
}

add_action('wp_footer', 'fc_mascot_render_container', 99);
function fc_mascot_render_container() {
    if (!fc_mascot_is_enabled() || fc_mascot_is_mobile()) return;

    $s      = fc_mascot_settings();
    $scale  = max(2, min(5, (int) $s['scale']));
    $corner = in_array($s['corner'], ['bl', 'br', 'tl', 'tr'], true) ? $s['corner'] : 'br';

    printf(
        '<div id="fosscomm-mascot" data-scale="%s" data-corner="%s" aria-hidden="true"></div>',
        esc_attr((string) $scale),
        esc_attr($corner)
    );
}
