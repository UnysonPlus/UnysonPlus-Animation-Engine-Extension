---
type: shortcode
name: interactive_reveal
since: Unyson+ (Animation Engine)
provides: leaf-shortcode
---

# Interactive Reveal

A stack of image **layers** that animate from an assembled state (progress 0) to an
exploded / peeled / assembled-apart state (progress 1) — the layered "product reveal."
Sibling to `image_sequence` (frame scrub) and `model_viewer` (3D); this is the
**Layered-2D** technique. Leaf shortcode, auto-discovered like the other Animation
Engine shortcodes.

## Options (options.php)

- **Layers** — `layers` addable-box, back→front (first = furthest back). Per layer:
  `image` (upload), `label`, `dx`/`dy` (% travel at full reveal), `rot` (deg), `scale`
  (% size change), `fade` (fade in as it separates).
- **Hotspots** — `hotspots` addable-box: `label`, `x`/`y` (% of box), `reveal_at`
  (progress % at which the pin fades in), `connector` (leader line between dot and
  label). Rendered as real-text labels (a11y/SEO).
- **Motion** — `trigger` (scroll / hover / drag / toggle / auto) + `drag_axis`, `reverse`, `start_at`
  (rest separation %), `duration` (ms for non-scroll triggers).
- **Style** — `placement` (inline / background / foreground) + `pointer` (decorative
  pass-through / interactive), `aspect`, `height`, `max_width`, `align`, `bg`.

## Rendering (views/view.php)

Wrapper `.sc-ireveal .sc-ireveal--{placement} .sc-ireveal--pointer-{x} .sc-ireveal--trigger-{t}`
with `data-ir-trigger/reverse/start/duration`. Each layer is
`.sc-ireveal__layer[data-dx/dy/rot/scale/fade]` (absolute, stacked by z-index) wrapping an
`<img>`. Hotspots are `.sc-ireveal__spot[data-at]` with a `.sc-ireveal__dot` + `.sc-ireveal__label`.

## JS (static/js/interactive-reveal.js)

Computes one progress `p` (0..1) from the trigger and applies
`translate(dx·p%,dy·p%) rotate(rot·p) scale(1+scale·p/100)` per layer; hotspots toggle
`.is-on` past their `data-at`. Scroll rides the shared `window.upwAnimRaf` +
`window.upwScrollProgress`; hover/toggle/auto tween with rAF (easeInOut, `duration`).
`reverse` swaps the from/to ends; `start_at` sets the resting separation. **Reduced motion
→ the fully-revealed static state** (internals + all hotspots visible), no animation.

## Placement & pointer-events

`background`/`foreground` absolutely fill a positioned ancestor (set the Section to
`position: relative`). `decorative` sets `pointer-events:none` so clicks pass through
(labels stay clickable); `interactive` captures the pointer (required for hover/toggle,
blocks clicks under the overlap — a browser limit, surfaced in the option desc).

## Reveal Packs (shared asset store)

`includes/reveal-packs.php` provides a clutter-free store under
`uploads/unysonplus/reveal/<slug>/` — layers/frames as loose files, never Media-Library
attachments. Layers tab: **Layers from** = Manual (the addable-box) or **Reveal Pack**
(a `pack` select of installed packs); the view swaps to `fw_ir_get_pack()`'s layers when
Pack is chosen. Manage packs at **Media → Reveal Packs** (ZIP upload → auto-manifest with
radial spread when no `pack.json`, poster, delete). A bundled **Demo Gadget** pack is
seeded on first admin load. Helpers: `fw_ir_reveal_dir/list_packs/get_pack/pack_choices/
install_zip/delete_pack`. `pack.json`: `{ name, type:'layers'|'sequence', poster, layers[
{src,label,dx,dy,rot,scale,fade} ] }`.

## Demo

`test-sites/interactive-reveal-demo/index.html` — self-contained (real CSS/JS + the demo
SVG layers in `static/img/demo/`) showing hover / drag / toggle / autoplay / background.

## Pending

- Wire Reveal Packs into `image_sequence` (frames) too.
- Pinned drag, hotspot camera-focus, and a demos-network page.
