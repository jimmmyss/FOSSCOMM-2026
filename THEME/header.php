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
    <?php
    /*
     * Tints the phone's browser chrome — the strip behind the clock, and on iOS
     * the toolbar around the address bar below the page.
     *
     * Without this Safari picks its own colour by sampling the page at each
     * edge, which is why the top came out blue (the top of the landing page IS
     * the blue bar) and the bottom did not (the bottom is whatever section you
     * had scrolled to). Naming a colour overrides the sampling and drives both
     * ends from one value.
     *
     * Deliberately CONSTANT, and on every page. Brand blue top and bottom is the
     * look; it does not track the site's own bar flipping to paper past the hero.
     * Nothing updates this at runtime, so there is no first-paint flash and no JS
     * involved in it at all.
     */
    printf(
        '<meta name="theme-color" content="%s">' . "\n",
        esc_attr(FC_CHROME_ACCENT)
    );
    ?>
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
