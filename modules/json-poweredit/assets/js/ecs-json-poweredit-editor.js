/**
 * ECS JSON PowerEdit — Editor Script
 */
( function ( $ ) {
	'use strict';

	var ACCESSIBILITY_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.88 122.88" width="13" height="13" fill="currentColor" style="vertical-align:middle;margin-right:4px"><path d="M61.44,0A61.46,61.46,0,1,1,18,18,61.21,61.21,0,0,1,61.44,0Zm-.39,74.18L52.1,98.91a4.94,4.94,0,0,1-2.58,2.83A5,5,0,0,1,42.7,95.5l6.24-17.28a26.3,26.3,0,0,0,1.17-4,40.64,40.64,0,0,0,.54-4.18c.24-2.53.41-5.27.54-7.9s.22-5.18.29-7.29c.09-2.63-.62-2.8-2.73-3.3l-.44-.1-18-3.39A5,5,0,0,1,27.08,46a5,5,0,0,1,5.05-7.74l19.34,3.63c.77.07,1.52.16,2.31.25a57.64,57.64,0,0,0,7.18.53A81.13,81.13,0,0,0,69.9,42c.9-.1,1.75-.21,2.6-.29l18.25-3.42A5,5,0,0,1,94.5,39a5,5,0,0,1,1.3,7,5,5,0,0,1-3.21,2.09L75.15,51.37c-.58.13-1.1.22-1.56.29-1.82.31-2.72.47-2.61,3.06.08,1.89.31,4.15.61,6.51.35,2.77.81,5.71,1.29,8.4.31,1.77.6,3.19,1,4.55s.79,2.75,1.39,4.42l6.11,16.9a5,5,0,0,1-6.82,6.24,4.94,4.94,0,0,1-2.58-2.83L63,74.23,62,72.4l-1,1.78Zm.39-53.52a8.83,8.83,0,1,1-6.24,2.59,8.79,8.79,0,0,1,6.24-2.59Zm36.35,4.43a51.42,51.42,0,1,0,15,36.35,51.27,51.27,0,0,0-15-36.35Z"/></svg>';

	var MODAL_ID      = 'ecs-jpe-modal';
	var BTN_CLASS     = 'ecs-jpe-btn';
	var BTN_DONE_ATTR = 'data-ecs-jpe';

	// ── Helpers ───────────────────────────────────────────────────────────────

	function getActiveContainer() {
		try {
			var c = window.elementor.getPanelView().content.currentView.options.container;
			if ( c ) { return c; }
		} catch ( _e ) {}
		try {
			var ev = window.elementor.getPanelView().content.currentView.options.editedElementView;
			if ( ev && ev.container ) { return ev.container; }
		} catch ( _e ) {}
		try {
			var model = window.elementor.getPanelView().content.currentView.model;
			var id    = model && model.get && model.get( 'id' );
			if ( id ) { return window.elementor.getContainer( id ); }
		} catch ( _e ) {}
		return null;
	}

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

	function esc( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	// ── Tree rendering ────────────────────────────────────────────────────────

	function valuePreview( val ) {
		if ( val === null )              return '<span class="ecs-jpe-tv-null">null</span>';
		if ( typeof val === 'boolean' )  return '<span class="ecs-jpe-tv-bool">' + val + '</span>';
		if ( typeof val === 'number' )   return '<span class="ecs-jpe-tv-num">'  + val + '</span>';
		if ( typeof val === 'string' )   return '<span class="ecs-jpe-tv-str">"' + esc( val.length > 60 ? val.slice( 0, 60 ) + '…' : val ) + '"</span>';
		if ( Array.isArray( val ) )      return '<span class="ecs-jpe-tv-meta">[' + val.length + ']</span>';
		if ( typeof val === 'object' )   return '<span class="ecs-jpe-tv-meta">{' + Object.keys( val ).length + '}</span>';
		return '';
	}

	function renderNode( val, keyLabel, depth ) {
		var indent = depth * 16;

		if ( val !== null && typeof val === 'object' ) {
			var isArr    = Array.isArray( val );
			var entries  = isArr ? val.map( function( v, i ) { return [ i, v ]; } ) : Object.entries( val );
			var count    = entries.length;
			var brackets = isArr ? [ '[', ']' ] : [ '{', '}' ];
			var preview  = entries.slice( 0, 2 ).map( function( e ) { return valuePreview( e[1] ); } ).join( ', ' );

			var childrenHtml = entries.map( function( entry ) {
				var k = isArr ? ( 'Item ' + ( parseInt( entry[0] ) + 1 ) ) : entry[0];
				return renderNode( entry[1], k, depth + 1 );
			} ).join( '' );

			return '<div class="ecs-jpe-tn" style="--jpe-indent:' + indent + 'px">' +
				'<div class="ecs-jpe-tn-row">' +
					'<button class="ecs-jpe-tn-toggle" type="button" aria-expanded="true">▾</button>' +
					( keyLabel !== undefined ? '<span class="ecs-jpe-tn-key">' + esc( String( keyLabel ) ) + '</span><span class="ecs-jpe-tn-sep">: </span>' : '' ) +
					'<span class="ecs-jpe-tv-meta">' + brackets[0] + '</span>' +
					'<span class="ecs-jpe-tn-count">' + count + ( isArr ? ' items' : ' props' ) + '</span>' +
					'<span class="ecs-jpe-tn-preview">' + preview + '</span>' +
				'</div>' +
				'<div class="ecs-jpe-tn-children">' + childrenHtml + '</div>' +
				'<div class="ecs-jpe-tn-close">' + brackets[1] + '</div>' +
			'</div>';
		}

		return '<div class="ecs-jpe-tl" style="--jpe-indent:' + indent + 'px">' +
			( keyLabel !== undefined ? '<span class="ecs-jpe-tn-key">' + esc( String( keyLabel ) ) + '</span><span class="ecs-jpe-tn-sep">: </span>' : '' ) +
			valuePreview( val ) +
		'</div>';
	}

	function renderTree( data ) {
		if ( ! Array.isArray( data ) || data.length === 0 ) {
			return '<p class="ecs-jpe-tree-empty">No items</p>';
		}
		return data.map( function( item, i ) {
			return renderNode( item, 'Item ' + ( i + 1 ), 0 );
		} ).join( '' );
	}

	function bindTreeEvents( treeEl ) {
		treeEl.addEventListener( 'click', function( e ) {
			var btn = e.target.closest( '.ecs-jpe-tn-toggle' );
			if ( ! btn ) return;
			var node     = btn.closest( '.ecs-jpe-tn' );
			var children = node.querySelector( ':scope > .ecs-jpe-tn-children' );
			var close    = node.querySelector( ':scope > .ecs-jpe-tn-close' );
			var expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
			btn.setAttribute( 'aria-expanded', ! expanded );
			btn.textContent     = expanded ? '▸' : '▾';
			children.style.display = expanded ? 'none' : '';
			if ( close ) close.style.display = expanded ? 'none' : '';
			var preview = node.querySelector( ':scope > .ecs-jpe-tn-row .ecs-jpe-tn-preview' );
			if ( preview ) preview.style.display = expanded ? '' : 'none';
		} );
	}

	// ── Modal ─────────────────────────────────────────────────────────────────

	function getOrCreateModal() {
		var existing = document.getElementById( MODAL_ID );
		if ( existing ) { return existing; }

		var modal = document.createElement( 'div' );
		modal.id  = MODAL_ID;
		modal.innerHTML = [
			'<div class="ecs-jpe-backdrop"></div>',
			'<div class="ecs-jpe-dialog">',
			'  <div class="ecs-jpe-header">',
			'    <div class="ecs-jpe-header-icon">{}</div>',
			'    <span class="ecs-jpe-title">JSON PowerEdit</span>',
			'    <div class="ecs-jpe-header-actions">',
			'      <button class="ecs-jpe-close" title="Close">&times;</button>',
			'    </div>',
			'  </div>',
			'  <div class="ecs-jpe-meta"></div>',
			'  <textarea class="ecs-jpe-textarea" spellcheck="false"></textarea>',
			'  <div class="ecs-jpe-tree-toolbar" style="display:none">',
			'    <button class="ecs-jpe-action" data-tree-action="expand">Expand All</button>',
			'    <button class="ecs-jpe-action" data-tree-action="collapse">Collapse All</button>',
			'  </div>',
			'  <div class="ecs-jpe-tree" style="display:none"></div>',
			'  <div class="ecs-jpe-error"></div>',
			'  <div class="ecs-jpe-toolbar">',
			'    <button class="ecs-jpe-action" data-action="tree" id="ecs-jpe-tree-btn">Accessibility</button>',
			'    <button class="ecs-jpe-action" data-action="format">Format</button>',
			'    <button class="ecs-jpe-action" data-action="copy">Copy</button>',
			'    <button class="ecs-jpe-action" data-action="reset">Reset</button>',
			'    <button class="ecs-jpe-action" data-action="clear">Clear</button>',
			'    <button class="ecs-jpe-action ecs-jpe-apply" data-action="apply">Apply</button>',
			'  </div>',
			'</div>',
		].join( '' );

		document.body.appendChild( modal );

		var tb = modal.querySelector( '#ecs-jpe-tree-btn' );
		if ( tb ) { tb.innerHTML = ACCESSIBILITY_SVG + 'Accessibility'; tb.removeAttribute( 'id' ); }

		// Tree toolbar and tree toggle listeners are attached once here so they
		// don't accumulate on every openModal() / switchToTree() call.
		var treeEl      = modal.querySelector( '.ecs-jpe-tree' );
		var treeToolbar = modal.querySelector( '.ecs-jpe-tree-toolbar' );

		treeToolbar.addEventListener( 'click', function( e ) {
			var act = e.target.dataset.treeAction;
			if ( act === 'expand' )   setAllNodes( treeEl, true );
			if ( act === 'collapse' ) setAllNodes( treeEl, false );
		} );

		bindTreeEvents( treeEl );

		return modal;
	}

	function setAllNodes( treeEl, expand ) {
		treeEl.querySelectorAll( '.ecs-jpe-tn' ).forEach( function( node ) {
			var btn      = node.querySelector( ':scope > .ecs-jpe-tn-row .ecs-jpe-tn-toggle' );
			var children = node.querySelector( ':scope > .ecs-jpe-tn-children' );
			var close    = node.querySelector( ':scope > .ecs-jpe-tn-close' );
			var preview  = node.querySelector( ':scope > .ecs-jpe-tn-row .ecs-jpe-tn-preview' );
			if ( ! btn ) return;
			btn.setAttribute( 'aria-expanded', expand );
			btn.textContent = expand ? '▾' : '▸';
			if ( children ) children.style.display = expand ? '' : 'none';
			if ( close )    close.style.display    = expand ? '' : 'none';
			if ( preview )  preview.style.display  = expand ? 'none' : '';
		} );
	}

	function openModal( controlKey, widgetLabel, currentVal, container ) {
		var modal       = getOrCreateModal();
		var textarea    = modal.querySelector( '.ecs-jpe-textarea' );
		var treeEl      = modal.querySelector( '.ecs-jpe-tree' );
		var treeToolbar = modal.querySelector( '.ecs-jpe-tree-toolbar' );
		var metaEl      = modal.querySelector( '.ecs-jpe-meta' );
		var errorEl     = modal.querySelector( '.ecs-jpe-error' );

		var originalJson = JSON.stringify( currentVal, null, 2 );
		var isTreeMode   = false;

		textarea.value      = originalJson;
		metaEl.textContent  = widgetLabel + '  ·  ' + controlKey + '  ·  ' + currentVal.length + ' item(s)';
		errorEl.style.display = 'none';

		// Always start in raw mode.
		textarea.style.display      = '';
		treeEl.style.display        = 'none';
		treeToolbar.style.display   = 'none';
		var treeBtn = modal.querySelector( '[data-action="tree"]' );
		if ( treeBtn ) { treeBtn.innerHTML = ACCESSIBILITY_SVG + 'Accessibility'; treeBtn.classList.remove( 'ecs-jpe-action--active' ); }

		modal.classList.add( 'ecs-jpe-open' );
		textarea.focus();

		function setError( msg ) {
			errorEl.textContent   = msg;
			errorEl.style.display = msg ? 'block' : 'none';
		}

		function switchToTree() {
			var parsed;
			try { parsed = JSON.parse( textarea.value ); } catch ( e ) { setError( 'Invalid JSON — fix before switching to Tree view.' ); return; }
			isTreeMode                  = true;
			treeEl.innerHTML            = renderTree( parsed );
			textarea.style.display      = 'none';
			treeEl.style.display        = '';
			treeToolbar.style.display   = '';
			treeBtn.innerHTML           = '{ } Raw JSON';
			treeBtn.classList.add( 'ecs-jpe-action--active' );
			setError( '' );
		}

		function switchToRaw() {
			isTreeMode                  = false;
			textarea.style.display      = '';
			treeEl.style.display        = 'none';
			treeToolbar.style.display   = 'none';
			treeBtn.innerHTML           = ACCESSIBILITY_SVG + 'Accessibility';
			treeBtn.classList.remove( 'ecs-jpe-action--active' );
		}

		function handleAction( e ) {
			var action = e.target.dataset.action;
			if ( ! action ) { return; }

			if ( action === 'tree' ) {
				isTreeMode ? switchToRaw() : switchToTree();
				return;
			}

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
				var text = textarea.value;
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text )
						.then( function () { flashButton( e.target, 'Copied!' ); } )
						.catch( function () { legacyCopy( textarea ); } );
				} else {
					legacyCopy( textarea );
				}
				return;
			}

			if ( action === 'reset' ) { textarea.value = originalJson; setError( '' ); if ( isTreeMode ) switchToTree(); return; }
			if ( action === 'clear' ) { textarea.value = '[]'; setError( '' ); if ( isTreeMode ) switchToTree(); return; }

			if ( action === 'apply' ) {
				var raw;
				try {
					raw = JSON.parse( textarea.value );
				} catch ( jsonErr ) {
					if ( isTreeMode ) switchToRaw();
					setError( 'Invalid JSON: ' + jsonErr.message );
					return;
				}
				var validationError = validate( raw );
				if ( validationError ) { setError( validationError ); return; }
				applyToContainer( container, controlKey, raw );
				closeModal( modal );
			}
		}

		var toolbar    = modal.querySelector( '.ecs-jpe-toolbar' );
		var newToolbar = toolbar.cloneNode( true );
		toolbar.parentNode.replaceChild( newToolbar, toolbar );
		newToolbar.addEventListener( 'click', handleAction );

		// Re-bind tree button reference after toolbar replacement.
		treeBtn = modal.querySelector( '[data-action="tree"]' );

		modal.querySelector( '.ecs-jpe-close' ).onclick    = function () { closeModal( modal ); };
		modal.querySelector( '.ecs-jpe-backdrop' ).onclick = function () { closeModal( modal ); };
	}

	function closeModal( modal ) {
		modal.classList.remove( 'ecs-jpe-open' );
	}

	function flashButton( btn, label ) {
		var orig = btn.textContent;
		btn.textContent = label;
		setTimeout( function () { btn.textContent = orig; }, 1200 );
	}

	function legacyCopy( textarea ) {
		textarea.select();
		try { document.execCommand( 'copy' ); } catch ( _e ) {}
		window.getSelection().removeAllRanges();
	}

	// ── Apply to Elementor ────────────────────────────────────────────────────

	function applyToContainer( container, controlKey, newValue ) {
		var collection    = container.settings.get( controlKey );
		var existingCount = ( collection && collection.models ) ? collection.models.length : 0;

		// Remove existing items back-to-front so indices stay valid.
		for ( var i = existingCount - 1; i >= 0; i-- ) {
			try {
				$e.run( 'document/repeater/remove', { container: container, name: controlKey, index: i } );
			} catch ( _ ) {}
		}

		// Insert new items — document/repeater/insert creates the per-item
		// containers that the panel views depend on (direct collection
		// manipulation is deprecated since Elementor 3.0).
		newValue.forEach( function ( row, idx ) {
			try {
				$e.run( 'document/repeater/insert', {
					container : container,
					name      : controlKey,
					model     : row,
					options   : { at: idx },
				} );
			} catch ( _ ) {}
		} );

		try {
			$e.internal( 'document/save/set-is-modified', { status: true } );
		} catch ( _ ) {
			try { window.elementor.saver.setFlagEditorChange(); } catch ( _ ) {}
		}
	}

	// ── Button injection ──────────────────────────────────────────────────────

	function injectButton( controlEl ) {
		if ( controlEl.hasAttribute( BTN_DONE_ATTR ) ) { return; }
		controlEl.setAttribute( BTN_DONE_ATTR, '1' );

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

		btn.addEventListener( 'click', function () {
			var container = getActiveContainer();
			if ( ! container ) { return; }

			var rawVal = container.settings.get( controlKey );
			var val    = rawVal;
			if ( val && typeof val.toJSON === 'function' ) { val = val.toJSON(); }
			if ( ! Array.isArray( val ) ) { val = []; }

			var widgetType  = container.model && container.model.get( 'widgetType' );
			var widgetLabel = widgetType || 'Widget';
			try {
				var cache = window.elementor.widgetsCache[ widgetType ];
				if ( cache && cache.title ) { widgetLabel = cache.title; }
			} catch ( _e ) {}

			openModal( controlKey, widgetLabel, val, container );
		} );

		var addRow = controlEl.querySelector( '.elementor-repeater-add' );
		if ( addRow ) {
			addRow.parentNode.insertBefore( btn, addRow.nextSibling );
		} else {
			controlEl.appendChild( btn );
		}
	}

	function scanPanel() {
		var panel = document.getElementById( 'elementor-panel' );
		if ( ! panel ) { return; }
		panel.querySelectorAll( '.elementor-control-type-repeater:not([' + BTN_DONE_ATTR + '])' )
			.forEach( injectButton );
	}

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			var modal = document.getElementById( MODAL_ID );
			if ( modal && modal.classList.contains( 'ecs-jpe-open' ) ) { closeModal( modal ); }
		}
	} );

	function init() {
		var panel = document.getElementById( 'elementor-panel' );
		if ( ! panel ) { return; }
		new MutationObserver( function () { scanPanel(); } ).observe( panel, { childList: true, subtree: true } );
		scanPanel();
	}

	jQuery( window ).on( 'elementor:init', init );
	var pollTimer = setInterval( function () {
		if ( window.elementor && window.elementor.getPanelView ) { clearInterval( pollTimer ); init(); }
	}, 200 );

}( jQuery ) );
