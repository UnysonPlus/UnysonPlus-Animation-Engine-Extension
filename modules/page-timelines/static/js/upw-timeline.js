/**
 * Page timelines — the visitor runtime (Animation Engine roadmap 5d/5e, the Animation Creator).
 *
 * A page timeline is made in Kinetic Scenes (page mode) and plays elements of the real page: each track is one
 * element (its builder key), with keyframes of Move X / Y (% of the element's own size), Scale, Rotate, Opacity and
 * Blur, each eased into by a CSS linear() / cubic-bezier() curve the Studio bakes from its named curves and springs.
 *
 *   { id, name, duration, repeat, yoyo, trigger: { type: 'load'|'in-view'|'hover'|'click'|'scroll', target, once, leave, threshold, pin, length },
 *     tracks: [ { target: '<item key>' | '.<class>', stagger?, order?, frames: [ { t, x?, y?, scale?, rotate?, opacity?, blur?, e?: '<css ease>' } ] } ] }
 *
 * A track whose target is a class (".card", Animation Creator A3) moves every element with that class, each `stagger`
 * seconds after the one before, in `order`: first to last (the page order), last to first, from the centre out, or
 * random (the same "random" every time, so the editor and visitors see one order).
 *
 * Motion is written to the individual CSS properties translate / rotate / scale (so an element's own transform is
 * kept), opacity and filter; clear() puts back what the element had. No layout is touched.
 *
 * API (window.upwTimeline):
 *   load(list, { edit })   replace the timelines (edit: held still for scrubbing; otherwise armed for visitors)
 *   seek(id, t)            show timeline `id` at t seconds (stops it)
 *   play(id, from?)        play it from `from` (default 0) to the end, with its repeat / yoyo
 *   arm(id)                wait for its trigger, as a visitor would (shows its first moment until then)
 *   clear()                stop everything and put the elements back
 *   command({ op, ... })   the same, from a message (the Live Editor canvas)
 *
 * Scroll (Animation Creator A5): the scroll position is the playhead. Unpinned, the timeline runs while its element
 * crosses the screen (from entering at the bottom to leaving at the top); with `pin`, the element holds in place
 * (position: sticky, in a wrapper `length` screen heights taller) while the timeline runs from start to end.
 *
 * Visitors: a <script type="application/json" class="upw-timelines"> on the page is read and armed on load (the
 * page-timelines module prints it on pages that have timelines). A timeline with `runsOn` (desktop / tablet / phone,
 * the Interactions breakpoints) plays only on those, and is put back or armed when the window crosses one.
 * Reduced motion shows each timeline's last moment and plays nothing (unless the engine's "Respect reduced motion"
 * setting is off). The Live Editor's Edit canvas holds them still: Kinetic Scenes drives it instead.
 */
( function () {
	'use strict';
	if ( window.upwTimeline ) { return; }

	var PROPS = [ 'x', 'y', 'scale', 'rotate', 'opacity', 'blur' ];
	var BLUR_PX = 10; // one Blur unit, in CSS pixels on the page
	var EASES = {};

	/** A 0..1 → 0..1 function from a CSS easing string (linear(), cubic-bezier(), a keyword, a named curve), from the
	 *  engine's one easing set (window.upwEase, static/js/upw-raf.js, this script's dependency). null = linear. */
	function easeOf( css ) {
		css = String( css || '' ).trim();
		if ( ! css || css === 'linear' || css === 'none' ) { return null; }
		if ( ! ( css in EASES ) ) { EASES[ css ] = window.upwEase ? window.upwEase( css ) : null; }
		return EASES[ css ];
	}

	function num( v ) { return typeof v === 'number' && isFinite( v ); }

	/** The value of one property at time t, or undefined when no keyframe sets it. */
	function valueAt( frames, key, t ) {
		var keyed = [];
		for ( var i = 0; i < frames.length; i++ ) { if ( num( frames[ i ][ key ] ) ) { keyed.push( frames[ i ] ); } }
		if ( ! keyed.length ) { return undefined; }
		if ( t <= keyed[ 0 ].t ) { return keyed[ 0 ][ key ]; }
		var last = keyed[ keyed.length - 1 ];
		if ( t >= last.t ) { return last[ key ]; }
		for ( var n = 1; n < keyed.length; n++ ) {
			if ( keyed[ n ].t > t ) {
				var a = keyed[ n - 1 ], b = keyed[ n ], p = ( t - a.t ) / ( b.t - a.t ), f = easeOf( b.e );
				if ( f ) { p = f( p ); }
				return a[ key ] + ( b[ key ] - a[ key ] ) * p;
			}
		}
		return last[ key ];
	}

	var R = function ( v ) { return Math.round( v * 1000 ) / 1000; };

	function resolve( id ) {
		if ( ! id ) { return null; }
		if ( /^\.[A-Za-z_-][A-Za-z0-9_-]*$/.test( String( id ) ) ) { try { return document.querySelector( id ); } catch ( e ) { return null; } }
		var q = String( id ).replace( /["\\]/g, '' );
		return document.querySelector( '[data-fw-item-id="' + q + '"]' ) || document.querySelector( '[data-upw-tl~="' + q + '"]' );
	}

	var isClass = function ( target ) { return /^\.[A-Za-z_-][A-Za-z0-9_-]*$/.test( String( target || '' ) ); };

	/** The elements a track moves: its element, or every element with its class (looked up again when the page changed). */
	function elsOf( track ) {
		if ( ! track.els || ! track.els.length || ! track.els[ 0 ].isConnected ) {
			if ( isClass( track.target ) ) {
				try { track.els = [].slice.call( document.querySelectorAll( track.target ) ); } catch ( e ) { track.els = []; }
			} else {
				var one = resolve( track.target );
				track.els = one ? [ one ] : [];
			}
			track.delays = delaysOf( track, track.els.length );
		}
		return track.els;
	}
	function elOf( track ) { return elsOf( track )[ 0 ] || null; }

	/** When each of n elements starts: rank in the chosen order × stagger. */
	function delaysOf( track, n ) {
		var st = track.stagger || 0, rank = [], i;
		for ( i = 0; i < n; i++ ) { rank.push( i ); }
		if ( track.order === 'last' ) { rank = rank.map( function ( r ) { return n - 1 - r; } ); }
		else if ( track.order === 'center' ) {
			var mid = ( n - 1 ) / 2, by = rank.slice().sort( function ( a, b ) { return Math.abs( a - mid ) - Math.abs( b - mid ) || a - b; } );
			by.forEach( function ( idx, r ) { rank[ idx ] = r; } );
		} else if ( track.order === 'random' ) {
			var seed = 0, str = String( track.target );
			for ( i = 0; i < str.length; i++ ) { seed = ( seed * 31 + str.charCodeAt( i ) ) >>> 0; }
			var rnd = function () { seed = ( seed * 1664525 + 1013904223 ) >>> 0; return seed / 4294967296; };
			var shuffled = rank.slice();
			for ( i = n - 1; i > 0; i-- ) { var j = Math.floor( rnd() * ( i + 1 ) ), tmp = shuffled[ i ]; shuffled[ i ] = shuffled[ j ]; shuffled[ j ] = tmp; }
			shuffled.forEach( function ( idx, r ) { rank[ idx ] = r; } );
		}
		return rank.map( function ( r ) { return r * st; } );
	}

	/** Remember what an element had before a timeline touched it (put back by clear()). */
	function keep( el ) {
		if ( el.__upwTl ) { return; }
		var s = el.style;
		el.__upwTl = { translate: s.translate, rotate: s.rotate, scale: s.scale, opacity: s.opacity, filter: s.filter, willChange: s.willChange };
	}
	function restore( el ) {
		var o = el && el.__upwTl;
		if ( ! o ) { return; }
		for ( var k in o ) { if ( Object.prototype.hasOwnProperty.call( o, k ) ) { el.style[ k ] = o[ k ] || ''; } }
		delete el.__upwTl;
	}

	function render( tl, t ) {
		tl.t = t;
		for ( var i = 0; i < tl.tracks.length; i++ ) {
			var tr = tl.tracks[ i ], els = elsOf( tr );
			for ( var n = 0; n < els.length; n++ ) {
				var el = els[ n ], lt = t - ( tr.delays[ n ] || 0 );
				keep( el );
				var v = {};
				for ( var p = 0; p < PROPS.length; p++ ) { v[ PROPS[ p ] ] = valueAt( tr.frames, PROPS[ p ], lt ); }
				var s = el.style;
				if ( v.x !== undefined || v.y !== undefined ) { s.translate = R( v.x || 0 ) + '% ' + R( v.y || 0 ) + '%'; }
				if ( v.rotate !== undefined ) { s.rotate = R( v.rotate ) + 'deg'; }
				if ( v.scale !== undefined ) { s.scale = String( R( Math.max( 0, v.scale ) ) ); }
				if ( v.opacity !== undefined ) { s.opacity = String( R( Math.max( 0, Math.min( 1, v.opacity ) ) ) ); }
				if ( v.blur !== undefined ) { s.filter = v.blur > 0.001 ? 'blur(' + R( v.blur * BLUR_PX ) + 'px)' : el.__upwTl.filter || ''; }
			}
		}
	}

	function clean( def ) {
		if ( ! def || typeof def !== 'object' || ! def.id ) { return null; }
		var tracks = ( Array.isArray( def.tracks ) ? def.tracks : [] ).map( function ( tr ) {
			var frames = ( Array.isArray( tr && tr.frames ) ? tr.frames : [] ).filter( function ( f ) { return f && num( f.t ); } )
				.sort( function ( a, b ) { return a.t - b.t; } );
			return {
				target: String( ( tr && tr.target ) || '' ), frames: frames, els: null,
				stagger: Math.max( 0, Math.min( 5, Number( tr && tr.stagger ) || 0 ) ),
				order: [ 'last', 'center', 'random' ].indexOf( tr && tr.order ) >= 0 ? tr.order : 'first',
			};
		} ).filter( function ( tr ) { return tr.target && tr.frames.length; } );
		var trig = def.trigger && typeof def.trigger === 'object' ? def.trigger : {};
		return {
			id: String( def.id ),
			duration: Math.max( 0.05, Number( def.duration ) || 1 ),
			repeat: Math.round( Number( def.repeat ) || 0 ), // -1 = forever
			yoyo: !! def.yoyo,
			trigger: {
				type: [ 'load', 'in-view', 'hover', 'click', 'scroll' ].indexOf( trig.type ) >= 0 ? trig.type : 'load',
				pin: !! trig.pin,
				length: Math.max( 0.5, Math.min( 10, Number( trig.length ) || 2 ) ),
				target: trig.target ? String( trig.target ) : '',
				once: trig.once !== false,
				leave: trig.leave === 'stay' ? 'stay' : 'reverse',
				toggle: !! trig.toggle,
				threshold: Math.max( 0, Math.min( 1, Number( trig.threshold ) || 0.25 ) ),
				delay: Math.max( 0, Number( trig.delay ) || 0 ),
			},
			runsOn: Array.isArray( def.runsOn ) ? def.runsOn.map( String ) : null,
			tracks: tracks, t: 0, raf: 0, off: [],
		};
	}

	var TL = {};
	var editing = false;
	var cfg = window.upwTimelineCfg || {};
	var reduced = cfg.reducedMotion !== false && !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );

	/** Runs on: phone < 768 px, tablet 768–991 px, desktop ≥ 992 px (the Interactions breakpoints). */
	var DEVICE_MQ = { desktop: '(min-width: 992px)', tablet: '(min-width: 768px) and (max-width: 991.98px)', phone: '(max-width: 767.98px)' };
	function deviceOk( runsOn ) {
		if ( ! Array.isArray( runsOn ) || runsOn.length >= 3 || ! window.matchMedia ) { return true; }
		return runsOn.some( function ( k ) { return DEVICE_MQ[ k ] && window.matchMedia( DEVICE_MQ[ k ] ).matches; } );
	}

	// Playback runs on the engine's shared frame loop (window.upwAnimRaf, static/js/upw-raf.js, this script's dependency).
	var RAF = window.upwAnimRaf;
	function stop( tl ) { if ( tl.raf ) { RAF.remove( tl.raf ); tl.raf = 0; } }

	/**
	 * Play from `from` towards `to` (seconds). With repeat / yoyo (forward plays only), it goes on: a yoyo
	 * repeat counts each way as one play, so repeat 1 + yoyo = there and back.
	 */
	function run( tl, from, to, loops ) {
		stop( tl );
		var d = tl.duration, dir = to >= from ? 1 : -1, t = from, last = 0, left = loops;
		var step = function ( now ) {
			if ( ! last ) { last = now; }
			var dt = Math.min( 0.5, ( now - last ) / 1000 ); // real time, even when frames are dropped (a tab in the background)
			last = now;
			t += dt * dir;
			if ( ( dir > 0 && t >= to ) || ( dir < 0 && t <= to ) ) {
				if ( left !== 0 && ( tl.repeat !== 0 ) ) {
					if ( left > 0 ) { left--; }
					if ( tl.yoyo ) { var over = dir > 0 ? t - to : to - t; dir = -dir; var a = to; to = dir > 0 ? d : 0; t = a + over * dir; }
					else { t = dir > 0 ? 0 : d; }
				} else {
					render( tl, to );
					tl.raf = 0;
					return false;
				}
			}
			render( tl, Math.max( 0, Math.min( d, t ) ) );
		};
		render( tl, t );
		tl.raf = RAF.add( step );
	}
	function loopsOf( tl ) { return tl.repeat < 0 ? -1 : tl.repeat * ( tl.yoyo ? 1 : 1 ); }

	function play( id, from ) {
		var tl = TL[ id ];
		if ( ! tl ) { return; }
		if ( reduced && ! editing ) { stop( tl ); render( tl, tl.duration ); return; }
		run( tl, num( from ) ? from : 0, tl.duration, loopsOf( tl ) );
	}

	function disarm( tl ) {
		tl.off.forEach( function ( f ) { try { f(); } catch ( e ) {} } );
		tl.off = [];
		stop( tl );
	}

	function arm( id ) {
		var tl = TL[ id ];
		if ( ! tl ) { return; }
		disarm( tl );
		tl.armed = true;
		var g = tl.trigger;
		if ( reduced && ! editing ) { render( tl, tl.duration ); return; }
		render( tl, 0 );
		var on = function ( el, ev, fn ) { el.addEventListener( ev, fn ); tl.off.push( function () { el.removeEventListener( ev, fn ); } ); };
		var target = resolve( g.target ) || ( tl.tracks[ 0 ] && elOf( tl.tracks[ 0 ] ) );
		var later = function ( fn ) { if ( g.delay > 0 ) { var h = setTimeout( fn, g.delay * 1000 ); tl.off.push( function () { clearTimeout( h ); } ); } else { fn(); } };
		if ( g.type === 'load' || ! target ) {
			later( function () { play( id ); } );
		} else if ( g.type === 'hover' ) {
			on( target, 'mouseenter', function () { run( tl, tl.t, tl.duration, 0 ); } );
			on( target, 'mouseleave', function () { if ( g.leave === 'reverse' ) { run( tl, tl.t, 0, 0 ); } } );
			on( target, 'focusin', function () { run( tl, tl.t, tl.duration, 0 ); } );
			on( target, 'focusout', function () { if ( g.leave === 'reverse' ) { run( tl, tl.t, 0, 0 ); } } );
		} else if ( g.type === 'click' ) {
			var fwd = false;
			on( target, 'click', function () {
				if ( g.toggle ) { fwd = ! fwd; run( tl, tl.t, fwd ? tl.duration : 0, 0 ); }
				else { play( id ); }
			} );
		} else if ( g.type === 'scroll' ) {
			var vh = function () { return window.innerHeight || 1; }, wrap = null, pending = 0;
			// Pinned: the element holds in place in a taller wrapper (never on the editor's canvas, where the page must
			// keep its own layout to stay editable).
			if ( g.pin && ! editing && target.parentNode ) {
				wrap = document.createElement( 'div' );
				wrap.className = 'upw-tl-pin';
				target.parentNode.insertBefore( wrap, target );
				wrap.appendChild( target );
				var oldPos = target.style.position, oldTop = target.style.top;
				var size = function () { wrap.style.height = ( target.offsetHeight + g.length * vh() ) + 'px'; };
				size();
				target.style.position = 'sticky';
				target.style.top = '0px';
				tl.off.push( function () {
					if ( wrap.parentNode ) { wrap.parentNode.insertBefore( target, wrap ); wrap.parentNode.removeChild( wrap ); }
					target.style.position = oldPos; target.style.top = oldTop;
				} );
				on( window, 'resize', size );
			}
			// The shared scroll-progress formula (upw-raf.js): pass-through for an unpinned element, progress through
			// the pinned stretch (wrapper height = element + length screens) for a pinned one.
			var follow = function () {
				pending = 0;
				var p = wrap ? window.upwScrollProgress( wrap, { pin: true, stageH: target.offsetHeight } ) : window.upwScrollProgress( target );
				render( tl, p * tl.duration );
				return false; // one frame only: the next scroll event asks again
			};
			var onScroll = function () { if ( ! pending ) { pending = RAF.add( follow ); } };
			on( window, 'scroll', onScroll );
			on( window, 'resize', onScroll );
			tl.off.push( function () { if ( pending ) { RAF.remove( pending ); pending = 0; } } );
			follow();
		} else if ( g.type === 'in-view' ) {
			// The engine's shared in-view watcher (window.upwInView, upw-raf.js).
			var seen = false;
			tl.off.push( window.upwInView( target, function ( inView ) {
				if ( inView ) {
					seen = true;
					later( function () { play( id ); } );
				} else if ( ! g.once && seen ) {
					stop( tl ); render( tl, 0 );
				}
			}, { threshold: g.threshold, once: g.once } ) );
		} else {
			play( id );
		}
	}

	function restoreTrack( tr ) { ( tr.els || [] ).forEach( restore ); }

	function clear() {
		Object.keys( TL ).forEach( function ( id ) {
			var tl = TL[ id ];
			disarm( tl );
			tl.tracks.forEach( restoreTrack );
		} );
		TL = {};
	}

	function load( list, opts ) {
		clear();
		editing = !! ( opts && opts.edit );
		( Array.isArray( list ) ? list : [] ).forEach( function ( d ) { var tl = clean( d ); if ( tl ) { TL[ tl.id ] = tl; } } );
		if ( ! editing ) {
			Object.keys( TL ).forEach( function ( id ) { if ( deviceOk( TL[ id ].runsOn ) ) { arm( id ); } } );
			watchDevices();
		}
	}

	/** Visitors: a window crossing a breakpoint arms the timelines that now run, and puts back the ones that stop. */
	var watching = false;
	function watchDevices() {
		if ( watching || ! window.matchMedia ) { return; }
		watching = true;
		Object.keys( DEVICE_MQ ).forEach( function ( k ) {
			var mq = window.matchMedia( DEVICE_MQ[ k ] );
			var on = function () {
				if ( editing ) { return; }
				Object.keys( TL ).forEach( function ( id ) {
					var tl = TL[ id ];
					if ( ! tl.runsOn ) { return; }
					var ok = deviceOk( tl.runsOn );
					if ( ok && ! tl.armed ) { arm( id ); }
					else if ( ! ok && tl.armed ) { disarm( tl ); tl.armed = false; tl.tracks.forEach( restoreTrack ); }
				} );
			};
			if ( mq.addEventListener ) { mq.addEventListener( 'change', on ); } else if ( mq.addListener ) { mq.addListener( on ); }
		} );
	}

	/**
	 * Replace the definitions but keep the elements where they are (the Studio sends the timelines on every edit):
	 * elements no longer in any track are put back.
	 */
	function update( list, opts ) {
		editing = !! ( opts && opts.edit );
		var next = {}, keepEls = [];
		( Array.isArray( list ) ? list : [] ).forEach( function ( d ) { var tl = clean( d ); if ( tl ) { next[ tl.id ] = tl; tl.tracks.forEach( function ( tr ) { keepEls = keepEls.concat( elsOf( tr ) ); } ); } } );
		Object.keys( TL ).forEach( function ( id ) {
			disarm( TL[ id ] );
			TL[ id ].tracks.forEach( function ( tr ) { ( tr.els || [] ).forEach( function ( el ) { if ( keepEls.indexOf( el ) < 0 ) { restore( el ); } } ); } );
		} );
		TL = next;
	}

	function seek( id, t ) {
		var tl = TL[ id ];
		if ( ! tl ) { return; }
		stop( tl );
		render( tl, Math.max( 0, Math.min( tl.duration, Number( t ) || 0 ) ) );
	}

	var api = {
		load: load, update: update, seek: seek, play: play, arm: arm, clear: clear,
		stop: function ( id ) { if ( TL[ id ] ) { stop( TL[ id ] ); } },
		list: function () { return Object.keys( TL ); },
		command: function ( p ) {
			if ( ! p || typeof p !== 'object' ) { return; }
			if ( p.op === 'load' ) { load( p.timelines, { edit: p.edit !== false } ); }
			else if ( p.op === 'update' ) {
				update( p.timelines, { edit: p.edit !== false } );
				if ( p.id && TL[ p.id ] ) {
					// Editing one timeline: the elements of the others show as they are on the page.
					var mine = [];
					TL[ p.id ].tracks.forEach( function ( tr ) { mine = mine.concat( elsOf( tr ) ); } );
					Object.keys( TL ).forEach( function ( id ) {
						if ( id === p.id ) { return; }
						TL[ id ].tracks.forEach( function ( tr ) { elsOf( tr ).forEach( function ( el ) { if ( mine.indexOf( el ) < 0 ) { restore( el ); } } ); } );
					} );
					seek( p.id, num( p.t ) ? p.t : 0 );
				}
			}
			else if ( p.op === 'seek' ) { seek( p.id, p.t ); }
			else if ( p.op === 'play' ) { play( p.id, p.from ); }
			else if ( p.op === 'arm' ) { arm( p.id ); } // the author asked to see it: played even with reduced motion
			else if ( p.op === 'stop' ) { if ( TL[ p.id ] ) { stop( TL[ p.id ] ); } }
			else if ( p.op === 'clear' ) { clear(); }
		},
	};
	window.upwTimeline = api;

	// Visitors: the page's timelines, printed by the server, armed once the page is ready.
	function boot() {
		var nodes = document.querySelectorAll( 'script.upw-timelines[type="application/json"]' ), all = [];
		for ( var i = 0; i < nodes.length; i++ ) {
			try { var d = JSON.parse( nodes[ i ].textContent || '[]' ); all = all.concat( Array.isArray( d ) ? d : [ d ] ); } catch ( e ) {}
		}
		// The Live Editor's Edit canvas holds motion still; Kinetic Scenes plays the timelines there when it is open.
		if ( all.length && ! window.upwEditCanvas ) {
			load( all );
			// The first moments are inline now: the head CSS that held them until the runtime came (no flash) steps
			// aside, so it can never outlast the motion (a cleared blur falling back to the CSS one, say).
			document.documentElement.classList.remove( 'upw-tl-on' );
		}
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
} )();
