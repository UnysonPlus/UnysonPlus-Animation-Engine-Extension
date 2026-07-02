# Parallax Depth Layers module

Pointer/scroll-driven multi-layer depth parallax, added via any element's **Animations** tab
(mirrors the Hover / Physics modules). Self-contained vanilla JS — one shared RAF loop, no library.

## Roles (picker id `role`)
An inline (non-popover) multi-picker whose picker is a **select** with three roles:
- **none** — off (default).
- **scene** — the tracking stage. Options: `source` (mouse / scroll / both), `intensity` (px the
  deepest layers travel), `smoothing` (0–100 → pointer easing).
- **layer** — a moving element. Options: `depth` (0–100), `axis` (both / x / y), `direction`
  (with / against the pointer), `scale_far` (scale by depth), `blur_far` (depth-of-field blur).

A **Layer** finds its **Scene** via `closest('[data-pl-scene]')`; with no Scene ancestor it falls
back to a synthetic **window scene** (tracks the whole viewport), so a few depth layers work
without marking a stage.

Saved value shape: `[ 'role' => 'layer', 'layer' => [ 'depth' => 30, 'axis' => 'both', … ] ]`.

## Wiring (mirrors physics.php)
- `parallax.php` → `sc_animation_fields` adds the `parallax` control; `sc_build_wrapper_attr`
  (priority 21) stamps `sc-parallax-scene` / `sc-parallax-layer` classes + `data-pl-*` attrs;
  `sc_needs_wrapper` forces a wrapper on leaf elements with a role; `wp_footer` enqueues the runtime
  only when a role rendered. Global on/off → Theme Settings → Animations → Parallax
  (`animation_parallax`).
- `static/js/parallax.js` — scene-level pointer smoothing (each layer maps the smoothed pointer by
  its depth × intensity × direction); scroll uses the scene's viewport progress. Off-screen layers
  are culled (IntersectionObserver); the loop pauses on tab-hide.
- `static/css/parallax.css` — `.sc-parallax-scene{position:relative}` (anchor for absolutely-placed
  layers) + `will-change` hint.

## Guards
Reduced motion + `disable_on_mobile` skip everything (layers stay put). The **pointer** source is
skipped on touch (a scene set to *scroll* still moves). mtime cache-busting on the enqueue.

## Notes
- The runtime overwrites each layer's inline `transform` every frame, so a parallax layer and an
  entrance/physics transform on the SAME element don't compose. Different elements are fine.
- Works on normal-flow children (subtle drift) or absolutely-positioned layers inside a
  `position:relative` scene (overlapping hero scenes).
