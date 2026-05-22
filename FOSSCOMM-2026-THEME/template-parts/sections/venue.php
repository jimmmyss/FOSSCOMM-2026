<?php
/**
 * Venue — globe + editions section.
 * Left: intro text. Right: globe. Editions: a vertical list in the sections
 * sidebar on desktop (rendered by template-parts/partials/section-nav.php), a
 * sticky horizontal nav bar at the top of the section on mobile (below).
 * Below the globe: travel cards (how to get here).
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$data    = fc_section_data($section);

$title       = fc_bi($data, 'title');
$city        = (string) ($data['city']  ?? 'ATHENS');
$lat         = (string) ($data['lat']   ?? '37.98°N');
$lon         = (string) ($data['lon']   ?? '23.73°E');
$uni_title   = fc_bi($data, 'university_title');
$coords_lbl  = (string) ($data['coords_label']    ?? '');
$maps_url    = (string) ($data['google_maps_url'] ?? '');
$address     = fc_bi($data, 'address');
$info_rows   = (array)  ($data['info_rows']       ?? []);
$travel_cards = (array) ($data['travel_cards'] ?? []);
$cluster_label = (string) ($data['cluster_label'] ?? 'FOSSCOMM');

// Editions data — serves as BOTH the year browser AND the globe pins.
// Normalised centrally so the desktop sidebar list (section-nav.php) and the
// mobile bar below stay in sync with the globe. See fc_venue_editions().
$editions_json_arr = fc_venue_editions();
$editions_json     = wp_json_encode($editions_json_arr);

/* Open the section manually (not using fc_section_open) so we can place the year browser
   INSIDE the <section> but OUTSIDE the max-w container. */
$id          = (string) $section['key'];
$eyebrow_en  = (string) ($section['eyebrow_en'] ?? '');
if ($eyebrow_en === '') $eyebrow_en = (string) ($section['eyebrow_el'] ?? '');
?>
<section id="<?php echo esc_attr($id); ?>" class="bg-paper relative border-t border-border">
    <?php if (!empty($editions_json_arr)) : ?>
        <!-- Editions bar (MOBILE ONLY — lg:hidden). A sticky horizontal nav shown
             while the venue section is on screen. On mobile the FOSSCOMM bar
             scrolls away after home, so the only persistent top chrome is the
             section nav (sticky top-0, 40px) — this sticks just below it at
             top-10. The desktop equivalent is the vertical sticky panel right
             below this — same position:sticky mechanism, bounded by the venue
             <section>. "Editions:" stays pinned far left; scrolling auto-selects
             the leftmost. -->
        <nav
            aria-label="<?php echo esc_attr(fc_t('past_editions_label')); ?>"
            data-fc-editions-mobile
            class="
                lg:hidden
                sticky top-10 z-30 h-[41px]
                bg-paper border-b border-border
                font-mono text-[11px] uppercase tracking-widest text-ink-muted
                overflow-x-auto whitespace-nowrap fc-nav-no-scrollbar flex items-center
            "
        >
            <span class="fc-editions-label shrink-0 sticky left-0 z-20 flex items-center gap-4 h-full bg-paper pl-4 pr-4 text-ink">
                <span><?php echo esc_html(fc_t('past_editions_label')); ?></span>
                <span class="opacity-50">//</span>
            </span>
            <?php foreach (array_reverse($editions_json_arr) as $ed) :
                if (!empty($ed['current'])) continue;   // matches the desktop panel: past editions only
                $yr = (int) $ed['year'];
                $ct = (string) $ed['city'];
                ?>
                <button type="button"
                        class="fc-year-btn fc-edition-mobile-btn shrink-0 h-full flex items-center pr-4 whitespace-nowrap transition-colors text-ink-muted"
                        data-fc-edition-year="<?php echo esc_attr($yr); ?>"
                        data-fc-edition-lat="<?php echo esc_attr($ed['lat']); ?>"
                        data-fc-edition-lon="<?php echo esc_attr($ed['lon']); ?>"
                        data-fc-edition-city="<?php echo esc_attr($ct); ?>"
                        data-fc-edition-url="<?php echo esc_attr($ed['url']); ?>"
                >
                    <span class="fc-edition-text"><?php echo esc_html($yr); ?> / <?php echo esc_html(ucfirst($ct)); ?></span>
                    <span class="fc-edition-arrow-sel">(click me again)</span>
                </button>
            <?php endforeach; ?>
        </nav>

        <!-- Editions panel (DESKTOP ONLY — hidden lg:block). Same position:sticky
             mechanism as the mobile bar above: an absolute, section-tall rail
             (so it never displaces the venue content) pulled into the sidebar
             column at z-50, ON TOP OF the fixed section-nav. Inside, the list is
             position:sticky top-10 — bounded by the venue <section>, so it
             enters aligned to the section's top, locks, then releases at the
             section's bottom: the exact lifecycle the mobile bar has. -->
        <div class="hidden lg:block lg:absolute lg:-top-px lg:-bottom-px lg:-left-[200px] lg:w-[200px] z-30 pointer-events-none">
            <nav
                aria-label="<?php echo esc_attr(fc_t('past_editions_label')); ?>"
                data-fc-editions-desktop
                class="
                    sticky pointer-events-auto
                    bg-paper border-t border-r border-b border-border
                    px-5 py-6
                    font-mono text-[11px] uppercase tracking-widest text-ink-muted
                "
            >
                <div class="flex items-center gap-2 mb-6 text-ink">
                    <span><?php echo esc_html(fc_t('past_editions_label')); ?></span>
                    <span class="opacity-50">//</span>
                </div>
                <ul class="flex flex-col gap-y-2">
                    <?php foreach (array_reverse($editions_json_arr) as $ed) :
                        if (!empty($ed['current'])) continue;
                        $yr = (int) $ed['year'];
                        $ct = (string) $ed['city'];
                        ?>
                        <li>
                            <button type="button"
                                    class="fc-year-btn block w-full text-left p-0 transition-colors text-ink-muted"
                                    data-fc-edition-year="<?php echo esc_attr($yr); ?>"
                                    data-fc-edition-lat="<?php echo esc_attr($ed['lat']); ?>"
                                    data-fc-edition-lon="<?php echo esc_attr($ed['lon']); ?>"
                                    data-fc-edition-city="<?php echo esc_attr($ct); ?>"
                                    data-fc-edition-url="<?php echo esc_attr($ed['url']); ?>"
                            >
                                <span class="fc-edition-text"><?php echo esc_html($yr); ?> / <?php echo esc_html(ucfirst($ct)); ?></span>
                                <span class="fc-edition-arrow-sel">(click me again)</span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
    <div class="max-w-[1440px] mx-auto px-4 md:px-8 py-24 md:py-40">
        <?php if ($eyebrow_en !== '') : ?>
            <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6" lang="en">
                <?php echo esc_html($eyebrow_en); ?>
            </div>
        <?php endif; ?>
        <?php if ($title['en'] !== '') : ?>
            <h2 class="font-display text-4xl md:text-6xl leading-[1.0] tracking-tight mb-3" lang="en"><?php echo fc_format($title['en']); ?></h2>
        <?php endif; ?>
        <?php if ($title['el'] !== '') : ?>
            <p class="font-display text-2xl md:text-3xl leading-tight tracking-tight text-ink-muted mb-16"><?php echo fc_format($title['el']); ?></p>
        <?php elseif ($title['en'] !== '') : ?>
            <div class="mb-16"></div>
        <?php endif; ?>

        <!-- Main content: left text | right globe. On md the right column stretches to
             the row height so the globe (justify-end) stays pinned to the bottom line
             instead of floating in the middle when it shrinks. -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-start md:items-stretch">

            <!-- Left: venue card — big hover-scramble title + Google Maps link + address + info rows -->
            <div class="space-y-6 pb-6 md:pb-20">
                <?php if ($uni_title['en'] !== '' || $uni_title['el'] !== '') :
                    $has_maps   = $maps_url !== '';
                    $has_coords = $coords_lbl !== '';
                    $link_tag   = $has_maps ? 'a' : 'div';
                    $link_attrs = 'class="fc-venue-title-link block no-underline text-inherit"';
                    if ($has_maps) {
                        $link_attrs .= ' href="' . esc_url($maps_url) . '" target="_blank" rel="noreferrer"';
                    }
                    ?>
                    <<?php echo $link_tag; ?> <?php echo $link_attrs; ?>>
                        <?php if ($uni_title['en'] !== '') : ?>
                            <h3 class="fc-venue-title-en font-display text-3xl md:text-5xl leading-[1.05] tracking-tight text-ink m-0"
                                lang="en"
                                data-fc-default="<?php echo esc_attr($uni_title['en']); ?>"
                                data-fc-hover="<?php echo esc_attr($has_coords ? $coords_lbl : $uni_title['en']); ?>"><?php echo esc_html($uni_title['en']); ?></h3>
                        <?php endif; ?>
                        <?php if ($uni_title['el'] !== '' && $uni_title['el'] !== $uni_title['en']) : ?>
                            <p class="fc-venue-title-el font-display text-xl md:text-2xl text-ink-muted leading-tight m-0 mt-1"
                               data-fc-default="<?php echo esc_attr($uni_title['el']); ?>"><?php echo esc_html($uni_title['el']); ?></p>
                        <?php endif; ?>
                    </<?php echo $link_tag; ?>>
                <?php endif; ?>

                <?php if ($address['en'] !== '' || $address['el'] !== '') : ?>
                    <div class="font-mono text-sm leading-relaxed border-l-2 border-accent pl-4">
                        <div class="flex flex-wrap gap-x-3 gap-y-2">
                            <?php if ($address['en'] !== '') : ?>
                                <p class="m-0 text-ink-muted whitespace-pre-line" lang="en"><?php echo fc_format($address['en']); ?></p>
                            <?php endif; ?>
                            <?php if ($address['el'] !== '' && $address['el'] !== $address['en']) : ?>
                                <p class="m-0 text-ink-muted whitespace-pre-line opacity-70"><?php echo fc_format($address['el']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($info_rows)) : ?>
                    <dl class="border-t border-border m-0 p-0">
                        <?php foreach ($info_rows as $row) :
                            $rlabel = fc_bi($row, 'label');
                            $rvalue = fc_bi($row, 'value');
                            if ($rlabel['en'] === '' && $rlabel['el'] === '' && $rvalue['en'] === '' && $rvalue['el'] === '') continue;
                            ?>
                            <div class="grid grid-cols-[1fr_2fr] gap-4 items-baseline py-3 border-b border-border">
                                <dt class="font-mono text-[11px] uppercase tracking-widest text-ink-muted m-0">
                                    <?php echo fc_bi_inline($rlabel['el'], $rlabel['en']); ?>
                                </dt>
                                <dd class="font-mono text-sm text-ink m-0 text-right tabular-nums">
                                    <?php echo fc_bi_inline($rvalue['el'], $rvalue['en'], ' / '); ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>

            <!-- Right: globe. Editions live in the globe's ED panel (desktop) and the
                 mobile sticky bar above (mobile) — no side panel here anymore. -->
            <div class="relative flex flex-col justify-end h-full">
                <div class="w-full" data-fc-island="ascii-globe" data-fc-cluster-label="<?php echo esc_attr($cluster_label); ?>" data-fc-editions="<?php echo esc_attr($editions_json); ?>">
                    <noscript>
                        <div class="ascii text-xs text-ink-faint border border-border p-6 text-center">[ JavaScript-rendered globe — <?php echo esc_html($lat . ' ' . $lon); ?> ]</div>
                    </noscript>
                </div>
            </div>
        </div>

        <?php if (!empty($travel_cards)) : ?>
            <div class="grid grid-cols-12 gap-8 border-t border-border pt-12 mt-0">
                <div class="col-span-12 md:col-span-3 font-mono text-[11px] uppercase tracking-widest text-ink-muted">
                    <?php echo esc_html(fc_t('getting_here')); ?>
                </div>
                <div class="col-span-12 md:col-span-9 grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8 text-base leading-relaxed">
                    <?php foreach ($travel_cards as $card) :
                        $ct = fc_bi($card, 'title');
                        $cb = fc_bi($card, 'body');
                        if ($ct['el'] === '' && $ct['en'] === '') continue;
                        ?>
                        <div>
                            <div class="font-display text-2xl mb-2" lang="en"><?php echo fc_format($ct['en']); ?></div>
                            <?php if ($ct['en'] !== '' && $ct['el'] !== '') : ?>
                                <div class="font-display text-lg text-ink-muted mb-2"><?php echo fc_format($ct['el']); ?></div>
                            <?php endif; ?>
                            <?php if ($cb['en'] !== '') : ?><p class="text-ink-muted" lang="en"><?php echo fc_format($cb['en']); ?></p><?php endif; ?>
                            <?php if ($cb['el'] !== '') : ?><p class="text-ink-muted opacity-80 mt-1"><?php echo fc_format($cb['el']); ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div><!-- end .max-w container -->
</section>

<style>
/* Whole editions browser is always uppercase (years, cities, arrows). The
   descendant rule is required because Tailwind Preflight resets
   button{text-transform:none}, which would otherwise win over an inherited
   .uppercase. Applies to both the mobile bar and the desktop sticky panel. */
[data-fc-editions-mobile], [data-fc-editions-mobile] *,
[data-fc-editions-desktop], [data-fc-editions-desktop] * { text-transform: uppercase; }

/* Desktop panel locks where the section-nav text ends, with the same gap below
   it as there is between the top bar and the first section link. That Y is
   measured by assets/section-nav.js (separate fixed element — CSS can't read
   it) into --fc-sections-end; 2.5rem (top-10, the sidebar's own top) is the
   pre-JS fallback. The sticky lifecycle itself stays pure CSS.
   The +1px offset keeps the panel's own border-t one pixel below the section
   nav's bottom edge so the line is visible (rather than hidden flush against
   the section nav's bg-paper). */
[data-fc-editions-desktop] { top: calc(var(--fc-sections-end, 2.5rem) + 1px); }

/* Selected NON-current bar item: its whole label is replaced by "(click me again)"
   — a hint to click once more to open the archive. The current edition (2026) is
   excluded: it keeps its "year / city ← you are here" and never says that. */
.fc-year-btn .fc-edition-arrow-sel { display: none; }
.fc-year-btn[data-fc-edition-selected]:not([data-fc-edition-current]) .fc-edition-text,
.fc-year-btn[data-fc-edition-selected]:not([data-fc-edition-current]) .fc-edition-arrow { display: none; }
.fc-year-btn[data-fc-edition-selected]:not([data-fc-edition-current]) .fc-edition-arrow-sel { display: inline; }

/* A real :hover (mouse devices only — touch keeps no sticky hover) OR a hovered globe
   pin (.is-hovered, set from JS) turns the matching item black. */
@media (hover: hover) {
    .fc-year-btn:not([data-fc-edition-selected]):not([data-fc-edition-current]):hover {
        color: var(--accent) !important;
    }
}
.fc-year-btn.is-hovered:not([data-fc-edition-selected]):not([data-fc-edition-current]) {
    color: var(--accent) !important;
}
/* Selected (mobile or desktop): stays accent. */
.fc-year-btn[data-fc-edition-selected] {
    color: var(--accent) !important;
}

/* Venue card title: hover scrambles the English title into the coordinates
   label (window.fcScramble, same engine the FAQ uses) and instantly hides the
   Greek sub-line so the coordinates stand alone. Click opens Google Maps.
   Hover behaviour is gated behind the lg breakpoint (matches fc.js's
   `(max-width: 1023.98px)` mobile check) so touch viewports — including
   hybrid laptops and DevTools mobile mode that still report `(hover: hover)`
   — never trigger the scramble or colour change. Tap stays click-only. */
.fc-venue-title-link { cursor: pointer; }
@media (min-width: 1024px) {
    a.fc-venue-title-link { transition: color 200ms ease; }
    a.fc-venue-title-link:hover .fc-venue-title-en { color: var(--color-accent, #0033FF); }
    .fc-venue-title-link.is-hovering .fc-venue-title-el { visibility: hidden; }
}
</style>
<?php /* Hover-scramble swap.
   On hover: EN scrambles into the coordinates label; EL is hidden instantly
   via the .is-hovering class (visibility:hidden in <style> above).
   On un-hover: EN scrambles back to its default; EL also gets the same hack
   effect — we pre-set its textContent to random glyphs BEFORE removing
   the hidden class (so the original text doesn't flash for one frame),
   then call window.fcScramble to animate the glyphs into the original
   Greek title. Pointer-only — touch devices never get a sticky hover. */ ?>
<script>
(function () {
    // Live check — viewport-width-based, matching fc.js's lg breakpoint. Using
    // (hover: none) here was unreliable: hybrid touch laptops and DevTools'
    // mobile mode often still report (hover: hover) and the scramble would
    // fire on tap. Re-evaluated at every event so a window resize takes effect
    // without reload.
    var mqMobile = window.matchMedia && window.matchMedia('(max-width: 1023.98px)');
    function isMobile() { return !!(mqMobile && mqMobile.matches); }
    var GLYPHS = 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩ░▒▓0123456789@#';
    function glyphify(text) {
        var out = '';
        for (var i = 0; i < text.length; i++) {
            var c = text.charAt(i);
            out += (c === ' ' || c === '\n')
                ? c
                : GLYPHS.charAt(Math.floor(Math.random() * GLYPHS.length));
        }
        return out;
    }
    var links = document.querySelectorAll('.fc-venue-title-link');
    links.forEach(function (link) {
        var en = link.querySelector('.fc-venue-title-en');
        var el = link.querySelector('.fc-venue-title-el');
        if (!en) return;
        var state = false;
        function swap(toHover) {
            // Mobile: never engage the scramble. Tap behaviour is link-only.
            if (isMobile()) return;
            if (toHover === state) return;
            state = toHover;
            if (toHover) {
                // Hover IN: EN scrambles into coordinates, EL hides via CSS.
                link.classList.add('is-hovering');
                var hoverText = en.getAttribute('data-fc-hover');
                if (hoverText !== null && typeof window.fcScramble === 'function') {
                    window.fcScramble(en, hoverText);
                }
            } else {
                // Hover OUT: EN scrambles back, EL also scrambles back in.
                var enDefault = en.getAttribute('data-fc-default');
                if (enDefault !== null && typeof window.fcScramble === 'function') {
                    window.fcScramble(en, enDefault);
                }
                if (el) {
                    var elDefault = el.getAttribute('data-fc-default') || el.textContent;
                    // Pre-fill with glyphs BEFORE un-hiding so the original
                    // Greek doesn't flash for one frame before fcScramble runs.
                    el.textContent = glyphify(elDefault);
                }
                link.classList.remove('is-hovering');
                if (el && typeof window.fcScramble === 'function') {
                    var target = el.getAttribute('data-fc-default');
                    if (target !== null) window.fcScramble(el, target);
                }
            }
        }
        link.addEventListener('mouseenter', function () { swap(true); });
        link.addEventListener('mouseleave', function () { swap(false); });
        link.addEventListener('focus',      function () { swap(true); }, true);
        link.addEventListener('blur',       function () { swap(false); }, true);
    });
})();
</script>
