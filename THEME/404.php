<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();

// Admin-editable, single-language copy (FOSSCOMM → 404 Page). Heading is
// optional; the message falls back to the built-in chrome string.
$data    = get_option('fc_404', []);
if (!is_array($data)) $data = [];
$title   = fc_one(fc_bi($data, 'title'));
$message = fc_one(fc_bi($data, 'message'));
if ($message === '') {
    $message = fc_t('not_found_message');
}
?>
<?php /* The page's parts are the site's own, not a set of their own:
         .fc-section-heading is the heading every section uses (so a *marked*
         word here is the same blue pixel face it is everywhere else), the
         message is body copy at the reading size the standalone pages use, and
         the way home is a BUTTON — fc_cta_link(), the same call the hero, the
         sponsors and Get Involved make, so it carries the display face, the
         uppercase and the Qaroxe ">" and goes accent on hover with them. */ ?>
<main class="min-h-screen flex items-center justify-center px-4 md:px-8">
    <div class="text-center max-w-xl">
        <?php if ($title !== '') : ?>
            <h1 class="fc-section-heading"><?php echo fc_format($title); ?></h1>
        <?php endif; ?>
        <?php if ($message !== '') : ?>
            <div class="fc-copy text-lg leading-relaxed text-ink-muted mb-8"><?php echo wp_kses_post(fc_format_block($message)); ?></div>
        <?php endif; ?>
        <?php
        /* fc_t() already returns the active language, so the same string goes in
           both slots — fc_cta_link() picks one of them and renders a single
           label, with the site's ">" after it. */
        $home_label = fc_t('back_home');
        fc_cta_link([
            'url' => home_url('/'),
            'en'  => $home_label,
            'el'  => $home_label,
        ]);
        ?>
    </div>
</main>
<?php get_footer();
