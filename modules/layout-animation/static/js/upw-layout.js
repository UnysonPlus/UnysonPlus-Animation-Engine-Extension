/*!
 * Unyson+ Animation Engine — Layout Animation (roadmap 6d).
 *
 * [data-upw-layout] = { w: items|height|both, q: item selector ('' = automatic), d: seconds, e: CSS easing,
 * n: 1 = new items fade in }.
 *
 * The "measure, then animate the difference" technique: each item's layout position is remembered, relative to the
 * element (from the offset chain, which transforms do not move). A MutationObserver (children, classes, hidden /
 * open) and a ResizeObserver (the element and its items) report changes after layout and before paint; each moved
 * item is then drawn back at its old place with an ADDITIVE transform and eased to its new one, so its own transform
 * and other effects stay. An item caught mid-glide starts again from where it is on screen. The element's own
 * height can glide too (an accordion). Nested Layout Animations are processed innermost first, and a parent simply
 * follows while a child glides its height. Window resizes and the page's own loading re-measure without animating.
 * Reduced motion: no animation.
 */
( function () {
	'use strict';

	if ( typeof window === 'undefined' || ! window.Element || ! Element.prototype.animate ) { return; }
	if ( window.upwEditCanvas ) { return; } // the Live Editor's Edit canvas holds every effect still

	var cfg = window.upwLayoutCfg || {};
	var reduce = cfg.reducedMotion !== false && !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	var LINEAR_OK = !! ( window.CSS && CSS.supports && CSS.supports( 'transition-timing-function', 'linear(0, 1)' ) );
	var BUSY = 'data-upw-layout-busy';
	function ez( e ) {
		e = e || 'cubic-bezier(.22,1,.36,1)';
		return ( LINEAR_OK || e.indexOf( 'linear(' ) !== 0 ) ? e : 'cubic-bezier(.22,1,.36,1)';
	}
	var micro = window.queueMicrotask || function ( f ) { Promise.resolve().then( f ); };

	/** A node's layout box in document coordinates, from the offset chain (transforms do not affect it). */
	function box( n ) {
		var x = 0, y = 0, p = n;
		while ( p ) { x += p.offsetLeft || 0; y += p.offsetTop || 0; p = p.offsetParent; }
		return { x: x, y: y, w: n.offsetWidth, h: n.offsetHeight };
	}
	function visible( n ) { return !! ( n.offsetWidth || n.offsetHeight || ( n.getClientRects && n.getClientRects().length ) ); }

	/** The items: the selector's matches, or the children of the first level that holds several. */
	function itemsOf( el, q ) {
		if ( q ) {
			try { return Array.prototype.slice.call( el.querySelectorAll( q ) ); } catch ( e ) { return []; }
		}
		var n = el, guard = 0;
		while ( n && n.children.length === 1 && guard++ < 8 ) { n = n.children[ 0 ]; }
		return n ? Array.prototype.filter.call( n.children, function ( c ) { return ! /^(SCRIPT|STYLE|TEMPLATE)$/.test( c.tagName ); } ) : [];
	}

	/* One scheduler for every instance: the changes of one moment are handled in one go, innermost element first,
	 * still before the browser paints. */
	var pending = [], flushing = false;
	function schedule( inst ) {
		if ( pending.indexOf( inst ) < 0 ) { pending.push( inst ); }
		if ( ! flushing ) { flushing = true; micro( flush ); }
	}
	function flush() {
		flushing = false;
		var list = pending;
		pending = [];
		list.sort( function ( a, b ) { return a.el.contains( b.el ) ? 1 : ( b.el.contains( a.el ) ? -1 : 0 ); } );
		list.forEach( function ( i ) { i.check(); } );
	}

	function setup( el ) {
		if ( el.__upwLayout ) { return; }
		var c;
		try { c = JSON.parse( el.getAttribute( 'data-upw-layout' ) || '{}' ); } catch ( e ) { return; }
		var doItems = c.w !== 'height', doHeight = c.w === 'height' || c.w === 'both';
		var dur = Math.max( 100, ( c.d || 0.45 ) * 1000 ), easing = ez( c.e );
		var last = new Map(), lastH = el.offsetHeight, anims = new Map(), hAnim = null, ro = null;
		var winW = window.innerWidth, winH = window.innerHeight;
		var inst = { el: el };
		el.__upwLayout = inst;

		// Positions are kept relative to the element, so anything moving the whole element (content above it
		// loading, a scroll) is not mistaken for an item moving.
		var rel = function ( it, o ) { var b = box( it ); return { x: b.x - o.x, y: b.y - o.y }; };
		var snapshot = function () {
			last = new Map();
			var o = box( el );
			if ( doItems ) { itemsOf( el, c.q ).forEach( function ( it ) { if ( visible( it ) ) { last.set( it, rel( it, o ) ); } } ); }
			if ( ! hAnim ) { lastH = el.offsetHeight; }
		};
		// The items are watched for size changes in every mode: that is how a change of the element's own contents
		// is seen while its height is held by a running glide.
		var watchItems = function () {
			if ( ! ro ) { return; }
			itemsOf( el, c.q ).forEach( function ( it ) { if ( ! it.__upwLayoutRo ) { it.__upwLayoutRo = true; ro.observe( it ); } } );
		};

		var glideHeight = function () {
			var from = lastH, h;
			if ( hAnim ) { // a change mid-glide: from where it is now to its new natural height
				from = el.getBoundingClientRect().height;
				var old = hAnim;
				hAnim = null;
				try { old.cancel(); } catch ( e ) {}
			}
			h = el.offsetHeight;
			if ( Math.abs( h - from ) <= 1 ) { lastH = h; el.removeAttribute( BUSY ); return; }
			var ov = el.__upwLayoutOv !== undefined ? el.__upwLayoutOv : el.style.overflow;
			el.__upwLayoutOv = ov;
			el.style.overflow = 'hidden';
			el.setAttribute( BUSY, '' );
			try {
				var a = el.animate( [ { height: from + 'px' }, { height: h + 'px' } ], { duration: dur, easing: easing } );
				hAnim = a;
				a.onfinish = function () {
					if ( hAnim !== a ) { return; }
					hAnim = null;
					el.style.overflow = ov;
					el.__upwLayoutOv = undefined;
					el.removeAttribute( BUSY );
					snapshot();
				};
			} catch ( e ) { el.style.overflow = ov; el.__upwLayoutOv = undefined; el.removeAttribute( BUSY ); }
			lastH = h;
		};

		inst.check = function () {
			// A window resize reflows everything: re-measure, but do not animate the whole page.
			if ( window.innerWidth !== winW || window.innerHeight !== winH ) {
				winW = window.innerWidth; winH = window.innerHeight;
				snapshot();
				return;
			}
			// Until the page has loaded, images and fonts arriving move things: only re-measure. And while an element
			// inside this one glides its own height, everything here follows it on its own: only re-measure.
			if ( reduce || document.readyState !== 'complete' || el.querySelector( '[' + BUSY + ']' ) ) { snapshot(); watchItems(); return; }

			if ( doHeight && ( hAnim || Math.abs( el.offsetHeight - lastH ) > 1 ) ) { glideHeight(); }

			if ( doItems ) {
				var now = new Map(), o = box( el ), sx = window.pageXOffset || 0, sy = window.pageYOffset || 0;
				itemsOf( el, c.q ).forEach( function ( it ) {
					if ( ! visible( it ) ) { return; }
					var b = rel( it, o ), before = last.get( it ), run = anims.get( it );
					now.set( it, b );
					if ( run ) { // mid-glide: start from where it is on screen now
						var r = it.getBoundingClientRect();
						before = { x: r.left + sx - o.x, y: r.top + sy - o.y };
						try { run.cancel(); } catch ( e ) {}
						anims.delete( it );
					}
					if ( ! before ) { // a new item (added, or shown again)
						if ( c.n ) {
							// The fade replaces the item's opacity for its length; the grow is added to its own transform.
							try { it.animate( [ { opacity: 0 }, { opacity: 1 } ], { duration: dur, easing: 'ease-out' } ); } catch ( e ) {}
							try { it.animate( [ { transform: 'scale(.92)' }, { transform: 'scale(1)' } ], { duration: dur, easing: easing, composite: 'add' } ); } catch ( e ) {}
						}
						return;
					}
					var dx = before.x - b.x, dy = before.y - b.y;
					if ( Math.abs( dx ) < 0.5 && Math.abs( dy ) < 0.5 ) { return; }
					try {
						var a = it.animate( [ { transform: 'translate(' + dx + 'px,' + dy + 'px)' }, { transform: 'translate(0px,0px)' } ], { duration: dur, easing: easing, composite: 'add' } );
						anims.set( it, a );
						a.onfinish = function () { if ( anims.get( it ) === a ) { anims.delete( it ); } };
					} catch ( e ) {}
				} );
				last = now;
			}
			watchItems();
		};

		snapshot();
		if ( window.MutationObserver ) {
			new MutationObserver( function () { schedule( inst ); } ).observe( el, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'hidden', 'open', 'aria-expanded' ] } );
		}
		if ( window.ResizeObserver ) {
			ro = new ResizeObserver( function ( entries ) {
				// The element's own height glide resizes it every frame: that is not a change to react to.
				if ( hAnim && entries.every( function ( en ) { return en.target === el; } ) ) { return; }
				schedule( inst );
			} );
			ro.observe( el );
			watchItems();
		}
		// Fonts and images that load late move things without anyone asking for a glide: re-measure.
		window.addEventListener( 'load', snapshot );
	}

	function init( root ) {
		Array.prototype.forEach.call( ( root || document ).querySelectorAll( '[data-upw-layout]' ), setup );
	}
	window.upwLayout = { init: init };
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', function () { init(); } ); } else { init(); }
} )();
