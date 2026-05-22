<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> lang="el">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('text-ink antialiased'); ?>>
<?php wp_body_open(); ?>
<?php get_template_part('template-parts/partials/status-bar'); ?>
<?php get_template_part('template-parts/partials/section-nav'); ?>
