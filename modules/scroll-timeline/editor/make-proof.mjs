/**
 * Generates proof.html: a standalone page that compiles a sample pinned-Section (native kf lists) with
 * our compiler, then mounts it through the VENDORED Kinetic Scenes player in scroll mode — proving the
 * compiled scene plays, scrubbed by scroll, in the reused runtime. Verified headless by proof.test.mjs.
 *   node make-proof.mjs
 */
import { writeFileSync, readFileSync } from 'node:fs';
import { compileFromKf } from './compiler.mjs';

const STAGE_W = 960, STAGE_H = 540;
const section = {
  title: 'Scroll Timeline — proof',
  pin: { length: 2, smooth: 0.2 },
  elements: [
    { id: 'a', name: 'Rise', box: { x: 30, y: 50, w: 26 }, kf: [
      { at: 0,   v: { x: 0, y: 150, scale: 0.8, opacity: 0 }, ease: 'out' },
      { at: 0.5, v: { x: 0, y: 0,   scale: 1,   opacity: 1 }, ease: 'back' },
      { at: 1,   v: { x: 0, y: -150, scale: 1,  opacity: 0 }, ease: 'in' },
    ] },
    { id: 'b', name: 'Slide', box: { x: 70, y: 50, w: 26 }, kf: [
      { at: 0,   v: { x: 160, y: 0, opacity: 0 }, ease: 'out' },
      { at: 1,   v: { x: 0,   y: 0, opacity: 1 }, ease: 'out' },
    ] },
  ],
};

const { scene, warnings } = compileFromKf(section, { stageWidth: STAGE_W, stageHeight: STAGE_H });
// Make the proxy layers visible so the scrub is watchable + measurable.
scene.layers[0].fill = '#2ad38b';
scene.layers[1].fill = '#2f74e6';
scene.stage.background = '#0f1420';

const html = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Scroll Timeline — proof</title>
<style>
  body { margin: 0; font: 14px system-ui, sans-serif; background: #f4f5f6; color: #1c1e22; }
  .lead, .tail { padding: 2rem; text-align: center; color: #5b6b7b; }
  .lead { height: 30vh; display: grid; place-items: center; }
  .tail { height: 60vh; }
  #scene { width: 100%; max-width: ${STAGE_W}px; margin: 0 auto; }
</style></head>
<body>
  <div class="lead"><div><strong>Scroll down.</strong><br>The scene below is compiled from native keyframes and scrubbed by scroll through the reused player.</div></div>
  <div id="scene" data-proof></div>
  <div class="tail">End of the pinned range.</div>
  <script src="../static/vendor/scene-player.js"></script>
  <script>
    window.__SCENE__ = ${JSON.stringify(scene)};
    window.__MOUNTED__ = false;
    var host = document.getElementById('scene');
    try { window.UnysonPlusScene.mount(host, window.__SCENE__, {}); window.__MOUNTED__ = true; }
    catch (e) { document.title = 'MOUNT ERROR: ' + (e && e.message); console.error(e); }
  </script>
</body></html>`;

writeFileSync(new URL('./proof.html', import.meta.url), html);
const kb = Math.round(readFileSync(new URL('./proof.html', import.meta.url)).length / 1024);
console.log(`Wrote proof.html (${kb}KB): ${scene.layers.length} layers, duration ${scene.duration}s, warnings ${warnings.length}.`);
