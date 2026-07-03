/* Animation Engine — Hover "text_swap": label slides out, a second line slides in. */
(function () {
	(window.upwHoverFx = window.upwHoverFx || {}).text_swap = {
		pointer: false, reduceSkip: false,
		run: function (el) {
			if (el.__upwTextSwap) { return; }
			el.__upwTextSwap = true;
			var swap = el.getAttribute('data-hover-swap');
			var orig = el.textContent;
			if (swap === null || swap === '') { swap = orig; }
			// Rebuild as two stacked spans inside a clipped inline-block.
			el.textContent = '';
			var a = document.createElement('span');
			var b = document.createElement('span');
			a.className = 'sc-hover-swap-a';
			b.className = 'sc-hover-swap-b';
			a.textContent = orig;
			b.textContent = swap;
			b.setAttribute('aria-hidden', 'true');
			el.appendChild(a);
			el.appendChild(b);
		}
	};
})();
