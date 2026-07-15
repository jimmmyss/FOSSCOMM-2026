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
<main class="min-h-screen flex items-center justify-center px-4">
    <div class="text-center max-w-xl">
        <?php if ($title !== '') : ?>
            <h1 class="font-display text-5xl md:text-7xl leading-none tracking-tight mb-6"><?php echo fc_format($title); ?></h1>
        <?php endif; ?>
        <?php if ($message !== '') : ?>
            <div class="font-mono text-sm text-ink-muted mb-8 leading-relaxed"><?php echo wp_kses_post(fc_format_block($message)); ?></div>
        <?php endif; ?>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="underline-link accent-link font-display text-2xl">
            ← <?php echo esc_html(fc_t('back_home')); ?>
        </a>
    </div>
</main>
<?php get_footer();
