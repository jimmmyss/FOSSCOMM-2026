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
require_once FC_THEME_DIR . '/inc/attributions.php';
require_once FC_THEME_DIR . '/assets/mascot/mascot.php';

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

/**
 * Version every theme asset by its own modification time.
 *
 * FC_THEME_VERSION is a hand-edited literal, so `?ver=` only changes when
 * somebody remembers to bump it — and the note on that constant records this
 * biting once already, when it sat at 0.5.3 while style.css said 3.0.0 and every
 * file shipped in between stayed in visitors' caches. Editing a file is the
 * thing that should invalidate it, so mtime is the version.
 *
 * A filter rather than 18 edited call sites: it covers every enqueue that exists
 * now and every one added later, and cannot be forgotten at the point of use.
 * Theme files only — CDN URLs are left exactly as they are.
 */
add_filter('script_loader_src', 'fc_asset_version_src', 20);
add_filter('style_loader_src', 'fc_asset_version_src', 20);
function fc_asset_version_src($src) {
    if (!is_string($src) || strpos($src, FC_THEME_URI) !== 0) {
        return $src;
    }
    $rel = parse_url(substr($src, strlen(FC_THEME_URI)), PHP_URL_PATH);
    if (!$rel) {
        return $src;
    }
    $file = FC_THEME_DIR . $rel;
    if (!is_file($file)) {
        return $src;
    }
    return add_query_arg('ver', (string) filemtime($file), $src);
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
        'fc-app',
        FC_THEME_URI . '/assets/dist/fc.js',
        [],
        FC_THEME_VERSION,
        true
    );
    // MapLibre GL JS (v5 — globe projection) + the venue map island. Loaded from
    // CDN, matching the Tailwind-from-CDN pattern above. Map data is OpenFreeMap
    // (no API key, no usage cap); the recoloured style is built in venue-map.js.
    wp_enqueue_style(
        'maplibre-gl',
        'https://unpkg.com/maplibre-gl@5/dist/maplibre-gl.css',
        [],
        '5'
    );
    wp_enqueue_script(
        'maplibre-gl',
        'https://unpkg.com/maplibre-gl@5/dist/maplibre-gl.js',
        [],
        '5',
        true
    );
    wp_enqueue_script(
        'fc-venue-map',
        FC_THEME_URI . '/assets/venue-map.js',
        ['maplibre-gl'],
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
    // Speakers row: decides at runtime whether the cards need a moving belt at
    // all, and drives the drag/arrows if they do. Inert when the section is
    // absent — it looks for [data-fc-speakers] and stops if there is none.
    wp_enqueue_script(
        'fc-speakers',
        FC_THEME_URI . '/assets/speakers-carousel.js',
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

/*
 * The colour the phone's browser chrome is tinted, kept as a literal because a
 * <meta> tag cannot read a CSS custom property. It MUST match --color-accent in
 * fc_inline_theme_config() below; change one and change the other.
 *
 * One value, not a pair: the chrome is blue on every page and at every scroll
 * position, and nothing swaps it at runtime. See header.php.
 */
const FC_CHROME_ACCENT = '#0033FF';   // matches --color-accent

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

/**
 * Custom site cursors (FOSSCOMM → Appearance). `cursor` is an inherited CSS
 * property, so setting it on <html> cascades everywhere; the pointer variant
 * (or the default cursor when none is set) overrides it on interactive
 * elements, and the two grab variants override it again on anything draggable
 * — the mascot and the venue globe — with the "grabbing" (closed hand) one
 * winning over "grab" (open hand) while something is actually being held.
 * Emitted only when an image is uploaded — otherwise the site keeps the system
 * cursor. Front-end only (wp_head), so the WP admin is untouched.
 *
 * An optional `cursor_scale` MULTIPLIES each file's own size (×2, ×3, …); see
 * fc_cursor_css_value() for why that needs an SVG wrapper rather than plain
 * width/height.
 *
 * Every value is also published as a CSS custom property, because an inline
 * `style.cursor` written by JS beats any stylesheet rule — code that has to set
 * a cursor imperatively (assets/venue-map.js on the map pins) reads the
 * variable instead of hard-coding `pointer`.
 */
add_action('wp_head', 'fc_inline_cursor_css', 6);
function fc_inline_cursor_css() {
    // At a scale > 1 the CSS embeds the cursor files as base64, so it is worth
    // caching rather than rebuilding per request. FOSSCOMM → Appearance drops
    // this transient whenever the settings are saved.
    $css = get_transient('fc_cursor_css_v6');
    if ($css === false) {
        $css = fc_build_cursor_css();
        set_transient('fc_cursor_css_v6', $css, DAY_IN_SECONDS);
    }
    if (!is_string($css) || $css === '') return;
    echo "\n<style id=\"fc-cursor\">" . $css . "</style>\n";
}

function fc_build_cursor_css(): string {
    $a = get_option('fc_appearance', []);
    if (!is_array($a)) return '';

    $scale = (int) ($a['cursor_scale'] ?? 1);
    $scale = max(1, min(8, $scale));

    // Pointer falls back to the default cursor, and "grabbing" (closed hand)
    // falls back to "grab" (open hand) — so uploading a single hand still gives
    // both states something to show. "grab" itself does NOT fall back to the
    // default: with no image the mascot and the globe keep the browser's own
    // grab/grabbing pair, which is what they had before this setting existed.
    $slots = [
        'cur'          => (string) ($a['cursor'] ?? ''),
        'cur-pointer'  => (string) ($a['cursor_pointer'] ?? ''),
        'cur-grab'     => (string) ($a['cursor_grab'] ?? ''),
        'cur-grabbing' => (string) ($a['cursor_grabbing'] ?? ''),
    ];
    if ($slots['cur-pointer']  === '') $slots['cur-pointer']  = $slots['cur'];
    if ($slots['cur-grabbing'] === '') $slots['cur-grabbing'] = $slots['cur-grab'];

    // The keyword each slot must end with. A `cursor` value that names an image
    // is only valid if a keyword follows it, so the keyword is baked INTO the
    // variable rather than appended at each use site.
    //
    // That matters more than it looks. `--fc-cur-grab: url(…) 8 8` used from a
    // template as `cursor: var(--fc-cur-grab, grab)` expands to `url(…) 8 8`
    // with nothing after it — invalid, so the whole declaration is dropped and
    // the element silently inherits some other cursor. And a var() that fails is
    // "invalid at computed-value time", which does NOT fall back to an earlier
    // declaration in the cascade, so the usual two-declaration trick cannot
    // rescue it either. Only the variable carrying its own keyword works in both
    // cases: defined, it is `url(…) 8 8, grab`; undefined, the var() fallback
    // supplies a bare `grab`.
    $keywords = [
        'cur'          => 'auto',
        'cur-pointer'  => 'pointer',
        'cur-grab'     => 'grab',
        'cur-grabbing' => 'grabbing',
    ];

    // Which option field holds each slot's click point.
    $hot_keys = [
        'cur'          => 'cursor',
        'cur-pointer'  => 'cursor_pointer',
        'cur-grab'     => 'cursor_grab',
        'cur-grabbing' => 'cursor_grabbing',
    ];

    $vars = [];
    foreach ($slots as $name => $url) {
        if ($url === '') continue;
        $v = fc_cursor_css_value($url, $scale, fc_cursor_hotspot($a, $hot_keys[$name]));
        if ($v !== '') $vars[$name] = $v . ',' . $keywords[$name];
    }
    if (!$vars) return '';

    $decl = '';
    foreach ($vars as $name => $v) $decl .= '--fc-' . $name . ':' . $v . ';';
    $css = 'html{' . $decl . '}';

    if (isset($vars['cur'])) {
        $css .= 'html{cursor:var(--fc-cur)}';
    }

    // Every `html` prefix here is load-bearing, and so is the length of the
    // list. Section templates print their <style> INSIDE the body, which is
    // after wp_head — so an equal-specificity `.fc-year-btn{cursor:pointer}` in
    // a section was winning on document order and quietly showing the system
    // hand instead of this one. The prefix takes those rules out of the running.
    //
    // The list covers what is genuinely clickable rather than just links and
    // buttons: form controls, anything given a button role, and anything carrying
    // an inline handler. Theme code that has to set a cursor itself should use
    // `var(--fc-cur-pointer, pointer)` rather than a bare `pointer`, so the two
    // can never disagree.
    if (isset($vars['cur-pointer'])) {
        $css .= 'html a[href],html button,html [role="button"],html [role="link"],'
              . 'html [role="tab"],html [role="menuitem"],html summary,html label,'
              . 'html select,html input[type="submit"],html input[type="button"],'
              . 'html input[type="reset"],html input[type="checkbox"],'
              . 'html input[type="radio"],html input[type="file"],html [onclick],'
              . 'html .fc-nav-link,html .fc-year-btn,html .fc-sched-select,'
              . 'html .fc-topbar-brand,html .fc-venue-title-link,'
              . 'html .cursor-pointer{cursor:var(--fc-cur-pointer)}';
    }

    // The `html` prefix wins against mascot.css's own `cursor: grab` on
    // otherwise identical specificity, regardless of stylesheet order. The
    // MapLibre selectors are the globe in the venue section: MapLibre ships
    // `.maplibregl-canvas-container.maplibregl-interactive{cursor:grab}` and an
    // `:active` grabbing variant, and the <canvas> inherits from that container.
    // [data-fcm-hot] rather than the bare sprite: the mascot sets that from a
    // per-pixel hit test, so the open hand appears over his drawing and not over
    // the transparent corners of his sprite box.
    if (isset($vars['cur-grab'])) {
        $css .= ':root #fosscomm-mascot[data-fcm-hot] .fcm-sprite,'
              . ':root .cursor-grab,'
              . ':root [data-fc-grab],'
              . ':root .maplibregl-canvas-container.maplibregl-interactive,'
              . ':root .maplibregl-ctrl-group button.maplibregl-ctrl-compass'
              . '{cursor:var(--fc-cur-grab)}';
    }

    // Closed hand — emitted AFTER the open hand so an equal-specificity tie goes
    // to grabbing, which is the state that should win.
    //
    // The mascot is carried by two independent selectors on purpose: setState()
    // only writes data-state="held" when a held clip is configured, so the
    // html[data-fc-grabbing] attribute mascot.js sets for the whole life of a
    // drag is the one that always fires. Each mascot selector has to out-specify
    // the plain `html #fosscomm-mascot .fcm-sprite` open hand above (1,1,1) —
    // the bare `*` at (0,1,1) does not, which is why the sprite gets its own
    // entry rather than riding the catch-all. Same for the map container, which
    // MapLibre matches at (0,2,0).
    // `:root`, not `html`, and that is the whole fix for the closed hand.
    //
    // mascot.css carries `html[data-fc-grabbing] #fosscomm-mascot .fcm-sprite`
    // itself — the SAME selector this block used, at (1,2,1). A tie, and
    // stylesheets print at wp_head priority 8 while this inline block prints at
    // 6, so mascot.css was always later and always won: the native grabbing
    // cursor beat the uploaded sprite every time. The `html` prefix was added to
    // out-rank mascot.css's *grab* rule, which has no prefix; nobody noticed the
    // *grabbing* rule already had one.
    //
    // `:root` matches the same element but is a pseudo-class, so it scores in the
    // class column instead of the element one: (1,3,0) against (1,2,1), which
    // wins on the second component and cannot be undone by source order. The
    // [data-fcm-hot] variants stay as well, for the same reason one step up.
    if (isset($vars['cur-grabbing'])) {
        $css .= ':root[data-fc-grabbing] #fosscomm-mascot[data-fcm-hot] .fcm-sprite,'
              . ':root #fosscomm-mascot[data-state="held"][data-fcm-hot] .fcm-sprite,'
              . ':root #fosscomm-mascot[data-state="fall"][data-fcm-hot] .fcm-sprite,'
              . ':root #fosscomm-mascot[data-state="held"] .fcm-sprite,'
              . ':root #fosscomm-mascot[data-state="fall"] .fcm-sprite,'
              . ':root[data-fc-grabbing] #fosscomm-mascot .fcm-sprite,'
              . ':root .cursor-grabbing,'
              . ':root [data-fc-grabbing],'
              . ':root .maplibregl-canvas-container.maplibregl-interactive:active,'
              . ':root .maplibregl-ctrl-group button.maplibregl-ctrl-compass:active,'
              . ':root[data-fc-grabbing] .maplibregl-canvas-container.maplibregl-interactive'
              . '{cursor:var(--fc-cur-grabbing)}'
              . ':root[data-fc-grabbing],:root[data-fc-grabbing] *'
              . '{cursor:var(--fc-cur-grabbing)}';
    }

    return $css;
}

/**
 * Build one CSS cursor value: `url("…") 0 0`.
 *
 * At $scale <= 1 that is just the image URL and the browser uses the file's own
 * dimensions. Above that the image is wrapped in an SVG $scale times its
 * intrinsic size, because CSS cannot resize a cursor bitmap on its own —
 * `cursor: url(x)` ignores width/height entirely.
 *
 * The wrapper has to carry the image INLINE (base64) rather than reference it
 * by URL: a data: URI document gets an opaque origin, and browsers refuse to
 * load external resources from inside one, so an <image href="https://…">
 * would silently produce an empty cursor.
 *
 * Falls back to the unscaled URL whenever the file can't be inlined or measured
 * — a correct cursor at the wrong size beats no cursor at all.
 */
/**
 * The click point a cursor starts with before anyone touches the setting.
 *
 * The pointing hand's fingertip sits one pixel in and five down in the artwork
 * it was drawn for; the rest default to the corner, which is where a browser
 * puts a cursor with no hotspot of its own. These are only STARTING values —
 * FOSSCOMM → Cursor makes each one editable, because the right answer depends
 * entirely on the images uploaded, and cursors whose click points disagree
 * visibly jump as the pointer moves between them.
 *
 * Lives here rather than in the admin page because the front end has to read it
 * too, and inc/admin is only required when is_admin().
 */
function fc_cursor_hotspot_default(string $base): array {
    // The SAME point for all four, deliberately. Cursors whose click points
    // disagree appear to JUMP as the pointer moves between them, because the
    // browser pins that pixel to the mouse and hangs the image off it — so
    // identical values are what makes the swap invisible. Kept as a table rather
    // than one constant because each is still editable per cursor, for artwork
    // that genuinely does not line up.
    $defaults = [
        'cursor'          => [1, 5],
        'cursor_pointer'  => [1, 5],
        'cursor_grab'     => [1, 5],
        'cursor_grabbing' => [1, 5],
    ];
    return $defaults[$base] ?? [1, 5];
}

/** The saved click point for one cursor, falling back to its default. */
function fc_cursor_hotspot(array $settings, string $base): array {
    $default = fc_cursor_hotspot_default($base);
    $x = $settings[$base . '_hx'] ?? null;
    $y = $settings[$base . '_hy'] ?? null;
    return [
        ($x === null || $x === '') ? $default[0] : max(0, (int) $x),
        ($y === null || $y === '') ? $default[1] : max(0, (int) $y),
    ];
}

/**
 * @param array $hotspot The click point, in the SOURCE image's own pixels —
 *                       [x, y] from its top-left. Scaled along with the image,
 *                       including through the 128px shrink, so it keeps
 *                       pointing at the same drawn pixel at every multiplier.
 */
function fc_cursor_css_value(string $url, int $scale, array $hotspot = [0, 0]): string {
    $url = esc_url_raw($url);
    if ($url === '') return '';
    $hx = max(0, (int) ($hotspot[0] ?? 0));
    $hy = max(0, (int) ($hotspot[1] ?? 0));

    $plain = 'url("' . $url . '") ' . $hx . ' ' . $hy;
    if ($scale <= 1) return $plain;

    $src = fc_cursor_inline_data($url);
    // A multiplier is meaningless without the source's own size, so an image we
    // can't measure stays at 1:1 rather than guessing a square.
    if (empty($src['data']) || empty($src['w']) || empty($src['h'])) return $plain;

    $w = (int) max(1, round($src['w'] * $scale));
    $h = (int) max(1, round($src['h'] * $scale));

    // Browsers ignore custom cursors past ~128px in either direction. Shrink the
    // whole thing back to fit instead of letting the cursor vanish.
    if ($w > 128 || $h > 128) {
        $f = min(128 / $w, 128 / $h);
        $w = (int) max(1, round($w * $f));
        $h = (int) max(1, round($h * $f));
    }

    // The hotspot rides the same transform the image did. Expressed against the
    // FINAL size rather than the multiplier, so it survives the 128px shrink
    // above as well — the click point stays on the same drawn pixel whatever the
    // size setting. Clamped inside the image, since a hotspot outside it is
    // ignored and the browser falls back to the top-left corner.
    $hx = min($w - 1, (int) round($hx * ($w / $src['w'])));
    $hy = min($h - 1, (int) round($hy * ($h / $src['h'])));

    $d   = esc_attr($src['data']);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"'
         . ' width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
         . '<image xlink:href="' . $d . '" href="' . $d . '"'
         . ' width="' . $w . '" height="' . $h . '"'
         . ' image-rendering="pixelated" preserveAspectRatio="none"/></svg>';

    return 'url("data:image/svg+xml;base64,' . base64_encode($svg) . '") ' . $hx . ' ' . $hy;
}

/**
 * The size at which browsers will honour a custom cursor ANYWHERE on the page.
 *
 * Chromium refuses to draw a custom cursor bigger than this when the pointer is
 * near a window edge, and silently uses the system one instead. It is an
 * anti-spoofing measure — a large cursor image can be used to paint fake browser
 * UI over the real thing — so it is deliberate behaviour, not a bug, and there
 * is no way to opt out of it from a page.
 *
 * The visible symptom is a cursor that works in the middle of the page and turns
 * native as you approach the bottom or the sides.
 */
const FC_CURSOR_EDGE_SAFE = 32;

/**
 * What a configured cursor will ACTUALLY be drawn at, and whether that is a size
 * browsers will use everywhere.
 *
 * Mirrors the decisions in fc_cursor_css_value() rather than re-deriving them,
 * so the admin cannot promise a size the front end does not produce.
 *
 * @return array{w:int,h:int,known:bool,edge_safe:bool,scaled:bool}
 */
function fc_cursor_render_info(string $url, int $scale): array {
    $out = ['w' => 0, 'h' => 0, 'known' => false, 'edge_safe' => true, 'scaled' => false];
    if (trim($url) === '') return $out;

    $src = fc_cursor_inline_data($url);
    if (empty($src['w']) || empty($src['h'])) {
        // Either unreadable or a .cur/.ico, which carries its own size. Both end
        // up on the plain-URL path at whatever the file's own size is.
        return $out;
    }

    $w = (int) $src['w'];
    $h = (int) $src['h'];

    if ($scale > 1) {
        $w = (int) max(1, round($w * $scale));
        $h = (int) max(1, round($h * $scale));
        if ($w > 128 || $h > 128) {
            $f = min(128 / $w, 128 / $h);
            $w = (int) max(1, round($w * $f));
            $h = (int) max(1, round($h * $f));
        }
        $out['scaled'] = true;
    }

    $out['w'] = $w;
    $out['h'] = $h;
    $out['known'] = true;
    $out['edge_safe'] = ($w <= FC_CURSOR_EDGE_SAFE && $h <= FC_CURSOR_EDGE_SAFE);
    return $out;
}

/**
 * Read an uploads-dir image and return ['data' => data-URI, 'w' => int, 'h' => int],
 * or [] when that isn't possible.
 *
 * Deliberately refuses anything resolving outside wp_upload_dir() (realpath
 * containment), anything that isn't a raster/SVG image, and anything large
 * enough that inlining it on every page view would be a bad trade. .cur/.ico
 * carry their own hotspot and size table and don't survive SVG wrapping, so
 * they're excluded and fall back to the unscaled URL.
 */
function fc_cursor_inline_data(string $url): array {
    $uploads = wp_get_upload_dir();
    $baseurl = (string) ($uploads['baseurl'] ?? '');
    $basedir = (string) ($uploads['basedir'] ?? '');
    if ($baseurl === '' || $basedir === '') return [];

    // Compare scheme-relative, so an http/https mismatch still resolves.
    $rel_url  = (string) preg_replace('#^https?:#', '', $url);
    $rel_base = (string) preg_replace('#^https?:#', '', $baseurl);
    if (strpos($rel_url, $rel_base) !== 0) return [];

    $rel = ltrim(substr($rel_url, strlen($rel_base)), '/');
    if (($q = strpos($rel, '?')) !== false) $rel = substr($rel, 0, $q);
    $rel = rawurldecode($rel);
    if ($rel === '' || strpos($rel, '..') !== false) return [];

    $path = realpath($basedir . '/' . $rel);
    $root = realpath($basedir);
    if ($path === false || $root === false || strpos($path, $root) !== 0) return [];
    if (!is_file($path) || !is_readable($path)) return [];
    if (filesize($path) > 128 * 1024) return [];

    // Explicit map rather than the site's allowed-upload mimes: SVG is only in
    // that list while an admin is logged in (see inc/admin/pages/appearance.php),
    // so relying on it would leave anonymous visitors with an unscaled cursor.
    $mime = (string) (wp_check_filetype($path, [
        'png'      => 'image/png',
        'gif'      => 'image/gif',
        'jpg|jpeg' => 'image/jpeg',
        'webp'     => 'image/webp',
        'svg'      => 'image/svg+xml',
    ])['type'] ?? '');
    if ($mime === '') return [];

    $bytes = file_get_contents($path);
    if ($bytes === false || $bytes === '') return [];

    $w = 0;
    $h = 0;
    if ($mime === 'image/svg+xml') {
        list($w, $h) = fc_svg_intrinsic_size($bytes);
    } else {
        $dim = getimagesize($path);
        if (is_array($dim) && !empty($dim[0]) && !empty($dim[1])) {
            $w = (int) $dim[0];
            $h = (int) $dim[1];
        }
    }

    return [
        'data' => 'data:' . $mime . ';base64,' . base64_encode($bytes),
        'w'    => $w,
        'h'    => $h,
    ];
}

/**
 * Intrinsic pixel size of an SVG, as [w, h], or [0, 0] when it has none.
 *
 * getimagesize() can't read SVG, and the size matters here because the scale
 * setting is a multiplier of it. Prefers width/height, falls back to the
 * viewBox — including the half-and-half case where only one of the two is a
 * real length and the viewBox supplies the ratio for the other.
 */
function fc_svg_intrinsic_size(string $svg): array {
    if (!preg_match('/<svg\b[^>]*>/i', $svg, $m)) return [0, 0];
    $tag = $m[0];

    $w = fc_svg_length($tag, 'width');
    $h = fc_svg_length($tag, 'height');
    if ($w > 0 && $h > 0) return [(int) round($w), (int) round($h)];

    if (preg_match(
        '/\bviewBox\s*=\s*["\']\s*[-\d.eE]+[,\s]+[-\d.eE]+[,\s]+([-\d.eE]+)[,\s]+([-\d.eE]+)/i',
        $tag,
        $vb
    )) {
        $vw = (float) $vb[1];
        $vh = (float) $vb[2];
        if ($vw > 0 && $vh > 0) {
            if ($w > 0) return [(int) round($w), (int) max(1, round($w * $vh / $vw))];
            if ($h > 0) return [(int) max(1, round($h * $vw / $vh)), (int) round($h)];
            return [(int) round($vw), (int) round($vh)];
        }
    }

    return [0, 0];
}

/**
 * One SVG root length attribute in px, or 0.0 when it isn't one — percentages
 * are relative to a viewport the cursor doesn't have, and em/pt/… would need a
 * font context to resolve, so both are left to the viewBox instead of guessed.
 */
function fc_svg_length(string $tag, string $attr): float {
    // The lookbehind is what keeps `stroke-width` from answering for `width`.
    if (!preg_match('/(?<![-\w:])' . $attr . '\s*=\s*["\']([^"\']*)["\']/i', $tag, $m)) return 0.0;
    $v = trim($m[1]);
    if ($v === '' || !preg_match('/^([-\d.eE]+)\s*(?:px)?$/', $v, $n)) return 0.0;
    $f = (float) $n[1];
    return $f > 0 ? $f : 0.0;
}

add_action('after_switch_theme', 'fc_on_activate');
function fc_on_activate() {
    if (function_exists('fc_seed_initial_content')) {
        fc_seed_initial_content();
    }
}
