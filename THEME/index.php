<?php
/**
 * Fallback template — WordPress requires it. The site is single-page; everything renders via front-page.php.
 */
get_header();
?>
<main class="lg:pl-[200px]">
    <section class="max-w-[1440px] mx-auto px-4 md:px-8 py-24">
        <?php // The sections' heading, like every other page's title. ?>
        <h1 class="fc-section-heading"><?php echo esc_html(get_bloginfo('name')); ?></h1>
        <p class="text-lg leading-relaxed text-ink-muted">Set a static front page in Settings → Reading to view the landing page.</p>
    </section>
</main>
<?php
get_footer();
