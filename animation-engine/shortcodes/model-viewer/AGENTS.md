# `[model_viewer]` shortcode

An interactive 3D model element (glTF / GLB) built on Google's **`<model-viewer>`** web
component. Lives under *Media Elements* in the page builder, beside `[webgl_object]`.

## Why `<model-viewer>` (not the vendored Three.js)

The engine vendors Three.js **r0.149 UMD** for `[webgl_object]`, but r0.149's `examples/js/`
(the UMD `THREE.GLTFLoader` / `THREE.OrbitControls` globals) was **removed upstream at r148** —
only the ESM `examples/jsm/` remains, which needs an import map. Rather than mix an ESM loader
with the UMD `THREE` global, this element vendors the self-contained **`<model-viewer>` UMD
bundle** (`static/js/vendor/model-viewer-umd.min.js`, pinned **3.5.0**). It bundles its own
Three.js internally and owns the render loop, camera/orbit, IBL lighting, ground shadow, poster,
lazy-load, off-screen pausing, embedded-clip playback and AR — so our code stays thin.

## Files (mirrors the `webgl-object` shortcode shape)

- `config.php` — page-builder title/description/tab (`Media Elements`) + `title_template`.
- `options.php` — tabs: **Model** (URL / media pick / alt / poster / height), **Camera**
  (orbit, zoom, auto-rotate + speed/delay, starting angle, FOV), **Lighting** (environment
  IBL, exposure, ground shadow + softness), **Playback & AR** (embedded clips, interaction
  hint, AR), **Styling** (background + spacing), **Animations** (`sc_get_animation_fields()`),
  **Advanced** (`sc_get_advanced_tab()`).
- `static.php` — enqueues the vendored `<model-viewer>` bundle (`google-model-viewer`
  handle), the element CSS and the thin harness JS. mtime cache-busting like `webgl-object`.
  Loads **only on pages that use the shortcode** (opt-in), so the ~900 KB bundle never ships
  to pages without a model.
- `views/view.php` — `sc_model_viewer_render()`: media pick wins over the pasted URL; maps
  options → `<model-viewer>` attributes (booleans = bare presence); wrapper via
  `sc_build_wrapper_attr` (Advanced id/class + animations); height/solid-bg as CSS vars;
  background color resolved with `sc_color_to_css(..., true)` (hex).
- `static/js/model-viewer.js` — thin harness: strips `auto-rotate` under
  `prefers-reduced-motion`, drives the load-progress bar (`progress` event), and flips to the
  poster fallback on `error` / unsupported custom elements.
- `static/css/model-viewer.css` — sizes the element (`--model-h`), solid bg (`--model-bg`),
  progress bar, and the `.is-unsupported` poster fallback.

## Gotchas

- **`.glb` uploads**: WordPress blocks the `model/gltf-binary` mime by default, so the **Model
  URL** text field is the primary source; the media picker is the fallback for sites that
  allow the mime.
- **Source precedence**: `model_file` (media) wins over `model_url` (text); no `src` → renders
  nothing (empty string).
- The tag is auto-derived from the folder name: `model-viewer` → **`[model_viewer]`**.
