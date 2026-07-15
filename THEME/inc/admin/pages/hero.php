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
    $cta_fields = [
        'label'       => ['type' => 'bilingual', 'label' => 'CTA label'],
        'label_hover' => ['type' => 'bilingual', 'label' => 'Hover label (optional — scrambles in on hover)'],
        'url'         => ['type' => 'url',       'label' => 'URL (#section or https://…)'],
    ];

    fc_render_section_admin_page([
        'slug'       => 'fc_section_hero',
        'title'      => 'Home',
        'option_key' => 'fc_section_hero',
        'schema'     => [
            'top_label'           => 'bilingual',
            'dates'               => 'bilingual',
            'dates_sub'           => 'bilingual',
            'venue'               => 'bilingual',
            'venue_sub'           => 'bilingual',
            'cost'                => 'bilingual',
            'cost_sub'            => 'bilingual',
            'brand'               => 'text',
            'year'                => 'text',
            'email'               => 'text',
        ],
        'render_form' => function ($values) use ($social_fields, $cta_fields) {
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
            fc_bilingual_field('dates_sub', $values, ['label' => 'When — second line (small grey, shown under the value)']);
            fc_bilingual_field('venue',     $values, ['label' => 'Where · Πού']);
            fc_bilingual_field('venue_sub', $values, ['label' => 'Where — second line (small grey, shown under the value)']);
            fc_bilingual_field('cost',      $values, ['label' => 'How much · Πόσο']);
            fc_bilingual_field('cost_sub',  $values, ['label' => 'How much — second line (small grey, e.g. “…and beer”)']);
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
            <p class="description">Each CTA renders on its own line in the right panel. Add as many as you want and drag to reorder. The optional <strong>hover label</strong> scrambles in with the "hack" effect on mouseover (desktop only); leave it empty for the standard accent-color hover.</p>
            <p class="description">URLs accept a full address (<code>https://…</code>) or an on-page anchor: <code>#manifesto</code>, <code>#speakers</code>, <code>#schedule</code>, <code>#news</code>, <code>#venue</code>, <code>#sponsors</code>, <code>#volunteer</code>, <code>#faq</code>.</p>
            <?php
            fc_repeater([
                'name'      => 'fc_ctas',
                'rows'      => (array) ($values['ctas'] ?? []),
                'fields'    => $cta_fields,
                'add_label' => 'Add CTA',
            ]);
            ?>
            <?php
        },
        'post_process' => function ($clean, $raw) use ($social_fields, $cta_fields) {
            $rows = isset($raw['fc_socials']) && is_array($raw['fc_socials']) ? $raw['fc_socials'] : [];
            $clean['socials'] = fc_sanitize_repeater($rows, $social_fields);
            $cta_rows = isset($raw['fc_ctas']) && is_array($raw['fc_ctas']) ? $raw['fc_ctas'] : [];
            $clean['ctas'] = fc_sanitize_repeater($cta_rows, $cta_fields);
            return $clean;
        },
    ]);
}
