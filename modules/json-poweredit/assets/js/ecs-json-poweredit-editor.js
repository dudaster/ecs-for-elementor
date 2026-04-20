/**
 * ECS JSON PowerEdit — Editor Script
 *
 * Injects an "Edit JSON" button into every Elementor repeater control rendered
 * in the panel. Clicking it opens a floating modal where the user can view,
 * edit and apply the repeater's data as formatted JSON.
 *
 * Flow:
 *   MutationObserver on #elementor-panel watches for repeater controls being
 *   inserted → injects the button → button click builds + shows the modal →
 *   Apply validates JSON (must be array of objects) → writes back via
 *   $e.run('document/elements/settings').
 */
( function ( $ ) {
	'use strict';

	// ── Constants ─────────────────────────────────────────────────────────────

	var MODAL_ID      = 'ecs-jpe-modal';
	var BTN_CLASS     = 'ecs-jpe-btn';
	var BTN_DONE_ATTR = 'data-ecs-jpe';

	// ── Helpers ───────────────────────────────────────────────────────────────

	/** Return the active widget container from the panel. */
	function getActiveContainer() {
		// Path 1: direct container in options (older Elementor)
		try {
			var c = window.elementor.getPanelView().content.currentView.options.container;
			if ( c ) { return c; }
		} catch ( _e ) {}

		// Path 2: container via editedElementView (Elementor 3.x)
		try {
			var ev = window.elementor.getPanelView().content.currentView.options.editedElementView;
			if ( ev && ev.container ) { return ev.container; }
		} catch ( _e ) {}

		// Path 3: derive from model ID
		try {
			var model = window.elementor.getPanelView().content.currentView.model;
			var id    = model && model.get && model.get( 'id' );
			if ( id ) { return window.elementor.getContainer( id ); }
		} catch ( _e ) {}

		return null;
	}

	/**
	 * Validate that the parsed value is an array of plain objects.
	 * Returns null on success, or an error string.
	 */
	function validate( parsed ) {
		if ( ! Array.isArray( parsed ) ) {
			return 'Root must be a JSON array ( [ … ] ).';
		}
		for ( var i = 0; i < parsed.length; i++ ) {
			var item = parsed[ i ];
			if ( item === null || typeof item !== 'object' || Array.isArray( item ) ) {
				return 'Each element must be an object ( { … } ). Problem at index ' + i + '.';
			}
		}
		return null;
	}

	// ── Modal ─────────────────────────────────────────────────────────────────

	/**
	 * Build the modal DOM once and cache it.  Re-opens update the textarea.
	 */
	function getOrCreateModal() {
		var existing = document.getElementById( MODAL_ID );
		if ( existing ) { return existing; }

		var modal = document.createElement( 'div' );
		modal.id  = MODAL_ID;
		modal.innerHTML = [
			'<div class="ecs-jpe-backdrop"></div>',
			'<div class="ecs-jpe-dialog">',
			'  <div class="ecs-jpe-header">',
			'    <span class="ecs-jpe-title">JSON PowerEdit</span>',
			'    <button class="ecs-jpe-close" title="Close">&times;</button>',
			'  </div>',
			'  <div class="ecs-jpe-meta"></div>',
			'  <textarea class="ecs-jpe-textarea" spellcheck="false"></textarea>',
			'  <div class="ecs-jpe-error"></div>',
			'  <div class="ecs-jpe-toolbar">',
			'    <button class="ecs-jpe-action" data-action="format">Format</button>',
			'    <button class="ecs-jpe-action" data-action="copy">Copy</button>',
			'    <button class="ecs-jpe-action" data-action="reset">Reset</button>',
			'    <button class="ecs-jpe-action" data-action="clear">Clear</button>',
			'    <button class="ecs-jpe-action ecs-jpe-apply" data-action="apply">Apply</button>',
			'  </div>',
			'</div>',
		].join( '' );

		document.body.appendChild( modal );
		return modal;
	}

	/**
	 * Open the modal for a specific repeater control.
	 *
	 * @param {string} controlKey  e.g. "slides"
	 * @param {string} widgetLabel e.g. "Slides"
	 * @param {Array}  currentVal  Current repeater value
	 * @param {Object} container   Elementor container
	 */
	function openModal( controlKey, widgetLabel, currentVal, container ) {
		var modal    = getOrCreateModal();
		var textarea = modal.querySelector( '.ecs-jpe-textarea' );
		var metaEl   = modal.querySelector( '.ecs-jpe-meta' );
		var errorEl  = modal.querySelector( '.ecs-jpe-error' );

		var originalJson = JSON.stringify( currentVal, null, 2 );
		textarea.value   = originalJson;
		metaEl.textContent = widgetLabel + '  ·  ' + controlKey + '  ·  ' + currentVal.length + ' item(s)';
		errorEl.textContent = '';
		errorEl.style.display = 'none';

		modal.classList.add( 'ecs-jpe-open' );
		textarea.focus();

		// ── Toolbar actions ──────────────────────────────────────────────────

		function setError( msg ) {
			errorEl.textContent = msg;
			errorEl.style.display = msg ? 'block' : 'none';
		}

		function handleAction( e ) {
			var action = e.target.dataset.action;
			if ( ! action ) { return; }

			if ( action === 'format' ) {
				try {
					var parsed = JSON.parse( textarea.value );
					textarea.value = JSON.stringify( parsed, null, 2 );
					setError( '' );
				} catch ( parseErr ) {
					setError( 'Invalid JSON — cannot format. ' + parseErr.message );
				}
				return;
			}

			if ( action === 'copy' ) {
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( textarea.value )
						.then( function () { flashButton( e.target, 'Copied!' ); } )
						.catch( function () { legacyCopy( textarea ); } );
				} else {
					legacyCopy( textarea );
				}
				return;
			}

			if ( action === 'reset' ) {
				textarea.value = originalJson;
				setError( '' );
				return;
			}

			if ( action === 'clear' ) {
				textarea.value = '[]';
				setError( '' );
				return;
			}

			if ( action === 'apply' ) {
				var raw;
				try {
					raw = JSON.parse( textarea.value );
				} catch ( jsonErr ) {
					setError( 'Invalid JSON: ' + jsonErr.message );
					return;
				}

				var validationError = validate( raw );
				if ( validationError ) {
					setError( validationError );
					return;
				}

				applyToContainer( container, controlKey, raw );
				closeModal( modal );
			}
		}

		// Remove previous listeners by cloning the toolbar
		var toolbar    = modal.querySelector( '.ecs-jpe-toolbar' );
		var newToolbar = toolbar.cloneNode( true );
		toolbar.parentNode.replaceChild( newToolbar, toolbar );
		newToolbar.addEventListener( 'click', handleAction );

		// Close triggers
		modal.querySelector( '.ecs-jpe-close' ).onclick    = function () { closeModal( modal ); };
		modal.querySelector( '.ecs-jpe-backdrop' ).onclick = function () { closeModal( modal ); };
	}

	function closeModal( modal ) {
		modal.classList.remove( 'ecs-jpe-open' );
	}

	/** Flash a button label briefly, then restore. */
	function flashButton( btn, label ) {
		var orig = btn.textContent;
		btn.textContent = label;
		setTimeout( function () { btn.textContent = orig; }, 1200 );
	}

	/** Fallback clipboard copy for older browsers. */
	function legacyCopy( textarea ) {
		textarea.select();
		try { document.execCommand( 'copy' ); } catch ( _e ) {}
		window.getSelection().removeAllRanges();
	}

	// ── Apply to Elementor ────────────────────────────────────────────────────

	function applyToContainer( container, controlKey, newValue ) {
		var settings = {};
		settings[ controlKey ] = newValue;

		try {
			$e.run( 'document/elements/settings', {
				containers : [ container ],
				settings   : settings,
				options    : { render: true },
			} );
		} catch ( err ) {
			// Fallback for very old Elementor versions
			container.settings.set( controlKey, newValue );
			container.render();
		}
	}

	// ── Button injection ──────────────────────────────────────────────────────

	/**
	 * Inject the "Edit JSON" button into a repeater control element that hasn't
	 * been processed yet.
	 */
	function injectButton( controlEl ) {
		if ( controlEl.hasAttribute( BTN_DONE_ATTR ) ) { return; }
		controlEl.setAttribute( BTN_DONE_ATTR, '1' );

		// Elementor does not add data-setting to repeater wrappers.
		// The control key is embedded in the class as elementor-control-{key},
		// where the key uses only [a-zA-Z0-9_] (no dashes).  Generic Elementor
		// classes like elementor-control-type-repeater contain dashes so the
		// regex below naturally skips them.
		var controlKey = controlEl.dataset.setting;
		if ( ! controlKey ) {
			controlEl.classList.forEach( function ( cls ) {
				if ( controlKey ) { return; }
				var m = cls.match( /^elementor-control-([a-zA-Z0-9_]+)$/ );
				if ( m ) { controlKey = m[ 1 ]; }
			} );
		}
		if ( ! controlKey ) { return; }

		var btn = document.createElement( 'button' );
		btn.className   = BTN_CLASS;
		btn.type        = 'button';
		btn.textContent = 'Edit JSON';
		btn.title       = 'Open JSON PowerEdit for this list';

		btn.addEventListener( 'click', function () {
			var container = getActiveContainer();
			if ( ! container ) { return; }

			var rawVal = container.settings.get( controlKey );
			var val    = rawVal;

			// Backbone Collections (Elementor repeater) expose toJSON()
			if ( val && typeof val.toJSON === 'function' ) {
				val = val.toJSON();
			}
			if ( ! Array.isArray( val ) ) {
				val = [];
			}

			// Widget label for display in the modal header
			var widgetType  = container.model && container.model.get( 'widgetType' );
			var widgetLabel = widgetType || 'Widget';
			try {
				var cache = window.elementor.widgetsCache[ widgetType ];
				if ( cache && cache.title ) { widgetLabel = cache.title; }
			} catch ( _e ) {}

			openModal( controlKey, widgetLabel, val, container );
		} );

		// Insert after the repeater's "Add Item" button row, or at the end
		var addRow = controlEl.querySelector( '.elementor-repeater-add' );
		if ( addRow ) {
			addRow.parentNode.insertBefore( btn, addRow.nextSibling );
		} else {
			controlEl.appendChild( btn );
		}
	}

	/** Scan the panel for unprocessed repeater controls. */
	function scanPanel() {
		var panel = document.getElementById( 'elementor-panel' );
		if ( ! panel ) { return; }

		var repeaters = panel.querySelectorAll(
			'.elementor-control-type-repeater:not([' + BTN_DONE_ATTR + '])'
		);
		repeaters.forEach( injectButton );
	}

	// ── Keyboard shortcut: Escape closes modal ────────────────────────────────

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			var modal = document.getElementById( MODAL_ID );
			if ( modal && modal.classList.contains( 'ecs-jpe-open' ) ) {
				closeModal( modal );
			}
		}
	} );

	// ── Initialisation ────────────────────────────────────────────────────────

	function init() {
		// Watch for Elementor panel DOM changes (tab switches, widget open/close)
		var panel = document.getElementById( 'elementor-panel' );
		if ( ! panel ) { return; }

		var observer = new MutationObserver( function () { scanPanel(); } );
		observer.observe( panel, { childList: true, subtree: true } );

		// Initial scan (panel may already show a widget)
		scanPanel();
	}

	// Elementor fires elementor:init as a jQuery event
	jQuery( window ).on( 'elementor:init', init );

	// Polling fallback — elementor:init may fire before this script runs
	var pollTimer = setInterval( function () {
		if ( window.elementor && window.elementor.getPanelView ) {
			clearInterval( pollTimer );
			init();
		}
	}, 200 );

}( jQuery ) );
