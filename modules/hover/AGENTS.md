---
type: engine-module
name: hover
since: animation-engine 1.0.10
provides: per-element Hover Interactions (Animations tab)
---

# Hover Interactions module

Adds a pointer-driven **"Interaction"** hover effect to **every** element's
**Animations** tab. Loaded by the engine class `_init()` (only when the
`animation-engine` extension is active), so the field only appears when the engine
is on. Effects: **magnetic · tilt (3D) · spotlight · image_reveal · text_scramble**.

## How it plugs in (no per-element code)

1. **Options** — hooks **`sc_animation_fields`** (a filter the shortcodes extension
   exposes at the end of `sc_get_animation_fields()`) to append the `interaction`
   multi-picker. So it rides the existing Animations tab alongside Entrance Animation
   and GSAP Scroll Motion. Saved shape: `{ effect:'tilt', tilt:{ max_tilt, … } }`.
2. **Emission** — hooks **`sc_build_wrapper_attr`** (priority 21, just after the
   entrance filter's 20) to put `class="sc-hover sc-hover--<effect>"`, `data-hover`,
   the per-effect `data-hover-*` attrs and (spotlight/image_reveal) CSS vars on the
   element wrapper. Works for all ~69 elements that call the wrapper helper.
3. **Runtime** — `wp_footer` enqueues `static/{js/hover.js,css/hover.css}` only when
   an effect actually rendered (per-request `upw_hover_flag()`, mirroring the
   entrance-animation enqueue). An inline `window.upwHoverCfg` carries the engine's
   global reduced-motion / disable-on-mobile policy.
4. **Global toggle** — hooks **`upw_anim_engine_module_tabs`** to add an
   "Interactions" sub-tab to Theme Settings → Animations (master `animation_hover.enable`).

## Effects (where the work happens)

| Effect | Runtime | Notes |
|--------|---------|-------|
| `magnetic` | JS | translate toward cursor by `strength`; resets on leave |
| `tilt` | JS | perspective rotateX/Y from pointer; optional `glare` overlay + hover scale |
| `spotlight` | JS sets `--mx/--my`, **CSS** paints | radial glow (`--hover-glow`, `--hover-glow-size`); best on darker elements (mix-blend screen) |
| `image_reveal` | **CSS only** | zoom / grayscale→color / shine sweep on a contained `<img>`; suits Image elements |
| `text_scramble` | JS | chars resolve from random glyphs over `duration`; replaces `textContent`, so use on text/heading/button leaves |

## Guards

`hover.js` skips pointer effects on touch (`hover:none`/`pointer:coarse`), honours
`prefers-reduced-motion` (and the engine's `respect_reduced_motion`), and skips when
`disable_on_mobile` + viewport < 768. `window.upwHoverRescan()` re-scans after dynamic
inserts.

## Pitfalls

- The wrapper helper resolves URIs via **`fw_ext('animation-engine')`** — same rename
  gotcha as the WebGL `static.php`.
- `interaction` is filled with `{effect:'none'}` on every element by the corrector;
  the wrapper filter no-ops for `none` (cheap).
- `text_scramble` overwrites `textContent` — don't apply to elements with rich inner
  HTML (it flattens to text).
