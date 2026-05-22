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
     height on short articles. The <section> inside is the pet's platform
     (assets/pet/engine.js, platformSelector = 'section'); keeping it tight
     to the text means the border-b — the line the pet walks on — sits right
     where the content ends, instead of being pushed to the bottom of the
     viewport. <section> stays inside <main class="lg:pl-[200px]"> so the
     pet's horizontal range never crosses into the fixed sidebar. -->
<div class="min-h-screen">
<section class="bg-paper border-t border-b border-border">
    <div class="max-w-[1200px] mx-auto px-4 md:px-8 py-24 md:py-32">
        <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <a href="<?php echo esc_url(home_url('/#news')); ?>" class="hover:text-accent transition-colors">← News</a>
            <?php if ($pretty_date !== '') : ?>
                <span class="opacity-50">//</span>
                <time datetime="<?php echo esc_attr($date); ?>" class="tabular-nums"><?php echo esc_html(strtoupper($pretty_date)); ?></time>
            <?php endif; ?>
        </div>

        <?php if ($title['en'] !== '') : ?>
            <h1 class="font-display text-4xl md:text-6xl leading-[1.05] tracking-tight m-0" lang="en">
                <?php echo fc_format($title['en']); ?>
            </h1>
        <?php endif; ?>
        <?php if ($title['el'] !== '' && $title['el'] !== $title['en']) : ?>
            <p class="font-display text-2xl md:text-3xl text-ink-muted leading-tight mt-2 mb-0">
                <?php echo fc_format($title['el']); ?>
            </p>
        <?php endif; ?>

        <?php if ($photo !== '') : ?>
            <div class="mt-10 border border-border bg-paper">
                <img src="<?php echo esc_url($photo); ?>"
                     alt="<?php echo esc_attr($title['en'] ?: $title['el']); ?>"
                     class="block w-full h-auto"
                     loading="eager" decoding="async">
            </div>
        <?php endif; ?>

        <?php if ($body['en'] !== '' || $body['el'] !== '') : ?>
            <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 text-lg leading-relaxed">
                <?php if ($body['en'] !== '') : ?>
                    <div class="space-y-3" lang="en">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-muted m-0">EN / English</p>
                        <?php echo wp_kses_post(fc_format_block($body['en'])); ?>
                    </div>
                <?php endif; ?>
                <?php if ($body['el'] !== '') : ?>
                    <div class="space-y-3 text-ink-muted">
                        <p class="font-mono text-[10px] uppercase tracking-widest m-0">EL / Ελληνικά</p>
                        <?php echo wp_kses_post(fc_format_block($body['el'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($url !== '') : ?>
            <p class="mt-12">
                <a href="<?php echo esc_url($url); ?>"
                   target="_blank" rel="noreferrer"
                   class="font-display text-xl underline-link accent-link text-ink inline-flex items-baseline gap-2">
                    <span lang="en">External source</span>
                    <span aria-hidden="true">→</span>
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>
</div>
