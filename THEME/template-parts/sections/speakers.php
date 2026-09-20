<?php
/**
 * Speakers — a row of cut-out portraits standing on the bottom of the section.
 *
 * No card, no panel, no background of its own: the section's paper dot-grid runs
 * straight through and each speaker is just three things stacked on it —
 *
 *   NAME    one word per line, in the display face. The LAST line is always
 *           outlined rather than filled, which is the whole signature of the
 *           layout: "GABE" solid, "NEWELL" hollow underneath it.
 *   ROLES   small mono lines under the name, one per line as entered.
 *   PHOTO   a cut-out with an outline around it, standing ON the section's
 *           bottom edge — not floating in the middle of the section's height.
 *           The section's bottom padding is cancelled for exactly this reason.
 *
 * The row behaves one of two ways, decided at runtime by
 * assets/speakers-carousel.js from the measured widths:
 *
 *   • Not enough speakers to overflow — they sit centred and still. No drag, no
 *     duplication. A three-speaker conference should not have a carousel
 *     limping along with a gap in it.
 *   • Wider than the screen — the list is duplicated and drifts continuously,
 *     and can be dragged on desktop. No arrow buttons: the row already moves on
 *     its own, and dragging is the control.
 *
 * The markup here is the STILL version. Everything the moving version needs is
 * added by the script, so the section renders correctly with JS off.
 *
 * The row is a SAMPLE: its heading links to /speakers/, where the same cards are
 * laid out in lines (template-parts/speakers-page.php). The card itself and its
 * styles are shared — see the two partials below — so the two lists cannot drift
 * apart.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section  = $args['section'] ?? [];
$speakers = fc_section_data($section);
if (!is_array($speakers)) $speakers = [];

$meta = fc_section_meta('speakers', [
    'title_el' => 'Άνθρωποι που εμφανίστηκαν',
    'title_en' => 'People who showed up.',
]);
$style = fc_speakers_style();

/* The rows, and the one font size every name is set at — built by
   fc_speaker_cards(), which /speakers/ uses as well so the two lists are the
   same cards. The portrait's ring, and the SVG filter that draws it, live in
   template-parts/partials/speaker-card.php.

   Only the HIGHLIGHTS here: the row is a selection the dashboard makes, ticked
   speaker by speaker, and /speakers/ is where everybody is. */
$built   = fc_speaker_cards(fc_speakers_featured($speakers));
$cards   = $built['cards'];
$longest = $built['longest'];

// `fc-section-flush` cancels the section's bottom padding so the portraits can
// stand on its edge. Only when there is something to stand there — the TBA
// placeholder still wants its normal breathing room.
// fc-section-bleed lifts the wrapper's 1440px cap so the row can run to the
// section's edges; assets/site.css hands that cap straight back to everything in
// the section that is not the row. Same mechanism the sponsor belts use.
$section_class = 'fc-section-dots' . ($cards ? ' fc-section-flush fc-section-bleed' : '');
// title_class: the sponsors' heading face and size (assets/site.css).
// title_href: the heading is the way to the full list, with the site's ">" after
// it — the row here is a moving sample, /speakers/ is everybody. Only when there
// is somebody to list.
fc_section_open($section, array_merge($meta, [
    'class'       => $section_class,
    // fc-spk-title: only so the stylesheet can close the gap under the heading
    // when the indicator is showing — see the :has() rule beside the dots.
    'title_class' => 'fc-section-heading fc-spk-title',
    'title_href'  => $cards ? fc_speakers_permalink() : '',
]));
?>
    <?php if (empty($cards)) : ?>
        <?php fc_render_tba('speakers'); ?>
    <?php else : ?>
        <?php /* data-fc-drag on the WRAP, not on the rail: assets/belt-drag.js
                 drags by scrubbing every belt animation inside the element it is
                 given, and the indicator's animation is up here beside the row
                 rather than inside it. Given the wrap, the hand moves both. */ ?>
        <div class="fc-spk-wrap" data-fc-speakers data-fc-drag
             style="--fc-spk-rim: <?php echo esc_attr($style['rim']); ?>;
                    --fc-spk-rim-hover: <?php echo esc_attr($style['rim_hover']); ?>;
                    --fc-spk-longest: <?php echo (int) $longest; ?>;">

            <?php /* The two ring filters, once for the page — every portrait here
                     and on /speakers/ points at one of them. */ ?>
            <?php get_template_part('template-parts/partials/speaker-filters'); ?>

            <?php /* The indicator, above the names: one dot per speaker, filling as
                     the belt travels one full set — the sponsors' dots exactly,
                     down to the arithmetic, in the accent blue over ink.

                     Rendered whether or not the row ends up looping, and hidden by
                     CSS until it does: the loop/no-loop decision is MEASURED at
                     runtime and re-taken on every resize, so PHP cannot know it,
                     and a row that never moves has no progress to report.

                     aria-hidden, as the sponsors' are: it reports the position of a
                     decorative drift, and the speakers are in the accessibility
                     tree below it. */ ?>
            <div class="fc-spk-dots" aria-hidden="true">
                <?php foreach ($cards as $d => $unused) : ?>
                    <span class="fc-spk-dot" style="--fc-dot-i: <?php echo (int) $d; ?>;">
                        <span class="fc-spk-dot-fill"></span>
                    </span>
                <?php endforeach; ?>
            </div>

            <div class="fc-spk-viewport" data-fc-spk-viewport>
                <ol class="fc-spk-rail" data-fc-spk-rail>
                    <?php foreach ($cards as $i => $card) : ?>
                        <?php
                        /* `sizes` describes --fc-spk-photo-h below: clamp(260px,
                           26vw, 420px). 26vw reaches 260px at a 1000px viewport
                           and 420px at about 1615px, which is where the two
                           breakpoints come from. Keep the three in step if that
                           clamp changes. */
                        get_template_part('template-parts/partials/speaker-card', null, [
                            'card'  => $card,
                            'index' => $i,
                            'sizes' => '(max-width: 999px) 260px, (min-width: 1615px) 420px, 26vw',
                        ]);
                        ?>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
    <?php endif; ?>
<?php
fc_section_close();

/* The card's styles, shared with /speakers/ — printed once per page. */
get_template_part('template-parts/partials/speakers-style');
?>
