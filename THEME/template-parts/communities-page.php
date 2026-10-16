<?php
/**
 * All communities — the standalone page at /communities/ (routed by
 * inc/communities.php). The landing page's Communities heading links here.
 *
 * /speakers/ with communities in it: the landing page's card (the name, the small
 * lines under it, and the logo in its square) standing on a rule that runs the
 * width of the screen under every row. Each card has an id so it can be linked to.
 */
if (!defined('ABSPATH')) {
    exit;
}

$built = fc_communities_items(fc_communities_list());
$items = $built['items'];
// This page's OWN heading (FOSSCOMM → Communities → "Heading on the communities
// page"), falling back to the section's — as /speakers/ does.
$heading = fc_communities_page_title();
$title   = fc_pick($heading['el'], $heading['en']);
$style = fc_speakers_style();
?>
<div class="min-h-screen">
<section id="communities" class="relative border-t border-border fc-section-dots fc-grid-page">
    <div class="max-w-[1440px] mx-auto px-4 md:px-8 py-24 md:py-32">
        <div class="fc-label text-ink-muted mb-6 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <?php fc_back_link(home_url('/'), fc_t('back_home')); ?>
        </div>

        <?php if ($title !== '') : ?>
            <h1 class="fc-section-heading"><?php echo fc_format($title); ?></h1>
        <?php endif; ?>

        <?php if (empty($items)) : ?>
            <?php fc_render_tba('communities'); ?>
        <?php else : ?>
            <ol class="fc-com-grid"
                style="--fc-spk-rim: <?php echo esc_attr($style['rim']); ?>;
                       --fc-spk-rim-hover: <?php echo esc_attr($style['rim_hover']); ?>;
                       --fc-spk-longest: <?php echo (int) $built['longest']; ?>;">
                <?php foreach ($items as $i => $it) {
                    get_template_part('template-parts/partials/community-card', null, [
                        'item'  => $it,
                        'index' => $i,
                        'id'    => $it['slug'],
                        /* The logo's width in this grid: 45% of a column that is
                           94% of a cell, the cells 2 / 3 / 4 / 5 to a row beside the
                           200px sidebar (see the grid's rules below). */
                        'sizes' => '(max-width: 639px) 21vw, (max-width: 1023px) 14vw, (max-width: 1439px) 10.6vw, 8.5vw',
                    ]);
                } ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
</div>
<?php
get_template_part('template-parts/partials/speakers-style');
get_template_part('template-parts/partials/communities-style');
?>
<style>
/* ── All communities: the same cards, in lines ──────────────────────────────
   /speakers/' grid, rule for rule — see template-parts/speakers-page.php for
   the reasoning behind each: flex so an incomplete row is centred, the gutter
   as padding inside each cell so the rules under the cards meet, a line under
   every row running the full width of the screen, and the card cut at it. */
.fc-com-grid {
    --per-row: 2;
    --gx: 10px;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: flex-end;
    gap: clamp(2rem, 4vw, 3.5rem) 0;
    list-style: none;
    margin: 0;
    padding: 0;
}
@media (min-width: 640px)  { .fc-com-grid { --per-row: 3; } }
@media (min-width: 1024px) { .fc-com-grid { --per-row: 4; --gx: 24px; } }
@media (min-width: 1440px) { .fc-com-grid { --per-row: 5; } }

.fc-com-grid > .fc-spk {
    container-type: inline-size;
    flex: 0 0 calc(100% / var(--per-row));
    display: flex;
    padding: 0 calc(var(--gx) / 2);
    position: relative;
}
.fc-com-grid > .fc-spk::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: -100vw;
    right: -100vw;
    /* The site's line colour, like every other rule: the role grey on white,
       the role blue on the blue panel (assets/site.css). OPAQUE, which matters —
       the copies overlap along the row, and a translucent line stacked darker
       wherever they did. */
    border-bottom: 1px solid var(--color-border, #DDDDDA);
    pointer-events: none;
}
.fc-com-grid .fc-spk-card {
    clip-path: inset(-100vh -100vw 0 -100vw);
    --fc-spk-col: 94cqw;
    --fc-spk-pad: 0px;
    width: 100%;
}
.fc-com-grid .fc-spk-roles { font-size: clamp(9px, calc(var(--fc-spk-col) / 22), 12px); }
/* The logo box is square to its COLUMN here, as a portrait is on /speakers/ —
   aspect-ratio on the shared box already does it. */

@media (hover: hover) {
    .fc-com-grid .fc-com-card:hover { --fc-spk-rim: var(--fc-spk-rim-hover, #EE8101); }
}</style>
