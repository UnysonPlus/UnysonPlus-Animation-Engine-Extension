/* Shared authoring-guide HUD for the Animation Engine's scroll-driven elements (Scroll Choreography +
 * Model Viewer). One consistent overlay: page markers at each keyframe %, and a stacking HUD showing the
 * live scroll %, the phase window, and — per posed thing — a named row with its fade and (on "show more")
 * a labelled value grid (X · Y · scale · turn · tilt · fade …). Every guide is numbered, tinted its own
 * hue (matching its markers), stacks with the others (never overlaps), collapses/expands, and flashes its
 * element when its title is clicked. Callers own the scroll geometry; this owns all the DOM + presentation.
 *
 *   var g = window.upwAnimGuide.create({
 *     label:   'Model Viewer',                 // HUD title
 *     target:  el,                             // flashed when the title is clicked
 *     marks:   [0, 40, 100],                   // keyframe percents → page marker lines
 *     sections:[{ name:'Astronaut', cells:['X','Y','scale','turn','tilt','fade'] }],
 *     meta:    'travel 3 screens · smooth 16%' // shown under "show more"
 *   });
 *   g.positionMarks(travel, vh, startY);       // on measure/resize
 *   g.update({ pct: 33, rows:[{ on:true, fade:'93%', values:['0vw','2vh','99%','12°','-4°','93%'] }] });
 */
(function () {
	'use strict';
	if (typeof window === 'undefined' || window.upwAnimGuide) { return; }
	var CIRCLED = '①②③④⑤⑥⑦⑧⑨⑩⑪⑫⑬⑭⑮⑯⑰⑱⑲⑳';
	function numB(n) { return (n >= 1 && n <= 20) ? CIRCLED.charAt(n - 1) : ('#' + n); }
	function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (txt != null) { e.textContent = txt; } return e; }

	function stack() {
		var s = document.getElementById('fw-anim-guide-stack');
		if (!s) { s = document.createElement('div'); s.id = 'fw-anim-guide-stack'; document.body.appendChild(s); }
		return s;
	}

	var N = 0;

	function create(opts) {
		opts = opts || {};
		N++;
		var num = N, hue = (num * 47) % 360;
		var ats = (opts.marks || []).slice().sort(function (a, b) { return a - b; });

		// Page markers.
		var wrap = el('div', 'fw-anim-guide'); wrap.style.setProperty('--g-hue', hue);
		var marks = ats.map(function (a) {
			var m = el('div', 'fw-anim-guide-mark');
			m.innerHTML = '<span class="fw-anim-guide-badge">' + numB(num) + ' ' + a + '%</span>';
			m.__at = a; wrap.appendChild(m); return m;
		});
		document.body.appendChild(wrap);

		// HUD.
		var hud = el('div', 'fw-anim-guide-hud'); hud.style.setProperty('--g-hue', hue);
		var head = el('div', 'fw-anim-guide-head');
		var title = el('button', 'fw-anim-guide-title'); title.type = 'button';
		title.title = 'Click to flash this element on the page';
		title.appendChild(el('span', 'fw-anim-guide-swatch'));
		title.appendChild(el('span', 'fw-anim-guide-name-el', opts.label || 'Guide'));
		title.appendChild(el('span', 'fw-anim-guide-num', numB(num)));
		var pct = el('span', 'fw-anim-guide-pct'); pct.innerHTML = 'scroll <b>0%</b>'; var pctB = pct.querySelector('b');
		var edit = null;
		if (opts.editor) {
			edit = el('button', 'fw-anim-guide-edit'); edit.type = 'button';
			edit.title = 'Edit mode — scrub the timeline and tweak keyframes live'; edit.textContent = '✎';
		}
		var more = el('button', 'fw-anim-guide-more'); more.type = 'button'; more.title = 'Show more / less'; more.textContent = '⋯';
		var grip = el('span', 'fw-anim-guide-grip', '⠿'); grip.title = 'Drag to move this panel';
		head.appendChild(grip); head.appendChild(title); head.appendChild(pct); if (edit) { head.appendChild(edit); } head.appendChild(more);

		var phase = el('div', 'fw-anim-guide-phase');
		var rowsWrap = el('div', 'fw-anim-guide-rows');
		var edSteps = (opts.editor && opts.editor.steps) || [];
		var rowRefs = (opts.sections || []).map(function (sec, si) {
			var row = el('div', 'fw-anim-guide-row');
			var nrow = el('div', 'fw-anim-guide-nrow');
			nrow.appendChild(el('span', 'fw-anim-guide-dot'));
			nrow.appendChild(el('span', 'fw-anim-guide-lname', sec.name || ''));
			var fade = el('b', 'fw-anim-guide-fade'); nrow.appendChild(fade);
			var grid = el('div', 'fw-anim-guide-grid'); var vals = [];
			(sec.cells || []).forEach(function (label, ci) {
				var c = el('span', 'fw-anim-guide-cell'); c.appendChild(el('i', null, label));
				if (opts.editor && edSteps[ci]) {
					c.classList.add('is-editable');
					var dn = el('button', 'fw-anim-guide-step'); dn.type = 'button'; dn.textContent = '−';
					var v0 = el('b');
					var up = el('button', 'fw-anim-guide-step'); up.type = 'button'; up.textContent = '+';
					dn.addEventListener('click', (function (S, C) { return function () { opts.editor.onNudge(S, C, -1); }; })(si, ci));
					up.addEventListener('click', (function (S, C) { return function () { opts.editor.onNudge(S, C, 1); }; })(si, ci));
					c.appendChild(dn); c.appendChild(v0); c.appendChild(up); grid.appendChild(c); vals.push(v0);
				} else {
					var v = el('b'); c.appendChild(v); grid.appendChild(c); vals.push(v);
				}
			});
			row.appendChild(nrow); row.appendChild(grid);
			// Keyframe track (edit mode) — a horizontal 0–100% ruler with a draggable marker per keyframe
			// and a playhead. Built empty here; redrawTracks() populates it from editor.getTracks().
			var track = null;
			if (opts.editor && opts.editor.getTracks) {
				track = el('div', 'fw-anim-guide-track'); track.setAttribute('data-si', si);
				track.appendChild(el('div', 'fw-anim-guide-playhead'));
				row.appendChild(track);
			}
			rowsWrap.appendChild(row);
			return { row: row, fade: fade, vals: vals, track: track };
		});
		var meta = el('div', 'fw-anim-guide-meta', opts.meta || '');

		// Editor bar — a scrub playhead (freeze the scene at any % without scrolling), a Live release,
		// and Copy-keys (serialise the edited keyframes to the clipboard for pasting back into the option).
		var edbar = null, scrub = null, copyBtn = null, delKey = null;
		if (opts.editor) {
			edbar = el('div', 'fw-anim-guide-editbar');
			var srow = el('label', 'fw-anim-guide-scrubrow');
			srow.appendChild(el('span', 'fw-anim-guide-scrublbl', 'scrub'));
			scrub = document.createElement('input');
			scrub.type = 'range'; scrub.min = '0'; scrub.max = '100'; scrub.step = '0.5'; scrub.value = '0';
			scrub.className = 'fw-anim-guide-scrub';
			srow.appendChild(scrub);
			var live = el('button', 'fw-anim-guide-live'); live.type = 'button'; live.textContent = 'Live';
			live.title = 'Release the playhead — follow the real scroll again';
			srow.appendChild(live);
			copyBtn = el('button', 'fw-anim-guide-copy'); copyBtn.type = 'button'; copyBtn.textContent = 'Copy keys';
			copyBtn.title = 'Copy the edited keyframes as JSON to paste back into the element option';
			delKey = el('button', 'fw-anim-guide-delkey'); delKey.type = 'button'; delKey.textContent = 'Delete key';
			delKey.title = 'Delete the selected keyframe (min 2 kept)'; delKey.disabled = true;
			edbar.appendChild(srow); edbar.appendChild(delKey); edbar.appendChild(copyBtn);

			scrub.addEventListener('input', function () { hud.classList.add('is-scrubbing'); opts.editor.onScrub(parseFloat(scrub.value) / 100); updatePlayheads(); });
			live.addEventListener('click', function () { hud.classList.remove('is-scrubbing'); opts.editor.onLive(); });
			copyBtn.addEventListener('click', function () {
				var out = opts.editor.onCopy(); if (out == null) { return; }
				var done = function () { copyBtn.textContent = 'Copied ✓'; setTimeout(function () { copyBtn.textContent = 'Copy keys'; }, 1400); };
				if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(out).then(done, done); }
				else { try { var ta = document.createElement('textarea'); ta.value = out; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); done(); } catch (e) {} }
			});
		}

		// ---- Keyframe-track timeline (edit mode) ----------------------------------------------------
		// A per-layer 0–100% track: drag a marker to retime a keyframe, click it to select + scrub to it
		// (the ± steppers then edit it), double-click empty track to add one, Delete key to remove one.
		// Engine-agnostic: the editor exposes getTracks / onKeyMove / onKeyAdd / onKeyDelete.
		var redrawTracks = function () {}, updatePlayheads = function () {};
		if (opts.editor && opts.editor.getTracks) {
			var sel = { si: null, ki: null }, drag = null;
			var scrubPct = function () { return parseFloat(scrub.value) || 0; };
			var atFromX = function (track, cx) { var r = track.getBoundingClientRect(); return Math.max(0, Math.min(100, ((cx - r.left) / (r.width || 1)) * 100)); };
			var paintSel = function () {
				for (var i = 0; i < rowRefs.length; i++) {
					var t = rowRefs[i].track; if (!t) { continue; }
					var ks = t.querySelectorAll('.fw-anim-guide-key');
					for (var j = 0; j < ks.length; j++) { ks[j].classList.toggle('is-sel', sel.si === i && sel.ki === j); }
				}
			};
			var clearSel = function () { sel.si = null; sel.ki = null; if (delKey) { delKey.disabled = true; } paintSel(); };
			updatePlayheads = function () {
				var p = scrubPct();
				for (var i = 0; i < rowRefs.length; i++) {
					var t = rowRefs[i].track; if (!t) { continue; }
					var ph = t.querySelector('.fw-anim-guide-playhead'); if (ph) { ph.style.left = p + '%'; }
				}
			};
			var selectKey = function (si, ki, at) {
				sel.si = si; sel.ki = ki; if (delKey) { delKey.disabled = false; }
				scrub.value = at; hud.classList.add('is-scrubbing');
				if (opts.editor.onScrub) { opts.editor.onScrub(at / 100); }
				updatePlayheads(); paintSel();
			};
			var onKeyDown = function (ev) {
				ev.preventDefault(); ev.stopPropagation();
				drag = { si: ev.currentTarget.__si, ki: ev.currentTarget.__ki, marker: ev.currentTarget, track: ev.currentTarget.parentNode, moved: false, startX: ev.clientX };
			};
			document.addEventListener('pointermove', function (ev) {
				if (!drag) { return; }
				if (Math.abs(ev.clientX - drag.startX) > 2) { drag.moved = true; }
				var at = atFromX(drag.track, ev.clientX);
				if (opts.editor.onKeyMove) { opts.editor.onKeyMove(drag.si, drag.ki, at); }
				var data = opts.editor.getTracks() || [], real = data[drag.si] && data[drag.si].ats[drag.ki];
				drag.marker.style.left = (real != null ? real : at) + '%';
			});
			document.addEventListener('pointerup', function () {
				if (!drag) { return; }
				var d = drag; drag = null;
				if (!d.moved) { var data = opts.editor.getTracks() || [], at = (data[d.si] && data[d.si].ats[d.ki]) || 0; selectKey(d.si, d.ki, at); }
				else { redrawTracks(); }
			});
			redrawTracks = function () {
				var data = opts.editor.getTracks() || [];
				for (var i = 0; i < rowRefs.length; i++) {
					var t = rowRefs[i].track; if (!t) { continue; }
					var old = t.querySelectorAll('.fw-anim-guide-key');
					for (var o = 0; o < old.length; o++) { t.removeChild(old[o]); }
					var ats = (data[i] && data[i].ats) || [];
					for (var ki = 0; ki < ats.length; ki++) {
						var m = el('div', 'fw-anim-guide-key'); m.style.left = ats[ki] + '%'; m.__si = i; m.__ki = ki;
						m.title = 'Drag to retime · click to select';
						m.addEventListener('pointerdown', onKeyDown);
						m.addEventListener('dblclick', function (ev) { ev.stopPropagation(); });
						t.appendChild(m);
					}
					if (!t.__addWired) {
						t.__addWired = true;
						t.addEventListener('dblclick', (function (si) { return function (ev) {
							if (opts.editor.onKeyAdd) { opts.editor.onKeyAdd(si, atFromX(this, ev.clientX)); }
							clearSel(); redrawTracks();
						}; })(i));
					}
				}
				updatePlayheads(); paintSel();
			};
			if (delKey) {
				delKey.addEventListener('click', function () {
					if (sel.si == null) { return; }
					if (opts.editor.onKeyDelete) { opts.editor.onKeyDelete(sel.si, sel.ki); }
					clearSel(); redrawTracks();
				});
			}
		}

		hud.appendChild(head); hud.appendChild(phase); hud.appendChild(rowsWrap); if (edbar) { hud.appendChild(edbar); } hud.appendChild(meta);
		stack().appendChild(hud);

		more.addEventListener('click', function () { hud.classList.toggle('is-expanded'); });
		if (edit) {
			edit.addEventListener('click', function () {
				var on = hud.classList.toggle('is-editing');
				if (on) { hud.classList.add('is-expanded'); redrawTracks(); } else { hud.classList.remove('is-scrubbing'); opts.editor.onLive(); }
				edit.classList.toggle('is-active', on);
			});
		}
		title.addEventListener('click', function () {
			var t = opts.target; if (!t) { return; }
			t.classList.remove('fw-anim-guide-flash'); void t.offsetWidth; t.classList.add('fw-anim-guide-flash');
		});

		// Drag the panel by its grip — pop it out of the bottom-right stack and pin it anywhere on screen,
		// so it never covers the corner you're posing into. Position becomes fixed (viewport-relative).
		var hudDrag = null, posKey = 'fwAnimGuidePos:' + (opts.label || 'guide') + ':' + num;
		function pinAt(left, top) {
			var w = hud.offsetWidth, h = hud.offsetHeight;
			hud.style.position = 'fixed'; hud.style.margin = '0'; hud.style.right = 'auto'; hud.style.bottom = 'auto';
			hud.style.left = Math.max(4, Math.min(window.innerWidth - w - 4, left)) + 'px';
			hud.style.top  = Math.max(4, Math.min(window.innerHeight - h - 4, top)) + 'px';
			hud.classList.add('is-dragged');
		}
		grip.addEventListener('pointerdown', function (ev) {
			ev.preventDefault();
			var r = hud.getBoundingClientRect();
			hudDrag = { dx: ev.clientX - r.left, dy: ev.clientY - r.top };
			pinAt(r.left, r.top);
		});
		document.addEventListener('pointermove', function (ev) {
			if (!hudDrag) { return; }
			pinAt(ev.clientX - hudDrag.dx, ev.clientY - hudDrag.dy);
		});
		document.addEventListener('pointerup', function () {
			if (!hudDrag) { return; }
			hudDrag = null;
			// Remember where the author parked this panel (per browser, per guide).
			try { localStorage.setItem(posKey, JSON.stringify({ left: hud.style.left, top: hud.style.top })); } catch (e) {}
		});
		// Restore a saved position on load.
		try {
			var savedPos = JSON.parse(localStorage.getItem(posKey) || 'null');
			if (savedPos && savedPos.left) { pinAt(parseFloat(savedPos.left) || 0, parseFloat(savedPos.top) || 0); }
		} catch (e) {}

		function phaseText(p) {
			if (!ats.length) { return ''; }
			if (p <= ats[0]) { return 'before ' + ats[0] + '%'; }
			if (p >= ats[ats.length - 1]) { return 'at ' + ats[ats.length - 1] + '%'; }
			for (var i = 1; i < ats.length; i++) { if (p <= ats[i]) { return ats[i - 1] + '% → ' + ats[i] + '%'; } }
			return '';
		}

		return {
			num: num, hue: hue, hud: hud, marks: marks,
			isEditing: function () { return hud.classList.contains('is-editing'); },
			isScrubbing: function () { return hud.classList.contains('is-scrubbing'); },
			// Keep the playhead following the real scroll while editing but not actively dragging it.
			syncScrub: function (pct) { if (scrub && !hud.classList.contains('is-scrubbing')) { scrub.value = pct; } },
			positionMarks: function (travel, vh, startY) {
				for (var i = 0; i < marks.length; i++) { marks[i].style.top = (startY + (ats[i] / 100) * travel * vh) + 'px'; }
			},
			update: function (frame) {
				var p = Math.round(frame.pct);
				pctB.textContent = p + '%';
				phase.textContent = phaseText(p);
				var rows = frame.rows || [];
				for (var i = 0; i < rowRefs.length; i++) {
					var r = rows[i]; if (!r) { continue; }
					rowRefs[i].row.classList.toggle('is-on', !!r.on);
					rowRefs[i].fade.textContent = r.fade || '';
					for (var j = 0; j < rowRefs[i].vals.length; j++) { rowRefs[i].vals[j].textContent = (r.values && r.values[j] != null) ? r.values[j] : ''; }
				}
				for (var k = 0; k < marks.length; k++) { marks[k].classList.toggle('is-active', Math.abs(marks[k].__at - p) <= 1); }
			}
		};
	}

	window.upwAnimGuide = { create: create };
})();
