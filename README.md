# UnysonPlus Animation Engine

The home for UnysonPlus's animation capabilities, organised as **modules**. It ships
**bundled with the UnysonPlus plugin** but is **inactive by default** — activate it under
**UnysonPlus → Extensions** (it is not downloaded or enabled automatically, so a minimalist
site never pays for it until the user opts in). This repo is the extension's source /
update repo; the plugin bundles a copy at `framework/extensions/animation-engine/`.

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

### Model Viewer (`[model_viewer]`)

An interactive **3D model** element (glTF / GLB) under *Media Elements* — the visitor can
**orbit, zoom and inspect** a real model, with **auto-rotate** (speed + resume delay),
**image-based lighting** (Neutral / Legacy / custom HDR / none) + exposure, a soft **ground
shadow**, a **poster** placeholder while it streams in, embedded-**animation** playback, a
starting camera angle / field-of-view, camera **limits** (pan / zoom / orbit), **tone mapping**
+ **skybox**, a **material / color variant switcher** (for models with `KHR_materials_variants`),
pinned **hotspots** (label / detail / link callouts), an optional solid background, and optional
**AR** ("View in your space" on supporting phones). Built on Google's self-contained
**`<model-viewer>`** web component (vendored UMD 3.5.0 — it bundles its own Three.js, since
r0.149's UMD `GLTFLoader` global was dropped upstream). Loads only on pages that use it;
strips auto-rotate under reduced motion; falls back to the poster when 3D isn't supported.
Paste a **Model URL** (`.glb` recommended) — WordPress blocks `.glb` uploads by default, so a
URL is the reliable source.

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

### Physics Effects

**27 physics-driven motions** addable to any element via its *Animations* tab, chosen from an
animated-SVG image picker (popover, with the effect name on each tile), grouped:

- **Drag** — Draggable (grab & throw, spring back or glide to a stop), Slingshot.
- **Pointer** — Spring (lean toward cursor), Attract, Repel, Orbit Cursor, Rubber Band, Inertia Tilt.
- **Ambient** — Float, Levitate, Wind Sway, Pendulum, Wobble, Breathing, Drift, Orbit Point.
- **Entrance** — Gravity Drop, Gravity Rise, Weight Sag, Ragdoll, Pop In.
- **Container** — Bounce Box (bounces around its parent).
- **Reaction** — Jelly, Squash & Stretch, Recoil, Shake, Momentum Spin.

A tiny built-in **spring/verlet integrator — no library**. One shared render loop drives every
element; continuous effects pause off-screen and when the tab is hidden; reduced motion skips them
all and pointer-following effects are skipped on touch. Loads only on pages that use an effect.
Global on/off lives in **Theme Settings → Animations → Physics**.

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

### Page Transitions (site-wide)

Smooth transitions between pages. A full-screen overlay injected at `wp_body_open` **reveals
each page on load** (pure CSS, so it runs even without JS) and **covers it when you navigate**
to another internal page, then the destination reveals — so multi-page navigation feels
connected. **23 transitions**, chosen from an image-picker with per-transition options
(direction / origin / split / axis / count / density): Fade, Slide, Zoom, Rotate, Curtain,
Doors, Split, Wipe, Diagonal, Bars, Stripes, Blinds, Circle Reveal, Shape Reveal, Iris,
Glitch, Flip 3D, Checkerboard, Pixel Dissolve, Ripple (from click), Conic Wipe, Morph Blob
and Content Fade-Up — with an overlay colour (palette preset) + duration. An optional
**first-visit loader** (spinner / bar / dots) shows until the first page finishes loading.
Skips new-tab /
download / hash / external / modified clicks (and any link with `data-no-transition`); honours
reduced motion (normal navigation); a safety timeout always completes the navigation.
Enabled in **Theme Settings → Animations → Page Transitions** (off by default).

### Shared guards

Viewport-only render loop, pause when the tab is hidden, `prefers-reduced-motion` →
static frame / skipped, pointer-only effects skipped on touch, poster / CSS-gradient
fallback when WebGL is unavailable, DPR + FPS caps, and full teardown when removed from
the DOM. Hover/WebGL honour the engine's global *Respect reduce motion* /
*Disable on mobile* settings.

## Requirements

- UnysonPlus (with the **Shortcodes** and **Page Builder** extensions active).

## Layout

The repo root **is** the extension folder — it's copied verbatim to
`framework/extensions/animation-engine/` inside the plugin, so the slug is always
**`animation-engine`** (the plugin folder name is fixed).

```
<repo root> = framework/extensions/animation-engine/   ← slug "animation-engine"
├─ manifest.php                            ← requires shortcodes + page-builder
├─ class-fw-extension-animation-engine.php ← loads the modules
├─ includes/theme-settings.php             ← Theme Settings → Animations section
├─ includes/glb-upload.php                 ← allows .glb/.gltf Media uploads (for [model_viewer])
├─ modules/scroll-motion/                  ← Scroll Motion module (GSAP + ScrollTrigger)
├─ modules/cursor/                         ← Cursor module (site-wide custom cursor)
├─ modules/hover/                          ← Hover Interactions module
├─ modules/physics/                        ← Physics Effects module (per-element)
├─ shortcodes/webgl-object/                ← the [webgl_object] leaf shortcode
└─ shortcodes/model-viewer/                ← the [model_viewer] leaf shortcode (<model-viewer>)
```

(Earlier releases used the slug `webgl`, and this repo previously nested the extension
under an inner `animation-engine/` folder for standalone zip installs; now that it ships
bundled with the plugin, the repo root is the extension root — matching the other
`UnysonPlus-<Name>-Extension` repos.)

## Three.js

Vendored as **r0.149 UMD** (`build/three.min.js`, exposes `window.THREE`) — the last
release line that ships the UMD global while already supporting
`iridescence`/`transmission` on `MeshPhysicalMaterial`.
