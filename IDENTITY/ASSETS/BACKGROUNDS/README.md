# BACKGROUNDS

One flat color with a grid of small dots in a lighter shade of it. Every background is made the same way.

| Folder | What's in it |
|---|---|
| [SOCIAL](SOCIAL) | For screens. One folder per size, each with `BLACK`, `BLUE` and `WHITE`. |
| [SIGNAGE](SIGNAGE) | For print. One folder per printed piece. Sizes and bleeds are in [its README](SIGNAGE/README.md). |
| [COLORS](COLORS) | The swatches shown below. |

## THE DOTS

<img src="GRID.svg" alt="A dot every 40 units, the first one 20 units from the edge, each dot 6 x 6 units with the corners off">

The numbers are grid units, the same on every background. Only the size of a unit changes:

| | 1 unit | Dot | Step |
|---|---|---|---|
| Screens ([SOCIAL](SOCIAL)) | 1 px | 6 px | 40 px |
| Lanyards | 1 px | 6 px | 40 px |
| Cards | 2 px | 12 px | 80 px |
| Poster | 4 px | 24 px | 160 px |
| Roll-up | 15 px | 90 px | 600 px |

- **Grid:** one dot every 40 units, across and down.
- **Dot:** 6 x 6 units with the 4 corners off, so always 15% of the step.
- **Edge:** the grid is centered on the file. When the size is a whole number of steps, the first dot's center is 20 units (half a step) from every edge, and two backgrounds side by side carry the grid on across the join.
- **Other sizes:** when it isn't (1200x627, 1920x325 and every print piece), the dots stay one step apart and the leftover is split evenly between opposite edges.
- **Two colors only:** every file is exactly ground and dots. The dots are written pixel by pixel, not drawn, so they have no soft edges.

## COLORS

Screen colors are RGB. Print colors are CMYK for Coated FOGRA39, and their swatches show how they look on paper.

| | SCREEN | RGB | PRINT | CMYK |
|---|:-:|---|:-:|---|
| **BLUE** | <img src="COLORS/BLUE.svg" width="120" alt="Blue"> | `#0033FF`<br>dots `#3860FF` | <img src="COLORS/BLUE-CMYK.svg" width="120" alt="Blue, print"> | `C97 M73 Y0 K0`<br>dots `C76 M57 Y0 K0`<br><sub>cards, lanyards, poster, roll-up</sub> |
| **BLACK** | <img src="COLORS/BLACK.svg" width="120" alt="Black"> | `#0A0A0A`<br>dots `#3F3F3E` | <img src="COLORS/BLACK-K70-CMYK.svg" width="120" alt="Black, print, K70 dots"> | `C0 M0 Y0 K97`<br>dots `C0 M0 Y0 K70`<br><sub>cards</sub> |
| | | | <img src="COLORS/BLACK-K85-CMYK.svg" width="120" alt="Black, print, K85 dots"> | `C0 M0 Y0 K97`<br>dots `C0 M0 Y0 K85`<br><sub>lanyards, roll-up</sub> |
| **WHITE** | <img src="COLORS/WHITE.svg" width="120" alt="White"> | `#FAFAF7`<br>dots `#E2E2DF` | <img src="COLORS/WHITE-K22-CMYK.svg" width="120" alt="White, print, K22 dots"> | `C0 M0 Y0 K0` (the paper)<br>dots `C0 M0 Y0 K22`<br><sub>cards</sub> |
| | | | <img src="COLORS/WHITE-K15-CMYK.svg" width="120" alt="White, print, K15 dots"> | `C0 M0 Y0 K0` (the paper)<br>dots `C0 M0 Y0 K15`<br><sub>lanyards, roll-up</sub> |
| **YELLOW** | <img src="COLORS/YELLOW.svg" width="120" alt="Yellow"> | `#F7BF03`<br>dots `#FFDB35` | <img src="COLORS/YELLOW-Y50-CMYK.svg" width="120" alt="Yellow, print, Y50 dots"> | `C0 M27 Y100 K0`<br>dots `C0 M14 Y50 K0`<br><sub>cards</sub> |
| | | | <img src="COLORS/YELLOW-Y84-CMYK.svg" width="120" alt="Yellow, print, Y84 dots"> | `C0 M27 Y100 K0`<br>dots `C0 M12 Y84 K0`<br><sub>lanyards</sub> |
| **NAVY-BLACK** | | | <img src="COLORS/NAVY-BLACK-CMYK.svg" width="120" alt="Navy-black, print"> | `C90 M80 Y15 K95`<br>dots `C66 M58 Y11 K69`<br><sub>lanyards</sub> |
| **WARM-BLACK** | | | <img src="COLORS/WARM-BLACK-CMYK.svg" width="120" alt="Warm-black, print"> | `C40 M50 Y90 K100`<br>dots `C34 M43 Y77 K86`<br><sub>lanyards</sub> |

`#0033FF` is brighter than any ink can print, so print uses `C97 M73 Y0 K0` instead.
