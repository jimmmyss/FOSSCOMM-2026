<?php
/**
 * Manifesto — desktop: body (active language) on the left half, stats stacked on
 * the right half with a vertical centre line between them. Mobile: body on top,
 * a horizontal line, then the stats stacked. Consecutive stats are divided by a
 * line in both layouts.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$data    = fc_section_data($section);

$title   = fc_bi($data, 'title');
$body    = fc_bi($data, 'body');
$stats   = (array) ($data['stats'] ?? []);

fc_section_open($section, [
    'title_el' => $title['el'],
    'title_en' => $title['en'],
]);

$body_text = fc_one($body);
?>
<!-- Desktop: paragraph (left half) │ vertical centre line │ stats (right half),
     vertically centred. Mobile: paragraph on top, a horizontal line, then the
     stats stacked one-per-row. In BOTH, consecutive stats are separated by a
     divider line (N stats → N−1 lines). -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-0 items-stretch">
    <div class="text-lg leading-relaxed space-y-3 md:pr-12">
        <?php if ($body_text !== '') echo wp_kses_post(fc_format_block($body_text)); ?>
    </div>
    <?php if (!empty($stats)) : ?>
        <div class="border-t md:border-t-0 md:border-l border-border pt-8 md:pt-0 md:pl-12 flex flex-col justify-center">
            <div class="divide-y divide-border">
                <?php foreach ($stats as $stat) :
                    $number = (string) ($stat['number'] ?? '');
                    $label  = fc_bi($stat, 'label');
                    if ($number === '' && $label['el'] === '' && $label['en'] === '') continue;
                    ?>
                    <div class="py-8 first:pt-0 last:pb-0">
                        <div class="font-display text-6xl md:text-7xl text-ink leading-none" <?php echo fc_island_attrs('scramble'); ?>>
                            <?php echo fc_format($number); ?>
                        </div>
                        <div class="mt-3 font-mono text-[11px] uppercase tracking-widest text-ink-muted">
                            <?php echo fc_bi_inline($label['el'], $label['en']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
fc_section_close();
