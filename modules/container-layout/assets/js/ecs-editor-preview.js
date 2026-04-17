/**
 * DTE Editor Preview — Container Custom Layout
 *
 * Runs in the Elementor editor parent frame (not the preview iframe).
 *
 * Approach:
 *  1. Wait for Elementor editor to initialise.
 *  2. When the preview iframe loads, find every .e-con.e-dte-custom container
 *     and ask the PHP AJAX handler to render the selected DTE Custom Layout
 *     template with placeholder HTML so we know WHERE children go.
 *  3. Parse the AJAX response, strip Elementor model attributes from template
 *     elements, then MOVE the live child DOM elements (real Elementor widgets)
 *     into their corresponding template slots.  The children stay editable.
 *  4. On every Elementor command (settings change, add/remove/move element),
 *     schedule a debounced re-injection (400 ms).
 *  5. If a container leaves dte-custom mode, restore children to .e-con-inner
 *     and remove the injected template structure.
 *
 * State tracking:
 *  container.dataset.dteState       — djb2 hash of (layoutId + children HTML)
 *  container.dataset.dteChildOrder  — comma-separated ordered child IDs
 */

/* global ecsEditorPreview, elementor */
( function () {
	'use strict';

	var iframeDoc          = null;
	var refreshTimer       = null;
	var domObserver        = null;
	var suppressObserver   = false;

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	var initialized  = false;
	var channelBound = false;
	var pollTimer    = null;

	function initListeners() {
		if ( initialized || ! window.elementor ) {
			return;
		}
		initialized = true;
		clearInterval( pollTimer );

		elementor.on( 'preview:loaded', onPreviewLoaded );

		var iframe = elementor.$preview && elementor.$preview[ 0 ];
		if ( iframe && iframe.contentDocument && iframe.contentDocument.body &&
		     iframe.contentDocument.body.children.length ) {
			onPreviewLoaded();
		}
	}

	if ( window.jQuery ) {
		jQuery( window ).on( 'elementor:init', initListeners );
	}
	window.addEventListener( 'elementor:init', initListeners );
	pollTimer = setInterval( function () {
		if ( window.elementor ) {
			initListeners();
		}
	}, 100 );

	function onPreviewLoaded() {
		var iframe = elementor.$preview && elementor.$preview[ 0 ];
		if ( ! iframe ) {
			return;
		}
		iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
		if ( ! iframeDoc ) {
			return;
		}
		setTimeout( injectAll, 800 );
		if ( ! channelBound ) {
			channelBound = true;
			// command:after catches undo/redo/add/remove; editor change catches settings panel edits.
			elementor.channels.data.on( 'command:after', scheduleRefresh );
			elementor.channels.editor.on( 'change', scheduleRefresh );
		}
		bindDomObserver();
	}

	// ── MutationObserver: detect new children added by Elementor ─────────────

	/**
	 * Watch for elements being inserted directly into .e-con-inner of any
	 * dte-custom container.  Elementor renders new widgets asynchronously
	 * (Backbone view), so command:after fires before the DOM is updated.
	 * The observer catches the insertion and schedules a re-injection.
	 */
	function bindDomObserver() {
		if ( domObserver ) {
			domObserver.disconnect();
		}
		if ( ! iframeDoc ) {
			return;
		}
		domObserver = new iframeDoc.defaultView.MutationObserver( function ( mutations ) {
			if ( suppressObserver ) {
				return;
			}
			var needRefresh = false;
			mutations.forEach( function ( mutation ) {
				if ( mutation.type !== 'childList' || ! mutation.addedNodes.length ) {
					return;
				}
				// Only care about nodes added inside a .e-dte-custom container.
				var target = mutation.target;
				var inCustom = target.closest && target.closest( '.e-dte-custom' );
				if ( ! inCustom ) {
					return;
				}
				// Only care if an element with data-id was added (a real Elementor element).
				mutation.addedNodes.forEach( function ( node ) {
					if ( node.nodeType === 1 && node.dataset && node.dataset.id ) {
						needRefresh = true;
					}
				} );
			} );
			if ( needRefresh ) {
				scheduleRefresh();
			}
		} );

		// Observe the entire preview document for subtree changes.
		domObserver.observe( iframeDoc.body, { childList: true, subtree: true } );
	}

	// ── Debounce ──────────────────────────────────────────────────────────────

	function scheduleRefresh() {
		clearTimeout( refreshTimer );
		refreshTimer = setTimeout( injectAll, 400 );
	}

	// ── Injection orchestration ────────────────────────────────────────────────

	function injectAll() {
		if ( ! iframeDoc ) {
			return;
		}

		// Restore containers that have left dte-custom mode.
		iframeDoc.querySelectorAll( '.dte-preview-active' ).forEach( function ( el ) {
			if ( ! el.classList.contains( 'e-dte-custom' ) ) {
				cleanupContainer( el );
			}
		} );

		iframeDoc.querySelectorAll( '.e-con.e-dte-custom' ).forEach( injectContainer );
	}

	// ── Per-container injection ────────────────────────────────────────────────

	function injectContainer( container ) {
		var layoutId = getLayoutId( container );
		if ( ! layoutId ) {
			if ( container.classList.contains( 'dte-preview-active' ) ) {
				cleanupContainer( container );
			}
			return;
		}

		var inner    = container.querySelector( ':scope > .e-con-inner' ) || container;
		var childEls = getChildEls( container, inner );
		var htmls    = childEls.map( function ( el ) { return el.outerHTML; } );

		// Skip if nothing changed since last injection.
		// Also include direct inner children IDs so newly added widgets
		// (not yet in dteChildOrder) always trigger a re-injection.
		var innerIds  = Array.from( inner.querySelectorAll( ':scope > [data-id]' ) )
			.map( function ( el ) { return el.dataset.id; } ).join( ',' );
		var stateKey = layoutId + ':' + djb2( htmls.join( '' ) + '|' + innerIds );
		if ( container.dataset.dteState === stateKey ) {
			return;
		}
		container.dataset.dteState = stateKey;

		// Restore children to inner before re-injecting.
		if ( container.classList.contains( 'dte-preview-active' ) ) {
			restoreToInner( container, inner );
		}

		// Re-read children now that they are back in inner.
		childEls = Array.from( inner.querySelectorAll( ':scope > [data-id]' ) );
		var childIds = childEls.map( function ( el ) { return el.dataset.id; } );
		htmls        = childEls.map( function ( el ) { return el.outerHTML; } );

		var formData = new FormData();
		formData.append( 'action', 'ecs_preview_layout' );
		formData.append( 'nonce', ecsEditorPreview.nonce );
		formData.append( 'layout_id', layoutId );
		htmls.forEach( function ( html ) {
			formData.append( 'children[]', html );
		} );

		fetch( ecsEditorPreview.ajaxUrl, { method: 'POST', body: formData } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( resp ) {
				// Guard: another update may have arrived while this was in-flight.
				if ( container.dataset.dteState !== stateKey ) {
					return;
				}
				if ( ! resp.success ) {
					return;
				}

				var doc     = container.ownerDocument;
				var tempDiv = doc.createElement( 'div' );
				tempDiv.innerHTML = resp.data.html;

				// Build a quick lookup of which IDs belong to live children.
				var childIdSet = Object.create( null );
				childIds.forEach( function ( id ) { childIdSet[ id ] = true; } );

				// Step 1 — find slot nodes by child data-id (before stripping).
				var slots = Object.create( null );
				childIds.forEach( function ( id ) {
					var slot = tempDiv.querySelector( '[data-id="' + id + '"]' );
					if ( slot ) {
						slots[ id ] = slot;
					}
				} );

				// Step 2 — strip Elementor model attributes from template's own
				//           elements so Elementor's click handler won't try to
				//           resolve them against the page model.
				Array.from( tempDiv.querySelectorAll( '[data-id]' ) ).forEach( function ( el ) {
					if ( ! childIdSet[ el.dataset.id ] ) {
						el.removeAttribute( 'data-id' );
						el.removeAttribute( 'data-element_type' );
						el.removeAttribute( 'data-widget_type' );
					}
				} );

				// Suppress the MutationObserver while we move DOM nodes so our
				// own insertions don't trigger a redundant scheduleRefresh().
				suppressObserver = true;

				// Step 3 — move each live child element into its template slot.
				childIds.forEach( function ( id ) {
					var slot   = slots[ id ];
					var liveEl = inner.querySelector( '[data-id="' + id + '"]' );
					if ( slot && liveEl ) {
						// replaceWith() moves liveEl from inner into tempDiv.
						slot.replaceWith( liveEl );
					}
				} );

				// Step 4 — mark every top-level template element for cleanup.
				//           With cycling there may be multiple template instances.
				Array.from( tempDiv.children ).forEach( function ( child ) {
					child.classList.add( 'dte-injected-structure' );
				} );

				// Step 5 — insert template structure at the start of inner.
				//           Remaining direct children of inner (overflow children
				//           without a slot) stay at the end naturally.
				var refNode = inner.firstChild;
				while ( tempDiv.firstChild ) {
					inner.insertBefore( tempDiv.firstChild, refNode );
				}

				// Persist child order and mark the container as active.
				container.dataset.dteChildOrder = childIds.join( ',' );
				container.classList.add( 'dte-preview-active' );

				// Re-enable observer after microtasks flush so MutationObserver
				// callbacks for our own DOM changes are already dispatched.
				Promise.resolve().then( function () {
					suppressObserver = false;
				} );
			} )
			.catch( function () {
				// On network error, clear state so the next refresh retries.
				if ( container.dataset.dteState === stateKey ) {
					delete container.dataset.dteState;
				}
			} );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Return the live child elements of a container.
	 *
	 * If children have already been moved into a template structure
	 * (dteChildOrder is set), locate them anywhere inside the container.
	 * Otherwise return the direct [data-id] children of inner.
	 */
	function getChildEls( container, inner ) {
		var order = container.dataset.dteChildOrder;
		if ( order ) {
			return order.split( ',' ).filter( Boolean ).map( function ( id ) {
				return container.querySelector( '[data-id="' + id + '"]' );
			} ).filter( Boolean );
		}
		return Array.from( inner.querySelectorAll( ':scope > [data-id]' ) );
	}

	/**
	 * Move live children back to direct children of inner, then remove
	 * the injected template structure.
	 *
	 * Elements that are direct children of inner but NOT in dteChildOrder are
	 * newly added widgets (Elementor appended them directly to inner while the
	 * template structure was in place).  They are re-appended at the END so
	 * the final child order is: [known children…, new children…].
	 */
	function restoreToInner( container, inner ) {
		var order = container.dataset.dteChildOrder;
		if ( ! order ) {
			return;
		}

		var knownIds = {};
		order.split( ',' ).filter( Boolean ).forEach( function ( id ) {
			knownIds[ id ] = true;
		} );

		// Collect newly added elements (direct inner children not in dteChildOrder)
		// before we start moving things around.
		var unknownEls = Array.from( inner.querySelectorAll( ':scope > [data-id]' ) )
			.filter( function ( el ) { return ! knownIds[ el.dataset.id ]; } );

		// Re-append known children to inner in the original order.
		order.split( ',' ).filter( Boolean ).forEach( function ( id ) {
			var el = container.querySelector( '[data-id="' + id + '"]' );
			if ( el ) {
				inner.appendChild( el );
			}
		} );

		// Remove all injected template structures (one per cycling pass).
		inner.querySelectorAll( '.dte-injected-structure' ).forEach( function ( el ) {
			el.remove();
		} );

		// Append newly added elements at the end.
		unknownEls.forEach( function ( el ) {
			inner.appendChild( el );
		} );
	}

	function cleanupContainer( container ) {
		var inner = container.querySelector( ':scope > .e-con-inner' ) || container;
		restoreToInner( container, inner );
		container.classList.remove( 'dte-preview-active' );
		delete container.dataset.dteState;
		delete container.dataset.dteChildOrder;
	}

	/**
	 * Return the dte_custom_layout_id setting of a container element by
	 * querying Elementor's in-memory model via elementor.getContainer().
	 */
	function getLayoutId( container ) {
		var elementId = container.dataset.id;
		if ( ! elementId ) {
			return 0;
		}
		try {
			var eContainer = window.elementor.getContainer( elementId );
			var val = eContainer &&
			          eContainer.settings &&
			          eContainer.settings.get( 'dte_custom_layout_id' );
			return val ? ( parseInt( val, 10 ) || 0 ) : 0;
		} catch ( e ) {
			return 0;
		}
	}

	/**
	 * djb2 hash — fast, good distribution for change-detection fingerprints.
	 */
	function djb2( str ) {
		var h = 5381;
		for ( var i = 0; i < str.length; i++ ) {
			h = ( ( h << 5 ) + h + str.charCodeAt( i ) ) | 0;
		}
		return ( h >>> 0 ).toString( 36 );
	}

} )();
