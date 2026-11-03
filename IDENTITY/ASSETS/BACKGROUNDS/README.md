# BACKGROUNDS

Dots. The same grid on everything.

## SOCIAL

For screens, RGB. The folder name is the size; the brackets give the aspect ratio and, if it has one, where it goes. Each folder has `BLACK`, `BLUE` and `WHITE`.

## SIGNAGE

For print. `.png` is the screen version, `-CMYK.tif` is what went to the printer. Every CMYK file is made for Coated FOGRA39. Print blue is C97 M73 Y0 K0, and the dots are always a close tint of their ground.

### CARDS — `90x60mm (CARDS)`

- The card is 85.68 × 54.02 mm. The file is 90 × 60 mm, so about 2.2 mm of bleed left and right and 3 mm top and bottom. 600 dpi.
- Attendee: black K97, dots K70.
- Contributor: blue C97 M73, dots C76 M57.
- Curator: white, dots K22.
- Volunteer: yellow C0 M27 Y100, dots C0 M14 Y50.

### LANYARDS — `930x20mm (LANYARDS)`

- The whole 930 × 20 mm strip, 300 dpi.
- Attendee and Contributor: `BLUE` with `NAVY-BLACK`.
- Volunteer: `YELLOW` with `WARM-BLACK`.
- Why two blacks: every lanyard fades from its colour into black. Plain K black turns that fade muddy, grey on the blue side and olive on the yellow. Navy-black (C90 M80 Y15 K95) is a cold black built from the blue's own inks, so the fade stays blue. Warm-black (C40 M50 Y90 K100) is built from the yellow's, so it stays golden.
- `BLACK` (K97) and `WHITE` are here too, but no lanyard uses them.

### POSTER — `297x420mm (POSTER)`

- A3, 297 × 420 mm, no bleed. 300 dpi.
- Blue C97 M73, dots C76 M57.

### ROLL-UP — `870x2270mm (ROLL-UP)`

- The visible area is 850 × 2000 mm. Bleed is 1 cm left, right and top and 7 cm at the bottom, so the page is 870 × 2080 mm. The background runs on to 2270 mm and the page crops it. 304.8 ppi.
- Blue on top, white below. Blue dots C76 M57, white dots K15.
- `BLACK` (K97, dots K85) is here too, but the roll-up doesn't use it.

## How they're made

On screens: a 40 px grid, each dot a 6×6 square with its corners off, 20 px in from every edge, so two tiles butt together without a seam. Print uses the same pattern at print resolution.

| | Ground | Dots |
|---|---|---|
| Blue | `#0033FF` | `#3860FF` |
| Black | `#0A0A0A` | `#3F3F3E` |
| White | `#FAFAF7` | `#E2E2DF` |
| Yellow | `#F7BF03` | `#FFDB35` |
