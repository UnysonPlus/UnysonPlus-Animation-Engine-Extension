---
type: engine-module
name: scroll-loop
since: animation-engine 1.0.78
provides: Seamless / Infinite Scroll Loop for a run of full-height sections (Animations tab)
---

# Seamless / Infinite Scroll Loop module

Turns a run of consecutive full-height **Sections** into a **terminal infinite
scroll loop** — the "Infinite Scroll with Parallax" look (Codrops/Tympanus),
powered by **Lenis** (`static/js/vendor/lenis/lenis.min.js`, exposes `window.Lenis`)
and **Lenis Snap** (`lenis-snap.min.js`, `window.Snap`). This module supplies only
the *loop + snapping*; the **media drift** reuses the existing Scroll Motion
**Parallax** effect on the elements inside. Loaded by the engine class `_init()`,
so it only exists when the `animation-engine` extension is active.

## Model — TERMINAL loop (important)

An infinite downward scroll can't be scrolled *past* to reach content below it, so
the loop is **terminal**: mark the sections at the **bottom** of the page. Content
**above** the first marked section (header, intro sections) scrolls normally and is
reachable by scrolling up. The marked group loops **forever downward**; a **clone of
the first section** is appended after the last so the wrap is pixel-seamless. Nothing
placed after the loop group is reachable by scrolling. (Whole-page and pinned-release
variants were considered and rejected for v1 — see the plan.)

## How it plugs in (no base-plugin code, no view.php change)

1. **Options (SECTION ONLY)** — a loop only makes sense on a full-height Section, so
   this does **not** use the context-free `sc_animation_fields` filter (which hits
   every element). Instead it hooks **`fw_shortcode_get_options`** (which passes the
   shortcode `$tag` — same mechanism as `sc_filter_styling_options`) and appends the
   control to the **section's `tab_animation`** only. The section builder item pulls
   its options via `get_shortcode('section')->get_options()`, so the filter fires for
   its modal too. The control is a **popover image-picker multi-picker** keyed
   **`scroll_loop`** (picker id `mode`, tiles `off`/`loop` under `static/img/loop/`),
   so it stays compact. The `loop` choice reveals a `group` of `snap` ·
   `snap_duration` · `run_on_mobile`. Popover rule: label on the TOP level, picker
   label `false`. Saved shape:
   `{ mode:'loop', loop:{ snap:'yes', snap_duration:0.8, run_on_mobile:'yes' } }`.
2. **Emission** — hooks **`sc_build_wrapper_attr`** (priority 26, after Scroll
   Motion's 25) to stamp `data-upw-loop="1"`, `data-upw-loop-snap="1|0"`,
   `data-upw-loop-snap-dur`, and `data-upw-loop-mobile="0"` (opt-out only). The
   Section already routes through the wrapper helper, so no `view.php` edit.
3. **Wrapper force** — hooks **`sc_needs_wrapper`** so a section whose only setting
   is the loop flag still gets a wrapper for the attrs to land on.
4. **Runtime** — `wp_footer` (priority 6, after Scroll Motion's 5) enqueues Lenis
   (+ Lenis Snap only when snapping is used) + `upw-scroll-loop.js` + the CSS, only
   when a loop section rendered (`sc_scroll_loop_flag()` / `sc_scroll_loop_snap_used()`).
   Pages without a loop ship zero of it. Inline `window.upwLoopCfg` carries the
   engine's global `respect_reduced_motion` policy.

## Runtime (`upw-scroll-loop.js`)

Collect `[data-upw-loop="1"]` (need ≥2) → clone the first (aria-hidden, loop attrs
stripped, ids removed) after the last → init Lenis → **one global bridge**
(`window.__upwLenis` guard): if `window.gsap` present, `gsap.ticker` drives
`lenis.raf` and `lenis.on('scroll', ScrollTrigger.update)` so **Parallax reads the
smoothed scroll**; else a plain rAF loop. **Wrap** (`lenis.on('scroll', …)`, ALWAYS
bound — both snap and free modes): when `lenis.scroll ≥ regionTop + cycle − 1`,
`lenis.scrollTo(scroll − cycle,{immediate,force})` (re-entry guarded). This both
drives the loop and **caps travel at the clone**, so a theme footer below the group
is never reached (the terminal contract). `cycle` = clone top − first-section top,
re-measured on load/resize. Snap on → `new Snap(lenis,{type:'mandatory',duration})`
+ `addElement` each **real** section (`align:['start']`) — the clone is NOT a snap
element (resting on it is pre-empted by the wrap); snap only adds the eased feel.

**Vendored Lenis bundles MUST be IIFE-wrapped.** The upstream `lenis.min.js` /
`lenis-snap.min.js` dumps its minified top-level names (`w`,`y`,`L`,`f`,…) into the
global scope — loading both unwrapped makes lenis-snap's `y` clobber lenis-core's
`y`, and Lenis's constructor then builds the wrong class → `offsetTop of undefined`,
so Lenis never inits. Each vendored file is wrapped in `(function(){ … })();` (the
trailing `globalThis.Lenis=`/`Snap=` still exposes the globals). Re-wrap after any
re-vendor.

## Guards / bails

In-builder (`fw-builder-active`/iframe), `prefers-reduced-motion` (when the engine's
`respect_reduced_motion` is on), `run_on_mobile` off + viewport < 768, fewer than 2
marked sections, or Lenis missing → **no clone, no Lenis, native scroll** (nothing is
ever left hidden). Single global Lenis instance (`window.__upwLenis`), so it never
double-drives ScrollTrigger alongside Scroll Motion.

## Pitfalls

- **Clone + Parallax:** `cloneNode(true)` copies `data-upw-g` but NOT the `__upwG`
  expando, so Scroll Motion's MutationObserver re-inits the clone's parallax cleanly.
  Don't add a matching expando to the clone.
- **Full height:** loop sections default to `min-height:100svh` via the module CSS
  (fallback only — an explicit inline `min-height` from the Section option wins).
- **URI resolution** uses `fw_ext('animation-engine')` — same rename gotcha as the
  other modules' static enqueues.
- **Terminal only:** don't expect content after the loop to be reachable — that's by
  design, not a bug.
