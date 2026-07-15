<?php
/**
 * Venue — MapLibre map + editions section.
 * Left: venue card. Right: the MapLibre globe map (assets/venue-map.js).
 * Editions: a vertical sticky panel on desktop + a sticky horizontal bar on
 * mobile (both below), which drive the map (hover-to-move, click-to-open).
 * Below the map: travel cards (how to get here).
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$data    = fc_section_data($section);

$title       = fc_bi($data, 'title');
$uni_title   = fc_bi($data, 'university_title');
// Hover text: the big venue title scrambles into this on hover. Was a lat/lon
// pair; now a single free-text field (legacy coords_lat kept as a fallback).
$hover_text  = (string) ($data['hover_text'] ?? '');
if ($hover_text === '') $hover_text = (string) ($data['coords_lat'] ?? '');
$maps_url    = (string) ($data['google_maps_url'] ?? '');
$address     = fc_bi($data, 'address');
$info_rows   = (array)  ($data['info_rows']       ?? []);
$travel_cards = (array) ($data['travel_cards'] ?? []);
$cluster_label = (string) ($data['cluster_label'] ?? 'FOSSCOMM');
$pin_sprite      = (string) ($data['pin_sprite'] ?? '');
$spotlight_sprite = (string) ($data['spotlight_sprite'] ?? '');
$pin_scale       = (float) ($data['pin_scale'] ?? 1.0);       if ($pin_scale <= 0)       $pin_scale = 1.0;
$spotlight_scale = (float) ($data['spotlight_scale'] ?? 1.0); if ($spotlight_scale <= 0) $spotlight_scale = 1.0;
$getting_here  = fc_bi($data, 'getting_here');

/* Render one editions-sidebar item (shared by the mobile bar + desktop panel).
   Rows with an archive URL render as a real <a target="_blank"> (hover moves
   the map, click opens the archive instantly); link-less rows render as a
   <button> that shows a sass message on click. assets/venue-map.js wires the
   hover-to-move + highlight + mobile scroll-select off the data-* attributes. */
$render_edition_item = function (array $ed, string $extra_class) {
    $yr  = (int) ($ed['year'] ?? 0);
    $ct  = (string) ($ed['city'] ?? '');
    $url = (string) ($ed['url'] ?? '');
    $tag = $url !== '' ? 'a' : 'button';
    $attrs = 'class="fc-year-btn no-underline transition-colors text-ink-muted ' . esc_attr($extra_class) . '"'
        . ' data-fc-edition-year="' . esc_attr($yr) . '"'
        . ' data-fc-edition-lat="' . esc_attr($ed['lat'] ?? '') . '"'
        . ' data-fc-edition-lon="' . esc_attr($ed['lon'] ?? '') . '"'
        . ' data-fc-edition-city="' . esc_attr($ct) . '"'
        . ' data-fc-edition-url="' . esc_attr($url) . '"';
    if ($tag === 'a') {
        $attrs .= ' href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer"';
    } else {
        $attrs = 'type="button" ' . $attrs;
    }
    echo '<' . $tag . ' ' . $attrs . '>';
    echo '<span class="fc-edition-text">' . esc_html($yr . ' / ' . ucfirst($ct)) . '</span>';
    echo '</' . $tag . '>';
};

// Editions data — serves as BOTH the year browser AND the globe pins.
// Normalised centrally so the desktop sidebar list (section-nav.php) and the
// mobile bar below stay in sync with the globe. See fc_venue_editions().
$editions_json_arr = fc_venue_editions();
$editions_json     = wp_json_encode($editions_json_arr);

/* Open the section manually (not using fc_section_open) so we can place the year browser
   INSIDE the <section> but OUTSIDE the max-w container. */
$id          = (string) $section['key'];
$eyebrow     = fc_section_eyebrow($section);
?>
<section id="<?php echo esc_attr($id); ?>" class="bg-paper relative border-t border-border">
    <?php if (!empty($editions_json_arr)) : ?>
        <!-- Editions bar (MOBILE ONLY — lg:hidden). A sticky horizontal nav shown
             while the venue section is on screen. On mobile the blue FOSSCOMM
             bar slides away after Home, so the persistent top chrome is the
             section nav (sticky top-0, 40px) — this sticks just below it at
             top-10. The desktop equivalent is the vertical sticky panel right
             below this. "Editions:" stays pinned far left; scrolling auto-selects
             the leftmost item (assets/venue-map.js). -->
        <nav
            aria-label="<?php echo esc_attr(fc_t('editions_label')); ?>"
            data-fc-editions-mobile
            class="
                lg:hidden
                sticky fc-bar-sub z-40 fc-bar
                bg-paper border-b border-border
                font-mono text-[11px] uppercase tracking-widest text-ink-muted
                overflow-x-auto whitespace-nowrap fc-nav-no-scrollbar flex items-center
            "
        >
            <span class="fc-editions-label shrink-0 sticky left-0 z-20 flex items-center gap-4 h-full bg-paper pl-4 pr-4 text-ink">
                <span><?php echo esc_html(fc_t('editions_label')); ?></span>
                <span class="opacity-50">//</span>
            </span>
            <?php foreach (array_reverse($editions_json_arr) as $ed) :
                $render_edition_item($ed, 'fc-edition-mobile-btn shrink-0 h-full flex items-center pr-4 whitespace-nowrap');
            endforeach; ?>
            <!-- Trailing spacer so the LAST item can scroll left far enough to
                 reach the highlight edge (assets/venue-map.js selectLeftmost). -->
            <span class="fc-edition-spacer shrink-0" aria-hidden="true"></span>
        </nav>

        <!-- Editions panel (DESKTOP ONLY — hidden lg:block). Same position:sticky
             mechanism as the mobile bar above: an absolute, section-tall rail
             (so it never displaces the venue content) pulled into the sidebar
             column at z-50, ON TOP OF the fixed section-nav. Inside, the list is
             position:sticky top-10 — bounded by the venue <section>, so it
             enters aligned to the section's top, locks, then releases at the
             section's bottom: the exact lifecycle the mobile bar has. -->
        <div class="hidden lg:block lg:absolute lg:-top-px lg:-bottom-px lg:-left-[200px] lg:w-[200px] z-40 pointer-events-none">
            <nav
                aria-label="<?php echo esc_attr(fc_t('editions_label')); ?>"
                data-fc-editions-desktop
                class="
                    sticky pointer-events-auto
                    bg-paper border-t border-r border-b border-border
                    px-5 py-8
                    font-mono text-[11px] uppercase tracking-widest text-ink-muted
                "
            >
                <div class="flex items-center gap-2 mb-6 text-ink">
                    <span><?php echo esc_html(fc_t('editions_label')); ?></span>
                    <span class="opacity-50">//</span>
                </div>
                <ul class="flex flex-col gap-y-2">
                    <?php foreach (array_reverse($editions_json_arr) as $ed) : ?>
                        <li><?php $render_edition_item($ed, 'block w-full text-left p-0'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
    <div class="max-w-[1440px] mx-auto px-4 md:px-8 py-24 md:py-40">
        <?php if ($eyebrow !== '') : ?>
            <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6">
                <?php echo esc_html($eyebrow); ?>
            </div>
        <?php endif; ?>
        <?php $title_text = fc_one($title); if ($title_text !== '') : ?>
            <h2 class="font-display text-4xl md:text-6xl leading-[1.0] tracking-tight mb-16"><?php echo fc_format($title_text); ?></h2>
        <?php endif; ?>

        <!-- Main content: left text | right globe. On md the right column stretches to
             the row height so the globe (justify-end) stays pinned to the bottom line
             instead of floating in the middle when it shrinks. -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-start md:items-stretch">

            <!-- Left: venue card — big hover-scramble title + Google Maps link + address + info rows -->
            <div class="space-y-6 pb-6 md:pb-20">
                <?php $uni = fc_one($uni_title); if ($uni !== '') :
                    $has_maps   = $maps_url !== '';
                    $has_hover  = $hover_text !== '';
                    $link_tag   = $has_maps ? 'a' : 'div';
                    $link_attrs = 'class="fc-venue-title-link block no-underline text-inherit"';
                    if ($has_maps) {
                        $link_attrs .= ' href="' . esc_url($maps_url) . '" target="_blank" rel="noreferrer"';
                    }
                    // Main title = the venue name (active language); on hover it
                    // scrambles into the admin-set hover text (any free text).
                    // Click opens Google Maps.
                    ?>
                    <<?php echo $link_tag; ?> <?php echo $link_attrs; ?>>
                        <h3 class="fc-venue-title-en font-display text-3xl md:text-5xl leading-[1.05] tracking-tight text-ink m-0"
                            data-fc-default="<?php echo esc_attr($uni); ?>"
                            data-fc-hover="<?php echo esc_attr($has_hover ? $hover_text : $uni); ?>"><?php echo esc_html($uni); ?></h3>
                    </<?php echo $link_tag; ?>>
                <?php endif; ?>

                <?php $address_text = fc_one($address); if ($address_text !== '') : ?>
                    <div class="font-mono text-sm leading-relaxed border-l-2 border-accent pl-4">
                        <div class="flex flex-wrap gap-x-3 gap-y-2">
                            <p class="m-0 text-ink-muted whitespace-pre-line"><?php echo fc_format($address_text); ?></p>
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
                            <div class="grid grid-cols-[1fr_2fr] gap-4 items-center py-3 border-b border-border">
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

            <!-- Right: MapLibre venue map (assets/venue-map.js). Editions are map
                 pins; the year browser lives in the desktop panel + mobile bar. -->
            <div class="relative flex flex-col justify-end h-full">
                <div class="w-full"
                     data-fc-island="venue-map"
                     data-fc-cluster-label="<?php echo esc_attr($cluster_label); ?>"
                     data-fc-pin-sprite="<?php echo esc_attr($pin_sprite); ?>"
                     data-fc-spotlight-sprite="<?php echo esc_attr($spotlight_sprite); ?>"
                     data-fc-pin-scale="<?php echo esc_attr((string) $pin_scale); ?>"
                     data-fc-spotlight-scale="<?php echo esc_attr((string) $spotlight_scale); ?>"
                     data-fc-editions="<?php echo esc_attr($editions_json); ?>">
                    <noscript>
                        <div class="ascii text-xs text-ink-faint border border-border p-6 text-center">[ Map requires JavaScript ]</div>
                    </noscript>
                </div>
            </div>
        </div>

        <?php if (!empty($travel_cards)) : ?>
            <div class="grid grid-cols-12 gap-8 border-t border-border pt-12 mt-0">
                <?php $gh = fc_one($getting_here); if ($gh === '') $gh = fc_t('getting_here'); ?>
                <div class="col-span-12 md:col-span-3 font-mono text-[11px] uppercase tracking-widest text-ink-muted">
                    <div><?php echo esc_html($gh); ?></div>
                </div>
                <div class="col-span-12 md:col-span-9 grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8 text-base leading-relaxed">
                    <?php foreach ($travel_cards as $card) :
                        $ct = fc_one(fc_bi($card, 'title'));
                        $cb = fc_one(fc_bi($card, 'body'));
                        if ($ct === '') continue;
                        ?>
                        <div>
                            <div class="font-display text-2xl mb-2"><?php echo fc_format($ct); ?></div>
                            <?php if ($cb !== '') : ?>
                                <div class="mt-3">
                                    <p class="text-ink-muted mt-1"><?php echo fc_format($cb); ?></p>
                                </div>
                            <?php endif; ?>
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
[data-fc-editions-desktop] { top: calc(var(--fc-sections-end, var(--fc-bar-h)) + 1px); }

/* Editions sidebar items behave like normal links now: a real :hover (mouse
   only) OR a JS-set .is-hovered (from a map-pin hover, or the mobile bar's
   scroll-select) turns the item accent and shows the pointer cursor. Rows with
   an archive URL are <a target="_blank">; link-less rows are <button>s that
   show a sass message on click (assets/venue-map.js). */
.fc-year-btn { cursor: pointer; }
@media (hover: hover) {
    .fc-year-btn:hover { color: var(--accent) !important; }
}
.fc-year-btn.is-hovered { color: var(--accent) !important; }

/* Mobile bar trailing spacer — lets the last item scroll left to the highlight
   edge so it can be auto-selected like the rest. */
.fc-edition-spacer { width: 70vw; }

/* Venue card title: hover scrambles the English title into the coordinates
   label (window.fcScramble, same engine the FAQ uses) and instantly hides the
   Greek sub-line so the coordinates stand alone. Click opens Google Maps.
   Hover behaviour is gated behind the lg breakpoint (matches fc.js's
   `(max-width: 1023.98px)` mobile check) so touch viewports — including
   hybrid laptops and DevTools mobile mode that still report `(hover: hover)`
   — never trigger the scramble or colour change. Tap stays click-only. */
.fc-venue-title-link { cursor: pointer; }
/* Subline that only exists to show the longitude on hover (no Greek title set).
   Reserve the visual space so the title doesn't shift when hover content appears,
   but keep it invisible until hover. */
.fc-venue-title-el-hover-only { visibility: hidden; }
.fc-venue-title-link.is-hovering .fc-venue-title-el-hover-only { visibility: visible; }
@media (min-width: 1024px) {
    a.fc-venue-title-link { transition: color 200ms ease; }
    a.fc-venue-title-link:hover .fc-venue-title-en { color: var(--color-accent, #0033FF); }
}
</style>
<?php /* Hover-scramble swap.
   Desktop pointer hover (and keyboard focus): EN scrambles into the latitude;
   the subline scrambles into the longitude. On un-hover both scramble back.
   Mobile tap: first tap scrambles to lat/lon (no CSS :hover effect — those
   styles are gated behind the lg media query above). Second tap navigates
   to Google Maps if a URL is set, otherwise swaps back to defaults. A tap
   outside the title resets the state. */ ?>
<script>
(function () {
    // Live check — viewport-width-based, matching fc.js's lg breakpoint. Using
    // (hover: none) here was unreliable: hybrid touch laptops and DevTools'
    // mobile mode often still report (hover: hover) and the scramble would
    // fire on tap. Re-evaluated at every event so a window resize takes effect
    // without reload.
    var mqMobile = window.matchMedia && window.matchMedia('(max-width: 1023.98px)');
    function isMobile() { return !!(mqMobile && mqMobile.matches); }
    var GLYPHS = 'αβγδεζηθικλμνξοπρστυφχψω0123456789';
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
    function scrambleTo(elm, text) {
        if (!elm || text === null) return;
        if (typeof window.fcScramble === 'function') {
            window.fcScramble(elm, text);
        } else {
            elm.textContent = text;
        }
    }
    function isDeadHref(link) {
        if (link.tagName !== 'A') return true;
        var href = link.getAttribute('href');
        if (href === null) return true;
        var t = href.trim();
        return t === '' || t === '#';
    }
    var links = document.querySelectorAll('.fc-venue-title-link');
    links.forEach(function (link) {
        var en = link.querySelector('.fc-venue-title-en');
        var el = link.querySelector('.fc-venue-title-el');
        if (!en && !el) return;
        var state = false;
        function swap(toHover) {
            if (toHover === state) return;
            state = toHover;
            if (toHover) {
                // EN scrambles to lat, subline scrambles to lon. If the subline
                // is empty by default (no Greek title set), glyphify first so
                // the user sees characters land instead of an empty string.
                link.classList.add('is-hovering');
                if (en) scrambleTo(en, en.getAttribute('data-fc-hover'));
                if (el) {
                    var elHover = el.getAttribute('data-fc-hover');
                    var elDefault = el.getAttribute('data-fc-default') || '';
                    if (elDefault === '' && elHover) {
                        el.textContent = glyphify(elHover);
                    }
                    scrambleTo(el, elHover);
                }
            } else {
                if (en) scrambleTo(en, en.getAttribute('data-fc-default'));
                if (el) {
                    var elDefault2 = el.getAttribute('data-fc-default') || '';
                    el.textContent = glyphify(elDefault2);
                    link.classList.remove('is-hovering');
                    scrambleTo(el, elDefault2);
                } else {
                    link.classList.remove('is-hovering');
                }
            }
        }
        // Desktop: pointer-only hover (+ keyboard focus parity).
        link.addEventListener('mouseenter', function () {
            if (isMobile()) return;
            swap(true);
        });
        link.addEventListener('mouseleave', function () {
            if (isMobile()) return;
            swap(false);
        });
        link.addEventListener('focus', function () {
            if (isMobile()) return;
            swap(true);
        }, true);
        link.addEventListener('blur', function () {
            if (isMobile()) return;
            swap(false);
        }, true);
        // Mobile: a tap on a real link (Google Maps URL set) navigates
        // immediately — no reveal-first / two-tap. Only the link-less title
        // (a <div>, dead href) toggles the hover reveal on tap, since it has
        // nothing to open.
        link.addEventListener('click', function (e) {
            if (!isMobile()) return;
            if (!isDeadHref(link)) return;   // real <a href> → let the browser navigate
            e.preventDefault();
            swap(!state);
        });
        // Reset when the user taps anywhere else.
        document.addEventListener('click', function (e) {
            if (!isMobile() || !state) return;
            if (link.contains(e.target)) return;
            swap(false);
        }, true);
    });
})();
</script>
