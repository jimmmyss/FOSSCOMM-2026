/**
 * Frame playback. Given a clip and elapsed time, says which cell to show.
 *
 * Deliberately has no idea what a mascot is: it takes milliseconds in and gives
 * a cell index out. That is what lets wp-admin's per-entry preview run the exact
 * same code as the live mascot instead of a re-implementation that drifts —
 * which is how the old build ended up with the preview honouring atlas
 * durations while the front end quietly ignored them.
 */

export class Animator {
    /**
     * @param {object} clip  from sheet.js buildClip()
     * @param {boolean} loop replay from the top when the sequence ends
     */
    constructor(clip, loop = true) {
        this.clip = clip;
        this.loop = !!loop;
        this.reset();
    }

    reset() {
        this.pos = 0;          // index into clip.order
        this.elapsed = 0;      // ms spent on the current cell
        this.done = false;     // a non-looping clip has played its last cell
        this.loops = 0;        // completed passes, for anything counting repeats
    }

    /** The cell to draw right now. */
    get cell() {
        if (!this.clip || !this.clip.order.length) return 0;
        return this.clip.order[Math.min(this.pos, this.clip.order.length - 1)];
    }

    /** How long one full pass takes, in ms. */
    get durationMs() {
        return this.clip ? this.clip.totalMs : 0;
    }

    /**
     * Advance by dt milliseconds.
     *
     * Loops rather than branches so a long frame gap — a background tab, a slow
     * paint — advances by however many cells actually elapsed instead of one.
     * The step guard stops a zero-duration clip spinning forever inside it.
     */
    update(dt) {
        const clip = this.clip;
        if (!clip || !clip.order.length || this.done) return this.cell;

        this.elapsed += dt;
        let guard = 0;
        while (guard++ < 1000) {
            const cell = clip.order[this.pos];
            const hold = clip.durations[cell] || 0;
            if (hold <= 0 || this.elapsed < hold) break;

            this.elapsed -= hold;
            this.pos++;
            if (this.pos >= clip.order.length) {
                this.loops++;
                if (this.loop) {
                    this.pos = 0;
                } else {
                    this.pos = clip.order.length - 1;
                    this.done = true;
                    this.elapsed = 0;
                    break;
                }
            }
        }
        return this.cell;
    }
}

/**
 * Where a cell sits in the sheet, in unscaled pixels.
 * Row-major, matching sliceGrid().
 */
export function cellOffset(clip, cell) {
    const col = cell % clip.cols;
    const row = Math.floor(cell / clip.cols);
    return { x: col * clip.frameW, y: row * clip.frameH };
}

/**
 * Point an element at one cell of a clip.
 *
 * background-size is the WHOLE sheet scaled up, and background-position walks a
 * window over it. Scaling the sheet rather than the element is what keeps
 * `image-rendering: pixelated` honest at fractional scales — the browser
 * upscales once, from the source pixels.
 */
export function paintCell(el, clip, cell, scale) {
    const { x, y } = cellOffset(clip, cell);
    // Every value rounded to a whole pixel. A fractional element size or
    // background offset lands the art on half-pixels, and the browser resolves
    // that by blending — which is the one thing `image-rendering: pixelated`
    // cannot save you from, because the softening happens in layout, not paint.
    el.style.width = `${Math.round(clip.frameW * scale)}px`;
    el.style.height = `${Math.round(clip.frameH * scale)}px`;
    el.style.backgroundImage = `url("${clip.url}")`;
    el.style.backgroundSize = `${Math.round(clip.width * scale)}px ${Math.round(clip.height * scale)}px`;
    el.style.backgroundPosition = `${Math.round(-x * scale)}px ${Math.round(-y * scale)}px`;
}
