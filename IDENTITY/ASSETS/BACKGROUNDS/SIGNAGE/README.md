# SIGNAGE

Backgrounds for print. One folder per piece, named by the file's size.

- `.png` is the screen version (RGB).
- `-CMYK.tif` is the file for the printer, made for Coated FOGRA39.

The colors are in [BACKGROUNDS](../README.md#colors).

## SIZES

| | Finished size | Bleed | File size | Resolution | Grid | Dots |
|---|---|---|---|---|---|---|
| [CARDS](90x60mm%20%28CARDS%29) | 85.68 x 54.02 mm | 2.2 mm left and right, 3 mm top and bottom | 90 x 60 mm | 600 dpi | 2x | 0.51 mm, one every 3.39 mm |
| [LANYARDS](930x20mm%20%28LANYARDS%29) | 930 x 20 mm | none | 930 x 20 mm | 300 dpi | 1x | 0.51 mm, one every 3.39 mm |
| [POSTER](297x420mm%20%28POSTER%29) | 297 x 420 mm (A3) | none | 297 x 420 mm | 300 dpi | 4x | 2.03 mm, one every 13.55 mm |
| [ROLL-UP](870x2270mm%20%28ROLL-UP%29) | 850 x 2000 mm | 10 mm left, right and top, 70 mm bottom | 870 x 2270 mm | 304.8 dpi | 15x | 7.5 mm, one every 50 mm |

- **Grid** is the screen grid scaled up: at 2x a dot is 12 px and the spacing 80 px. The dot is always 6 to every 40.
- The grid is centered on each piece, so opposite edges get the same margin.
- The roll-up page with its bleed is 870 x 2080 mm. The background is taller, and the page crops it.
- The cards, lanyards and roll-up printed for 2026 had slightly different dot spacing. These files are corrected to the exact ratio.

## CARDS

| Card | Background |
|---|---|
| ATTENDEE | `BLACK` |
| CONTRIBUTOR | `BLUE` |
| CURATOR | `WHITE` |
| VOLUNTEER | `YELLOW` |

## LANYARDS

Every lanyard fades from its color into black.

| Lanyard | From | Into |
|---|---|---|
| ATTENDEE, CONTRIBUTOR | `BLUE` | `NAVY-BLACK` |
| VOLUNTEER | `YELLOW` | `WARM-BLACK` |

Why not plain black? A fade into plain black goes muddy in the middle: grey on the blue, olive on the yellow. `NAVY-BLACK` is a cold black mixed from the blue's own inks, so the fade stays blue. `WARM-BLACK` is mixed from the yellow's, so it stays golden.

`BLACK` and `WHITE` are here too, but no lanyard uses them.

## POSTER

`BLUE`, at 4x so the grid still shows from across a room. `BLACK` and `WHITE` are screen only (`.png`).

## ROLL-UP

`BLUE` on top, `WHITE` below. `BLACK` is here too, but the roll-up doesn't use it.
