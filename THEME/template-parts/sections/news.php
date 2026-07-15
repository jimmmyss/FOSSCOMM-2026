<?php
/**
 * News — bilingual articles (photo / title / description), rendered as a
 * responsive card grid. Editable in FOSSCOMM → News. Empty state pulls the
 * bilingual TBA copy from FOSSCOMM → TBA Text.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$rows    = fc_section_data($section);
if (!is_array($rows)) $rows = [];

$meta = fc_section_meta('news', [
    'title_el' => 'Νέα και ανακοινώσεις.',
    'title_en' => 'News & announcements.',
]);
fc_section_open($section, array_merge($meta, ['class' => 'fc-section-dots']));
?>
    <?php if (empty($rows)) : ?>
        <?php fc_render_tba('news'); ?>
    <?php else : ?>
        <ol class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-px bg-border border border-border list-none p-0 m-0">
            <?php foreach (array_values($rows) as $i => $row) :
                $title = fc_bi($row, 'title');
                if ($title['en'] === '' && $title['el'] === '') continue;
                $body  = fc_bi($row, 'body');
                $photo = (string) ($row['photo'] ?? '');
                $date  = (string) ($row['date']  ?? '');
                $permalink = fc_news_permalink_for_row($row);
                $pretty_date = '';
                if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $ts = strtotime($date);
                    if ($ts !== false) $pretty_date = strtoupper(date('d M Y', $ts));
                }
                $idx_label = sprintf('%02d/', $i + 1);
                ?>
                <li class="fc-news bg-paper p-0 m-0 relative">
                    <a href="<?php echo esc_url($permalink); ?>"
                       class="flex flex-col gap-4 p-6 h-full no-underline text-inherit">
                        <div class="flex items-center justify-between font-mono text-[11px] uppercase tracking-widest text-ink-muted">
                            <span><?php echo esc_html($idx_label); ?></span>
                            <?php if ($pretty_date !== '') : ?>
                                <time datetime="<?php echo esc_attr($date); ?>" class="tabular-nums"><?php echo esc_html($pretty_date); ?></time>
                            <?php endif; ?>
                        </div>

                        <?php $title_text = fc_one($title); $body_text = fc_one($body); ?>
                        <div class="aspect-video w-full border border-border bg-paper relative overflow-hidden">
                            <?php if ($photo !== '') : ?>
                                <img src="<?php echo esc_url($photo); ?>"
                                     alt="<?php echo esc_attr($title_text); ?>"
                                     class="absolute inset-0 w-full h-full object-cover"
                                     loading="lazy" decoding="async">
                            <?php else : ?>
                                <div class="absolute inset-0 flex items-center justify-center font-mono text-[10px] uppercase tracking-widest text-ink-faint">no image</div>
                            <?php endif; ?>
                        </div>

                        <?php if ($title_text !== '') : ?>
                            <h3 class="font-display text-xl md:text-2xl leading-[1.1] tracking-tight text-ink m-0"><?php echo fc_format($title_text); ?></h3>
                        <?php endif; ?>

                        <?php if ($body_text !== '') : ?>
                            <div class="text-sm leading-relaxed text-ink-muted space-y-2">
                                <p class="m-0"><?php echo fc_format(wp_trim_words($body_text, 28, '…')); ?></p>
                            </div>
                        <?php endif; ?>

                        <span class="mt-auto font-mono text-[11px] uppercase tracking-widest text-ink-muted inline-flex items-baseline gap-2">
                            <span><?php echo esc_html(fc_t('read_more')); ?></span><span aria-hidden="true">→</span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
<?php
fc_section_close();
?>
<style>
.fc-news { transition: background-color 200ms ease; }
.fc-news:hover { background-color: color-mix(in oklab, var(--color-accent, #0033FF) 4%, var(--color-paper, #FAFAF7)); }
.fc-news:hover h3 { color: var(--color-accent, #0033FF); transition: color 200ms ease; }
</style>
