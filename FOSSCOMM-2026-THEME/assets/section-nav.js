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
    if (isMobileNav() || links.length === 0) return
    const statusBar = document.querySelector('[data-fc-island="status-bar"]')
    const topBarH = statusBar ? statusBar.offsetHeight : 40
    const firstTop = links[0].getBoundingClientRect().top
    const lastBottom = links[links.length - 1].getBoundingClientRect().bottom
    const gap = Math.max(0, firstTop - topBarH)
    document.documentElement.style.setProperty('--fc-sections-end', lastBottom + gap + 'px')
  }

  // Mobile only: make the fixed FOSSCOMM bar behave like the venue/schedule
  // sticky bars but bounded by the HERO section — pinned at the top through
  // all of home, then scrolling out exactly as hero's bottom (the next
  // section's line) passes. The section-nav rides directly beneath it and
  // slides up to top:0 in lockstep as the bar leaves. Pure transform, so it's
  // independent of DOM order; hero's overflow-hidden rules out CSS sticky here.
  const statusBar = document.querySelector('[data-fc-island="status-bar"]')
  function syncHomeChrome() {
    if (!isMobileNav()) {
      if (statusBar) statusBar.style.transform = ''
      nav.style.transform = ''
      return
    }
    const hero = document.getElementById('hero')
    const barH = statusBar ? statusBar.offsetHeight : 40
    let offset = 0
    if (hero) {
      const heroBottom = hero.getBoundingClientRect().bottom
      offset = Math.max(-barH, Math.min(0, heroBottom - barH))
    }
    if (statusBar) statusBar.style.transform = 'translateY(' + offset + 'px)'
    nav.style.transform = 'translateY(' + Math.max(0, barH + offset) + 'px)'
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

  window.addEventListener('scroll', syncHomeChrome, { passive: true })
  window.addEventListener('resize', syncHomeChrome)
  window.addEventListener('load', syncHomeChrome)
  syncHomeChrome()

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
