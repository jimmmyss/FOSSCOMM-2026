# BACKGROUNDS

One flat color with a grid of small dots in a lighter shade of it. Every background is made the same way.

| Folder | What's in it |
|---|---|
| [SOCIAL](SOCIAL) | For screens. One folder per size, each with `BLACK`, `BLUE` and `WHITE`. |
| [SIGNAGE](SIGNAGE) | For print. One folder per printed piece. Sizes and bleeds are in [its README](SIGNAGE/README.md). |
| [COLORS](COLORS) | The swatches shown below. |

## THE DOTS

<img src="GRID.svg" alt="A dot every 40 px, the first one 20 px from the edge, each dot 6 x 6 px with the corners off">

- **Grid:** one dot every 40 px, across and down.
- **Dot:** a 6 x 6 px square with its 4 corners off.
- **Edge:** the first dot's center is 20 px from every edge, half a step. Put two backgrounds side by side and the grid carries on across the join.
- **Sizes:** every size is a multiple of 40, so all four edges get the same 20 px. When a platform asks for one that isn't (1200x627, 1920x325), the dots stay 40 px apart and the leftover is split between the two edges.
- **Two colors only:** every file is exactly ground and dots. The dots are written pixel by pixel, not drawn, so they have no soft edges.
- **Print:** the same grid, scaled up. Dot and spacing grow together, so the dot is always 6 to every 40: at 2x it's 12 px every 80 px, at 15x it's 90 px every 600 px. Each piece's scale is in [SIGNAGE](SIGNAGE/README.md).

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
