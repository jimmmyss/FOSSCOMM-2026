export const CONFIG = {
  petHeight: 105,
  petWidth: 180,
  platformSelector: 'section',
  scrollThreshold: 0.35,

  horizontalMin: 80,
  horizontalMax: null,
  edgeInset: 8,
  walkSpeed: 3.4,

  referenceSectionWidth: 1100,
  minWorldScale: 0.8,
  maxWorldScale: 1.4,
  maxPxPerFrame: 4,

  jumpInitialVy: -10,
  jumpWindupGravity: 0.6,
  fallInitialVy: 2,
  fallGravity: 0.55,

  bigJumpVy: -14,
  jumpGravity: 0.45,
  jumpOvershoot: 35,

  climbSpeed: 15,
  climbFrameMs: 80,
  walkToEdgeSpeed: 8,

  idleActionDuration: 3000,
  idleRestDuration: 3000,
  idleActions: ['walk', 'wave', 'dance'],

  landingStates: ['landNormal', 'landHurt'],
  landingWeights: [0.7, 0.3],
  landNormalDuration: 600,
  landHurtDuration: 1200,

  bobAmplitude: 2,
  bobFrequency: 0.05
}
