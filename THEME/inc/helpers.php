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

/** Option holding the speaker-card colours. Written by the Speakers admin page. */
const FC_SPEAKERS_STYLE_OPTION = 'fc_speakers_style';

/** Option holding the manifesto stat colours. Written by the Manifesto admin page. */
const FC_MANIFESTO_STYLE_OPTION = 'fc_manifesto_style';

/** Option holding the venue name colours. Written by the Venue admin page. */
const FC_VENUE_STYLE_OPTION = 'fc_venue_style';

/** Option holding the CFP heading colours. Written by the Get Involved admin page. */
const FC_CFP_STYLE_OPTION = 'fc_cfp_style';

/**
 * The house pair for every outlined thing on the site: blue at rest, orange on
 * hover.
 *
 * Three sections draw hollow text — the speakers' last name, the manifesto's
 * "+", the venue's last line — and they are all the same gesture, so they all
 * start from the same two colours. Each still has its OWN option and its own
 * pair of dashboard fields: a shared setting would mean tuning the speakers
 * silently moved the venue, which is a surprise nobody asked for. These are the
 * defaults those three fall back to, in one place so the house colours cannot
 * drift apart in three files.
 */
const FC_OUTLINE_REST  = '#0033FF';
const FC_OUTLINE_HOVER = '#EE8101';

/**
 * The hover colours these replaced, kept ONLY so the migration below can
 * recognise a value nobody deliberately chose. A saved colour equal to an old
 * default is a default, not a decision.
 */
const FC_OUTLINE_HOVER_LEGACY = ['#FF6A2B', '#FFCC00'];

/**
 * One-time migration: move a saved hover colour that is still one of the old
 * defaults onto the new house orange.
 *
 * Changing a default only reaches installs that never saved the field — and both
 * of these have a Save button that writes every field whether it was touched or
 * not, so anybody who has ever pressed Save on Speakers or Manifesto has the old
 * value stored and would keep it forever. A colour that exactly equals the old
 * default was not a choice, so it moves; anything else is left alone.
 *
 * Same shape as fc_mascot_maybe_raise_upright_max(): an autoloaded marker, and
 * admin_init because a live install never re-activates the theme.
 */
add_action('admin_init', 'fc_migrate_outline_hover_colour');
function fc_migrate_outline_hover_colour(): void {
    if (get_option('fc_outline_hover_v1') === '1') return;
    update_option('fc_outline_hover_v1', '1', true);

    foreach ([
        [FC_SPEAKERS_STYLE_OPTION, 'rim_hover'],
        [FC_MANIFESTO_STYLE_OPTION, 'plus_hover'],
    ] as [$option, $key]) {
        $saved = get_option($option, null);
        if (!is_array($saved) || !isset($saved[$key])) continue;
        $was = strtoupper(trim((string) $saved[$key]));
        if (!in_array($was, FC_OUTLINE_HOVER_LEGACY, true)) continue;
        $saved[$key] = FC_OUTLINE_HOVER;
        update_option($option, $saved, false);
    }
}

/**
 * Split a heading into solid and HOLLOW runs, marking each `*starred*` run to be
 * drawn outlined.
 *
 * The other two hollow headings pick their outlined part by POSITION — the
 * speakers' last word, the venue's last line — which works because a name has an
 * obvious end. A sentence does not: "Submit a talk. Or a workshop. Or both."
 * has no last-anything worth outlining, so the author marks it instead.
 *
 * NB this is NOT fc_format(). There, `*text*` means "accent colour"; here it
 * means "hollow". Both are asterisks and they cannot both run over the same
 * string — whichever went first would eat the markers. A field rendered through
 * this one therefore loses the accent-colour meaning of `*text*`, which is the
 * intended trade and is worth knowing before adding this to another field.
 *
 * Everything is escaped. Unmatched asterisks are left as literal text rather
 * than swallowed, so a stray one shows up as a typo instead of silently
 * disappearing or outlining the rest of the line.
 */
function fc_hollow_markup(
    string $text,
    string $outline_class = 'is-outline',
    string $run_attrs = ''
): string {
    if ($text === '') return '';
    // DELIM_CAPTURE keeps the inner text, so odd indices are the starred runs.
    $parts = preg_split('/\*([^*]+)\*/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) return esc_html($text);

    // `$run_attrs` puts the same attributes on EVERY run, solid ones included,
    // which means solid runs get a span they would not otherwise need. That is
    // for the manifesto's stat numbers: they carry a scramble island, and
    // assets/dist/fc.js animates by assigning `el.textContent`, which destroys
    // every child element on the first frame. A span nested inside a scrambled
    // box loses its class immediately and never gets it back — so each run has
    // to be its own island, with no nesting anywhere. Passed raw and unescaped:
    // it is a literal attribute string from the template, never user input.
    $attrs = $run_attrs === '' ? '' : ' ' . $run_attrs;

    $out = '';
    foreach ($parts as $i => $part) {
        if ($part === '') continue;
        if ($i % 2 === 1) {
            $out .= '<span class="' . esc_attr($outline_class) . '"' . $attrs . '>'
                . esc_html($part) . '</span>';
        } elseif ($run_attrs !== '') {
            $out .= '<span' . $attrs . '>' . esc_html($part) . '</span>';
        } else {
            $out .= esc_html($part);
        }
    }
    return $out;
}

/**
 * The two colours the venue's hollow last line is drawn in: at rest, and on
 * hover. Same shape as fc_speakers_style() and fc_manifesto_style().
 */
function fc_venue_style(): array {
    return fc_outline_style(FC_VENUE_STYLE_OPTION);
}

/** Sentinels standing in for the asterisks while a heading is split up. */
const FC_HOLLOW_OPEN  = "\x01";
const FC_HOLLOW_CLOSE = "\x02";

/**
 * Split a heading into pieces — words, or lines — and wrap the `*starred*` parts
 * of each piece so they can be drawn hollow.
 *
 * Every hollow heading on the site works this way now: the speakers' name (split
 * into words), the venue's name and the CFP heading (split into lines). Which
 * part is outlined is always the author's `*asterisks*`, never a position.
 *
 * A NAIVE split cannot do this. `*West Attica*` broken on whitespace gives
 * `*West` and `Attica*`, two pieces each holding one unmatched asterisk — so
 * neither would be outlined and both would show a stray `*`. The asterisks are
 * therefore turned into sentinels FIRST, and the open/close depth is carried
 * across pieces, so a run spanning several words or lines outlines all of them.
 *
 * A span cannot cross a line, so an open run is closed at the end of each piece
 * and reopened at the start of the next.
 *
 * @return string[] one HTML string per piece, already escaped.
 */
function fc_hollow_split(string $text, string $split_regex, string $outline_class = 'is-outline'): array {
    if (trim($text) === '') return [];

    $marked = preg_replace(
        '/\*([^*]+)\*/u',
        FC_HOLLOW_OPEN . '$1' . FC_HOLLOW_CLOSE,
        $text
    );
    if ($marked === null) $marked = $text;

    $pieces = preg_split($split_regex, $marked, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($pieces)) $pieces = [$marked];

    $open_tag  = '<span class="' . esc_attr($outline_class) . '">';
    $out       = [];
    $depth     = 0;          // carried ACROSS pieces

    // Tokenised on the sentinels, NOT walked character by character.
    //
    // A character walk would need mb_substr(), and mbstring is not guaranteed —
    // this very theme guards mb_strtoupper() with function_exists() for that
    // reason, and the first version of this function died with "undefined
    // function mb_strlen" on a PHP build without it. Splitting on the sentinels
    // needs no character indexing at all, and is UTF-8 safe because 0x01 and
    // 0x02 cannot appear inside a multi-byte sequence: every continuation byte
    // is >= 0x80.
    $sentinel = '/(' . FC_HOLLOW_OPEN . '|' . FC_HOLLOW_CLOSE . ')/';

    foreach ($pieces as $piece) {
        $html = '';
        $inside = $depth > 0;
        if ($inside) $html .= $open_tag;

        foreach (preg_split($sentinel, $piece, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $token) {
            if ($token === FC_HOLLOW_OPEN) {
                if (!$inside) { $html .= $open_tag; $inside = true; }
                $depth++;
                continue;
            }
            if ($token === FC_HOLLOW_CLOSE) {
                if ($inside) { $html .= '</span>'; $inside = false; }
                $depth = max(0, $depth - 1);
                continue;
            }
            $html .= esc_html($token);
        }
        // Close anything still open: the next piece reopens it.
        if ($inside) $html .= '</span>';

        // A piece that was nothing but markers contributes no line.
        if (trim(strip_tags($html)) !== '') $out[] = $html;
    }

    return $out;
}

/**
 * The same text with the asterisk markers removed, for measuring.
 *
 * The name-size formulas count characters to work out how big the longest piece
 * can be set; counting the asterisks too would make a marked name render smaller
 * than an unmarked one saying the same thing.
 */
function fc_hollow_plain(string $text): string {
    $out = preg_replace('/\*([^*]+)\*/u', '$1', $text);
    return $out === null ? $text : $out;
}

/**
 * The two colours the CFP heading's `*starred*` run is drawn in.
 * Same shape as the other three.
 */
function fc_cfp_style(): array {
    return fc_outline_style(FC_CFP_STYLE_OPTION);
}

/**
 * One reader for an outline colour pair, since there are now four of them.
 *
 * They were four copies of the same eight lines, differing only in the option
 * name and — in two cases — the array keys. The keys are `rim` / `rim_hover`
 * everywhere here; the Speakers and Manifesto readers keep their own historical
 * key names (`plus` / `plus_hover` for the manifesto) because those are what is
 * already in the database, and renaming them would need a migration to buy
 * nothing.
 */
function fc_outline_style(string $option): array {
    $saved = get_option($option, []);
    if (!is_array($saved)) $saved = [];
    $pick = static function ($value, string $fallback): string {
        $hex = sanitize_hex_color((string) $value);
        return $hex ? $hex : $fallback;
    };
    return [
        'rim'       => $pick($saved['rim'] ?? '', FC_OUTLINE_REST),
        'rim_hover' => $pick($saved['rim_hover'] ?? '', FC_OUTLINE_HOVER),
    ];
}

/**
 * The two colours the "+" on a manifesto stat is drawn in: at rest, and on hover.
 *
 * A separate option from the speakers' pair rather than one shared "accent
 * colours" setting, because the two are answering different questions — the
 * speakers' is the outline round a photograph, this is a glyph in a number — and
 * somebody tuning one should not silently move the other.
 *
 * Read on every landing-page render, so it lives here rather than in the admin
 * directory, which only exists when is_admin().
 */
function fc_manifesto_style(): array {
    $saved = get_option(FC_MANIFESTO_STYLE_OPTION, []);
    if (!is_array($saved)) $saved = [];
    $pick = static function ($value, string $fallback): string {
        $hex = sanitize_hex_color((string) $value);
        return $hex ? $hex : $fallback;
    };
    return [
        'plus'       => $pick($saved['plus'] ?? '', FC_OUTLINE_REST),
        'plus_hover' => $pick($saved['plus_hover'] ?? '', FC_OUTLINE_HOVER),
    ];
}

/**
 * The two outline colours for a speaker's cut-out photo: at rest, and on hover.
 *
 * Lives HERE rather than in inc/admin/pages/speakers.php because the admin
 * directory is only required when is_admin() — the front end needs to read this
 * on every landing-page render, and a reader that only exists in wp-admin is a
 * fatal on the public site.
 *
 * sanitize_hex_color() returns null for anything that is not a valid hex colour,
 * so a half-typed value in the box falls back rather than emitting broken CSS.
 */
function fc_speakers_style(): array {
    $saved = get_option(FC_SPEAKERS_STYLE_OPTION, []);
    if (!is_array($saved)) $saved = [];
    $pick = static function ($value, string $fallback): string {
        $hex = sanitize_hex_color((string) $value);
        return $hex ? $hex : $fallback;
    };
    return [
        // The portraits stand on the section's own paper, so the resting outline
        // is the theme accent — a white one would be invisible against it.
        'rim'       => $pick($saved['rim'] ?? '', FC_OUTLINE_REST),
        'rim_hover' => $pick($saved['rim_hover'] ?? '', FC_OUTLINE_HOVER),
    ];
}

/**
 * Given the URL of a WordPress image derivative, return the original upload's
 * URL. Anything else is handed straight back.
 *
 * The media picker stores whatever URL it was given, and its default is the
 * "medium" size — 300px on the longest side out of the box. That is fine for a
 * thumbnail and badly wrong for anything drawn large: a 300px file in a box
 * 840 device pixels wide is a 2.8x upscale, which reads as a soft, mushy photo
 * that no amount of filtering can rescue.
 *
 * The `-WxH` suffix is only a HINT that a URL might be a derivative — a file
 * genuinely called `team-photo-1920x1080.png` matches it too. So the suffix
 * merely decides whether to bother looking, and the answer comes from the media
 * library: no attachment, no substitution.
 */
function fc_media_original_url(string $url): string {
    if ($url === '') return $url;
    if (!preg_match('/-\d+x\d+\.(?:jpe?g|png|gif|webp|avif)$/i', $url)) return $url;
    if (!function_exists('attachment_url_to_postid')) return $url;

    $id = attachment_url_to_postid($url);
    if (!$id) return $url;

    $full = function_exists('wp_get_original_image_url') ? wp_get_original_image_url($id) : '';
    if (!$full) $full = wp_get_attachment_url($id);
    return $full ? (string) $full : $url;
}

/**
 * Split a multi-line admin field into trimmed, non-empty lines.
 *
 * One role per line is how a speaker's titles are entered — "Co-founder",
 * "President / Valve" — and every one of them becomes its own line on the card.
 */
function fc_lines(string $text): array {
    $out = [];
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line !== '') $out[] = $line;
    }
    return $out;
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
 * and get filtered out of the globe pins by the front-end (assets/venue-map.js).
 * Sorted oldest → newest by year.
 *
 * @return array<int,array{year:int,city:string,lat:float|string,lon:float|string,url:string,spotlight:bool}>
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
        // City is bilingual now (city_el / city_en); fall back to the legacy
        // single `city` field for rows saved before the split. Resolved to the
        // active language so the globe + editions list stay in sync with the
        // language toggle.
        $city = fc_pick((string) ($ed['city_el'] ?? ''), (string) ($ed['city_en'] ?? ''));
        if ($city === '') {
            $city = (string) ($ed['city'] ?? '');
        }
        $out[] = [
            'year'    => (int)    ($ed['year'] ?? 0),
            'city'    => $city,
            'lat'       => is_numeric($rawLat) ? (float) $rawLat : '',
            'lon'       => is_numeric($rawLon) ? (float) $rawLon : '',
            'url'       => (string) ($ed['url']  ?? ''),
            // Legacy installs stored the featured edition as `current`; honour it
            // as a spotlight until the admin re-saves the Venue page.
            'spotlight' => !empty($ed['spotlight']) || !empty($ed['current']),
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
 * Escapes the string, then applies three inline markups so editors can style a
 * segment of any field by wrapping it:
 *   *text*        → <span class="fc-accent">…</span>  — blue Qaroxe pixel face.
 *   %text%        → <span class="fc-fine">…</span>    — small, muted-grey aside
 *                   (e.g. a parenthetical gloss inside a big title).
 *   [text](url)   → <a class="fc-link …">text →</a>   — same hyperlink treatment
 *                   the FAQ uses (see fc_format_inline_links()).
 *
 * Use this wherever user-editable text is rendered on the front-end (in place of
 * esc_html). Multiline-safe; each pattern intentionally stops at a newline or its
 * closing delimiter, so a lone unmatched * or % renders literally.
 *
 * Link support is global: fc_format() delegates to fc_format_inline_links(), so
 * every field that already renders through fc_format() (titles, addresses, the
 * venue "getting here" travel cards, schedule copy, …) accepts [text](url).
 */
function fc_format(string $s): string {
    return fc_format_inline_links($s);
}

/**
 * The plain styling pass behind fc_format(): escape + *accent* + %fine%, with no
 * link parsing. fc_format_inline_links() calls this for the non-link segments of
 * the string (calling fc_format() there would recurse, since fc_format() now
 * routes through fc_format_inline_links()).
 */
function fc_format_styles(string $s): string {
    $out = esc_html($s);
    // *highlight* → accent (blue Qaroxe pixel face).
    $out = (string) preg_replace_callback(
        '/\*([^\*\n]+?)\*/u',
        function ($m) {
            return '<span class="fc-accent">' . fc_fix_homoglyphs($m[1]) . '</span>';
        },
        $out
    );
    // %fine print% → small, muted-grey aside.
    $out = (string) preg_replace_callback(
        '/%([^%\n]+?)%/u',
        function ($m) {
            return '<span class="fc-fine">' . $m[1] . '</span>';
        },
        $out
    );
    return $out;
}

/**
 * Repair a Latin word contaminated by a visually-identical Greek/Cyrillic
 * CAPITAL (a "homoglyph"). The accent face used by .fc-accent (Qaroxe) is
 * Latin-only, so a stray Greek capital Tau in "Τhree" has no glyph and falls
 * back to the body font for that one letter — the rest of the word stays in
 * the pixel face, which is the reported bug.
 *
 * We map the confusable capitals back to their Latin twins, but ONLY inside a
 * letter-run that already contains an ASCII Latin letter. That repairs an
 * accidental mixed word ("Τhree" → "Three") while leaving genuinely Greek
 * highlights (e.g. "ΕΛ/ΛΑΚ", "Δωρεάν" — no ASCII letters) untouched. Runs on
 * the *highlight* segment only, so non-highlighted copy is never altered.
 */
function fc_fix_homoglyphs(string $text): string {
    if ($text === '') return $text;
    static $map = [
        // Greek capitals → Latin look-alikes
        'Α' => 'A', 'Β' => 'B', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H',
        'Ι' => 'I', 'Κ' => 'K', 'Μ' => 'M', 'Ν' => 'N', 'Ο' => 'O',
        'Ρ' => 'P', 'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X',
        // Cyrillic capitals → Latin look-alikes
        'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M',
        'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'Х' => 'X',
    ];
    return (string) preg_replace_callback('/\p{L}+/u', function ($m) use ($map) {
        // Only repair runs that read as a Latin word (≥1 ASCII letter).
        if (!preg_match('/[A-Za-z]/', $m[0])) {
            return $m[0];
        }
        return strtr($m[0], $map);
    }, $text);
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
 * fc_format()'s link-aware engine: any "[text](url)" segment becomes an
 * <a class="fc-link">text</a> — just the accent-blue site colour (styled in
 * assets/site.css), no underline and no trailing arrow. Non-link text is run
 * through fc_format_styles() so the asterisk/percent highlights still work, and
 * everything is escaped.
 *
 * This is what fc_format() routes through, so link support is site-wide; the
 * function name is kept for the explicit callers (FAQ, conduct page) that want
 * to be self-documenting about needing links.
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
            $out .= fc_format_styles(substr($s, $offset));
            break;
        }
        $close_text = strpos($s, ']', $open + 1);
        if ($close_text === false || $close_text + 1 >= $len || $s[$close_text + 1] !== '(') {
            // No "](" right after; treat the "[" as a literal.
            $out .= fc_format_styles(substr($s, $offset, $open - $offset + 1));
            $offset = $open + 1;
            continue;
        }
        $close_url = strpos($s, ')', $close_text + 2);
        if ($close_url === false) {
            $out .= fc_format_styles(substr($s, $offset));
            break;
        }
        // Pre-link text.
        if ($open > $offset) {
            $out .= fc_format_styles(substr($s, $offset, $open - $offset));
        }
        $text = substr($s, $open + 1, $close_text - $open - 1);
        $url  = trim(substr($s, $close_text + 2, $close_url - $close_text - 2));
        $href = fc_sanitize_faq_link_url($url);
        if ($href === '' || $text === '') {
            // Bad link — keep the raw markdown so the editor notices.
            $out .= fc_format_styles(substr($s, $open, $close_url - $open + 1));
        } else {
            $is_external = (bool) preg_match('#^https?://#i', $href);
            $target_attr = $is_external ? ' target="_blank" rel="noreferrer noopener"' : '';
            $out .= '<a href="' . esc_url($href) . '"'
                . ' class="fc-link"'
                . $target_attr . '>'
                . fc_format_styles($text)
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
    $tba  = fc_tba_text($section_key);
    $text = fc_pick($tba['el'], $tba['en']);
    if ($text === '') return;
    ?>
    <div class="py-16 md:py-24 text-center font-mono text-xs md:text-sm uppercase tracking-widest text-ink-muted">
        <p class="m-0 leading-relaxed"><?php echo fc_format($text); ?></p>
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
 * Collapse an ['el'=>…, 'en'=>…] pair (as returned by fc_bi()) to the single
 * active-language string. Falls back to the other language when the active one
 * is empty. Used by templates that hold an fc_bi() pair now that the site shows
 * one language at a time.
 */
function fc_one(array $pair): string {
    return fc_pick((string) ($pair['el'] ?? ''), (string) ($pair['en'] ?? ''));
}

/**
 * Built-in EL/EN names for the sponsor tiers — used as both the admin
 * placeholders and the front-end fallback when no custom name is saved.
 */
function fc_sponsor_tier_defaults(): array {
    return [
        'diamond'   => ['el' => 'Diamond χορηγός',    'en' => 'Diamond sponsor'],
        'gold'      => ['el' => 'Gold χορηγός',        'en' => 'Gold sponsor'],
        'silver'    => ['el' => 'Silver χορηγός',      'en' => 'Silver sponsor'],
        'bronze'    => ['el' => 'Bronze χορηγός',      'en' => 'Bronze sponsor'],
        'community' => ['el' => 'Community συνεργάτης', 'en' => 'Community partner'],
        'in-kind'   => ['el' => 'In-kind χορηγός',     'en' => 'In-kind sponsor'],
    ];
}

/**
 * The admin-editable name of a sponsor tier (option `fc_sponsors_tiers`),
 * resolved to the active language and falling back to the built-in default.
 */
function fc_sponsor_tier_label(string $tier): string {
    $def = fc_sponsor_tier_defaults()[$tier] ?? ['el' => '', 'en' => ''];
    $opt = get_option('fc_sponsors_tiers', []);
    $row = (is_array($opt) && is_array($opt[$tier] ?? null)) ? $opt[$tier] : [];
    // Legacy: the Diamond tier was renamed from Platinum — honour a custom label
    // saved under the old key until the admin re-saves the Sponsors page.
    if (!$row && $tier === 'diamond' && is_array($opt) && is_array($opt['platinum'] ?? null)) {
        $row = $opt['platinum'];
    }
    $el  = (string) ($row['label_el'] ?? '');
    if ($el === '') $el = $def['el'];
    $en  = (string) ($row['label_en'] ?? '');
    if ($en === '') $en = $def['en'];
    return fc_pick($el, $en);
}

/**
 * Renders a body block in the active language only (single column now — the
 * site shows one language at a time, so the old EN|EL two-column split and its
 * "EN / English" captions are gone). Signature keeps ($el, $en) for callers.
 *
 * @param string $el       Greek paragraph text. Newlines become <p> tags.
 * @param string $en       English paragraph text.
 * @param array  $args     class
 */
function fc_bi_block(string $el, string $en, array $args = []): void {
    $text = fc_pick($el, $en);
    if ($text === '') return;
    $wrap_class = $args['class'] ?? 'text-lg leading-relaxed';
    ?>
    <div class="space-y-3 <?php echo esc_attr($wrap_class); ?>">
        <?php echo wp_kses_post(fc_format_block($text)); ?>
    </div>
    <?php
}

/**
 * Formerly the small "EN / English" · "EL / Ελληνικά" caption shown above a
 * bilingual block. The site is single-language now, so these captions are
 * suppressed — the function is kept (returns '') so existing callers are inert
 * without needing to be touched.
 */
function fc_lang_label(string $lang): string {
    return '';
}

/**
 * Heading in the active language only (was "English primary, Greek below").
 */
function fc_bi_stack(string $el, string $en, string $tag = 'div', array $args = []): void {
    $text = fc_pick($el, $en);
    if ($text === '') return;
    $primary_class = (string) ($args['primary_class'] ?? 'font-display text-xl');
    printf('<%1$s class="%2$s">%3$s</%1$s>', esc_attr($tag), esc_attr($primary_class), fc_format($text));
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

    // Single active language now: pick the label + its hover variant for the
    // current language (falling back to the other when one side is empty). An
    // empty hover scrambles the text out to nothing on mouseenter; mouseleave
    // scrambles it back to the default (handled by assets/hover-scramble.js).
    $default = fc_pick($el, $en);
    $alt     = fc_pick($hover_el, $hover_en);
    if ($default === '') return;

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
    if ($has_hover) {
        echo '<span'
            . ' data-fc-hover-default="' . esc_attr($default) . '"'
            . ' data-fc-hover-alt="'     . esc_attr($alt)     . '">'
            . fc_format($default) . '</span>';
    } else {
        echo '<span>' . fc_format($default) . '</span>';
    }
    echo '</span>';
    if ($a['arrow'] !== '') {
        echo '<span aria-hidden="true">' . esc_html((string) $a['arrow']) . '</span>';
    }
    echo '</a>';
}

/**
 * Compact metadata in the active language only. (Was "both languages on one
 * line"; the site shows one language at a time now, so the $sep argument is
 * accepted for call-site compatibility but unused.) Falls back to the other
 * language when the active one is empty.
 */
function fc_bi_inline(string $el, string $en, string $sep = ' / '): string {
    $text = fc_pick($el, $en);
    if ($text === '') return '';
    return fc_format($text);
}
