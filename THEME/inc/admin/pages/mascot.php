<?php
/**
 * Admin page: FOSSCOMM → Mascot.
 *
 * Renders the whole mascot from the declarations in assets/mascot/mascot.php —
 * the state list, the physics groups, the particle fields. Nothing about a state
 * or a tuning number is written twice: add a row to fc_mascot_states() or
 * fc_mascot_physics_groups() and it appears here, is validated on save, and
 * reaches the browser.
 *
 * This page does NOT use fc_render_section_admin_page(). That scaffolding is
 * built for a flat `fc_field[key]` schema, and a mascot is three levels deep
 * (state → entry → particle → per-frame timings). The nonce/capability/save
 * shape is the same, it just walks a nested array.
 *
 * The interactive half — cutting a sheet into frames, the per-frame timeline,
 * the per-entry preview, the live stage — is assets/mascot/js/admin.js, which
 * imports the SAME modules the front end runs on. A preview that re-implements
 * playback is a preview that lies about it, which is exactly how the old build
 * ended up honouring frame durations in one place and ignoring them in the other.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_mascot', 15);
function fc_admin_register_mascot() {
    add_submenu_page(FC_ADMIN_SLUG, 'Mascot', '— Mascot', FC_ADMIN_CAP, 'fc_mascot', 'fc_admin_page_mascot');
}

add_action('admin_enqueue_scripts', 'fc_admin_mascot_assets');
function fc_admin_mascot_assets($hook) {
    if (strpos((string) $hook, 'fc_mascot') === false) return;

    wp_enqueue_media();
    wp_enqueue_style('fc-mascot', FC_THEME_URI . '/assets/mascot/mascot.css', [], FC_THEME_VERSION);
    wp_enqueue_script('fc-mascot-admin', FC_THEME_URI . '/assets/mascot/js/admin.js', [], FC_THEME_VERSION, true);
    wp_add_inline_style('fc-mascot', fc_admin_mascot_css());

    // NB: no wp_localize_script here. This hook runs BEFORE the page callback,
    // and the page callback is where a save happens — so the payload built here
    // would be the configuration the user just replaced, and the preview would
    // spend the whole post-save request running it while the form beside it
    // showed the new one. fc_admin_page_mascot() localises instead, after the
    // save; the script is a footer script, so the data still prints in time.
}

add_filter('script_loader_tag', 'fc_admin_mascot_module_tag', 10, 3);
function fc_admin_mascot_module_tag($tag, $handle, $src) {
    if ($handle !== 'fc-mascot-admin') return $tag;
    return '<script type="module" src="' . esc_url($src) . '" id="fc-mascot-admin-js"></script>' . "\n";
}

function fc_admin_mascot_css(): string {
    return <<<'CSS'
    .fcm-admin { max-width: 1080px; }
    .fcm-card { background: #fff; border: 1px solid #c3c4c7; padding: 14px 16px; margin: 0 0 16px; }
    .fcm-card > h2 { margin: 0 0 4px; font-size: 14px; }
    .fcm-card > .description { margin: 0 0 12px; }
    .fcm-callout { border-left: 4px solid #2271b1; background: #f0f6fc; padding: 10px 12px;
                   margin: 0 0 14px; font-size: 13px; line-height: 1.6; }

    /* A state with nothing in it is dimmed and says what happens instead, so
       what still needs drawing — and what it costs — reads at a glance down the
       list. The two cases differ, so the label does too. */
    .fcm-state.is-empty > summary { color: #787c82; }
    .fcm-state.is-empty[data-fcm-empty="idle"] > summary .fcm-count::after { content: ' — using Idle'; }
    .fcm-state.is-empty[data-fcm-empty="skip"] > summary .fcm-count::after { content: ' — skipped'; }

    /* Stage: the real mascot, in a box. The mount is dropped from fixed to
       absolute (two IDs, so it wins), which makes this relative box its
       containing block AND its offsetParent — and main.js reads the frame he
       lives in off exactly that, so he confines himself here with no preview
       flag anywhere in the code. */
    #fcm-stage { position: relative; height: 300px; max-width: 720px; overflow: hidden;
                 background: #FAFAF7; border: 1px solid #ccd0d4;
                 background-image: linear-gradient(rgba(10,10,10,.06) 1px, transparent 1px),
                                   linear-gradient(90deg, rgba(10,10,10,.06) 1px, transparent 1px);
                 background-size: 16px 16px; }
    #fcm-stage #fosscomm-mascot { position: absolute; }
    .fcm-stage-bar { display: flex; align-items: center; gap: 10px; max-width: 722px;
                     border: 1px solid #c3c4c7; border-top: 0; background: #fff; padding: 10px 12px;
                     box-sizing: border-box; }
    .fcm-stage-bar .description { margin: 0; }

    .fcm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(232px, 1fr)); gap: 12px 20px; }
    .fcm-num > label { display: block; }
    .fcm-num .fcm-label { display: block; font-weight: 600; font-size: 12px; margin-bottom: 2px; }
    .fcm-num input[type=number], .fcm-num select { width: 100%; }
    .fcm-num .fcm-help { color: #646970; font-size: 11px; display: block; margin-top: 2px; line-height: 1.4; }
    .fcm-num .fcm-range { color: #8c8f94; font-size: 11px; }

    /* States */
    .fcm-state { border: 1px solid #c3c4c7; background: #fff; margin: 0 0 12px; }
    .fcm-state > summary { padding: 10px 14px; cursor: pointer; font-weight: 600; background: #f6f7f7; }
    .fcm-state > summary .fcm-count { font-weight: 400; color: #646970; margin-left: 6px; }
    .fcm-state-body { padding: 14px; border-top: 1px solid #dcdcde; }
    .fcm-rules { display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: flex-end;
                 padding-bottom: 12px; margin-bottom: 4px; }
    .fcm-rules .fcm-num { flex: 0 0 190px; }
    .fcm-loop-note { font-size: 12px; margin: 0 0 12px; padding-bottom: 12px;
                     border-bottom: 1px solid #f0f0f1; line-height: 1.6; }
    .fcm-loop-note .fcm-warn { color: #b32d2e; font-weight: 600; }
    .fcm-loop-note button { margin-left: 6px; }

    /* Entries */
    .fcm-entry { border: 1px solid #dcdcde; border-left: 3px solid #2271b1; background: #fff;
                 padding: 12px; margin: 0 0 10px; position: relative; }
    .fcm-entry-head { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
    .fcm-entry-head input[type=text] { flex: 1 1 200px; }
    .fcm-stage-row { display: grid; grid-template-columns: auto 1fr; gap: 16px; align-items: start; margin-top: 12px; }

    /* The preview is sized to the frame by admin.js and rendered at a whole-number
       scale, so one art pixel is a whole number of screen pixels. The checker
       behind it is the transparency backdrop. */
    .fcm-preview-box { border: 1px solid #dcdcde; overflow: hidden; display: inline-block;
                       background: repeating-conic-gradient(#ececed 0% 25%, #fafafa 0% 50%) 0 0 / 12px 12px; }
    .fcm-preview-inner { position: relative; }
    .fcm-bounce-demo { display: flex; gap: 4px; margin-top: 6px; flex-wrap: wrap; }
    .fcm-bounce-demo .button.is-running { border-color: #2271b1; color: #2271b1; }
    /* left/top only, never `inset: 0` — paintCell writes an explicit width and
       height, and against a stretched box those two would be fighting.
       transform-origin matches the front end so the squash preview squashes him
       onto his feet rather than about his middle. */
    .fcm-preview-inner .fcm-sprite { position: absolute; left: 0; top: 0; pointer-events: none;
                                     transform-origin: 50% 100%; }
    .fcm-preview-inner .fcm-particles { position: absolute; left: 50%; top: 50%; width: 0; height: 0; }
    .fcm-transport { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
    .fcm-transport .button { min-width: 34px; padding: 0 6px; }
    .fcm-frame-label { color: #646970; font-size: 11px; }
    .fcm-sheet-meta { color: #646970; font-size: 11px; margin: 0; line-height: 1.5; }

    .fcm-timeline { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .fcm-frame { border: 1px solid #dcdcde; padding: 4px; text-align: center; background: #fbfbfc;
                 cursor: pointer; }
    .fcm-frame:hover { border-color: #2271b1; }
    .fcm-frame.is-current { border-color: #2271b1; background: #f0f6fc; box-shadow: inset 0 0 0 1px #2271b1; }
    .fcm-frame span { display: block; font-size: 10px; color: #646970; }
    .fcm-frame input { width: 62px; }
    .fcm-empty { color: #646970; font-style: italic; }

    .fcm-row-tools { display: flex; gap: 6px; margin-left: auto; }
    .fcm-particle { margin-top: 12px; border-top: 1px dashed #dcdcde; padding-top: 10px; }
    .fcm-particle > summary { cursor: pointer; font-weight: 600; font-size: 12px; }
    .fcm-particle-body { margin-top: 10px; }
    .fcm-media { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .fcm-media .fcm-filename { color: #646970; font-size: 11px; word-break: break-all; flex: 1 1 100%; }
    [hidden] { display: none !important; }
CSS;
}

/* ─────────────────────────────────────────────────────────────────────────────
   FIELD RENDERERS
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * One number box from a [default, min, max, step, label, help] declaration.
 *
 * `$hook` stamps a data attribute on the <input> so admin.js can find it by
 * role. That matters: the alternative is matching on the visible label text,
 * which silently breaks the timeline the first time someone rewords a label.
 */
function fc_mascot_number(string $name, $value, array $def, string $hook = ''): void {
    [$default, $min, $max, $step, $label, $help] = $def;
    // The label WRAPS the input rather than pointing at an id. Entry rows are
    // cloned from a <template> whose placeholders are rewritten in JS, and
    // sanitize_key() would have lowercased __INDEX__ out of the id where that
    // rewrite can't reach it — every cloned row would then carry the same id.
    ?>
    <div class="fcm-num">
        <label>
            <span class="fcm-label"><?php echo esc_html($label); ?></span>
            <input type="number"
                   name="<?php echo esc_attr($name); ?>"
                   value="<?php echo esc_attr((string) (is_numeric($value) ? $value : $default)); ?>"
                   min="<?php echo esc_attr((string) $min); ?>"
                   max="<?php echo esc_attr((string) $max); ?>"
                   step="<?php echo esc_attr((string) $step); ?>"
                   <?php echo $hook !== '' ? 'data-fcm-' . esc_attr($hook) : ''; ?>>
        </label>
        <span class="fcm-help"><?php echo esc_html($help); ?>
            <span class="fcm-range"><?php printf('(%s–%s)', esc_html((string) $min), esc_html((string) $max)); ?></span>
        </span>
    </div>
    <?php
}

/** A checkbox with the hidden 0 that makes "unchecked" actually post. */
function fc_mascot_checkbox(string $name, $value, string $label, string $help = ''): void {
    ?>
    <div class="fcm-num">
        <input type="hidden" name="<?php echo esc_attr($name); ?>" value="0">
        <label>
            <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked(!empty($value)); ?>>
            <?php echo esc_html($label); ?>
        </label>
        <?php if ($help !== '') : ?><span class="fcm-help"><?php echo esc_html($help); ?></span><?php endif; ?>
    </div>
    <?php
}

/**
 * The media picker for one sheet.
 *
 * The hidden input is the value; admin.js reads the URL out of it, measures the
 * image and rebuilds the timeline beside it. `data-fcm-sheet` is the hook.
 */
function fc_mascot_sheet_field(string $name, string $value, string $label): void {
    ?>
    <div class="fcm-media" data-fcm-media>
        <input type="hidden" name="<?php echo esc_attr($name); ?>"
               value="<?php echo esc_attr($value); ?>" data-fcm-sheet>
        <button type="button" class="button" data-fcm-pick><?php
            echo esc_html($value !== '' ? 'Replace ' . $label : 'Select ' . $label);
        ?></button>
        <button type="button" class="button-link-delete" data-fcm-clear <?php
            echo $value === '' ? 'hidden' : ''; ?>>Remove</button>
        <span class="fcm-filename" data-fcm-filename><?php
            echo esc_html($value === '' ? '' : basename((string) (parse_url($value, PHP_URL_PATH) ?: $value)));
        ?></span>
    </div>
    <?php
}

/** The direction <select>. */
function fc_mascot_direction_field(string $name, string $value): void {
    ?>
    <div class="fcm-num">
        <label>
            <span class="fcm-label">Direction</span>
            <select name="<?php echo esc_attr($name); ?>" data-fcm-direction>
                <?php foreach (fc_mascot_directions() as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <span class="fcm-help">Ping-pong drops the repeated end frame, so the turn reads as a turn.</span>
    </div>
    <?php
}

/**
 * One `entry_extra` control — a clamped number, or a whitelisted dropdown.
 *
 * `$key` becomes a `data-fcm-extra-<key>` hook on the input so admin.js can find
 * a specific one without matching on its label, which would break the moment
 * anybody reworded it. Loops uses this to keep the total-length note in step.
 */
function fc_mascot_entry_extra_field(string $name, $value, array $def, string $key = ''): void {
    if (($def['type'] ?? 'number') === 'select') {
        ?>
        <div class="fcm-num">
            <label>
                <span class="fcm-label"><?php echo esc_html((string) $def['label']); ?></span>
                <select name="<?php echo esc_attr($name); ?>">
                    <?php foreach ((array) $def['options'] as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>"
                            <?php selected((string) $value, (string) $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <span class="fcm-help"><?php echo esc_html((string) ($def['help'] ?? '')); ?></span>
        </div>
        <?php
        return;
    }
    fc_mascot_number($name, $value, [
        $def['default'] ?? 0, $def['min'] ?? 0, $def['max'] ?? 100, $def['step'] ?? 1,
        (string) $def['label'], (string) ($def['help'] ?? ''),
    ], $key !== '' ? 'extra-' . $key : '');
}

/**
 * One animation entry.
 *
 * `$prefix` is the full POST prefix — `fc_mascot[states][idle][entries][3]` or
 * `fc_mascot[sections][0]`. Section reaction rows reuse this whole block, which
 * is why it takes a prefix rather than knowing where it lives.
 *
 * `$extras` are the state's per-animation fields (Random's chance and timing).
 */
function fc_mascot_entry_row(string $prefix, array $entry, bool $is_template = false, array $extras = []): void {
    $entry = array_merge(fc_mascot_entry_defaults(), $entry);
    $particle = array_merge(fc_mascot_particle_defaults(), (array) ($entry['particle'] ?? []));
    ?>
    <div class="fcm-entry" data-fcm-entry <?php echo $is_template ? 'data-fcm-template' : ''; ?>>
        <div class="fcm-entry-head">
            <input type="text" name="<?php echo esc_attr($prefix . '[name]'); ?>"
                   value="<?php echo esc_attr((string) $entry['name']); ?>"
                   placeholder="Name this one — e.g. “fall mad”">
            <div class="fcm-row-tools">
                <button type="button" class="button" data-fcm-move="-1" title="Move up">↑</button>
                <button type="button" class="button" data-fcm-move="1" title="Move down">↓</button>
                <button type="button" class="button-link-delete" data-fcm-remove>Delete</button>
            </div>
        </div>

        <?php // A self-contained scope: admin.js finds this animation's sheet and
              // timeline within it, so the particle block must be a SIBLING
              // rather than a descendant. ?>
        <div data-fcm-sprite-block>
            <?php fc_mascot_sheet_field($prefix . '[png]', (string) $entry['png'], 'sprite sheet'); ?>

            <div class="fcm-grid" style="margin-top:10px;">
                <?php
                fc_mascot_direction_field($prefix . '[direction]', (string) $entry['direction']);
                fc_mascot_number($prefix . '[bounce]', $entry['bounce'], fc_mascot_bounce_field(), 'bounce');
                foreach ($extras as $key => $def) {
                    fc_mascot_entry_extra_field($prefix . '[' . $key . ']', $entry[$key] ?? ($def['default'] ?? ''), $def, $key);
                }
                ?>
            </div>

            <div class="fcm-stage-row">
                <div>
                    <?php // Sized to the frame by admin.js, and the particle layer
                          // rides inside it so a particle is previewed against the
                          // animation it belongs to rather than on its own. ?>
                    <div class="fcm-preview-box" data-fcm-preview>
                        <div class="fcm-preview-inner">
                            <div class="fcm-sprite" data-fcm-preview-sprite></div>
                            <div class="fcm-particles" data-fcm-preview-particles></div>
                        </div>
                    </div>
                    <div class="fcm-transport">
                        <button type="button" class="button" data-fcm-play title="Play / pause">❚❚</button>
                        <span class="fcm-frame-label" data-fcm-frame-label></span>
                    </div>
                    <?php
                    // Two ways to SEE bounciness, because there are two of them
                    // and they are easy to mistake for one.
                    //
                    // Which buttons appear is decided in admin.js from the frame
                    // count: the sheet has not been measured at this point, and
                    // the count comes from the image rather than from anything
                    // stored here.
                    ?>
                    <div class="fcm-bounce-demo" data-fcm-bounce-demo hidden>
                        <button type="button" class="button button-small" data-fcm-demo-entry
                                title="Play the arrival pop once, on the first frame">Spawn bounce</button>
                        <button type="button" class="button button-small" data-fcm-demo-frames
                                title="Run the timeline, popping each frame by its own bounciness">Frame bounce</button>
                    </div>
                </div>
                <div>
                    <p class="fcm-sheet-meta" data-fcm-meta>No sheet selected.</p>
                    <p style="margin:8px 0 0;"><strong>Timeline</strong>
                        <span class="fcm-help">Per frame: <strong>ms</strong> it is held, and its
                            <strong>bounciness</strong>. Click a frame to hold it.</span></p>
                    <?php
                    // Bounces are seeded from the animation's own bounciness for
                    // any frame that has none, so an animation saved before this
                    // existed opens with every frame already showing the value it
                    // has been using all along, rather than a column of blanks.
                    $frames  = array_values((array) $entry['frames']);
                    $bounces = array_values((array) ($entry['bounces'] ?? []));
                    $seed    = is_numeric($entry['bounce'] ?? null) ? (float) $entry['bounce'] : 1;
                    for ($i = 0; $i < count($frames); $i++) {
                        if (!isset($bounces[$i]) || !is_numeric($bounces[$i])) $bounces[$i] = $seed;
                    }
                    [, $bmin, $bmax, $bstep] = fc_mascot_bounce_field();
                    ?>
                    <div class="fcm-timeline" data-fcm-timeline
                         data-fcm-frame-name="<?php echo esc_attr($prefix . '[frames]'); ?>"
                         data-fcm-bounce-name="<?php echo esc_attr($prefix . '[bounces]'); ?>"
                         data-fcm-bounce-min="<?php echo esc_attr((string) $bmin); ?>"
                         data-fcm-bounce-max="<?php echo esc_attr((string) $bmax); ?>"
                         data-fcm-bounce-step="<?php echo esc_attr((string) $bstep); ?>"
                         data-fcm-bounce-seed="<?php echo esc_attr((string) $seed); ?>"
                         data-fcm-bounces="<?php echo esc_attr(wp_json_encode($bounces)); ?>"
                         data-fcm-frames="<?php echo esc_attr(wp_json_encode($frames)); ?>">
                    </div>
                </div>
            </div>
        </div>

        <details class="fcm-particle" <?php echo $particle['png'] !== '' ? 'open' : ''; ?>>
            <summary>Particle<?php echo $particle['png'] !== '' ? ' — on' : ' — none'; ?></summary>
            <div class="fcm-particle-body" data-fcm-particle-block>
                <p class="description" style="margin-top:0;">
                    An optional second sheet that plays alongside this animation and disappears
                    with it — stars round a hard landing, z’s off a sleep, dust off a jump.
                    It shows in the preview above, around the sprite.
                </p>
                <?php fc_mascot_sheet_field($prefix . '[particle][png]', (string) $particle['png'], 'particle sheet'); ?>

                <div class="fcm-grid" style="margin-top:10px;">
                    <div class="fcm-num">
                        <label>
                            <span class="fcm-label">Motion</span>
                            <select name="<?php echo esc_attr($prefix . '[particle][motion]'); ?>" data-fcm-motion>
                                <?php foreach (fc_mascot_particle_motions() as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($particle['motion'], $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <span class="fcm-help">What they do. Everything below means the same thing whichever you pick.</span>
                    </div>
                    <?php foreach (fc_mascot_particle_fields() as $key => $def) {
                        fc_mascot_number($prefix . '[particle][' . $key . ']', $particle[$key], $def, 'p-' . str_replace('_', '-', $key));
                    } ?>
                </div>

                <p style="margin:12px 0 0;"><strong>Particle timeline</strong>
                    <span class="fcm-help">ms each frame is held.</span></p>
                <div class="fcm-timeline" data-fcm-timeline
                     data-fcm-frame-name="<?php echo esc_attr($prefix . '[particle][frames]'); ?>"
                     data-fcm-frames="<?php echo esc_attr(wp_json_encode(array_values((array) $particle['frames']))); ?>">
                </div>
            </div>
        </details>
    </div>
    <?php
}

/* ─────────────────────────────────────────────────────────────────────────────
   THE PAGE
   ───────────────────────────────────────────────────────────────────────────*/

function fc_admin_page_mascot() {
    if (!current_user_can(FC_ADMIN_CAP)) {
        wp_die(__('Insufficient permissions.', 'fosscomm'));
    }

    $saved_now = false;
    if (isset($_POST['fc_mascot_save']) && check_admin_referer('fc_mascot_save', 'fc_mascot_nonce')) {
        $raw = wp_unslash($_POST['fc_mascot'] ?? []);
        update_option(FC_MASCOT_OPTION, fc_mascot_sanitize_post(is_array($raw) ? $raw : []), false);
        $saved_now = true;
        echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
    }

    // Re-read past the memo when we just wrote, so both the form AND the preview
    // below show what was saved rather than what the request started with.
    $s = fc_mascot_settings($saved_now);
    $states = fc_mascot_states();

    // Localised HERE, not at admin_enqueue_scripts: see the note in
    // fc_admin_mascot_assets(). admin.js is a footer script, so this still
    // reaches it.
    wp_localize_script('fc-mascot-admin', 'FC_MASCOT', fc_mascot_js_config($s));

    // How much art actually exists, so the preview can say "nothing configured
    // yet" rather than sitting there empty and looking broken.
    $total_entries = 0;
    foreach ($s['states'] as $state) $total_entries += count((array) ($state['entries'] ?? []));
    ?>
    <div class="wrap fcm-admin">
        <h1>FOSSCOMM — Mascot</h1>

        <div class="fcm-card">
            <h2>Preview</h2>
            <p class="description">
                The real mascot, running the settings you last saved — grab and throw him,
                walk him with the arrow keys or <code>A</code>/<code>D</code>, jump with
                <code>W</code>, <code>↑</code> or space.
            </p>
            <div id="fcm-stage" data-fcm-entry-count="<?php echo esc_attr((string) $total_entries); ?>"></div>
            <div class="fcm-stage-bar">
                <button type="button" class="button button-primary" id="fcm-respawn">Spawn</button>
                <label>Preview size
                    <input type="number" id="fcm-preview-scale" value="3" min="0.5" max="12" step="0.5" style="width:5em;">
                </label>
                <p class="description" id="fcm-stage-status">Preview size is local to this box — the real sizes are set below.</p>
            </div>
        </div>

        <form method="post">
            <?php wp_nonce_field('fc_mascot_save', 'fc_mascot_nonce'); ?>

            <div class="fcm-card">
                <h2>General</h2>
                <div class="fcm-grid">
                    <?php
                    fc_mascot_checkbox('fc_mascot[enabled]', $s['enabled'], 'Show the mascot',
                        'Off means nothing is enqueued and nothing is printed.');
                    fc_mascot_number('fc_mascot[scale_desktop]', $s['scale_desktop'],
                        [3, 0.5, 12, 0.1, 'Size — desktop', 'Multiplier on the sprite’s own pixels. Whole numbers keep the pixel grid exact.']);
                    fc_mascot_number('fc_mascot[scale_mobile]', $s['scale_mobile'],
                        [2, 0.5, 12, 0.1, 'Size — mobile', 'Used below the breakpoint. Measured off the real window, so a narrow desktop counts too.']);
                    fc_mascot_number('fc_mascot[mobile_breakpoint]', $s['mobile_breakpoint'],
                        [768, 0, 3000, 1, 'Mobile breakpoint', 'px of window width below which the mobile size applies.']);
                    ?>
                    <div class="fcm-num">
                        <label>
                            <span class="fcm-label">Your art faces</span>
                            <select name="fc_mascot[art_facing]">
                                <option value="left" <?php selected($s['art_facing'] ?? 'left', 'left'); ?>>Left</option>
                                <option value="right" <?php selected($s['art_facing'] ?? 'left', 'right'); ?>>Right</option>
                            </select>
                        </label>
                        <span class="fcm-help">
                            Which way he is drawn looking in the sheets. He is mirrored from this when he
                            moves the other way, so getting it wrong makes him walk backwards everywhere.
                        </span>
                    </div>
                </div>
            </div>

            <?php foreach (fc_mascot_physics_groups() as $gkey => $group) : ?>
                <div class="fcm-card">
                    <h2>Physics — <?php echo esc_html($group['label']); ?></h2>
                    <p class="description"><?php echo esc_html($group['help']); ?></p>
                    <div class="fcm-grid">
                        <?php foreach ($group['fields'] as $fkey => $def) :
                            $key = $gkey . '_' . $fkey;
                            fc_mascot_number('fc_mascot[physics][' . $key . ']', $s['physics'][$key] ?? $def[0], $def);
                        endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="fcm-card">
                <h2>States</h2>
                <p class="description">
                    Each state can hold several named animations — add “fall mad” and “fall sad” and one is
                    picked at random each time he falls, and kept for the whole fall. The rules belong to the
                    state, so every animation in it behaves the same and only looks different.
                </p>
                <div class="fcm-callout">
                    <strong>Only Idle is required.</strong> Fill it in and everything works — he drops in,
                    lands, walks, jumps and can be thrown, wearing the Idle animation wherever a state has
                    none of its own. Fill the rest in as you draw them; each one you add simply replaces the
                    stand-in. States that hand straight over — Land, After-land, the sleep transitions — are
                    skipped entirely when empty rather than showing Idle for the length of a landing.
                </div>

                <?php foreach ($states as $key => $def) :
                    $stored  = $s['states'][$key] ?? ['rules' => [], 'entries' => []];
                    $rules   = (array) $stored['rules'];
                    $entries = (array) $stored['entries'];
                    $prefix  = 'fc_mascot[states][' . $key . ']';
                    ?>
                    <?php // Empty behaviour comes from the wiring: a state that hands
                          // over is skipped, one that does not borrows Idle. Declared
                          // here so the summary can say which without duplicating the
                          // rule in JS. ?>
                    <details class="fcm-state" data-fcm-state
                             data-fcm-empty="<?php echo esc_attr($def['next'] ? 'skip' : 'idle'); ?>"
                             <?php echo empty($entries) ? '' : 'open'; ?>>
                        <summary>
                            <?php echo esc_html($def['label']); ?>
                            <span class="fcm-count"><?php
                                echo esc_html(count($entries) === 1 ? '1 animation' : count($entries) . ' animations');
                            ?></span>
                        </summary>
                        <div class="fcm-state-body">
                            <p class="description" style="margin-top:0;"><?php echo esc_html($def['help']); ?></p>

                            <?php $state_rules = (array) ($def['rules'] ?? []); ?>
                            <?php if ($state_rules) : ?>
                                <div class="fcm-rules">
                                    <?php foreach ($state_rules as $rkey => $rdef) {
                                        // No hooks left here. "Play for" used to be wired
                                        // to admin.js so it could show the animation's real
                                        // length and warn about values that would cut it
                                        // off mid-loop — all of which existed only because
                                        // the control was a duration. Loops is a count on
                                        // the animation itself; there is nothing to
                                        // reconcile and nothing to warn about.
                                        fc_mascot_number(
                                            $prefix . '[rules][' . $rkey . ']',
                                            $rules[$rkey] ?? $rdef[0],
                                            $rdef
                                        );
                                    } ?>
                                </div>
                                <p class="fcm-loop-note" data-fcm-loop-note hidden></p>
                            <?php endif; ?>

                            <div data-fcm-entries data-fcm-prefix="<?php echo esc_attr($prefix . '[entries]'); ?>"
                                 data-fcm-state-key="<?php echo esc_attr($key); ?>">
                                <?php foreach (array_values($entries) as $i => $entry) {
                                    fc_mascot_entry_row(
                                        $prefix . '[entries][' . $i . ']',
                                        (array) $entry,
                                        false,
                                        (array) ($def['entry_extra'] ?? [])
                                    );
                                } ?>
                            </div>
                            <p><button type="button" class="button" data-fcm-add>Add animation</button></p>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div class="fcm-card">
                <h2>Section reactions</h2>
                <p class="description">
                    A one-off animation when a section scrolls into view. The active section is worked out
                    exactly the way the sidebar nav works it out, so his reaction and the highlighted nav
                    item always agree. Target a section by CSS selector — <code>#sponsors</code>,
                    <code>#schedule</code>. Add two rows with the same target and one is picked at random.
                </p>
                <div data-fcm-entries data-fcm-prefix="fc_mascot[sections]" data-fcm-section>
                    <?php foreach (array_values((array) $s['sections']) as $i => $row) :
                        $row = (array) $row;
                        $prefix = 'fc_mascot[sections][' . $i . ']';
                        ?>
                        <div class="fcm-section-row">
                            <div class="fcm-grid" style="margin-bottom:8px;">
                                <div class="fcm-num">
                                    <label>
                                        <span class="fcm-label">Section selector</span>
                                        <input type="text" name="<?php echo esc_attr($prefix . '[selector]'); ?>"
                                               value="<?php echo esc_attr((string) ($row['selector'] ?? '')); ?>"
                                               placeholder="#sponsors" style="width:100%;">
                                    </label>
                                    <span class="fcm-help">Any CSS selector that matches one &lt;section&gt;.</span>
                                </div>
                                <?php fc_mascot_number($prefix . '[loops]', $row['loops'] ?? 1,
                                    [1, 1, 50, 1, 'Loops', 'How many times this animation plays through before he '
                                    . 'goes back to what he was doing. It also stops the moment you scroll out of '
                                    . 'the section, so this is the LONGEST it will run, not the exact length.']); ?>
                            </div>
                            <?php fc_mascot_entry_row($prefix, $row); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p><button type="button" class="button" data-fcm-add>Add reaction</button></p>
            </div>

            <p><button type="submit" name="fc_mascot_save" value="1" class="button button-primary">Save changes</button></p>
        </form>

        <?php
        // Row templates. __PREFIX__ / __INDEX__ are rewritten by admin.js on add.
        // States whose animations carry extra fields (Random's chance and
        // timing) get their own template, because a generic one would add rows
        // missing those inputs and they would save as defaults.
        ?>
        <template id="fcm-entry-template"><?php fc_mascot_entry_row('__PREFIX__[__INDEX__]', [], true); ?></template>
        <?php foreach ($states as $skey => $sdef) :
            if (empty($sdef['entry_extra'])) continue; ?>
            <template id="fcm-entry-template-<?php echo esc_attr($skey); ?>"><?php
                fc_mascot_entry_row('__PREFIX__[__INDEX__]', [], true, (array) $sdef['entry_extra']);
            ?></template>
        <?php endforeach; ?>
        <template id="fcm-section-template">
            <div class="fcm-section-row">
                <div class="fcm-grid" style="margin-bottom:8px;">
                    <div class="fcm-num">
                        <label>
                            <span class="fcm-label">Section selector</span>
                            <input type="text" name="__PREFIX__[__INDEX__][selector]" value=""
                                   placeholder="#sponsors" style="width:100%;">
                        </label>
                        <span class="fcm-help">Any CSS selector that matches one &lt;section&gt;.</span>
                    </div>
                    <?php fc_mascot_number('__PREFIX__[__INDEX__][loops]', 1,
                        [1, 1, 50, 1, 'Loops', 'How many times this animation plays through before he '
                        . 'goes back to what he was doing. It also stops the moment you scroll out of '
                        . 'the section, so this is the LONGEST it will run, not the exact length.']); ?>
                </div>
                <?php fc_mascot_entry_row('__PREFIX__[__INDEX__]', [], true); ?>
            </div>
        </template>
    </div>
    <?php
}

/**
 * Validate the whole posted form.
 *
 * Rows are re-indexed with array_values because deleting a row client-side
 * leaves a gap in the POST indices; storing the gap would work (PHP is happy
 * with a sparse array) but would make the stored option grow an ever-sparser
 * key space over successive edits.
 */
function fc_mascot_sanitize_post(array $raw): array {
    $out = fc_mascot_defaults();

    $out['enabled'] = !empty($raw['enabled']);
    $out['debug']   = !empty($raw['debug']);
    $out['scale_desktop']     = fc_mascot_clamp_scale($raw['scale_desktop'] ?? 3);
    $out['scale_mobile']      = fc_mascot_clamp_scale($raw['scale_mobile'] ?? 2);
    $out['mobile_breakpoint'] = max(0, min(3000, (int) ($raw['mobile_breakpoint'] ?? FC_MASCOT_BREAKPOINT)));

    $physics = [];
    foreach (array_keys(fc_mascot_physics_defaults()) as $key) {
        $physics[$key] = fc_mascot_clamp_physics($key, $raw['physics'][$key] ?? null);
    }
    $out['physics'] = $physics;

    $states = [];
    foreach (fc_mascot_states() as $key => $def) {
        $posted  = (array) ($raw['states'][$key] ?? []);
        $rulesIn = (array) ($posted['rules'] ?? []);
        $extras  = (array) ($def['entry_extra'] ?? []);

        // Only the declared rules are stored. `loop` and `next` are the
        // machine's wiring, not settings, so there is nothing to read for them.
        $rules = [];
        foreach ((array) ($def['rules'] ?? []) as $rkey => $rdef) {
            [$default, $min, $max, $step] = $rdef;
            $v = $rulesIn[$rkey] ?? null;
            if (!is_numeric($v)) { $rules[$rkey] = $default; continue; }
            $n = ($step >= 1) ? (int) round((float) $v) : (float) $v;
            $rules[$rkey] = max($min, min($max, $n));
        }

        $entries = [];
        foreach ((array) ($posted['entries'] ?? []) as $entry) {
            $clean = fc_mascot_sanitize_entry($entry, $extras);
            if ($clean !== null) $entries[] = $clean;
        }

        $states[$key] = ['rules' => $rules, 'entries' => $entries];
    }
    $out['states'] = $states;

    $sections = [];
    foreach ((array) ($raw['sections'] ?? []) as $row) {
        if (!is_array($row)) continue;
        $selector = trim((string) ($row['selector'] ?? ''));
        $entry    = fc_mascot_sanitize_entry($row);
        // A row needs both a target and art to do anything; either alone is an
        // abandoned edit, not a setting.
        if ($selector === '' || $entry === null) continue;
        $sections[] = array_merge($entry, [
            'selector' => sanitize_text_field($selector),
            'loops'    => max(1, min(50, (int) ($row['loops'] ?? 1))),
        ]);
    }
    $out['sections'] = $sections;

    return $out;
}
