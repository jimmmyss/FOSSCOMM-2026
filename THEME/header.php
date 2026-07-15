<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> lang="<?php echo esc_attr(fc_current_lang()); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('text-ink antialiased' . (fc_is_landing_page() ? ' fc-landing' : '')); ?>>
<?php wp_body_open(); ?>
<?php get_template_part('template-parts/partials/status-bar'); ?>
<?php
// Landing page renders the section-nav as a sticky rail inside front-page.php's
// post-hero column (so it locks at the Manifesto line). Other pages (news / coc)
// have no hero, so the nav stays here as the original fixed left rail.
if (!fc_is_landing_page()) {
    get_template_part('template-parts/partials/section-nav');
}
?>
