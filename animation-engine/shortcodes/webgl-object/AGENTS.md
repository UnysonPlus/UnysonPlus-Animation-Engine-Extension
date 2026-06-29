---
type: shortcode
name: webgl_object
since: webgl extension 1.0.0
provides: leaf (Media Elements)
---

# WebGL Object

A real-time WebGL element rendered with Three.js — a refractive **Glass Blob**,
**Liquid Metal**, **Distorted Sphere**, or **Particle Field**. It reacts to the
pointer and (optionally) scroll. This is a **leaf** shortcode (like `lottie`) — it
does NOT hold inner rows and needs none of the section-like hooks.

It ships inside the standalone **`webgl` extension** (not the core plugin). The
shortcodes loader auto-discovers it for any active extension's `/shortcodes/`
folder, so activating the extension registers `[webgl_object]`; deactivating it
unregisters the tag (saved instances then render empty — by design).

## Registration

None in PHP. The folder name `webgl-object` → tag `webgl_object`; the loader
instantiates the base `FW_Shortcode`. No `class-fw-shortcode-*.php` is needed.

## Options schema (atts)

| Att | Type | Default | Notes |
|-----|------|---------|-------|
| `style_preset` | multi-picker | `{preset:'glass'}` | `preset` ∈ glass/metal/sphere/particles; reveals per-preset sub-opts |
| `style_preset.glass.ior` | slider | 1.45 | 1–2.33 |
| `style_preset.glass.iridescence` | slider | 0.3 | 0–1 |
| `style_preset.metal.metalness` | slider | 1 | 0–1 |
| `style_preset.metal.roughness` | slider | 0.15 | 0–1 |
| `style_preset.sphere.roughness` | slider | 0.6 | 0–1 |
| `style_preset.particles.particle_count` | slider | 4000 | 500–12000 |
| `style_preset.particles.particle_size` | slider | 0.02 | 0.005–0.08 |
| `scale` | slider | 1 | object size 0.5–1.6 |
| `height` | text | `520` | canvas height px |
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

Saved multi-picker shape: `{ preset:'glass', glass:{ior,iridescence} }`.

## Rendering

`views/view.php` outputs `<div class="fw-webgl fw-webgl--<preset> fw-webgl--bg-<bg>"
data-webgl="1" data-config='{…}'>` containing `.fw-webgl__canvas` and an optional
`.fw-webgl__poster`. `static/js/webgl-object.js` reads `data-config` and builds the
Three.js scene (`MeshPhysicalMaterial` for glass/metal/sphere with GPU-side simplex
noise displacement injected via `onBeforeCompile`; `THREE.Points` for particles;
env-map reflections generated procedurally from `color_a`/`color_b`).

## Guards (built in)

Viewport-only render loop (IntersectionObserver) + pause on tab-hidden; one static
frame for `prefers-reduced-motion`; poster / CSS-gradient fallback when WebGL or
`THREE` is unavailable; DPR + FPS caps; `dispose()` when the node leaves the DOM.

## Pitfalls

- Three.js is the **UMD r0.149 global** (`window.THREE`) — the last line shipping
  `build/three.min.js` with `iridescence`/`transmission`. Don't bump to a build that
  drops the UMD global without switching the enqueue to ES modules.
- `static.php` runs isolated (no `$this`); it resolves URIs via `fw_ext('webgl')`.

## Files

- `config.php` — page-builder config (Media Elements tab)
- `options.php` — atts schema (the AI contract above)
- `static.php` — enqueues vendored three.min.js + webgl-object.js + css
- `views/view.php` — frontend HTML
- `static/js/vendor/three.min.js` — vendored Three.js r0.149 (UMD)
- `static/js/webgl-object.js` — the engine
- `static/css/webgl-object.css`, `static/img/page_builder.svg`
