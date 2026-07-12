# Animation Engine — inventory of what already exists

**Purpose:** the single source of truth for every capability the engine already ships — its
**modules** (each an effect family added to elements/sections/site), their **styles/effects**, and
its **shortcodes** (page-builder elements). **Before proposing or building a new module, effect, or
shortcode, check this list first: a new one must be genuinely DISTINCT from everything here** (not a
rename or a near-duplicate of an existing style). Keep this file updated when styles/modules are
added or removed.

_Engine version at last update: 1.1.57. (Scroll Effect module +5 distinct effects — Velocity Skew, 3D Tilt Scrub, Scroll Spin, Mask Wipe, Color Scrub — plus Split-Text per-piece animations, Odometer counter, Zoom-out, diagonal reveals, parallax motion/fade, pin edge-fade, rounded clip.)_

---

## Modules (effect families)

Modules attach to elements via the **Animations** tab (the "Add Animation" inserter), to Sections,
or apply site-wide. Category = the inserter tab it lives under. Each is on-demand: a page ships only
the styles it uses.

### Per-element (Animations tab)

| Module | Category | Count | Styles / effects |
| --- | --- | --- | --- |
| **hover** (Hover Interactions) | Pointer | 43 | magnetic *(pull / push)* · tilt *(+ invert, glare)* · spotlight *(glow / gradient-tint)* · image_reveal *(zoom / grayscale / duotone / blur / shine)* · text_scramble · glow_border *(steady / pulse)* · underline_grow *(under / over / through)* · ripple *(pointer / center)* · lift *(lift / tilt / sink)* · color_shift *(bg / text / border)* · scale *(in / out)* · push *(press / into-screen)* · jelly · skew *(X / Y / both)* · shine *(sheen / holographic)* · gradient_border · corner_brackets *(pop / draw)* · fill_sweep *(L / R / B / center / diagonal)* · border_draw *(corner / center-out)* · glitch *(rgb / slice / jitter)* · text_swap *(slide / fade / flip)* · rotate *(2D / 3D-flip)* · pulse *(scale / glow / opacity)* · shake *(horizontal / vertical / rotate)* · bounce *(up / drop / squash)* · grayscale *(grayscale / sepia / invert / hue / saturate)* · blur · brightness *(brightness / contrast / saturation)* · bg_pan *(+ angle)* · outline *(solid / dashed / double)* · letter_spacing · goo · squash · arrow_slide · depth_layers · marching_ants · flashlight · cursor_trail · magnetic_letters · shockwave · peel · blob — many effects carry **sub-styles** (variants) shown in parens; picker tiles are sorted A–Z |
| **physics** (Physics Effects) | Physics | 27 | draggable · slingshot · spring · attract · repel · orbit_cursor · rubber_band · tilt_inertia · float · levitate · sway · pendulum · wobble · breathing · drift · orbit · gravity · rise · sag · ragdoll · pop · bounded · jelly · squash · recoil · shake · spin |
| **text-effects** (Text Effects) | Text | 37 | split_reveal · scramble · typewriter · shimmer · wave · glitch · vf_weight · blur · mask · flip3d · scale · slide · bounce · random · skew · gradient_flow · rainbow · neon · breathing · jitter · float · marker · strikebox · outline_fill · chromatic · width_sweep · rotating_words · countup · splitflap · matrix · fill_sweep · letter_jump · expand_spacing · color_wave · magnetic · image_mask · kinetic |
| **scroll-motion** (Scroll Motion, GSAP) | Scroll | 19 | reveal *(subtle / standard / dramatic / bounce / elastic styles; 4 cardinals + 4 diagonals)* · stagger · splittext *(per-piece: slide / flip3d / scale / blur / rotate / random)* · parallax *(+ rotate/scale motion, + fade)* · pin *(+ anticipate, + edge-fade)* · scrub *(fade / scale / rotate / slide / blur / skew)* · zoom *(in / out)* · rotate · blur · clip *(up/down/left/right / iris / diagonal / rounded)* · skew *(axis X/Y)* · flip *(3D X/Y hinge)* · expand *(scaleX/Y + origin)* · counter *(count / **odometer** roll; start-from, prefix/suffix)* · **velocity_skew** *(leans by scroll speed, springs back)* · **tilt_scrub** *(3D perspective tilt on scroll)* · **scroll_spin** *(scrubbed rotation)* · **mask_wipe** *(feathered gradient reveal)* · **color_scrub** *(text/bg colour tween on scroll)*. GSAP 3.13 + ScrollTrigger; picker tiles sorted A–Z. |
| **scroll-reveal** (Scroll Reveal / clip wipe) | Scroll | 6 | left · right · up · down · iris · diagonal (clip-path wipe on scroll-in) |
| **motion-path** (Motion Path) | Scroll/Motion | 37 | Path shapes: wave · arc · loop · s_curve · zigzag · spiral · circle · incline · figure8 · double_loop · knot · triangle · square · diamond · pentagon · hexagon · octagon · star · stairs · steps_down · l_corner · chevron · lightning · u_turn · bounce · pendulum · helix · corkscrew · swoosh · comet · ricochet · heart · teardrop · petal · ribbon · line · drift (+ custom SVG `d`). The element **travels the path** — drive by scroll (scrubbed) / loop / on-view; options: path size, start offset, direction, easing, **align to path** (rotate to tangent) |
| **scroll-text-highlight** (Scroll Text Highlight) | Scroll | 4 | fill · fade · blur · marker — word/char scrolly-telling reveal (each word lights up as it scrolls through the viewport) |
| **parallax** (Parallax Depth Layers) | Pointer/Scroll | 1 | Single behavior — mark a Scene, give each child a **Depth**; pointer/scroll multi-layer depth |
| **marquee** (Marquee) | Motion | 1 | Single behavior — seamless ticker; directions left/right/up/down + **curve/arc** path; options: speed, drag, fade, curve; **Text orientation** for up/down (horizontal lines / vertical-sideways / vertical-upright) |
| **flip-card** (3D Flip Card) | Pointer | 7 | Flip styles: flip · cube · fold · door · diagonal · pop · carousel — each Horizontal/Vertical, trigger hover/click/scroll/auto; back face (bg / color / image / heading / text / button, align) |
| **backgrounds** (Animated Backgrounds) *(Styling tab, Sections/rows)* | — | 35 | aurora · gradient · dots · particles · constellation · waves · starfield · noise · mesh · grid · orbs · conic · scanlines · rays · snow · confetti · bubbles · fireflies · bokeh · rain · shapes · meteors · pgrid · hexgrid · topo · circuit · halftone · blobs · ripple · flow · matrix · nebula · borealis · orbits · spotlight |

### Section-level (a Section's Animations tab)

| Module | Count | Styles |
| --- | --- | --- |
| **sticky-stack** (Sticky Card Stack) | 11 | stack · scale_fade · fade · blur · tilt · fan · messy · side · peel · push · grow |
| **horizontal-scroll** (Horizontal Scroll Section) | 15 | standard · reverse · snap · parallax · fade · coverflow · blur · grow · arc · wave · zigzag · rotate3d · wall · skew · drag |
| **scroll-loop** (Infinite / Seamless Scroll Loop, Lenis) | 1 | Single behavior — turns a run of full-height sections into a never-ending, snapping scroll |
| **scroll-color-shift** (Scroll Color Shift) | 1 | Single behavior — give each Section a target colour; the page background morphs section-to-section as you scroll |
| **scrollytelling** (Scrollytelling / Pinned Steps) | 29 | crossfade · slide · zoom · clip_wipe · blur · ken_burns · parallax · pixelate · push · cover · curtain · split · flip · cube · tilt · iris · barn · blinds · dissolve · glitch · flash · duotone · zoom_blur · page_turn · scan · color_shift · frame_sequence · horizontal_track · liquid (directional styles carry an up/down/left/right Direction option) |

### Site-wide (Theme Settings → Animations)

| Module | Count | Styles |
| --- | --- | --- |
| **cursor** (Custom Cursor) | 42 | dot · ring · dot_ring · crosshair · brackets · square · dashed · glow · gradient · blob · spotlight · comet · particles · elastic · lens · arrow · radar · plus · star · diamond · dual_ring · bullseye · reticle · invert · echo · firefly · confetti · bubble · spring · streak · rope · metaball · label · sticky · word_trail · reveal · magnify · ink · fluid · distort · custom · glyph |
| **page-transitions** (Page Transitions) | 23 | fade · slide · zoom · rotate · curtain · doors · split · wipe · diagonal · bars · stripes · blinds · reveal · shape · iris · glitch · flip · checkerboard · pixels · ripple · conic · morph · contentfade |
| **scroll-progress** (Scroll Progress indicator) | 16 | bar · gradient · glow · segments · pill · labeled · under_nav · liquid · edge · ring · ring_number · gauge · battery · counter · reading_time · dots |
| **preloader** (Preloader / Page Loader) | 16 | spinner · dual_ring · gradient · dots · dots_fade · orbit · bars · grid · pulse · ripple · square · bar · progress_ring · counter · curtain · logo — full-screen loading screen shown until the page is ready, then animated away (distinct from Page Transitions, which animate route changes). Supersedes the theme's basic preloader when the engine is active. |

**Also in Theme Settings (not a style list):** the Entrance Animation (Animate.css, ~56 effects —
lives in the **shortcodes** extension core, available even with the engine off).

---

## Shortcodes (page-builder elements bundled in the engine)

| Shortcode | Tag | What it is | Variants |
| --- | --- | --- | --- |
| **WebGL Object** | `[webgl_object]` | Real-time WebGL object (Three.js) | glass (refractive blob) · metal (liquid metal) · sphere (distorted) · particles (field) · image particles (image sampled into a cursor-scatter point cloud) · plasma |
| **Model Viewer** | `[model_viewer]` | Interactive 3D model (glTF/GLB) the visitor can orbit/zoom, with auto-rotate, IBL, ground shadow, poster, optional AR (`<model-viewer>`) | — |
| **Image Sequence** | `[image_sequence]` | Scroll-scrubbed frame sequence — the "product-reveal" effect; upload frames or a numbered URL pattern; pin full-screen or play as it passes | — |
| **SVG Draw** | `[svg_draw]` | Self-drawing SVG — line art / signature / animated divider or icon that traces itself on scroll; paste code, upload a file, or pick a preset | presets: arrow · check · circle · heart · signature · star · underline · wave |
| **SVG Morph** | `[svg_morph]` | An SVG shape that **morphs into another** — samples each shape into N points + cyclically aligns them (no twist), then interpolates. Fill or outline; trigger loop / hover / on-view / click. Distinct from SVG Draw (traces a fixed outline) | one-click sets: blob (liquid loop) · geometric (circle→square→triangle→hexagon→star) · circle↔square · heart↔star · blob↔circle · star↔hexagon · droplet↔circle. OR **Build your own** — an addable list of shapes, each picked from a 12-shape **library** (image grid), an **uploaded .svg** (primary path auto-extracted + auto-fitted), or a **custom path** |

---

## Quick "is it already covered?" checklist

- **Pointer-following / cursor-reactive on an element** → hover (31) or physics pointer effects.
- **Whole-page custom cursor** → cursor (42) — do NOT add per-element cursor effects here.
- **Scroll-in entrance** → scroll-motion (GSAP) or scroll-reveal (clip) or Entrance Animation (Animate.css).
- **Kinetic / animated text** → text-effects (37).
- **Living background behind a container** → backgrounds (35).
- **Section pinned + cards move** → sticky-stack (vertical) or horizontal-scroll (sideways).
- **Page-load / route transition** → page-transitions (23).
- **Reading indicator** → scroll-progress (16).
- **3D / WebGL / media element** → webgl_object, model_viewer, image_sequence, svg_draw.
- **Repeating/looping motion** → marquee (ticker) or scroll-loop (infinite sections).

If a request maps cleanly onto a row above, it already exists — extend that module's style list
rather than creating a parallel one. Only build a NEW module/shortcode when the interaction model
is genuinely different from everything here.
