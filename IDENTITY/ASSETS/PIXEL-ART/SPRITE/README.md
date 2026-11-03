# SPRITE

The mascot, a light bulb, drawn in pixels. Every animation is one file: its frames side by side, each 32 x 32 px, played left to right.

## SPRITE SHEET

[`SPRITE-SHEET.png`](SPRITE-SHEET.png) has every animation in one file: one row each, in the order of the table below, frames from the left. 9 x 22 cells of 32 x 32 px, so 288 x 704 px. Empty cells are transparent.

<img src="SPRITE-SHEET.png" alt="Every animation, one row each">

## ANIMATIONS

Shown at 2x. The 18 marked "yes" are the exact files the site uses.

| Row | Animation | Frames | On the site |
|---|---|---|---|
| 1 | **ANGRY**<br>4 frames | <img src="ANGRY.svg" height="64" alt="ANGRY"> | no, ANGRY-FLASH is used |
| 2 | **ANGRY-FLASH**<br>4 frames | <img src="ANGRY-FLASH.svg" height="64" alt="ANGRY-FLASH"> | yes |
| 3 | **BLINK**<br>4 frames | <img src="BLINK.svg" height="64" alt="BLINK"> | yes |
| 4 | **DISSAGREE**<br>9 frames | <img src="DISSAGREE.svg" height="64" alt="DISSAGREE"> | yes |
| 5 | **DISSAPOINTED**<br>1 frame | <img src="DISSAPOINTED.svg" height="64" alt="DISSAPOINTED"> | yes |
| 6 | **DIZZY**<br>1 frame | <img src="DIZZY.svg" height="64" alt="DIZZY"> | yes |
| 7 | **DRIFT**<br>1 frame | <img src="DRIFT.svg" height="64" alt="DRIFT"> | yes |
| 8 | **DROWSY**<br>5 frames | <img src="DROWSY.svg" height="64" alt="DROWSY"> | yes |
| 9 | **FALLING**<br>2 frames | <img src="FALLING.svg" height="64" alt="FALLING"> | yes |
| 10 | **HELD**<br>2 frames | <img src="HELD.svg" height="64" alt="HELD"> | yes |
| 11 | **IDLE**<br>1 frame | <img src="IDLE.svg" height="64" alt="IDLE"> | yes |
| 12 | **JUMP**<br>1 frame | <img src="JUMP.svg" height="64" alt="JUMP"> | yes |
| 13 | **SAD**<br>2 frames | <img src="SAD.svg" height="64" alt="SAD"> | yes |
| 14 | **SAD-OLD**<br>1 frame | <img src="SAD-OLD.svg" height="64" alt="SAD-OLD"> | no, an older SAD |
| 15 | **SHOCKED**<br>4 frames | <img src="SHOCKED.svg" height="64" alt="SHOCKED"> | yes |
| 16 | **SHOCKED-FLASH**<br>4 frames | <img src="SHOCKED-FLASH.svg" height="64" alt="SHOCKED-FLASH"> | no, SHOCKED is used |
| 17 | **SHY**<br>2 frames | <img src="SHY.svg" height="64" alt="SHY"> | yes |
| 18 | **SLEEP**<br>2 frames | <img src="SLEEP.svg" height="64" alt="SLEEP"> | yes |
| 19 | **SLEEP-STATIC**<br>1 frame | <img src="SLEEP-STATIC.svg" height="64" alt="SLEEP-STATIC"> | no, it heads the repo README |
| 20 | **TRIP**<br>3 frames | <img src="TRIP.svg" height="64" alt="TRIP"> | yes |
| 21 | **WALK**<br>8 frames | <img src="WALK.svg" height="64" alt="WALK"> | yes |
| 22 | **WAVE**<br>4 frames | <img src="WAVE.svg" height="64" alt="WAVE"> | yes |

## FILES

- `<NAME>.png` is the animation: 32 px tall, one 32 x 32 frame after another.
- `<NAME>.svg` has the same pixels as a vector, sharp at any size.
- `SPRITE-SHEET.png` and `.svg` hold every animation in one sheet.
- `COLOR-MAP.png` and `.svg` are the palette below.

## COLORS

<img src="COLOR-MAP.svg" width="231" alt="The color map: lit bulb, unlit bulb, base and face">

| Part | Colors |
|---|---|
| Lit bulb | <img src="../COLORS/FFEE87.svg" width="16" height="16" alt="#FFEE87"> `#FFEE87` &nbsp; <img src="../COLORS/FECE01.svg" width="16" height="16" alt="#FECE01"> `#FECE01` &nbsp; <img src="../COLORS/EE8101.svg" width="16" height="16" alt="#EE8101"> `#EE8101` &nbsp; <img src="../COLORS/FFDB35.svg" width="16" height="16" alt="#FFDB35"> `#FFDB35`<br><img src="../COLORS/FEDC51.svg" width="16" height="16" alt="#FEDC51"> `#FEDC51` &nbsp; <img src="../COLORS/FCDD58.svg" width="16" height="16" alt="#FCDD58"> `#FCDD58` &nbsp; <img src="../COLORS/F7BF03.svg" width="16" height="16" alt="#F7BF03"> `#F7BF03` &nbsp; <img src="../COLORS/F7BF01.svg" width="16" height="16" alt="#F7BF01"> `#F7BF01` |
| Unlit bulb<br><sub>ANGRY-FLASH, SHOCKED-FLASH</sub> | <img src="../COLORS/BEE7E9.svg" width="16" height="16" alt="#BEE7E9"> `#BEE7E9` &nbsp; <img src="../COLORS/99D0DE.svg" width="16" height="16" alt="#99D0DE"> `#99D0DE` &nbsp; <img src="../COLORS/5A9D9D.svg" width="16" height="16" alt="#5A9D9D"> `#5A9D9D` &nbsp; <img src="../COLORS/9BBFC1.svg" width="16" height="16" alt="#9BBFC1"> `#9BBFC1` |
| Base | <img src="../COLORS/3C3C3C.svg" width="16" height="16" alt="#3C3C3C"> `#3C3C3C` &nbsp; <img src="../COLORS/646464.svg" width="16" height="16" alt="#646464"> `#646464` &nbsp; <img src="../COLORS/A0A0A0.svg" width="16" height="16" alt="#A0A0A0"> `#A0A0A0` &nbsp; <img src="../COLORS/969696.svg" width="16" height="16" alt="#969696"> `#969696`<br><img src="../COLORS/AAAAAA.svg" width="16" height="16" alt="#AAAAAA"> `#AAAAAA` &nbsp; <img src="../COLORS/8C8C8C.svg" width="16" height="16" alt="#8C8C8C"> `#8C8C8C` &nbsp; <img src="../COLORS/B4B4B4.svg" width="16" height="16" alt="#B4B4B4"> `#B4B4B4` &nbsp; <img src="../COLORS/828282.svg" width="16" height="16" alt="#828282"> `#828282`<br><img src="../COLORS/787878.svg" width="16" height="16" alt="#787878"> `#787878` &nbsp; <img src="../COLORS/BEBEBE.svg" width="16" height="16" alt="#BEBEBE"> `#BEBEBE` &nbsp; <img src="../COLORS/6E6E6E.svg" width="16" height="16" alt="#6E6E6E"> `#6E6E6E` |
| Face | eyes <img src="../COLORS/1E1E1E.svg" width="16" height="16" alt="#1E1E1E"> `#1E1E1E`<br>shine <img src="../COLORS/FEFEF1.svg" width="16" height="16" alt="#FEFEF1"> `#FEFEF1` &nbsp; <img src="../COLORS/F6FFFF.svg" width="16" height="16" alt="#F6FFFF"> `#F6FFFF`<br>cheeks <img src="../COLORS/FFBAE3.svg" width="16" height="16" alt="#FFBAE3"> `#FFBAE3` &nbsp; <img src="../COLORS/FF6BC3.svg" width="16" height="16" alt="#FF6BC3"> `#FF6BC3` |
| Extras<br><sub>not in the color map</sub> | angry <img src="../COLORS/FF4673.svg" width="16" height="16" alt="#FF4673"> `#FF4673` <sub>ANGRY, ANGRY-FLASH</sub><br>tears <img src="../COLORS/64E7E9.svg" width="16" height="16" alt="#64E7E9"> `#64E7E9` <sub>DISSAPOINTED, SAD-OLD, TRIP</sub><br>blush <img src="../COLORS/FFA5DB.svg" width="16" height="16" alt="#FFA5DB"> `#FFA5DB` <sub>SHY</sub> |
