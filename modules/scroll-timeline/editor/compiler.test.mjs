/**
 * Tests for the Scroll Timeline native → scene compiler.
 * Run: node modules/scroll-timeline/editor/compiler.test.mjs
 * Asserts the output is a well-formed, scroll-triggered Kinetic Scenes Scene (mirrors the key checks
 * in packages/player/src/validate.ts) and that the Start/Mid/End → keyframes mapping is faithful.
 */
import assert from 'node:assert/strict';
import { compilePinnedSection, compileFromKf, sceneToNative, sceneToStates, SCENE_FORMAT, SCENE_VERSION } from './compiler.mjs';

const r2 = (v) => Math.round(v * 100) / 100;
const r3 = (v) => Math.round(v * 1000) / 1000;

const section = {
  title: 'Test Scroll Scene',
  pin: { length: 2, smooth: 0.4 },
  elements: [
    // A 2-state fade-up (the module's default preset): start (y40, opacity0) → end (natural).
    {
      id: 'headline', name: 'Headline', box: { x: 50, y: 40, w: 60 },
      scroll_keyframes: { mode: 'keyframes', keyframes: {
        states: {
          start: { x: 0, y: 40, scale: 1, rotation: 0, rotationX: 0, rotationY: 0, opacity: 0, blur: 0 },
          mid: { enable: 'no' },
          end: { ease: 'out', x: 0, y: 0, scale: 1, rotation: 0, rotationX: 0, rotationY: 0, opacity: 1, blur: 0 },
        }, run_on_mobile: 'yes',
      } },
    },
    // A 3-state slide-in with a Middle (at 60%, back ease) and a 3D rotationY that must be dropped+warned.
    {
      id: 'card', name: 'Card', box: { x: 50, y: 60, w: 40 },
      scroll_keyframes: { mode: 'keyframes', keyframes: {
        states: {
          start: { x: -200, y: 0, scale: 0.8, rotation: 0, rotationX: 0, rotationY: 45, opacity: 0, blur: 8 },
          mid: { enable: 'yes', at: 60, ease: 'back', x: 0, y: 0, scale: 1.05, rotation: 0, rotationX: 0, rotationY: 0, opacity: 1, blur: 0 },
          end: { ease: 'sine', x: 0, y: 0, scale: 1, rotation: 0, rotationX: 0, rotationY: 0, opacity: 1, blur: 0 },
        },
      } },
    },
    // Not keyframes → must be skipped.
    { id: 'ignored', scroll_keyframes: { mode: 'none' } },
  ],
};

const { scene, warnings } = compilePinnedSection(section, { stageWidth: 1280, stageHeight: 720 });

// --- Scene is a well-formed scroll scene -----------------------------------
assert.equal(scene.format, SCENE_FORMAT);
assert.equal(scene.version, SCENE_VERSION);
assert.ok(scene.duration > 0, 'duration > 0');
assert.equal(scene.playback.trigger, 'scroll', 'scroll-triggered');
assert.equal(scene.playback.scroll.pin, true, 'pinned stage');
assert.equal(scene.playback.scroll.length, 2);
assert.equal(scene.layers.length, 2, 'only the two keyframes elements become layers (ignored one skipped)');

// --- Every layer carries one valid keyframes clip --------------------------
for (const L of scene.layers) {
  assert.ok(L.id && L.type === 'shape' && Array.isArray(L.clips) && L.clips.length === 1, 'layer shape');
  const c = L.clips[0];
  assert.equal(c.preset, 'keyframes');
  assert.ok(Array.isArray(c.params.frames) && c.params.frames.length >= 2, 'frames');
  let last = -Infinity;
  for (const f of c.params.frames) {
    assert.ok(typeof f.t === 'number' && f.t >= 0 && f.t <= scene.duration, 't within [0,duration]');
    assert.ok(f.t >= last, 'frames sorted by t'); last = f.t;
    for (const k of ['x', 'y', 'scale', 'rotate', 'opacity', 'blur']) {
      assert.equal(typeof f[k], 'number', `${k} numeric`);
    }
  }
}

// --- Faithful Start/Mid/End → keyframes mapping ----------------------------
const H = scene.layers[0].clips[0].params.frames; // headline
const C = scene.layers[1].clips[0].params.frames; // card
const dur = scene.duration;

assert.equal(H.length, 2, 'headline: start + end (no middle)');
assert.equal(C.length, 3, 'card: start + mid + end');

// px → % conversion (card start x = -200px / 1280 * 100)
assert.equal(C[0].x, r2((-200 / 1280) * 100));
assert.equal(C[0].opacity, 0);
assert.equal(C[0].blur, 8);
assert.equal(C[0].scale, 0.8);

// Middle sits at 60% of the duration, back ease into it
assert.equal(C[1].t, r3(0.6 * dur));
assert.equal(C[1].ease, 'back.out(1.7)');
assert.equal(C[1].scale, 1.05);

// End eases: card 'sine' → sine.inOut, headline 'out' → power2.out
assert.equal(C[2].t, dur);
assert.equal(C[2].ease, 'sine.inOut');
assert.equal(H[1].ease, 'power2.out');

// Start frame carries no ease (it is the initial state)
assert.equal(H[0].ease, undefined);
assert.equal(C[0].ease, undefined);

// --- Lossy 3D rotation is dropped AND warned (not silent) ------------------
assert.ok(
  warnings.some((w) => /rotationY/.test(w) && /card/.test(w)),
  'card rotationY:45 dropped + warned'
);
// headline has no non-zero 3D rotation → no warning
assert.ok(!warnings.some((w) => /headline/.test(w)), 'headline: no 3D warning');

console.log(`OK — compiled a scroll scene: ${scene.layers.length} layers, duration ${dur}s, ${warnings.length} warning(s).`);
warnings.forEach((w) => console.log('  ⚠', w));

// === Round-trip: scene → native free `kf` list (the runtime shape) ========
const near = (a, b, tol = 0.5) => assert.ok(Math.abs(a - b) <= tol, `${a} ≈ ${b} (±${tol})`);
const nat = sceneToNative(scene, { stageWidth: 1280, stageHeight: 720 });
assert.equal(nat.elements.length, 2, 'two elements round-trip');

const hKf = nat.elements.find((e) => e.id === 'headline').kf;
const cKf = nat.elements.find((e) => e.id === 'card').kf;

// at is normalized 0..1; ease names are AE names; v is in AE units (px).
assert.deepEqual(hKf.map((k) => k.at), [0, 1], 'headline at [0,1]');
near(hKf[0].v.y, 40); // start y 40px round-trips
assert.equal(hKf[0].v.opacity, 0);
near(hKf[1].v.y, 0);
assert.equal(hKf[1].v.opacity, 1);
assert.equal(hKf[0].ease, 'out', 'start ease baked as out (matches render)');
assert.equal(hKf[1].ease, 'out', 'end ease out → out');

assert.deepEqual(cKf.map((k) => k.at), [0, 0.6, 1], 'card at [0,0.6,1]');
near(cKf[0].v.x, -200); // start x -200px survives px→%→px (within tolerance)
assert.equal(cKf[0].v.scale, 0.8);
assert.equal(cKf[0].v.blur, 8);
assert.equal(cKf[0].v.rotationX, 0);
assert.equal(cKf[0].v.rotationY, 0, '3D rotation lost on the forward pass → 0 (known)');
assert.equal(cKf[1].v.scale, 1.05);
assert.equal(cKf[1].ease, 'back', 'mid ease back.out(1.7) → back');
assert.equal(cKf[2].ease, 'sine', 'end ease sine.inOut → sine');

// === Convenience: scene → Start/Mid/End states (≤3 keyframes) =============
const st = sceneToStates(scene, { stageWidth: 1280, stageHeight: 720 });
const hState = st.elements.find((e) => e.id === 'headline').states;
const cState = st.elements.find((e) => e.id === 'card').states;
assert.equal(hState.mid.enable, 'no', 'headline: no middle');
near(hState.start.y, 40);
assert.equal(cState.mid.enable, 'yes', 'card: has middle');
assert.equal(cState.mid.at, 60, 'card middle at 60%');
assert.equal(cState.mid.ease, 'back');
assert.equal(cState.end.ease, 'sine');

// A clip with >3 keyframes can't be a 3-state option → warned, not silently truncated.
const fourFrameScene = {
  format: SCENE_FORMAT, version: SCENE_VERSION, duration: 4,
  stage: scene.stage, playback: scene.playback,
  layers: [{ id: 'many', type: 'shape', box: { x: 50, y: 50, w: 50 }, clips: [{ id: 'many-kf', preset: 'keyframes', start: 0, duration: 4,
    params: { frames: [{ t: 0, opacity: 0 }, { t: 1, opacity: 1 }, { t: 2, opacity: 0.5 }, { t: 4, opacity: 1 }] } }] }],
};
const stMany = sceneToStates(fourFrameScene);
assert.equal(stMany.elements.length, 0, '>3-keyframe clip is not folded into states');
assert.ok(stMany.warnings.some((w) => /many/.test(w) && /4 keyframes/.test(w)), '>3 keyframes warned');

console.log(`OK — round-trip: ${nat.elements.length} elements → native kf; states fold for ≤3; ${nat.warnings.length} note(s), ${stMany.warnings.length} >3-warning(s).`);

// === Live-DOM path: compile from a baked `kf` list, then round-trip back ===
// This is what the editor extracts off `data-upw-skf` (any-length free list, at 0..1, v in px).
const kfSection = {
  title: 'From kf',
  pin: { length: 3, smooth: 0.4 },
  elements: [
    { id: 'panel', name: 'Panel', box: { x: 50, y: 50, w: 70 }, kf: [
      { at: 0,    v: { x: 0,   y: 120, scale: 0.9, rotation: 0, rotationX: 0, rotationY: 0, opacity: 0, blur: 6 }, ease: 'out' },
      { at: 0.35, v: { x: 0,   y: 0,   scale: 1,   rotation: 0, rotationX: 0, rotationY: 0, opacity: 1, blur: 0 }, ease: 'back' },
      { at: 0.75, v: { x: 0,   y: 0,   scale: 1.1, rotation: 6, rotationX: 0, rotationY: 0, opacity: 1, blur: 0 }, ease: 'sine' },
      { at: 1,    v: { x: 240, y: 0,   scale: 1,   rotation: 0, rotationX: 0, rotationY: 0, opacity: 0, blur: 0 }, ease: 'in' },
    ] },
  ],
};
const sw = 1280, sh = 720;
const kfOut = compileFromKf(kfSection, { stageWidth: sw, stageHeight: sh });
assert.equal(kfOut.scene.layers.length, 1);
const clip = kfOut.scene.layers[0].clips[0];
assert.equal(clip.preset, 'keyframes');
assert.equal(clip.params.frames.length, 4, 'four keyframes survive (free list, > 3)');
assert.equal(kfOut.scene.playback.scroll.length, 3);

// Round-trip the free list: compileFromKf → sceneToNative should reproduce at + v (px within tolerance).
const back = sceneToNative(kfOut.scene, { stageWidth: sw, stageHeight: sh }).elements[0].kf;
assert.deepEqual(back.map((k) => k.at), [0, 0.35, 0.75, 1], 'at values preserved');
near(back[0].v.y, 120); near(back[3].v.x, 240);
assert.equal(back[0].v.opacity, 0);
assert.equal(back[2].v.scale, 1.1);
assert.equal(back[2].v.rotation, 6);
assert.equal(back[1].ease, 'back'); // mid eases preserved through name↔gsap mapping
assert.equal(back[2].ease, 'sine');
assert.equal(back[3].ease, 'in');

console.log(`OK — live-DOM path: compiled ${clip.params.frames.length}-keyframe free list and round-tripped it.`);
