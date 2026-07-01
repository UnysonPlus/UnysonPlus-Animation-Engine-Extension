/**
 * Animation Engine — Cursor settings reveal (admin only).
 *
 * Theme Settings → Animations → Cursor shows every sub-option at once, but most
 * only apply to one style (Trail → Dot+Ring/Comet/Blob, Glyph char → Glyph, …).
 * This hides the rows that don't apply to the currently-picked style so the panel
 * isn't cluttered. Pure client-side: if JS is off, every row simply stays visible
 * (each row's `desc` still says which style it serves), so nothing is ever lost.
 */
( function ( $ ) {
	// leaf option id => styles it applies to.
	var MAP = {
		'trail':        [ 'dot_ring', 'comet', 'blob' ],
		'glyph_char':   [ 'glyph' ],
		'custom_image': [ 'custom' ],
		'spot_radius':  [ 'spotlight' ],
		'spot_dim':     [ 'spotlight' ]
	};

	function $row( leaf ) {
		// Rows live inside the `animation_cursor` multi; match the id suffix so the
		// outer settings-page prefix doesn't matter.
		return $( '[id$="animation_cursor-' + leaf + '"]' );
	}

	function styleSelect() {
		return $( '[id$="animation_cursor-style"]' ).find( 'select' ).first();
	}

	function apply() {
		var $sel = styleSelect();
		if ( ! $sel.length ) {
			return;
		}
		var v = $sel.val();
		$.each( MAP, function ( leaf, styles ) {
			$row( leaf ).toggle( $.inArray( v, styles ) !== -1 );
		} );
	}

	$( function () {
		if ( ! styleSelect().length ) {
			return;
		}
		apply();
		// The image-picker plugin enhances the <select> and mirrors clicks back to
		// it as a `change`; listen there, plus a settle tick and a tile-click fallback.
		$( document ).on( 'change', '[id$="animation_cursor-style"] select', apply );
		$( document ).on( 'click', '[id$="animation_cursor-style"] .image_picker_selector li', function () {
			setTimeout( apply, 30 );
		} );
		setTimeout( apply, 300 );
	} );
} )( jQuery );
