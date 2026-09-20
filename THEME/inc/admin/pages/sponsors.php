<?php
/**
 * FOSSCOMM → Sponsors.
 *
 * The page is a NESTED repeater: tiers on the outside, that tier's sponsors
 * inside it. Tiers are rows now, not a fixed vocabulary — there is no list of
 * six slugs anywhere in the theme any more, so "add a tier" is a button rather
 * than a code change in four files.
 *
 * One colour per tier does two jobs, deliberately: it draws the hollow run in
 * the tier's title AND it is the colour the shine sweeps in. They were separate
 * before and there was no case for two — a tier is one visual identity.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_sponsors', 20);
function fc_admin_register_sponsors() {
    add_submenu_page(FC_ADMIN_SLUG, 'Sponsors', '— Sponsors', FC_ADMIN_CAP, 'fc_section_sponsors', 'fc_admin_page_sponsors');
}

function fc_admin_page_sponsors() {

    // The sponsors inside one tier.
    $sponsor_fields = [
        'name'     => ['type' => 'text',  'label' => 'Organization name'],
        'logo'     => ['type' => 'media', 'label' => 'Logo', 'full' => true],
        'logo_alt' => ['type' => 'media', 'label' => 'Logo on hover (optional — e.g. a colour variant)', 'full' => true],
        'url'      => ['type' => 'url',   'label' => 'Link (optional)'],
    ];

    $fields = [
        'desc' => [
            'type'  => 'bilingual',
            'label' => 'Description (small line ABOVE the title)',
            'help'  => 'Set in the speakers’ role face — mono, uppercase, muted. Short: “Tier 1”, “Επίπεδο 1”.',
        ],
        'title' => [
            'type'  => 'bilingual',
            'label' => 'Title',
            'help'  => 'Set in the speakers’ name face. Two markers, both in the tier colour: '
                . '<code>*one asterisk*</code> fills that run, <code>**two**</code> outlines it. '
                . '<code>Gold **sponsors**</code>. No hover — this heading is a label, not a link.',
        ],
        'colour' => [
            'type'    => 'colour',
            'label'   => 'Tier colour',
            'default' => FC_OUTLINE_REST,
            'help'    => 'The <code>*marked*</code> run in the title, and the colour the shine sweeps in.',
        ],
        'line' => [
            'type'    => 'colour',
            'label'   => 'Bottom line colour',
            'default' => FC_OUTLINE_REST,
            'help'    => 'The rule under the tier. Leave blank to follow the tier colour.',
        ],
        'shine' => [
            'type'  => 'bool',
            'label' => 'Shine',
            'help'  => 'Sweep a mosaic highlight across this tier’s logos every 3 seconds.',
        ],
        'sponsors' => [
            'type'      => 'repeater',
            'label'     => 'Sponsors in this tier',
            'add_label' => 'Add sponsor',
            'fields'    => $sponsor_fields,
            'help'      => 'Drag the <code>⋮⋮</code> handle to reorder within the tier. Every logo is drawn at the '
                . '<strong>same height</strong> and takes whatever width its shape needs, so nothing has to be '
                . 'uploaded at a matching size or aspect ratio. Crop the file tight to the logo — any transparent '
                . 'or white margin in the image is counted as part of it and makes that logo look smaller than '
                . 'the rest.',
        ],
    ];

    fc_render_collection_admin_page([
        'slug'       => 'fc_section_sponsors',
        'title'      => 'Sponsors',
        'option_key' => 'fc_sponsors',
        'intro'      => 'Tiers are rows: add as many as you like, name them what you like, give each a colour. '
            . 'Sponsors live <strong>inside</strong> their tier — use that tier’s own <strong>Add sponsor</strong> button. '
            . 'Each tier renders as one continuous line of logos; when there are more than fit, the line becomes a '
            . 'draggable belt rather than wrapping onto a second row.',
        'fields'     => $fields,
        'add_label'  => 'Add tier',
        'render_before' => function ($rows) {
            /* ── The section's heading: a sentence with the money in the middle.
             *
             * Four fields rather than one, because the amount is drawn quite
             * differently from the words around it — Qaroxe, filling up like a
             * glass — and it can sit anywhere in the sentence. Whatever is typed
             * before it and after it is the author's, in either language. */
            $fund = get_option('fc_sponsors_funding', []);
            if (!is_array($fund)) $fund = [];
            ?>
            <h2 style="margin-top:0.5rem;">Section heading</h2>
            <p class="description">
                Reads as one sentence: <strong>text before → amount → text after</strong>.
                The amount is set in the pixel face and fills up like a glass — how full is the
                amount against the goal, so €1,500 of €10,000 is a number an eighth full. The €
                sign is added automatically and is not part of the pixel face.
                Leave every field empty to hide the heading.
            </p>
            <?php
            fc_bilingual_field('part1', $fund, [
                'label'          => 'Text BEFORE the amount',
                'name_prefix'    => 'fc_sponsor_fund',
                'placeholder_en' => 'So far you have raised',
                'placeholder_el' => 'Μέχρι στιγμής μαζέψατε',
            ]);
            ?>
            <div class="fc-grid-2">
                <div class="fc-field">
                    <label for="fc_fund_amount">Amount raised (€)</label>
                    <input type="number" min="0" step="1" id="fc_fund_amount" name="fc_sponsor_fund[amount]" value="<?php echo esc_attr((string) ($fund['amount'] ?? '')); ?>">
                    <p class="description">The figure shown in the sentence.</p>
                </div>
                <div class="fc-field">
                    <label for="fc_fund_goal">Goal (€)</label>
                    <input type="number" min="0" step="1" id="fc_fund_goal" name="fc_sponsor_fund[goal]" value="<?php echo esc_attr((string) ($fund['goal'] ?? '')); ?>">
                    <p class="description">Not shown anywhere — it only decides how full the number is drawn. No goal, no fill.</p>
                </div>
            </div>
            <?php
            fc_bilingual_field('part2', $fund, [
                'label'          => 'Text AFTER the amount',
                'name_prefix'    => 'fc_sponsor_fund',
                'placeholder_en' => 'of ‘free’.',
                'placeholder_el' => 'από το «δωρεάν».',
            ]);
            echo '<hr style="margin:2rem 0;">';
            $cta = get_option('fc_sponsors_cta', []);
            if (!is_array($cta)) $cta = [];
            $pdf = (string) ($cta['pdf'] ?? '');
            $url = (string) ($cta['url'] ?? '');
            ?>
            <h2 style="margin-top:0;">"Become a sponsor" CTA</h2>
            <p class="description">Renders directly under the sponsor tiers on the front-end, in the same style as the hero CTAs. Upload a PDF prospectus or paste a URL; the PDF takes priority when both are set.</p>
            <?php
            fc_bilingual_field('label', $cta, [
                'label'       => 'CTA label (the arrow > is added automatically)',
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

            <?php
            /* The donation CTA — the second button on that row. It only appears
               on the front end when it has BOTH a label and a URL, so a blank
               one is simply absent rather than a dead link. */
            $donate_url = (string) ($cta['donate_url'] ?? '');
            ?>
            <h2 style="margin-top:2rem;">Donation CTA</h2>
            <p class="description">A second button beside “Become a sponsor”, for people who want to chip in
               rather than sponsor. The two are spread evenly across the section — equal space at the edges
               and between them. Leave the label or the link empty to hide it.</p>
            <?php
            fc_bilingual_field('donate_label', $cta, [
                'label'       => 'Donation label (the arrow > is added automatically)',
                'name_prefix' => 'fc_sponsor_cta',
                'placeholder_en' => 'Buy us a coffee',
                'placeholder_el' => 'Κέρασέ μας καφέ',
            ]);
            fc_bilingual_field('donate_hover_label', $cta, [
                'label'       => 'Donation hover label (optional — scrambles in on hover, desktop only)',
                'name_prefix' => 'fc_sponsor_cta',
            ]);
            fc_bilingual_field('donate_desc', $cta, [
                'label'       => 'Donation description (small line under the button)',
                'type'        => 'textarea',
                'rows'        => 2,
                'name_prefix' => 'fc_sponsor_cta',
                'placeholder_en' => 'Any amount. Goes straight to travel grants for speakers.',
            ]);
            ?>
            <div class="fc-field">
                <label>Donation link</label>
                <input type="text" name="fc_sponsor_cta[donate_url]" value="<?php echo esc_attr($donate_url); ?>" placeholder="https://… (opens in a new tab)">
            </div>
            <hr style="margin:2rem 0;">
            <h2>Tiers</h2>
            <?php
        },
        'post_process' => function ($clean, $raw) {
            // The heading's four pieces live in their own option, so the tier
            // rows and the sentence cannot overwrite one another on save.
            $fund_raw = isset($raw['fc_sponsor_fund']) && is_array($raw['fc_sponsor_fund']) ? $raw['fc_sponsor_fund'] : [];
            update_option('fc_sponsors_funding', [
                'part1_el' => sanitize_text_field((string) ($fund_raw['part1_el'] ?? '')),
                'part1_en' => sanitize_text_field((string) ($fund_raw['part1_en'] ?? '')),
                'part2_el' => sanitize_text_field((string) ($fund_raw['part2_el'] ?? '')),
                'part2_en' => sanitize_text_field((string) ($fund_raw['part2_en'] ?? '')),
                'amount'   => max(0, (int) ($fund_raw['amount'] ?? 0)),
                'goal'     => max(0, (int) ($fund_raw['goal'] ?? 0)),
            ], false);
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
                // The donation button beside it.
                'donate_label_el'       => sanitize_text_field((string) ($cta_raw['donate_label_el'] ?? '')),
                'donate_label_en'       => sanitize_text_field((string) ($cta_raw['donate_label_en'] ?? '')),
                'donate_hover_label_el' => sanitize_text_field((string) ($cta_raw['donate_hover_label_el'] ?? '')),
                'donate_hover_label_en' => sanitize_text_field((string) ($cta_raw['donate_hover_label_en'] ?? '')),
                'donate_desc_el'        => sanitize_textarea_field((string) ($cta_raw['donate_desc_el'] ?? '')),
                'donate_desc_en'        => sanitize_textarea_field((string) ($cta_raw['donate_desc_en'] ?? '')),
                'donate_url'            => esc_url_raw((string) ($cta_raw['donate_url'] ?? '')),
            ];
            update_option('fc_sponsors_cta', $cta_clean, false);

            // A tier saved with no colour would render its marked run in
            // nothing at all. The field can legitimately be left blank — that is
            // what a new row starts as — so it lands on the house blue rather
            // than on an invisible heading.
            foreach ($clean as $i => $tier) {
                if (trim((string) ($tier['colour'] ?? '')) === '') {
                    $clean[$i]['colour'] = FC_OUTLINE_REST;
                }
            }
            return $clean;
        },
    ]);
}
