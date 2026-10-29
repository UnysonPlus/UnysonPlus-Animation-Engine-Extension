/**
 * Animation Engine — Scroll Ruler (admin-only, front end).
 *
 * Lists every GSAP ScrollTrigger on the page and maps their [start,end] scroll ranges onto a vertical
 * ruler with a live playhead. Toggle with the ⟺ button (bottom-left) or Shift+R. Read-only.
 */
( function () {
	'use strict';

	var OPEN_KEY = 'upwScrollRulerOpen';
	var btn, panel, rail, list, playhead, open = false, built = false;

	function ST() { return window.ScrollTrigger || null; }
	function maxScroll() { return Math.max( 1, ( document.documentElement.scrollHeight || document.body.scrollHeight ) - window.innerHeight ); }
	function scrollY() { return window.pageYOffset || document.documentElement.scrollTop || 0; }

	// A short, readable label for a trigger's element.
	function describe( el ) {
		if ( ! el || ! el.tagName ) { return '(element)'; }
		var t = el.tagName.toLowerCase();
		if ( el.id ) { return t + '#' + el.id; }
		var cls = ( el.className || '' ).toString().trim().split( /\s+/ ).filter( function ( c ) {
			return c && c.indexOf( 'pin-spacer' ) < 0 && c.indexOf( 'gsap' ) < 0;
		} );
		// Prefer a telling class (a gallery design, a section, a sequence…).
		var telling = cls.filter( function ( c ) { return /tdg--|choreo|upw-|fw-|section/.test( c ); } );
		var pick = ( telling[ 0 ] || cls[ 0 ] );
		return t + ( pick ? '.' + pick : '' );
	}

	function triggers() {
		var S = ST();
		if ( ! S ) { return []; }
		return S.getAll().map( function ( t ) {
			return { t: t, start: Math.round( t.start ), end: Math.round( t.end ), pin: !! t.pin, scrub: !! ( t.vars && t.vars.scrub ), el: t.trigger };
		} ).filter( function ( r ) { return isFinite( r.start ) && isFinite( r.end ) && r.end > r.start; } )
		  .sort( function ( a, b ) { return a.start - b.start; } );
	}

	function build() {
		btn = document.createElement( 'button' );
		btn.className = 'upw-sruler-btn';
		btn.type = 'button';
		btn.title = 'Scroll Ruler (Shift+R)';
		btn.innerHTML = '<span>⟺</span>';
		btn.addEventListener( 'click', toggle );

		panel = document.createElement( 'div' );
		panel.className = 'upw-sruler-panel';
		panel.innerHTML =
			'<div class="upw-sruler-head"><strong>Scroll Ruler</strong>' +
			'<button type="button" class="upw-sruler-x" title="Close">×</button></div>' +
			'<div class="upw-sruler-count"></div>' +
			'<div class="upw-sruler-list"></div>';
		panel.querySelector( '.upw-sruler-x' ).addEventListener( 'click', toggle );
		list = panel.querySelector( '.upw-sruler-list' );

		rail = document.createElement( 'div' );
		rail.className = 'upw-sruler-rail';
		rail.innerHTML = '<div class="upw-sruler-track"></div><div class="upw-sruler-playhead"></div>';
		playhead = rail.querySelector( '.upw-sruler-playhead' );

		document.body.appendChild( btn );
		document.body.appendChild( panel );
		document.body.appendChild( rail );
		built = true;
	}

	function colorFor( i ) { return 'hsl(' + ( ( i * 47 ) % 360 ) + ' 70% 58%)'; }

	function render() {
		if ( ! built ) { return; }
		var rows = triggers(), max = maxScroll();
		panel.querySelector( '.upw-sruler-count' ).textContent =
			rows.length ? ( rows.length + ' scroll trigger' + ( rows.length === 1 ? '' : 's' ) ) : 'No scroll animations on this page.';

		// List.
		list.innerHTML = '';
		rows.forEach( function ( r, i ) {
			var row = document.createElement( 'div' );
			row.className = 'upw-sruler-row';
			row.innerHTML =
				'<span class="upw-sruler-dot" style="background:' + colorFor( i ) + '"></span>' +
				'<span class="upw-sruler-name">' + describe( r.el ) + '</span>' +
				'<span class="upw-sruler-tags">' + ( r.pin ? '📌' : '' ) + ( r.scrub ? ' scrub' : '' ) + '</span>' +
				'<span class="upw-sruler-range">' + r.start + '–' + r.end + '</span>';
			row.addEventListener( 'click', function () { window.scrollTo( { top: r.start, behavior: 'smooth' } ); } );
			list.appendChild( row );
		} );

		// Rail bars (mapped onto the full document scroll height).
		var track = rail.querySelector( '.upw-sruler-track' );
		track.innerHTML = '';
		rows.forEach( function ( r, i ) {
			var bar = document.createElement( 'div' );
			bar.className = 'upw-sruler-bar';
			bar.style.top = ( r.start / max * 100 ) + '%';
			bar.style.height = Math.max( 0.6, ( r.end - r.start ) / max * 100 ) + '%';
			bar.style.background = colorFor( i );
			bar.title = describe( r.el ) + '  ' + r.start + '–' + r.end + ( r.pin ? '  pinned' : '' );
			bar.addEventListener( 'click', function () { window.scrollTo( { top: r.start, behavior: 'smooth' } ); } );
			track.appendChild( bar );
		} );
		updatePlayhead();
	}

	function updatePlayhead() {
		if ( ! built || ! open ) { return; }
		playhead.style.top = ( scrollY() / maxScroll() * 100 ) + '%';
	}

	function apply() {
		document.documentElement.classList.toggle( 'upw-sruler-on', open );
		if ( open ) { render(); }
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
			if ( e.shiftKey && ( e.key === 'R' || e.key === 'r' ) && ! /input|textarea|select/i.test( ( e.target.tagName || '' ) ) ) { toggle(); }
		} );
		// ScrollTriggers register a little after load; refresh a few times so the list fills in.
		var tries = 0, iv = setInterval( function () { render(); if ( ++tries >= 8 ) { clearInterval( iv ); } }, 600 );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); } else { init(); }
}() );
