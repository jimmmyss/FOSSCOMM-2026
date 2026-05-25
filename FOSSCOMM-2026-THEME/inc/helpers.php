<?php
/**
 * Misc helpers reused across the theme.
 */
if (!defined('ABSPATH')) {
    exit;
}

function fc_get_event_start_iso() {
    $settings = get_option('fc_site_settings', []);
    return $settings['event_start'] ?? '2026-10-17T09:00:00+03:00';
}

/**
 * True when the request is the landing page (front-page.php).
 * Used by the status bar's "FOSSCOMM" brand link and the section-nav links
 * to decide whether to emit in-page hash links (#hero, #manifesto, …) or
 * full URLs back to home (home_url('/#hero'), etc) so the same chrome
 * works on /news/<slug>/ and /coc/.
 */
function fc_is_landing_page(): bool {
    if (get_query_var('fc_news_slug')) return false;
    if (get_query_var('fc_conduct'))  return false;
    return true;
}

/** Build the canonical link to a landing-page section. */
function fc_section_anchor_url(string $section_key): string {
    $key = ltrim($section_key, '#');
    if (fc_is_landing_page()) {
        return '#' . $key;
    }
    return home_url('/#' . $key);
}

/**
 * Normalised venue editions — the single source for the globe pins AND both
 * editions browsers (mobile sticky bar in template-parts/sections/venue.php,
 * desktop sidebar list in template-parts/partials/section-nav.php).
 *
 * Reads the Venue section's `editions` repeater, falling back to the legacy
 * fc_past_editions option. lat/lon must be set explicitly per row; rows whose
 * coordinates are missing or non-numeric come through with empty-string lat/lon
 * and get filtered out of the globe pins by the front-end (assets/dist/fc.js).
 * Sorted oldest → newest by year.
 *
 * @return array<int,array{year:int,city:string,lat:float|string,lon:float|string,url:string,current:bool}>
 */
function fc_venue_editions(): array {
    $venue    = get_option('fc_section_venue', []);
    $editions = (is_array($venue) && !empty($venue['editions']) && is_array($venue['editions']))
        ? $venue['editions']
        : [];
    if (empty($editions)) {
        $legacy = get_option('fc_past_editions', []);
        if (is_array($legacy) && !empty($legacy)) {
            $editions = $legacy;
        }
    }

    usort($editions, function ($a, $b) {
        return ((int) ($a['year'] ?? 0)) <=> ((int) ($b['year'] ?? 0));
    });

    $out = [];
    foreach ($editions as $ed) {
        if (empty($ed['year'])) continue;
        $rawLat = isset($ed['lat']) ? trim((string) $ed['lat']) : '';
        $rawLon = isset($ed['lon']) ? trim((string) $ed['lon']) : '';
        $out[] = [
            'year'    => (int)    ($ed['year'] ?? 0),
            'city'    => (string) ($ed['city'] ?? ''),
            'lat'     => is_numeric($rawLat) ? (float) $rawLat : '',
            'lon'     => is_numeric($rawLon) ? (float) $rawLon : '',
            'url'     => (string) ($ed['url']  ?? ''),
            'current' => !empty($ed['current']),
        ];
    }
    return $out;
}

/**
 * Sanitize an ASCII art block — strip HTML but preserve every space, newline
 * and blank line. NB: wp_strip_all_tags() ends with trim(), which would eat
 * leading blank lines and the indentation of the first row — fatal for art.
 */
function fc_sanitize_ascii($value) {
    $value = wp_check_invalid_utf8((string) $value);
    $value = preg_replace('@<(script|style)[^>]*?>.*?</\1>@si', '', $value);
    $value = strip_tags($value);
    // Normalise line endings only; indentation and blank lines are significant.
    return str_replace(["\r\n", "\r"], "\n", (string) $value);
}

/**
 * Pick one of two strings by current language. Falls back to the other when empty.
 */
function fc_pick(string $el, string $en): string {
    $lang = fc_current_lang();
    if ($lang === 'el') {
        return $el !== '' ? $el : $en;
    }
    return $en !== '' ? $en : $el;
}

/**
 * Pull a bilingual pair from an associative array using a base key.
 * E.g. fc_pair($payload, 'title') reads 'title_el' / 'title_en'.
 */
function fc_pair(array $data, string $base): string {
    $el = (string) ($data[$base . '_el'] ?? '');
    $en = (string) ($data[$base . '_en'] ?? '');
    return fc_pick($el, $en);
}

/**
 * Render an ASCII block safely.
 */
function fc_ascii_pre(string $content, string $extra_class = ''): string {
    return '<pre class="ascii ' . esc_attr($extra_class) . '">' . esc_html($content) . '</pre>';
}

/**
 * Global formatter for admin-written copy.
 * Escapes the string, then turns *text* segments into <span class="fc-accent">…</span>
 * so editors can highlight a word in any field by wrapping it with asterisks.
 *
 * Use this wherever user-editable text is rendered on the front-end (in place of
 * esc_html). Multiline-safe; the pattern intentionally stops at a newline or a
 * second asterisk so unmatched asterisks render literally.
 */
function fc_format(string $s): string {
    $escaped = esc_html($s);
    return (string) preg_replace(
        '/\*([^\*\n]+?)\*/u',
        '<span class="fc-accent">$1</span>',
        $escaped
    );
}

/**
 * Like fc_format() but for multi-paragraph text (textarea). Runs wpautop after
 * formatting so paragraphs survive, and re-applies the asterisk highlight to the
 * escaped output.
 */
function fc_format_block(string $s): string {
    return wpautop(fc_format($s));
}

/**
 * fc_format() + markdown-style inline links: any "[text](url)" segment becomes
 * an <a class="fc-link">text →</a>. Used by the FAQ so editors can drop a link
 * into an answer with the same underline + accent hover treatment as the CTA
 * links. Plain text is still escaped and the asterisk highlight still works
 * outside of link segments.
 *
 * Allowed URL schemes: http(s), mailto:, tel:, and #anchors (so editors can
 * point at on-page sections like "#schedule" or "#home").
 */
function fc_format_inline_links(string $s): string {
    $out = '';
    $offset = 0;
    $len = strlen($s);
    // Manual scan so we can match brackets that contain literal characters reliably.
    while ($offset < $len) {
        $open = strpos($s, '[', $offset);
        if ($open === false) {
            $out .= fc_format(substr($s, $offset));
            break;
        }
        $close_text = strpos($s, ']', $open + 1);
        if ($close_text === false || $close_text + 1 >= $len || $s[$close_text + 1] !== '(') {
            // No "](" right after; treat the "[" as a literal.
            $out .= fc_format(substr($s, $offset, $open - $offset + 1));
            $offset = $open + 1;
            continue;
        }
        $close_url = strpos($s, ')', $close_text + 2);
        if ($close_url === false) {
            $out .= fc_format(substr($s, $offset));
            break;
        }
        // Pre-link text.
        if ($open > $offset) {
            $out .= fc_format(substr($s, $offset, $open - $offset));
        }
        $text = substr($s, $open + 1, $close_text - $open - 1);
        $url  = trim(substr($s, $close_text + 2, $close_url - $close_text - 2));
        $href = fc_sanitize_faq_link_url($url);
        if ($href === '' || $text === '') {
            // Bad link — keep the raw markdown so the editor notices.
            $out .= fc_format(substr($s, $open, $close_url - $open + 1));
        } else {
            $is_external = (bool) preg_match('#^https?://#i', $href);
            $target_attr = $is_external ? ' target="_blank" rel="noreferrer noopener"' : '';
            $out .= '<a href="' . esc_url($href) . '"'
                . ' class="fc-link underline-link accent-link inline-flex items-baseline gap-1 whitespace-nowrap"'
                . $target_attr . '>'
                . '<span>' . fc_format($text) . '</span>'
                . '<span aria-hidden="true">→</span>'
                . '</a>';
        }
        $offset = $close_url + 1;
    }
    return $out;
}

/**
 * Strip [text](url) markdown link syntax down to its visible text. Used to
 * produce the plain-text version the scramble animation tweens through before
 * the rich HTML is swapped in.
 */
function fc_strip_inline_links(string $s): string {
    return (string) preg_replace('/\[([^\]\n]*)\]\(([^\)\n]*)\)/u', '$1', $s);
}

/**
 * Restrict link URLs accepted by fc_format_inline_links() to schemes we trust
 * for editor-supplied copy: http(s), mailto:, tel:, and #anchors.
 */
function fc_sanitize_faq_link_url(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if ($url[0] === '#') {
        $slug = sanitize_html_class(substr($url, 1));
        return $slug === '' ? '' : '#' . $slug;
    }
    if (preg_match('#^(mailto:|tel:)#i', $url)) {
        return $url;
    }
    if (preg_match('#^https?://#i', $url)) {
        return esc_url_raw($url);
    }
    return '';
}

/**
 * Editor-facing TBA copy for empty sections (sponsors, speakers, schedule, news…).
 * Stored as one bilingual array per section key in option `fc_tba_text`.
 * Returns the EL/EN pair, falling back to the global default copy.
 */
function fc_tba_text(string $section_key): array {
    static $cache = null;
    if ($cache === null) {
        $stored = get_option('fc_tba_text', []);
        $cache  = is_array($stored) ? $stored : [];
    }
    $default = 'Insert profound, life-changing content here. (Check back when we figure out what that is).';
    $row = isset($cache[$section_key]) && is_array($cache[$section_key]) ? $cache[$section_key] : [];
    return [
        'el' => (string) ($row['el'] ?? $default),
        'en' => (string) ($row['en'] ?? $default),
    ];
}

/**
 * Renders the standard "section is empty" block — bilingual TBA copy, centered,
 * styled like the section eyebrow. Each section template calls this when it has
 * no admin-managed rows yet.
 */
function fc_render_tba(string $section_key): void {
    $tba = fc_tba_text($section_key);
    if ($tba['el'] === '' && $tba['en'] === '') return;
    ?>
    <div class="py-16 md:py-24 text-center font-mono text-xs md:text-sm uppercase tracking-widest text-ink-muted">
        <?php if ($tba['en'] !== '') : ?>
            <p class="m-0 leading-relaxed" lang="en"><?php echo fc_format($tba['en']); ?></p>
        <?php endif; ?>
        <?php if ($tba['el'] !== '') : ?>
            <p class="m-0 mt-2 opacity-70 leading-relaxed"><?php echo fc_format($tba['el']); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Echo-safe array attribute renderer for data-* attributes on island mount points.
 */
function fc_island_attrs(string $name, array $payload = []): string {
    $attrs = 'data-fc-island="' . esc_attr($name) . '"';
    if (!empty($payload)) {
        $attrs .= ' data-fc-payload="' . esc_attr(wp_json_encode($payload)) . '"';
    }
    return $attrs;
}

/**
 * Returns a bilingual pair from a data array with `_el` / `_en` keys.
 *
 * @return array{el: string, en: string}
 */
function fc_bi(array $data, string $base): array {
    return [
        'el' => (string) ($data[$base . '_el'] ?? ''),
        'en' => (string) ($data[$base . '_en'] ?? ''),
    ];
}

/**
 * Renders a two-column body block — English left, Greek right.
 * The function signature keeps ($el, $en) for caller clarity, but renders English first.
 *
 * @param string $el       Greek paragraph text. Newlines become <p> tags.
 * @param string $en       English paragraph text.
 * @param array  $args     class, no_labels (suppress EN/EL column headings)
 */
function fc_bi_block(string $el, string $en, array $args = []): void {
    if ($el === '' && $en === '') return;
    $no_labels  = !empty($args['no_labels']);
    $wrap_class = $args['class'] ?? 'text-lg leading-relaxed';
    ?>
    <div class="grid grid-cols-12 gap-8 md:gap-12">
        <div class="col-span-12 md:col-span-6 space-y-3 <?php echo esc_attr($wrap_class); ?>" lang="en">
            <?php if (!$no_labels) : ?>
                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">EN / English</p>
            <?php endif; ?>
            <?php echo wp_kses_post(fc_format_block($en)); ?>
        </div>
        <div class="col-span-12 md:col-span-6 space-y-3 <?php echo esc_attr($wrap_class); ?>">
            <?php if (!$no_labels) : ?>
                <p class="font-mono text-[10px] uppercase tracking-widest text-ink-muted">EL / Ελληνικά</p>
            <?php endif; ?>
            <?php echo wp_kses_post(fc_format_block($el)); ?>
        </div>
    </div>
    <?php
}

/**
 * Stacked bilingual heading: English primary, Greek smaller below.
 */
function fc_bi_stack(string $el, string $en, string $tag = 'div', array $args = []): void {
    if ($el === '' && $en === '') return;
    $primary_class   = (string) ($args['primary_class']   ?? 'font-display text-xl');
    $secondary_class = (string) ($args['secondary_class'] ?? 'font-mono text-[11px] uppercase tracking-widest text-ink-muted');
    if ($en !== '') {
        printf('<%1$s class="%2$s" lang="en">%3$s</%1$s>', esc_attr($tag), esc_attr($primary_class), fc_format($en));
    }
    if ($el !== '') {
        printf('<span class="%1$s">%2$s</span>', esc_attr($secondary_class), fc_format($el));
    }
}

/**
 * Render a CTA link with the theme's display-text style (Home, Get Involved,
 * Sponsor CTA, Footer share the same one). Optionally supports admin-driven
 * hover text — when either hover_en or hover_el is non-empty, the link gets
 * data-fc-hover-link + per-span data-fc-hover-default / data-fc-hover-alt,
 * which assets/hover-scramble.js picks up to glitch the visible text into the
 * hover variant on mouseenter (and back on mouseleave). Without hover text,
 * the link renders identically to before — the JS skips it entirely and the
 * existing .accent-link CSS hover stays in charge.
 *
 * @param array{
 *     url:           string,
 *     en:            string,
 *     el:            string,
 *     hover_en?:     string,
 *     hover_el?:     string,
 *     class?:        string,
 *     el_class?:     string,
 *     el_prefix?:    string,  // joiner before EL when EN is also present (e.g. "/ ")
 *     arrow?:        string,
 *     target_blank?: bool,
 * } $args
 */
function fc_cta_link(array $args): void {
    $defaults = [
        'url'          => '#',
        'en'           => '',
        'el'           => '',
        'hover_en'     => '',
        'hover_el'     => '',
        // The link itself no longer carries `underline-link`; instead the
        // EN+EL spans are wrapped in `<span class="fc-cta-text">` which owns
        // the native text-decoration: underline. That makes the underline
        // match the actual text width and skip the trailing arrow, matching
        // the hero CTAs.
        'class'        => 'font-display text-2xl md:text-3xl accent-link text-ink inline-flex items-baseline gap-2 whitespace-nowrap',
        'el_class'     => 'text-base md:text-xl opacity-50',
        'el_prefix'    => '/ ',
        'arrow'        => '→',
        'target_blank' => false,
    ];
    $a = array_merge($defaults, $args);

    // Trailing "→" stripping (the seeds historically shipped some labels with
    // a hard-coded arrow; the template now owns it via $a['arrow']).
    $strip = static function (string $s): string {
        return (string) preg_replace('/[\s→]+$/u', '', $s);
    };
    $en       = $strip((string) $a['en']);
    $el       = $strip((string) $a['el']);
    $hover_en = $strip((string) $a['hover_en']);
    $hover_el = $strip((string) $a['hover_el']);

    if ($en === '' && $el === '') return;

    // EL carries the joiner only when there's a visible EN on the SAME state
    // (default or hover). Each side of a bilingual pair is independent: if the
    // admin leaves hover_el empty while hover_en is set, the EL span should
    // vanish on hover (not fall back to the default Greek next to a different
    // English) — and vice versa. An empty alt scrambles the existing text out
    // to nothing; mouseleave scrambles it back to the default.
    $en_default = $en;
    $el_default = $el !== '' ? (($en !== '' ? $a['el_prefix'] : '') . $el) : '';
    $en_alt     = $hover_en;  // raw — empty means "hide on hover"
    $el_alt     = $hover_el !== ''
        ? (($hover_en !== '' ? $a['el_prefix'] : '') . $hover_el)
        : '';

    $has_hover = ($hover_en !== '' || $hover_el !== '');

    $attrs  = 'href="' . esc_url((string) $a['url']) . '"';
    if (!empty($a['target_blank'])) {
        $attrs .= ' target="_blank" rel="noreferrer"';
    }
    $attrs .= ' class="' . esc_attr((string) $a['class']) . '"';
    if ($has_hover) {
        $attrs .= ' data-fc-hover-link';
    }

    echo '<a ' . $attrs . '>';
    // Wrapper that owns the underline. The arrow is rendered OUTSIDE this
    // wrapper so the underline doesn't extend under it.
    echo '<span class="fc-cta-text">';
    if ($en_default !== '') {
        if ($has_hover) {
            echo '<span lang="en"'
                . ' data-fc-hover-default="' . esc_attr($en_default) . '"'
                . ' data-fc-hover-alt="'     . esc_attr($en_alt)     . '">'
                . fc_format($en_default) . '</span>';
        } else {
            echo '<span lang="en">' . fc_format($en_default) . '</span>';
        }
    }
    if ($el_default !== '') {
        if ($has_hover) {
            echo '<span class="' . esc_attr((string) $a['el_class']) . '"'
                . ' data-fc-hover-default="' . esc_attr($el_default) . '"'
                . ' data-fc-hover-alt="'     . esc_attr($el_alt)     . '">'
                . fc_format($el_default) . '</span>';
        } else {
            echo '<span class="' . esc_attr((string) $a['el_class']) . '">' . fc_format($el_default) . '</span>';
        }
    }
    echo '</span>';
    if ($a['arrow'] !== '') {
        echo '<span aria-hidden="true">' . esc_html((string) $a['arrow']) . '</span>';
    }
    echo '</a>';
}

/**
 * Both languages on one line, English first then Greek. Good for compact metadata.
 */
function fc_bi_inline(string $el, string $en, string $sep = ' / '): string {
    if ($el === '' && $en === '') return '';
    if ($el === '') return '<span lang="en">' . fc_format($en) . '</span>';
    if ($en === '') return fc_format($el);
    return '<span lang="en">' . fc_format($en) . '</span><span class="opacity-50">' . esc_html($sep) . '</span>' . fc_format($el);
}
