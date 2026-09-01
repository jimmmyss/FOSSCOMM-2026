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
$cfp_style = fc_cfp_style();

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
            <?php
            $cfp_title_text = fc_one($cfp_title);
            // Sized by the venue name's rule: the size at which the LONGEST line
            // exactly fills the column, floored at 18px and capped at 82px.
            // Measured on the PLAIN text — the asterisks are markup, and counting
            // them would set a marked heading smaller than an unmarked one saying
            // the same thing.
            $cfp_longest = 1;
            foreach (fc_lines(fc_hollow_plain($cfp_title_text)) as $line) {
                $len = function_exists('mb_strlen') ? mb_strlen($line, 'UTF-8') : strlen($line);
                if ($len > $cfp_longest) $cfp_longest = $len;
            }
            ?>
            <?php if ($cfp_title_text !== '') : ?>
                <!-- data-fc-centre / -item: on touch there is no pointer, so
                     assets/centre-highlight.js lights this while its centre is
                     inside the middle 30% of the screen, and puts it out again as
                     it scrolls away. The .is-hot it sets is handled in the <style>
                     below, outside the hover query, which is what lets a phone
                     have it at all. -->
                <div class="mb-6 <?php echo $has_aside ? 'fc-cfp-half' : 'fc-cfp-full'; ?>" data-fc-centre
                     style="--fc-cfp-rim: <?php echo esc_attr($cfp_style['rim']); ?>;
                            --fc-cfp-rim-hover: <?php echo esc_attr($cfp_style['rim_hover']); ?>;
                            --fc-cfp-longest: <?php echo (int) $cfp_longest; ?>;">
                    <h3 class="fc-cfp-title m-0" data-fc-centre-item>
                        <?php // One line per line typed, and the *starred* part
                              // drawn hollow. NO automatic last line — same
                              // convention as the speakers' name, the venue's
                              // name and the stat numbers: an automatic rule has
                              // to guess which part matters, and here it would be
                              // guessing about a sentence. ?>
                        <?php foreach (fc_hollow_split($cfp_title_text, '/\R/u') as $line) : ?>
                            <span><?php echo $line; ?></span>
                        <?php endforeach; ?>
                    </h3>
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
<style>
/* ── CFP heading: the starred run drawn hollow ────────────────────────────────
   The same treatment as a speaker's last name and the venue's last line, with
   one difference: which part is outlined comes from `*asterisks*` in the field
   rather than from position. A sentence has no last-anything worth outlining.

   Case is left alone deliberately. Speakers and the venue are uppercased because
   they are names; this is a sentence with full stops in it, and "SUBMIT A TALK.
   OR A WORKSHOP. OR BOTH." reads as shouting. Say the word if you want it
   uppercased to match them exactly. */
.fc-cfp-title {
    font-family: var(--font-display, "Space Grotesk"), ui-sans-serif, system-ui, sans-serif;
    /* 700, matching the other hollow headings. .font-display's own default is
       500, and a hollow run cut out of a 500 weight is a thin, wiry outline —
       the stroke needs some letter to sit inside. */
    font-weight: 700;
    /* THE VENUE NAME'S SIZE, by the venue name's rule: the size at which the
       longest line exactly fills the column, floored at 18px and capped at 82px.
       Same three numbers, same 1.45 character budget, same reasoning — see the
       long note on .fc-venue-name in template-parts/sections/venue.php.
       Was clamp(1.875rem, 3.4vw, 2.25rem), a fixed 30→36px that ignored how long
       the heading actually was. */
    font-size: clamp(
        18px,
        calc(var(--fc-cfp-col, 24rem) * 1.45 / var(--fc-cfp-longest, 14)),
        82px
    );
    line-height: 0.86;
    letter-spacing: -0.05em;
    color: var(--color-ink, #0A0A0A);
    /* One variable drives the outline. The rest colour starts it, .is-hot and
       :hover move it, and both the fill and the stroke read it — so the colour
       is declared once instead of copied into every hover rule and again inside
       the @supports. */
    --fc-cfp-rim-now: var(--fc-cfp-rim, #0033FF);
}
/* The column the heading has to fit, reconstructed from the layout above it —
   the same arithmetic .fc-venue-name uses, because it is the same grid:
   fc_section_open() wraps everything in max-w-[1440px] with px-4 (1rem a side)
   and px-8 (2rem a side) from md.
   TWO cases, because this block's grid is conditional: with a countdown or a
   funding bar beside it the row is md:grid-cols-2 with gap-12 (3rem), and
   without one it is a single full-width column. Sizing the full-width case as
   though it were a half would waste half the room. */
.fc-cfp-half, .fc-cfp-full { --fc-cfp-col: calc(100vw - 2rem); }
@media (min-width: 768px) {
    .fc-cfp-half { --fc-cfp-col: calc((min(1440px, 100vw) - 4rem - 3rem) / 2); }
    .fc-cfp-full { --fc-cfp-col: calc(min(1440px, 100vw) - 4rem); }
}

/* One line per typed line. DIRECT children, so an inner .is-outline span around
   part of a line does not become a block and break the line in half. */
.fc-cfp-title > span { display: block; width: fit-content; }
.fc-cfp-title .is-outline { color: var(--fc-cfp-rim-now, #0033FF); }
@supports (-webkit-text-stroke: 1px black) {
    .fc-cfp-title .is-outline {
        color: transparent;
        /* em, so the outline stays the same proportion of the letters as the
           heading scales with the viewport. */
        -webkit-text-stroke: 0.035em var(--fc-cfp-rim-now, #0033FF);
    }
}
/* OUTSIDE the hover query, because a phone has to have it: there is no pointer
   there, so centre-highlight.js sets .is-hot on the heading while it is near the
   middle of the screen. Putting this inside @media (hover: hover) is the mistake
   that made the speakers' mobile highlight do nothing. */
.fc-cfp-title.is-hot { --fc-cfp-rim-now: var(--fc-cfp-rim-hover, #EE8101); }
.fc-cfp-title .is-outline {
    transition: color 50ms ease, -webkit-text-stroke-color 50ms ease;
}
/* Not on touch. There is no pointer, so the highlight is driven by the centre
   band as you SCROLL — the colour changes because the page moved, not because
   anything was done to that element, and a fade tied to scroll position reads as
   lag rather than as polish. It also keeps this in step with the speakers row,
   whose ring is an SVG filter and genuinely cannot be animated at phone speed.
   (hover: none), the same gate the scripts use to decide there is no pointer. */
@media (hover: none) {
    .fc-cfp-title .is-outline { transition: none; }
}
@media (prefers-reduced-motion: reduce) {
    .fc-cfp-title .is-outline { transition: none; }
}
/* Pointer devices: hovering the heading moves it. On a phone :hover latches
   after a tap and would stay moved, which is why the positional highlight above
   exists instead. */
@media (hover: hover) {
    /* THE LINES, not the box — the venue name's rule exactly.
     *
     * The h3 is a block, so its box is the whole column, and the lines are
     * `width: fit-content`. `:hover` on the h3 would light the heading while the
     * pointer was in the empty air to the right of a short line. `:has()`
     * narrows it to a line. */
    @supports selector(:has(*)) {
        .fc-cfp-title:has(> span:hover) {
            --fc-cfp-rim-now: var(--fc-cfp-rim-hover, #EE8101);
        }
    }
    /* Without :has() there is no way to ask "is the pointer on the text", so the
       box is the best available answer. Over-triggering beats not working. */
    @supports not selector(:has(*)) {
        .fc-cfp-title:hover { --fc-cfp-rim-now: var(--fc-cfp-rim-hover, #EE8101); }
    }
}
</style>
<?php
fc_section_close();
