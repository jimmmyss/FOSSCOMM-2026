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
     height on short content. The <section> inside is what the sidebar nav and
     the mascot's section reactions both read to decide which section you are
     in (assets/section-nav.js and assets/mascot/js/sections.js, same 0.35
     viewport trigger), so keep it wrapping the real content. -->
<div class="min-h-screen">
<section class="bg-paper border-t border-b border-border">
    <div class="max-w-[1200px] mx-auto px-4 md:px-8 py-24 md:py-32">
        <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="hover:text-accent transition-colors">← <?php echo esc_html(fc_t('back_home')); ?></a>
        </div>

        <?php $title_text = fc_one($title); $body_text = fc_one($body); ?>
        <?php if ($title_text !== '') : ?>
            <h1 class="font-display text-4xl md:text-6xl leading-[1.05] tracking-tight m-0">
                <?php echo fc_format($title_text); ?>
            </h1>
        <?php endif; ?>

        <?php if ($body_text !== '') : ?>
            <div class="mt-12 text-lg leading-relaxed max-w-3xl">
                <div class="space-y-3">
                    <?php echo wp_kses_post(wpautop(fc_format_inline_links($body_text))); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
</div>
