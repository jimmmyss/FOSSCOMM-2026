<?php
/**
 * Manifesto — body always renders both EL and EN columns.
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

fc_bi_block($body['el'], $body['en']);

if (!empty($stats)) : ?>
    <div class="mt-20 grid grid-cols-1 sm:grid-cols-3 gap-8 border-t border-border pt-12">
        <?php foreach ($stats as $stat) :
            $number = (string) ($stat['number'] ?? '');
            $label  = fc_bi($stat, 'label');
            if ($number === '' && $label['el'] === '' && $label['en'] === '') continue;
            ?>
            <div>
                <div class="font-display text-6xl md:text-7xl text-ink leading-none" <?php echo fc_island_attrs('scramble'); ?>>
                    <?php echo fc_format($number); ?>
                </div>
                <div class="mt-3 font-mono text-[11px] uppercase tracking-widest text-ink-muted">
                    <?php echo fc_bi_inline($label['el'], $label['en']); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif;
fc_section_close();
