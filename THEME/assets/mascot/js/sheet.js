/**
 * Sprite sheets: load the image, work out the grid, build a playable clip.
 *
 * This replaces the old atlas-JSON pipeline outright. A sheet is now just an
 * image of equal cells and the timing is data typed in wp-admin, so there is no
 * export step, no second file to keep beside the PNG, and no way for the two to
 * disagree. Everything that used to be read out of a LibreSprite "Hash" export
 * — cell size, frame count, per-frame duration — is derived or configured here.
 *
 * Cutting rule: a sheet is a strip of SQUARE cells, so the cell is the shorter
 * side of the image and the frame count is the longer side divided by it. A
 * 128×32 strip is four 32×32 frames; a 32×128 one is the same four stacked.
 * Nothing is configurable, because nothing needs to be — the image already says
 * what it is, and the two form fields that used to ask had exactly one correct
 * answer each.
 */

const DEFAULT_FRAME_MS = 120;

/** One image, fetched once and shared by every clip that names the same URL. */
const imageCache = new Map();

export function loadImage(url) {
    if (imageCache.has(url)) return imageCache.get(url);
    const promise = new Promise((resolve) => {
        const img = new Image();
        const done = () => resolve(img.naturalWidth && img.naturalHeight ? img : null);

        img.onload = () => {
            // `load` only means DOWNLOADED. The bitmap is still compressed, and
            // the decode happens on first paint — so the frame that first shows
            // a sheet can be composited before the pixels exist, which is the
            // blank flicker on an animation change. decode() moves that work to
            // now, off the critical frame.
            //
            // Both branches of the promise go to the same place: a decode that
            // fails still leaves a perfectly usable image, and the fallback is
            // simply the old behaviour.
            if (typeof img.decode === 'function') img.decode().then(done, done);
            else done();
        };
        // Resolve with null rather than rejecting: a broken URL must leave one
        // animation inert, never take down the boot chain that awaits it.
        img.onerror = () => resolve(null);
        img.src = url;
    });
    imageCache.set(url, promise);
    return promise;
}

/**
 * Work out the cell grid for an image: square cells, laid along its long axis.
 *
 * Exported because the admin timeline needs the same answer to know how many
 * per-frame boxes to draw — one rule, one implementation, two callers.
 */
export function sliceGrid(width, height) {
    const cell = Math.max(1, Math.min(width, height));
    if (width >= height) {
        const cols = Math.max(1, Math.floor(width / cell));
        return { frameW: cell, frameH: cell, cols, rows: 1, count: cols };
    }
    const rows = Math.max(1, Math.floor(height / cell));
    return { frameW: cell, frameH: cell, cols: 1, rows, count: rows };
}

/**
 * The order cells play in.
 *
 * Both ping-pongs deliberately drop the repeated end cell — bouncing 0,1,2,1
 * rather than 0,1,2,2,1 — so the turn reads as a turn instead of a stutter on
 * the frame you dwell on twice.
 */
export function playbackOrder(count, direction) {
    const forward = [];
    for (let i = 0; i < count; i++) forward.push(i);
    if (count < 2) return forward;

    switch (direction) {
        case 'reverse':
            return forward.slice().reverse();
        case 'pingpong':
            return forward.concat(forward.slice(1, -1).reverse());
        case 'pingpong_reverse': {
            const back = forward.slice().reverse();
            return back.concat(back.slice(1, -1).reverse());
        }
        default:
            return forward;
    }
}

/**
 * Build a clip from an entry.
 *
 * Returns null when the art can't be loaded, which every caller reads as "this
 * animation doesn't exist" — a state with no usable clip is passed straight
 * through rather than becoming a dead end.
 */
export async function buildClip(entry) {
    if (!entry || !entry.png) return null;
    const img = await loadImage(entry.png);
    if (!img) return null;

    const grid = sliceGrid(img.naturalWidth, img.naturalHeight);
    const order = playbackOrder(grid.count, entry.direction);

    // Per-cell durations, indexed by CELL not by playback position, so a
    // ping-pong shows each cell for its own time on the way back too. A sheet
    // that grew a frame since it was last saved falls back to the default rather
    // than freezing on frame 0.
    const durations = [];
    for (let i = 0; i < grid.count; i++) {
        const ms = entry.frames && entry.frames[i];
        durations.push(Number.isFinite(ms) && ms >= 16 ? ms : DEFAULT_FRAME_MS);
    }

    let totalMs = 0;
    for (const cell of order) totalMs += durations[cell] || DEFAULT_FRAME_MS;

    return {
        name: entry.name || '',
        url: entry.png,
        img,
        width: img.naturalWidth,
        height: img.naturalHeight,
        frameW: grid.frameW,
        frameH: grid.frameH,
        cols: grid.cols,
        rows: grid.rows,
        count: grid.count,
        order,
        durations,
        totalMs,
    };
}

/** Build every entry of a state in parallel, dropping the ones that fail. */
export async function buildClips(entries) {
    const built = await Promise.all((entries || []).map(buildClip));
    return built.filter(Boolean);
}

/** Alpha below this counts as "not him" for hit-testing. */
const SOLID_ALPHA = 16;

/**
 * One byte of alpha per pixel of the sheet, so a click can be tested against the
 * DRAWING rather than against its bounding box.
 *
 * Built lazily and cached on the clip: most clips never need it, and reading a
 * whole sheet back out of a canvas is not free. Returns null — meaning "treat
 * the whole cell as solid" — if anything at all goes wrong, which for a sheet
 * served from another origin is a certainty rather than a risk: getImageData on
 * a canvas tainted by a cross-origin image throws, and a mascot you cannot pick
 * up would be a much worse outcome than one with a generous hit box.
 */
function alphaMask(clip) {
    if (clip.alpha !== undefined) return clip.alpha;
    clip.alpha = null;
    try {
        const canvas = document.createElement('canvas');
        canvas.width = clip.width;
        canvas.height = clip.height;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(clip.img, 0, 0);
        const data = ctx.getImageData(0, 0, clip.width, clip.height).data;
        const mask = new Uint8Array(clip.width * clip.height);
        for (let i = 0; i < mask.length; i++) mask[i] = data[i * 4 + 3];
        clip.alpha = mask;
    } catch {
        clip.alpha = null;
    }
    return clip.alpha;
}

/**
 * The transparent margin around the DRAWING, in unscaled pixels per side.
 *
 * A sprite cell is almost never filled to its edges, so colliding with the cell
 * leaves him hovering off the floor and stopping short of the walls by however
 * much empty space the artist left. Measuring it lets the collision use his
 * actual silhouette, so "against the left edge" means his arm touches it.
 *
 * Taken as the union across every frame of the clip rather than per frame: a
 * per-frame box would change size as he animates and jitter him against the
 * walls.
 *
 * Zero on every side when the mask is unavailable, which is the old behaviour.
 */
export function drawnInsets(clip) {
    if (clip.insets !== undefined) return clip.insets;
    clip.insets = { left: 0, right: 0, top: 0, bottom: 0 };

    const mask = alphaMask(clip);
    if (!mask) return clip.insets;

    let minU = clip.frameW, maxU = -1, minV = clip.frameH, maxV = -1;
    for (let cell = 0; cell < clip.count; cell++) {
        const ox = (cell % clip.cols) * clip.frameW;
        const oy = Math.floor(cell / clip.cols) * clip.frameH;
        for (let v = 0; v < clip.frameH; v++) {
            const rowBase = (oy + v) * clip.width + ox;
            for (let u = 0; u < clip.frameW; u++) {
                if (mask[rowBase + u] < SOLID_ALPHA) continue;
                if (u < minU) minU = u;
                if (u > maxU) maxU = u;
                if (v < minV) minV = v;
                if (v > maxV) maxV = v;
            }
        }
    }
    if (maxU < 0) return clip.insets;   // nothing drawn at all

    clip.insets = {
        left: minU,
        right: clip.frameW - 1 - maxU,
        top: minV,
        bottom: clip.frameH - 1 - maxV,
    };
    return clip.insets;
}

/**
 * Is (u, v) — in unscaled pixels within `cell` — actually drawn on?
 * True whenever the mask is unavailable, so a hit test can only ever make the
 * target smaller, never lose it entirely.
 */
export function isSolidPixel(clip, cell, u, v) {
    if (!clip) return false;
    if (u < 0 || v < 0 || u >= clip.frameW || v >= clip.frameH) return false;

    const mask = alphaMask(clip);
    if (!mask) return true;

    const col = cell % clip.cols;
    const row = Math.floor(cell / clip.cols);
    const x = col * clip.frameW + Math.floor(u);
    const y = row * clip.frameH + Math.floor(v);
    if (x < 0 || y < 0 || x >= clip.width || y >= clip.height) return false;

    return mask[y * clip.width + x] >= SOLID_ALPHA;
}
