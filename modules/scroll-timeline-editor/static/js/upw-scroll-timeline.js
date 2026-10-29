/**
 * Animation Engine — Scroll Timeline Editor (admin-only, front end).
 *
 * A bottom-docked, video-editor-style timeline of every GSAP ScrollTrigger on the page. Each trigger
 * is a TRACK; its [start, end] scroll range is drawn as a block on a 0–100 % scroll axis. A draggable
 * playhead scrubs the real page live.
 *
 * Phase 2 — DRAG TO RETIME. Tracks whose element carries a stable `data-upw-g-key` (Scroll Motion
 * effects) are editable: drag the block to move its START, drag the right handle to move the END
 * (scrub tracks). The change previews live (sets the trigger's vars + refreshes) and persists to a
 * per-page post meta (never the builder JSON) via admin AJAX, so it ships to real visitors. A Reset
 * (↺) per edited track reverts to the original timing. Toggle the panel with Shift+T or the Timeline
 * button (bottom-right).
 */
( function () {
	'use strict';

	var OPEN_KEY = 'upwScrollTimelineOpen';
	var DATA = window.upwStlData || {};
	var btn, panel, scrollEl, rowsHost, axisEl, playhead, readout, countEl;
	var open = false, built = false, rows = [], dragging = false;

	// Scroll Keyframes beat-state editor (Phase 3b): the eight animatable props + the ease curves,
	// matching the Scroll Keyframes option definitions.
	var SKF_PROPS = [
		{ k: 'x', label: 'X', min: -300, max: 300, step: 5 },
		{ k: 'y', label: 'Y', min: -300, max: 300, step: 5 },
		{ k: 'scale', label: 'Scale', min: 0, max: 3, step: 0.05 },
		{ k: 'rotation', label: 'Rotate', min: -360, max: 360, step: 1 },
		{ k: 'rotationX', label: 'Rot X', min: -180, max: 180, step: 1 },
		{ k: 'rotationY', label: 'Rot Y', min: -180, max: 180, step: 1 },
		{ k: 'opacity', label: 'Opacity', min: 0, max: 1, step: 0.05 },
		{ k: 'blur', label: 'Blur', min: 0, max: 40, step: 1 }
	];
	var SKF_EASES = [ 'linear', 'out', 'in', 'inout', 'back', 'sine' ];
	var beatEditorEl = null;

	function overrides() {
		var o = window.upwTimingOverrides;
		if ( ! o || Array.isArray( o ) ) { o = window.upwTimingOverrides = {}; } // PHP prints [] for empty
		return o;
	}
	function ST() { return window.ScrollTrigger || null; }
	function maxScroll() { return Math.max( 1, ( document.documentElement.scrollHeight || document.body.scrollHeight ) - window.innerHeight ); }
	function scrollY() { return window.pageYOffset || document.documentElement.scrollTop || 0; }
	function pct( n ) { return Math.round( n * 1000 ) / 10; }
	function round1( n ) { return Math.round( n * 10 ) / 10; }

	function describe( el ) {
		if ( ! el || ! el.tagName ) { return '(element)'; }
		var t = el.tagName.toLowerCase();
		if ( el.id ) { return t + '#' + el.id; }
		var cls = ( el.className || '' ).toString().trim().split( /\s+/ ).filter( function ( c ) {
			return c && c.indexOf( 'pin-spacer' ) < 0 && c.indexOf( 'gsap' ) < 0 && c.indexOf( 'upw-stl-' ) !== 0;
		} );
		var telling = cls.filter( function ( c ) { return /tdg--|choreo|upw-|fw-|section/.test( c ); } );
		var pick = ( telling[ 0 ] || cls[ 0 ] );
		return t + ( pick ? '.' + pick : '' );
	}

	// The stable key lives on the Scroll Motion wrapper; effects that trigger off an inner node
	// (e.g. SplitText text reveals fire on the heading inside the wrapper) need the keyed ancestor.
	function keyOf( el ) {
		if ( ! el || ! el.getAttribute ) { return ''; }
		var k = el.getAttribute( 'data-upw-g-key' );
		if ( k ) { return k; }
		var anc = el.closest ? el.closest( '[data-upw-g-key]' ) : null;
		return anc ? ( anc.getAttribute( 'data-upw-g-key' ) || '' ) : '';
	}

	function collect() {
		var S = ST(), max = maxScroll(), canEdit = !! DATA.canEdit;
		var gsap = S ? S.getAll().map( function ( t ) {
			var el = t.trigger;
			var key = keyOf( el );
			return {
				t: t, start: Math.round( t.start ), end: Math.round( t.end ),
				pin: !! t.pin, scrub: !! ( t.vars && t.vars.scrub ), el: el,
				key: key || '', editable: !! ( key && canEdit ), max: max
			};
		} ).filter( function ( r ) { return isFinite( r.start ) && isFinite( r.end ) && r.end > r.start; } ) : [];
		// Scroll Keyframes elements aren't GSAP ScrollTriggers (they ride the shared frame loop), so
		// surface them here too, as keyframe-lane tracks with a marker per beat.
		return gsap.concat( collectKf( max, canEdit ) )
			.sort( function ( a, b ) { return a.start - b.start || b.end - a.end; } );
	}

	// ---- Scroll Keyframes tracks (keyframe lanes) --------------------------

	// The element's live beat positions (0..1). Prefer the runtime's parsed kf (reflects any applied
	// override); fall back to decoding the baked attribute.
	function skfAts( el ) {
		if ( el.__skfKf && el.__skfKf.length ) {
			return el.__skfKf.map( function ( k ) { return k.at; } );
		}
		try {
			var d = JSON.parse( window.atob( el.getAttribute( 'data-upw-skf' ) || '' ) );
			return ( d && d.kf ) ? d.kf.map( function ( k ) { return k.at; } ) : [];
		} catch ( e ) { return []; }
	}

	// The element's live beat objects ({at,v,ease}); prefer the runtime array so state edits mutate it.
	function skfBeats( el ) {
		if ( el.__skfKf && el.__skfKf.length ) { return el.__skfKf; }
		try {
			var d = JSON.parse( window.atob( el.getAttribute( 'data-upw-skf' ) || '' ) );
			return ( d && d.kf ) ? d.kf : [];
		} catch ( e ) { return []; }
	}

	// The element's scroll range in document pixels, so its beats can be placed on the 0–100 % axis.
	function skfRange( el ) {
		var max = maxScroll(), vh = window.innerHeight || 1;
		var runway = el.__skfRunway ||
			( el.parentNode && el.parentNode.classList && el.parentNode.classList.contains( 'sc-skf-runway' ) ? el.parentNode : null );
		if ( runway ) { // Pin · Hold · Exit → the sticky runway's scroll span (matches pinProgress())
			var rtop = runway.getBoundingClientRect().top + scrollY();
			var span = runway.offsetHeight - ( el.offsetHeight || vh );
			if ( span < 1 ) { span = runway.offsetHeight; }
			return { start: Math.max( 0, Math.round( rtop ) ), end: Math.min( max, Math.round( rtop + span ) ) };
		}
		// Otherwise it scrubs as it travels the viewport (matches the runtime's viewportTravel()).
		var r = el.getBoundingClientRect(), docTop = r.top + scrollY();
		return { start: Math.max( 0, Math.round( docTop - vh ) ), end: Math.min( max, Math.round( docTop + r.height ) ) };
	}

	function collectKf( max, canEdit ) {
		var els = document.querySelectorAll( '.sc-scroll-kf[data-upw-skf]' ), out = [];
		Array.prototype.forEach.call( els, function ( el ) {
			var ats = skfAts( el );
			if ( ats.length < 2 ) { return; }
			var rg = skfRange( el );
			if ( ! ( rg.end > rg.start ) ) { return; }
			var key = keyOf( el );
			out.push( {
				kfTrack: true, el: el, key: key || '', editable: !! ( key && canEdit ),
				start: rg.start, end: rg.end, ats: ats, beats: skfBeats( el ), max: max, scrub: false,
				pin: !! ( el.__skfRunway || el.getAttribute( 'data-upw-skf-pin' ) )
			} );
		} );
		return out;
	}

	function colorFor( i ) { return 'hsl(' + ( ( i * 47 + 190 ) % 360 ) + ' 68% 60%)'; }

	function build() {
		btn = document.createElement( 'button' );
		btn.className = 'upw-stl-btn';
		btn.type = 'button';
		btn.title = 'Scroll Timeline (Shift+T)';
		btn.innerHTML = '<span class="upw-stl-btn-glyph"><i></i><i></i><i></i></span><span>Timeline</span>';
		btn.addEventListener( 'click', toggle );

		panel = document.createElement( 'div' );
		panel.className = 'upw-stl';
		panel.innerHTML =
			'<div class="upw-stl-bar">' +
				'<strong>Scroll Timeline</strong>' +
				'<span class="upw-stl-count"></span>' +
				'<span class="upw-stl-readout"></span>' +
				'<button type="button" class="upw-stl-x" title="Close (Shift+T)">×</button>' +
			'</div>' +
			'<div class="upw-stl-scroll">' +
				'<div class="upw-stl-axisrow"><div class="upw-stl-corner"></div><div class="upw-stl-axis"></div></div>' +
				'<div class="upw-stl-rows"></div>' +
			'</div>' +
			'<div class="upw-stl-playhead"><i></i><b title="Drag to scrub"></b></div>';

		panel.querySelector( '.upw-stl-x' ).addEventListener( 'click', toggle );
		scrollEl = panel.querySelector( '.upw-stl-scroll' );
		rowsHost = panel.querySelector( '.upw-stl-rows' );
		axisEl   = panel.querySelector( '.upw-stl-axis' );
		readout  = panel.querySelector( '.upw-stl-readout' );
		countEl  = panel.querySelector( '.upw-stl-count' );
		playhead = panel.querySelector( '.upw-stl-playhead' );

		playhead.querySelector( 'b' ).addEventListener( 'pointerdown', startScrub );
		axisEl.addEventListener( 'pointerdown', function ( e ) { scrubToClientX( e.clientX ); startScrub( e ); } );

		document.body.appendChild( btn );
		document.body.appendChild( panel );
		built = true;
	}

	// ---- Scrub (playhead) --------------------------------------------------

	function axisFraction( clientX ) {
		var r = axisEl.getBoundingClientRect();
		if ( r.width <= 0 ) { return 0; }
		return Math.min( 1, Math.max( 0, ( clientX - r.left ) / r.width ) );
	}
	function scrubToClientX( clientX ) { window.scrollTo( 0, axisFraction( clientX ) * maxScroll() ); }
	function startScrub( e ) {
		dragging = true;
		window.addEventListener( 'pointermove', onScrub );
		window.addEventListener( 'pointerup', endScrub );
		e.preventDefault();
	}
	function onScrub( e ) { if ( dragging ) { scrubToClientX( e.clientX ); } }
	function endScrub() {
		dragging = false;
		window.removeEventListener( 'pointermove', onScrub );
		window.removeEventListener( 'pointerup', endScrub );
	}

	// ---- Element highlight -------------------------------------------------

	var hlEl = null;
	function highlight( el ) {
		if ( hlEl === el ) { return; }
		if ( hlEl ) { hlEl.classList.remove( 'upw-stl-hl' ); }
		hlEl = el;
		if ( hlEl && hlEl.classList ) { hlEl.classList.add( 'upw-stl-hl' ); }
	}

	// ---- Retime: geometry <-> GSAP position string -------------------------

	// Absolute document offset of a trigger element's top (what GSAP's "top X%" measures against).
	function elemDocTop( r ) {
		var el = r.el;
		if ( ! el || ! el.getBoundingClientRect ) { return 0; }
		// For pinned triggers the element may be wrapped in a pin-spacer; prefer that for a stable top.
		var node = ( el.parentNode && el.parentNode.classList && el.parentNode.classList.contains( 'pin-spacer' ) ) ? el.parentNode : el;
		return node.getBoundingClientRect().top + scrollY();
	}
	// A scroll-pixel position -> a "top N%" string that reproduces it for this element.
	function pxToPos( r, px ) {
		var vh = window.innerHeight || 1;
		var n = ( elemDocTop( r ) - px ) / vh * 100;
		n = Math.max( -60, Math.min( 160, n ) );
		return 'top ' + round1( n ) + '%';
	}

	function applyLive( r ) {
		var S = ST();
		if ( S ) { S.refresh(); }
	}

	// POST one element's override (start/end for GSAP tracks, kf/kfs for keyframe tracks) and reconcile.
	// `quiet` skips the re-render on response (used while the beat editor is open, so it never flickers).
	function postOverride( key, fields, reset, quiet ) {
		if ( ! DATA.ajaxurl || ! DATA.postId ) { return; }
		var body = 'action=upw_save_timing&nonce=' + encodeURIComponent( DATA.nonce ) +
			'&post_id=' + encodeURIComponent( DATA.postId ) + '&key=' + encodeURIComponent( key );
		if ( reset ) {
			body += '&reset=1';
		} else {
			for ( var f in fields ) { if ( fields[ f ] != null && fields[ f ] !== '' ) { body += '&' + f + '=' + encodeURIComponent( fields[ f ] ); } }
		}
		fetch( DATA.ajaxurl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body
		} ).then( function ( res ) { return res.json(); } ).then( function ( j ) {
			if ( j && j.success ) {
				var o = ( j.data && j.data.overrides ) ? j.data.overrides : {};
				// PHP returns [] for an empty map; normalise to a plain object so key lookups work.
				window.upwTimingOverrides = Array.isArray( o ) ? {} : o;
				if ( ! quiet ) { render(); }
			}
		} ).catch( function () {} );
	}

	// Persist (or reset) one GSAP track's start/end override.
	function saveOverride( r, reset ) {
		var fields = {};
		if ( ! reset ) {
			if ( r.t._upwNewStart ) { fields.start = r.t._upwNewStart; }
			if ( r.t._upwNewEnd ) { fields.end = r.t._upwNewEnd; }
		}
		postOverride( r.key, fields, reset );
	}

	function resetTrack( r ) {
		if ( r.kfTrack ) { // keyframe lane — revert beat positions + states live, then drop the override
			closeBeatEditor();
			if ( r.key && window.upwSkfReset ) { window.upwSkfReset( r.key ); }
			delete overrides()[ r.key ];
			render();
			postOverride( r.key, {}, true );
			return;
		}
		var st = r.t;
		if ( st._upwOrig ) {
			if ( st._upwOrig.start != null ) { st.vars.start = st._upwOrig.start; }
			if ( st._upwOrig.end != null ) { st.vars.end = st._upwOrig.end; }
			delete st._upwOrig;
		}
		delete st._upwNewStart; delete st._upwNewEnd;
		delete overrides()[ r.key ]; // optimistic local clear, so the UI de-edits instantly
		applyLive( r );
		render();
		saveOverride( r, true ); // persist + reconcile
	}

	// ---- Drag a block (move start) or its right handle (move end) ----------

	function startBlockDrag( r, mode, e ) {
		if ( ! r.editable ) { return; }
		e.preventDefault(); e.stopPropagation();
		var st = r.t;
		if ( ! st._upwOrig ) { st._upwOrig = { start: st.vars.start, end: st.vars.end }; }
		var max = maxScroll(), startPx0 = st.start, endPx0 = st.end;
		var axisRect = axisEl.getBoundingClientRect();
		var startX = e.clientX, moved = false;

		function move( ev ) {
			var dx = ev.clientX - startX;
			if ( Math.abs( dx ) > 2 ) { moved = true; }
			var dPx = dx / axisRect.width * max;
			if ( mode === 'move' ) {
				var ns = Math.max( 0, Math.min( max, startPx0 + dPx ) );
				st.vars.start = pxToPos( r, ns );
				st._upwNewStart = st.vars.start;
				// Move the END by the same delta for EVERY track (scrub keeps its duration; a
				// near-zero-range toggle reveal keeps its range from inverting, which would drop
				// the track out of collect()'s end>start filter and make it vanish mid-drag).
				st.vars.end = pxToPos( r, Math.max( ns + 1, endPx0 + dPx ) );
				st._upwNewEnd = st.vars.end;
			} else { // 'end'
				var ne = Math.max( startPx0 + 1, Math.min( max, endPx0 + dPx ) );
				st.vars.end = pxToPos( r, ne );
				st._upwNewEnd = st.vars.end;
			}
			applyLive( r );
			positionBlock( r );
			updatePlayhead();
		}
		function up() {
			window.removeEventListener( 'pointermove', move );
			window.removeEventListener( 'pointerup', up );
			if ( moved ) {
				r._suppressClick = true;
				// Instant local feedback (the edited ring/badge), then persist in the background.
				var o = {};
				if ( st._upwNewStart ) { o.start = st._upwNewStart; }
				if ( st._upwNewEnd ) { o.end = st._upwNewEnd; }
				overrides()[ r.key ] = o;
				render();
				saveOverride( r, false );
			}
		}
		window.addEventListener( 'pointermove', move );
		window.addEventListener( 'pointerup', up );
	}

	// Re-read a track's live start/end and reposition its block (during/after a drag).
	function positionBlock( r ) {
		if ( ! r._block ) { return; }
		var max = maxScroll();
		r.start = Math.round( r.t.start ); r.end = Math.round( r.t.end );
		r._block.style.left = ( r.start / max * 100 ) + '%';
		r._block.style.width = Math.max( 0.3, ( r.end - r.start ) / max * 100 ) + '%';
	}

	// ---- Drag a keyframe marker (retime one beat's position) ----------------

	function positionMarkers( r ) {
		if ( ! r._markers ) { return; }
		var span = r.end - r.start, max = r.max;
		r._markers.forEach( function ( m, idx ) {
			m.style.left = ( ( r.start + r.ats[ idx ] * span ) / max * 100 ) + '%';
		} );
	}

	// Interior beats drag to retime; a click (no drag) opens the beat-state editor. Dragging converts the
	// pointer X to a beat `at` fraction of the range, clamped strictly between its neighbours, previews live.
	function startMarkerDrag( r, idx, lane, e ) {
		if ( ! r.editable || idx <= 0 || idx >= r.ats.length - 1 ) { return; }
		e.preventDefault(); e.stopPropagation();
		var span = r.end - r.start, moved = false, startX = e.clientX;
		function move( ev ) {
			if ( Math.abs( ev.clientX - startX ) > 3 ) { moved = true; }
			if ( ! moved ) { return; }
			var rr = lane.getBoundingClientRect();
			var frac = rr.width > 0 ? Math.min( 1, Math.max( 0, ( ev.clientX - rr.left ) / rr.width ) ) : 0;
			var a = span > 0 ? ( frac * r.max - r.start ) / span : 0;
			var lo = r.ats[ idx - 1 ] + 0.01, hi = r.ats[ idx + 1 ] - 0.01;
			r.ats[ idx ] = Math.max( lo, Math.min( hi, a ) );
			if ( r.key && window.upwSkfRetime ) { window.upwSkfRetime( r.key, r.ats ); }
			positionMarkers( r );
		}
		function up() {
			window.removeEventListener( 'pointermove', move );
			window.removeEventListener( 'pointerup', up );
			if ( moved ) { r._suppressClick = true; saveKfTrack( r ); }
			else { openBeatEditor( r, idx ); }
		}
		window.addEventListener( 'pointermove', move );
		window.addEventListener( 'pointerup', up );
	}

	// ---- Persist a keyframe track (positions + per-beat states) ------------

	// Per-beat state DIFF vs the authored values: kfs[i] = { v:{changed props}, ease? } | null.
	function buildKfs( r ) {
		var baseV = ( r.el && r.el.__skfBaseV ) || [], baseE = ( r.el && r.el.__skfBaseEase ) || [];
		return r.beats.map( function ( b, i ) {
			var row = {}, vdiff = {};
			SKF_PROPS.forEach( function ( p ) {
				var cur = b.v ? b.v[ p.k ] : undefined;
				var base = baseV[ i ] ? baseV[ i ][ p.k ] : undefined;
				if ( typeof cur === 'number' && ( base === undefined || Math.abs( cur - base ) > 1e-6 ) ) { vdiff[ p.k ] = cur; }
			} );
			if ( Object.keys( vdiff ).length ) { row.v = vdiff; }
			if ( b.ease && baseE[ i ] !== undefined && b.ease !== baseE[ i ] ) { row.ease = b.ease; }
			return Object.keys( row ).length ? row : null;
		} );
	}

	function saveKfTrack( r, quiet ) {
		var baseAt = ( r.el && r.el.__skfBaseAt ) || [];
		var posChanged = r.ats.some( function ( a, i ) { return baseAt[ i ] === undefined || Math.abs( a - baseAt[ i ] ) > 1e-4; } );
		var kfs = buildKfs( r );
		var hasState = kfs.some( function ( x ) { return x; } );
		if ( ! posChanged && ! hasState ) { // back to the authored timeline → drop the override
			delete overrides()[ r.key ];
			if ( ! quiet ) { render(); }
			postOverride( r.key, {}, true, quiet );
			return;
		}
		var o = {}, fields = {};
		if ( posChanged ) { o.kf = r.ats.slice(); fields.kf = r.ats.join( ',' ); }
		if ( hasState ) { o.kfs = kfs; fields.kfs = JSON.stringify( kfs ); }
		overrides()[ r.key ] = o;
		if ( ! quiet ) { render(); }
		postOverride( r.key, fields, false, quiet );
	}

	// ---- Beat-state editor (Phase 3b) --------------------------------------

	var beatSaveT = null;
	function scheduleBeatSave( r ) { clearTimeout( beatSaveT ); beatSaveT = setTimeout( function () { saveKfTrack( r, true ); }, 300 ); }

	function closeBeatEditor() {
		if ( beatEditorEl && beatEditorEl.parentNode ) { beatEditorEl.parentNode.removeChild( beatEditorEl ); }
		beatEditorEl = null;
		document.removeEventListener( 'pointerdown', onBeatOutside, true );
	}
	function onBeatOutside( e ) {
		if ( beatEditorEl && ! beatEditorEl.contains( e.target ) && ! ( e.target.classList && e.target.classList.contains( 'upw-stl-kfdot' ) ) ) { closeBeatEditor(); }
	}

	function openBeatEditor( r, idx ) {
		closeBeatEditor();
		if ( ! r.editable || ! r.beats[ idx ] ) { return; }
		var beat = r.beats[ idx ]; // === the live kf beat, so edits mutate the running animation
		if ( ! beat.v ) { beat.v = {}; }

		// Seek the page so the element renders THIS beat's pose while you edit it.
		var seek = Math.round( r.start + r.ats[ idx ] * ( r.end - r.start ) );
		window.scrollTo( 0, seek );

		var box = document.createElement( 'div' );
		box.className = 'upw-stl-beat';
		var last = r.ats.length - 1;
		var role = idx === 0 ? 'Start' : ( idx === last ? 'End' : 'Middle ' + idx );
		var head = '<div class="upw-stl-beat-h"><strong>' + role + '</strong><span>beat ' + ( idx + 1 ) + '/' + r.ats.length + ' · ' + Math.round( r.ats[ idx ] * 100 ) + '%</span><button type="button" class="upw-stl-beat-x" title="Close">×</button></div>';
		var grid = '<div class="upw-stl-beat-grid">';
		SKF_PROPS.forEach( function ( p ) {
			var val = ( typeof beat.v[ p.k ] === 'number' ) ? beat.v[ p.k ] : ( p.k === 'scale' || p.k === 'opacity' ? 1 : 0 );
			grid += '<label><span>' + p.label + '</span><input type="number" data-k="' + p.k + '" value="' + val + '" min="' + p.min + '" max="' + p.max + '" step="' + p.step + '"></label>';
		} );
		grid += '</div>';
		var easeSel = '<label class="upw-stl-beat-ease"><span>Ease</span><select>' +
			SKF_EASES.map( function ( e ) { return '<option value="' + e + '"' + ( beat.ease === e ? ' selected' : '' ) + '>' + e + '</option>'; } ).join( '' ) +
			'</select></label>';
		var foot = '<div class="upw-stl-beat-f"><button type="button" class="upw-stl-beat-reset">↺ Reset beat</button></div>';
		box.innerHTML = head + grid + easeSel + foot;
		document.body.appendChild( box );
		beatEditorEl = box;

		// Position above the panel, horizontally near the diamond.
		var dot = r._markers && r._markers[ idx ];
		var panelH = panel ? panel.getBoundingClientRect().height : 160;
		var cx = dot ? ( dot.getBoundingClientRect().left + dot.getBoundingClientRect().width / 2 ) : ( window.innerWidth / 2 );
		var w = box.getBoundingClientRect().width || 260;
		box.style.left = Math.max( 8, Math.min( window.innerWidth - w - 8, cx - w / 2 ) ) + 'px';
		box.style.bottom = ( panelH + 12 ) + 'px';

		box.querySelector( '.upw-stl-beat-x' ).addEventListener( 'click', closeBeatEditor );
		Array.prototype.forEach.call( box.querySelectorAll( 'input[data-k]' ), function ( inp ) {
			inp.addEventListener( 'input', function () {
				var k = inp.getAttribute( 'data-k' ), val = parseFloat( inp.value );
				if ( ! isFinite( val ) ) { return; }
				beat.v[ k ] = val;
				if ( r.key && window.upwSkfSetState ) { window.upwSkfSetState( r.key, idx, ( function () { var o = {}; o[ k ] = val; return o; }() ), null ); }
				scheduleBeatSave( r );
			} );
		} );
		box.querySelector( 'select' ).addEventListener( 'change', function () {
			beat.ease = this.value;
			if ( r.key && window.upwSkfSetState ) { window.upwSkfSetState( r.key, idx, null, this.value ); }
			saveKfTrack( r, true );
		} );
		box.querySelector( '.upw-stl-beat-reset' ).addEventListener( 'click', function () {
			if ( r.key && window.upwSkfResetBeat ) { window.upwSkfResetBeat( r.key, idx ); }
			saveKfTrack( r, true );
			closeBeatEditor();
			render();
		} );
		setTimeout( function () { document.addEventListener( 'pointerdown', onBeatOutside, true ); }, 0 );
	}

	// ---- Render ------------------------------------------------------------

	function axisTicks() {
		axisEl.innerHTML = '';
		[ 0, 25, 50, 75, 100 ].forEach( function ( p ) {
			var tick = document.createElement( 'div' );
			tick.className = 'upw-stl-tick';
			tick.style.left = p + '%';
			tick.innerHTML = '<span>' + p + '%</span>';
			axisEl.appendChild( tick );
		} );
	}

	function render() {
		if ( ! built ) { return; }
		rows = collect();
		var max = maxScroll(), ov = overrides();

		countEl.textContent = rows.length ? ( '· ' + rows.length + ' animation' + ( rows.length === 1 ? '' : 's' ) ) : '';
		axisTicks();
		rowsHost.innerHTML = '';

		if ( ! rows.length ) {
			var empty = document.createElement( 'div' );
			empty.className = 'upw-stl-empty';
			empty.textContent = 'No scroll-linked animations on this page. Add a Scroll Motion, Scroll Keyframes, Motion Sequence or Motion Gallery choreography and they appear here as tracks.';
			rowsHost.appendChild( empty );
			updatePlayhead();
			return;
		}

		rows.forEach( function ( r, i ) {
			var color = colorFor( i );
			var edited = !! ( r.key && ov[ r.key ] );
			var row = document.createElement( 'div' );
			row.className = 'upw-stl-row';

			var badge;
			if ( ! r.editable ) { badge = r.kfTrack ? 'keyframes' : ( r.scrub ? 'scrub' : 'locked' ); }
			else { badge = edited ? 'edited' : ( r.kfTrack ? 'keyframes' : '' ); }

			var label = document.createElement( 'div' );
			label.className = 'upw-stl-label';
			label.innerHTML =
				'<span class="upw-stl-dot" style="background:' + color + '"></span>' +
				'<span class="upw-stl-name" title="' + describe( r.el ) + '">' + describe( r.el ) + '</span>' +
				'<span class="upw-stl-badge">' + badge + '</span>';
			if ( edited ) {
				var reset = document.createElement( 'button' );
				reset.type = 'button'; reset.className = 'upw-stl-reset'; reset.title = 'Reset timing'; reset.textContent = '↺';
				reset.addEventListener( 'click', function ( e ) { e.stopPropagation(); resetTrack( r ); } );
				label.appendChild( reset );
			}
			label.addEventListener( 'click', function () { window.scrollTo( { top: r.start, behavior: 'smooth' } ); } );

			var lane = document.createElement( 'div' );
			lane.className = 'upw-stl-lane';

			if ( r.kfTrack ) {
				// A faint backdrop for the element's scroll range + one draggable diamond per beat.
				var kblock = document.createElement( 'div' );
				kblock.className = 'upw-stl-block is-kf' + ( r.pin ? ' is-pin' : '' ) + ( edited ? ' is-edited' : '' );
				kblock.style.color = color;
				kblock.style.left = ( r.start / max * 100 ) + '%';
				kblock.style.width = Math.max( 0.3, ( r.end - r.start ) / max * 100 ) + '%';
				kblock.title = describe( r.el ) + '  ·  ' + r.ats.length + ' beats' + ( r.pin ? '  ·  pinned' : '' );
				kblock.addEventListener( 'click', function () {
					if ( r._suppressClick ) { r._suppressClick = false; return; }
					window.scrollTo( { top: r.start, behavior: 'smooth' } );
				} );
				lane.appendChild( kblock );
				r._block = kblock;
				r._markers = [];
				var span = r.end - r.start;
				var ovRow = ov[ r.key ], baseAt = ( r.el && r.el.__skfBaseAt ) || [];
				r.ats.forEach( function ( a, idx ) {
					var interior = idx > 0 && idx < r.ats.length - 1;
					// A beat is "edited" if its position moved or it carries a state override.
					var beatEdited = !! ( ovRow && ( ( ovRow.kfs && ovRow.kfs[ idx ] ) ||
						( ovRow.kf && baseAt[ idx ] !== undefined && Math.abs( a - baseAt[ idx ] ) > 1e-4 ) ) );
					var m = document.createElement( 'span' );
					m.className = 'upw-stl-kfdot' + ( interior && r.editable ? ' is-move' : '' ) +
						( interior ? '' : ' is-edge' ) + ( r.editable ? ' is-state' : '' ) + ( beatEdited ? ' is-edited' : '' );
					m.style.left = ( ( r.start + a * span ) / max * 100 ) + '%';
					m.style.color = color;
					m.title = 'Beat ' + ( idx + 1 ) + ' · ' + Math.round( a * 100 ) + '% of range' +
						( r.editable ? '  ·  click to edit state' : '' ) + ( interior && r.editable ? '  ·  drag to retime' : '' );
					if ( r.editable ) {
						if ( interior ) {
							m.addEventListener( 'pointerdown', ( function ( i ) { return function ( e ) { startMarkerDrag( r, i, lane, e ); }; }( idx ) ) );
						} else {
							m.addEventListener( 'click', ( function ( i ) { return function ( e ) { e.stopPropagation(); openBeatEditor( r, i ); }; }( idx ) ) );
						}
					}
					lane.appendChild( m );
					r._markers.push( m );
				} );
			} else {
				var block = document.createElement( 'div' );
				block.className = 'upw-stl-block' + ( r.scrub ? ' is-scrub' : '' ) + ( r.pin ? ' is-pin' : '' ) +
					( r.editable ? ' is-editable' : '' ) + ( edited ? ' is-edited' : '' );
				block.style.color = color;
				block.style.left = ( r.start / max * 100 ) + '%';
				block.style.width = Math.max( 0.3, ( r.end - r.start ) / max * 100 ) + '%';
				block.title = describe( r.el ) + '  ·  ' + pct( r.start / max ) + '%–' + pct( r.end / max ) + '%' +
					( r.editable ? '  ·  drag to retime' : '' ) + ( r.pin ? '  ·  pinned' : '' );
				r._block = block;

				if ( r.editable ) {
					block.addEventListener( 'pointerdown', function ( e ) { startBlockDrag( r, 'move', e ); } );
					if ( r.scrub ) {
						var handle = document.createElement( 'span' );
						handle.className = 'upw-stl-handle';
						handle.addEventListener( 'pointerdown', function ( e ) { startBlockDrag( r, 'end', e ); } );
						block.appendChild( handle );
					}
				}
				block.addEventListener( 'click', function () {
					if ( r._suppressClick ) { r._suppressClick = false; return; }
					window.scrollTo( { top: r.start, behavior: 'smooth' } );
				} );
				lane.appendChild( block );
			}

			row.addEventListener( 'mouseenter', function () { highlight( r.el ); } );
			row.addEventListener( 'mouseleave', function () { highlight( null ); } );

			row.appendChild( label );
			row.appendChild( lane );
			rowsHost.appendChild( row );
		} );

		updatePlayhead();
	}

	function updatePlayhead() {
		if ( ! built || ! open ) { return; }
		var max = maxScroll(), frac = scrollY() / max;
		var panelRect = panel.getBoundingClientRect(), axisRect = axisEl.getBoundingClientRect();
		playhead.style.left = ( axisRect.left - panelRect.left + frac * axisRect.width ) + 'px';
		playhead.style.top = ( axisRect.top - panelRect.top ) + 'px';
		playhead.style.height = ( panelRect.bottom - axisRect.top ) + 'px';

		var sy = scrollY(), active = 0;
		for ( var i = 0; i < rows.length; i++ ) { if ( sy >= rows[ i ].start && sy <= rows[ i ].end ) { active++; } }
		readout.innerHTML = 'Scroll <b>' + pct( frac ) + '%</b>' + ( rows.length ? ' · <b>' + active + '</b> active' : '' );
	}

	// ---- Lifecycle ---------------------------------------------------------

	function apply() {
		document.documentElement.classList.toggle( 'upw-stl-on', open );
		if ( open ) { render(); } else { highlight( null ); closeBeatEditor(); }
		try { localStorage.setItem( OPEN_KEY, open ? '1' : '0' ); } catch ( e ) {}
	}
	function toggle() { open = ! open; apply(); }

	var rafPending = false;
	function onScroll() { if ( rafPending ) { return; } rafPending = true; requestAnimationFrame( function () { rafPending = false; updatePlayhead(); } ); }
	var reT;
	function onResize() { clearTimeout( reT ); reT = setTimeout( render, 150 ); }

	function init() {
		build();
		try { open = localStorage.getItem( OPEN_KEY ) === '1'; } catch ( e ) {}
		apply();
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', onResize );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && beatEditorEl ) { closeBeatEditor(); return; }
			if ( e.shiftKey && ( e.key === 'T' || e.key === 't' ) && ! /input|textarea|select/i.test( ( e.target.tagName || '' ) ) ) { toggle(); }
		} );
		var tries = 0, iv = setInterval( function () { if ( open ) { render(); } if ( ++tries >= 8 ) { clearInterval( iv ); } }, 600 );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
