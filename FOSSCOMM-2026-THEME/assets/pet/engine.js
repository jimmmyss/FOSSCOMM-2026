import { CONFIG } from './config.js'
import { ANIMATIONS } from './animations/index.js'

const pet = document.getElementById('pet')
const petAscii = document.getElementById('petAscii')

if (!pet || !petAscii) {
  console.warn('[fc-pet] container missing; pet disabled')
} else {
  start()
}

let platforms = []
let dividers = []
let sectionRects = []

const petState = {
  x: 120,
  y: 0,
  vy: 0,
  direction: 1,
  currentPlatform: 0,
  desiredPlatform: 0,
  busy: false,
  currentAnimation: 'walk',
  frameIndex: 0,
  lastFrameTime: 0,
  idleAction: null,
  idlePhase: 'action',
  idlePhaseStart: 0
}

function wait(ms) {
  return new Promise(r => setTimeout(r, ms))
}

function nextFrame() {
  return new Promise(r => requestAnimationFrame(r))
}

function choose(arr) {
  return arr[Math.floor(Math.random() * arr.length)]
}

function weightedChoose(arr, weights) {
  const r = Math.random()
  let cumulative = 0
  for (let i = 0; i < arr.length; i++) {
    cumulative += weights[i]
    if (r < cumulative) return arr[i]
  }
  return arr[arr.length - 1]
}

function petPreWidth() {
  return petAscii.offsetWidth || CONFIG.petWidth
}

function petPreHeight() {
  return petAscii.offsetHeight || CONFIG.petHeight
}

function clampIndex(index) {
  return Math.max(0, Math.min(index, Math.max(0, dividers.length - 1)))
}

function sectionLeftFor(index) {
  const r = sectionRects[clampIndex(index)]
  return r ? r.left : 0
}

function sectionWidthFor(index) {
  const r = sectionRects[clampIndex(index)]
  return r ? r.width : (window.innerWidth || document.documentElement.clientWidth)
}

function sectionTopFor(index) {
  const r = sectionRects[clampIndex(index)]
  return r ? r.top : 0
}

function sectionBottomFor(index) {
  const r = sectionRects[clampIndex(index)]
  return r ? r.bottom : 0
}

function horizontalMinXFor(index) {
  return sectionLeftFor(index) + CONFIG.edgeInset
}

function horizontalMaxXFor(index) {
  return sectionLeftFor(index) + sectionWidthFor(index) - petPreWidth() - CONFIG.edgeInset
}

function horizontalMinX() { return horizontalMinXFor(petState.currentPlatform) }
function horizontalMaxX() { return horizontalMaxXFor(petState.currentPlatform) }

function randomTargetX(index = petState.currentPlatform) {
  const min = horizontalMinXFor(index)
  const max = horizontalMaxXFor(index)
  if (max <= min) return min
  return min + Math.random() * (max - min)
}

function worldScale(index = petState.currentPlatform) {
  const w = sectionWidthFor(index)
  const raw = w / CONFIG.referenceSectionWidth
  return Math.max(CONFIG.minWorldScale, Math.min(CONFIG.maxWorldScale, raw))
}

function capStep(v) {
  const cap = CONFIG.maxPxPerFrame
  if (v > cap) return cap
  if (v < -cap) return -cap
  return v
}

function platformY(index) {
  if (index < 0) return 0
  if (index >= platforms.length) return document.body.scrollHeight
  return platforms[index]
}

function rebuildPlatforms() {
  dividers = Array.from(document.querySelectorAll(CONFIG.platformSelector))
  const sx = window.scrollX || window.pageXOffset || 0
  const sy = window.scrollY || window.pageYOffset || 0
  sectionRects = dividers.map(d => {
    const r = d.getBoundingClientRect()
    return {
      top: r.top + sy,
      bottom: r.bottom + sy,
      left: r.left + sx,
      right: r.right + sx,
      width: r.width,
      height: r.height
    }
  })
  platforms = sectionRects.map(r => r.bottom)
}

let lastTransformStr = ''
function updatePet(bob = 0) {
  const translateY = petState.y - petPreHeight()
  const str = `translate3d(${petState.x.toFixed(2)}px, ${(translateY + bob).toFixed(2)}px, 0)`
  if (str !== lastTransformStr) {
    pet.style.transform = str
    lastTransformStr = str
  }
}

function setAnimation(name) {
  if (petState.currentAnimation !== name) {
    petState.currentAnimation = name
    petState.frameIndex = 0
    petState.lastFrameTime = performance.now()
  }
}

function computeDesiredPlatform() {
  if (sectionRects.length === 0) return 0
  const trigger = window.scrollY + window.innerHeight * CONFIG.scrollThreshold
  let desired = 0
  sectionRects.forEach((rect, i) => {
    if (rect.top <= trigger) desired = i
  })
  return Math.min(desired, Math.max(0, platforms.length - 1))
}

async function stepDown() {
  petState.busy = true
  setAnimation('jump')

  const scale = worldScale()
  const scaleSq = scale * scale
  petState.vy = CONFIG.jumpInitialVy * scale
  while (petState.vy < 0) {
    petState.vy += CONFIG.jumpWindupGravity * scaleSq
    petState.y += petState.vy
    updatePet()
    await nextFrame()
  }

  setAnimation('fall')
  const nextIndex = petState.currentPlatform + 1
  const startX = petState.x
  const startY = petState.y
  const targetX = randomTargetX(nextIndex)
  const targetY = platformY(nextIndex)
  const fallDistance = Math.max(1, targetY - startY)
  petState.direction = targetX >= startX ? 1 : -1
  const fallScale = worldScale(nextIndex)
  const fallScaleSq = fallScale * fallScale
  petState.vy = CONFIG.fallInitialVy * fallScale

  while (petState.y < targetY) {
    petState.vy += CONFIG.fallGravity * fallScaleSq
    petState.y += petState.vy
    const progress = Math.min(1, (petState.y - startY) / fallDistance)
    petState.x = startX + (targetX - startX) * progress
    updatePet()
    await nextFrame()
  }

  petState.y = targetY
  petState.x = targetX
  petState.currentPlatform = nextIndex
  updatePet()

  const landing = weightedChoose(CONFIG.landingStates, CONFIG.landingWeights)
  setAnimation(landing)
  await wait(landing === 'landHurt' ? CONFIG.landHurtDuration : CONFIG.landNormalDuration)

  setAnimation('walk')
  petState.busy = false
}

async function jumpUp() {
  petState.busy = true
  setAnimation('jump')

  const prevIndex = petState.currentPlatform - 1
  const startX = petState.x
  const startY = petState.y
  const targetX = randomTargetX(prevIndex)
  const targetY = platformY(prevIndex)
  petState.direction = targetX >= startX ? 1 : -1

  const scale = worldScale(prevIndex)
  const scaleSq = scale * scale
  const overshoot = CONFIG.jumpOvershoot * scale
  const peakY = targetY - overshoot
  const ascentDistance = Math.max(1, startY - peakY)
  const gravity = CONFIG.jumpGravity * scaleSq
  const computedVy = -Math.sqrt(2 * gravity * ascentDistance)
  petState.vy = Math.min(computedVy, CONFIG.bigJumpVy * scale)

  while (petState.vy < 0) {
    petState.vy += gravity
    petState.y += petState.vy
    const progress = Math.min(1, (startY - petState.y) / ascentDistance)
    petState.x = startX + (targetX - startX) * progress
    updatePet()
    await nextFrame()
  }

  petState.x = targetX
  setAnimation('fall')
  petState.vy = 0
  const fallG = CONFIG.fallGravity * scaleSq
  while (petState.y < targetY) {
    petState.vy += fallG
    petState.y += petState.vy
    updatePet()
    await nextFrame()
  }

  petState.y = targetY
  petState.x = targetX
  petState.currentPlatform = prevIndex
  updatePet()

  const landing = weightedChoose(CONFIG.landingStates, CONFIG.landingWeights)
  setAnimation(landing)
  await wait(landing === 'landHurt' ? CONFIG.landHurtDuration : CONFIG.landNormalDuration)

  setAnimation('walk')
  petState.busy = false
}

async function wallClimb() {
  petState.busy = true

  const prevIndex = petState.currentPlatform - 1
  const sectionLeft = sectionLeftFor(petState.currentPlatform)
  const sectionWidth = sectionWidthFor(petState.currentPlatform)
  const sectionCenter = sectionLeft + sectionWidth / 2
  const climbLeft = petState.x < sectionCenter
  const edgeX = climbLeft ? horizontalMinXFor(petState.currentPlatform) : horizontalMaxXFor(petState.currentPlatform)
  petState.direction = climbLeft ? -1 : 1

  const scale = worldScale()
  const edgeSpeed = CONFIG.walkToEdgeSpeed * scale

  setAnimation('walk')
  while (Math.abs(petState.x - edgeX) > edgeSpeed) {
    petState.x += petState.x < edgeX ? edgeSpeed : -edgeSpeed
    updatePet()
    await nextFrame()
  }
  petState.x = edgeX
  updatePet()

  setAnimation('climb')
  const climbStep = CONFIG.climbSpeed * scale
  const targetY = platformY(prevIndex)
  while (petState.y > targetY) {
    petState.y -= climbStep
    updatePet()
    await wait(CONFIG.climbFrameMs)
  }
  petState.y = targetY
  updatePet()

  const pullDistance = 70 * scale
  const pullSteps = 12
  const pullDelta = (climbLeft ? pullDistance : -pullDistance) / pullSteps
  setAnimation('walk')
  petState.direction = climbLeft ? 1 : -1
  for (let i = 0; i < pullSteps; i++) {
    petState.x += pullDelta
    updatePet()
    await nextFrame()
  }

  petState.currentPlatform = prevIndex
  setAnimation('walk')
  petState.busy = false
}

async function spawnIntro() {
  petState.busy = true

  const targetY = platformY(0)
  const sectionTop = sectionTopFor(0)
  petState.x = Math.max(
    horizontalMinXFor(0),
    Math.min(horizontalMinXFor(0) + 60, horizontalMaxXFor(0))
  )
  petState.y = Math.max(sectionTop + petPreHeight() + 10, targetY - 320)
  setAnimation('fall')
  updatePet()

  const startY = petState.y
  const fallDistance = Math.max(1, targetY - startY)
  const introScale = worldScale(0)
  const introScaleSq = introScale * introScale
  petState.vy = CONFIG.fallInitialVy * introScale

  while (petState.y < targetY) {
    petState.vy += CONFIG.fallGravity * introScaleSq
    petState.y += petState.vy
    updatePet()
    await nextFrame()
  }

  petState.y = targetY
  updatePet()

  setAnimation('landNormal')
  await wait(CONFIG.landNormalDuration)

  setAnimation('walk')
  petState.busy = false
}

function renderLoop() {
  const tick = (now) => {
    const anim = ANIMATIONS[petState.currentAnimation] || ANIMATIONS.idle
    if (anim && anim.frames && anim.frames.length > 0) {
      if (petState.lastFrameTime === 0) petState.lastFrameTime = now
      let frameChanged = false
      if (now - petState.lastFrameTime >= anim.frameDuration) {
        const prevIdx = petState.frameIndex
        if (anim.loop) {
          petState.frameIndex = (petState.frameIndex + 1) % anim.frames.length
        } else if (petState.frameIndex < anim.frames.length - 1) {
          petState.frameIndex += 1
        }
        petState.lastFrameTime = now
        frameChanged = petState.frameIndex !== prevIdx
      }
      if (frameChanged || petAscii.textContent === '') {
        petAscii.textContent = anim.frames[petState.frameIndex]
      }
    }

    let bob = 0
    if (
      !petState.busy &&
      petState.currentAnimation === 'walk' &&
      petState.currentPlatform === petState.desiredPlatform &&
      petState.idlePhase === 'action'
    ) {
      const scale = worldScale()
      bob = Math.sin(now * CONFIG.bobFrequency) * CONFIG.bobAmplitude * scale
      petState.x += capStep(CONFIG.walkSpeed * petState.direction * scale)
      const minX = horizontalMinX()
      const maxX = horizontalMaxX()
      if (petState.x < minX) {
        petState.x = minX
        petState.direction = 1
      } else if (petState.x > maxX) {
        petState.x = maxX
        petState.direction = -1
      }
    }

    const wantFlip = petState.direction === -1 ? 'scaleX(-1)' : 'scaleX(1)'
    if (petAscii.dataset.flip !== wantFlip) {
      petAscii.style.transform = wantFlip
      petAscii.dataset.flip = wantFlip
    }

    updatePet(bob)
    requestAnimationFrame(tick)
  }
  petAscii.style.transformOrigin = 'center'
  requestAnimationFrame(tick)
}

async function navigationLoop() {
  while (true) {
    if (!petState.busy) {
      if (petState.currentPlatform < petState.desiredPlatform) {
        await stepDown()
      } else if (petState.currentPlatform > petState.desiredPlatform) {
        if (Math.random() < 0.5) {
          await jumpUp()
        } else {
          await wallClimb()
        }
      }
    }
    await wait(50)
  }
}

async function idleLoop() {
  while (true) {
    if (!petState.busy && petState.currentPlatform === petState.desiredPlatform) {
      const now = performance.now()
      if (petState.idlePhaseStart === 0) {
        petState.idlePhase = 'action'
        petState.idleAction = choose(CONFIG.idleActions)
        petState.idlePhaseStart = now
        setAnimation(petState.idleAction)
      }

      const elapsed = now - petState.idlePhaseStart
      const phaseDuration = petState.idlePhase === 'action'
        ? CONFIG.idleActionDuration
        : CONFIG.idleRestDuration

      if (elapsed >= phaseDuration) {
        if (petState.idlePhase === 'action') {
          petState.idlePhase = 'rest'
          petState.idleAction = null
          setAnimation('idle')
        } else {
          petState.idlePhase = 'action'
          petState.idleAction = choose(CONFIG.idleActions)
          setAnimation(petState.idleAction)
        }
        petState.idlePhaseStart = now
      }
    } else {
      petState.idlePhaseStart = 0
      petState.idleAction = null
      petState.idlePhase = 'action'
    }
    await wait(100)
  }
}

function onScroll() {
  petState.desiredPlatform = computeDesiredPlatform()
}

function onResize() {
  rebuildPlatforms()
  petState.y = platformY(petState.currentPlatform)
  const minX = horizontalMinX()
  const maxX = horizontalMaxX()
  if (petState.x < minX) petState.x = minX
  if (petState.x > maxX) petState.x = maxX
  updatePet()
}

function start() {
  const begin = async () => {
    rebuildPlatforms()
    petState.currentPlatform = 0
    petState.desiredPlatform = computeDesiredPlatform()

    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onResize)

    renderLoop()
    await spawnIntro()
    navigationLoop()
    idleLoop()
  }

  if (document.readyState === 'complete') {
    begin()
  } else {
    window.addEventListener('load', begin, { once: true })
  }
}
