# Physics Effects module

A per-element physics motion added via any element's **Animations** tab (mirrors the Hover
module). Self-contained vanilla JS — a tiny spring/verlet integrator, no library.

## Effects (picker id `effect`) — 27, grouped
- **Drag:** `draggable` (spring back / glide-to-stop, axis-lockable), `slingshot` (drag & fling
  with overshoot).
- **Pointer** (skipped on touch): `spring` (lean), `attract` (follow), `repel` (push away),
  `orbit_cursor` (circle the cursor), `rubber_band` (stretch toward + snap), `tilt_inertia`
  (3D tilt with spring — uses inline `perspective()`).
- **Ambient** (continuous, observed): `float`, `levitate`, `sway`, `pendulum`, `wobble`,
  `breathing` (scale pulse), `drift` (Brownian wander), `orbit` (circle a point).
- **Entrance** (one-shot on scroll-into-view): `gravity` (drop+bounce), `rise`, `sag`,
  `ragdoll` (drop+tumble), `pop` (scale overshoot).
- **Container:** `bounded` (bounces around the parent, DVD-style).
- **Reaction** (hover/click trigger): `jelly`, `squash`, `recoil`, `shake`, `spin` (flick-to-spin).

Each effect's options live under `physics.choices[<fx>]`; **physics.php stamps every scalar option
as `data-phys-<key-with-dashes>`** (so the emit stays compact) and the JS reads the ones it needs
via `num(el, '<key>', dflt)` / `getAttribute`. Swatches are animated SMIL SVGs with the effect
**name baked in** (`viewBox 0 0 100 118`, `<text>` label at the bottom).

## Wiring (mirrors hover.php)
- `physics.php` → `sc_animation_fields` adds the `physics` popover multi-picker; `sc_build_wrapper_attr`
  (priority 22) stamps `data-phys` + `data-phys-*` attrs + the `sc-phys sc-phys--<fx>` classes;
  `sc_needs_wrapper` forces a wrapper on leaf shortcodes; `wp_footer` enqueues the runtime **only**
  when a flag was set (an effect actually rendered). Global on/off → Theme Settings → Animations →
  Physics (`animation_physics`).
- `static/js/physics.js` — one shared RAF ticker drives all active elements; continuous effects
  (float/pendulum) pause off-screen (IntersectionObserver) + on tab-hide.

## Guards
Reduced motion → **all** effects skipped (every one is motion). `disable_on_mobile` honoured.
Pointer-following effects (`spring`, `repel`) skipped on touch. mtime cache-busting on the enqueue.

## Notes
- The runtime overwrites the element's `transform` each frame, so a physics effect and an entrance
  transform on the SAME element don't compose — physics wins. Different elements are fine.
- Swatches are animated SMIL SVGs (`static/img/effects/*.svg`), previewed larger on tile hover.
