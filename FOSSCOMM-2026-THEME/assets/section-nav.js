// Highlights the active sidebar link and (on mobile) scrolls the nav so the active
// item sits at the left edge. Uses the same threshold the pet uses (0.35 viewport).

// ---------- Page-load scroll hygiene ----------
// Two competing requirements:
//   a. Hero/footer/sponsor CTAs with hrefs like `#sponsors` (and external links
//      to https://…/#sponsors) MUST actually land the user at that section.
//   b. The browser keeps re-anchor-jumping to the URL hash as Tailwind CDN,
//      images, and the ASCII pet mount cause layout shifts. If we leave the
//      hash in the URL, the user gets "pulled back" each time content above
//      the target reflows.
//
// Resolution: if there's a hash on load, scroll to the target ourselves
// (initially, on each rAF tick until layout settles, and once more after
// `load` so the final image-loaded Y is correct), THEN strip the hash so
// later reflows can't re-anchor. Hash that points to no element is just
// stripped — same as before.
//   We also don't re-write the hash on section-nav clicks (see init()),
// so the stale-hash-on-reload scenario the old code defended against can't
// arise from in-page navigation any more.
if ('scrollRestoration' in history) history.scrollRestoration = 'manual'
if (window.location.hash) {
  const targetId = decodeURIComponent(window.location.hash.slice(1))
  const target = targetId ? document.getElementById(targetId) : null
  // Strip the hash up front so layout shifts during load can't re-anchor.
  history.replaceState(
    null,
    '',
    window.location.pathname + window.location.search
  )
  if (target) {
    const scrollToTarget = () => {
      const top = target.getBoundingClientRect().top + window.scrollY
      window.scrollTo({ top, behavior: 'auto' })
    }
    scrollToTarget()
    requestAnimationFrame(scrollToTarget)
    window.addEventListener('load', scrollToTarget, { once: true })
  }
} else {
  // No hash → always begin at the very top (the intro curtain plays from here,
  // and a reload no longer leaves you part-way down the page).
  window.scrollTo(0, 0)
}

// Re-enable CSS smooth-scroll only after the page has finished loading. During
// initial load the browser may scroll-restore or jump to a URL hash, and with
// `scroll-behavior: smooth` active that motion competes with any scrolling the
// user does in the first second — felt like being "pulled back up to home".
// Anchor-link clicks (which fire well after load) still animate.
function enableSmoothScroll() {
  document.documentElement.classList.add('fc-scroll-smooth')
}
if (document.readyState === 'complete') {
  enableSmoothScroll()
} else {
  window.addEventListener('load', enableSmoothScroll, { once: true })
  // Max-wait fallback in case `load` is held back by slow third-party assets
  // (e.g. the Tailwind CDN). 800ms is well past the browser's
  // scroll-restoration window for any reasonable navigation.
  setTimeout(enableSmoothScroll, 800)
}

// ---------- Global in-page anchor smooth-scroll ----------
// Makes EVERY same-page hash link behave the same: hero/sponsor/footer CTAs
// (href="#schedule"…), FAQ anchors, and a `#section` typed straight into the URL
// bar all smooth-scroll to their target. Runs on every page, independent of the
// sidebar nav (which keeps its own handler — we skip [data-fc-nav-link] here so
// it isn't handled twice). Robust by design: it scrolls with JS instead of
// relying on the browser's native hash jump or the CSS `scroll-behavior`.
const fcPrefersReducedMotion =
  window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches

function fcSmoothScrollToEl(el) {
  const top = el.getBoundingClientRect().top + window.scrollY
  window.scrollTo({ top, behavior: fcPrefersReducedMotion ? 'auto' : 'smooth' })
}

// The on-THIS-page element id an anchor points at, or null. Uses the anchor's
// resolved .pathname/.host/.hash, so both href="#x" and a full
// "https://site/page#x" work, while links to another page return null.
function fcSamePageHashTarget(a) {
  if (!a || !a.hash) return null
  if (a.pathname !== window.location.pathname || a.host !== window.location.host) return null
  const id = decodeURIComponent(a.hash.slice(1))
  return id && document.getElementById(id) ? id : null
}

document.addEventListener('click', (e) => {
  // Bail if something already handled it (the dead-anchor guard or the mobile
  // tap-to-scramble in hover-scramble.js both preventDefault), and let modified
  // clicks (open-in-new-tab/window) keep their native behaviour.
  if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return
  const a = e.target && e.target.closest && e.target.closest('a[href]')
  if (!a || a.hasAttribute('data-fc-nav-link')) return
  const id = fcSamePageHashTarget(a)
  if (!id) return
  e.preventDefault()
  fcSmoothScrollToEl(document.getElementById(id))
})

// A hash typed/pasted into the URL bar (or back/forward between hashes) on the
// page that's already open: scroll to it smoothly too.
window.addEventListener('hashchange', () => {
  const id = window.location.hash ? decodeURIComponent(window.location.hash.slice(1)) : ''
  const el = id ? document.getElementById(id) : null
  if (el) fcSmoothScrollToEl(el)
})

const THRESHOLD = 0.35

const nav = document.querySelector('[data-fc-section-nav]')
const links = Array.from(document.querySelectorAll('[data-fc-nav-link]'))
if (nav && links.length > 0) {
  init()
}

function init() {
  const sectionFor = (link) => {
    const key = link.dataset.fcNavTarget
    return key ? document.getElementById(key) : null
  }

  function computeActiveKey() {
    const sections = document.querySelectorAll('section')
    if (sections.length === 0) return null
    const trigger = window.scrollY + window.innerHeight * THRESHOLD
    let activeId = null
    sections.forEach((sec) => {
      const top = sec.getBoundingClientRect().top + window.scrollY
      if (top <= trigger) activeId = sec.id
    })
    return activeId
  }

  const isMobileNav = () => !window.matchMedia('(min-width: 1024px)').matches

  // The venue editions panel (template-parts/sections/venue.php) is a separate
  // fixed-sidebar element, so CSS can't read where the section-nav text ends.
  // Publish that Y as --fc-sections-end: the bottom of the links list plus the
  // same gap that sits between the top bar and the first link. Desktop only;
  // the panel is hidden < lg and falls back to 2.5rem until this runs.
  function measureSectionsEnd() {
    if (isMobileNav()) return
    // The nav rail's <nav> is sticky at top:40px (lg:top-10) once locked, so its
    // stuck bottom is 40 + the nav's own content height. Measuring offsetHeight
    // is position-independent — correct even before the rail scrolls into view
    // (it starts at the Manifesto line, off-screen on Home).
    const navEl = document.querySelector('[data-fc-section-nav]')
    if (!navEl) return
    document.documentElement.style.setProperty('--fc-sections-end', (40 + navEl.offsetHeight) + 'px')
  }

  // Top-bar chrome, driven off the HERO's bottom edge — the actual Manifesto
  // section line, NOT a scroll-percentage threshold. The sidebar itself is a
  // pure CSS-sticky rail (template-parts/partials/section-nav.php), so JS never
  // touches it.
  //   • Mobile: the blue bar slides up and off as Home leaves (its sliding-away
  //     is the only "hide" — the section-nav rail then locks at the top on its
  //     own as you reach Manifesto).
  //   • Desktop: the bar stays put and flips blue→white the moment the Manifesto
  //     line reaches it.
  const statusBar = document.querySelector('[data-fc-island="status-bar"]')
  const isLanding = document.body.classList.contains('fc-landing')
  function chrome() {
    if (!isLanding || !statusBar) return
    const hero = document.getElementById('hero')
    const barH = statusBar.offsetHeight || 40
    const heroBottom = hero ? hero.getBoundingClientRect().bottom : -barH
    if (isMobileNav()) {
      const offset = Math.max(-barH, Math.min(0, heroBottom - barH))
      statusBar.style.transform = 'translateY(' + offset + 'px)'
      statusBar.classList.add('fc-topbar-blue')        // stays blue; it slides away
    } else {
      statusBar.style.transform = ''
      statusBar.classList.toggle('fc-topbar-blue', heroBottom > barH)  // white once past the line
    }
  }

  function setActive(key) {
    let activeLink = null
    links.forEach((link) => {
      const on = link.dataset.fcNavTarget === key
      link.classList.toggle('is-active', on)
      if (on) activeLink = link
    })
    if (activeLink && isMobileNav()) {
      const linkRect = activeLink.getBoundingClientRect()
      const navRect = nav.getBoundingClientRect()
      const navPaddingLeft = parseFloat(getComputedStyle(nav).paddingLeft) || 0
      const targetScrollLeft = nav.scrollLeft + (linkRect.left - navRect.left) - navPaddingLeft
      nav.scrollTo({ left: Math.max(0, targetScrollLeft), behavior: 'smooth' })
    }
  }

  let lastKey = null
  function tick() {
    const key = computeActiveKey()
    if (key && key !== lastKey) {
      lastKey = key
      setActive(key)
    }
  }

  window.addEventListener('scroll', tick, { passive: true })
  window.addEventListener('resize', tick)
  tick()

  window.addEventListener('scroll', chrome, { passive: true })
  window.addEventListener('resize', chrome)
  window.addEventListener('load', chrome)
  chrome()

  measureSectionsEnd()
  requestAnimationFrame(measureSectionsEnd)
  window.addEventListener('load', measureSectionsEnd)
  window.addEventListener('resize', measureSectionsEnd)

  links.forEach((link) => {
    link.addEventListener('click', (e) => {
      const key = link.dataset.fcNavTarget
      const target = key ? document.getElementById(key) : null
      if (!target) return
      e.preventDefault()
      const top = target.getBoundingClientRect().top + window.scrollY
      window.scrollTo({ top, behavior: 'smooth' })
      // Deliberately NOT writing #<key> to the URL — on reload the browser
      // would anchor-jump to that section as resources load, fighting any
      // user scroll happening in the first second after load.
    })
  })
}
