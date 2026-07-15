<?php
/**
 * Get Involved — renders the CFP block (if filled) above the volunteer cards.
 * The CFP block was previously its own section; folded in per project layout decision.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$data    = fc_section_data($section);

$title       = fc_bi($data, 'title');
$intro       = fc_bi($data, 'intro');
$cards       = (array) ($data['cards'] ?? []);

$cfp_title    = fc_bi($data, 'cfp_title');
$cfp_body     = fc_bi($data, 'cfp_body');
$cfp_deadline = trim((string) ($data['cfp_deadline'] ?? ''));
$fund_goal    = (int) ($data['fund_goal'] ?? 0);
$fund_raised  = (int) ($data['fund_raised'] ?? 0);

$S = fc_strings();

$has_cfp_text  = $cfp_title['el'] !== '' || $cfp_title['en'] !== '' || $cfp_body['el'] !== '' || $cfp_body['en'] !== '';
$has_countdown = $cfp_deadline !== '';
$has_funding   = $fund_goal > 0;
$has_aside     = $has_countdown || $has_funding;
$has_cfp       = $has_cfp_text || $has_aside;

$fund_pct  = $fund_goal > 0 ? ($fund_raised / $fund_goal) * 100 : 0;
$fund_over = $fund_raised > $fund_goal;
$fund_fill = $fund_over ? 100 : max(0, min(100, $fund_pct));

fc_section_open($section, [
    'title_el' => $title['el'],
    'title_en' => $title['en'],
]);

$intro_text = fc_one($intro);
if ($intro_text !== '') : ?>
    <div class="md:w-1/2 text-lg leading-relaxed space-y-3">
        <?php echo wp_kses_post(fc_format_block($intro_text)); ?>
    </div>
    <div class="mb-16"></div>
<?php endif;

if ($has_cfp) : ?>
    <div class="grid grid-cols-1 <?php echo $has_aside ? 'md:grid-cols-2' : ''; ?> gap-8 md:gap-12 mb-20 pb-16 border-b border-border">
        <!-- Left half: the secondary title (full width of the half) + its body,
             where the body is 2/3 of this half. -->
        <div>
            <?php $cfp_title_text = fc_one($cfp_title); if ($cfp_title_text !== '') : ?>
                <div class="mb-6">
                    <h3 class="font-display text-3xl md:text-4xl leading-tight"><?php echo fc_format($cfp_title_text); ?></h3>
                </div>
            <?php endif; ?>

            <?php $cfp_body_text = fc_one($cfp_body); if ($cfp_body_text !== '') : ?>
                <div class="md:w-2/3 text-lg leading-relaxed space-y-3">
                    <?php echo wp_kses_post(fc_format_block($cfp_body_text)); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($has_aside) : ?>
            <div class="md:w-2/3 md:mx-auto">
                <?php if ($has_countdown) : ?>
                    <div class="border border-border bg-paper p-6 mb-4 font-mono">
                        <div class="text-[11px] uppercase tracking-widest text-ink-muted mb-3">
                            <?php echo fc_bi_inline($S['el']['cfp_closes_in'], $S['en']['cfp_closes_in']); ?>
                        </div>
                        <div class="font-display text-3xl md:text-4xl text-ink tabular-nums"
                             data-fc-cfp-countdown
                             data-deadline="<?php echo esc_attr($cfp_deadline); ?>"
                             data-closed="<?php echo esc_attr(fc_t('cfp_closed')); ?>">…</div>
                    </div>
                <?php endif; ?>

                <?php if ($has_funding) : ?>
                    <div class="fc-fund border border-border bg-paper p-6 font-mono relative<?php echo $fund_over ? ' is-broken' : ''; ?>">
                        <div class="flex items-baseline justify-between gap-4 text-[11px] uppercase tracking-widest text-ink-muted mb-3">
                            <span><?php echo fc_bi_inline($S['el']['funding_goal'], $S['en']['funding_goal']); ?></span>
                            <span class="text-ink whitespace-nowrap">€<?php echo esc_html(number_format($fund_raised)); ?> / €<?php echo esc_html(number_format($fund_goal)); ?></span>
                        </div>
                        <div class="fc-progress<?php echo $fund_over ? ' is-over' : ''; ?>">
                            <div class="fc-progress-fill" style="width: <?php echo esc_attr((string) round($fund_fill, 2)); ?>%;"></div>
                            <?php if ($fund_over) : ?>
                                <div class="fc-progress-over" aria-hidden="true"></div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3 flex items-baseline justify-between gap-4 text-[11px] uppercase tracking-widest <?php echo $fund_over ? 'text-accent' : 'text-ink-muted'; ?>">
                            <span><?php echo (int) round($fund_pct); ?>%</span>
                            <?php if ($fund_over) : ?>
                                <span><?php echo fc_bi_inline($S['el']['funding_reached'], $S['en']['funding_reached']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($fund_over) : ?>
                            <!-- Two angled stubs at the funding card's right corners.
                                 Each line is 40% of the card's height, leaving a 20%
                                 gap in the middle for the bar's red stub to poke
                                 through. preserveAspectRatio="none" makes the stubs
                                 scale with the card's actual height. -->
                            <svg class="fc-fund-break" viewBox="0 0 20 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                <path d="M2 0 L14 40 M2 100 L14 60" />
                            </svg>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php foreach (array_values($cards) as $i => $card) :
            $card_title = fc_bi($card, 'title');
            if ($card_title['en'] === '' && $card_title['el'] === '') continue;
            $card_hover = fc_bi($card, 'hover_title');
            $card_body  = fc_bi($card, 'body');
            $card_url   = (string) ($card['url'] ?? '');
            ?>
            <div>
                <div class="flex items-baseline gap-3">
                    <span class="font-mono text-[11px] uppercase tracking-widest text-ink-muted shrink-0"><?php echo esc_html(sprintf('%02d/', $i + 1)); ?></span>
                    <?php fc_cta_link([
                        'url'      => $card_url !== '' ? $card_url : '#',
                        'en'       => $card_title['en'],
                        'el'       => $card_title['el'],
                        'hover_en' => $card_hover['en'],
                        'hover_el' => $card_hover['el'],
                    ]); ?>
                </div>
                <?php $card_body_text = fc_one($card_body); if ($card_body_text !== '') : ?>
                    <div class="mt-3 pl-8 text-base text-ink-muted leading-relaxed space-y-3 max-w-sm">
                        <p class="mt-1"><?php echo fc_format($card_body_text); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php
fc_section_close();
