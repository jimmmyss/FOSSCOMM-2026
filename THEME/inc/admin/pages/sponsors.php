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
            'diamond'   => 'Diamond',
            'gold'      => 'Gold',
            'silver'    => 'Silver',
            'bronze'    => 'Bronze',
            'community' => 'Community partner',
            'in-kind'   => 'In-kind',
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
                'diamond'   => (string) ($shine['diamond']   ?? $shine['platinum'] ?? $legacy),
                'gold'      => (string) ($shine['gold']      ?? $legacy),
                'silver'    => (string) ($shine['silver']    ?? $legacy),
                'bronze'    => (string) ($shine['bronze']    ?? $legacy),
                'community' => (string) ($shine['community'] ?? $legacy),
                'in-kind'   => (string) ($shine['in-kind']   ?? $legacy),
            ];
            ?>
            <h2 style="margin-top:0;">Sponsor tiers</h2>
            <p class="description">Per tier: the bilingual <strong>name</strong> shown in its eyebrow (e.g. "05 / Gold sponsors" — the count is added automatically; blank uses the built-in default) and an optional <strong>shine colour</strong> that sweeps across that tier's logos every 3 seconds (blank = no shine for that tier). Any CSS colour works: <code>#ffcc00</code>, <code>rgba(255,204,0,0.6)</code>, <code>gold</code>.</p>
            <?php
            $tiers_opt = get_option('fc_sponsors_tiers', []);
            if (!is_array($tiers_opt)) $tiers_opt = [];
            $tier_defaults = fc_sponsor_tier_defaults();
            foreach (['diamond' => 'Diamond', 'gold' => 'Gold', 'silver' => 'Silver', 'bronze' => 'Bronze', 'community' => 'Community', 'in-kind' => 'In-kind'] as $tk => $tlabel) {
                $row = is_array($tiers_opt[$tk] ?? null) ? $tiers_opt[$tk] : [];
                // Legacy: pull a custom Platinum name into the renamed Diamond field.
                if (!$row && $tk === 'diamond' && is_array($tiers_opt['platinum'] ?? null)) {
                    $row = $tiers_opt['platinum'];
                }
                ?>
                <div style="border:1px solid #ccd0d4;background:#fff;padding:1rem 1.25rem;margin-bottom:1rem;">
                    <h3 style="margin:0 0 0.75rem;"><?php echo esc_html($tlabel); ?></h3>
                    <div class="fc-grid-2">
                        <div>
                            <?php fc_bilingual_field('label', $row, [
                                'label'          => 'Name',
                                'name_prefix'    => 'fc_sponsor_tiers[' . $tk . ']',
                                'placeholder_en' => $tier_defaults[$tk]['en'],
                                'placeholder_el' => $tier_defaults[$tk]['el'],
                            ]); ?>
                        </div>
                        <div class="fc-field">
                            <label>Shine colour</label>
                            <input type="text" name="fc_sponsors_shine[<?php echo esc_attr($tk); ?>]" value="<?php echo esc_attr($shine_tiers[$tk]); ?>" placeholder="#ffcc00">
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
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
                <input type="text" name="fc_sponsor_cta[url]" value="<?php echo esc_attr($url); ?>" placeholder="https://… or #section">
            </div>
            <hr style="margin:2rem 0;">
            <h2>Sponsors list</h2>
            <p class="description">Add each sponsor below and pick its tier. Use <strong>Add sponsor</strong> at the bottom for more, drag the <code>⋮⋮</code> handle to reorder, and <em>Save changes</em> when done. Every logo in a tier picks up that tier's shine colour set above.</p>
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
            foreach (['diamond', 'gold', 'silver', 'bronze', 'community', 'in-kind'] as $tier) {
                $shine_clean[$tier] = sanitize_text_field(trim((string) ($shine_raw[$tier] ?? '')));
            }
            update_option('fc_sponsors_shine', $shine_clean, false);
            delete_option('fc_sponsors_shine_color');

            // Bilingual tier names.
            $tiers_raw   = isset($raw['fc_sponsor_tiers']) && is_array($raw['fc_sponsor_tiers']) ? $raw['fc_sponsor_tiers'] : [];
            $tiers_clean = [];
            foreach (['diamond', 'gold', 'silver', 'bronze', 'community', 'in-kind'] as $tier) {
                $row = is_array($tiers_raw[$tier] ?? null) ? $tiers_raw[$tier] : [];
                $tiers_clean[$tier] = [
                    'label_el' => sanitize_text_field((string) ($row['label_el'] ?? '')),
                    'label_en' => sanitize_text_field((string) ($row['label_en'] ?? '')),
                ];
            }
            update_option('fc_sponsors_tiers', $tiers_clean, false);

            return $clean;
        },
    ]);
}
