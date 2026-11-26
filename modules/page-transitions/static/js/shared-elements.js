/*!
 * Unyson+ Animation Engine — shared elements between pages (roadmap 6e).
 *
 * Runs in <head> on pages that opt in to cross-document View Transitions. The browser moves every element whose
 * view-transition-name exists on both pages from its old place into its new one. This script:
 *  - keeps names unique on the page (a repeated Shared Element name would cancel the whole transition);
 *  - with "Featured images and titles flow into their post" on (cfg.posts): names the clicked card's image and title
 *    as a list page is left, and the featured image and title of a single post (on leaving it and on arriving), and
 *    the card linking to the post you came back from; names it set are cleared once the transition ends.
 * While a page arrives through a transition, <html> carries the class upw-vt-active.
 * Browsers without cross-document View Transitions (no `pagereveal`) skip all of it and navigate normally.
 */
( function () {
	'use strict';
	if ( typeof window === 'undefined' || ! ( 'onpagereveal' in window ) ) { return; }
	var cfg = window.upwShareCfg || {};
	var lastLink = null, auto = [];
	// The browser's own transition replaces the Page Transitions cover here (hidden by CSS from the first frame).
	document.documentElement.classList.add( 'upw-vt' );

	document.addEventListener( 'click', function ( e ) {
		var a = e.target && e.target.closest ? e.target.closest( 'a[href]' ) : null;
		if ( a ) { lastLink = a; }
	}, true );

	function key( u ) { try { var x = new URL( u, location.href ); return x.origin + x.pathname.replace( /\/$/, '' ); } catch ( e ) { return ''; } }
	function name( n, v ) {
		if ( ! n || n.style.viewTransitionName || document.querySelector( '[style*="view-transition-name: ' + v + '"]' ) ) { return; }
		n.style.viewTransitionName = v;
		auto.push( n );
	}
	function clearAuto() { auto.forEach( function ( n ) { n.style.viewTransitionName = ''; } ); auto = []; }
	/** A repeated name cancels the whole transition: keep the first of each, drop the rest. */
	function dedupe() {
		var seen = {};
		Array.prototype.forEach.call( document.querySelectorAll( '[data-upw-vt]' ), function ( n ) {
			var v = n.getAttribute( 'data-upw-vt' );
			if ( seen[ v ] ) { n.style.viewTransitionName = 'none'; }
			seen[ v ] = 1;
		} );
	}
	/** The card that holds a link: its picture and its title are what flow. */
	function cardOf( a ) {
		var up = a.parentElement;
		return a.closest( 'article' ) || a.closest( 'li' ) || ( up && up.closest( '.post, .card, [class*="card"], [class*="item"], [class*="entry"]' ) ) || a;
	}
	function linkTo( u ) {
		var k = key( u ), found = null;
		if ( ! k ) { return null; }
		if ( lastLink && key( lastLink.href ) === k ) { return lastLink; }
		Array.prototype.some.call( document.querySelectorAll( 'main a[href], #content a[href], a[href]' ), function ( a ) {
			if ( key( a.href ) === k && cardOf( a ).querySelector( 'img' ) ) { found = a; return true; }
			return false;
		} );
		return found;
	}
	function nameHero() {
		var root = document.querySelector( 'main' ) || document.body;
		name( root.querySelector( '.wp-post-image' ) || root.querySelector( 'article img' ), 'upw-hero' );
		name( root.querySelector( 'h1' ) || document.querySelector( 'h1' ), 'upw-hero-title' );
	}
	function nameCard( a ) {
		if ( ! a ) { return; }
		var card = cardOf( a );
		name( card.querySelector( 'img' ), 'upw-hero' );
		name( card.querySelector( 'h1, h2, h3, h4' ), 'upw-hero-title' );
	}
	function urlOf( entry ) { return entry && entry.url; }

	window.addEventListener( 'pageswap', function ( e ) {
		if ( ! e.viewTransition ) { return; }
		dedupe();
		if ( ! cfg.posts ) { return; }
		if ( cfg.single ) { nameHero(); } else { nameCard( linkTo( urlOf( e.activation && e.activation.entry ) ) ); }
	} );
	window.addEventListener( 'pagereveal', function ( e ) {
		if ( ! e.viewTransition ) { return; }
		// While the page arrives through a transition, <html> carries .upw-vt-active (for CSS that should wait).
		var root = document.documentElement;
		root.classList.add( 'upw-vt-active' );
		var done = function () { root.classList.remove( 'upw-vt-active' ); };
		e.viewTransition.finished.then( done, done );
		dedupe();
		if ( cfg.posts ) {
			var from = window.navigation && navigation.activation && urlOf( navigation.activation.from );
			if ( cfg.single ) { nameHero(); } else if ( from ) { nameCard( linkTo( from ) ); }
		}
		e.viewTransition.finished.then( clearAuto, clearAuto );
	} );
	// Back / forward from the bfcache: names this script set on leaving must not linger.
	window.addEventListener( 'pageshow', function ( e ) { if ( e.persisted ) { clearAuto(); } } );
} )();
