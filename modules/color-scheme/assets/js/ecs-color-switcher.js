/**
 * DTE Dark Mode Switcher
 *
 * Handles three display modes (toggle, dual, dropdown) for the
 * DTE Dark Mode Switcher widget.  Persists the user's choice via
 * a cookie so PHP can add data-dte-scheme="alt" to <html> before
 * first paint — cache-safe.
 *
 * No dependencies — vanilla JS, loaded in the footer.
 */
(function () {
	'use strict';

	// Guard against double-init when both DTE and ECS plugins are active simultaneously.
	if ( window.__dteDmsReady ) { return; }
	window.__dteDmsReady = true;

	var COOKIE_NAME = 'dte_color_scheme';
	var ALT_VALUE   = 'alt';

	/**
	 * Get the current active scheme ('alt' or null).
	 */
	function getCurrentScheme() {
		return document.documentElement.getAttribute('data-dte-scheme');
	}

	/**
	 * Apply a scheme by setting / removing the attribute on <html>.
	 */
	function applyScheme(scheme) {
		if (scheme === ALT_VALUE) {
			document.documentElement.setAttribute('data-dte-scheme', ALT_VALUE);
		} else {
			document.documentElement.removeAttribute('data-dte-scheme');
		}
	}

	/**
	 * Persist the choice as a cookie so both PHP (server-side, for cache-aware
	 * pages) and the inline anti-FOUC script can read it before first paint.
	 */
	function persistScheme(scheme) {
		if (scheme === ALT_VALUE) {
			document.cookie = COOKIE_NAME + '=alt; path=/; max-age=31536000; SameSite=Lax';
		} else {
			document.cookie = COOKIE_NAME + '=; path=/; max-age=0; SameSite=Lax';
		}
	}

	/**
	 * Return true if the user has a manually stored scheme preference.
	 */
	function hasCookie() {
		return /(?:^|;\s*)dte_color_scheme=/.test( document.cookie );
	}

	/**
	 * Sync every widget on the page to the current scheme.
	 * Handles toggle, dual, and dropdown display types.
	 */
	function syncAllWidgets() {
		var isAlt = getCurrentScheme() === ALT_VALUE;

		document.querySelectorAll('.dte-dms-wrap').forEach(function (wrap) {
			// Wrapper gets .is-alt for CSS toggle state switching
			wrap.classList.toggle('is-alt', isAlt);

			var display = wrap.dataset.display;

			if (display === 'toggle') {
				var btn = wrap.querySelector('.dte-dms-btn');
				if (btn) {
					btn.classList.toggle('is-active', isAlt);
				}

			} else if (display === 'dual') {
				wrap.querySelectorAll('.dte-dms-btn').forEach(function (btn) {
					var isActive = btn.dataset.scheme === ALT_VALUE ? isAlt : !isAlt;
					btn.classList.toggle('is-active', isActive);
					btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
				});

			} else if (display === 'dropdown') {
				var select = wrap.querySelector('.dte-dms-select');
				if (select) {
					select.value = isAlt ? ALT_VALUE : 'default';
				}
			}
		});
	}

	/**
	 * Click handler — handles toggle and dual buttons.
	 */
	function handleClick(event) {
		var btn = event.target.closest('.dte-dms-btn');
		if (!btn) { return; }

		var wrap = btn.closest('.dte-dms-wrap');
		if (!wrap) { return; }

		var newScheme;

		if (wrap.dataset.display === 'dual') {
			// Each dual button carries its target scheme explicitly
			newScheme = btn.dataset.scheme;
		} else {
			// Toggle: flip the current scheme
			newScheme = getCurrentScheme() === ALT_VALUE ? 'default' : ALT_VALUE;
		}

		applyScheme(newScheme);
		persistScheme(newScheme);
		syncAllWidgets();
	}

	/**
	 * Change handler — handles dropdown.
	 */
	function handleDropdownChange(event) {
		var select = event.target;
		if (!select.classList.contains('dte-dms-select')) { return; }

		applyScheme(select.value);
		persistScheme(select.value);
		syncAllWidgets();
	}

	// ── Init ─────────────────────────────────────────────────────────────────

	function init() {
		// Sync widgets to whatever the anti-FOUC script already set on <html>
		syncAllWidgets();

		// System Auto: if enabled and no manual preference stored,
		// follow live OS theme changes.
		var cfg = window.dteSchemeConfig || {};
		if ( cfg.systemAuto ) {
			var mq = window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' );
			if ( mq ) {
				mq.addEventListener( 'change', function ( e ) {
					if ( ! hasCookie() ) {
						applyScheme( e.matches ? ALT_VALUE : 'default' );
						syncAllWidgets();
					}
				} );
			}
		}

		// Delegate on document so Elementor re-renders don't require re-binding
		document.addEventListener('click', handleClick);
		document.addEventListener('change', handleDropdownChange);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
