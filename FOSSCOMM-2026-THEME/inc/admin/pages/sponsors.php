<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_sponsors', 20);
function fc_admin_register_sponsors() {
    add_submenu_page(FC_ADMIN_SLUG, 'Sponsors', '— Sponsors', FC_ADMIN_CAP, 'fc_section_sponsors', 'fc_admin_page_sponsors');
}

function fc_admin_page_sponsors() {
    $title_defaults = [
        'title_el' => 'Όσοι κάνουν δυνατό το «δωρεάν».',
        'title_en' => 'The people who make ‘free’ possible.',
    ];
    $fields = [
        'name'  => ['type' => 'text',   'label' => 'Organization name'],
        'tier'  => ['type' => 'select', 'label' => 'Tier', 'options' => [
            'platinum'  => 'Platinum',
            'gold'      => 'Gold',
            'silver'    => 'Silver',
            'community' => 'Community partner',
        ]],
        'logo'     => ['type' => 'media', 'label' => 'Logo (default)'],
        'logo_alt' => ['type' => 'media', 'label' => 'Logo on hover (e.g. colour variant)'],
        'url'      => ['type' => 'url', 'label' => 'Link (optional)'],
    ];
    fc_render_collection_admin_page([
        'slug'       => 'fc_section_sponsors',
        'title'      => 'Sponsors',
        'option_key' => 'fc_sponsors',
        'intro'      => 'Grouped by tier. The <strong>default</strong> logo is shown normally and swaps to the '
            . '<strong>hover</strong> logo on mouseover (e.g. a colour variant). Both keep their real colours. '
            . 'Upload both at the same <strong>5:2 (2.5:1) aspect ratio</strong> (e.g. 600×240px) so every cell in a '
            . 'tier matches. Higher tiers render larger automatically; rows are centered and balanced — no empty cells.',
        'fields'     => $fields,
        'add_label'  => 'Add sponsor',
        'render_before' => function ($rows) use ($title_defaults) {
            fc_section_meta_render('sponsors', $title_defaults);
            echo '<hr style="margin:2rem 0;">';
            $cta = get_option('fc_sponsors_cta', []);
            if (!is_array($cta)) $cta = [];
            $pdf = (string) ($cta['pdf'] ?? '');
            $url = (string) ($cta['url'] ?? '');
            $shine = get_option('fc_sponsors_shine', []);
            if (!is_array($shine)) $shine = [];
            // Backwards-compat: the old single 'fc_sponsors_shine_color' option
            // (one colour for every tier) seeds all four tiers when migrating.
            $legacy = (string) get_option('fc_sponsors_shine_color', '');
            $shine_tiers = [
                'platinum'  => (string) ($shine['platinum']  ?? $legacy),
                'gold'      => (string) ($shine['gold']      ?? $legacy),
                'silver'    => (string) ($shine['silver']    ?? $legacy),
                'community' => (string) ($shine['community'] ?? $legacy),
            ];
            ?>
            <h2 style="margin-top:0;">Shine effect — one colour per tier</h2>
            <p class="description">Sweeps a coloured highlight across every logo (masked to the logo's non-transparent pixels) every 3 seconds. <strong>Leave a tier empty</strong> to disable the shine for that tier; the other tiers are unaffected.</p>
            <div class="fc-grid-2">
                <div class="fc-field">
                    <label>Platinum shine colour</label>
                    <input type="text" name="fc_sponsors_shine[platinum]" value="<?php echo esc_attr($shine_tiers['platinum']); ?>" placeholder="#ffcc00">
                </div>
                <div class="fc-field">
                    <label>Gold shine colour</label>
                    <input type="text" name="fc_sponsors_shine[gold]" value="<?php echo esc_attr($shine_tiers['gold']); ?>" placeholder="#ffcc00">
                </div>
                <div class="fc-field">
                    <label>Silver shine colour</label>
                    <input type="text" name="fc_sponsors_shine[silver]" value="<?php echo esc_attr($shine_tiers['silver']); ?>" placeholder="#ffcc00">
                </div>
                <div class="fc-field">
                    <label>Community shine colour</label>
                    <input type="text" name="fc_sponsors_shine[community]" value="<?php echo esc_attr($shine_tiers['community']); ?>" placeholder="#ffcc00">
                </div>
            </div>
            <p class="description">Any CSS colour: <code>#ffcc00</code>, <code>rgba(255,204,0,0.6)</code>, <code>gold</code>, etc.</p>
            <hr style="margin:2rem 0;">
            <h2 style="margin-top:0;">"Become a sponsor" CTA</h2>
            <p class="description">Renders directly under the sponsor tiers on the front-end, in the same style as the hero CTAs. Upload a PDF prospectus or paste a URL; the PDF takes priority when both are set.</p>
            <?php
            fc_bilingual_field('label', $cta, [
                'label'       => 'CTA label (the arrow → is added automatically)',
                'name_prefix' => 'fc_sponsor_cta',
                'placeholder_en' => 'Become a sponsor',
                'placeholder_el' => 'Γίνε χορηγός',
            ]);
            fc_bilingual_field('hover_label', $cta, [
                'label'       => 'CTA hover label (optional — scrambles in with the “hack” effect on hover, desktop only)',
                'name_prefix' => 'fc_sponsor_cta',
                'placeholder_en' => 'Send your logo',
                'placeholder_el' => 'Στείλε το λογότυπο',
            ]);
            fc_bilingual_field('desc', $cta, [
                'label'       => 'Inline description (small mono text next to the CTA)',
                'type'        => 'textarea',
                'rows'        => 2,
                'name_prefix' => 'fc_sponsor_cta',
                'placeholder_en' => 'One-page prospectus (PDF, 240KB). Reply by 30 June 2026 to be on the printed program.',
            ]);
            ?>
            <div class="fc-field">
                <label>Prospectus PDF</label>
                <div class="fc-media" data-fc-media-type="application/pdf">
                    <input type="hidden" class="fc-media-input" name="fc_sponsor_cta[pdf]" value="<?php echo esc_attr($pdf); ?>">
                    <div class="fc-media-preview"><?php if ($pdf !== '') : ?><span class="fc-media-file"><?php echo esc_html(basename($pdf)); ?></span><?php endif; ?></div>
                    <button type="button" class="button fc-media-pick"><?php echo $pdf !== '' ? 'Replace file' : 'Select file'; ?></button>
                    <button type="button" class="button fc-media-clear"<?php echo $pdf === '' ? ' style="display:none"' : ''; ?>>Remove</button>
                </div>
                <p class="description">Uploaded to Media Library. PDFs only.</p>
            </div>
            <div class="fc-field">
                <label>Fallback URL (used if no PDF is uploaded)</label>
                <input type="url" name="fc_sponsor_cta[url]" value="<?php echo esc_attr($url); ?>" placeholder="https://…">
            </div>
            <hr style="margin:2rem 0;">
            <h2>Sponsors list</h2>
            <?php
        },
        'post_process' => function ($clean, $raw) {
            fc_section_meta_save('sponsors', $raw);
            $cta_raw = isset($raw['fc_sponsor_cta']) && is_array($raw['fc_sponsor_cta']) ? $raw['fc_sponsor_cta'] : [];
            $cta_clean = [
                'label_el'       => sanitize_text_field((string) ($cta_raw['label_el'] ?? '')),
                'label_en'       => sanitize_text_field((string) ($cta_raw['label_en'] ?? '')),
                'hover_label_el' => sanitize_text_field((string) ($cta_raw['hover_label_el'] ?? '')),
                'hover_label_en' => sanitize_text_field((string) ($cta_raw['hover_label_en'] ?? '')),
                'desc_el'        => sanitize_textarea_field((string) ($cta_raw['desc_el'] ?? '')),
                'desc_en'        => sanitize_textarea_field((string) ($cta_raw['desc_en'] ?? '')),
                'pdf'            => esc_url_raw((string) ($cta_raw['pdf'] ?? '')),
                'url'            => esc_url_raw((string) ($cta_raw['url'] ?? '')),
            ];
            update_option('fc_sponsors_cta', $cta_clean, false);

            // Per-tier shine colours. Plain CSS-colour strings; empty disables
            // the shine for that tier. The legacy single-colour option is
            // removed below so future loads come from fc_sponsors_shine only.
            $shine_raw = isset($raw['fc_sponsors_shine']) && is_array($raw['fc_sponsors_shine']) ? $raw['fc_sponsors_shine'] : [];
            $shine_clean = [];
            foreach (['platinum', 'gold', 'silver', 'community'] as $tier) {
                $shine_clean[$tier] = sanitize_text_field(trim((string) ($shine_raw[$tier] ?? '')));
            }
            update_option('fc_sponsors_shine', $shine_clean, false);
            delete_option('fc_sponsors_shine_color');

            return $clean;
        },
    ]);
}
