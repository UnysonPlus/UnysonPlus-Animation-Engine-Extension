---
type: shortcode
name: webgl_object
since: animation-engine extension 1.0.0 (folder/machine name was 'webgl' through 1.0.3)
provides: leaf (Media Elements)
---

# WebGL Object

A real-time WebGL element rendered with Three.js. Two families of presets:

- **3D Objects:** refractive **Glass Blob**, **Liquid Metal**, **Distorted Sphere**,
  **Particle Field**.
- **Full-screen Shaders** (fragment shader on a quad): **Gradient Mesh**, **Plasma**,
  **Aurora**, **Fluid** (pointer-reactive), **Dot Matrix / Halftone**, **Image
  Distortion**.

It reacts to the pointer and (optionally) scroll. This is a **leaf** shortcode (like
`lottie`) — it does NOT hold inner rows and needs none of the section-like hooks.

It ships inside the **`animation-engine` extension** — WebGL is the engine's **first
module** (the extension was renamed from the standalone `webgl` extension; its machine
name, class `FW_Extension_Animation_Engine`, and `fw_ext('animation-engine')` lookups
all use `animation-engine`). The shortcodes loader auto-discovers this folder for any
active extension's `/shortcodes/` folder, so activating the extension registers
`[webgl_object]`; deactivating it unregisters the tag (saved instances then render
empty — by design).

> **Rename gotcha (this caused a blank-canvas bug):** the extension's machine name is
> baked into `static.php` (`fw_ext('animation-engine')`). When renaming the extension
> folder you MUST grep **every** file for the old machine name — not just the manifest
> / class. A stale `fw_ext('webgl')` in `static.php` returns early and silently skips
> the Three.js + JS + CSS enqueue, so the element renders nothing (no PHP/JS error).
> Run `grep -rn "fw_ext" .` across the extension after any rename.

## Registration

None in PHP. The folder name `webgl-object` → tag `webgl_object`; the loader
instantiates the base `FW_Shortcode`. No `class-fw-shortcode-*.php` is needed.

## Options schema (atts)

| Att | Type | Default | Notes |
|-----|------|---------|-------|
| `style_preset` | multi-picker | `{preset:'glass'}` | `preset` ∈ **objects** glass/metal/sphere/particles **+ shaders** gradient_mesh/plasma/aurora/fluid/dots/image_distort; reveals per-preset sub-opts |
| `style_preset.glass.ior` | slider | 1.45 | 1–2.33 |
| `style_preset.glass.iridescence` | slider | 0.3 | 0–1 |
| `style_preset.metal.metalness` | slider | 1 | 0–1 |
| `style_preset.metal.roughness` | slider | 0.15 | 0–1 |
| `style_preset.sphere.roughness` | slider | 0.6 | 0–1 |
| `style_preset.particles.particle_count` | slider | 4000 | 500–12000 |
| `style_preset.particles.particle_size` | slider | 0.02 | 0.005–0.08 |
| `style_preset.gradient_mesh.{blend_speed,grain}` | slider | 0.4, 0.15 | shader → uP1, uP2 |
| `style_preset.plasma.{scale,flow_speed,contrast}` | slider | 3, 0.5, 0.6 | shader → uP1, uP2, uP3 |
| `style_preset.aurora.{band_count,drift_speed,softness}` | slider | 3, 0.4, 0.5 | shader → uP1, uP2, uP3 |
| `style_preset.fluid.{viscosity,splat_strength}` | slider | 0.5, 0.6 | shader → uP1, uP2 |
| `style_preset.dots.{dot_style,grid_density,dot_size}` | select/slider | dot, 40, 0.5 | dot_style halftone→uP3=1 |
| `style_preset.image_distort.{image,strength,hover_only}` | upload/slider/switch | —, 0.3, yes | empty image → falls back to color gradient |
| `scale` | slider | 1 | object size 0.5–1.6 |
| `placement` | multi-picker | `{mode:'inline'}` | `mode` ∈ inline/background; **inline** reveals `height`; **background** fills the parent Section (its Min Height sizes it — no height here) |
| `placement.inline.height` | text | `520` | canvas height px (inline only) |
| `color_a` | color-picker | `#6aa6ff` | primary / object tint |
| `color_b` | color-picker | `#b388ff` | reflections + gradient bg |
| `background` | select | `transparent` | transparent/solid/gradient |
| `bg_color` | color-picker | `#0b0f1a` | used when background=solid |
| `auto_rotate` | slider | 0.3 | 0–1 |
| `noise_amount` | slider | 0.45 | surface wobble 0–1 |
| `noise_speed` | slider | 0.5 | 0–1 |
| `scroll_link` | switch | `yes` | rotate/scale on scroll |
| `pointer_follow` | switch | `yes` | |
| `pointer_strength` | slider | 0.5 | 0–1 |
| `parallax` | slider | 0.3 | 0–1 |
| `quality` | select | `auto` | auto/high/low (auto drops to low on weak GPUs) |
| `dpr_cap` | select | `2` | 1 / 1.5 / 2 |
| `poster` | upload | — | fallback image (no-WebGL / reduced-motion) |
| + standard `spacing`, Animations, Advanced tabs | | | |

Saved multi-picker shape stores **every** preset's sub-group (standard multi-picker
behaviour), with `preset` selecting the active one — e.g.
`{ preset:'glass', glass:{ior,iridescence}, metal:{…}, …, image_distort:{…} }`. A
verified full export (all 10 presets on one page) lives in
`test-sites/webgl-test-full-*.json`.

## Rendering

`views/view.php` outputs `<div class="fw-webgl fw-webgl--<preset> fw-webgl--bg-<bg>"
data-webgl="1" data-config='{…}'>` containing `.fw-webgl__canvas` and an optional
`.fw-webgl__poster`. For `image_distort`, view.php resolves the uploaded image to
`presetOpts.imageUrl`. `static/js/webgl-object.js` reads `data-config` and takes one
of **two render paths** (branch = `isShaderPreset(preset)`):

- **3D objects** (glass/metal/sphere/particles): PerspectiveCamera + lights + a
  procedural env map; `MeshPhysicalMaterial` with GPU-side simplex-noise displacement
  injected via `onBeforeCompile` (glass/metal/sphere) or `THREE.Points` (particles).
- **Full-screen shaders** (the other 6): OrthographicCamera + a `PlaneGeometry(2,2)`
  quad + a `ShaderMaterial`. Each preset's GLSL lives in the `FRAG` map (sharing the
  vendored SIMPLEX noise); named sub-options map onto generic uniforms `uP1..uP3` via
  `paramFor()`. Uniforms fed per frame: `uTime`, `uMouse` (smoothed pointer), `uScroll`
  (when scroll-linked), `uColorA/B`. `image_distort` loads the image into `uTexture`
  (falls back to a color gradient when none / not yet loaded) and, when `hover_only`,
  eases `uP1` (strength) up only while the pointer is over.

## Guards (built in)

Viewport-only render loop (IntersectionObserver) + pause on tab-hidden; one static
frame for `prefers-reduced-motion`; poster / CSS-gradient fallback when WebGL or
`THREE` is unavailable; DPR + FPS caps; `dispose()` when the node leaves the DOM.

## Pitfalls

- Three.js is the **UMD r0.149 global** (`window.THREE`) — the last line shipping
  `build/three.min.js` with `iridescence`/`transmission`. Don't bump to a build that
  drops the UMD global without switching the enqueue to ES modules.
- `static.php` runs isolated (no `$this`); it resolves URIs via
  **`fw_ext('animation-engine')`** (the extension machine name — see the Rename gotcha
  above). If this lookup fails, NOTHING enqueues and the element is silently blank.
- Shader GLSL is single-precision; keep helper names unique (e.g. the `dots` "dot"
  variable was renamed `dt` to avoid shadowing). The 6 shaders are reference-quality —
  tune constants in the `FRAG` map / `paramFor()`, not the option defaults.
- **Placement is a multi-picker** (`placement/mode`; Height is revealed only for
  `inline` — in `background` the parent Section's Min Height sizes the canvas). Note:
  the page-builder items-corrector **strips atts not in the schema and default-fills
  new ones** before `view.php` runs, so you can't read a removed att as a fallback in
  the view — if you change an option's shape, migrate the stored builder JSON instead.

## Files

- `config.php` — page-builder config (Media Elements tab)
- `options.php` — atts schema (the AI contract above)
- `static.php` — enqueues vendored three.min.js + webgl-object.js + css
- `views/view.php` — frontend HTML
- `static/js/vendor/three.min.js` — vendored Three.js r0.149 (UMD)
- `static/js/webgl-object.js` — the engine
- `static/css/webgl-object.css`, `static/img/page_builder.svg`
