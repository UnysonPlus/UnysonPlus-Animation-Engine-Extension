# Animation Engine picker icons — STANDARD: animated SVG (REQUIRED)

Every Animation Engine **effect / design picker icon** (these design tiles, and engine module
pickers in general) is an **animated SVG (SMIL)** — a tiny living preview of the effect, not a
static thumbnail. A static icon is the exception, allowed only as a temporary placeholder until the
effect it represents is built.

## The recipe (match the existing icons)

- **Canvas:** `viewBox="0 0 96 64"`, `fill="none"`. Cards are rounded `<rect>`s.
- **Standard icon blue** (the ONLY palette): a top-left→bottom-right gradient
  **`#8fbcff` → `#2f74e6`**. No other colors. This blue is shared across every engine icon.
- **Motion mirrors the real effect.** Each card rides an `<animateMotion>` path shaped like the
  effect's motion:
  - Ring / orbit effects → an **elliptical** path (see `carousel-ring.svg`; it also rolls the whole
    group for the diagonal tilt).
  - Wall / scroll effects → a **horizontal conveyor** path, optionally barrel-arced per row (see
    `panorama-wall.svg`).
- **Fake depth with position-keyed scale + opacity.** Alongside the motion, animate the card's
  `scale` (bigger/nearer at the focal point, smaller at the rim) and `opacity` (brighter at focus,
  dimmer at the rim) with `keyTimes` that line up with the path's focal/rim points.
- **Hide the recycle.** Fade `opacity` to **0** at the wrap point of the path so the jump back is
  invisible (a card fades out at one edge as a staggered sibling fades in at the other).
- **Stagger with negative `begin`.** Reuse ONE path for N cards, each with `begin="-<offset>s"` so
  they chase around continuously (offset = `k * dur / N`, plus a small per-row offset for grids).

## Gotcha (cost us a rewrite once)

**Do NOT share a card via `<use href="#card">` when the card contains its own scale/opacity
animations.** Each `<use>` clones the inner animations but they all run on the SAME timeline
(`begin=0`), so every card pulses in unison instead of scaling by its own position. **Inline each
card** (its `animateMotion` + `scale` + `opacity` all sharing that card's `begin` offset) so motion
and depth stay in phase. Generating the SVG with a tiny script (see how these were built) keeps the
repetition correct.

## Rendering / fallback

SMIL animates when the SVG is referenced via `<img>` (how the option image-picker renders it), which
covers Chrome / Edge / Firefox = every browser wp-admin runs in. Author the **first frame** to be a
correct static composition too, so a SMIL-disabled viewer still shows a sensible icon.

## Status

- `carousel-ring.svg` — animated (diagonal elliptical ring). ✅
- `panorama-wall.svg` — animated (horizontal barrel conveyor). ✅
- `card-sphere.svg` — animated (spinning globe; cards ride elliptical latitude bands). ✅
- `photo-scatter.svg` — animated (cards glide in from the edges, rest scattered, sweep out). ✅
