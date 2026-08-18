/**
 * Animation Engine — Scroll Var runtime.
 *
 * For each [data-upw-svar] element, publishes its in-view scroll progress (0→1 as it crosses the
 * viewport) to a named CSS custom property, so any custom CSS can be scroll-driven. An
 * IntersectionObserver activates only on-screen elements; a single passive, rAF-throttled scroll
 * check reads getBoundingClientRect() (so it rides Lenis smooth-scroll automatically) and sets the
 * property to a clamped 0..1 value. No GSAP, no library.
 */
( function () {
	'use strict';

	function clamp01( n ) {
		return n < 0 ? 0 : ( n > 1 ? 1 : n );
	}

	// Progress for an element given its rect, the viewport size, axis and range mode.
	function progressFor( rect, vw, vh, axis, range ) {
		var start, end, pos;
		if ( axis === 'x' ) {
			start = rect.left;
			end   = rect.right;
			pos   = vw;
		} else {
			start = rect.top;
			end   = rect.bottom;
			pos   = vh;
		}
		var size = end - start; // element extent along the axis

		if ( range === 'enter' ) {
			// 0 when the leading edge enters the viewport, 1 when the element is fully in view.
			// start goes from `pos` (just off the far edge) → 0 (leading edge at origin).
			return clamp01( ( pos - start ) / ( pos + size || 1 ) );
		}

		if ( range === 'center' ) {
			// element centre vs the viewport: 0 when centre sits at the far edge (pos), 1 at the
			// near edge (0), so it reads 0.5 exactly as the element centre crosses viewport centre.
			var center = start + size / 2;
			return clamp01( ( pos - center ) / ( pos || 1 ) );
		}

		// 'cover' (default): 0 as the element enters (bottom/right edge of viewport),
		// 1 as it fully exits (top/left edge). Travel distance = viewport + element size.
		return clamp01( ( pos - start ) / ( pos + size || 1 ) );
	}

	var active = [];      // elements currently intersecting
	var ticking = false;

	function update() {
		var vw = window.innerWidth || document.documentElement.clientWidth;
		var vh = window.innerHeight || document.documentElement.clientHeight;
		for ( var i = 0; i < active.length; i++ ) {
			var el   = active[ i ];
			var rect = el.getBoundingClientRect();
			var p    = progressFor( rect, vw, vh, el.__svarAxis, el.__svarRange );
			el.style.setProperty( el.__svarName, p.toFixed( 4 ) );
		}
		ticking = false;
		// One-shot: unsubscribe from the shared loop after running (schedule() re-arms it on the next
		// scroll). Returning false is how upwAnimRaf drops a callback — without it this became a permanent
		// per-frame subscriber recomputing rects forever, defeating "costs nothing while idle".
		return false;
	}

	function schedule() {
		if ( ticking ) {
			return;
		}
		ticking = true;
		if ( window.upwAnimRaf && typeof window.upwAnimRaf.add === 'function' ) {
			window.upwAnimRaf.add( update ); // reuse the shared scheduler when present
		} else {
			window.requestAnimationFrame( update );
		}
	}

	function add( el ) {
		if ( active.indexOf( el ) === -1 ) {
			active.push( el );
		}
	}

	function remove( el ) {
		var i = active.indexOf( el );
		if ( i !== -1 ) {
			active.splice( i, 1 );
		}
	}

	function init() {
		var els = document.querySelectorAll( '[data-upw-svar]' );
		if ( ! els.length ) {
			return;
		}

		for ( var i = 0; i < els.length; i++ ) {
			var el = els[ i ];
			el.__svarName  = el.getAttribute( 'data-upw-svar-name' ) || '--progress';
			el.__svarAxis  = el.getAttribute( 'data-upw-svar-axis' ) === 'x' ? 'x' : 'y';
			el.__svarRange = el.getAttribute( 'data-upw-svar-range' ) || 'cover';
		}

		var io = ( 'IntersectionObserver' in window ) ? new IntersectionObserver( function ( entries ) {
			for ( var j = 0; j < entries.length; j++ ) {
				if ( entries[ j ].isIntersecting ) {
					add( entries[ j ].target );
				} else {
					remove( entries[ j ].target );
				}
			}
			schedule();
		}, { rootMargin: '0px', threshold: 0 } ) : null;

		for ( var k = 0; k < els.length; k++ ) {
			if ( io ) {
				io.observe( els[ k ] );
			} else {
				add( els[ k ] ); // no IO support → track everything
			}
		}

		window.addEventListener( 'scroll', schedule, { passive: true } );
		window.addEventListener( 'resize', schedule, { passive: true } );
		schedule(); // set initial values
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
