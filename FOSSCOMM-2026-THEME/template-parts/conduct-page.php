<?php
/**
 * Code of Conduct standalone page. Rendered at /coc/ by inc/conduct.php.
 * Content lives in option fc_section_conduct (FOSSCOMM → Code of Conduct).
 */
if (!defined('ABSPATH')) {
    exit;
}

$data  = get_option('fc_section_conduct', []);
if (!is_array($data)) $data = [];
$title = fc_bi($data, 'title');
$body  = fc_bi($data, 'body');
?>
<!-- Outer wrapper carries min-h-screen so the page still has full viewport
     height on short content. The <section> inside is the pet's platform
     (assets/pet/engine.js selects `section`); keeping it tight to the text
     means the border-b — the line the pet walks on — sits right where the
     content ends, instead of being pushed to the bottom of the viewport.
     <section> stays inside <main class="lg:pl-[200px]"> so the pet's
     horizontal range never crosses into the fixed sidebar. -->
<div class="min-h-screen">
<section class="bg-paper border-t border-b border-border">
    <div class="max-w-[1200px] mx-auto px-4 md:px-8 py-24 md:py-32">
        <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="hover:text-accent transition-colors">← <?php echo esc_html(fc_t('back_home')); ?></a>
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

        <?php if ($body['en'] !== '' || $body['el'] !== '') : ?>
            <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 text-lg leading-relaxed">
                <?php if ($body['en'] !== '') : ?>
                    <div class="space-y-3" lang="en">
                        <p class="font-mono text-[10px] uppercase tracking-widest text-ink-muted m-0">EN / English</p>
                        <?php echo wp_kses_post(wpautop(fc_format_inline_links($body['en']))); ?>
                    </div>
                <?php endif; ?>
                <?php if ($body['el'] !== '') : ?>
                    <div class="space-y-3 text-ink-muted">
                        <p class="font-mono text-[10px] uppercase tracking-widest m-0">EL / Ελληνικά</p>
                        <?php echo wp_kses_post(wpautop(fc_format_inline_links($body['el']))); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
</div>
