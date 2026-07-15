<?php
/**
 * Section renderer — looks up the matching template-part and passes the section context.
 */
if (!defined('ABSPATH')) {
    exit;
}

function fc_render_section(array $section): void {
    $key = (string) ($section['key'] ?? '');
    if ($key === '') return;
    $template = 'template-parts/sections/' . $key;
    if (locate_template($template . '.php')) {
        get_template_part('template-parts/sections/' . $key, null, ['section' => $section]);
        return;
    }
    if (current_user_can('manage_options')) {
        echo '<section class="border-t border-border"><div class="max-w-[1440px] mx-auto px-4 md:px-8 py-12">';
        echo '<p class="font-mono text-sm text-ink-muted">[section "' . esc_html($key) . '" has no template at ' . esc_html($template) . '.php]</p>';
        echo '</div></section>';
    }
}

/**
 * Read the section payload option as an array, never null.
 */
function fc_section_data(array $section): array {
    $opt_key = (string) ($section['option_key'] ?? '');
    if ($opt_key === '') return [];
    $data = get_option($opt_key, []);
    return is_array($data) ? $data : [];
}

/**
 * Opens a standard section wrapper used by most template-parts.
 * Eyebrow + title both render in the active language only (the site shows one
 * language at a time). When overrides don't supply an eyebrow, it's pulled
 * language-aware from the section's registry/admin name (fc_section_eyebrow()).
 *
 * Accepted overrides: eyebrow_el, eyebrow_en, title_el, title_en, class.
 */
function fc_section_open(array $section, array $overrides = []): void {
    $id      = (string) $section['key'];
    // Eyebrow: explicit override pair wins; otherwise the section's own
    // (language-aware, admin-overridable) eyebrow name.
    if (isset($overrides['eyebrow_el']) || isset($overrides['eyebrow_en'])) {
        $eyebrow = fc_pick((string) ($overrides['eyebrow_el'] ?? ''), (string) ($overrides['eyebrow_en'] ?? ''));
    } else {
        $eyebrow = fc_section_eyebrow($section);
    }
    $title       = fc_pick((string) ($overrides['title_el'] ?? ''), (string) ($overrides['title_en'] ?? ''));
    $extra_class = (string) ($overrides['class'] ?? '');
    // Every section gets an opaque paper background by default, so the
    // global wave-canvas (assets/wave-bg.js, z-index: -1 behind everything)
    // is hidden behind it. Sections that DO want to show the canvas opt out
    // by passing the .fc-section-dots marker via 'class', which we detect
    // here and skip the bg-paper class. CSS in site.css doubles up on the
    // selector for safety.
    $has_dots    = strpos($extra_class, 'fc-section-dots') !== false;
    $bg_class    = $has_dots ? '' : 'bg-paper';
    ?>
    <section id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($bg_class); ?> relative border-t border-border <?php echo esc_attr($extra_class); ?>">
        <div class="max-w-[1440px] mx-auto px-4 md:px-8 py-24 md:py-40">
            <?php if ($eyebrow !== '') : ?>
                <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6">
                    <?php echo esc_html($eyebrow); ?>
                </div>
            <?php endif; ?>
            <?php if ($title !== '') : ?>
                <h2 class="font-display text-4xl md:text-6xl leading-[1.0] tracking-tight mb-16"><?php echo fc_format($title); ?></h2>
            <?php endif; ?>
    <?php
}

function fc_section_close(): void {
    echo '</div></section>';
}
