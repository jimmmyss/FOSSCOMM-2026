<?php
/**
 * Communities — the section under Speakers, and its sibling in every way but
 * one: the row runs the OTHER WAY, left to right.
 *
 * The speakers' form factor: no panel, the dot-grid runs through, the row bleeds
 * to the section's edges and stands on its bottom edge, the dots above it report
 * how far round the belt has come, and the heading is the way to the whole list
 * at /communities/. Each card is template-parts/partials/community-card.php —
 * the speakers' name and small lines over a logo in a square the size of a
 * portrait.
 *
 * The movement is the speakers' machinery: assets/speakers-belt.js decides
 * whether the row loops and copies it, assets/belt-drag.js lets a hand take it,
 * and data-fc-belt-dir="right" turns it round. The markup is the STILL version.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$built   = fc_communities_items(fc_communities_list());
$items   = $built['items'];

$meta = fc_section_meta('communities', [
    'title_el' => 'Κοινότητες που χτίζουν μαζί μας.',
    'title_en' => 'Communities that build with us.',
]);
// The speakers' outline pair, for the hollow run in each name: the two sections
// sit one above the other, and two different blues would read as a mistake.
$style = fc_speakers_style();

/* No fc-section-flush, unlike Speakers: the logos do not stand on the section's
   edge, so the section keeps its bottom padding and has the same air below the
   row as above the heading. */
fc_section_open($section, array_merge($meta, [
    'class'       => 'fc-section-dots' . ($items ? ' fc-section-bleed' : ''),
    'title_class' => 'fc-section-heading fc-com-title',
    'title_href'  => $items ? fc_communities_permalink() : '',
]));
?>
    <?php if (empty($items)) : ?>
        <?php fc_render_tba('communities'); ?>
    <?php else : ?>
        <div class="fc-com-wrap"
             data-fc-communities data-fc-drag data-fc-belt-dir="right"
             style="--fc-spk-rim: <?php echo esc_attr($style['rim']); ?>;
                    --fc-spk-rim-hover: <?php echo esc_attr($style['rim_hover']); ?>;
                    --fc-spk-longest: <?php echo (int) $built['longest']; ?>;">

            <div class="fc-com-dots" aria-hidden="true">
                <?php foreach ($items as $d => $unused) : ?>
                    <span class="fc-com-dot" style="--fc-dot-i: <?php echo (int) $d; ?>;"><span class="fc-com-dot-fill"></span></span>
                <?php endforeach; ?>
            </div>

            <div class="fc-com-viewport" data-fc-spk-viewport>
                <ol class="fc-com-rail" data-fc-spk-rail>
                    <?php foreach ($items as $i => $it) {
                        get_template_part('template-parts/partials/community-card', null, [
                            'item'  => $it,
                            'index' => $i,
                        ]);
                    } ?>
                </ol>
            </div>
        </div>
    <?php endif; ?>
<?php
fc_section_close();
/* The speakers' card styles, then this section's own. Both print once. */
get_template_part('template-parts/partials/speakers-style');
get_template_part('template-parts/partials/communities-style');
