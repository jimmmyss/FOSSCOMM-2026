<?php
/**
 * Sponsors — tiered, centered rows (Diamond / Gold / Silver / Community / In-kind).
 * Row fields in option `fc_sponsors`: name, tier, logo, logo_alt, url.
 *
 * Each tier has a per-row cap. Items are split equally across ceil(n / cap)
 * rows, larger rows on top. Each row is one centered, non-wrapping flex line
 * (cells flex to fit). Horizontal dividers appear only BETWEEN rows, so a
 * single-row tier shows no rule that would imply a split. The default logo
 * swaps to its hover image on mouseover — see .fc-sponsor-* in site.css.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('fc_sponsor_split')) {
    /**
     * Split $items into rows of at most $max each, as EQUALLY as possible.
     * Rows = ceil(n / max); the remainder lands on the top rows.
     *   gold(3):  4→[2,2]  5→[3,2]  7→[3,2,2]
     *   community(6): 7→[4,3] 11→[6,5] 13→[5,4,4] 16→[6,5,5]
     *
     * @return array<int, array> list of rows
     */
    function fc_sponsor_split(array $items, int $max): array {
        $n = count($items);
        if ($n === 0) {
            return [];
        }
        $rows = (int) ceil($n / max(1, $max));
        $base = intdiv($n, $rows);
        $rem  = $n % $rows; // first $rem rows get one extra
        $groups = [];
        $offset = 0;
        for ($r = 0; $r < $rows; $r++) {
            $take = $base + ($r < $rem ? 1 : 0);
            $groups[] = array_slice($items, $offset, $take);
            $offset  += $take;
        }
        return $groups;
    }
}

$section  = $args['section'] ?? [];
$sponsors = fc_section_data($section);

$by_tier = ['diamond' => [], 'gold' => [], 'silver' => [], 'bronze' => [], 'community' => [], 'in-kind' => []];
foreach ($sponsors as $sp) {
    if (!is_array($sp)) continue;
    $tier = strtolower((string) ($sp['tier'] ?? 'community'));
    if ($tier === 'platinum') $tier = 'diamond';   // legacy tier rename
    if (!isset($by_tier[$tier])) $tier = 'community';
    $by_tier[$tier][] = $sp;
}

// Tier labels are admin-editable + bilingual now (fc_sponsor_tier_label()).
// Max logos per centered row, per tier.
$tier_max = [
    'diamond'   => 2,
    'gold'      => 3,
    'silver'    => 4,
    'bronze'    => 4,
    'community' => 4,
    'in-kind'   => 4,
];

// Optional per-tier shine colours. Each tier can opt-in independently —
// when its colour is non-empty, every sponsor logo in that tier gets a
// left-to-right shine sweep (masked to the logo's non-transparent pixels)
// every 3 seconds. Empty colour = no shine for that tier.
$shine_tiers = get_option('fc_sponsors_shine', []);
if (!is_array($shine_tiers)) $shine_tiers = [];
// Backwards-compat: honour the old single-colour option if the new map
// isn't populated yet (covers any in-flight install between the migration).
$legacy_shine = trim((string) get_option('fc_sponsors_shine_color', ''));
// Legacy: a Platinum shine colour carries into the renamed Diamond tier.
if (!isset($shine_tiers['diamond']) && isset($shine_tiers['platinum'])) {
    $shine_tiers['diamond'] = $shine_tiers['platinum'];
}
foreach (['diamond', 'gold', 'silver', 'bronze', 'community', 'in-kind'] as $_t) {
    $shine_tiers[$_t] = trim((string) ($shine_tiers[$_t] ?? $legacy_shine));
}
$has_any_shine = (bool) array_filter($shine_tiers, function ($c) { return $c !== ''; });

$meta = fc_section_meta('sponsors', [
    'title_el' => 'Όσοι κάνουν δυνατό το «δωρεάν».',
    'title_en' => 'The people who make ‘free’ possible.',
]);
fc_section_open($section, array_merge($meta, ['class' => 'fc-section-dots']));
?>
    <?php foreach ($by_tier as $tier => $items) :
        if (empty($items)) continue;
        $n           = count($items);
        $line_groups = fc_sponsor_split($items, $tier_max[$tier]);
        // Zero-padded to 2 digits, theme eyebrow style: "05 / Gold sponsors".
        // Plural "s" is English grammar only; Greek labels stay as entered.
        $tier_name   = fc_sponsor_tier_label($tier);
        $plural      = (fc_current_lang() === 'en' && $n !== 1) ? 's' : '';
        $label       = sprintf('%02d', $n) . ' / ' . $tier_name . $plural;
        ?>
        <div class="border-t border-border pt-8 pb-12">
            <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6">
                <?php echo esc_html($label); ?>
            </div>
            <div class="fc-sponsor-rows divide-y divide-border">
                <?php foreach ($line_groups as $group) : ?>
                    <div class="fc-sponsor-row flex justify-center divide-x divide-border">
                        <?php foreach ($group as $sp) :
                            $name     = (string) ($sp['name']     ?? '');
                            $url      = (string) ($sp['url']      ?? '');
                            $logo     = (string) ($sp['logo']     ?? '');
                            $logo_alt = (string) ($sp['logo_alt'] ?? '');
                            if ($name === '') continue;

                            $tier_shine = (string) ($shine_tiers[$tier] ?? '');
                            $classes = 'fc-sponsor-cell fc-tier-' . $tier;
                            if ($logo !== '' && $logo_alt !== '') {
                                $classes .= ' is-swap';
                            }
                            if ($tier_shine !== '') {
                                $classes .= ' fc-shine';
                            }
                            $tag  = $url !== '' ? 'a' : 'div';
                            $attr = 'class="' . esc_attr($classes) . '"';
                            if ($url !== '') {
                                $attr .= ' href="' . esc_url($url) . '" target="_blank" rel="noreferrer" title="' . esc_attr($name) . '"';
                            }
                            echo "<{$tag} {$attr}>";
                            ?>
                            <span class="fc-sponsor-box">
                                <?php if ($logo !== '') : ?>
                                    <img class="fc-sponsor-logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy" decoding="async">
                                <?php endif; ?>
                                <?php if ($logo_alt !== '') : ?>
                                    <img class="fc-sponsor-logo fc-sponsor-logo-alt" src="<?php echo esc_url($logo_alt); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async">
                                <?php endif; ?>
                                <?php if ($logo === '' && $logo_alt === '') : ?>
                                    <span class="fc-sponsor-name font-display"><?php echo esc_html($name); ?></span>
                                <?php endif; ?>
                                <?php if ($tier_shine !== '' && $logo !== '') :
                                    // The shine sweep is *masked* by the logo image itself —
                                    // mask-image uses the logo's alpha channel, so the gradient
                                    // is painted ONLY on opaque logo pixels (PNG transparency
                                    // is preserved). mask-size: contain mirrors the logo IMG's
                                    // object-fit: contain so the mask aligns with the visible
                                    // logo. No shine is rendered for plain-name cells (no logo
                                    // = nothing to mask). ?>
                                    <span class="fc-shine-mask" aria-hidden="true"
                                          style="-webkit-mask-image: url('<?php echo esc_url($logo); ?>'); mask-image: url('<?php echo esc_url($logo); ?>');">
                                        <span class="fc-shine-sweep"></span>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <?php
                            echo "</{$tag}>";
                        endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (array_sum(array_map('count', $by_tier)) === 0) : ?>
        <?php fc_render_tba('sponsors'); ?>
    <?php endif; ?>

    <?php
    // "Become a sponsor" CTA — admin-managed in FOSSCOMM → Sponsors. Mirrors the
    // hero CTA styling (display-link with optional EL slash + arrow). The link
    // target is the uploaded PDF prospectus when present; falls back to a custom URL.
    $cta       = get_option('fc_sponsors_cta', []);
    $cta_arr   = is_array($cta) ? $cta : [];
    $cta_label = fc_bi($cta_arr, 'label');
    $cta_hover = fc_bi($cta_arr, 'hover_label');
    $cta_pdf   = (string) ($cta_arr['pdf'] ?? '');
    $cta_url   = (string) ($cta_arr['url'] ?? '');
    $cta_desc  = fc_bi($cta_arr, 'desc');
    $cta_href  = $cta_pdf !== '' ? $cta_pdf : $cta_url;
    if (($cta_label['en'] !== '' || $cta_label['el'] !== '') && $cta_href !== '') :
        // TWO separate entities:
        //   1) A bare divider line, placed exactly like every other inter-tier
        //      divider (just border-t, no margin — the last tier's pb-12 is
        //      the gap above it).
        //   2) The Sponsor CTA itself, with mt equal to the section's bottom
        //      padding (py-24 / md:py-40 = 96 / 160 px) so it sits exactly
        //      mid-way between the divider line above and the section's
        //      bottom edge below.
        ?>
        <div class="border-t border-border" aria-hidden="true"></div>
        <div class="mt-24 md:mt-40 flex flex-wrap items-baseline gap-x-6 gap-y-2">
            <?php fc_cta_link([
                'url'          => $cta_href,
                'en'           => $cta_label['en'],
                'el'           => $cta_label['el'],
                'hover_en'     => $cta_hover['en'],
                'hover_el'     => $cta_hover['el'],
                'target_blank' => $cta_pdf !== '',
            ]); ?>
            <?php if ($cta_desc['en'] !== '' || $cta_desc['el'] !== '') : ?>
                <span class="font-mono text-xs text-ink-muted leading-relaxed">
                    <?php echo fc_bi_inline($cta_desc['el'], $cta_desc['en']); ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php
fc_section_close();

if ($has_any_shine) :
    // One CSS rule per active tier, scoping the gradient to that tier's
    // sponsor cells. Layout + animation live in site.css; this block only
    // owns the per-tier colour token. Empty tiers add no CSS at all.
    ?>
<style>
<?php foreach (['diamond', 'gold', 'silver', 'bronze', 'community', 'in-kind'] as $_t) :
    $sc = trim((string) ($shine_tiers[$_t] ?? ''));
    if ($sc === '') continue;
    $sc_attr = esc_attr($sc);
    ?>
.fc-sponsor-cell.fc-tier-<?php echo $_t; ?>.fc-shine .fc-shine-sweep {
    background: linear-gradient(
        100deg,
        transparent 0%,
        transparent 25%,
        <?php echo $sc_attr; ?> 50%,
        transparent 75%,
        transparent 100%
    );
}
<?php endforeach; ?>
</style>
<?php endif; ?>
