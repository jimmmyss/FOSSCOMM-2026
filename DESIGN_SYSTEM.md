# FOSSCOMM 2026 — Design System & Brand Identity

> Theme: **FOSSCOMM 2026** | Version: 1.0.0 | URI: https://2026.fosscomm.gr
> Aesthetic: **ASCII-on-paper** — monochrome, squared, monospace-forward, performance-focused.

---

## Table of Contents

1. [Brand Identity](#1-brand-identity)
2. [Color Palette](#2-color-palette)
3. [Typography](#3-typography)
4. [Spacing & Layout](#4-spacing--layout)
5. [Border Radius](#5-border-radius)
6. [CSS Custom Properties (Tokens)](#6-css-custom-properties-tokens)
7. [Component Classes](#7-component-classes)
8. [Animation & Motion](#8-animation--motion)
9. [Responsive Design](#9-responsive-design)
10. [Accessibility](#10-accessibility)
11. [Asset Overview](#11-asset-overview)

---

## 1. Brand Identity

| Property | Value |
|---|---|
| **Theme Name** | FOSSCOMM 2026 |
| **Theme URI** | https://2026.fosscomm.gr |
| **Text Domain** | `fosscomm` |
| **Aesthetic Direction** | ASCII-on-paper |
| **Design Philosophy** | Monochrome, squared corners, monospace-forward, minimal decoration |
| **Bilingual** | Full EN / EL (Greek) support with side-by-side layout |

### Design Philosophy

The FOSSCOMM 2026 theme uses an **"ASCII-on-paper"** aesthetic. This means:

- **Squared corners everywhere** — zero border-radius across all components (`0px`)
- **Near-monochrome palette** — off-white paper, near-black ink, one electric blue accent
- **Monospace as a design element** — ASCII art mascot, tabular counters, code-style labels
- **Performance-first animations** — GPU-composited transforms, canvas-based wave background, no layout-triggering animation properties
- **Text as craft** — letter-spacing, tabular numbers, glyph-scramble effects, hover text swap

---

## 2. Color Palette

### Primary Colors

| Token | CSS Variable | Hex | Description |
|---|---|---|---|
| **Paper** | `--color-paper` / `--paper` | `#FAFAF7` | Page background — warm off-white, not pure white |
| **Ink** | `--color-ink` / `--ink` | `#0A0A0A` | Primary text — near-black, not pure black |
| **Accent** | `--color-accent` / `--accent` | `#0033FF` | Primary interactive color — electric blue |

### Secondary Colors

| Token | CSS Variable | Hex | Description |
|---|---|---|---|
| **Ink Muted** | `--color-ink-muted` / `--ink-muted` | `#6B6B66` | Secondary text, labels, placeholders |
| **Ink Faint** | `--color-ink-faint` / `--ink-faint` | `#C9C7BF` | Tertiary text, dot grid pattern, decorative elements |
| **Paper Shadow** | `--paper-shadow` | `rgba(10,10,10,0.04)` | Subtle shadows on paper background |

### Computed Colors

| Token | CSS Variable | Value | Description |
|---|---|---|---|
| **Border** | `--color-border` | `color-mix(in oklab, #0A0A0A 12%, transparent)` | Borders and dividers — Ink at 12% opacity, blended in oklab |

### Semantic / Contextual Colors

| Context | Color | Usage |
|---|---|---|
| **Selection highlight** | `#0033FF` (Accent) bg + `#FAFAF7` (Paper) text | Browser text selection (::selection) |
| **Active nav link** | `#0033FF` (Accent) | Currently visible section in sidebar nav |
| **Pet mascot** | `#0033FF` (Accent) | ASCII art character color |
| **Progress bar fill** | `#0033FF` (Accent) | Funding / CFP progress bar |
| **Progress overflow** | `#FF3B30` → `#FF1E1E` | Over-goal indicator (red gradient) |

### Contrast Ratios (WCAG)

| Foreground | Background | Ratio | Level |
|---|---|---|---|
| Ink `#0A0A0A` | Paper `#FAFAF7` | ~18.5:1 | AAA |
| Accent `#0033FF` | Paper `#FAFAF7` | ~8.7:1 | AA |
| Ink Muted `#6B6B66` | Paper `#FAFAF7` | ~7.9:1 | AA |

---

## 3. Typography

### Font Families

#### Display Font — Space Grotesk

```css
font-family: "Space Grotesk", "Inter", ui-sans-serif, system-ui, sans-serif;
letter-spacing: -0.04em;
font-weight: 500; /* default for .font-display class */
```

| Property | Value |
|---|---|
| **Source** | Google Fonts |
| **Weights loaded** | 400, 500, 700 |
| **Letter-spacing** | `-0.04em` (tight tracking — characteristic of the design) |
| **Usage** | Headings, display text, section titles, hero typography |
| **CSS class** | `.font-display` |

#### Body / Sans Font — Inter

```css
font-family: "Inter", ui-sans-serif, system-ui, sans-serif;
-webkit-font-smoothing: antialiased;
```

| Property | Value |
|---|---|
| **Source** | Google Fonts |
| **Weights loaded** | 400, 500, 700 |
| **Usage** | Body text, paragraphs, UI elements, default text |
| **Applied to** | `body` element (global default) |

#### Monospace Font — JetBrains Mono

```css
font-family: "JetBrains Mono", ui-monospace, "SF Mono", Menlo, monospace;
font-variant-numeric: tabular-nums;
```

| Property | Value |
|---|---|
| **Source** | Google Fonts |
| **Weights loaded** | 400, 500, 700 |
| **Numeric variant** | `tabular-nums` — all digits are equal-width (aligned columns) |
| **Usage** | ASCII art, counters, timestamps, code, labels, pet mascot |
| **CSS classes** | `.font-mono`, `.ascii` |

### Font Loading

All three families are loaded from a single Google Fonts request:

```
https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Inter:wght@400;500;700&family=JetBrains+Mono:wght@400;500;700&display=swap
```

The `display=swap` parameter ensures text renders in system fonts immediately (no invisible text during load).

### Typography CSS Classes

| Class | Font | Special Properties | Usage |
|---|---|---|---|
| `.font-display` | Space Grotesk | `letter-spacing: -0.04em`, `font-weight: 500` | Headings, display |
| `.font-mono` | JetBrains Mono | `font-variant-numeric: tabular-nums` | Inline monospace |
| `.ascii` | JetBrains Mono | `white-space: pre`, `line-height: 1`, `letter-spacing: 0` | ASCII art blocks |

### Pet Mascot Typography

The ASCII art mascot uses a dedicated size separate from the rest of the type system:

| Viewport | Font Size | Line Height |
|---|---|---|
| Desktop (`> 900px`) | `11px` | `11px` |
| Mobile (`≤ 900px`) | `10px` | `10px` |

Color: `#0033FF` (Accent blue)

---

## 4. Spacing & Layout

### Fluid Spacing (clamp-based)

The layout uses `clamp()` for fluid scaling — no breakpoint jumps, smooth resize.

#### Sponsor Cell Padding

```css
padding: clamp(0.75rem, 1.5vw, 1.75rem);
/* Min: 12px  |  Ideal: 1.5vw  |  Max: 28px */
```

#### Sponsor Tier Max-Widths

| Tier | CSS Class | Min | Fluid | Max |
|---|---|---|---|---|
| Platinum | `.fc-tier-platinum` | 260px | 24vw | 420px |
| Gold | `.fc-tier-gold` | 220px | 18vw | 330px |
| Silver | `.fc-tier-silver` | 190px | 15vw | 280px |
| Community | `.fc-tier-community` | 170px | 12vw | 230px |

### Sponsor Logo Aspect Ratio

```css
aspect-ratio: 5 / 2;   /* 2.5:1 — wide landscape format */
object-fit: contain;   /* letterboxed, never cropped */
```

### Progress Bar

```css
height: 0.6rem;   /* 9.6px */
```

### CTA Underline

```css
text-underline-offset: 6px;
text-decoration-thickness: 1px;
```

### Underline Hover Effect

```css
bottom: -2px;     /* 2px below the text baseline */
height: 1px;      /* hairline underline */
```

---

## 5. Border Radius

**All radii are zero.** This is a deliberate design decision — the squared aesthetic is fundamental.

```css
--radius-sm: 0px;
--radius-md: 0px;
--radius-lg: 0px;
```

No rounded corners anywhere in the UI.

---

## 6. CSS Custom Properties (Tokens)

### Tailwind v4 `@theme` Tokens (via `inc/bootstrap.php`)

These are the canonical, Tailwind-integrated tokens used with utility classes (`text-accent`, `bg-paper`, etc.):

```css
@theme {
    --color-paper:      #FAFAF7;
    --color-ink:        #0A0A0A;
    --color-ink-muted:  #6B6B66;
    --color-ink-faint:  #C9C7BF;
    --color-accent:     #0033FF;
    --color-border:     color-mix(in oklab, #0A0A0A 12%, transparent);

    --font-display:     "Space Grotesk", "Inter", ui-sans-serif, system-ui, sans-serif;
    --font-sans:        "Inter", ui-sans-serif, system-ui, sans-serif;
    --font-mono:        "JetBrains Mono", ui-monospace, "SF Mono", Menlo, monospace;

    --radius-sm:        0px;
    --radius-md:        0px;
    --radius-lg:        0px;
}
```

### Fallback `:root` Tokens (via `assets/dist/fc.css`)

These are the non-Tailwind fallbacks for plain CSS rules:

```css
:root {
    --paper:        #FAFAF7;
    --ink:          #0A0A0A;
    --ink-muted:    #6B6B66;
    --ink-faint:    #C9C7BF;
    --accent:       #0033FF;
    --paper-shadow: rgba(10,10,10,0.04);
}
```

> **Note:** The `--color-*` prefixed variables are Tailwind v4 tokens. The bare `--*` variables are the fallback equivalents. Both sets point to identical values.

---

## 7. Component Classes

### Text & Link Utilities

| Class | Effect | Details |
|---|---|---|
| `.fc-accent` | Color = Accent blue | Applied by `*text*` markup in admin fields |
| `.underline-link` | Animated underline | Origin flips right→left on hover, `0.35s cubic-bezier(0.22, 1, 0.36, 1)` |
| `.accent-link` | Hover → accent color | Simple color transition |
| `.fc-cta-text` | CTA underline styling | `1px` thick, `6px` offset, `skip-ink: auto` |

### Navigation

| Class | Effect | Details |
|---|---|---|
| `.fc-nav-link` | Section nav link | `color: inherit`, transitions to accent on active/hover (`80ms ease`) |
| `.fc-nav-link.is-active` | Active nav state | `color: #0033FF` |
| `.fc-nav-no-scrollbar` | Hides scrollbar | Works in WebKit, Firefox, and IE |
| `.fc-topbar-brand` | Brand link in top bar | Same `80ms ease` hover transition to accent |
| `.fc-year-btn` | Year selector button | Same `80ms ease` hover transition to accent |

### Background & Layout

| Class | Effect | Details |
|---|---|---|
| `.fc-hero-dots` | Animated dot grid | Pure CSS, GPU-composited, 5s loop |
| `.fc-section-dots` | Transparent section | Lets the wave canvas show through |

### Sponsors

| Class | Effect | Details |
|---|---|---|
| `.fc-sponsor-row` | Sponsor tier row | Centered flex, non-wrapping, equal-share cells |
| `.fc-sponsor-cell` | Single sponsor slot | Fluid padding, tier-specific max-width |
| `.fc-sponsor-box` | Logo container | `aspect-ratio: 5/2`, `overflow: hidden` |
| `.fc-sponsor-logo` | Logo image | `object-fit: contain`, `opacity` transition `250ms` |
| `.fc-sponsor-logo-alt` | Hover alternate logo | `opacity: 0` by default, fades in on hover (desktop only) |
| `.fc-sponsor-name` | Text fallback | Ink-muted color, `0.95rem`, centered |
| `.fc-sponsor-cell.is-swap` | Enables hover swap | Cross-fade between default and alt logo |
| `.fc-sponsor-cell.fc-shine` | Enables shine effect | Swept gradient, masked to logo shape, `3s` loop |

### Funding / Progress

| Class | Effect | Details |
|---|---|---|
| `.fc-progress` | Progress bar track | `0.6rem` height, border-color background |
| `.fc-progress-fill` | Fill bar | Accent blue, `600ms ease` width transition |
| `.fc-progress.is-over` | Over-goal state | `overflow: visible` |
| `.fc-progress-over` | Red overflow indicator | `48px` base width, accent→red gradient, trembles `36–60px` via JS |
| `.fc-fund` | Funding card container | `position: relative` |
| `.fc-fund.is-broken` | Over-goal card state | Right border replaced by angled SVG stubs |
| `.fc-fund-break` | Angled corner SVG | Replaces straight right border when over goal |

---

## 8. Animation & Motion

### Hero Dot Grid Drift (`assets/site.css`)

```css
@keyframes fc-hero-dots-drift {
    from { transform: translate(0, 0); }
    to   { transform: translate(32px, 32px); }
}
/* Duration: 5s | Easing: linear | Repeat: infinite */
/* Speed: 32px / 5s = 6.4px/s — constant regardless of viewport */
```

Dot grid: `radial-gradient` circles, `2.5px` radius, `32×32px` spacing, color `#C9C7BF`.

### Underline Link Hover (`assets/dist/fc.css`)

```css
transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
/* transform-origin flips: right (idle) → left (hovered) */
```

### Sponsor Logo Cross-Fade (`assets/site.css`)

```css
transition: opacity 250ms ease;
/* Desktop hover-capable only (lg+, hover: hover) */
```

### Sponsor Shine Sweep (`assets/site.css`)

```css
@keyframes fc-sponsor-shine {
    0%   { transform: translateX(-100%); }  /* off-screen left */
    35%  { transform: translateX(-100%); }  /* hold */
    70%  { transform: translateX(100%); }   /* sweep across */
    100% { transform: translateX(100%); }   /* off-screen right */
}
/* Duration: 3s | Easing: ease-in-out | Repeat: infinite */
/* Masked by sponsor logo — only paints on the logo shape */
```

### Nav Link / Brand Hover (`assets/site.css`)

```css
transition: color 80ms ease;
/* Very fast — nearly instant color snap to accent */
```

### Progress Bar Fill (`assets/site.css`)

```css
transition: width 600ms ease;
```

### Progress Overflow Tremble (`assets/site.css`)

```css
transition: width 60ms cubic-bezier(0.22, 0.9, 0.28, 1);
/* Width trembles 36–60px, driven by assets/cfp.js */
```

### Reduced Motion

All animations respect `prefers-reduced-motion: reduce`:

```css
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.001ms !important;
        transition-duration: 0.001ms !important;
    }
}
```

Additionally, specific components have targeted overrides (hero dots, sponsor shine, progress bar, scroll behavior) that disable or neutralize their animations under this preference.

---

## 9. Responsive Design

### Approach

- **Mobile-first** — Tailwind v4 standard breakpoints (`sm`, `md`, `lg`, `xl`)
- **Fluid scaling** via `clamp()` — no breakpoint jumps for sponsor sizing
- **Hover-gating** — desktop-only effects behind `@media (hover: hover) and (min-width: 1024px)`

### Key Breakpoints

| Breakpoint | Width | Notes |
|---|---|---|
| `sm` | `640px` | Standard Tailwind |
| `md` | `768px` | Standard Tailwind |
| `lg` | `1024px` | Sponsor hover effects activate |
| `xl` | `1280px` | Standard Tailwind |
| Pet mobile | `900px` | Pet font scales from 11px → 10px |

### Mobile-Specific Behavior

- **Sponsor logos**: No hover swap (avoids iOS "sticky hover" bug)
- **Sponsor shine**: Visible only on `lg+` with `hover: hover` pointer
- **Pet mascot**: Smaller font (`10px`) with `transform-origin: top left` for scaling

---

## 10. Accessibility

### Text Selection

```css
::selection {
    background: var(--accent);   /* #0033FF */
    color: var(--paper);         /* #FAFAF7 */
}
```

### Focus States

- `.fc-nav-link:focus-visible` → `color: var(--accent)`, `outline: none`
- `.fc-topbar-brand:focus-visible` → `color: var(--accent)`, `outline: none`

Focus is communicated via color change rather than outline rings (high-contrast color is sufficient given the contrast ratios).

### Reduced Motion

See [Section 8](#8-animation--motion) — all animations are disabled or collapsed under `prefers-reduced-motion: reduce`.

### Semantic Patterns

- ASCII art uses `<pre class="ascii">` — screen readers can announce or skip
- Icon links use `<span aria-hidden="true">→</span>` to hide decorative arrows
- Scroll behavior stays `auto` until page load completes (prevents fighting browser scroll-restoration)

### WCAG Contrast Summary

All text/background combinations exceed WCAG AA (minimum 4.5:1 for normal text). Primary text (#0A0A0A / #FAFAF7) exceeds AAA at ~18.5:1.

---

## 11. Asset Overview

### Stylesheets

| File | Purpose |
|---|---|
| [style.css](style.css) | WordPress theme header only — no styles |
| [assets/dist/fc.css](assets/dist/fc.css) | Font imports, `:root` tokens, utility classes (`.font-display`, `.ascii`, `.underline-link`), global resets |
| [assets/site.css](assets/site.css) | Component styles: hero dots, CTAs, sponsors, progress bar, nav links, section transparency |
| [assets/pet/pet.css](assets/pet/pet.css) | ASCII mascot positioning, font, color, mobile scaling |

### JavaScript (Behavior & Effects)

| File | Purpose |
|---|---|
| [assets/dist/fc.js](assets/dist/fc.js) | Main compiled application JS |
| [assets/section-nav.js](assets/section-nav.js) | Section navigation + smooth scroll activation |
| [assets/countdown.js](assets/countdown.js) | Event countdown timer |
| [assets/scramble.js](assets/scramble.js) | Reusable glyph-scramble (`window.fcScramble()`) |
| [assets/faq.js](assets/faq.js) | FAQ expand/collapse with scramble transitions |
| [assets/hover-scramble.js](assets/hover-scramble.js) | CTA hover text swap with scramble animation |
| [assets/cfp.js](assets/cfp.js) | CFP countdown + funding bar tremble effect |
| [assets/wave-bg.js](assets/wave-bg.js) | Animated wave canvas — fixed behind all sections |
| [assets/pet/engine.js](assets/pet/engine.js) | ASCII mascot physics engine |
| [assets/pet/config.js](assets/pet/config.js) | Mascot behavior configuration |
| [assets/pet/animations/](assets/pet/animations/) | Per-state ASCII art frames (idle, walk, jump, dance, wave, climb, fall…) |

### Design Token Source of Truth

| Location | Purpose |
|---|---|
| [inc/bootstrap.php](inc/bootstrap.php) lines 143–156 | Tailwind `@theme {}` tokens — canonical source for Tailwind utilities |
| [assets/dist/fc.css](assets/dist/fc.css) lines 10–17 | `:root {}` fallback tokens for plain CSS |

---

*Generated from source inspection of FOSSCOMM-2026-THEME v1.0.0*
