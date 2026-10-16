<?php
/**
 * The loading screen — WordPress side.
 *
 * What the visitor sees: the site's blue with the "light off" logo in the middle;
 * the logo flickers on like a tube light, each step popping with the mascot's own
 * squash and stretch; it stays lit until the page has actually loaded (phones take
 * longer); then the mascot takes the logo's place and plays his Opening animation,
 * and the blue sweeps away towards the hero's blue panel — left on desktop, up on
 * a phone — taking his floor with it, so he falls. Under it is the site's white
 * with its own moving dots, the top bar and the wordmark in blue; under that, blue
 * again with the bar and the wordmark in white; and under that the site, every
 * piece of it already exactly in place.
 *
 * This file is the contract: the option, its defaults and its validation, the
 * markup and CSS for the first paint, and the payload the browser runs on. The
 * behaviour is assets/loader/loader.js (a side-effect-free factory, also used by
 * the dashboard preview) and assets/loader/boot.js (the front-end sequence).
 *
 * ── Why everything here is inline ────────────────────────────────────────────
 * The overlay has to be in the FIRST paint, or the site flashes before it. So its
 * CSS and a tiny classic script go into <head> at priority 1 — ahead of every
 * stylesheet <link>, because an inline script after a stylesheet waits for that
 * stylesheet — and the markup is the first child of <body>. The two scripts that
 * drive it are printed inline straight after the markup, so they run the moment
 * the overlay exists: enqueued, they would sit behind seventeen synchronous footer
 * scripts (MapLibre among them) and start long after the visitor has been staring
 * at a dark logo.
 *
 * ── Two constraints from the rest of the theme ──────────────────────────────
 * • The Tailwind CDN runtime watches the whole document for class changes and
 *   added nodes and rescans on every one. The loader therefore changes state with
 *   data-* attributes and inline styles only, and adds no nodes at runtime.
 * • Nothing touches overflow or scrollbar-gutter on <html>/<body>: a desktop
 *   scrollbar appearing or disappearing changes the layout width that the belts,
 *   the schedule, the dot field and the mascot have already measured. Scrolling is
 *   blocked from the overlay instead (assets/loader/boot.js).
 */
if (!defined('ABSPATH')) {
    exit;
}

const FC_LOADER_OPTION = 'fc_loader';

/**
 * The width at which the hero's blue panel moves from the top of the page to its
 * left third (template-parts/sections/hero.php, `@media (min-width: 1024px)`).
 * The swipe follows the panel: up below this, left from it.
 */
const FC_LOADER_BREAKPOINT = 1024;

/**
 * The longest the mascot's Opening is waited for before the swipe goes ahead
 * without it, in ms. Sent to the browser in the payload, so the wait there and
 * the failsafe budget here (fc_loader_failsafe_ms) are one number.
 */
const FC_LOADER_OPENING_CAP = 20000;

/* ─────────────────────────────────────────────────────────────────────────────
   THE OPTION
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * A fluorescent tube starting: dark, a couple of stuttering catches, a long dark
 * gap, one more catch, and then it holds. The last row is the light STAYING ON —
 * its hold is the shortest time the lit logo is shown before anything moves, and
 * its bounciness is the big pop of it coming on.
 */
function fc_loader_default_steps(): array {
    return [
        ['state' => 'off', 'ms' => 500, 'bounce' => 0],
        ['state' => 'on',  'ms' => 60,  'bounce' => 0.6],
        ['state' => 'off', 'ms' => 90,  'bounce' => 0],
        ['state' => 'on',  'ms' => 40,  'bounce' => 0.4],
        ['state' => 'off', 'ms' => 280, 'bounce' => 0],
        ['state' => 'on',  'ms' => 120, 'bounce' => 0.8],
        ['state' => 'off', 'ms' => 70,  'bounce' => 0],
        ['state' => 'on',  'ms' => 600, 'bounce' => 2],
    ];
}

/** Everything, at its default. Also the shape a never-saved install runs on. */
function fc_loader_defaults(): array {
    return [
        'enabled'       => true,
        'where'         => 'all',       // all | front
        'repeat'        => 'every',     // every | session
        'logo_off'      => '',
        'logo_on'       => '',
        'tint_off'      => '',          // '' = the logo's own colours (placeholder: black)
        'tint_on'       => '',          // '' = the logo's own colours (placeholder: white)
        'size_desktop'  => 220,
        'size_mobile'   => 140,
        'glow'          => 40,          // strength, 0–100
        'glow_size'     => 60,          // px beyond the logo's edge
        'glow_colour'   => '',          // '' = white
        'glow_style'    => 'bloom',     // bloom | neon | both
        'bounce_origin' => 'bottom',    // bottom | centre
        'bounce_ms'     => 700,
        'steps'         => fc_loader_default_steps(),
        'max_wait_s'    => 8.0,
        'buzz'          => true,
        'mascot'        => true,
        'fall_at'       => 'floor',     // floor (the blue sweeps from under him) | swipe | reveal
        'swipe_sync'    => 'reach',     // reach (the first edge reaches him as Opening ends) | after (Opening, then the swipe)
        'swipe_delay'   => 0,           // ms he holds Opening's last frame: before the floor goes (reach) / the blue moves (after)
        'layer1'        => '',          // '' = follow the site
        'layer2'        => '',
        'layer3'        => '',
        'swipe_ms'      => 1000,
        'gap_ms'        => 420,
        'ease'          => 'persona',   // persona | smooth | glide
        'edge'          => 'straight',  // straight | wave — the shape of each layer's trailing edge
        'wave_height'   => 26,          // px, crest to centre
        'wave_length'   => 340,         // px, crest to crest
        'wave_speed'    => 1.2,         // ripples per second along the edge
        'layer2_dots'   => true,        // the white layer carries the site's own moving dot field
        'layer2_spot'   => true,        // …and the cursor's goo, the site's own (assets/spotlight-dots.js)
        'reveal_build'  => true,        // the layers carry the top bar and the wordmark before the site
    ];
}

/**
 * What an empty colour box means. Read at call time, never at include time:
 * FC_CHROME_ACCENT is declared in inc/bootstrap.php AFTER this file is required.
 */
function fc_loader_colour_defaults(): array {
    return [
        'layer1' => defined('FC_CHROME_ACCENT') ? FC_CHROME_ACCENT : '#0033FF',   // the site blue, the phone chrome's too
        'layer2' => defined('FC_PAPER') ? FC_PAPER : '#FAFAF7',                   // the page's own white
        'layer3' => defined('FC_CHROME_ACCENT') ? FC_CHROME_ACCENT : '#0033FF',   // blue again, last over the site
    ];
}

/**
 * The three curves on offer. "Persona" is the reference sweep's own
 * easeInOutQuart; "Smooth" is a gentler cubic; "Long glide" arrives early and
 * coasts. Mirrored by FCLoader.EASE in assets/loader/loader.js.
 */
function fc_loader_eases(): array {
    return [
        'persona' => ['Persona (sharp in, sharp out)', 'cubic-bezier(0.76, 0, 0.24, 1)'],
        'smooth'  => ['Smooth',                        'cubic-bezier(0.65, 0, 0.35, 1)'],
        'glide'   => ['Long glide',                    'cubic-bezier(0.7, 0, 0.2, 1)'],
    ];
}

function fc_loader_int($v, int $min, int $max, int $default): int {
    return is_numeric($v) ? max($min, min($max, (int) round((float) $v))) : $default;
}

function fc_loader_float($v, float $min, float $max, float $default, int $dp = 1): float {
    return is_numeric($v) ? round(max($min, min($max, (float) $v)), $dp) : $default;
}

/** A hex colour, or '' — which every reader takes to mean "follow the site". */
function fc_loader_hex($v): string {
    $c = sanitize_hex_color(trim((string) $v));
    return is_string($c) ? $c : '';
}

/** The most steps a timeline may have, "Stays on" included. Mirrored in loader.js and admin.js. */
const FC_LOADER_MAX_STEPS = 60;

/**
 * The flicker timeline: 1–60 rows, the last one always ON. A list that arrives
 * empty or unreadable falls back to the default rather than to nothing, because a
 * loading screen with no light in it never turns on. A list that is too long
 * loses rows from the middle, never its last one: that is "Stays on", whose hold
 * and pop belong to the light coming on for good.
 */
function fc_loader_sanitize_steps($raw): array {
    if (!is_array($raw)) return fc_loader_default_steps();
    $steps = [];
    foreach ($raw as $row) {
        if (!is_array($row)) continue;
        $steps[] = [
            'state'  => (($row['state'] ?? '') === 'on') ? 'on' : 'off',
            'ms'     => fc_loader_int($row['ms'] ?? null, 16, 10000, 120),
            'bounce' => fc_loader_float($row['bounce'] ?? null, 0.0, 10.0, 0.0, 2),
        ];
        if (count($steps) >= 1000) break;       // read no further into a runaway list
    }
    if (!$steps) return fc_loader_default_steps();
    if (count($steps) > FC_LOADER_MAX_STEPS) {
        $steps = array_merge(array_slice($steps, 0, FC_LOADER_MAX_STEPS - 1), [$steps[count($steps) - 1]]);
    }
    $steps[count($steps) - 1]['state'] = 'on';
    return $steps;
}

/**
 * Validate a whole option. Used on save, on every read, and for the dashboard's
 * preview payload, so all three agree on what a setting means.
 *
 * Numbers are clamped rather than rejected; choices fall back to their default;
 * a box that is missing reads as unticked, so callers must send explicit
 * booleans. Unknown keys are dropped.
 */
function fc_loader_sanitize(array $raw): array {
    $d = fc_loader_defaults();
    $pick = static function ($v, array $allowed, string $default): string {
        return in_array($v, $allowed, true) ? $v : $default;
    };

    return [
        'enabled'       => !empty($raw['enabled']),
        'where'         => $pick($raw['where'] ?? null, ['all', 'front'], $d['where']),
        'repeat'        => $pick($raw['repeat'] ?? null, ['every', 'session'], $d['repeat']),
        'logo_off'      => esc_url_raw(trim((string) ($raw['logo_off'] ?? ''))),
        'logo_on'       => esc_url_raw(trim((string) ($raw['logo_on'] ?? ''))),
        'tint_off'      => fc_loader_hex($raw['tint_off'] ?? ''),
        'tint_on'       => fc_loader_hex($raw['tint_on'] ?? ''),
        'size_desktop'  => fc_loader_int($raw['size_desktop'] ?? null, 24, 1200, $d['size_desktop']),
        'size_mobile'   => fc_loader_int($raw['size_mobile'] ?? null, 24, 1200, $d['size_mobile']),
        'glow'          => fc_loader_int($raw['glow'] ?? null, 0, 100, $d['glow']),
        'glow_size'     => fc_loader_int($raw['glow_size'] ?? null, 0, 400, $d['glow_size']),
        'glow_colour'   => fc_loader_hex($raw['glow_colour'] ?? ''),
        'glow_style'    => $pick($raw['glow_style'] ?? null, ['bloom', 'neon', 'both'], $d['glow_style']),
        'bounce_origin' => $pick($raw['bounce_origin'] ?? null, ['bottom', 'centre'], $d['bounce_origin']),
        'bounce_ms'     => fc_loader_int($raw['bounce_ms'] ?? null, 100, 3000, $d['bounce_ms']),
        'steps'         => fc_loader_sanitize_steps($raw['steps'] ?? null),
        'max_wait_s'    => fc_loader_float($raw['max_wait_s'] ?? null, 1.0, 30.0, $d['max_wait_s']),
        'buzz'          => !empty($raw['buzz']),
        'mascot'        => !empty($raw['mascot']),
        'fall_at'       => $pick($raw['fall_at'] ?? null, ['floor', 'swipe', 'reveal'], $d['fall_at']),
        'swipe_sync'    => $pick($raw['swipe_sync'] ?? null, ['reach', 'after'], $d['swipe_sync']),
        'swipe_delay'   => fc_loader_int($raw['swipe_delay'] ?? null, 0, 3000, $d['swipe_delay']),
        'layer1'        => fc_loader_hex($raw['layer1'] ?? ''),
        'layer2'        => fc_loader_hex($raw['layer2'] ?? ''),
        'layer3'        => fc_loader_hex($raw['layer3'] ?? ''),
        'swipe_ms'      => fc_loader_int($raw['swipe_ms'] ?? null, 200, 5000, $d['swipe_ms']),
        'gap_ms'        => fc_loader_int($raw['gap_ms'] ?? null, 0, 5000, $d['gap_ms']),
        'ease'          => $pick($raw['ease'] ?? null, array_keys(fc_loader_eases()), $d['ease']),
        'edge'          => $pick($raw['edge'] ?? null, ['straight', 'wave'], $d['edge']),
        'wave_height'   => fc_loader_int($raw['wave_height'] ?? null, 0, 200, $d['wave_height']),
        'wave_length'   => fc_loader_int($raw['wave_length'] ?? null, 40, 3000, $d['wave_length']),
        'wave_speed'    => fc_loader_float($raw['wave_speed'] ?? null, 0.0, 6.0, $d['wave_speed'], 2),
        'layer2_dots'   => !empty($raw['layer2_dots']),
        'layer2_spot'   => !empty($raw['layer2_spot']),
        'reveal_build'  => !empty($raw['reveal_build']),
    ];
}

/**
 * The settings in force for this request.
 *
 * Memoised. Pass $fresh after writing the option — the dashboard saves during its
 * own render, and without the re-read it would spend that request showing what
 * was just replaced. A valid dashboard preview request (see the PREVIEW section)
 * runs on the settings it carries instead of the saved ones.
 *
 * The saved option is merged over the defaults and validated again on the way
 * out, so a setting added after the option was last saved reads as its default
 * rather than as missing — or, for a tick box, as false.
 */
function fc_loader_settings(bool $fresh = false): array {
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;

    $preview = fc_loader_preview_raw();
    if ($preview !== null) {
        return $cache = fc_loader_sanitize(array_merge(fc_loader_defaults(), $preview));
    }

    $saved = get_option(FC_LOADER_OPTION, null);
    if (!is_array($saved)) return $cache = fc_loader_defaults();
    return $cache = fc_loader_sanitize(array_merge(fc_loader_defaults(), $saved));
}

/** Colours with "follow the site" resolved to the actual hex. */
function fc_loader_colours(array $s): array {
    $out = [];
    foreach (fc_loader_colour_defaults() as $key => $fallback) {
        $out[$key] = ($s[$key] ?? '') !== '' ? $s[$key] : $fallback;
    }
    return $out;
}

/* ─────────────────────────────────────────────────────────────────────────────
   WHEN IT SHOWS
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * Whether this request gets the loading screen at all.
 *
 * Front-end page views only — never wp-admin, feeds, REST, AJAX, cron, embeds or
 * the Customizer. "Front page only" uses the same test that adds body.fc-landing.
 * `?fc_loader=0` switches it off for one view, which is handy when checking
 * something else on the site. A valid dashboard preview always shows it.
 *
 * Whether a visitor has already seen it this visit ("first page of each visit")
 * is decided in the browser — sessionStorage — by the head script, not here: a
 * page cache would otherwise freeze whichever answer it saw first.
 */
function fc_loader_should_show(): bool {
    static $memo = null;
    if ($memo !== null) return $memo;

    if (is_admin() || wp_doing_ajax() || wp_doing_cron()
        || (defined('REST_REQUEST') && REST_REQUEST)
        || is_feed() || is_robots() || is_embed() || is_customize_preview()) {
        return $memo = false;
    }
    if (fc_loader_is_preview()) return $memo = true;

    $s = fc_loader_settings();
    if (empty($s['enabled'])) return $memo = false;
    if (isset($_GET['fc_loader']) && (string) wp_unslash($_GET['fc_loader']) === '0') return $memo = false;
    if ($s['where'] === 'front' && !(function_exists('fc_is_landing_page') && fc_is_landing_page())) {
        return $memo = false;
    }
    return $memo = true;
}

/* ─────────────────────────────────────────────────────────────────────────────
   THE LOGOS
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * One logo as ['src', 'w', 'h'], or null for the placeholder square.
 *
 * Inlined as a data: URI wherever possible (an uploads-folder image of 128KB or
 * less, via fc_cursor_inline_data()), so the dark logo is in the very first paint
 * instead of arriving a round trip later. Anything else is linked by URL, with its
 * shape read from the file (SVG) or the media library, so the box can be the
 * right proportion before the image has loaded.
 */
function fc_loader_logo(string $url): ?array {
    $url = trim($url);
    if ($url === '') return null;

    if (function_exists('fc_cursor_inline_data')) {
        $inline = fc_cursor_inline_data($url);
        if (!empty($inline['data'])) {
            return ['src' => $inline['data'], 'w' => (int) ($inline['w'] ?? 0), 'h' => (int) ($inline['h'] ?? 0)];
        }
    }

    $w = 0;
    $h = 0;
    if (function_exists('fc_is_svg_url') && fc_is_svg_url($url)) {
        [$w, $h] = fc_svg_size($url);
    } else {
        $id = attachment_url_to_postid($url);
        if ($id) {
            $meta = wp_get_attachment_metadata($id);
            $w = (int) ($meta['width'] ?? 0);
            $h = (int) ($meta['height'] ?? 0);
        }
    }
    return ['src' => $url, 'w' => (int) $w, 'h' => (int) $h];
}

/** width/height of the box: the lit logo's proportions, else the dark one's, else square. */
function fc_loader_ratio(?array $off, ?array $on): float {
    foreach ([$on, $off] as $logo) {
        if ($logo && $logo['w'] > 0 && $logo['h'] > 0) return $logo['w'] / $logo['h'];
    }
    return 1.0;
}

/* ─────────────────────────────────────────────────────────────────────────────
   MARKUP + CSS
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * How long until the safety nets pull the overlay down, in ms: the longest a
 * healthy sequence can take, plus margin. Only a broken script ever gets here.
 */
function fc_loader_failsafe_ms(array $s): int {
    $flicker = 0;
    foreach ($s['steps'] as $step) $flicker += (int) $step['ms'];
    $steps = array_values((array) $s['steps']);
    $last  = $steps ? (int) $steps[count($steps) - 1]['ms'] : 0;
    // The wait for the page — and then, on a slow one, the light settling for
    // the last step's hold once more before anything moves (boot.js steadilyLit).
    $wait = max((int) round($s['max_wait_s'] * 1000), $flicker + 1500) + $last;
    // + the Opening cap, the swipe and its two gaps, and margin.
    return $wait + FC_LOADER_OPENING_CAP + (int) $s['swipe_delay'] + 2 * (int) $s['gap_ms'] + (int) $s['swipe_ms'] + 4000;
}

/** A number for a CSS value: no trailing zeros, never "-0". */
function fc_loader_css_num(float $n, int $dp = 4): string {
    $s = rtrim(rtrim(number_format($n, $dp, '.', ''), '0'), '.');
    return ($s === '' || $s === '-0') ? '0' : $s;
}

/**
 * One of the two logos, as the element that draws it.
 *
 * Always a <span>, in one of three modes, so the dashboard can switch between
 * them by changing an attribute rather than swapping nodes:
 *   img   the file as it is, in its own colours (a CSS background);
 *   mask  the file's SHAPE filled with the chosen colour (a CSS mask) — the way to
 *         recolour an SVG that is drawn as an image and cannot be styled inside;
 *   ph    no file: the placeholder square, in the chosen colour or the default
 *         (black for off, white for on).
 * --src carries the file and --tint the colour, both inline.
 */
function fc_loader_art(?array $logo, string $which, string $tint): string {
    $mode  = $logo ? ($tint !== '' ? 'mask' : 'img') : 'ph';
    $style = [];
    if ($logo) $style[] = '--src:url("' . str_replace('"', '%22', $logo['src']) . '")';
    $style[] = '--tint:' . ($tint !== '' ? $tint : ($which === 'on' ? '#FFFFFF' : '#000000'));
    return sprintf(
        '<span class="fc-loader__art" data-fcl-%s data-mode="%s" style="%s"></span>',
        $which, $mode, esc_attr(implode(';', $style))
    );
}

/**
 * The overlay.
 *
 * Every layer is rendered here, so nothing is ever inserted at runtime except the
 * copies of the site that the second and third layers carry (see loader.js,
 * scenes()) — and those go in while the lit logo is still up, long before
 * anything moves. The third layer's colour is on a fill of its own rather than on
 * the layer, so that the fill can be cut open over the real wordmark. $args:
 *   stage  true for the dashboard's preview box: no id, absolutely positioned in
 *          its parent, shown regardless of <html>'s data-fc-loader.
 */
function fc_loader_markup(array $s, array $args = []): string {
    $stage = !empty($args['stage']);
    $c     = fc_loader_colours($s);
    $off   = fc_loader_logo((string) $s['logo_off']);
    $on    = fc_loader_logo((string) $s['logo_on']);
    $ratio = fc_loader_ratio($off, $on);

    $style = sprintf(
        '--fcl-l1:%s;--fcl-l2:%s;--fcl-l3:%s;--fcl-w:%dpx;--fcl-w-m:%dpx;--fcl-ratio:%s;'
        . '--fcl-glow:%s;--fcl-glow-size:%dpx;--fcl-glow-c:%s;--fcl-origin:%s;--fcl-failsafe:%dms',
        $c['layer1'], $c['layer2'], $c['layer3'],
        (int) $s['size_desktop'], (int) $s['size_mobile'],
        fc_loader_css_num($ratio),
        fc_loader_css_num($s['glow'] / 100, 2),
        (int) $s['glow_size'],
        $s['glow_colour'] !== '' ? $s['glow_colour'] : '#FFFFFF',
        $s['bounce_origin'] === 'centre' ? '50% 50%' : '50% 100%',
        fc_loader_failsafe_ms($s) + 2000
    );

    return sprintf(
        '<div%s class="fc-loader" aria-hidden="true" data-fc-loader-root%s data-light="off" data-glow="%s" style="%s">'
            . '<div class="fc-loader__layer fc-loader__layer--3" data-fcl-layer3><div class="fc-loader__fill" data-fcl-fill></div><div class="fc-loader__scene" data-fcl-scene></div></div>'
            . '<div class="fc-loader__layer fc-loader__layer--2" data-fcl-layer2><div class="fc-loader__scene" data-fcl-scene></div></div>'
            . '<div class="fc-loader__layer fc-loader__layer--1" data-fcl-layer1>'
                . '<div class="fc-loader__logo" data-fcl-logo>'
                    . '<div class="fc-loader__bounce" data-fcl-bounce>'
                        . '<span class="fc-loader__glow"></span>%s%s'
                    . '</div>'
                . '</div>'
            . '</div>'
        . '</div>',
        $stage ? '' : ' id="fc-loader"',
        $stage ? ' data-fc-loader-stage' : '',
        esc_attr($s['glow_style']),
        esc_attr($style),
        fc_loader_art($off, 'off', (string) $s['tint_off']),
        fc_loader_art($on, 'on', (string) $s['tint_on'])
    );
}

/**
 * The overlay's CSS — the front end prints it in <head>, the dashboard attaches
 * it to its own page for the preview stage.
 *
 * Every box property is set explicitly on every element. Tailwind's Preflight
 * arrives later, inside a cascade layer, and would otherwise restyle the logo's
 * <img> mid-animation; these rules are unlayered, so they win. Colours are
 * literals carried in custom properties set inline by fc_loader_markup(), because
 * the theme's --color-* variables do not exist until the Tailwind runtime has
 * compiled them.
 *
 * The swipe is a clip on each layer, written frame by frame by
 * assets/loader/loader.js (exit()); nothing about it is in this CSS.
 *
 * NOTHING HERE MAY PUT THE OVERLAY ON A LAYER OF ITS OWN: no will-change, no 3D
 * transform, no animation of opacity or transform — not even one waiting out a
 * delay, which the browser composites from the start. Type on a layer of the
 * graphics card's is smoothed differently from type on the page (greyscale
 * against ClearType on a Windows screen), and the copies of the top bar and the
 * wordmark would come out a shade lighter than the real ones.
 */
function fc_loader_css(): string {
    return <<<'CSS'
.fc-loader { display: none; }
html[data-fc-loader="on"] #fc-loader,
.fc-loader[data-fc-loader-stage] { display: block; }
/* The loader's OWN elements only — never the copies of the site's top bar and
   wordmark inside .fc-loader__scene, which must keep the site's styling (these
   rules are unlayered, so they would beat every Tailwind utility on them). */
.fc-loader, .fc-loader__layer, .fc-loader__fill, .fc-loader__scene, .fc-loader__logo,
.fc-loader__bounce, .fc-loader__glow, .fc-loader__art {
    margin: 0; padding: 0; border: 0; box-sizing: border-box;
    line-height: 0; max-width: none; min-width: 0; float: none;
    font: inherit; color: inherit; background-clip: border-box;
}
.fc-loader {
    position: fixed; top: 0; right: 0; bottom: 0; left: 0;
    z-index: 2147483000;
    overflow: hidden; isolation: isolate;
    /* No cursor of its own: the site's (FOSSCOMM → Cursor) carries on over it. */
    pointer-events: auto; touch-action: none;
    user-select: none; -webkit-user-select: none; -webkit-tap-highlight-color: transparent;
    /* The CSS safety net: if the scripts never finish — a fatal error, a script
       blocker — this hides the overlay anyway. Delays are untouched by the
       theme's reduced-motion rule, so it fires there too. Visibility only, never
       opacity: see the note above. */
    animation: fc-loader-failsafe 1ms step-end var(--fcl-failsafe, 30000ms) forwards;
}
@keyframes fc-loader-failsafe { to { visibility: hidden; pointer-events: none; } }
.fc-loader[data-fc-loader-stage] { position: absolute; z-index: 1; animation: none; }

/* Three plain layers, stacked: the blue on top (with the logo), the white under
   it, and blue again under that, over the site. Each is wiped away by a clip, so
   nothing any of them carries ever moves: the top layer's edge passes over the
   logo rather than the logo riding along with it. The third layer's colour is on
   its fill, which is cut open over the real wordmark (loader.js openOver()). */
.fc-loader__layer {
    position: absolute; display: block; top: 0; left: 0;
    width: 100%; height: 100%; overflow: hidden;
}
.fc-loader__layer--1 { z-index: 3; background: var(--fcl-l1, #0033FF); }
.fc-loader__layer--2 { z-index: 2; background: var(--fcl-l2, #FAFAF7); }
.fc-loader__layer--3 { z-index: 1; background: transparent; }
.fc-loader__fill {
    position: absolute; display: block; top: 0; left: 0;
    width: 100%; height: 100%; background: var(--fcl-l3, #0033FF);
}

/* What a layer shows of the site before the site itself: copies of the top bar
   and the wordmark, placed exactly over the real ones (loader.js scenes()). */
.fc-loader__scene { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; overflow: visible; }
.fc-loader__scene > * { pointer-events: none !important; }

/* The logo: centred without a transform, so the transform is free for the
   counter-parallax on the way out (here) and the squash (on .fc-loader__bounce). */
.fc-loader__logo {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 2;
    display: flex; align-items: center; justify-content: center;
}
.fc-loader[data-logo="hidden"] .fc-loader__logo { visibility: hidden; }
.fc-loader__bounce {
    position: relative; display: block;
    width: var(--fcl-w, 220px); height: auto; aspect-ratio: var(--fcl-ratio, 1);
    transform-origin: var(--fcl-origin, 50% 100%);
}
@media (max-width: 1023.98px) {
    .fc-loader:not([data-fc-loader-stage]) .fc-loader__bounce { width: var(--fcl-w-m, 140px); }
}
/* The two logos (fc_loader_art()): the file as it is, its shape filled with a
   chosen colour, or the placeholder square. */
.fc-loader__art {
    position: absolute; top: 0; left: 0; display: block; width: 100%; height: 100%;
    pointer-events: none;
}
.fc-loader__art[data-mode="img"] {
    background: var(--src) center / contain no-repeat;
}
.fc-loader__art[data-mode="mask"] {
    background: var(--tint, #fff);
    -webkit-mask: var(--src) center / contain no-repeat;
    mask: var(--src) center / contain no-repeat;
}
.fc-loader__art[data-mode="ph"] { background: var(--tint, #000); }
.fc-loader [data-fcl-on] { opacity: 0; }
.fc-loader[data-light="on"] [data-fcl-on] { opacity: 1; }
.fc-loader[data-light="on"] [data-fcl-off] { opacity: 0; }

/* The shine, when lit — strength, size, colour and style all from the dashboard.
   "bloom": a soft light behind the logo, reaching --fcl-glow-size past its edge.
   "neon": a glow that hugs the logo's own shape. "both": the two together. */
.fc-loader__glow {
    position: absolute; display: block; left: 50%; top: 50%;
    width: calc(100% + 2 * var(--fcl-glow-size, 60px)); height: calc(100% + 2 * var(--fcl-glow-size, 60px));
    transform: translate(-50%, -50%); pointer-events: none; opacity: 0;
    background: radial-gradient(closest-side,
        color-mix(in srgb, var(--fcl-glow-c, #fff) 62%, transparent),
        color-mix(in srgb, var(--fcl-glow-c, #fff) 20%, transparent) 55%,
        transparent);
}
.fc-loader[data-light="on"]:not([data-glow="neon"]) .fc-loader__glow { opacity: var(--fcl-glow, 0.4); }
.fc-loader[data-light="on"][data-glow="neon"] [data-fcl-on],
.fc-loader[data-light="on"][data-glow="both"] [data-fcl-on] {
    filter: drop-shadow(0 0 calc(var(--fcl-glow-size, 60px) * 0.25) color-mix(in srgb, var(--fcl-glow-c, #fff) calc(var(--fcl-glow, 0.4) * 100%), transparent))
            drop-shadow(0 0 calc(var(--fcl-glow-size, 60px) * 0.08) color-mix(in srgb, var(--fcl-glow-c, #fff) calc(var(--fcl-glow, 0.4) * 100%), transparent));
}

/* While it is up: the page underneath must not rubber-band on a phone, and the
   mascot is drawn ABOVE the overlay — he takes the logo's place on it. */
html[data-fc-loader="on"] { overscroll-behavior: none; }
html[data-fc-loader="on"] #fosscomm-mascot { z-index: 2147483001; }
CSS;
}

/* ─────────────────────────────────────────────────────────────────────────────
   THE PAYLOAD
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * What the browser runs on: the option as stored, with colours resolved, the
 * mascot hand-over only when there is a mascot to hand over to, and the few
 * facts the scripts cannot work out for themselves. Read by FCLoader.readConfig(),
 * the one place in JS that knows this shape.
 */
function fc_loader_js_config(array $s, array $extra = []): array {
    $mascot_on = function_exists('fc_mascot_is_enabled') && fc_mascot_is_enabled();
    $payload = array_merge($s, fc_loader_colours($s), [
        'mascot'     => !empty($s['mascot']) && $mascot_on,
        'breakpoint' => FC_LOADER_BREAKPOINT,
        'opening_cap_ms' => FC_LOADER_OPENING_CAP,
        'preview'    => fc_loader_is_preview(),
        'slow_ms'    => fc_loader_is_preview() ? fc_loader_preview_slow_ms() : 0,
    ], $extra);
    // The logos travel in the markup; the script never needs them twice.
    unset($payload['logo_off'], $payload['logo_on']);
    return $payload;
}

/* ─────────────────────────────────────────────────────────────────────────────
   OUTPUT
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * <head>, priority 1: the CSS, and the script that decides — before anything
 * paints — whether the overlay is up.
 *
 * window.fcLoader is the loader's STICKY state. The mascot's boot module and the
 * front-end sequence both read it; events are not sticky, and the mascot's module
 * can run before or after any given moment of the sequence. finish() lives here
 * so that the script failing later cannot take the way out down with it: the
 * timer below calls it if nothing else ever does.
 */
add_action('wp_head', 'fc_loader_head', 1);
function fc_loader_head() {
    if (!fc_loader_should_show()) return;
    $s = fc_loader_settings();

    printf("<style id=\"fc-loader-css\">\n%s\n</style>\n", fc_loader_css());

    $session = ($s['repeat'] === 'session' && !fc_loader_is_preview()) ? 'true' : 'false';
    $script = sprintf(<<<'JS'
(function (w, d) {
    var h = d.documentElement, session = %s;
    var L = w.fcLoader = { state: 'loading' };
    try {
        if (session && w.sessionStorage.getItem('fc-loader-seen') === '1') {
            L.state = 'skip';
            h.setAttribute('data-fc-loader', 'skip');
            return;
        }
    } catch (e) {}
    h.setAttribute('data-fc-loader', 'on');
    L.finish = function (why) {
        if (L.state === 'done') return;
        L.state = 'done';
        clearTimeout(L.failsafe);
        try { if (L.cleanup) L.cleanup(); } catch (e) {}
        if (why === 'failsafe') h.setAttribute('data-fc-loader', 'off');
        else h.removeAttribute('data-fc-loader');
        try { if (session) w.sessionStorage.setItem('fc-loader-seen', '1'); } catch (e) {}
        var ev;
        try { ev = new CustomEvent('fc:loader-done'); } catch (e) { ev = d.createEvent('Event'); ev.initEvent('fc:loader-done', false, false); }
        d.dispatchEvent(ev);
    };
    L.failsafe = setTimeout(function () { L.finish('failsafe'); }, %d);
})(window, document);
JS, $session, fc_loader_failsafe_ms($s));

    wp_print_inline_script_tag($script, ['id' => 'fc-loader-head']);
}

/**
 * <body>, first thing: the overlay, the payload, and the two scripts, inline, so
 * they run the moment the overlay exists.
 */
add_action('wp_body_open', 'fc_loader_body', 0);
function fc_loader_body() {
    if (!fc_loader_should_show()) return;
    $s = fc_loader_settings();

    echo fc_loader_markup($s), "\n";
    wp_print_inline_script_tag(
        'window.FC_LOADER = ' . wp_json_encode(fc_loader_js_config($s)) . ';',
        ['id' => 'fc-loader-config']
    );
    foreach (['loader', 'boot'] as $name) {
        $file = FC_THEME_DIR . '/assets/loader/' . $name . '.js';
        $code = is_readable($file) ? (string) file_get_contents($file) : '';
        if ($code !== '') wp_print_inline_script_tag($code, ['id' => 'fc-loader-' . $name]);
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   PREVIEW — the dashboard's "real site" preview
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * The dashboard shows the loading screen on the actual front page in an iframe,
 * running the settings on its form — saved or not. Those settings travel in the
 * URL (base64url JSON in `fc_loader_preview`) and are honoured only for a user
 * who could have saved them anyway, with a nonce, and only on the front end.
 * Anything else about the request is ignored and the page renders normally.
 */
function fc_loader_preview_raw(): ?array {
    static $memo = false;
    if ($memo !== false) return $memo;

    if (empty($_GET['fc_loader_preview']) || is_admin()) return $memo = null;
    // Who is asking is not known before init; answer "no" without remembering it.
    if (!did_action('init')) return null;
    if (!current_user_can('manage_options')) return $memo = null;

    $nonce = isset($_GET['_fcl']) ? sanitize_text_field(wp_unslash((string) $_GET['_fcl'])) : '';
    if (!wp_verify_nonce($nonce, 'fc_loader_preview')) return $memo = null;

    $json = fc_loader_b64url_decode((string) wp_unslash($_GET['fc_loader_preview']));
    $data = $json !== '' ? json_decode($json, true) : null;
    return $memo = is_array($data) ? $data : null;
}

function fc_loader_is_preview(): bool {
    return fc_loader_preview_raw() !== null;
}

/** The dashboard's "slow load" button: seconds of pretend loading, 0–20. */
function fc_loader_preview_slow_ms(): int {
    $s = isset($_GET['fc_loader_slow']) ? (float) wp_unslash($_GET['fc_loader_slow']) : 0.0;
    return (int) round(max(0.0, min(20.0, $s)) * 1000);
}

/** base64url, as the dashboard's script writes it: no padding, - and _ for + and /. */
function fc_loader_b64url_decode(string $s): string {
    $s = strtr(trim($s), '-_', '+/');
    if (strlen($s) % 4) $s .= str_repeat('=', 4 - strlen($s) % 4);
    $out = base64_decode($s, true);
    return $out === false ? '' : $out;
}

function fc_loader_b64url_encode(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/**
 * The front page with a fresh preview nonce; the dashboard's script appends the
 * settings and the slow-load seconds to it.
 */
function fc_loader_preview_base(): string {
    return add_query_arg(['_fcl' => wp_create_nonce('fc_loader_preview')], home_url('/'));
}

/** The whole preview URL for a given raw option, built in PHP. */
function fc_loader_preview_url(array $raw, int $slow_s = 0): string {
    return add_query_arg([
        'fc_loader_preview' => fc_loader_b64url_encode((string) wp_json_encode($raw)),
        'fc_loader_slow'    => max(0, min(20, $slow_s)),
    ], fc_loader_preview_base());
}

/**
 * No admin bar inside the preview: it is the visitor's view being previewed.
 * Evaluated lazily, when WordPress first asks — by then the user is known.
 */
add_filter('show_admin_bar', function ($show) {
    return fc_loader_is_preview() ? false : $show;
});
