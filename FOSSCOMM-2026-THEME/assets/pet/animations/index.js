import { walk } from './walk.js'
import { wave } from './wave.js'
import { dance } from './dance.js'
import { idle } from './idle.js'
import { jump } from './jump.js'
import { fall } from './fall.js'
import { landNormal } from './land-normal.js'
import { landHurt } from './land-hurt.js'
import { climb } from './climb.js'

export const ANIMATIONS = {
  walk, wave, dance, idle,
  jump, fall, landNormal, landHurt, climb
}
