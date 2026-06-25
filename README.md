# UnysonPlus WebGL Extension

A standalone **UnysonPlus extension** that adds a real-time WebGL "liquid glass"
element to the page builder. It is **not** part of the UnysonPlus plugin — install
it from **UnysonPlus → Extensions → Install Extension** (upload this repo's `.zip`,
or paste this repo's GitHub URL), then activate it.

When active, a **WebGL Object** element appears under *Media Elements* in the page
builder (`[webgl_object]`). Deactivating the extension removes it; any placed
instances then render empty (by design).

## What it renders

Four Three.js presets — **Glass Blob** (refractive `MeshPhysicalMaterial`
transmission), **Liquid Metal**, **Distorted Sphere**, and **Particle Field** — with
pointer interaction, optional scroll reaction, procedural env-map reflections, and
GPU-side noise displacement.

Built-in guards: viewport-only render loop, pause when the tab is hidden,
`prefers-reduced-motion` → static frame, poster / CSS-gradient fallback when WebGL is
unavailable, DPR + FPS caps, and full teardown when removed from the DOM.

## Requirements

- UnysonPlus (with the **Shortcodes** and **Page Builder** extensions active).

## Layout

```
webgl/                         ← the extension (slug "webgl")
├─ manifest.php                ← requires shortcodes + page-builder
├─ class-fw-extension-webgl.php
└─ shortcodes/webgl-object/    ← the [webgl_object] leaf shortcode
```

The installer derives the extension slug from the folder that contains
`manifest.php`. This repo nests the extension under `webgl/`, so installing from a
GitHub repo of any name still yields the slug **`webgl`**.

## Three.js

Vendored as **r0.149 UMD** (`build/three.min.js`, exposes `window.THREE`) — the last
release line that ships the UMD global while already supporting
`iridescence`/`transmission` on `MeshPhysicalMaterial`.
