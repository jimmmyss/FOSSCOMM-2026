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
 * An eyebrow with its leading number picked out: "05 / Venue" comes back as
 * `<span class="fc-num">05</span> / Venue`, so the index can be painted in the
 * accent while the name stays with the rest of the label. Used by the section
 * eyebrows and by the section-nav links.
 *
 * Anything that does not START with digits is returned escaped and untouched —
 * the field is editable, and "Venue" without a number is a legitimate value.
 *
 * Returns HTML; the caller must not escape it again.
 */
function fc_eyebrow_html(string $eyebrow): string {
    $eyebrow = trim($eyebrow);
    if ($eyebrow === '') return '';
    // digits, then whatever separates them from the name (spaces, slash, dot…).
    if (preg_match('/^(\d+)([^\p{L}\p{N}]*)(.*)$/u', $eyebrow, $m)) {
        return '<span class="fc-num">' . esc_html($m[1]) . '</span>'
             . esc_html($m[2]) . esc_html($m[3]);
    }
    return esc_html($eyebrow);
}

/**
 * Are the buttons, card titles and FAQ questions (everything in the .fc-btn
 * face) forced into capitals? FOSSCOMM → Text Style.
 *
 * OFF by default: the text is shown exactly as typed, so a label written in
 * normal case keeps its lower-case letters. Switching it on capitalises the lot.
 */
function fc_buttons_uppercase(): bool {
    $opt = get_option('fc_text_style', []);
    if (!is_array($opt) || !array_key_exists('buttons_caps', $opt)) {
        return false;
    }
    return (string) $opt['buttons_caps'] === '1';
}

/* The switch is a class on <body> rather than a second set of templates: the
   face is one CSS rule, so turning its uppercase off is one more
   (.fc-btn-as-typed in assets/site.css). body_class covers every page that
   goes through header.php — the landing page, news, conduct, attributions. */
add_filter('body_class', 'fc_body_class_text_style');
function fc_body_class_text_style(array $classes): array {
    if (!fc_buttons_uppercase()) {
        $classes[] = 'fc-btn-as-typed';
    }
    return $classes;
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
 * The page's paper. Same value as `--paper` in assets/fc.css and
 * `--color-paper` in inc/bootstrap.php — repeated here because a sponsor tier's
 * background is a stored hex and PHP has to be able to name the default it falls
 * back to. Change one, change all three.
 */
const FC_PAPER = '#FAFAF7';

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
/** And for the DOUBLE marker, `**like this**`, where a heading asks for one. */
const FC_HOLLOW_OPEN2  = "\x03";
const FC_HOLLOW_CLOSE2 = "\x04";

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
function fc_hollow_split(
    string $text,
    string $split_regex,
    string $outline_class = 'is-outline',
    ?string $double_class = null
): array {
    if (trim($text) === '') return [];

    /* TWO MARKERS, when a heading asks for them.
     *
     * `$double_class` lets a heading give `**this**` a different treatment from
     * `*this*` — the sponsor tiers fill a single-marked run and outline a
     * double-marked one. Left null it is not even looked for, so the three
     * headings that only ever wanted one marker behave exactly as before.
     *
     * The DOUBLE pass has to run first. The single-asterisk pattern below cannot
     * match `**x**` starting at the first asterisk — the next character is
     * another asterisk and its character class excludes them — so it would match
     * the inner `*x*` and leave a stray asterisk on each side. Consuming the
     * doubles up front removes the ambiguity rather than relying on a cleverer
     * single pattern.
     *
     * (And do not paste that pattern into this comment to illustrate the point:
     * it ends in an escaped asterisk followed by a slash, which closes the block
     * comment early and turns the rest of this paragraph into code. It did.) */
    $marked = $text;
    if ($double_class !== null) {
        $d = preg_replace(
            '/\*\*([^*]+)\*\*/u',
            FC_HOLLOW_OPEN2 . '$1' . FC_HOLLOW_CLOSE2,
            $marked
        );
        if ($d !== null) $marked = $d;
    }
    $s = preg_replace(
        '/\*([^*]+)\*/u',
        FC_HOLLOW_OPEN . '$1' . FC_HOLLOW_CLOSE,
        $marked
    );
    if ($s !== null) $marked = $s;

    $pieces = preg_split($split_regex, $marked, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($pieces)) $pieces = [$marked];

    // What each opening sentinel opens, and the literal text to fall back to if
    // one turns up where it cannot be honoured.
    $runs = [
        FC_HOLLOW_OPEN  => ['close' => FC_HOLLOW_CLOSE,  'class' => $outline_class, 'lit' => '*'],
        FC_HOLLOW_OPEN2 => ['close' => FC_HOLLOW_CLOSE2, 'class' => (string) $double_class, 'lit' => '**'],
    ];
    $closes = [
        FC_HOLLOW_CLOSE  => FC_HOLLOW_OPEN,
        FC_HOLLOW_CLOSE2 => FC_HOLLOW_OPEN2,
    ];

    $out  = [];
    $open = null;            // the OPEN sentinel currently active, carried ACROSS pieces

    // Tokenised on the sentinels, NOT walked character by character.
    //
    // A character walk would need mb_substr(), and mbstring is not guaranteed —
    // this very theme guards mb_strtoupper() with function_exists() for that
    // reason, and the first version of this function died with "undefined
    // function mb_strlen" on a PHP build without it. Splitting on the sentinels
    // needs no character indexing at all, and is UTF-8 safe because 0x01..0x04
    // cannot appear inside a multi-byte sequence: every continuation byte
    // is >= 0x80.
    $sentinel = '/(' . FC_HOLLOW_OPEN . '|' . FC_HOLLOW_CLOSE
        . '|' . FC_HOLLOW_OPEN2 . '|' . FC_HOLLOW_CLOSE2 . ')/';

    $open_tag = static function (string $sentinel_char) use ($runs): string {
        return '<span class="' . esc_attr($runs[$sentinel_char]['class']) . '">';
    };

    foreach ($pieces as $piece) {
        $html = '';
        // A span cannot cross a line, so a run still open from the previous piece
        // is reopened here and closed again at the end.
        if ($open !== null) $html .= $open_tag($open);

        foreach (preg_split($sentinel, $piece, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $token) {
            if (isset($runs[$token])) {
                if ($open === null) {
                    $open = $token;
                    $html .= $open_tag($token);
                } else {
                    // A marker inside another run. Nesting is not a thing these
                    // headings do, and silently swallowing it would hide a typo —
                    // so it comes back as the asterisks the author actually typed.
                    $html .= esc_html($runs[$token]['lit']);
                }
                continue;
            }
            if (isset($closes[$token])) {
                $opener = $closes[$token];
                if ($open === $opener) {
                    $html .= '</span>';
                    $open = null;
                } else {
                    $html .= esc_html($runs[$opener]['lit']);
                }
                continue;
            }
            $html .= esc_html($token);
        }
        // Close anything still open: the next piece reopens it.
        if ($open !== null) $html .= '</span>';

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
 *
 * Doubles first, for the same reason fc_hollow_split() consumes them first: the
 * single pattern cannot match `**x**` from its first asterisk and would leave one
 * stranded at each end.
 */
function fc_hollow_plain(string $text): string {
    $out = preg_replace('/\*\*([^*]+)\*\*/u', '$1', $text);
    if ($out === null) $out = $text;
    $out = preg_replace('/\*([^*]+)\*/u', '$1', $out);
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
 * Get Involved's content, in the section's current shape: three columns, the
 * first of which (Participate) has a title in three parts around a live
 * countdown word, a start and a stop time, and the small line under its title.
 *
 *   p_title1 / p_counter / p_title2   the Participate title, around its counter
 *   p_start / p_stop                  when the counter runs from and to
 *   p_closed                          the small line once the stop has passed
 *   p_body                            its description
 *   p_btn / p_btn_hover / p_url       the button under it, and where it goes
 *   c2_* / c3_*                       the other two: title, label, body, button
 *
 * Returned FLAT (`p_title1_en`, …) so fc_bi() and the dashboard's own field
 * helpers read it directly. Any field the page has never been saved with is
 * filled from the old shape — the CFP heading, body and deadline become the
 * first column, and each card's title and link become the button under its
 * column — so nothing goes blank between updating the theme and pressing Save,
 * including fields (the three buttons) added after an earlier save. A key that
 * IS stored wins even when it is empty: once the field has been on the form,
 * clearing it means clearing it.
 */
function fc_involved_values(array $data): array {
    $cards = array_values((array) ($data['cards'] ?? []));
    if (!$cards && function_exists('fc_default_volunteer_cards')) {
        // Saved in this shape already: the cards are gone, so the buttons fall
        // back to the ones the three cards carried before the redesign.
        $cards = fc_default_volunteer_cards();
    }
    $card = static function (int $i, string $key, string $lang = '') use ($cards): string {
        $row = is_array($cards[$i] ?? null) ? $cards[$i] : [];
        return (string) ($row[$lang !== '' ? $key . '_' . $lang : $key] ?? '');
    };
    $out  = $data;
    $fill = static function (string $key, string $value) use (&$out): void {
        if (!array_key_exists($key, $out)) $out[$key] = $value;
    };
    foreach (['en', 'el'] as $l) {
        $fill('p_title1_' . $l,    (string) ($data['cfp_title_' . $l] ?? ''));
        $fill('p_body_' . $l,      (string) ($data['cfp_body_' . $l] ?? ''));
        $fill('p_btn_' . $l,       $card(0, 'title', $l));
        $fill('p_btn_hover_' . $l, $card(0, 'hover_title', $l));
        foreach ([2, 3] as $n) {
            $fill('c' . $n . '_title_' . $l,     '');
            $fill('c' . $n . '_body_' . $l,      $card($n - 1, 'body', $l));
            $fill('c' . $n . '_btn_' . $l,       $card($n - 1, 'title', $l));
            $fill('c' . $n . '_btn_hover_' . $l, $card($n - 1, 'hover_title', $l));
        }
    }
    $fill('p_url',  $card(0, 'url'));
    $fill('c2_url', $card(1, 'url'));
    $fill('c3_url', $card(2, 'url'));
    $fill('p_stop', (string) ($data['cfp_deadline'] ?? ''));
    return $out;
}

/**
 * A dashboard datetime-local value ("2026-10-01T12:00", in the site's timezone)
 * as an absolute timestamp, or null when it is empty or unreadable. The page
 * hands the ISO form to the browser so the countdown is right in every
 * visitor's timezone, not just the site's.
 */
function fc_site_time(string $local): ?DateTimeImmutable {
    $local = trim($local);
    if ($local === '') return null;
    try {
        return new DateTimeImmutable($local, wp_timezone());
    } catch (Exception $e) {
        return null;
    }
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
 * The heading for /speakers/, as an ['el' => …, 'en' => …] pair.
 *
 * Its own field (FOSSCOMM → Speakers → "Page heading"), stored beside the
 * section's heading in `fc_section_speakers`. Empty means "say what the section
 * says", so a site that never fills it in still has a heading on the page.
 */
function fc_speakers_page_title(): array {
    $data = get_option('fc_section_speakers', []);
    if (!is_array($data)) $data = [];
    $meta = fc_section_meta('speakers', [
        'title_el' => 'Άνθρωποι που εμφανίστηκαν',
        'title_en' => 'People who showed up.',
    ]);
    $el = trim((string) ($data['page_title_el'] ?? ''));
    $en = trim((string) ($data['page_title_en'] ?? ''));
    return [
        'el' => $el !== '' ? $el : (string) $meta['title_el'],
        'en' => $en !== '' ? $en : (string) $meta['title_en'],
    ];
}

/**
 * The speakers the landing page's row shows: the ones ticked as highlights.
 *
 * /speakers/ lists everybody; the row is a selection, so each speaker carries a
 * `featured` tick in the dashboard. A row saved BEFORE that field existed has no
 * such key at all, and an absent key means shown — otherwise updating the theme
 * would empty the section until someone went through and ticked everyone. Only
 * a box that has actually been unticked hides a speaker.
 */
function fc_speakers_featured(array $speakers): array {
    $out = [];
    foreach ($speakers as $sp) {
        if (!is_array($sp)) continue;
        if (array_key_exists('featured', $sp) && empty($sp['featured'])) continue;
        $out[] = $sp;
    }
    return $out;
}

/**
 * The speakers, turned into the rows both places that draw them need: the row on
 * the landing page and the full list at /speakers/.
 *
 * Returns ['cards' => [...], 'longest' => int]. Each card carries the name split
 * one word per line (with the *starred* part already wrapped in .is-outline),
 * the plain name for alt text and aria-labels, the roles as lines, the photo and
 * the link. A nameless entry is skipped.
 *
 * `longest` is the longest WORD across the whole list, which is what decides the
 * one font size every name is set at — see .fc-spk-name. Measured on the plain
 * text, since the asterisks are markup: counting them would set a marked name
 * smaller than an unmarked one saying the same.
 */
function fc_speaker_cards(array $speakers): array {
    $cards   = [];
    $longest = 1;
    foreach (array_values($speakers) as $sp) {
        $name = trim((string) ($sp['name'] ?? ''));
        if ($name === '') continue;

        // One word per line, and the *starred* words drawn hollow. Which word
        // that is, is the author's asterisks rather than the last position:
        // "Richard *Stallman*" and "*Linus* Torvalds" are both reasonable, and
        // only the person writing the name knows which.
        $words = fc_hollow_split($name, '/\s+/u');
        if (!$words) $words = [esc_html($name)];

        foreach (preg_split('/\s+/u', fc_hollow_plain($name), -1, PREG_SPLIT_NO_EMPTY) ?: [$name] as $word) {
            $len     = function_exists('mb_strlen') ? mb_strlen($word, 'UTF-8') : strlen($word);
            $longest = max($longest, $len);
        }

        $cards[] = [
            'name'   => fc_hollow_plain($name),
            'words'  => $words,
            'online' => !empty($sp['online']),
            'photo'  => (string) ($sp['photo'] ?? ''),
            'roles'  => fc_lines(fc_one(fc_bi($sp, 'roles'))),
            'url'    => (string) ($sp['url'] ?? ''),
        ];
    }
    return ['cards' => $cards, 'longest' => max(1, $longest)];
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
 * Which attachment is this URL, if any — including when the URL is the
 * un-scaled original.
 *
 * attachment_url_to_postid() matches against `_wp_attached_file`, and for a
 * large upload that is the `-scaled` copy WordPress made, not the file that was
 * uploaded. So the original's own URL — exactly what
 * fc_media_original_url() hands back — is the one URL that does NOT resolve.
 * Three attempts, cheapest first.
 */
function fc_attachment_id_from_url(string $url): int {
    if ($url === '' || !function_exists('attachment_url_to_postid')) return 0;

    $id = (int) attachment_url_to_postid($url);
    if ($id) return $id;

    // An original whose stored file is `name-scaled.ext`.
    $scaled = preg_replace('/\.(jpe?g|png|gif|webp|avif)$/i', '-scaled.$1', $url);
    if ($scaled && $scaled !== $url) {
        $id = (int) attachment_url_to_postid($scaled);
        if ($id) return $id;
    }

    // A derivative: drop the `-WxH` and try the base.
    $base = preg_replace('/-\d+x\d+(\.(?:jpe?g|png|gif|webp|avif))$/i', '$1', $url);
    if ($base && $base !== $url) {
        $id = (int) attachment_url_to_postid($base);
        if ($id) return $id;
    }

    return 0;
}

/**
 * Responsive `<img>` attributes for a stored media URL: src, srcset, sizes and
 * intrinsic dimensions.
 *
 * WHY, and it is worth spelling out because the obvious reading of
 * fc_media_original_url() above is that bigger is simply better:
 *
 * That function exists because a 300px file in an 840-device-pixel box is a
 * mushy upscale. It fixed that by always serving the ORIGINAL — which on a phone
 * is the opposite mistake and a much more expensive one. A 1536x1144 PNG decodes
 * to about 7MB of bitmap whatever size it is painted at, and it is being painted
 * into a 260px box. Multiply by a dozen speakers, twice over because the belt
 * clones its cards, and the phone is holding far more decoded image than it has
 * budget for. That is not a download problem you can wait out; it is memory
 * pressure and decode time on the main thread, and it shows up as a carousel
 * that will not hold its frame rate.
 *
 * srcset is the actual answer to "how big should this image be": it offers all
 * the sizes and lets the browser pick using the box size AND the device pixel
 * ratio. A 3x phone with a 260px box asks for 780px and gets the 1024 file; a
 * desktop with a 420px box at 2x asks for 840 and gets the same one; nobody gets
 * the 1536 unless their screen genuinely warrants it. Sharper than the 300px
 * derivative that started all this, and a fraction of the original's cost.
 *
 * `sizes` must describe the box, and the box is
 * `clamp(260px, 26vw, 420px)` (see --fc-spk-photo-h). Written out as explicit
 * conditions rather than a clamp() inside the attribute: math functions in
 * `sizes` are newer than the rest of this and a wrong `sizes` is worse than a
 * verbose one — it makes the browser choose badly in silence.
 *
 * Degrades to exactly the current behaviour: no attachment, no srcset, plain src.
 */
function fc_media_img_attrs(string $url, string $sizes = ''): array {
    $out = ['src' => $url, 'srcset' => '', 'sizes' => '', 'width' => 0, 'height' => 0];
    if ($url === '') return $out;

    $id = fc_attachment_id_from_url($url);
    if (!$id) return $out;

    // 'large' is the default src: 1024px on the long side, which covers every
    // box this theme draws at 1x-2x. srcset carries the rest, up and down.
    $src = function_exists('wp_get_attachment_image_src')
        ? wp_get_attachment_image_src($id, 'large')
        : false;
    if (!$src || empty($src[0])) return $out;

    $srcset = function_exists('wp_get_attachment_image_srcset')
        ? wp_get_attachment_image_srcset($id, 'large')
        : '';

    $out['src']    = (string) $src[0];
    $out['width']  = (int) ($src[1] ?? 0);
    $out['height'] = (int) ($src[2] ?? 0);
    // A srcset with one candidate is noise; the browser has no choice to make.
    if (is_string($srcset) && strpos($srcset, ',') !== false) {
        $out['srcset'] = $srcset;
        $out['sizes']  = $sizes;
    }
    return $out;
}

/* ── Sponsors ───────────────────────────────────────────────────────────────
 *
 * `fc_sponsors` holds an ordered list of TIERS, each owning its own sponsors:
 *
 *   [ 'desc_el'|'desc_en'   => the small line above the title (roles font)
 *     'title_el'|'title_en' => the heading, with *asterisks* marking the
 *                              hollow run, exactly as everywhere else
 *     'colour'              => one colour, used for BOTH the hollow title and
 *                              that tier's shine
 *     'shine'               => whether the mosaic sweep runs
 *     'sponsors'            => [ ['name','logo','logo_alt','url'], … ] ]
 *
 * It used to be a flat list of sponsors each naming one of six hard-coded
 * tiers, with the tier's label and shine colour in two more options keyed by
 * those same six slugs. Which meant a seventh tier was a code change in four
 * places, and a conference that wanted "Tier 1 / Tier 2" instead of
 * "Gold / Silver" could rename the labels but never the set.
 */

/**
 * The sponsor tiers, normalised — and safe to call before the migration has run.
 *
 * A theme update lands before `admin_init` fires, so for one page load the option
 * can still hold the OLD flat shape. Returning [] then (rather than letting the
 * template walk sponsor rows as if they were tiers) means the section renders its
 * "to be announced" state for a moment instead of a pile of PHP warnings.
 */
function fc_sponsor_tiers(): array {
    $rows = get_option('fc_sponsors', []);
    if (!is_array($rows)) return [];

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        // The tell-tale of the old shape: a sponsor row, not a tier row.
        if (!array_key_exists('sponsors', $row)) continue;

        $sponsors = [];
        foreach ((array) $row['sponsors'] as $sp) {
            if (!is_array($sp)) continue;
            $name = trim((string) ($sp['name'] ?? ''));
            $logo = trim((string) ($sp['logo'] ?? ''));
            // A row with neither a name nor a logo is an empty form the editor
            // added and never filled in.
            if ($name === '' && $logo === '') continue;
            $sponsors[] = [
                'name'     => $name,
                'logo'     => $logo,
                'logo_alt' => trim((string) ($sp['logo_alt'] ?? '')),
                'url'      => trim((string) ($sp['url'] ?? '')),
            ];
        }

        $title = fc_bi($row, 'title');
        // A tier with no sponsors AND no title is not a tier yet.
        if (!$sponsors && $title['el'] === '' && $title['en'] === '') continue;

        $out[] = [
            'desc'     => fc_bi($row, 'desc'),
            'title'    => $title,
            'colour'   => (string) (sanitize_hex_color((string) ($row['colour'] ?? '')) ?? ''),
            // Blank means "follow the tier colour"; the template resolves it, so
            // an empty field reads as a default rather than as no line at all.
            'line'     => (string) (sanitize_hex_color((string) ($row['line'] ?? '')) ?? ''),
            'shine'    => !empty($row['shine']),
            'sponsors' => $sponsors,
        ];
    }
    return $out;
}

/**
 * The widest a sponsor logo's box may get, as a multiple of its height.
 *
 * A safety valve, not a design decision. Every logo is drawn at the same height
 * and takes whatever width its shape asks for, which is the point — but a 10:1
 * banner would then be ten box-widths of the row on its own. Past this the box
 * stops growing and `object-fit: contain` makes that one logo shorter instead,
 * which is the only case where the equal-height rule gives way. 5:1 is well past
 * any normal wordmark.
 */
const FC_LOGO_AR_MAX = 5.0;
/** And the tallest, for the same reason in the other direction. */
const FC_LOGO_AR_MIN = 0.4;

/**
 * The box a logo of this shape is drawn in, as multiples of the row's base size.
 *
 * TWO EXTREMES, and this sits halfway between them.
 *
 *   Fit everything into one SQUARE box. A wide wordmark is then limited by its
 *   WIDTH, so `contain` pulls its height down — a 4:1 mark comes out a quarter
 *   the height of the square one beside it, which looks like a mistake.
 *
 *   Give everything the same HEIGHT and let width follow. Now they match, but a
 *   4:1 mark is four box-widths of the row on its own and dominates it.
 *
 * So: scale a wide logo up only HALF as far as filling the height would take it.
 * The width lands midway between one box and `ar` boxes:
 *
 *     w = (1 + ar) / 2        h = w / ar
 *
 * A square is unchanged at 1x1. A 3:1 wordmark is 2 boxes wide and two thirds of
 * the height, rather than 3 wide and full height. Big enough to read as an equal,
 * small enough that the row is still a row.
 *
 * A TALL logo (ar < 1) is already limited by the height and gets none of this —
 * there is no scaling up to halve.
 *
 * Taken server-side from the media library rather than measured in the browser,
 * which matters for two reasons: the cell's size is then known at first paint so
 * nothing reflows when the images decode, and the belt measures the right width
 * the first time instead of deciding whether to scroll from a row of empty boxes.
 *
 * Returns a 1x1 square when the shape is unknown, because that is the one box
 * that cannot be wrong in a way that hides anything: `contain` letterboxes inside
 * it rather than cropping.
 *
 * @return array{w: float, h: float} multiples of --fc-logo-box
 */
function fc_logo_box(int $w, int $h): array {
    if ($w <= 0 || $h <= 0) return ['w' => 1.0, 'h' => 1.0];

    $ar = max(FC_LOGO_AR_MIN, min(FC_LOGO_AR_MAX, $w / $h));
    // Half the scale-up, and only where there IS one: a tall logo already fills
    // the height.
    $hf = $ar > 1 ? (1 + $ar) / (2 * $ar) : 1.0;

    return ['w' => round($hf * $ar, 4), 'h' => round($hf, 4)];
}

/**
 * `#RRGGBB` (or `#RGB`) at a given alpha, as `rgba(…)`.
 *
 * Written out rather than reached for with color-mix() because the result is
 * interpolated into a gradient with a dozen stops: one unsupported function
 * makes the WHOLE gradient invalid, and the shine would vanish rather than
 * degrade. rgba() has no such cliff.
 */
function fc_hex_rgba(string $hex, float $alpha): string {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return 'transparent';
    return sprintf(
        'rgba(%d, %d, %d, %s)',
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
        rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.') ?: '0'
    );
}

/**
 * How long one shine cycle lasts, in seconds.
 *
 * Shared because both halves have to agree: the `animation` in site.css runs for
 * this long, and the template spreads exactly one cycle of delay across a tier's
 * logos so the highlight crosses the row once. If they drift apart the sweep
 * stops being continuous — which is a thing you would notice and not be able to
 * name.
 */
const FC_SHINE_CYCLE = 3.0;

/**
 * The shine sweep's gradient — in HARD STEPS, not a smooth ramp.
 *
 * This is half of what makes the sweep read as a mosaic rather than a gloss. A
 * normal `linear-gradient(transparent, colour, transparent)` interpolates every
 * pixel, so no amount of masking will make it look quantised: the tiles would
 * each hold a smooth ramp of their own. Repeating each alpha level as a PAIR of
 * stops at the same two offsets gives a flat band instead, so the sweep is a
 * row of discrete slabs of colour before the tile mask ever touches it.
 *
 * The other half is the tile mask and the `steps()` timing in site.css.
 *
 * 100deg, so the bands lean — a vertical sweep across a logo reads as a wipe,
 * and the slant is what makes it read as a highlight passing over.
 */
function fc_sponsor_shine_gradient(string $colour): string {
    if ($colour === '') return '';
    // Up hard, down hard. Deliberately not symmetric-smooth: the leading edge is
    // brighter than the trailing one, which is how a highlight actually moves.
    //
    // NARROW AND TRANSLUCENT, and both numbers matter. The first version spanned
    // 26%-74% of the logo and peaked at full opacity, which meant that at any
    // moment roughly half of every logo was covered in solid tier colour. With
    // the mosaic tiles on top that did not read as a highlight passing over a
    // mark — it read as the image being corrupted. A highlight is a thin bright
    // line you can see the artwork through.
    $profile = [0.06, 0.18, 0.38, 0.58, 0.38, 0.18, 0.06];
    $from = 40.0;                        // where the sweep starts, in % of width
    $to   = 60.0;
    $band = ($to - $from) / count($profile);

    $stops = ['transparent 0%', 'transparent ' . $from . '%'];
    foreach ($profile as $i => $a) {
        $c  = fc_hex_rgba($colour, $a);
        $s0 = round($from + $i * $band, 2);
        $s1 = round($from + ($i + 1) * $band, 2);
        $stops[] = $c . ' ' . $s0 . '%';
        $stops[] = $c . ' ' . $s1 . '%';
    }
    $stops[] = 'transparent ' . $to . '%';
    $stops[] = 'transparent 100%';

    return 'linear-gradient(100deg, ' . implode(', ', $stops) . ')';
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
 * What fc_format() would render, as plain text: the links unwrapped and the
 * *accent* and %fine% markers taken off, leaving the words the reader sees.
 *
 * For anywhere the text has to exist twice — once as markup and once as a plain
 * string. The FAQ is the case: assets/scramble.js animates character by
 * character, which it can only do on text, so each row carries the plain form
 * for the animation and the HTML to swap in once it settles. Without this the
 * asterisks would be part of the animation and then vanish at the end.
 */
function fc_format_plain(string $s): string {
    $out = fc_strip_inline_links($s);
    // The same two patterns fc_format_styles() turns into spans.
    $out = (string) preg_replace('/\*([^\*\n]+?)\*/u', '$1', $out);
    $out = (string) preg_replace('/%([^%\n]+?)%/u', '$1', $out);
    return $out;
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
    <div class="py-16 md:py-24 text-center fc-label text-ink-muted">
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
 * The six tier names the theme used to ship with.
 *
 * MIGRATION ONLY. Tiers are rows in `fc_sponsors` now, named by whoever adds
 * them, so nothing on the front end or in the dashboard reads this — it exists
 * so fc_migrate_sponsors_to_tiers() can give a tier whose label was never
 * customised the name it was displaying under. Delete it once no install can
 * still be holding the pre-tier shape.
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

/* fc_sponsor_tier_label() was here. It resolved one of six fixed tier slugs to
   its admin-set name; a tier's name is a field on the tier itself now, so there
   is nothing left to resolve. The migration reads `fc_sponsors_tiers` directly —
   it needs the raw EL/EN pair, not the active language. */

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
 * The way back from a standalone page — "< / BACK HOME", the eyebrows' shape
 * ("02 / Speakers") pointing backwards.
 *
 * Every page that has one prints the same thing: /speakers/, the Code of
 * Conduct, the attributions, a news article. The label comes from the caller,
 * so a news piece can say "News" while the rest say "Back home"; a leading "←"
 * or "<" typed into the string is dropped, since the mark is drawn here.
 *
 * It is set in whatever face the line it sits on uses — .fc-label, the small
 * mono — and only the "<" is coloured: accent at rest, the house orange under
 * the pointer, which is what the ">" on every button does. The rules live in
 * assets/site.css (.fc-back); they are written out there rather than left to a
 * `hover:text-accent` utility, because fc.css's unlayered `a { color: inherit }`
 * beats any layered Tailwind utility whatever its specificity.
 */
function fc_back_link(string $url, string $label): void {
    $label = trim((string) preg_replace('/^[\s←<\/]+/u', '', $label));
    if ($label === '') return;
    echo '<a class="fc-back" href="' . esc_url($url) . '">'
        . '<span class="fc-back-mark" aria-hidden="true">&lt;</span> / '
        . esc_html($label)
        . '</a>';
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
        // fc-btn + fc-btn-size: the sponsor tier title's face and size
        // (assets/site.css). Not font-display — that class would pin the
        // weight at 500.
        'class'        => 'fc-btn fc-btn-size accent-link text-ink inline-flex items-baseline gap-2 whitespace-nowrap',
        'el_class'     => 'text-base md:text-xl opacity-50',
        'el_prefix'    => '/ ',
        // A ">" in Qaroxe, in the accent — .fc-arrow in assets/site.css.
        'arrow'        => '>',
        'target_blank' => false,
    ];
    $a = array_merge($defaults, $args);

    // Trailing arrow stripping. The seeds historically shipped some labels with
    // a hard-coded "→", and an editor could type either arrow by hand; the
    // template owns the arrow via $a['arrow'], so a typed one would double up.
    $strip = static function (string $s): string {
        return (string) preg_replace('/[\s→>]+$/u', '', $s);
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
    $arrow_html = ((string) $a['arrow'] !== '')
        ? '<span class="fc-arrow" aria-hidden="true">' . esc_html((string) $a['arrow']) . '</span>'
        : '';

    echo '<span class="fc-cta-text">';
    if ($has_hover) {
        /* BOTH labels are printed, and the button is as wide as the wider of
         * them from the start — otherwise a hover text longer than the label
         * grows the button, and everything laid out beside it moves (the two
         * sponsor CTAs sit in one evenly-spaced row: hover the right one and the
         * left one slides).
         *
         * The two ghosts share one grid cell, so the cell measures the longer
         * one; the live label is absolutely positioned over that cell, so what
         * the scramble is drawing mid-flight — which is neither string — cannot
         * resize anything either. The ghosts are hidden but still take space,
         * and aria-hidden keeps the label from being read three times. */
        echo '<span class="fc-cta-stack">';
        /* The ">" travels WITH the words, inside the live layer, so it sits
         * where the visible label ends — as it does on the hero's buttons —
         * rather than at the far end of the width the ghosts have reserved.
         * It is a sibling of the scrambled span, never inside it:
         * hover-scramble.js writes textContent, which would eat it. */
        echo '<span class="fc-cta-live">'
            . '<span'
            . ' data-fc-hover-default="' . esc_attr($default) . '"'
            . ' data-fc-hover-alt="'     . esc_attr($alt)     . '">'
            . fc_format($default) . '</span>'
            . $arrow_html
            . '</span>';
        // The ghosts carry one too, so the width they reserve is the width a
        // label AND its arrow need.
        echo '<span class="fc-cta-ghost" aria-hidden="true">' . fc_format($default) . $arrow_html . '</span>';
        echo '<span class="fc-cta-ghost" aria-hidden="true">' . fc_format($alt) . $arrow_html . '</span>';
        echo '</span>';
    } else {
        echo '<span>' . fc_format($default) . '</span>';
    }
    echo '</span>';
    // Without a hover label there is no stack to put it in: the arrow stays a
    // child of the link, spaced by the row's own gap.
    if (!$has_hover) {
        echo $arrow_html;
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
