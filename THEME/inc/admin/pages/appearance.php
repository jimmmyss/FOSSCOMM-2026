<?php
/**
 * Appearance — global site chrome that isn't tied to a single section.
 * Currently: an optional custom mouse cursor that replaces the system one.
 * Stored in option `fc_appearance`; applied on the front end by
 * fc_inline_cursor_css() in inc/bootstrap.php.
 */
if (!defined('ABSPATH')) exit;

/**
 * Allow SVG (and .cur/.ico) uploads so they can be used as the custom cursor.
 * Gated to admins. NB: SVGs are stored as-is (not sanitized), so only upload
 * cursor files you trust. WP also content-sniffs the upload, so we have to
 * green-light the ext/mime pair in wp_check_filetype_and_ext too.
 */
add_filter('upload_mimes', function ($mimes) {
    if (current_user_can('manage_options')) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        $mimes['cur']  = 'image/x-icon';
        $mimes['ico']  = 'image/x-icon';
    }
    return $mimes;
});
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
    if (!current_user_can('manage_options')) return $data;
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'svg' || $ext === 'svgz') {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    } elseif ($ext === 'cur' || $ext === 'ico') {
        $data['ext']  = $ext;
        $data['type'] = 'image/x-icon';
    }
    return $data;
}, 10, 4);

add_action('admin_menu', 'fc_admin_register_appearance', 20);
function fc_admin_register_appearance() {
    add_submenu_page(FC_ADMIN_SLUG, 'Appearance', '— Appearance', FC_ADMIN_CAP, 'fc_appearance', 'fc_admin_page_appearance');
}

function fc_admin_page_appearance() {
    fc_render_section_admin_page([
        'slug'       => 'fc_appearance',
        'title'      => 'Appearance',
        'option_key' => 'fc_appearance',
        'schema'     => [
            'cursor'         => 'url',
            'cursor_pointer' => 'url',
        ],
        'intro'      => 'Optional custom mouse cursor for the whole site. Use a small image (≤ ~128&nbsp;px — PNG, SVG or .cur). Leave empty to keep the system cursor.',
        'render_form' => function ($values) {
            $cursor  = (string) ($values['cursor'] ?? '');
            $pointer = (string) ($values['cursor_pointer'] ?? '');
            ?>
            <div class="fc-field">
                <label>Default cursor (replaces the arrow everywhere)</label>
                <div class="fc-media">
                    <input type="hidden" class="fc-media-input" name="fc_field[cursor]" value="<?php echo esc_attr($cursor); ?>">
                    <div class="fc-media-preview"><?php if ($cursor !== '') : ?><img src="<?php echo esc_url($cursor); ?>" alt=""><?php endif; ?></div>
                    <button type="button" class="button fc-media-pick"><?php echo $cursor !== '' ? 'Replace image' : 'Select image'; ?></button>
                    <button type="button" class="button fc-media-clear"<?php echo $cursor === '' ? ' style="display:none"' : ''; ?>>Remove</button>
                </div>
            </div>
            <div class="fc-field">
                <label>Pointer cursor (optional — shown over links &amp; buttons)</label>
                <p class="description">Falls back to the default cursor above when empty.</p>
                <div class="fc-media">
                    <input type="hidden" class="fc-media-input" name="fc_field[cursor_pointer]" value="<?php echo esc_attr($pointer); ?>">
                    <div class="fc-media-preview"><?php if ($pointer !== '') : ?><img src="<?php echo esc_url($pointer); ?>" alt=""><?php endif; ?></div>
                    <button type="button" class="button fc-media-pick"><?php echo $pointer !== '' ? 'Replace image' : 'Select image'; ?></button>
                    <button type="button" class="button fc-media-clear"<?php echo $pointer === '' ? ' style="display:none"' : ''; ?>>Remove</button>
                </div>
            </div>
            <?php
        },
    ]);
}
