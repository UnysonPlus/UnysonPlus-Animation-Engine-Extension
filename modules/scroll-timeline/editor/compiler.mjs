/**
 * Scroll Timeline — native options → Kinetic Scenes "scene" compiler.
 *
 * Phase 1b of the Animation Engine 3D-Motion roadmap (/animation-engine/3d-motion-roadmap/roadmap).
 * Decision: reuse-kinetic-scenes-scroll-timeline — the visual editor reuses the Kinetic Scenes player
 * + timeline, bridged by THIS compiler so the builder's data model stays native (no stored scene file).
 *
 * This maps a pinned Section's native `scroll_keyframes` elements (Start / Middle / End states, each
 * with 8 animatable props + an easing) into a Kinetic Scenes **Scene** — one layer per element, each
 * carrying a single `keyframes`-preset clip whose `params.frames` is the per-property keyframe list the
 * player already interpolates. The Scene's playback is `trigger:'scroll'` (pinned sticky stage), the
 * exact mode the Kinetic Scenes player already ships, so the editor can scrub it against real scroll.
 *
 * It is a pure, framework-free function so it runs both in the editor bundle and under Node (tests).
 *
 * v1 scope / known lossy mappings (tracked, not silent — returned in `warnings`):
 *   • AE has 8 props; a scene keyframe has 6 (x, y, scale, rotate, opacity, blur). The two 3D rotations
 *     (rotationX / rotationY) have no scene-keyframe equivalent and are dropped (warned). Full 3D is a
 *     later increment (a scene supports a per-layer 3D turn, but not as a free keyframe track yet).
 *   • AE x / y are pixels; scene x / y are % of the stage, so they are converted with the stage size.
 *     Pass the stage's px size via opts.stageWidth / opts.stageHeight (defaults 1280×720).
 *   • Only `mode:'keyframes'` is compiled here; `pin_choreo` (one-click pin/hold/exit) is a follow-up.
 */

export const SCENE_FORMAT = 'unysonplus-scene';
export const SCENE_VERSION = 1;

/** AE Scroll-Keyframes ease names → the GSAP/named-ease strings a scene keyframe understands. */
export const EASE_MAP = {
  linear: 'none',
  out: 'power2.out',
  in: 'power2.in',
  inout: 'power2.inOut',
  back: 'back.out(1.7)',
  sine: 'sine.inOut',
};
const mapEase = (e) => EASE_MAP[e] || 'none';

const isNum = (v) => typeof v === 'number' && isFinite(v);
const num = (v, d = 0) => (isNum(v) ? v : d);
const r2 = (v) => Math.round(v * 100) / 100;
const r3 = (v) => Math.round(v * 1000) / 1000;
const clamp = (v, a, b) => (v < a ? a : v > b ? b : v);
const clamp01 = (v) => clamp(v, 0, 1);

/** A layer box the player will accept: every non-text layer needs a height (`h`) or `ratio`. The
 *  proxy box is only an overlay target for the real DOM element, so a square ratio is a safe default. */
function ensureBox(box) {
  const b = box && typeof box === 'object' ? box : { x: 50, y: 50, w: 80 };
  if (b.h == null && b.ratio == null) return { x: 50, y: 50, w: 80, ...b, ratio: 1 };
  return { x: 50, y: 50, w: 80, ...b };
}

/**
 * One AE keyframe STATE (its 8 props) → the 6 scene-keyframe props, converting px → % with the stage.
 * @returns {{props: object, dropped: string[]}} dropped = AE props with no scene equivalent.
 */
export function mapStateProps(state, stage) {
  const s = state || {};
  const stageW = num(stage && stage.stageW, 1280) || 1280;
  const stageH = num(stage && stage.stageH, 720) || 720;
  const props = {
    x: r2((num(s.x) / stageW) * 100), // px → % of stage width
    y: r2((num(s.y) / stageH) * 100), // px → % of stage height
    scale: r3(num(s.scale, 1)),
    rotate: r2(num(s.rotation)), // AE 2D `rotation` → scene `rotate`
    opacity: clamp01(num(s.opacity, 1)),
    blur: r2(num(s.blur)), // px
  };
  const dropped = [];
  if (num(s.rotationX)) dropped.push('rotationX');
  if (num(s.rotationY)) dropped.push('rotationY');
  return { props, dropped };
}

/**
 * AE `scroll_keyframes.keyframes.states` (start / optional mid / end) → a sorted scene-keyframe list
 * over [0, duration]. Each keyframe's `ease` shapes the move INTO it (start is the initial state).
 */
export function statesToFrames(states, duration, stage) {
  const st = states || {};
  const frames = [];
  const dropped = [];
  const add = (t, state, ease) => {
    const m = mapStateProps(state, stage);
    frames.push({ t: r3(t), ...m.props, ...(ease ? { ease } : {}) });
    m.dropped.forEach((d) => dropped.push(d));
  };
  add(0, st.start); // move into start = none (it is the resting/initial frame)
  if (st.mid && st.mid.enable === 'yes') {
    const at = clamp(num(st.mid.at, 50), 1, 99) / 100;
    add(at * duration, st.mid, mapEase(st.mid.ease));
  }
  add(duration, st.end, mapEase(st.end && st.end.ease));
  frames.sort((a, b) => a.t - b.t);
  return { frames, dropped: [...new Set(dropped)] };
}

/** One AE element (its `scroll_keyframes`, mode:keyframes) → a scene layer carrying one keyframes clip. */
export function elementToLayer(el, duration, stage) {
  const sk = (el && el.scroll_keyframes) || {};
  const kf = sk.keyframes || {};
  const { frames, dropped } = statesToFrames(kf.states || {}, duration, stage);
  const box = ensureBox(el.box);
  const layer = {
    id: el.id,
    name: el.name || el.id,
    // A proxy for the real WordPress DOM element (its content lives in the builder, not the scene);
    // only the clip's keyframes matter. The editor overlays the live element on this layer's box.
    type: 'shape',
    shape: 'rect',
    fill: 'rgba(0,0,0,0)',
    box,
    clips: [{ id: el.id + '-kf', preset: 'keyframes', start: 0, duration, params: { frames } }],
  };
  const warnings = dropped.map(
    (d) => `${el.id}: 3D "${d}" has no scene-keyframe track and was dropped (v1 is 2D keyframes).`
  );
  return { layer, warnings };
}

/**
 * Compile a pinned Section into a scroll-triggered Kinetic Scenes Scene.
 * @param {{ title?:string, pin?:{length?:number, smooth?:number}, elements?:Array }} section
 * @param {{ stageWidth?:number, stageHeight?:number, durationPerScreen?:number }} [opts]
 * @returns {{ scene: object, warnings: string[] }}
 */
export function compilePinnedSection(section, opts = {}) {
  const durationPerScreen = num(opts.durationPerScreen, 2) || 2;
  const pinLength = Math.max(0.5, num(section && section.pin && section.pin.length, 1.5));
  const duration = r3(Math.max(1, pinLength * durationPerScreen));
  const stage = { stageW: num(opts.stageWidth, 1280), stageH: num(opts.stageHeight, 720) };
  const warnings = [];
  const layers = [];
  for (const el of (section && section.elements) || []) {
    if (!el || !el.scroll_keyframes || el.scroll_keyframes.mode !== 'keyframes') continue;
    const out = elementToLayer(el, duration, stage);
    layers.push(out.layer);
    warnings.push(...out.warnings);
  }
  const scene = {
    format: SCENE_FORMAT,
    version: SCENE_VERSION,
    title: (section && section.title) || 'Scroll Scene',
    duration,
    stage: {
      breakpoints: [{ id: 'base', label: 'Base', minWidth: 0, aspect: [16, 9] }],
      heightMode: 'fill',
    },
    playback: {
      trigger: 'scroll',
      loop: false,
      scroll: {
        pin: true,
        length: pinLength,
        smooth: num(section && section.pin && section.pin.smooth, 0.4),
      },
    },
    layers,
  };
  return { scene, warnings };
}

/** One native `kf` entry ({ at:0..1, v:{AE units}, ease:name }) → a scene keyframe over [0,duration]. */
export function kfEntryToFrame(entry, duration, stage) {
  const e = entry || {};
  const v = e.v || {};
  const { props, dropped } = mapStateProps(v, stage);
  const t = r3(clamp01(num(e.at)) * num(duration, 1));
  return { frame: { t, ...props, ...(e.ease ? { ease: mapEase(e.ease) } : {}) }, dropped };
}

/**
 * Compile a pinned Section from each element's already-baked native `kf` list (the shape the runtime
 * reads off `data-upw-skf`, and what the live-DOM editor extracts) → a scroll-triggered scene. This is
 * the general path (arbitrary-length keyframes); `compilePinnedSection` is the Start/Mid/End-option path.
 * @param {{ title?:string, pin?:{length?:number,smooth?:number}, elements?:Array<{id,name?,box?,kf:Array}> }} section
 */
export function compileFromKf(section, opts = {}) {
  const durationPerScreen = num(opts.durationPerScreen, 2) || 2;
  const pinLength = Math.max(0.5, num(section && section.pin && section.pin.length, 1.5));
  const duration = r3(Math.max(1, pinLength * durationPerScreen));
  const stage = { stageW: num(opts.stageWidth, 1280), stageH: num(opts.stageHeight, 720) };
  const warnings = [];
  const layers = [];
  for (const el of (section && section.elements) || []) {
    const list = el && Array.isArray(el.kf) ? el.kf : null;
    if (!list || !list.length) continue;
    const frames = [];
    const dropped = [];
    for (const entry of list) {
      const out = kfEntryToFrame(entry, duration, stage);
      frames.push(out.frame);
      out.dropped.forEach((d) => dropped.push(d));
    }
    frames.sort((a, b) => a.t - b.t);
    layers.push({
      id: el.id,
      name: el.name || el.id,
      type: 'shape',
      shape: 'rect',
      fill: 'rgba(0,0,0,0)',
      box: ensureBox(el.box),
      clips: [{ id: el.id + '-kf', preset: 'keyframes', start: 0, duration, params: { frames } }],
    });
    [...new Set(dropped)].forEach((d) =>
      warnings.push(`${el.id}: 3D "${d}" has no scene-keyframe track and was dropped (v1 is 2D keyframes).`)
    );
  }
  const scene = {
    format: SCENE_FORMAT,
    version: SCENE_VERSION,
    title: (section && section.title) || 'Scroll Scene',
    duration,
    stage: { breakpoints: [{ id: 'base', label: 'Base', minWidth: 0, aspect: [16, 9] }], heightMode: 'fill' },
    playback: {
      trigger: 'scroll',
      loop: false,
      scroll: { pin: true, length: pinLength, smooth: num(section && section.pin && section.pin.smooth, 0.4) },
    },
    layers,
  };
  return { scene, warnings };
}

/* ---------------------------------------------------------------------------
 * The other direction: a (compiled or editor-edited) scene → native options.
 *
 * The native runtime already consumes a FREE keyframe list — `data-upw-skf:
 * { kf: [ { at:0..1, v:{x,y,scale,rotation,rotationX,rotationY,opacity,blur}, ease:<name> }, … ] }`
 * (any length). That is the faithful round-trip target, so the visual editor's arbitrary keyframes
 * map straight onto it. `sceneToStates` is a convenience that folds a ≤3-keyframe clip back into the
 * existing Start / Middle / End option UI (and warns when a clip has more than three).
 * ------------------------------------------------------------------------- */

const r1 = (v) => Math.round(v * 10) / 10;
/** GSAP/named ease string → the AE Scroll-Keyframes ease name (inverse of EASE_MAP). */
const EASE_INV = Object.fromEntries(Object.entries(EASE_MAP).map(([ae, gsap]) => [gsap, ae]));
export const gsapEaseToAe = (g) => EASE_INV[g] || 'out';

/** Inverse of mapStateProps: a 6-prop scene keyframe (scene units) → the native `v` (8 props, AE units). */
export function frameToNativeV(frame, stage) {
  const f = frame || {};
  const stageW = num(stage && stage.stageW, 1280) || 1280;
  const stageH = num(stage && stage.stageH, 720) || 720;
  return {
    x: r1((num(f.x) / 100) * stageW), // % of stage → px
    y: r1((num(f.y) / 100) * stageH),
    scale: r3(num(f.scale, 1)),
    rotation: r2(num(f.rotate)), // scene `rotate` → AE 2D `rotation`
    rotationX: 0, // no scene-keyframe track — reconstructed at identity (lost on the forward pass)
    rotationY: 0,
    opacity: clamp01(num(f.opacity, 1)),
    blur: r2(num(f.blur)),
  };
}

/** One scene `keyframes` clip → the native free `kf` list (at 0..1, v in AE units, ease AE name). */
export function clipToNativeKf(clip, duration, stage) {
  const frames = clip && clip.params && Array.isArray(clip.params.frames) ? clip.params.frames : [];
  const dur = num(duration, 1) || 1;
  return frames.map((f, i) => ({
    at: r3(clamp01(num(f.t) / dur)),
    v: frameToNativeV(f, stage),
    // The render bakes the Start keyframe's ease as 'out'; every later keyframe keeps its own ease.
    ease: i === 0 ? 'out' : gsapEaseToAe(f.ease),
  }));
}

/**
 * A scene → per-element native free keyframe lists (the runtime `kf` shape). The general round-trip.
 * @returns {{ elements: Array<{id:string, kf:Array}>, warnings: string[] }}
 */
export function sceneToNative(scene, opts = {}) {
  const stage = { stageW: num(opts.stageWidth, 1280), stageH: num(opts.stageHeight, 720) };
  const duration = num(scene && scene.duration, 1) || 1;
  const elements = [];
  for (const L of (scene && scene.layers) || []) {
    const clip = (L.clips || []).find((c) => c && c.preset === 'keyframes');
    if (!clip) continue;
    elements.push({ id: L.id, kf: clipToNativeKf(clip, duration, stage) });
  }
  const warnings = elements.length
    ? ['3D rotation (rotationX / rotationY) has no scene-keyframe track and is reconstructed as 0.']
    : [];
  return { elements, warnings };
}

/**
 * Convenience: a scene → the Start / Middle / End option shape, for clips with ≤ 3 keyframes
 * (back-compat with the existing scroll_keyframes option UI). Clips with more keyframes are reported
 * in `warnings` — those need the free-list storage, not the 3-state option.
 */
export function sceneToStates(scene, opts = {}) {
  const stage = { stageW: num(opts.stageWidth, 1280), stageH: num(opts.stageHeight, 720) };
  const duration = num(scene && scene.duration, 1) || 1;
  const warnings = [];
  const elements = [];
  for (const L of (scene && scene.layers) || []) {
    const clip = (L.clips || []).find((c) => c && c.preset === 'keyframes');
    if (!clip) continue;
    const frames = (clip.params && Array.isArray(clip.params.frames) ? clip.params.frames : [])
      .slice()
      .sort((a, b) => num(a.t) - num(b.t));
    if (frames.length > 3) {
      warnings.push(
        `${L.id}: ${frames.length} keyframes — the Start/Middle/End option holds 3; keep this on the timeline (free-list) storage.`
      );
      continue;
    }
    const first = frames[0] || {};
    const last = frames[frames.length - 1] || first;
    const states = {
      start: frameToNativeV(first, stage),
      mid:
        frames.length === 3
          ? {
              enable: 'yes',
              at: Math.round((num(frames[1].t) / duration) * 100),
              ease: gsapEaseToAe(frames[1].ease),
              ...frameToNativeV(frames[1], stage),
            }
          : { enable: 'no' },
      end: { ease: gsapEaseToAe(last.ease), ...frameToNativeV(last, stage) },
    };
    elements.push({ id: L.id, states });
  }
  return { elements, warnings };
}
