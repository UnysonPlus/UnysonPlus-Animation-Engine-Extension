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

### SVG Draw (`[svg_draw]`)

A **self-drawing SVG** element under *Media Elements* — line art, a signature, an animated
divider or icon that traces itself. Source it from a **built-in preset** (signature,
underline, arrow, checkmark, wave, star, heart, circle), **pasted SVG code** (scripts /
event handlers stripped), or an **uploaded .svg** (inlined so its paths can animate). The
runtime measures every path and animates `stroke-dashoffset` on the chosen trigger
(**scroll into view / on load / on hover**), with per-path **stagger**, **duration**,
**reverse**, **loop**, stroke width + preset colour, and an optional **fill-after** fade.
Self-contained vanilla JS; reduced-motion shows the art fully drawn.

### Scroll Motion (GSAP)

Scroll-driven motion added to any element via its *Animations* tab — **Reveal**,
**Stagger**, **Split Text**, **Parallax**, **Pin**, **Scrub**, plus entrance variants
**Zoom In / Rotate In / Blur In / Clip Wipe / Skew Settle** — powered by GSAP +
ScrollTrigger (bundled). Chosen from an animated-SVG image picker; GSAP + the runtime
load only on pages that use an effect. (Moved here from core — Scroll Motion is a
growing platform, so it belongs with the opt-in engine rather than the lightweight core.)

### Cursor (site-wide)

A custom cursor with **42 styles** — dot · ring · dot+trailing-ring · crosshair · brackets ·
square · dashed · glow · gradient · blob · spotlight · comet · particle trail · elastic ring ·
glass lens · directional arrow · radar pulse · plus · sparkle · diamond · dual ring · bullseye ·
camera reticle · invert disc · afterimage · firefly · confetti · bubbles · spring dot · motion
streak · rubber band · gooey metaball · contextual label · sticky cursor · word trail · image
reveal · magnify lens · ink brush · fluid smear · ripple trail · custom image · glyph/emoji —
chosen from an image grid, each revealing its own options (trail, density, stretchiness, lens
radius/blur, label text, reveal image, zoom, brush width …) in a popover, plus modifiers that
work with any style: **grow on hover**, **magnetic snap**, **difference blend**, **click
ripple**, **click burst**, **hide native cursor**. The **Contextual Label** and **Sticky
Cursor** styles read an optional **`data-cursor-label="…"`** attribute on any element (with a
configurable fallback). Configured in **Theme Settings → Animations → Cursor** (site-wide, not
per-element); the runtime loads on the front end **only when enabled**. Skips touch screens;
honours reduced motion. Cursor color uses the theme color-preset selector.

### Hover Interactions

A pointer-driven hover effect addable to any element via its *Animations* tab —
**Magnetic**, **3D Tilt**, **Spotlight**, **Image Reveal**, **Text Scramble**, **Glow
Border**, **Underline Grow**, **Ripple**, **Lift**, **Color Shift** — chosen from an
animated-SVG image picker. The runtime (JS/CSS) is enqueued only on pages that actually
use an effect.

### Animated Backgrounds

**35 animated backgrounds** addable to any **container** (section / bleed-section /
masonry-section / row) via its *Styling* tab — the option only appears on containers,
never on text/leaf elements. Chosen from an animated-SVG image picker (popover):

- **Gradient / color:** Aurora, Gradient, Mesh Gradient, Conic, Glow Orbs, Dot Grid, Grid
  Lines, Scanlines, Light Rays.
- **Particles:** Particles, Constellation, Snow / Petals / Embers / Ash, Confetti, Bubbles,
  Fireflies, Bokeh, Rain, Floating Shapes, Shooting Stars.
- **Waves / fluid:** Waves, Metaballs, Ripple, Flow Field, Nebula, Aurora Borealis.
- **Structural:** Perspective Grid, Hex Grid, Topographic, Circuit Board, Halftone, Orbits.
- **Space / ambient:** Starfield, Matrix Rain, Grain, Cursor Spotlight.

The runtime injects a layer **behind** the container's content (content is lifted above it),
with palette-preset colors and per-effect options (density, speed, colors, amplitude …).
**Self-contained vanilla JS + canvas.** Loops **pause when the section is off-screen or the
tab is hidden**, reduced-motion draws a single static frame, and the runtime loads only on
pages that use a background. Global on/off lives in **Theme Settings → Animations → Backgrounds**.

### Text Effects

**37 typographic animations** addable to any element's text via its *Animations* tab,
chosen from an animated-SVG image picker (popover), each with its own options:

- **Reveal:** Split Reveal, Blur, Mask, Flip 3D, Scale Pop, Slide, Bounce In, Random Order,
  Skew — by characters / words / lines, with stagger, duration and view/load triggers.
- **Type / decode:** Typewriter (caret + loop), Scramble, Split-Flap, Matrix Decode,
  Rotating Words, Count Up (numeric odometer).
- **Continuous:** Shimmer, Gradient Flow, Rainbow, Neon Flicker, Wave, Breathing, Jitter,
  Float, Chromatic.
- **Emphasis / hover:** Glitch, Weight Sweep, Width Sweep, Marker Highlight, Strike / Box,
  Outline → Fill, Fill Sweep, Letter Jump, Expand Spacing, Color Wave.
- **Interactive / media:** Magnetic Letters, Image Mask, Kinetic Scroll.

**Self-contained vanilla JS — no GSAP.** Reveal/decode effects trigger on scroll-into-view
or on load; hover effects trigger on hover or in view. Waits for `document.fonts.ready` so
line-splitting is accurate. Colors use the theme color-preset selector, reduced-motion
leaves text exactly as authored, and the runtime (JS/CSS) is enqueued only on pages that
actually use an effect. Global on/off lives in **Theme Settings → Animations → Text**.

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
