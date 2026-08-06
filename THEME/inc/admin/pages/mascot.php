<?php
/**
 * Admin page: FOSSCOMM → Mascot.
 *
 * Settings for the sticky pixel mascot — visibility, placement, and the scroll
 * triggers. Stored in option `fc_mascot_settings`; read on the front end by
 * fc_mascot_settings() in assets/mascot/mascot.php.
 *
 * Uses the shared fc_render_section_admin_page() scaffolding like every other
 * settings page, so the nonce/save/sanitise path is identical. Defaults and the
 * enum whitelists live next to the mascot itself (assets/mascot/mascot.php) so
 * the front end and this form can never disagree about them.
 *
 * The one thing this page adds over the others is a live preview: the real
 * mascot.js/mascot.css running inside a stage box, with the mascot's own debug
 * panel as the control surface.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_mascot', 15);
function fc_admin_register_mascot() {
    add_submenu_page(FC_ADMIN_SLUG, 'Mascot', '— Mascot', FC_ADMIN_CAP, 'fc_mascot', 'fc_admin_page_mascot');
}

/**
 * Load the real mascot on this page only.
 *
 * Config overrides vs the front end:
 *   debug        → ON  (the mascot's own panel IS this page's control surface)
 *   keyboardWalk → OFF (arrow keys must keep scrolling wp-admin)
 *   autoSleep    → OFF (he shouldn't nod off mid-inspection)
 *   sponsorsSurprise → OFF (there is no #sponsors section in wp-admin)
 */
add_action('admin_enqueue_scripts', 'fc_admin_mascot_preview_assets');
function fc_admin_mascot_preview_assets($hook) {
    if (strpos((string) $hook, 'fc_mascot') === false) return;

    wp_enqueue_style('fc-mascot', FC_THEME_URI . '/assets/mascot/mascot.css', [], FC_THEME_VERSION);
    wp_enqueue_script('fc-mascot', FC_THEME_URI . '/assets/mascot/mascot.js', [], FC_THEME_VERSION, true);

    $s = fc_mascot_settings();
    wp_localize_script('fc-mascot', 'FC_MASCOT', [
        'sprites' => fc_mascot_sprite_catalog(),
        'config'  => [
            'debug'            => true,
            'scale'            => max(2, min(5, (int) $s['scale'])),
            'corner'           => (string) $s['corner'],
            'startState'       => 'idle',
            'sponsorsSurprise' => false,
            'surpriseTarget'   => '',
            // Mirrors the saved setting so the preview is honest about it. The
            // panel's "Wander" button forces a step either way.
            'wander'           => !empty($s['wander']),
            'autoSleep'        => false,
            'keyboardWalk'     => false,
        ],
    ]);

    // Un-fix the two fixed-position pieces so they sit inside the page: the stage
    // becomes the positioning context for [data-corner], and the debug panel drops
    // out of its overlay into the normal flow (mascot.js re-parents it into
    // #fcm-debug-host when that element exists).
    wp_add_inline_style('fc-mascot', <<<CSS
    #fc-mascot-stage {
        position: relative;
        height: 280px;
        max-width: 720px;
        overflow: hidden;
        background: #FAFAF7;
        background-image:
            linear-gradient(rgba(10,10,10,.06) 1px, transparent 1px),
            linear-gradient(90deg, rgba(10,10,10,.06) 1px, transparent 1px);
        background-size: 16px 16px;
        border: 1px solid #ccd0d4;
    }
    #fc-mascot-stage #fosscomm-mascot { position: absolute; }
    #fcm-debug-host .fcm-debug { position: static; z-index: auto; margin-top: 12px; max-width: 720px; }
    .fc-mascot-hint { font-family: ui-monospace, Menlo, monospace; font-size: 11px; color: #50575e; margin: 0.5rem 0 1.5rem; }
CSS);
}

function fc_admin_page_mascot() {
    fc_render_section_admin_page([
        'slug'       => 'fc_mascot',
        'title'      => 'Mascot',
        'option_key' => 'fc_mascot_settings',
        'intro'      => 'The pixel companion that sits in the corner of every page. He idles, blinks, walks with the arrow keys, falls asleep when ignored, and reacts when the sponsors section scrolls into view.',
        'schema'     => [
            'enabled'           => 'bool',
            'debug_panel'       => 'bool',
            'scale'             => 'int',
            'corner'            => 'text',
            'start_state'       => 'text',
            'sponsors_surprise' => 'bool',
            'surprise_target'   => 'text',
            'wander'            => 'bool',
            'drag'              => 'bool',
            'scroll_flee'       => 'bool',
            'bubble'            => 'bool',
            'auto_sleep'        => 'bool',
            'keyboard_walk'     => 'bool',
        ],
        // Clamp / whitelist the three free-form fields. fc_sanitize_fields() only
        // type-casts; range and enum validation belongs here.
        'post_process' => function ($clean, $post) {
            $clean['scale'] = max(2, min(5, (int) ($clean['scale'] ?? 3)));

            if (!array_key_exists((string) ($clean['corner'] ?? ''), fc_mascot_corners())) {
                $clean['corner'] = 'br';
            }
            if (!array_key_exists((string) ($clean['start_state'] ?? ''), fc_mascot_start_states())) {
                $clean['start_state'] = 'idle';
            }

            $target = trim((string) ($clean['surprise_target'] ?? ''));
            $clean['surprise_target'] = ($target === '') ? '#sponsors' : $target;

            return $clean;
        },
        'render_form' => function ($values) {
            // Defaults fill in every field the first time this page is opened.
            $v = array_merge(fc_mascot_default_settings(), $values);
            $has_art = fc_mascot_has_surprise_art();
            $missing_drag = fc_mascot_missing_drag_art();
            ?>

            <?php if (!$has_art) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <strong>Surprise sprite not found.</strong>
                        The sponsors reaction below is wired and armed, but stays dormant until
                        <code>surprise.png</code> and <code>surprise.json</code> are exported into
                        <code>assets/mascot/</code>. Use a horizontal strip of 32×32&nbsp;px cells and
                        LibreSprite's <em>Hash</em> sheet format, same as <code>idle.json</code>.
                        Nothing breaks in the meantime.
                    </p>
                </div>
            <?php endif; ?>

            <h2>Preview</h2>
            <div id="fc-mascot-stage">
                <div id="fosscomm-mascot"
                     data-scale="<?php echo esc_attr((string) max(2, min(5, (int) $v['scale']))); ?>"
                     data-corner="<?php echo esc_attr((string) $v['corner']); ?>"
                     aria-hidden="true"></div>
            </div>
            <div id="fcm-debug-host"></div>
            <p class="fc-mascot-hint">Preview only — these buttons don't change the saved settings below.</p>

            <h2>Visibility</h2>
            <div class="fc-field">
                <label>
                    <input type="checkbox" name="fc_field[enabled]" value="1" <?php checked(!empty($v['enabled'])); ?>>
                    Show the mascot on the site
                </label>
                <p class="description">Off removes his script, stylesheet and markup from every page load.</p>
            </div>
            <div class="fc-field">
                <label>
                    <input type="checkbox" name="fc_field[debug_panel]" value="1" <?php checked(!empty($v['debug_panel'])); ?>>
                    Show the debug panel on the front end
                </label>
                <p class="description">
                    The floating control panel — <strong>visible to every visitor</strong> while this is on.
                    Leave it off in production: you can always bring it back for a single tab by adding
                    <code>?mascotdebug=1</code> to any URL.
                </p>
            </div>

            <h2>Placement</h2>
            <div class="fc-grid-2">
                <div class="fc-field">
                    <label for="fc-mascot-scale">Size</label>
                    <select id="fc-mascot-scale" name="fc_field[scale]">
                        <?php foreach ([2, 3, 4, 5] as $n) : ?>
                            <option value="<?php echo esc_attr((string) $n); ?>" <?php selected((int) $v['scale'], $n); ?>>
                                <?php echo esc_html($n . '×  (' . ($n * 32) . 'px)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fc-field">
                    <label for="fc-mascot-corner">Corner</label>
                    <select id="fc-mascot-corner" name="fc_field[corner]">
                        <?php foreach (fc_mascot_corners() as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected((string) $v['corner'], $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="fc-field">
                <label for="fc-mascot-start">Starting state</label>
                <select id="fc-mascot-start" name="fc_field[start_state]">
                    <?php foreach (fc_mascot_start_states() as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected((string) $v['start_state'], $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">What he does the moment a page finishes loading.</p>
            </div>

            <h2>Behaviour</h2>
            <div class="fc-field">
                <label>
                    <input type="checkbox" name="fc_field[wander]" value="1" <?php checked(!empty($v['wander'])); ?>>
                    Let him shuffle about on his own
                </label>
                <p class="description">
                    Takes a small step left or right every few seconds so he reads as alive rather
                    than as a sticker. He stays within about 48&nbsp;px of his corner, never wanders
                    across the page, and stops once he falls asleep. Ignored under
                    <em>prefers-reduced-motion</em>.
                </p>
            </div>
            <div class="fc-field">
                <label>
                    <input type="checkbox" name="fc_field[drag]" value="1" <?php checked(!empty($v['drag'])); ?>>
                    Let visitors pick him up and throw him
                </label>
                <p class="description">
                    Press and hold to grab him; he hangs from where you grabbed and swings with
                    your mouse. Let go and he falls, bounces, lands with a squash and sits dizzy
                    for a couple of seconds. He stays wherever he lands.
                    <?php if ($missing_drag) : ?>
                        <br><strong>Missing art:</strong>
                        <?php // escape each filename, then join with real markup ?>
                        <code><?php echo implode('</code>, <code>', array_map('esc_html', $missing_drag)); ?></code>
                        &mdash; dragging still works, he just keeps his current pose.
                    <?php endif; ?>
                </p>
            </div>
            <div class="fc-field">
                <label>
                    <input type="checkbox" name="fc_field[scroll_flee]" value="1" <?php checked(!empty($v['scroll_flee'])); ?>>
                    Get out of the way when the reader scrolls
                </label>
                <p class="description">
                    If he's sitting over the left 70% of the screen when you scroll, he dashes
                    right and parks 15% in from the edge, clear of the text. Already on the right?
                    He stays put &mdash; so he isn't running across the page on every scroll.
                </p>
            </div>
            <div class="fc-field">
                <label>
                    <input type="checkbox" name="fc_field[bubble]" value="1" <?php checked(!empty($v['bubble'])); ?>>
                    Show a speech bubble when you hover him
                </label>
                <p class="description">
                    Pops up instantly on hover and stays for 3 seconds, then won't reappear
                    for 20 seconds. Artwork: <code>pixel-speech-bubble.png</code>.
                    <?php if (!fc_mascot_bubble_url()) : ?>
                        <br><strong>Not found</strong> &mdash; the setting is inert until that file exists.
                    <?php endif; ?>
                </p>
            </div>
            <div class="fc-field">
                <label>
                    <input type="checkbox" name="fc_field[sponsors_surprise]" value="1" <?php checked(!empty($v['sponsors_surprise'])); ?>>
                    React with surprise when the sponsors section scrolls into view
                </label>
                <p class="description">
                    Fires once per page load, the first time the target is half visible, then he settles
                    back to idle. Scrolling up and down again won't replay it.
                </p>
            </div>
            <div class="fc-field">
                <label for="fc-mascot-target">Trigger target</label>
                <input type="text" id="fc-mascot-target" name="fc_field[surprise_target]"
                       value="<?php echo esc_attr((string) $v['surprise_target']); ?>" placeholder="#sponsors">
                <p class="description">
                    CSS selector to watch. Section ids match their key in Sections —
                    <code>#sponsors</code>, <code>#speakers</code>, <code>#venue</code>, and so on.
                    Ignored silently on pages where that element doesn't exist.
                </p>
            </div>
            <div class="fc-field">
                <label>
                    <input type="checkbox" name="fc_field[auto_sleep]" value="1" <?php checked(!empty($v['auto_sleep'])); ?>>
                    Fall asleep after 20 seconds of inactivity
                </label>
                <p class="description">Scrolling or clicking him wakes him up again.</p>
            </div>
            <div class="fc-field">
                <label>
                    <input type="checkbox" name="fc_field[keyboard_walk]" value="1" <?php checked(!empty($v['keyboard_walk'])); ?>>
                    Let the arrow keys walk him around
                </label>
                <p class="description">Left/Right move him while held. Ignored while typing in a field.</p>
            </div>
            <?php
        },
    ]);
}
