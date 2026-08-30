<?php
/**
 * Cursor — the custom mouse cursors that replace the system ones.
 *
 * Shown in wp-admin as "Cursor", which is what the page actually does; it was
 * called Appearance when it was meant to grow into general site chrome, and
 * never did. The option key and the menu slug both stay `fc_appearance` on
 * purpose: renaming the option would orphan every saved cursor, and renaming the
 * slug would break bookmarks to this page for no gain.
 *
 * Applied on the front end by fc_inline_cursor_css() in inc/bootstrap.php, which
 * also publishes each cursor as a CSS variable (`--fc-cur-pointer` and friends)
 * so theme code that has to set a cursor itself can use the custom one instead
 * of a hardcoded keyword.
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
    add_submenu_page(FC_ADMIN_SLUG, 'Cursor', '— Cursor', FC_ADMIN_CAP, 'fc_appearance', 'fc_admin_page_appearance');
}

/**
 * One line under a cursor field saying what it will actually be drawn at, and
 * warning when that is a size browsers will refuse near the window edges.
 *
 * This exists because the failure it reports is otherwise invisible and reads as
 * a bug in the site: the cursor works in the middle of the page and turns native
 * as you approach an edge, with nothing in the console and nothing wrong in the
 * CSS. It is Chromium declining to draw a large custom cursor near the chrome,
 * and the only fix is a smaller cursor — so the size has to be on screen next to
 * the control that sets it.
 */
function fc_cursor_size_note(string $url, int $scale): void {
    if (trim($url) === '') return;
    $info = fc_cursor_render_info($url, $scale);

    if (!$info['known']) {
        echo '<p class="fc-cursor-note">Rendered at the file’s own size. Keep it to '
           . FC_CURSOR_EDGE_SAFE . '&nbsp;px or under.</p>';
        return;
    }

    $size = sprintf('%d×%d&nbsp;px', $info['w'], $info['h']);
    if ($info['edge_safe']) {
        printf('<p class="fc-cursor-note is-ok">Renders at %s — small enough to show everywhere.</p>', $size);
        return;
    }

    printf(
        '<p class="fc-cursor-note is-warn"><strong>Renders at %s.</strong> Browsers refuse custom '
        . 'cursors larger than %d×%d&nbsp;px near the edges of the window — an anti-spoofing rule, '
        . 'not something a page can turn off — so this one will flip to the system cursor as you '
        . 'approach the bottom and sides. Use a smaller source image, or a lower size below.</p>',
        $size,
        FC_CURSOR_EDGE_SAFE,
        FC_CURSOR_EDGE_SAFE
    );
}

/**
 * The click point of one cursor: two boxes, in the source image's own pixels.
 *
 * This has to be per cursor, and it has to be adjustable, because the cursors
 * do not agree with each other. A pointer's hotspot belongs on its fingertip, a
 * hand's somewhere in its palm — and the moment two cursors put theirs in
 * different places, the sprite visibly JUMPS as you move between a link and the
 * page, because the browser lines the hotspot up with the mouse and the image
 * hangs off it. Matching them is the fix, and only someone looking at the
 * artwork can say where they match.
 */
function fc_cursor_hotspot_field(string $base, array $values, array $default): void {
    $x = $values[$base . '_hx'] ?? null;
    $y = $values[$base . '_hy'] ?? null;
    $x = $x === null || $x === '' ? $default[0] : (int) $x;
    $y = $y === null || $y === '' ? $default[1] : (int) $y;
    ?>
    <p class="fc-cursor-hot">
        <span>Click point</span>
        <label>x
            <input type="number" min="0" max="512" step="1"
                   name="fc_field[<?php echo esc_attr($base); ?>_hx]"
                   value="<?php echo esc_attr((string) $x); ?>">
        </label>
        <label>y
            <input type="number" min="0" max="512" step="1"
                   name="fc_field[<?php echo esc_attr($base); ?>_hy]"
                   value="<?php echo esc_attr((string) $y); ?>">
        </label>
        <span class="fc-cursor-hot-help">pixels from the image’s top-left, at its
            <em>original</em> size — scaled automatically.</span>
    </p>
    <?php
}

function fc_admin_page_appearance() {
    add_action('admin_head', function () {
        echo '<style>'
           . '.fc-cursor-note{font-size:12px;margin:4px 0 0;line-height:1.5}'
           . '.fc-cursor-note.is-ok{color:#646970}'
           . '.fc-cursor-note.is-warn{color:#b32d2e}'
           . '.fc-cursor-hot{display:flex;align-items:center;gap:8px;flex-wrap:wrap;'
           . 'font-size:12px;color:#646970;margin:6px 0 0}'
           . '.fc-cursor-hot > span:first-child{font-weight:600;color:#1d2327}'
           . '.fc-cursor-hot label{display:inline-flex;align-items:center;gap:4px}'
           . '.fc-cursor-hot input{width:64px}'
           . '.fc-cursor-hot-help{flex:1 1 100%;margin:0}'
           . '</style>';
    });

    fc_render_section_admin_page([
        'slug'       => 'fc_appearance',
        'title'      => 'Cursor',
        'option_key' => 'fc_appearance',
        'schema'     => [
            'cursor'             => 'url',
            'cursor_hx'          => 'int',
            'cursor_hy'          => 'int',
            'cursor_pointer'     => 'url',
            'cursor_pointer_hx'  => 'int',
            'cursor_pointer_hy'  => 'int',
            'cursor_grab'        => 'url',
            'cursor_grab_hx'     => 'int',
            'cursor_grab_hy'     => 'int',
            'cursor_grabbing'    => 'url',
            'cursor_grabbing_hx' => 'int',
            'cursor_grabbing_hy' => 'int',
            'cursor_scale'       => 'int',
        ],
        'intro'      => 'Optional custom mouse cursors for the whole site. Use small images (PNG, SVG or .cur). '
                      . 'Leave a slot empty to keep the system cursor for it.<br><br>'
                      . '<strong>Click point</strong> is the pixel in each image that sits exactly under the '
                      . 'mouse. Give every cursor the SAME click point if their artwork lines up — otherwise '
                      . 'the sprite appears to jump as you move onto a link, because the browser pins that '
                      . 'pixel to the pointer and hangs the rest of the image off it.',
        'render_form' => function ($values) {
            $scale_now = max(1, (int) ($values['cursor_scale'] ?? 1));

            fc_media_field('cursor', $values, [
                'label' => 'Default cursor (replaces the arrow everywhere)',
                'full'  => true,
            ]);
            fc_cursor_size_note((string) ($values['cursor'] ?? ''), $scale_now);
            fc_cursor_hotspot_field('cursor', $values, fc_cursor_hotspot_default('cursor'));

            fc_media_field('cursor_pointer', $values, [
                'label' => 'Pointer cursor — shown over anything clickable',
                'help'  => 'Links, buttons, the schedule and venue filters, form controls, checkboxes, '
                         . 'selects, the map’s zoom buttons and its pins — everything the site treats as '
                         . 'interactive. Falls back to the default cursor above when empty.',
                'full'  => true,
            ]);
            fc_cursor_size_note((string) ($values['cursor_pointer'] ?? ''), $scale_now);
            fc_cursor_hotspot_field('cursor_pointer', $values, fc_cursor_hotspot_default('cursor_pointer'));

            fc_media_field('cursor_grab', $values, [
                'label' => 'Grab cursor — open hand (optional — hovering something draggable)',
                'help'  => 'Shown while the pointer is <em>over</em> something you can drag but '
                         . 'have not grabbed yet: the mascot, the venue globe, and anything marked '
                         . '<code>.cursor-grab</code> or <code>data-fc-grab</code>. Left empty, '
                         . 'those keep the browser’s own “grab” cursor.',
                'full'  => true,
            ]);
            fc_cursor_size_note((string) ($values['cursor_grab'] ?? ''), $scale_now);
            fc_cursor_hotspot_field('cursor_grab', $values, fc_cursor_hotspot_default('cursor_grab'));

            fc_media_field('cursor_grabbing', $values, [
                'label' => 'Grabbing cursor — closed hand (optional — while actually holding)',
                'help'  => 'Shown from the moment you catch the mascot until you let go, while you '
                         . 'spin the globe, and on anything marked <code>.cursor-grabbing</code> or '
                         . '<code>data-fc-grabbing</code>. Falls back to the open hand above when '
                         . 'empty, so a single image still covers both states.',
                'full'  => true,
            ]);
            fc_cursor_size_note((string) ($values['cursor_grabbing'] ?? ''), $scale_now);
            fc_cursor_hotspot_field('cursor_grabbing', $values, fc_cursor_hotspot_default('cursor_grabbing'));

            $scale = $scale_now;
            ?>
            <div class="fc-field">
                <label for="fc_field_cursor_scale">Cursor size</label>
                <p class="description">
                    A multiplier on each file’s own size, applied to all four cursors above —
                    <code>×2</code> draws a 16&nbsp;px image at 32&nbsp;px. Pixel art is upscaled
                    with hard edges, so it stays crisp. <code>.cur</code> and <code>.ico</code>
                    files carry their own size and are never rescaled.
                </p>
                <p class="description">
                    <strong>Keep the result at <?php echo (int) FC_CURSOR_EDGE_SAFE; ?>&nbsp;px or
                    under.</strong> Above that, browsers stop drawing a custom cursor once the
                    pointer nears the edge of the window and quietly use the system one instead —
                    it is an anti-spoofing rule and a page cannot opt out of it. The result is a
                    cursor that looks right in the middle of the page and turns native near the
                    bottom and sides. Each cursor above reports the size it will actually be drawn
                    at; a <span style="color:#b32d2e">red note</span> means it is over the limit.
                </p>
                <select id="fc_field_cursor_scale" name="fc_field[cursor_scale]">
                    <?php for ($i = 1; $i <= 8; $i++) : ?>
                        <option value="<?php echo esc_attr((string) $i); ?>"
                            <?php selected($scale, $i); ?>>
                            &times;<?php echo esc_html((string) $i); ?><?php
                                echo $i === 1 ? ' (original size)' : ''; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php
        },
        'post_process' => function ($clean, $raw) {
            // ×1 = "use the file as-is". The old pixel-width `cursor_size` key is
            // deliberately absent from the schema, so it drops out on first save.
            $n = (int) ($clean['cursor_scale'] ?? 1);
            $clean['cursor_scale'] = max(1, min(8, $n));

            // Click points are pixel offsets, so a negative is meaningless and an
            // absurd one puts the hotspot outside the image — which a browser
            // ignores, silently reverting to the top-left corner and looking
            // exactly like the setting does nothing.
            foreach (['cursor', 'cursor_pointer', 'cursor_grab', 'cursor_grabbing'] as $base) {
                foreach (['_hx', '_hy'] as $axis) {
                    $key = $base . $axis;
                    $clean[$key] = max(0, min(512, (int) ($clean[$key] ?? 0)));
                }
            }
            // The emitted CSS base64-inlines the cursor files, so it's cached in
            // a transient (see fc_inline_cursor_css). Saving here invalidates it.
            // Every key this cache has ever used. The suffix gets bumped when the
            // GENERATOR changes rather than the settings — otherwise a string
            // built by the old code keeps being served for a whole day and the
            // change looks like it simply did not work.
            delete_transient('fc_cursor_css');      // legacy, pre-multiplier
            delete_transient('fc_cursor_css_v2');   // keyword appended per use site
            delete_transient('fc_cursor_css_v3');   // hotspot always 0 0
            delete_transient('fc_cursor_css_v4');   // pointer hotspot 0 4
            delete_transient('fc_cursor_css_v5');   // hotspots not yet shared
            delete_transient('fc_cursor_css_v6');
            return $clean;
        },
    ]);
}
