<?php
/**
 * Single news article — rendered when /news/<slug>/ resolves to a row in
 * the fc_news option (see inc/news.php).
 *
 * The row arrives via $args['row']: { title_*, body_*, photo, date, url }.
 */
if (!defined('ABSPATH')) {
    exit;
}

$row   = is_array($args['row'] ?? null) ? $args['row'] : [];
$title = fc_bi($row, 'title');
$body  = fc_bi($row, 'body');
$photo = (string) ($row['photo'] ?? '');
$date  = (string) ($row['date']  ?? '');
$url   = (string) ($row['url']   ?? '');

$pretty_date = '';
if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $ts = strtotime($date);
    if ($ts !== false) $pretty_date = date('j F Y', $ts);
}
?>
<!-- Outer wrapper carries min-h-screen so the page still has full viewport
     height on short articles. The <section> inside is what the sidebar nav and
     the mascot's section reactions both read to decide which section you are
     in (assets/section-nav.js and assets/mascot/js/sections.js, same 0.35
     viewport trigger), so keep it wrapping the real content. -->
<div class="min-h-screen">
<section class="bg-paper border-t border-b border-border">
    <div class="max-w-[1200px] mx-auto px-4 md:px-8 py-24 md:py-32">
        <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <a href="<?php echo esc_url(home_url('/#news')); ?>" class="hover:text-accent transition-colors"><?php echo esc_html(fc_t('news_back')); ?></a>
            <?php if ($pretty_date !== '') : ?>
                <span class="opacity-50">//</span>
                <time datetime="<?php echo esc_attr($date); ?>" class="tabular-nums"><?php echo esc_html(strtoupper($pretty_date)); ?></time>
            <?php endif; ?>
        </div>

        <?php $title_text = fc_one($title); $body_text = fc_one($body); ?>
        <?php if ($title_text !== '') : ?>
            <h1 class="font-display text-4xl md:text-6xl leading-[1.05] tracking-tight m-0">
                <?php echo fc_format($title_text); ?>
            </h1>
        <?php endif; ?>

        <?php if ($photo !== '') : ?>
            <div class="mt-10 border border-border bg-paper">
                <img src="<?php echo esc_url($photo); ?>"
                     alt="<?php echo esc_attr($title_text); ?>"
                     class="block w-full h-auto"
                     loading="eager" decoding="async">
            </div>
        <?php endif; ?>

        <?php if ($body_text !== '') : ?>
            <div class="mt-12 text-lg leading-relaxed max-w-3xl">
                <div class="space-y-3">
                    <?php echo wp_kses_post(fc_format_block($body_text)); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($url !== '') : ?>
            <p class="mt-12">
                <a href="<?php echo esc_url($url); ?>"
                   target="_blank" rel="noreferrer"
                   class="font-display text-xl underline-link accent-link text-ink inline-flex items-baseline gap-2">
                    <span><?php echo esc_html(fc_t('external_source')); ?></span>
                    <span aria-hidden="true">→</span>
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>
</div>
