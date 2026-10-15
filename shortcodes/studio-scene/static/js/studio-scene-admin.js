/*
 * Studio Scene — builder side (admin only).
 *
 * 1. A live summary under the Scene field ("Make it move": 7 layers, 7 s), or what is wrong with it.
 * 2. "Open in Animation Studio": opens the Studio in a new window, and talks to it over postMessage:
 *      Studio → us   { type: 'upw-studio:hello' }            once it has loaded
 *      us → Studio   { type: 'upw-studio:scene', scene }      this element's scene (or null)
 *      Studio → us   { type: 'upw-studio:save', scene }       "Save to page" was clicked
 *      us → Studio   { type: 'upw-studio:saved' }             written into the field
 *    Messages are only accepted from the Studio's own address AND from the window we opened, and
 *    replies go only to that address.
 */
(function ($) {
	'use strict';

	var cfg = window.upwStudioScene || {};
	var t = cfg.i18n || {};
	var studioUrl = cfg.studioUrl || 'https://animation-studio.unysonplus.com/';
	var studioOrigin;
	try { studioOrigin = new URL(studioUrl).origin; } catch (e) { return; }

	/** The window we opened and the field it edits. */
	var session = null;

	function parse(raw) {
		raw = String(raw || '').trim();
		if (!raw) { return { empty: true }; }
		var m = raw.match(/<script[^>]*type=["']application\/json["'][^>]*>([\s\S]*?)<\/script>/i);
		if (m) { raw = m[1].trim(); }
		try {
			var s = JSON.parse(raw);
			if (s && s.format === 'unysonplus-scene' && Array.isArray(s.layers)) { return { scene: s }; }
		} catch (e) { /* fall through */ }
		return { invalid: true };
	}

	function fmt(str) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return String(str).replace(/%(\d)\$s/g, function (_, n) { return args[Number(n) - 1]; }).replace(/%s/g, function () { return args[i++]; });
	}

	/** The Scene textarea that belongs to a tools block (same edit window). */
	function fieldFor(tools) {
		var $scope = $(tools).closest('.fw-options-modal, .fw-backend-options-virtual-context, form, .fw-modal, body');
		return $scope.find('.fw-backend-option-descriptor[data-fw-option-id="scene"] textarea, textarea.upw-studio-scene-field').first();
	}

	function setStatus(tools, text, tone) {
		$(tools).find('.upw-studio-status').text(text || '').attr('data-tone', tone || '');
	}

	function refresh(tools) {
		var r = parse(fieldFor(tools).val());
		if (r.empty) { setStatus(tools, t.empty, 'muted'); }
		else if (r.invalid) { setStatus(tools, t.invalid, 'error'); }
		else {
			var s = r.scene;
			setStatus(tools, fmt(t.summary, s.title || t.untitled, s.layers.length, s.duration), 'ok');
		}
	}

	function refreshAll(root) {
		$(root || document).find('.upw-studio-tools').each(function () { refresh(this); });
	}

	// Summaries when an edit window opens, and as the field is edited.
	$(document).on('fw:options:init', function (e, data) { refreshAll(data && data.$elements ? data.$elements : document); });
	$(document).on('input change', 'textarea.upw-studio-scene-field, .fw-backend-option-descriptor[data-fw-option-id="scene"] textarea', function () {
		var $scope = $(this).closest('.fw-options-modal, .fw-backend-options-virtual-context, form, .fw-modal, body');
		refreshAll($scope);
	});
	$(function () { refreshAll(); });

	$(document).on('click', '.upw-studio-open', function (e) {
		e.preventDefault();
		var tools = $(this).closest('.upw-studio-tools')[0];
		var $field = fieldFor(tools);
		var win = window.open(studioUrl + '?connect=unysonplus', 'upw-animation-studio');
		if (!win) {
			setStatus(tools, t.blocked, 'error');
			return;
		}
		session = { win: win, $field: $field, tools: tools };
		setStatus(tools, t.waiting, 'muted');
		try { win.focus(); } catch (err) { /* ignore */ }
	});

	window.addEventListener('message', function (e) {
		if (!session || e.origin !== studioOrigin || e.source !== session.win) { return; }
		var d = e.data || {};
		if (d.type === 'upw-studio:hello') {
			var r = parse(session.$field.val());
			session.win.postMessage({ type: 'upw-studio:scene', scene: r.scene || null, site: location.host }, studioOrigin);
			setStatus(session.tools, t.connected, 'ok');
		} else if (d.type === 'upw-studio:save' && d.scene && d.scene.format === 'unysonplus-scene') {
			session.$field.val(JSON.stringify(d.scene, null, 2)).trigger('input').trigger('change');
			setStatus(session.tools, t.saved, 'ok');
			session.win.postMessage({ type: 'upw-studio:saved' }, studioOrigin);
		}
	});
})(jQuery);
