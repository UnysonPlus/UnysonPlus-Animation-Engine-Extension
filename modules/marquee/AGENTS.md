# Marquee module

A seamless, never-ending ticker added via any element's **Animations** tab (mirrors Hover /
Physics / Parallax). Pure CSS animation — no library.

## Control (picker id `mode`)
Inline (non-popover) multi-picker, picker is a **select**:
- **none** — off (default).
- **left / right / up / down** — scroll direction. Each reveals the same options:
  `speed` (slow / normal / fast), `gap` (px between repeats), `separator` (optional text between
  repeats, e.g. `•`), `pause_on_hover` (default yes), `edge_fade` (mask the container edges).

Saved value: `[ 'mode' => 'left', 'left' => [ 'speed' => 'normal', 'gap' => 40, … ] ]`.

## How it stays seamless (marquee.js)
Captures the element's content as the **unit**, repeats it until the track spans the container
("one fill"), then **duplicates that fill** and animates the track by exactly **-50%** (one fill)
on a `linear infinite` CSS animation — so the wrap is invisible for both short text and long
strips. `is-vertical` swaps to a column track + `translateY`. `reverse` handles right/down.

## Wiring (mirrors parallax.php)
- `marquee.php` → `sc_animation_fields` adds the control; `sc_build_wrapper_attr` (priority 23)
  stamps `sc-marquee sc-marquee--<dir>` + `data-mq-*`; `sc_needs_wrapper` forces a wrapper;
  `wp_footer` enqueues the runtime only when used. Registers an `animation_marquee` enable tab,
  which the central `effects-control.php` folds into the shared **Effects** tab.
- `static/css/marquee.css` — the clip/track live under `.sc-mq-live` (added by JS only when it
  animates), so reduced-motion content shows normally, unclipped.

## Guards
Reduced motion + `disable_on_mobile` → runtime bails (content static, unclipped). Pauses on hover.
Loads only on pages that use it; mtime cache-busting.

## Notes
- Rebuilds the element's inner DOM (wraps content in a cloned track), so it's for display content,
  not interactive widgets. Best on a heading / text block with large type, or a logo/image row.
