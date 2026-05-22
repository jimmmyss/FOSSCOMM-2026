<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>
<main class="min-h-screen flex items-center justify-center px-4">
    <div class="text-center">
        <pre class="ascii text-[10px] md:text-xs text-ink-faint mb-8">
   ____  ____  _  _
  | ___||___ \| || |
  |___ \  __) | || |_
   ___) |/ __/|__   _|
  |____/|_____|  |_|
        </pre>
        <p class="font-mono text-sm text-ink-muted mb-4"><?php echo esc_html(fc_t('not_found_message')); ?></p>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="underline-link accent-link font-display text-2xl">
            ← <?php echo esc_html(fc_t('back_home')); ?>
        </a>
    </div>
</main>
<?php get_footer();
