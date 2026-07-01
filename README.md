# UnysonPlus Animation Engine

A standalone **UnysonPlus extension** that is the home for UnysonPlus's animation
capabilities, organised as **modules**. It is **not** part of the UnysonPlus plugin —
install it from **UnysonPlus → Extensions → Install Extension** (upload this repo's
`.zip`, or paste this repo's GitHub URL), then activate it.

When active it lights up two things:

1. A **WebGL Object** element under *Media Elements* in the page builder
   (`[webgl_object]`) — the engine's first module.
2. **Scroll Motion (GSAP)** + **Hover Interaction** groups on **every** element's
   *Animations* tab, plus an **Animations** section in *Appearance → Theme Settings*
   for global options.

Deactivating the extension removes them; placed `[webgl_object]` instances then render
empty (by design). The Animate.css **Entrance** animation stays in core (lightweight,
always available) — only the heavier, growing GSAP/WebGL/Hover live here, so a minimalist
site never ships them.

## Modules

### WebGL (`[webgl_object]`)

Real-time Three.js, in two families:

- **3D Objects** — **Glass Blob** (refractive `MeshPhysicalMaterial` transmission),
  **Liquid Metal**, **Distorted Sphere**, **Particle Field**. Procedural env-map
  reflections + GPU-side noise displacement.
- **Full-screen Shaders** (fragment shader on a quad) — **Gradient Mesh**, **Plasma**,
  **Aurora**, **Fluid** (pointer-reactive), **Dot Matrix / Halftone**, **Image
  Distortion**.

Pointer + optional scroll reaction; **Placement** is inline or *Section background*.

### Scroll Motion (GSAP)

Scroll-driven motion added to any element via its *Animations* tab — **Reveal**,
**Stagger**, **Split Text**, **Parallax**, **Pin**, **Scrub**, plus entrance variants
**Zoom In / Rotate In / Blur In / Clip Wipe / Skew Settle** — powered by GSAP +
ScrollTrigger (bundled). Chosen from an animated-SVG image picker; GSAP + the runtime
load only on pages that use an effect. (Moved here from core — Scroll Motion is a
growing platform, so it belongs with the opt-in engine rather than the lightweight core.)

### Cursor (site-wide)

A custom cursor with **14 styles** — dot · ring · dot+trailing-ring · crosshair ·
brackets · square · dashed · glow · gradient · blob · spotlight · comet · custom image ·
glyph/emoji — chosen from an image grid, plus modifiers: **grow on hover**, **magnetic
snap**, **difference blend**, **hide native cursor**. Configured in **Theme Settings →
Animations → Cursor** (site-wide, not per-element); the runtime loads on the front end
**only when enabled**, and only the chosen style's code runs. Skips touch screens;
honours reduced motion. Cursor color uses the theme color-preset selector.

### Hover Interactions

A pointer-driven hover effect addable to any element via its *Animations* tab —
**Magnetic**, **3D Tilt**, **Spotlight**, **Image Reveal**, **Text Scramble**, **Glow
Border**, **Underline Grow**, **Ripple**, **Lift**, **Color Shift** — chosen from an
animated-SVG image picker. The runtime (JS/CSS) is enqueued only on pages that actually
use an effect.

### Shared guards

Viewport-only render loop, pause when the tab is hidden, `prefers-reduced-motion` →
static frame / skipped, pointer-only effects skipped on touch, poster / CSS-gradient
fallback when WebGL is unavailable, DPR + FPS caps, and full teardown when removed from
the DOM. Hover/WebGL honour the engine's global *Respect reduce motion* /
*Disable on mobile* settings.

## Requirements

- UnysonPlus (with the **Shortcodes** and **Page Builder** extensions active).

## Layout

```
animation-engine/                          ← the extension (slug "animation-engine")
├─ manifest.php                            ← requires shortcodes + page-builder
├─ class-fw-extension-animation-engine.php ← loads the modules
├─ includes/theme-settings.php             ← Theme Settings → Animations section
├─ modules/scroll-motion/                  ← Scroll Motion module (GSAP + ScrollTrigger)
├─ modules/cursor/                         ← Cursor module (site-wide custom cursor)
├─ modules/hover/                          ← Hover Interactions module
└─ shortcodes/webgl-object/                ← the [webgl_object] leaf shortcode
```

The installer derives the extension slug from the folder that contains `manifest.php`.
This repo nests the extension under `animation-engine/`, so installing from a GitHub
repo of any name still yields the slug **`animation-engine`**. (Earlier releases used
the slug `webgl`; the WebGL element is now the engine's first module.)

## Three.js

Vendored as **r0.149 UMD** (`build/three.min.js`, exposes `window.THREE`) — the last
release line that ships the UMD global while already supporting
`iridescence`/`transmission` on `MeshPhysicalMaterial`.
