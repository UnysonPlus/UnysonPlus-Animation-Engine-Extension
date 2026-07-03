# Animation Engine — module authoring guide

Read this **before** creating or editing an engine module. It captures the conventions that keep
every module (and its picker swatches) consistent, so we stop re-solving the same issues.

A "module" adds one effect to the per-element **Animations** tab (via the `animation-stack`
"Add Animation" inserter). Reference modules: **`hover`** (per-element, the canonical multi-style
picker) and **`scroll-loop`** / **`sticky-stack`** (Section-only).

---

## 1. Picker swatch design (the #1 source of drift — get this exact)

Each choice in a module's popover image-picker is an **SVG swatch**. Match the Hover module exactly:

- **`viewBox="0 0 132 96"`** — landscape. (NOT square. NOT 120×120.)
- **No background rect.** The swatch is transparent; the tile provides its own surface. Do **not**
  bake a pale `#f2f7ff`/`#eef2ff` fill behind the motif.
- **Motif in the top ~62px** (roughly `y` 8–66), centered around `(66, 34)`.
- **Bake the label** into the SVG at the bottom — there is no separate HTML caption:
  ```xml
  <text x="66" y="86" text-anchor="middle"
        font-family="-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif"
        font-size="15" font-weight="600" fill="#475569">Label</text>
  ```
- **Escape XML in the label** — a raw `&` (e.g. "Scale & Fade") makes the SVG invalid → a broken-image
  tile. Use `&amp;`, `&lt;`, `&gt;`.
- **Flat blue palette:** primary `#2f74e6`, mid `#7aa9ee`, light `#bcd3f7`, muted/"off" stroke
  `#c3ccd8`, label text `#475569`. Flat shapes, no gradients/shadows.
- **The "off" / "none" swatch** = a muted card outline + a diagonal slash (see any module's `off.svg`).

### Tile config in PHP (heights)
```php
$tile = function ( $file, $label ) use ( $base ) {
    return array(
        'small' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 66 ),
        'large' => array( 'src' => $base . '/' . $file . '.svg', 'height' => 132 ),
        'label' => $label, // still set it — used for the popover trigger summary + a11y
    );
};
```

### Do NOT add a per-module `admin_head` tile-size override
The global multi-picker CSS
(`framework/includes/option-types/multi-picker/static/css/multi-picker.css`) already bumps tiles to
72px in the **large** modal and Theme Settings. A per-module override forcing 96/117px makes tiles
bigger (fewer per row) **and** breaks the baked-label layout. Older modules
(`marquee`/`scroll-progress`/`physics`) still carry one — do not copy it into new modules.

### 5 tiles per row is a MEDIUM `popup_size` thing
The Section/element edit modal is **medium** `popup_size`, where the 66px landscape tiles fall 5 per
row. Larger `popup_size` fits fewer — that is expected, don't fight it.

### Multi-style modules
For a module with several styles (see `sticky-stack`, 11 styles): one **shared options group**
mapped onto every style key, plus a single **"Intensity"** slider (0–1) that the JS maps to each
style's magnitude (scale / dim / blur / tilt° / fan spread / …). One swatch per style; the baked
label is the style name.

---

## 2. Module PHP structure

```php
// upw_<mod>_enabled(): reads fw_get_db_settings_option('animation_<mod>')['enable'], DEFAULT 'yes'.
// Assets load on demand via the shared loader (see §6) — record each used style with
// upw_anim_use_asset( '<mod>', $style ) in the wrapper filter; do NOT hand-roll a wp_footer enqueue.

// FIELD — a popover multi-picker, picker id `mode`, with anim_meta for the inserter:
$fields['<mod>'] = array(
    'type' => 'multi-picker', 'popover' => true,
    'label' => __( 'Nice Name', 'fw' ), 'desc' => __( '…', 'fw' ), 'help' => __( '…', 'fw' ),
    'show_borders' => false,
    'value' => array( 'mode' => 'off' ),
    'anim_meta' => array( 'category' => __( 'Scroll', 'fw' ) ), // Entrance|Scroll|Pointer|Physics|Motion|Text
    'picker' => array( 'mode' => array( 'type' => 'image-picker', 'label' => false, 'value' => 'off', 'choices' => $tiles ) ),
    'choices' => $reveal_groups,
);
```

- **Per-element module** → append the field on the **`sc_animation_fields`** filter (like `hover`).
- **Section-only module** → hook **`fw_shortcode_get_options`** (`$tag === 'section'`) and inject the
  field **inside the organizer**, not beside it:
  `$options['tab_animation']['options']['animation_stack']['options']` (see `scroll-loop` /
  `sticky-stack`). A fallback appends flat if the container isn't present.
- **Wrapper** — on `sc_build_wrapper_attr`, if the mode is active: add classes + `data-*` attrs and
  call **`upw_anim_use_asset( '<mod>', $style )`** for each style emitted (this is what triggers the
  on-demand enqueue — see §6). Force a wrapper via `sc_needs_wrapper`.
- **Assets** — do **NOT** write a `wp_footer` enqueue. Register the module's per-style asset layout
  once with the shared loader (§6); it enqueues only the used styles' partials.
- **Register** the module in `class-fw-extension-animation-engine.php` `_init()` (`require_once`).

### Inserter tile icon (shortcodes ext)
The inserter tile (not the swatch) uses a flat-blue **line icon** keyed by the field id. Add one to
`FW_Container_Type_Animation_Stack::icon_svg()` in
`shortcodes/includes/container-types/animation-stack/class-fw-container-type-animation-stack.php`
(24×24, `stroke="currentColor"`, no fill). Falls back to a generic glyph if omitted.

---

## 3. Do NOT add a Theme Settings enable tab

The **"Add Animation" inserter is the control surface**, and every module's assets already load only
on pages that use them — so a global enable/disable UI is redundant. **Do not register an
`upw_anim_engine_module_tabs` tab** for a new module. Keep `upw_<mod>_enabled()` (default `yes`) as a
programmatic choke point only; the runtime never needs a UI to flip it. The removed "Effects" tab
lived in `includes/effects-control.php` — that file now just strips any stray enable-only tabs.
Full-config site-wide tabs (Cursor, Page Transitions, Scroll Progress, Engine) are separate and DO
keep their tabs.

---

## 3b. Multi-instance (stackable) modules — opt in ONLY when the runtime can layer

A module can set **`anim_meta['multi'] => true`** to be added to one element MORE THAN once (its
inserter tile stays available; each Add drops a new card). Mechanically it's free: the container /
`sc_get_animation_fields()` pre-declare N slots per multi module (`<key>`, `<key>__2 … __N`), each
persisting under its own key, and the container groups the slots under one tile.

**But only opt in when the module's front-end can actually apply several instances at once.** That
means the module's `sc_build_wrapper_attr` handler AND its runtime must **loop over every instance**
(base + `<key>__N`) — see **`hover`** (the reference): `upw_hover_instances()` collects them, the
wrapper emits a combined class list + a space-joined `data-hover`, and `hover.js` splits that list
and wires each effect.

Do **NOT** set `multi` on a module whose effects fight over the same property or rebuild the same
DOM — two of them on one element would clobber each other. As of now the ONLY multi-enabled module is
**Hover** (its effects are independent CSS/JS decorations). Everything else is one-per-element by
nature (Entrance/Scroll/Physics/Marquee/Scroll-Reveal/Flip = one transform or clip per element;
Parallax = one depth role; the Section-level modules target the whole section). Text Effect *could*
stack but would need a runtime rewrite (its effects rebuild the text into per-char spans), so it is
deliberately left single. When in doubt: leave it single.

---

## 3c. On-demand, per-style assets (REQUIRED — the anti-bloat contract)

**A style's CSS/JS ships ONLY on a page that actually uses that style — never a per-module bundle.**
With hundreds of styles across modules this is the difference between a clean product and bloatware.
A page that uses one effect must load *one* effect's CSS (and zero JS if that effect is CSS-only).
The shared loader (`includes/asset-loader.php`) makes this automatic — **`hover` is the canonical
reference.** Never enqueue a single `<mod>.css` / `<mod>.js` bundle.

**File layout** (per module):
- `static/css/effects/<style>.css` — one small partial per style (its rules **and** its own
  `@media (prefers-reduced-motion: reduce)` block). Keep each `@keyframes` name unique to that file
  (two partials that ship the same keyframe name on one page collide — this bit the `shine` vs.
  `image_reveal` sweep; the fix was renaming one to `sc-hover-img-shine`).
- `static/js/effects/<style>.js` — **only** for styles that need JS. Each registers itself:
  `(window.upw<Mod>Fx = window.upw<Mod>Fx || {}).<style> = { pointer:bool, reduceSkip:bool, run:function(el,cfg){…} }`.
- `static/js/<mod>-core.js` — the dispatcher: reads `[data-<abbr>]`, looks up each listed style in the
  registry, applies the shared gating (`reduceSkip`→reduced-motion, `pointer`→touch/mobile), calls
  `run`. CSS-only styles have no registry entry and are simply skipped.

**Register once** (at module load, guarded by `function_exists( 'upw_anim_register_assets' )`):
```php
upw_anim_register_assets( '<mod>', array(
    'path'      => __DIR__,
    'uri'       => $ext->get_declared_URI( '/modules/<mod>' ),
    'ver'       => $ext->manifest->get_version(),
    'css_dir'   => 'static/css/effects',
    'js_dir'    => 'static/js/effects',
    'base_js'   => 'static/js/<mod>-core.js',           // '' if the module is CSS-only
    'js_styles' => array( 'magnetic', 'tilt', … ),      // ONLY styles that ship a JS partial
    'js_cfg'    => function () { return 'window.upw<Mod>Cfg=' . wp_json_encode( array(
        'reducedMotion' => …, 'disableMobile' => …,     // upw_anim_engine_setting(...)
    ) ) . ';'; },
) );
```
Then in the wrapper filter call `upw_anim_use_asset( '<mod>', $style )` per emitted style. The loader's
single `wp_footer` pass enqueues `base_css` (if any) + each used `<style>.css`, and — only when a
used style is in `js_styles` — loads that style's JS partial(s) followed by `<mod>-core.js` (which
depends on them, so it inits after they register). **A page using only CSS styles loads no JS.**
Per-style files are independently cacheable; the asset-optimizer extension can concatenate them.

> Migrating an existing bundle module: split its `.css`/`.js` into the layout above, delete the old
> `<mod>.css`/`<mod>.js` bundles (and prune them from the four xampp mirrors — `cp -r` won't remove
> files that no longer exist in source), and replace the `upw_<mod>_flag()`/`wp_footer` enqueue with
> the registration + `upw_anim_use_asset` calls.

---

## 4. JS / CSS conventions

- **No libraries** unless unavoidable (the four scroll/pointer modules are pure CSS + one passive,
  rAF-throttled `scroll` listener). Honour `prefers-reduced-motion` (gated by `cfg.reducedMotion`)
  and skip pointer effects on touch when `cfg.disableMobile`.
- **`clip-path` + IntersectionObserver don't mix:** Chromium counts the target's own `clip-path` in
  the intersection ratio, so a fully-clipped element reports ratio 0 and never fires. Use a
  `getBoundingClientRect` scroll check instead (see `scroll-reveal`).
- Namespace classes/attrs `upw-<mod>` / `sc-<mod>` / `data-<abbr>-*`.

---

## 5. Ship checklist

1. Lint PHP (`php -l`) + `node --check` the JS (core + every effect partial).
2. Bump **`animation-engine/manifest.php`** (+ **`shortcodes/manifest.php`** if you touched the
   container `icon_svg`).
3. **Mirror** to the four xampp installs (`root`/`demos`/`sshots`/`testsite`). A **new** module —
   or a new per-style partial — = `cp -r` the whole module folder (a per-file list misses new files);
   if you deleted an old bundle, `rm` it from each mirror too. `diff -rq` per install to confirm.
4. Verify in the builder: the tile appears in the inserter (correct count, 5/row, baked label, no
   bg), adds + reveals its options, no JS/console errors; smoke-test the front-end effect.
5. **Verify on-demand loading:** on a page using ONE style, confirm only that style's `<style>.css`
   is enqueued (and no JS for a CSS-only style) — `curl` the page or inspect `wp_styles()->queue`.
