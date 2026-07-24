# Animation Engine — inventory of what already exists

**Purpose:** the single source of truth for every capability the engine already ships — its
**modules** (each an effect family added to elements/sections/site), their **styles/effects**, and
its **shortcodes** (page-builder elements). **Before proposing or building a new module, effect, or
shortcode, check this list first: a new one must be genuinely DISTINCT from everything here** (not a
rename or a near-duplicate of an existing style). Keep this file updated when styles/modules are
added or removed.

_Engine version at last update: 1.1.83. (Since 1.1.57: **Confetti** module added — 6 burst styles.
**Trigger unification** — Entrance, Confetti and the applicable Text Effects + SVG Draw share one
image-picker **Trigger** control (`view`/`load`/`click`/`hover`, multi-select where events combine;
the label shows as the tile's hover title). **Accessibility pass** — hover effects fire on keyboard
focus (`:hover` → `:is(:hover, :focus-visible)`), reduced-motion guards added where missing, and
draggable/slingshot **Physics** is keyboard-operable (arrow keys move, Escape resets). **SVG Draw**
gained a scroll-**scrub** trigger. The "Add Animation" inserter was regrouped into four behavior
buckets — **Entrance / Scroll / Interaction / Ambient** — with self-describing tab descriptions;
the Category column below reflects those buckets.)_

---

## Modules (effect families)

Modules attach to elements via the **Animations** tab (the "Add Animation" inserter), to Sections,
or apply site-wide. Category = the inserter tab it lives under. Each is on-demand: a page ships only
the styles it uses.

### Per-element (Animations tab)

| Module | Category | Count | Styles / effects |
| --- | --- | --- | --- |
| **hover** (Hover Interactions) | Interaction | 43 | magnetic *(pull / push)* · tilt *(+ invert, glare)* · spotlight *(glow / gradient-tint)* · image_reveal *(zoom / grayscale / duotone / blur / shine)* · text_scramble · glow_border *(steady / pulse)* · underline_grow *(under / over / through)* · ripple *(pointer / center)* · lift *(lift / tilt / sink)* · color_shift *(bg / text / border)* · scale *(in / out)* · push *(press / into-screen)* · jelly · skew *(X / Y / both)* · shine *(sheen / holographic)* · gradient_border · corner_brackets *(pop / draw)* · fill_sweep *(L / R / B / center / diagonal)* · border_draw *(corner / center-out)* · glitch *(rgb / slice / jitter)* · text_swap *(slide / fade / flip)* · rotate *(2D / 3D-flip)* · pulse *(scale / glow / opacity)* · shake *(horizontal / vertical / rotate)* · bounce *(up / drop / squash)* · grayscale *(grayscale / sepia / invert / hue / saturate)* · blur · brightness *(brightness / contrast / saturation)* · bg_pan *(+ angle)* · outline *(solid / dashed / double)* · letter_spacing · goo · squash · arrow_slide · depth_layers · marching_ants · flashlight · cursor_trail · magnetic_letters · shockwave · peel · blob — many effects carry **sub-styles** (variants) shown in parens; picker tiles are sorted A–Z |
| **physics** (Physics Effects) | Interaction | 27 | draggable · slingshot · spring · attract · repel · orbit_cursor · rubber_band · tilt_inertia · float · levitate · sway · pendulum · wobble · breathing · drift · orbit · gravity · rise · sag · ragdoll · pop · bounded · jelly · squash · recoil · shake · spin |
| **text-effects** (Text Effects) | Entrance | 37 | split_reveal · scramble · typewriter · shimmer · wave · glitch · vf_weight · blur · mask · flip3d · scale · slide · bounce · random · skew · gradient_flow · rainbow · neon · breathing · jitter · float · marker · strikebox · outline_fill · chromatic · width_sweep · rotating_words · countup · splitflap · matrix · fill_sweep · letter_jump · expand_spacing · color_wave · magnetic · image_mask · kinetic |
| **scroll-motion** (Scroll Motion, GSAP) | Scroll | 19 | reveal *(subtle / standard / dramatic / bounce / elastic styles; 4 cardinals + 4 diagonals)* · stagger · splittext *(per-piece: slide / flip3d / scale / blur / rotate / random)* · parallax *(+ rotate/scale motion, + fade)* · pin *(+ anticipate, + edge-fade)* · scrub *(fade / scale / rotate / slide / blur / skew)* · zoom *(in / out)* · rotate · blur · clip *(up/down/left/right / iris / diagonal / rounded)* · skew *(axis X/Y)* · flip *(3D X/Y hinge)* · expand *(scaleX/Y + origin)* · counter *(count / **odometer** roll; start-from, prefix/suffix)* · **velocity_skew** *(leans by scroll speed, springs back)* · **tilt_scrub** *(3D perspective tilt on scroll)* · **scroll_spin** *(scrubbed rotation)* · **mask_wipe** *(feathered gradient reveal)* · **color_scrub** *(text/bg colour tween on scroll)*. GSAP 3.13 + ScrollTrigger; picker tiles sorted A–Z. **Advanced tier (v1.2.38)** — reveal · stagger · parallax · pin · scrub each expose an inline **Advanced** picker (Default / Custom…) with **Easing** (curated GSAP eases + a regex-validated custom string), **Scrub smoothing** (scrub-family only: `scrub:true` → `scrub:<s>`) and **Debug markers** (stamped only for users who can `edit_theme_options`). Every field is a no-op at Default, so legacy saves render byte-identical. Option `help` text carries "Under the hood:" notes naming the real GSAP property — the engine doubles as a way to learn GSAP. **"Show generated GSAP" panel (v1.2.39)** — a read-only `gsap-code-preview` option type inside each of those 5 effect groups prints the actual `gsap.from(el,{…})` / `ScrollTrigger.create({…})` the current settings generate, updating live as you change options (reads sibling values via the `fwEvents` bus, exactly like `gallery-3d-preview`; value-less, can't affect saves). It's the teaching rung — read the code your clicks produce, then copy it into a Motion Snippet. **Motion Snippet (v1.2.41)** — a "Custom Code" effect in the Scroll Effect picker: write your own GSAP for the element in a CodeMirror JS field. The runtime hands you `el` (the element), `tl` (a fresh timeline already tied to a ScrollTrigger) and `gsap`, running your code as `new Function('el','tl','gsap', <code>)`. It's the lossless escape hatch (styling→Custom CSS ⇒ motion→Motion Snippet). **Security = execution gate:** the code is base64-baked into post_content as `data-upw-snip` (survives like every other data-attr), but only EXECUTES when a per-request footer flag (`window.upwSnippetsOK`) is set — emitted solely when the page author has `unfiltered_html`. So a lower-privilege author's baked code never runs; kses also strips it from their saves (defense in depth). |
| **scroll-reveal** (Scroll Reveal / clip wipe) | Scroll | 6 | left · right · up · down · iris · diagonal (clip-path wipe on scroll-in) |
| **motion-path** (Motion Path) | Scroll | 37 | Path shapes: wave · arc · loop · s_curve · zigzag · spiral · circle · incline · figure8 · double_loop · knot · triangle · square · diamond · pentagon · hexagon · octagon · star · stairs · steps_down · l_corner · chevron · lightning · u_turn · bounce · pendulum · helix · corkscrew · swoosh · comet · ricochet · heart · teardrop · petal · ribbon · line · drift (+ custom SVG `d`). The element **travels the path** — drive by scroll (scrubbed) / loop / on-view; options: path size, start offset, direction, easing, **align to path** (rotate to tangent) |
| **scroll-text-highlight** (Scroll Text Highlight) | Scroll | 4 | fill · fade · blur · marker — word/char scrolly-telling reveal (each word lights up as it scrolls through the viewport) |
| **parallax** (Parallax Depth Layers) | Scroll | 1 | Single behavior — mark a Scene, give each child a **Depth**; pointer/scroll multi-layer depth |
| **marquee** (Marquee) | Ambient | 1 | Single behavior — seamless ticker; directions left/right/up/down + **curve/arc** path; options: speed, drag, fade, curve; **Text orientation** for up/down (horizontal lines / vertical-sideways / vertical-upright) |
| **flip-card** (3D Flip Card) | Interaction | 7 | Flip styles: flip · cube · fold · door · diagonal · pop · carousel — each Horizontal/Vertical, trigger hover/click/scroll/auto; back face (bg / color / image / heading / text / button, align) |
| **confetti** (Confetti) | Ambient | 26 | Classic/flat: confetti · stars · fireworks · streamers · hearts · snow. Realistic & Foil (3D tumbling paper — sheen + shadow, for photo backgrounds): realistic · foil_gold · foil_silver · rose_gold · holographic · triangles · hexagons · money · serpentine. Nature: sakura · autumn_leaves · realistic_snow · rain. Glow (additive): glitter · bokeh · fairy_dust · fireflies · embers · champagne · bubbles. — Canvas 2D burst fired on a trigger (view/click/load/hover, multi-select); options: count, spread, power, lifetime, palette *(brand / rainbow / gold / pastel / mono / silver / natural)*, replay-on-scroll. One shared full-viewport canvas + ONE on-demand runtime for every style, no library |
| **backgrounds** (Animated Backgrounds) *(Styling tab, Sections/rows)* | Ambient | 35 | aurora · gradient · dots · particles · constellation · waves · starfield · noise · mesh · grid · orbs · conic · scanlines · rays · snow · confetti · bubbles · fireflies · bokeh · rain · shapes · meteors · pgrid · hexgrid · topo · circuit · halftone · blobs · ripple · flow · matrix · nebula · borealis · orbits · spotlight |

### Section-level (a Section's Animations tab)

| Module | Count | Styles |
| --- | --- | --- |
| **motion-sequence** (Motion Sequence; Section-only) | 1 | Single behavior — plays the Section's descendant Scroll-Motion **Reveal/Stagger** children as ONE `gsap.timeline()` in document order (choreography without code), instead of each firing independently. Options: trigger (on-view / scrub-with-pin), **Overlap** between steps, start point, run-on-mobile. Reuses the Scroll Motion runtime (`compound()` config builder) — no new asset; claims its steps so they don't self-trigger. Other effects in the Section stay standalone. |
| **sticky-stack** (Card Stack; Section-only, cards = its columns) | 11 | stack · scale_fade · fade · blur · tilt · fan · messy · side · peel · push · grow |
| **horizontal-scroll** (Horizontal Scroll Section) | 15 | standard · reverse · snap · parallax · fade · coverflow · blur · grow · arc · wave · zigzag · rotate3d · wall · skew · drag |
| **scroll-loop** (Infinite / Seamless Scroll Loop, Lenis) | 1 | Single behavior — turns a run of full-height sections into a never-ending, snapping scroll |
| **scroll-color-shift** (Scroll Color Shift) | 1 | Single behavior — give each Section a target colour; the page background morphs section-to-section as you scroll |
| **scrollytelling** (Scrollytelling / Pinned Steps) | 29 | crossfade · slide · zoom · clip_wipe · blur · ken_burns · parallax · pixelate · push · cover · curtain · split · flip · cube · tilt · iris · barn · blinds · dissolve · glitch · flash · duotone · zoom_blur · page_turn · scan · color_shift · frame_sequence · horizontal_track · liquid (directional styles carry an up/down/left/right Direction option). **Two layouts:** *Media Panel + Steps* (classic — one column pins as media, the other's steps scroll) and *Full-screen Stage* (v1.2.28 — EVERY column becomes a full-viewport SCENE played in order, with an optional **Backdrop** scrubbed by story progress: uploaded frame set (Media-Library, user-replaceable) / numbered image-sequence pattern (advanced) / video / fixed image, plus a Scene-length pacing knob; consecutive scene buttons auto-group into a side-by-side CTA row; with a backdrop set, scene copy defaults to a legible light treatment — white + shadow + a low top/bottom scrim — overridable per element). **Exit hand-off (v1.2.42):** `exit:fade` fades the pinned stage to 0 over the last part of the scroll (from `exit_at`%), revealing the Section background so the ride dissolves into the next section instead of hard-cutting (set the Section bg to the following section's colour for a seamless flow). The Stage layout is the authoring model for cinematic "camera-ride" launch pages — scene transitions reuse the same 29 styles |

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
| **3D Gallery** | `[gallery_3d]` | Animated 3D image showcase — a **categorized popover Design picker** (tabs: 3D & Perspective, Carousel & Flow, Grid, Isometric, Orbit, Reveal & Wipe, Spotlight & Focus, Stack & Scatter) selects the layout, each design reveals its own controls. Shared: **card Source** (Media Library images, or a post type's featured images — Portfolio-aware via dynamic public-type list, cards stay fresh as you publish), **On Card Click** (lightbox · **Open Link** — a post's own page with the Post Type source, or the image's Media-Library "Link URL" field; external URLs auto new-tab), per-card Box Style + shadow, captions, shared lightbox, stage height/background, corner radius/padding, card ratio, **Use as Section Background** (fill the parent Section behind its content, via sc_section_background_field). Pure CSS 3D + one rAF driver, no library; reduced-motion aware; **live in-modal Design preview** (placeholder cards driven by the real runtime — the front end renders your images); self-contained image normalization (reuses only the Gallery lightbox) | designs: **carousel_ring** (rotating ring — base motion auto/scroll/static + optional **Drag to spin** (grab to hand-spin over any motion, with Drag Momentum), loop speed, direction, tilt, ring size, spacing, perspective, back-fade) · **panorama_wall** (curved scrolling multi-row wall — base motion continuous/scroll/static + optional **Drag to spin**, rows, curvature concave↔convex, tilt, gap, edge-fade, direction incl. alternate rows) — a flat **film-strip / marquee pan** is just `rows:1, curvature:0` (a single horizontal scrolling row; frame the cards with a box preset for the film look) · **card_sphere** (cards wrapped on a spinning sphere / disco ball — base motion continuous/scroll/static + optional **Drag to spin**, globe size, latitude bands, gap, back-fade, tilt) · **orbit_globe** (cards distributed through a sphere VOLUME, each billboarded/facing the camera at varying depths — a depth-of-field orbit vs card_sphere's surface bands; base motion continuous/scroll/static + optional **Drag to spin**, globe size, card size/density, gap, back-fade, tilt). · **photo_scatter** (Stack & Scatter — photos scattered flat on a tabletop, the "desk" look: jittered-grid seeded positions, random rotation/size within Rotation Range + Size Variance, glide-in from a chosen edge with stagger, dwell, sweep-out and the next set slides in; Shuffle auto (dwell + hover-pause) / on click / off; exit style **sweep/gather/fade** (gather = converge into a centre pile, the poly "collect" moment); Spread gathers or fills the stage; pool server-rendered so links/captions/lightbox ride each card, cap 60). More planned: card_tunnel · iso_cascade … |
| **Image Sequence** | `[image_sequence]` | Scroll-scrubbed frame sequence — the "product-reveal" effect; upload frames or a numbered URL pattern; pin full-screen or play as it passes | — |
| **SVG Draw** | `[svg_draw]` | Self-drawing SVG — line art / signature / animated divider or icon that traces itself on scroll; paste code, upload a file, or pick a preset | presets: arrow · check · circle · heart · signature · star · underline · wave |
| **SVG Morph** | `[svg_morph]` | An SVG shape that **morphs into another** — samples each shape into N points + cyclically aligns them (no twist), then interpolates. Fill or outline; trigger loop / hover / on-view / click. Distinct from SVG Draw (traces a fixed outline) | one-click sets: blob (liquid loop) · geometric (circle→square→triangle→hexagon→star) · circle↔square · heart↔star · blob↔circle · star↔hexagon · droplet↔circle. OR **Build your own** — an addable list of shapes, each picked from a 12-shape **library** (image grid), an **uploaded .svg** (primary path auto-extracted + auto-fitted), or a **custom path** |

---

## Quick "is it already covered?" checklist

- **Pointer-following / cursor-reactive on an element** → hover (43) or physics pointer effects.
- **Whole-page custom cursor** → cursor (42) — do NOT add per-element cursor effects here.
- **Scroll-in entrance** → scroll-motion (GSAP) or scroll-reveal (clip) or Entrance Animation (Animate.css).
- **Kinetic / animated text** → text-effects (37).
- **Living background behind a container** → backgrounds (35).
- **Section pinned + cards move** → sticky-stack (vertical) or horizontal-scroll (sideways).
- **Page-load / route transition** → page-transitions (23).
- **Reading indicator** → scroll-progress (16).
- **3D / WebGL / media element** → webgl_object, model_viewer, image_sequence, svg_draw.
- **Repeating/looping motion** → marquee (ticker) or scroll-loop (infinite sections).
- **Celebration / particle burst on a trigger** → confetti (6 styles) — do NOT add a parallel burst effect.

If a request maps cleanly onto a row above, it already exists — extend that module's style list
rather than creating a parallel one. Only build a NEW module/shortcode when the interaction model
is genuinely different from everything here.
