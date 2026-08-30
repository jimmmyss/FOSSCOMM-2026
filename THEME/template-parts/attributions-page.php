<?php
/**
 * Attributions standalone page. Rendered at /attributions/ by inc/attributions.php.
 * List lives in option fc_attributions, heading and intro in fc_attributions_meta
 * (FOSSCOMM → Attributions).
 *
 * Built from the same vocabulary as the rest of the site: the page shell and the
 * back-home eyebrow are conduct-page.php's, and the numbered hairline rows are
 * the FAQ's list treatment. Nothing here invents a new component.
 */
if (!defined('ABSPATH')) {
    exit;
}

$rows     = fc_attributions_rows();
$meta     = fc_attributions_meta();
$defaults = fc_attributions_defaults();

$title = fc_one(fc_bi($meta, 'title'));
if ($title === '') $title = fc_pick($defaults['title_el'], $defaults['title_en']);

$intro = fc_one(fc_bi($meta, 'intro'));
if ($intro === '') $intro = fc_pick($defaults['intro_el'], $defaults['intro_en']);
?>
<!-- Outer wrapper carries min-h-screen so the page still has full viewport
     height on a short list. The <section> inside is what the sidebar nav and
     the mascot's section reactions both read to decide which section you are
     in (assets/section-nav.js and assets/mascot/js/sections.js, same 0.35
     viewport trigger), so keep it wrapping the real content. -->
<div class="min-h-screen">
<section class="bg-paper border-t border-b border-border">
    <div class="max-w-[1200px] mx-auto px-4 md:px-8 py-24 md:py-32">
        <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="hover:text-accent transition-colors">← <?php echo esc_html(fc_t('back_home')); ?></a>
        </div>

        <h1 class="font-display text-4xl md:text-6xl leading-[1.05] tracking-tight m-0">
            <?php echo fc_format($title); ?>
        </h1>

        <?php if ($intro !== '') : ?>
            <div class="mt-8 text-lg leading-relaxed max-w-3xl">
                <?php echo wp_kses_post(fc_format_block($intro)); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($rows)) : ?>
            <!-- Zero-padded index in the same mono the section eyebrows use, so a
                 credit can be referred to by number. The row is one link when a
                 URL is set and plain text when it is not — never a dead anchor. -->
            <ul class="mt-12 border-t border-border">
                <?php foreach ($rows as $i => $row) :
                    $text = fc_one(fc_bi($row, 'text'));
                    $url  = (string) ($row['url'] ?? '');
                    // The bare host, as a quiet note of where it came from. An
                    // on-page anchor or a relative path has no host, so it is
                    // simply omitted rather than printed as an empty tag.
                    $host = $url !== '' ? (string) wp_parse_url($url, PHP_URL_HOST) : '';
                    // Cast: preg_replace returns null on failure, and passing null
                    // to esc_html() is deprecated from PHP 8.1 and fatal in 9.
                    if ($host !== '') $host = (string) preg_replace('/^www\./', '', $host);
                    $index = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                    ?>
                    <li class="border-b border-border">
                        <?php if ($url !== '') : ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noreferrer"
                               class="group no-underline flex items-baseline gap-4 py-5 hover:text-accent transition-colors">
                                <span class="font-mono text-sm text-accent w-6 shrink-0"><?php echo esc_html($index); ?></span>
                                <span class="flex-1">
                                    <span class="text-lg md:text-xl leading-snug block"><?php echo fc_format($text); ?></span>
                                    <?php if ($host !== '') : ?>
                                        <span class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mt-1 block group-hover:text-accent transition-colors"><?php echo esc_html($host); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="font-mono text-sm shrink-0" aria-hidden="true">→</span>
                            </a>
                        <?php else : ?>
                            <div class="flex items-baseline gap-4 py-5">
                                <span class="font-mono text-sm text-ink-muted w-6 shrink-0"><?php echo esc_html($index); ?></span>
                                <span class="flex-1 text-lg md:text-xl leading-snug"><?php echo fc_format($text); ?></span>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
</div>
