<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_hero', 20);
function fc_admin_register_hero() {
    add_submenu_page(FC_ADMIN_SLUG, 'Home', '— Home', FC_ADMIN_CAP, 'fc_section_hero', 'fc_admin_page_hero');
}

function fc_admin_page_hero() {
    $social_fields = [
        'label' => ['type' => 'text', 'label' => 'Label (short — e.g. YT, FB, TT, IG)'],
        'url'   => ['type' => 'url',  'label' => 'URL'],
    ];

    fc_render_section_admin_page([
        'slug'       => 'fc_section_hero',
        'title'      => 'Home',
        'option_key' => 'fc_section_hero',
        'schema'     => [
            'top_label'           => 'bilingual',
            'dates'               => 'bilingual',
            'venue'               => 'bilingual',
            'cost'                => 'bilingual',
            'brand'               => 'text',
            'year'                => 'text',
            'email'               => 'text',
            'cta_primary'         => 'bilingual',
            'cta_primary_hover'   => 'bilingual',
            'cta_primary_url'     => 'url',
            'cta_secondary'       => 'bilingual',
            'cta_secondary_hover' => 'bilingual',
            'cta_secondary_url'   => 'url',
            'cta_tertiary'        => 'bilingual',
            'cta_tertiary_hover'  => 'bilingual',
            'cta_tertiary_url'    => 'url',
        ],
        'render_form' => function ($values) use ($social_fields) {
            ?>
            <div class="fc-grid-2">
                <div class="fc-field">
                    <label>Brand text</label>
                    <input type="text" name="fc_field[brand]" value="<?php echo esc_attr((string) ($values['brand'] ?? 'FOSSCOMM')); ?>">
                </div>
                <div class="fc-field">
                    <label>Year</label>
                    <input type="text" name="fc_field[year]" value="<?php echo esc_attr((string) ($values['year'] ?? '2026')); ?>">
                </div>
            </div>
            <?php
            fc_bilingual_field('top_label', $values, ['label' => 'Bottom-left label (EN line + EL line below it)']);
            fc_bilingual_field('dates',     $values, ['label' => 'When · Πότε']);
            fc_bilingual_field('venue',     $values, ['label' => 'Where · Πού']);
            fc_bilingual_field('cost',      $values, ['label' => 'How much · Πόσο']);
            ?>
            <div class="fc-field">
                <label>Email (shown bottom-right of the FOSSCOMM panel)</label>
                <input type="text" name="fc_field[email]" value="<?php echo esc_attr((string) ($values['email'] ?? '')); ?>" placeholder="hello@fosscomm.gr">
                <p class="description">Plain text. Rendered in the same mono caps as the bottom label.</p>
            </div>
            <h2 style="margin-top:2rem;">Social links (above the email)</h2>
            <p class="description">Short labels — YT, FB, TT, IG — each links somewhere. Add as many or as few as you want; they render as a single mono line above the email. Each item: short label + URL.</p>
            <?php
            fc_repeater([
                'name'      => 'fc_socials',
                'rows'      => (array) ($values['socials'] ?? []),
                'fields'    => $social_fields,
                'add_label' => 'Add social link',
            ]);
            ?>
            <h2 style="margin-top:2rem;">CTAs</h2>
            <p class="description">The optional <strong>hover label</strong> on each CTA scrambles in with the global "hack" effect on mouseover (desktop only). Leave it empty to keep the standard accent-color hover. Each CTA renders on its own line in the right panel.</p>
            <p class="description">URLs accept a full address (<code>https://…</code>) or an on-page anchor. Valid anchors: <code>#hero</code>, <code>#manifesto</code>, <code>#speakers</code>, <code>#schedule</code>, <code>#news</code>, <code>#venue</code>, <code>#sponsors</code>, <code>#volunteer</code>, <code>#faq</code>, <code>#footer</code>.</p>
            <?php
            fc_bilingual_field('cta_primary',       $values, ['label' => 'Primary CTA label']);
            fc_bilingual_field('cta_primary_hover', $values, ['label' => 'Primary CTA hover label (optional)']);
            ?>
            <div class="fc-field">
                <label>Primary CTA URL</label>
                <input type="text" name="fc_field[cta_primary_url]" value="<?php echo esc_attr((string) ($values['cta_primary_url'] ?? '#schedule')); ?>" placeholder="#schedule or https://…">
            </div>
            <?php
            fc_bilingual_field('cta_secondary',       $values, ['label' => 'Secondary CTA label']);
            fc_bilingual_field('cta_secondary_hover', $values, ['label' => 'Secondary CTA hover label (optional)']);
            ?>
            <div class="fc-field">
                <label>Secondary CTA URL</label>
                <input type="text" name="fc_field[cta_secondary_url]" value="<?php echo esc_attr((string) ($values['cta_secondary_url'] ?? '#volunteer')); ?>" placeholder="#volunteer or https://…">
            </div>
            <?php
            fc_bilingual_field('cta_tertiary',       $values, ['label' => 'Tertiary CTA label']);
            fc_bilingual_field('cta_tertiary_hover', $values, ['label' => 'Tertiary CTA hover label (optional)']);
            ?>
            <div class="fc-field">
                <label>Tertiary CTA URL</label>
                <input type="text" name="fc_field[cta_tertiary_url]" value="<?php echo esc_attr((string) ($values['cta_tertiary_url'] ?? '#sponsors')); ?>" placeholder="#sponsors or https://…">
            </div>
            <?php
        },
        'post_process' => function ($clean, $raw) use ($social_fields) {
            $rows = isset($raw['fc_socials']) && is_array($raw['fc_socials']) ? $raw['fc_socials'] : [];
            $clean['socials'] = fc_sanitize_repeater($rows, $social_fields);
            return $clean;
        },
    ]);
}
