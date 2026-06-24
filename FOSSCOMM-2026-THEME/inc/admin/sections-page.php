<?php
/**
 * "FOSSCOMM → Sections" — overview page with drag-reorder + active toggles.
 */
if (!defined('ABSPATH')) {
    exit;
}

function fc_admin_sections_page() {
    if (!current_user_can(FC_ADMIN_CAP)) {
        wp_die(__('Insufficient permissions.', 'fosscomm'));
    }

    if (
        isset($_POST['fc_sections_save']) &&
        check_admin_referer('fc_sections_save', 'fc_sections_nonce')
    ) {
        $posted = isset($_POST['fc_section']) && is_array($_POST['fc_section']) ? $_POST['fc_section'] : [];
        $state = [];
        foreach (fc_section_registry() as $key => $def) {
            $entry = isset($posted[$key]) && is_array($posted[$key]) ? $posted[$key] : [];
            $state[$key] = [
                'active' => !empty($entry['active']),
                'order'  => isset($entry['order']) ? (int) $entry['order'] : (int) ($def['default_order'] ?? 999),
            ];
        }
        fc_save_sections_state($state);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Sections saved.', 'fosscomm') . '</p></div>';
    }

    $state    = fc_sections_state();
    $registry = fc_section_registry();
    $ordered  = $registry;
    uasort($ordered, function ($a, $b) use ($state) {
        return ($state[$a['key']]['order'] ?? 999) <=> ($state[$b['key']]['order'] ?? 999);
    });
    ?>
    <div class="wrap fc-wrap">
        <h1>FOSSCOMM — Sections</h1>
        <div class="fc-callout">
            <strong>Drag rows to reorder.</strong> Untick "Active" to hide a section from the landing page and the nav. Click <em>Edit</em> on any section with an admin page to edit its copy.
        </div>
        <form method="post">
            <?php wp_nonce_field('fc_sections_save', 'fc_sections_nonce'); ?>
            <table class="fc-sections-table">
                <thead>
                    <tr>
                        <th></th>
                        <th><?php esc_html_e('Active', 'fosscomm'); ?></th>
                        <th><?php esc_html_e('Type', 'fosscomm'); ?></th>
                        <th><?php esc_html_e('Label (EL)', 'fosscomm'); ?></th>
                        <th><?php esc_html_e('Label (EN)', 'fosscomm'); ?></th>
                        <th><?php esc_html_e('Edit', 'fosscomm'); ?></th>
                    </tr>
                </thead>
                <tbody id="fc-sections-tbody">
                <?php foreach ($ordered as $key => $def) :
                    $active = !empty($state[$key]['active']);
                    $order  = (int) ($state[$key]['order'] ?? $def['default_order'] ?? 999);
                    $edit_slug = 'fc_section_' . $key;
                    $edit_url  = $def['has_admin_page'] ? menu_page_url($edit_slug, false) : '';
                    ?>
                    <tr class="<?php echo $active ? '' : 'inactive'; ?>">
                        <td class="fc-handle">⋮⋮</td>
                        <td>
                            <input type="checkbox" name="fc_section[<?php echo esc_attr($key); ?>][active]" value="1" <?php checked($active); ?>>
                            <input type="hidden" class="fc-order-input" name="fc_section[<?php echo esc_attr($key); ?>][order]" value="<?php echo esc_attr((string) $order); ?>">
                        </td>
                        <td class="fc-type"><?php echo esc_html($key); ?></td>
                        <td><?php echo esc_html((string) $def['label_el']); ?></td>
                        <td><?php echo esc_html((string) $def['label_en']); ?></td>
                        <td>
                            <?php if ($edit_url) : ?>
                                <a href="<?php echo esc_url($edit_url); ?>">Edit →</a>
                            <?php else : ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:1rem;">
                <button type="submit" name="fc_sections_save" value="1" class="button button-primary">
                    <?php esc_html_e('Save sections', 'fosscomm'); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Shared helper used by every per-section admin page: standard "Save"d form scaffolding.
 *
 * Usage in a sub-page callback:
 *
 *   fc_render_section_admin_page([
 *     'slug'        => 'fc_section_manifesto',
 *     'title'       => 'Manifesto',
 *     'option_key'  => 'fc_section_manifesto',
 *     'schema'      => [...],          // for fc_sanitize_fields()
 *     'render_form' => function($values) { ... echo bilingual fields ... },
 *   ]);
 */
function fc_render_section_admin_page(array $cfg): void {
    if (!current_user_can(FC_ADMIN_CAP)) {
        wp_die(__('Insufficient permissions.', 'fosscomm'));
    }
    $slug       = (string) $cfg['slug'];
    $title      = (string) $cfg['title'];
    $option_key = (string) $cfg['option_key'];
    $schema     = (array) ($cfg['schema'] ?? []);

    if (
        isset($_POST[$slug . '_save']) &&
        check_admin_referer($slug . '_save', $slug . '_nonce')
    ) {
        // WordPress adds transport slashes to $_POST on EVERY request. Strip them
        // once (wp_unslash) before sanitising/saving — otherwise each save
        // re-escapes the already-escaped value and quotes/apostrophes accumulate
        // backslashes (\\\\' …) that grow on every save.
        $post  = wp_unslash($_POST);
        $raw   = isset($post['fc_field']) && is_array($post['fc_field']) ? $post['fc_field'] : [];
        $clean = fc_sanitize_fields($raw, $schema);
        if (isset($cfg['post_process']) && is_callable($cfg['post_process'])) {
            $clean = call_user_func($cfg['post_process'], $clean, $post);
        }
        update_option($option_key, $clean, false);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Saved.', 'fosscomm') . '</p></div>';
    }

    $values = get_option($option_key, []);
    if (!is_array($values)) $values = [];
    ?>
    <div class="wrap fc-wrap">
        <h1>FOSSCOMM — <?php echo esc_html($title); ?></h1>
        <?php if (!empty($cfg['intro'])) : ?>
            <div class="fc-callout"><?php echo wp_kses_post((string) $cfg['intro']); ?></div>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field($slug . '_save', $slug . '_nonce'); ?>
            <?php if (isset($cfg['render_form']) && is_callable($cfg['render_form'])) {
                call_user_func($cfg['render_form'], $values);
            } ?>
            <p style="margin-top:1.5rem;">
                <button type="submit" name="<?php echo esc_attr($slug . '_save'); ?>" value="1" class="button button-primary">
                    <?php esc_html_e('Save changes', 'fosscomm'); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Render an EL/EN "Section heading" field on a collection admin page. The
 * value is stored in the parallel `fc_section_<key>` option (NOT the rows
 * option) so the section's heading can live alongside its row data without
 * having to push title fields through the row schema. Pair with
 * fc_section_meta_save() inside the same admin page's post_process.
 *
 * $defaults: shown as placeholders + used as the option fallback so a
 * never-touched form still reads the same way the hardcoded copy used to.
 */
function fc_section_meta_render(string $section_key, array $defaults = []): void {
    $existing = fc_section_meta($section_key, $defaults);
    echo '<h2 style="margin-top:0.5rem;">' . esc_html__('Section heading', 'fosscomm') . '</h2>';
    echo '<p class="description">' . esc_html__('Shown above this section on the landing page. Leave blank to hide.', 'fosscomm') . '</p>';
    fc_bilingual_field('title', $existing, [
        'type'           => 'text',
        'placeholder_el' => (string) ($defaults['title_el'] ?? ''),
        'placeholder_en' => (string) ($defaults['title_en'] ?? ''),
        'name_prefix'    => 'fc_section_meta',
    ]);
}

/**
 * Persist what fc_section_meta_render() submitted. Reads from $post (the
 * collection admin page passes the whole $_POST into post_process).
 */
function fc_section_meta_save(string $section_key, array $post): void {
    $raw   = isset($post['fc_section_meta']) && is_array($post['fc_section_meta']) ? $post['fc_section_meta'] : [];
    $clean = fc_sanitize_fields($raw, ['title' => 'bilingual']);
    update_option('fc_section_' . $section_key, $clean, false);
}

/**
 * Shared helper for collection-style admin pages (Schedule, Tracks, Sponsors, Past Editions, FAQ).
 *
 * Stores a flat array of rows in a single option.
 */
function fc_render_collection_admin_page(array $cfg): void {
    if (!current_user_can(FC_ADMIN_CAP)) {
        wp_die(__('Insufficient permissions.', 'fosscomm'));
    }
    $slug       = (string) $cfg['slug'];
    $title      = (string) $cfg['title'];
    $option_key = (string) $cfg['option_key'];
    $fields     = (array) $cfg['fields'];
    $add_label  = (string) ($cfg['add_label'] ?? 'Add row');

    if (
        isset($_POST[$slug . '_save']) &&
        check_admin_referer($slug . '_save', $slug . '_nonce')
    ) {
        // Strip WordPress's transport slashes once before sanitising/saving (see
        // fc_render_section_admin_page) so repeated saves can't pile up backslashes.
        $post  = wp_unslash($_POST);
        $rows  = isset($post['fc_rows']) && is_array($post['fc_rows']) ? $post['fc_rows'] : [];
        $clean = fc_sanitize_repeater($rows, $fields);
        if (isset($cfg['post_process']) && is_callable($cfg['post_process'])) {
            $clean = call_user_func($cfg['post_process'], $clean, $post);
        }
        update_option($option_key, $clean, false);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Saved.', 'fosscomm') . '</p></div>';
    }

    $rows = get_option($option_key, []);
    if (!is_array($rows)) $rows = [];
    ?>
    <div class="wrap fc-wrap">
        <h1>FOSSCOMM — <?php echo esc_html($title); ?></h1>
        <?php if (!empty($cfg['intro'])) : ?>
            <div class="fc-callout"><?php echo wp_kses_post((string) $cfg['intro']); ?></div>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field($slug . '_save', $slug . '_nonce'); ?>
            <?php if (isset($cfg['render_before']) && is_callable($cfg['render_before'])) {
                call_user_func($cfg['render_before'], $rows);
            } ?>
            <?php fc_repeater([
                'name'      => 'fc_rows',
                'rows'      => $rows,
                'fields'    => $fields,
                'add_label' => $add_label,
            ]); ?>
            <p style="margin-top:1.5rem;">
                <button type="submit" name="<?php echo esc_attr($slug . '_save'); ?>" value="1" class="button button-primary">
                    <?php esc_html_e('Save changes', 'fosscomm'); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}
