<?php
/**
 * Get Involved — three columns, each a title (the Sponsors headline's size), a
 * small line under it (the sponsor tiers' label face) and a description. Side by
 * side while they fit, one under the other when they do not.
 *
 * The first column, Participate, is built like the Sponsors funding line: its
 * title is "<first text> <counter text> <last text>", and the counter text is
 * drawn outlined with a fill inside it that runs out, left to right, between a
 * start and a stop time — a progress bar made of the word itself. The small line
 * under it counts down to the stop time. All of it is edited in
 * FOSSCOMM → Get Involved; see fc_involved_values() for the fields.
 *
 * Every title takes the sponsor tiers' two markers: *single* sets a run in the
 * accent blue, **double** draws it outlined.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$v       = fc_involved_values((array) fc_section_data($section));

/* A title part with its markers turned into spans. Lines typed into the field
   are joined — a column title runs as one heading and wraps where it has to. */
$fc_gi_marked = static function (string $text): string {
    return implode(' ', fc_hollow_split($text, '/\R/u', 'is-accent', 'is-outline'));
};

/* "12D 04H 22M 10S" — the same form the script ticks, so the first paint and
   the first tick agree. */
$fc_gi_clock = static function (int $seconds): string {
    $s = max(0, $seconds);
    return sprintf('%dD %02dH %02dM %02dS', intdiv($s, 86400), intdiv($s % 86400, 3600), intdiv($s % 3600, 60), $s % 60);
};

// ── Participate ──────────────────────────────────────────────────────────────
$p_title1  = fc_one(fc_bi($v, 'p_title1'));
$p_counter = fc_one(fc_bi($v, 'p_counter'));
$p_title2  = fc_one(fc_bi($v, 'p_title2'));
$p_body    = fc_one(fc_bi($v, 'p_body'));
$p_btn     = fc_bi($v, 'p_btn');
$p_btn_h   = fc_bi($v, 'p_btn_hover');
$p_url     = (string) ($v['p_url'] ?? '');
$p_closed  = fc_one(fc_bi($v, 'p_closed'));
if ($p_closed === '') $p_closed = fc_t('cfp_closed');
$p_start   = fc_site_time((string) ($v['p_start'] ?? ''));
$p_stop    = fc_site_time((string) ($v['p_stop'] ?? ''));

/* Both live numbers measure the SAME thing: how much of the start→stop window
   is left. Before the window opens that is the whole of it — a 1→11 December
   window reads "10D 00H …" today, not the wait until December — and once it is
   open it is the time to the stop. The fill is that number as a percentage, so
   the word is full exactly while the clock still shows the whole window.
   With no start time there is no window to measure: the clock counts to the
   stop and the word stays full until then. Worked out here as well as in the
   script, so the page is right before the first tick (and without it). */
$now = time();
$p_left = 100.0;
$p_remaining = 0;
if ($p_stop) {
    $stop  = $p_stop->getTimestamp();
    $start = $p_start ? $p_start->getTimestamp() : null;
    $from  = ($start !== null && $start < $stop && $now < $start) ? $start : $now;
    $p_remaining = max(0, $stop - $from);
    if ($start !== null && $stop > $start) {
        $p_left = 100.0 * $p_remaining / ($stop - $start);
    } elseif ($now >= $stop) {
        $p_left = 0.0;
    }
    $p_left = max(0.0, min(100.0, $p_left));
}
$p_is_closed = $p_stop && $now >= $p_stop->getTimestamp();

$columns = [];
if ($p_title1 !== '' || $p_counter !== '' || $p_title2 !== '' || $p_body !== '' || fc_one($p_btn) !== '') {
    $columns[] = ['kind' => 'participate'];
}
foreach ([2, 3] as $n) {
    $t   = fc_one(fc_bi($v, 'c' . $n . '_title'));
    $b   = fc_one(fc_bi($v, 'c' . $n . '_body'));
    $btn = fc_bi($v, 'c' . $n . '_btn');
    if ($t === '' && $b === '' && fc_one($btn) === '') continue;
    $columns[] = [
        'kind'    => 'plain',
        'title'   => $t,
        'label'   => fc_one(fc_bi($v, 'c' . $n . '_label')),
        'body'    => $b,
        'btn'     => $btn,
        'btn_h'   => fc_bi($v, 'c' . $n . '_btn_hover'),
        'url'     => (string) ($v['c' . $n . '_url'] ?? ''),
    ];
}

/* The button under a column: the site's button, exactly as the Get Involved
   cards had it — not nowrap, so a long label wraps inside its column. */
$fc_gi_button = static function (array $label, array $hover, string $url): void {
    if (fc_one($label) === '') return;
    echo '<div class="fc-gi-cta">';
    fc_cta_link([
        'url'      => $url !== '' ? $url : '#',
        'en'       => $label['en'],
        'el'       => $label['el'],
        'hover_en' => $hover['en'],
        'hover_el' => $hover['el'],
        'class'    => 'fc-btn fc-btn-size accent-link text-ink inline-flex items-baseline gap-2',
    ]);
    echo '</div>';
};

/* No section title and no intro any more: the three columns ARE the section.
   The eyebrow ("07 / Get Involved") stays, as it does on Sponsors. */
fc_section_open($section, [
    'title_el' => '',
    'title_en' => '',
]);
?>
    <?php if ($columns) : ?>
        <div class="fc-gi">
            <?php foreach ($columns as $col) : ?>
                <?php if ($col['kind'] === 'participate') :
                    $has_clock = (bool) $p_stop;
                    ?>
                    <div class="fc-gi-col"<?php if ($has_clock) : ?>
                         data-fc-gi
                         data-stop="<?php echo esc_attr($p_stop->format('c')); ?>"
                         <?php if ($p_start) : ?>data-start="<?php echo esc_attr($p_start->format('c')); ?>"<?php endif; ?>
                         data-closed="<?php echo esc_attr($p_closed); ?>"<?php endif; ?>>
                        <h3 class="fc-gi-title"><?php
                            if ($p_title1 !== '') echo $fc_gi_marked($p_title1) . ' ';
                            if ($p_counter !== '') {
                                echo '<span class="fc-gi-counter" data-fc-gi-counter style="--fc-gi-left: '
                                    . esc_attr((string) round($p_left, 3)) . ';">' . esc_html($p_counter) . '</span>';
                            }
                            if ($p_title2 !== '') echo ' ' . $fc_gi_marked($p_title2);
                        ?></h3>
                        <?php if ($has_clock) : ?>
                            <?php // The clock alone — no words in front of it — until it lapses,
                                  // when the whole line becomes the closed text. ?>
                            <p class="fc-gi-under fc-label" data-fc-gi-line><?php if ($p_is_closed) :
                                echo esc_html($p_closed);
                            else :
                                ?><span class="fc-gi-clock" data-fc-gi-clock><?php echo esc_html($fc_gi_clock($p_remaining)); ?></span><?php
                            endif; ?></p>
                        <?php endif; ?>
                        <?php if ($p_body !== '') : ?>
                            <div class="fc-gi-body fc-copy"><?php echo wp_kses_post(fc_format_block($p_body)); ?></div>
                        <?php endif; ?>
                        <?php $fc_gi_button($p_btn, $p_btn_h, $p_url); ?>
                    </div>
                <?php else : ?>
                    <div class="fc-gi-col">
                        <?php if ($col['title'] !== '') : ?>
                            <h3 class="fc-gi-title"><?php echo $fc_gi_marked($col['title']); ?></h3>
                        <?php endif; ?>
                        <?php if ($col['label'] !== '') : ?>
                            <p class="fc-gi-under fc-label"><?php echo esc_html($col['label']); ?></p>
                        <?php endif; ?>
                        <?php if ($col['body'] !== '') : ?>
                            <div class="fc-gi-body fc-copy"><?php echo wp_kses_post(fc_format_block($col['body'])); ?></div>
                        <?php endif; ?>
                        <?php $fc_gi_button($col['btn'], $col['btn_h'], $col['url']); ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php /* The live parts: the counter's fill and the clock under it, once a
                 second. The fill is a registered custom property with a one-second
                 linear transition, so ticking it every second reads as a smooth
                 run-down rather than a step. Only this section's elements. */ ?>
        <script>
        (function () {
            function pad(n) { return (n < 10 ? '0' : '') + n; }
            function clock(ms) {
                var s = Math.max(0, Math.floor(ms / 1000));
                return Math.floor(s / 86400) + 'D ' + pad(Math.floor(s % 86400 / 3600)) + 'H '
                     + pad(Math.floor(s % 3600 / 60)) + 'M ' + pad(s % 60) + 'S';
            }
            document.querySelectorAll('[data-fc-gi]').forEach(function (col) {
                var stop  = new Date(col.getAttribute('data-stop')).getTime();
                var start = col.hasAttribute('data-start') ? new Date(col.getAttribute('data-start')).getTime() : NaN;
                if (isNaN(stop)) return;
                var counter = col.querySelector('[data-fc-gi-counter]');
                var line    = col.querySelector('[data-fc-gi-line]');
                var face    = col.querySelector('[data-fc-gi-clock]');
                var iv;
                function tick() {
                    var now = Date.now();
                    // What is left OF THE WINDOW: all of it before the start, the
                    // time to the stop once it is open. Same rule as the PHP.
                    var window_ = (!isNaN(start) && stop > start) ? stop - start : 0;
                    var from = (window_ && now < start) ? start : now;
                    var remaining = Math.max(0, stop - from);
                    var left = 100;
                    if (window_) left = Math.max(0, Math.min(100, 100 * remaining / window_));
                    else if (now >= stop) left = 0;
                    if (counter) counter.style.setProperty('--fc-gi-left', left.toFixed(3));
                    if (now >= stop) {
                        if (line) line.textContent = col.getAttribute('data-closed') || '';
                        clearInterval(iv);
                        return;
                    }
                    if (face) face.textContent = clock(remaining);
                }
                tick();
                iv = setInterval(tick, 1000);
            });
        })();
        </script>
    <?php endif; ?>

<style>
/* ── Get Involved: three columns ─────────────────────────────────────────────
   One on a phone, two from 768, three from 1280 — side by side while they fit,
   one under the other when they do not. */
.fc-gi {
    --fc-gi-gap-x: 3rem;
    --fc-gi-gap-y: 2.5rem;
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--fc-gi-gap-y) var(--fc-gi-gap-x);
    /* stretch, not start: a column's box then runs the full height of its row,
       so the divider beside it is a full-height line rather than one as short as
       its own text. The content inside still sits at the top. */
    align-items: stretch;
}
@media (min-width: 768px)  { .fc-gi { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1280px) { .fc-gi { grid-template-columns: repeat(3, 1fr); } }

/* ── The dividers, as in the Manifesto ──────────────────────────────────────
   A hairline between consecutive columns: vertical down the gutter while they
   are side by side, horizontal across the gap once they stack. Half the gap is
   borrowed back with a negative margin and handed to padding, so the line lands
   in the MIDDLE of the gutter instead of against the text, and the text does not
   move when the line appears. */
.fc-gi-col {
    border: 0 solid var(--color-border, color-mix(in oklab, #0A0A0A 12%, transparent));
}
.fc-gi-col + .fc-gi-col {
    margin-top: calc(var(--fc-gi-gap-y) / -2);
    padding-top: calc(var(--fc-gi-gap-y) / 2);
    border-top-width: 1px;
}
@media (min-width: 768px) {
    /* Two across: the second column takes the vertical line; the third drops to
       a new row, so it takes a horizontal one instead. */
    .fc-gi-col + .fc-gi-col { margin-top: 0; padding-top: 0; border-top-width: 0; }
    .fc-gi-col:nth-child(2n) {
        margin-left: calc(var(--fc-gi-gap-x) / -2);
        padding-left: calc(var(--fc-gi-gap-x) / 2);
        border-left-width: 1px;
    }
    .fc-gi-col:nth-child(n + 3) {
        margin-top: calc(var(--fc-gi-gap-y) / -2);
        padding-top: calc(var(--fc-gi-gap-y) / 2);
        border-top-width: 1px;
    }
}
@media (min-width: 1280px) {
    /* Three across: every column after the first takes a vertical line, and
       nothing wraps, so the horizontal one goes away. */
    .fc-gi-col:nth-child(n + 2) {
        margin-left: calc(var(--fc-gi-gap-x) / -2);
        padding-left: calc(var(--fc-gi-gap-x) / 2);
        border-left-width: 1px;
    }
    .fc-gi-col:nth-child(n + 3) { margin-top: 0; padding-top: 0; border-top-width: 0; }
}

/* The sponsor tier titles' face and size — --fc-title-size, the one token the
   tier titles and every button on the site share, so a title here and the button
   under it are the same size and all three columns match each other. Case is the
   author's: these are sentences, not names. */
.fc-gi-title {
    margin: 0;
    font-family: var(--font-display, "Space Grotesk"), ui-sans-serif, system-ui, sans-serif;
    font-weight: 700;
    letter-spacing: -0.04em;
    line-height: 1.05;
    color: var(--color-ink, #0A0A0A);
    font-size: var(--fc-title-size-sm, 24px);
}
@media (min-width: 768px) {
    .fc-gi-title { font-size: var(--fc-title-size, 36px); }
}

/* The markers, as on the sponsor tier titles: *single* in the accent, **double**
   outlined in it. Outside the @supports, so an engine without text-stroke shows
   solid accent type rather than nothing.

   Both are set in QAROXE — the pixel face the counter word beside them uses, and
   the one every *marked* run elsewhere on the site is set in (.fc-accent in
   assets/site.css). A marked run in this section now reads the same as a marked
   run in a section heading. The face has ONE weight and no Greek: asking for 400
   and refusing a synthetic bold keeps a heading's 700 from smearing the pixel
   steps, and a Greek run simply falls back to the title's own face. */
.fc-gi-title .is-accent,
.fc-gi-title .is-outline {
    color: var(--color-accent, #0033FF);
    font-family: "Qaroxe", var(--font-display, "Space Grotesk"), ui-sans-serif, system-ui, sans-serif;
    font-weight: 400;
    font-synthesis: none;
    -webkit-font-synthesis: none;
    letter-spacing: 0;

    /* Zero-height inline box, the same fix .fc-accent carries in assets/site.css
       and for the same reason: a line box's height depends on where the baseline
       sits inside it (ascent − descent), and Qaroxe's 80/20 override puts that
       11 points away from Space Grotesk's. Stack two such boxes on one baseline
       and the line grows by half the difference — invisible while the title fits
       on one line, plainly uneven the moment it wraps and only the second line
       holds a marked run (which is exactly what these three columns do on a
       phone). A box of zero height cannot stretch anything; the glyphs sit on
       the baseline, which line-height never moves. */
    line-height: 0;
}
@supports (-webkit-text-stroke: 1px black) {
    .fc-gi-title .is-outline {
        color: transparent;
        -webkit-text-stroke: 0.035em var(--color-accent, #0033FF);
    }
}

/* ── The counter word: a progress bar made of the word itself ───────────────
   Built exactly as the funding figure in Sponsors is, only running sideways:
   two flat layers clipped to the glyphs — solid accent across the first
   --fc-gi-left per cent of the word, the faint grey of an empty glass behind
   the rest. A hard edge between them, no fade and no transparency, and the
   letters are solid rather than outlined, so the word reads as a bar that has
   drained from the right.

   --fc-gi-left is registered, so the script's one-second ticks glide instead
   of stepping. Without background-clip: text the word is solid accent — never
   an empty outline that would say "closed" when it is not. */
@property --fc-gi-left {
    syntax: '<number>';
    inherits: true;
    initial-value: 100;
}
.fc-gi-counter {
    white-space: nowrap;
    color: var(--color-accent, #0033FF);
    /* Qaroxe, the accent face — as the funding amount in Sponsors is. It is a
       bitmap face with one weight and no Greek, so: no synthesised bold (the
       browser would smear the pixel steps), and a Greek counter word falls back
       to the heading's own face. */
    font-family: "Qaroxe", var(--font-display, "Space Grotesk"), ui-sans-serif, system-ui, sans-serif;
    font-weight: 400;
    font-synthesis: none;
    letter-spacing: 0;
    /* As the marked runs above: the counter is another Qaroxe box inline in the
       title, so it gets the same zero-height treatment or the line it lands on
       stands taller than the others. The fill is unaffected — an inline box
       paints its background over the CONTENT area, which the font's own metrics
       set, not over the line box. */
    line-height: 0;
}
@supports ((-webkit-background-clip: text) or (background-clip: text)) {
    .fc-gi-counter {
        background-image:
            linear-gradient(var(--color-accent, #0033FF), var(--color-accent, #0033FF)),
            linear-gradient(var(--color-ink-faint, #C9C7BF), var(--color-ink-faint, #C9C7BF));
        background-size: calc(var(--fc-gi-left) * 1%) 100%, 100% 100%;
        background-repeat: no-repeat, no-repeat;
        background-position: left top, left top;
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        color: transparent;
        transition: --fc-gi-left 1s linear;
    }
}
@media (prefers-reduced-motion: reduce) {
    .fc-gi-counter { transition: none; }
}

/* The small line under a title: the sponsor tiers' label face, ink and spacing —
   5px off the title, exactly as .fc-tier-desc sits above its own. */
.fc-gi-under {
    margin: 5px 0 0;
    line-height: 1.6;
    color: var(--color-ink-muted, #6B6B66);
}
.fc-gi-clock { font-variant-numeric: tabular-nums; white-space: nowrap; }

/* The description: size and measure only. The COLOUR comes from .fc-copy in
   assets/site.css, which every block of running text on the site now carries —
   these three used to be full ink and so read heavier than the same kind of
   writing beside the map. */
.fc-gi-body {
    margin-top: 1.25rem;
    max-width: 28rem;
    font-size: 1.125rem;
    line-height: 1.625;
}
.fc-gi-body > * + * { margin-top: 0.75rem; }

/* The button, back under the description and in the site's button size. */
.fc-gi-cta { margin-top: 1.5rem; }
</style>
<?php
fc_section_close();
