/**
 * ECS Dynamic Repeater Builder — Editor UI
 */

( function ( $ ) {
	'use strict';

	const cfg     = window.ecsDrb || {};
	const AJAX    = cfg.ajaxUrl || '';
	const NONCE   = cfg.nonce   || '';
	const SOURCES = cfg.sources || [];
	const L10N    = cfg.l10n    || {};

	// ── State ─────────────────────────────────────────────────────────────────

	const state = {
		controlKey    : null,
		widgetLabel   : null,
		container     : null,
		postId        : null,

		sourceType    : SOURCES[0]?.id || 'posts',
		sourceConfig  : {},
		sourceSchema  : [],
		sourceFields  : [],  // [{ key, label }] from server

		repeaterFields: [],  // [{ key, label }] from Elementor widget config
		mapping       : [],  // [{ repeater_field, source_field, before, after, fallback, skip_if_empty }]

		applyMode     : 'replace',
		emptyState    : 'keep',
		useCache      : true,
		cacheTtl      : 300,

		presets       : [],
		isBound       : false,
		bindingConfig : null,

		activeTab     : 'source',
		previewRows   : null,
		isLoading     : false,
	};

	// ── Helpers ───────────────────────────────────────────────────────────────

	function esc( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function post( action, data ) {
		return new Promise( ( resolve, reject ) => {
			$.post( AJAX, { action, nonce: NONCE, ...data } )
				.done( r => r.success ? resolve( r.data ) : reject( r.data?.message || 'Error' ) )
				.fail( () => reject( 'Request failed' ) );
		} );
	}

	function setStatus( msg, isError ) {
		const el = document.querySelector( '.ecs-drb-status' );
		if ( ! el ) return;
		el.textContent = msg;
		el.className = 'ecs-drb-status' + ( isError ? ' ecs-drb-status--error' : '' );
	}

	function setLoading( on ) {
		state.isLoading = on;
		const btn = document.querySelector( '.ecs-drb-btn-generate' );
		if ( btn ) btn.disabled = on;
		const overlay = document.querySelector( '.ecs-drb-loading-overlay' );
		if ( overlay ) overlay.style.display = on ? 'flex' : 'none';
	}

	// ── Elementor integration ─────────────────────────────────────────────────

	function getActiveContainer() {
		try {
			const panel = window.elementor.getPanelView();
			const view  = panel.content.currentView;
			if ( view?.options?.container ) return view.options.container;
			if ( view?.options?.editedElementView?.container ) return view.options.editedElementView.container;
			const id = view?.$el?.closest( '[data-id]' )?.attr( 'data-id' );
			if ( id ) return window.elementor.getContainer( id );
		} catch ( _ ) {}
		return null;
	}

	function getRepeaterFields( container, controlKey ) {
		// Try from the global widget config — available in Elementor 3.x+.
		const widgetType = container?.model?.get( 'widgetType' );
		if ( widgetType ) {
			const wCfg = window.elementor?.config?.widgets?.[ widgetType ];
			const ctrl = wCfg?.controls?.[ controlKey ];
			if ( ctrl?.fields ) {
				return Object.entries( ctrl.fields )
					.filter( ( [ k ] ) => ! k.startsWith( '_' ) )
					.map( ( [ key, def ] ) => ( {
						key,
						label : def.label || key,
						type  : def.type  || 'text',
					} ) );
			}
		}

		// Fallback: extract from existing repeater values (no type info available).
		const val = container?.settings?.get( controlKey );
		if ( val ) {
			const first = val?.models?.[0]?.attributes || ( Array.isArray( val ) ? val[0] : null );
			if ( first ) {
				return Object.keys( first )
					.filter( k => ! k.startsWith( '_' ) )
					.map( k => ( { key: k, label: k, type: 'text' } ) );
			}
		}

		return [];
	}

	/** Returns { fieldKey: elementorType } map for the current repeater. */
	function getFieldTypes() {
		return Object.fromEntries( state.repeaterFields.map( f => [ f.key, f.type ] ) );
	}

	function getCurrentPostId() {
		return window.elementor?.config?.document?.id || 0;
	}

	// ── Auto-suggest mapping ───────────────────────────────────────────────────
	//
	// Given the target repeater fields and available source fields, returns
	// a pre-filled mapping array. Called automatically when fields are fetched.
	//
	// Trade-off to consider:
	//   - Exact match (fast, zero false positives, misses synonyms like title/name)
	//   - Keyword match (catches title↔post_title, image↔thumbnail_url, etc.)
	//   - Edit-distance (catches typos but slow and over-eager on short keys)
	//
	// Implement your strategy below. The function receives:
	//   repeaterFields — [{ key, label }]  (target)
	//   sourceFields   — [{ key, label }]  (available)
	// It must return an array of mapping objects (same shape as state.mapping).

	function autoSuggestMappings( repeaterFields, sourceFields ) {
		const srcKeys = sourceFields.map( f => f.key );

		// Aliases: repeater key fragment → ranked list of source key candidates.
		const aliases = {
			title       : [ 'post_title', 'name', 'title' ],
			name        : [ 'name', 'post_title', 'title' ],
			text        : [ 'post_title', 'title', 'name', 'post_excerpt' ],
			label       : [ 'post_title', 'name', 'title' ],
			description : [ 'post_excerpt', 'description', 'post_content', 'content' ],
			excerpt     : [ 'post_excerpt', 'description' ],
			content     : [ 'post_content', 'post_excerpt', 'content' ],
			summary     : [ 'post_excerpt', 'description' ],
			url         : [ 'post_url', 'link', 'url' ],
			link        : [ 'post_url', 'link', 'url' ],
			href        : [ 'post_url', 'link', 'url' ],
			image       : [ 'thumbnail_id', 'thumbnail_url', 'image', 'photo' ],
			img         : [ 'thumbnail_id', 'thumbnail_url' ],
			photo       : [ 'thumbnail_url', 'thumbnail_id' ],
			thumbnail   : [ 'thumbnail_id', 'thumbnail_url' ],
			icon        : [ 'thumbnail_url', 'thumbnail_id' ],
			date        : [ 'post_date', 'date', 'post_modified' ],
			author      : [ 'post_author', 'author' ],
			id          : [ 'post_id', 'term_id', 'id' ],
			slug        : [ 'post_slug', 'slug' ],
			category    : [ 'category_names', 'taxonomy' ],
			categories  : [ 'category_names' ],
			count       : [ 'count' ],
			taxonomy    : [ 'taxonomy' ],
			parent      : [ 'parent_id', 'parent' ],
		};

		return repeaterFields.map( rf => {
			const key = rf.key.toLowerCase();

			// 1. Exact match.
			let matched = srcKeys.find( k => k === rf.key );

			// 2. Alias lookup — match on any fragment of the repeater key.
			if ( ! matched ) {
				for ( const [ fragment, candidates ] of Object.entries( aliases ) ) {
					if ( key === fragment || key.includes( fragment ) || fragment.includes( key ) ) {
						matched = candidates.find( c => srcKeys.includes( c ) );
						if ( matched ) break;
					}
				}
			}

			// 3. Substring fallback.
			if ( ! matched ) {
				matched = srcKeys.find( k => k.includes( key ) || key.includes( k ) );
			}

			return {
				repeater_field : rf.key,
				source_field   : matched ? `{${ matched }}` : '',
				fallback       : '',
				skip_if_empty  : false,
			};
		} );
	}

	// ── Modal ─────────────────────────────────────────────────────────────────

	function getOrCreateModal() {
		let modal = document.getElementById( 'ecs-drb-modal' );
		if ( modal ) return modal;

		modal = document.createElement( 'div' );
		modal.id = 'ecs-drb-modal';
		modal.innerHTML = `
			<div class="ecs-drb-backdrop"></div>
			<div class="ecs-drb-dialog">
				<div class="ecs-drb-header">
					<div class="ecs-drb-header-left">
						<div class="ecs-drb-header-icon">✦</div>
						<div class="ecs-drb-header-title-wrap">
							<span class="ecs-drb-title">${ esc( L10N.title ) }</span>
							<span class="ecs-drb-badge">Pro</span>
						</div>
					</div>
					<div class="ecs-drb-header-meta">
						<span class="ecs-drb-meta-widget"></span>
						<span class="ecs-drb-meta-sep"> › </span>
						<span class="ecs-drb-meta-control"></span>
					</div>
					<div class="ecs-drb-header-actions">
						<button class="ecs-drb-icon-btn" aria-label="Close">✕</button>
					</div>
				</div>

				<div class="ecs-drb-tabs">
					<button class="ecs-drb-tab" data-tab="source">${ esc( L10N.tabSource ) }</button>
					<button class="ecs-drb-tab" data-tab="mapping">${ esc( L10N.tabMapping ) }</button>
					<button class="ecs-drb-tab" data-tab="options">${ esc( L10N.tabOptions ) }</button>
					<button class="ecs-drb-tab" data-tab="presets">${ esc( L10N.tabPresets ) }</button>
					<button class="ecs-drb-tab" data-tab="binding">${ esc( L10N.tabBinding ) }</button>
				</div>

				<div class="ecs-drb-tab-body"></div>

				<div class="ecs-drb-loading-overlay" style="display:none">
					<span>${ esc( L10N.loading ) }</span>
				</div>

				<div class="ecs-drb-footer">
					<div class="ecs-drb-status"></div>
					<div class="ecs-drb-footer-actions">
						<button class="ecs-drb-btn-secondary ecs-drb-btn-generate">${ esc( L10N.generate ) }</button>
						<button class="ecs-drb-btn-primary ecs-drb-btn-apply" disabled>${ esc( L10N.apply ) }</button>
					</div>
				</div>
			</div>
		`;

		document.body.appendChild( modal );

		// Events.
		modal.querySelector( '.ecs-drb-backdrop' ).addEventListener( 'click', closeModal );
		modal.querySelector( '.ecs-drb-icon-btn' ).addEventListener( 'click', closeModal );

		modal.querySelectorAll( '.ecs-drb-tab' ).forEach( btn => {
			btn.addEventListener( 'click', () => setActiveTab( btn.dataset.tab ) );
		} );

		modal.querySelector( '.ecs-drb-btn-generate' ).addEventListener( 'click', handleGenerate );
		modal.querySelector( '.ecs-drb-btn-apply' ).addEventListener( 'click', handleApply );

		document.addEventListener( 'keydown', e => {
			if ( e.key === 'Escape' && modal.classList.contains( 'ecs-drb-open' ) ) closeModal();
		} );

		return modal;
	}

	function openModal( controlKey, widgetLabel, container ) {
		state.controlKey     = controlKey;
		state.widgetLabel    = widgetLabel;
		state.container      = container;
		state.postId         = getCurrentPostId();
		state.repeaterFields = getRepeaterFields( container, controlKey );
		state.previewRows    = null;

		const modal = getOrCreateModal();
		modal.querySelector( '.ecs-drb-header-meta .ecs-drb-meta-widget' ).textContent  = widgetLabel;
		modal.querySelector( '.ecs-drb-header-meta .ecs-drb-meta-control' ).textContent = controlKey;

		// Load presets and binding status in background.
		loadPresets();
		loadBindingStatus();

		// Load initial source schema if not yet loaded.
		if ( state.sourceSchema.length === 0 ) {
			loadSourceSchema( state.sourceType );
		} else {
			setActiveTab( state.activeTab );
		}

		modal.classList.add( 'ecs-drb-open' );
		document.body.style.overflow = 'hidden';
	}

	function closeModal() {
		const modal = document.getElementById( 'ecs-drb-modal' );
		if ( modal ) modal.classList.remove( 'ecs-drb-open' );
		document.body.style.overflow = '';
	}

	// ── Tabs ──────────────────────────────────────────────────────────────────

	function setActiveTab( tabId ) {
		state.activeTab = tabId;
		const modal = document.getElementById( 'ecs-drb-modal' );
		if ( ! modal ) return;

		modal.querySelectorAll( '.ecs-drb-tab' ).forEach( btn => {
			btn.classList.toggle( 'active', btn.dataset.tab === tabId );
		} );

		const body = modal.querySelector( '.ecs-drb-tab-body' );
		switch ( tabId ) {
			case 'source':  body.innerHTML = renderSourceTab();  bindSourceTab( body );  break;
			case 'mapping': body.innerHTML = renderMappingTab(); bindMappingTab( body ); break;
			case 'options': body.innerHTML = renderOptionsTab(); bindOptionsTab( body ); break;
			case 'presets': body.innerHTML = renderPresetsTab(); bindPresetsTab( body ); break;
			case 'binding': body.innerHTML = renderBindingTab(); bindBindingTab( body ); break;
		}
	}

	// ── Source Tab ────────────────────────────────────────────────────────────

	function renderSourceTab() {
		const typeBtns = SOURCES.map( s => `
			<button class="ecs-drb-src-type${ s.id === state.sourceType ? ' active' : '' }" data-source="${ esc( s.id ) }">
				${ esc( s.label ) }
			</button>
		` ).join( '' );

		const fields = state.sourceSchema.map( f => renderConfigField( f ) ).join( '' );

		return `
			<div class="ecs-drb-source-tab">
				<div class="ecs-drb-src-types">${ typeBtns }</div>
				<div class="ecs-drb-src-fields">${ fields || `<p class="ecs-drb-hint">${ esc( L10N.loading ) }</p>` }</div>
			</div>
		`;
	}

	function renderConfigField( f ) {
		const val = state.sourceConfig[ f.key ] ?? f.default ?? '';
		const placeholder = f.placeholder ? `placeholder="${ esc( f.placeholder ) }"` : '';

		switch ( f.type ) {
			case 'select': {
				const opts = ( f.options || [] ).map( o =>
					`<option value="${ esc( o.value ) }"${ String( val ) === String( o.value ) ? ' selected' : '' }>${ esc( o.label ) }</option>`
				).join( '' );
				return `<div class="ecs-drb-field">
					<label>${ esc( f.label ) }</label>
					<select data-cfg="${ esc( f.key ) }">${ opts }</select>
				</div>`;
			}
			case 'number': {
				const min = f.min != null ? `min="${ f.min }"` : '';
				const max = f.max != null ? `max="${ f.max }"` : '';
				return `<div class="ecs-drb-field">
					<label>${ esc( f.label ) }</label>
					<input type="number" data-cfg="${ esc( f.key ) }" value="${ esc( val ) }" ${ min } ${ max } ${ placeholder }>
				</div>`;
			}
			case 'toggle':
				return `<div class="ecs-drb-field ecs-drb-field--toggle">
					<label>
						<input type="checkbox" data-cfg="${ esc( f.key ) }"${ val ? ' checked' : '' }>
						${ esc( f.label ) }
					</label>
				</div>`;
			case 'textarea':
				return `<div class="ecs-drb-field">
					<label>${ esc( f.label ) }</label>
					<textarea data-cfg="${ esc( f.key ) }" rows="6" ${ placeholder }>${ esc( val ) }</textarea>
				</div>`;
			default: {
				const acAttr = f.autocomplete ? ` data-autocomplete="${ esc( f.autocomplete ) }"` : '';
				return `<div class="ecs-drb-field ecs-drb-field--autocomplete-wrap">
					<label>${ esc( f.label ) }</label>
					<input type="text" data-cfg="${ esc( f.key ) }" value="${ esc( val ) }" ${ placeholder }${ acAttr } autocomplete="off">
				</div>`;
			}
		}
	}

	function bindSourceTab( body ) {
		// Source type toggle.
		body.querySelectorAll( '.ecs-drb-src-type' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				state.sourceType   = btn.dataset.source;
				state.sourceConfig = {};
				state.sourceFields = [];
				state.mapping      = [];
				loadSourceSchema( state.sourceType );
			} );
		} );

		// Config field changes → update state.sourceConfig.
		body.querySelectorAll( '[data-cfg]' ).forEach( el => {
			el.addEventListener( 'change', () => syncConfigFromDOM( body ) );
			el.addEventListener( 'input',  () => syncConfigFromDOM( body ) );
		} );

		// Autocomplete for fields that declare it.
		body.querySelectorAll( 'input[data-autocomplete="taxonomy_terms"]' ).forEach( input => {
			bindTermAutocomplete( input, body );
		} );
	}

	// ── Term autocomplete ─────────────────────────────────────────────────────

	function bindTermAutocomplete( input, body ) {
		let dropdown = null;
		let debounceTimer = null;

		function getDropdown() {
			if ( dropdown ) return dropdown;
			dropdown = document.createElement( 'div' );
			dropdown.className = 'ecs-drb-ac-dropdown';
			input.parentElement.style.position = 'relative';
			input.parentElement.appendChild( dropdown );
			return dropdown;
		}

		function closeDropdown() {
			if ( dropdown ) dropdown.style.display = 'none';
		}

		function getCurrentToken() {
			const val   = input.value;
			const pos   = input.selectionStart || val.length;
			const chunk = val.slice( 0, pos );
			const parts = chunk.split( ',' );
			return parts[ parts.length - 1 ].trim();
		}

		function insertToken( slug ) {
			const val    = input.value;
			const pos    = input.selectionStart || val.length;
			const before = val.slice( 0, pos );
			const after  = val.slice( pos );
			const parts  = before.split( ',' );
			parts[ parts.length - 1 ] = ' ' + slug;
			const newVal = parts.join( ',' ) + ( after.startsWith( ',' ) ? after : ( after ? ', ' + after.trim() : '' ) );
			input.value = newVal.replace( /^,\s*/, '' );
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			closeDropdown();
			input.focus();
		}

		function showSuggestions( terms ) {
			const dd = getDropdown();
			if ( ! terms.length ) { closeDropdown(); return; }

			dd.innerHTML = terms.map( t =>
				`<div class="ecs-drb-ac-item" data-slug="${ esc( t.slug ) }">
					<span class="ecs-drb-ac-slug">${ esc( t.slug ) }</span>
					<span class="ecs-drb-ac-name">${ esc( t.name ) }</span>
					<span class="ecs-drb-ac-count">${ t.count }</span>
				</div>`
			).join( '' );

			dd.querySelectorAll( '.ecs-drb-ac-item' ).forEach( item => {
				item.addEventListener( 'mousedown', e => {
					e.preventDefault();
					insertToken( item.dataset.slug );
				} );
			} );

			dd.style.display = 'block';
		}

		function fetchTerms( search ) {
			const taxonomy = state.sourceConfig.taxonomy || '';
			if ( ! taxonomy ) { closeDropdown(); return; }

			post( 'ecs_drb_get_terms', { taxonomy, search } )
				.then( data => showSuggestions( data.terms || [] ) )
				.catch( () => closeDropdown() );
		}

		input.addEventListener( 'input', () => {
			clearTimeout( debounceTimer );
			debounceTimer = setTimeout( () => {
				const token = getCurrentToken();
				fetchTerms( token );
			}, 200 );
		} );

		input.addEventListener( 'focus', () => {
			const token = getCurrentToken();
			fetchTerms( token );
		} );

		input.addEventListener( 'blur', () => {
			setTimeout( closeDropdown, 150 );
		} );
	}

	function syncConfigFromDOM( body ) {
		body.querySelectorAll( '[data-cfg]' ).forEach( el => {
			const key = el.dataset.cfg;
			if ( el.type === 'checkbox' ) {
				state.sourceConfig[ key ] = el.checked;
			} else {
				state.sourceConfig[ key ] = el.value;
			}
		} );
	}

	// ── Tag Picker (singleton) ────────────────────────────────────────────────

	let pickerTargetInput = null;

	function getOrCreatePicker() {
		let picker = document.getElementById( 'ecs-drb-tag-picker' );
		if ( picker ) return picker;

		picker = document.createElement( 'div' );
		picker.id = 'ecs-drb-tag-picker';
		picker.innerHTML = `
			<div class="ecs-drb-tp-search">
				<input class="ecs-drb-tp-search-input" type="text" placeholder="Search fields…" autocomplete="off">
			</div>
			<div class="ecs-drb-tp-list"></div>
		`;
		document.body.appendChild( picker );

		// Filter on search input.
		picker.querySelector( '.ecs-drb-tp-search-input' ).addEventListener( 'input', e => {
			const q = e.target.value.toLowerCase();
			picker.querySelectorAll( '.ecs-drb-tp-item' ).forEach( item => {
				const match = item.dataset.key.includes( q ) || item.dataset.label.toLowerCase().includes( q );
				item.style.display = match ? '' : 'none';
			} );
		} );

		// Close on outside click.
		document.addEventListener( 'mousedown', e => {
			if ( picker.classList.contains( 'ecs-drb-tp-open' ) && ! picker.contains( e.target ) ) {
				closePicker();
			}
		} );

		return picker;
	}

	function openPicker( anchorEl, targetInput ) {
		pickerTargetInput = targetInput;
		const picker = getOrCreatePicker();

		// Populate list.
		const list = picker.querySelector( '.ecs-drb-tp-list' );
		list.innerHTML = state.sourceFields.length
			? state.sourceFields.map( f => `
				<button class="ecs-drb-tp-item" data-key="${ esc( f.key ) }" data-label="${ esc( f.label ) }" type="button">
					<span class="ecs-drb-tp-chip">{${ esc( f.key ) }}</span>
					<span class="ecs-drb-tp-label">${ esc( f.label ) }</span>
				</button>
			` ).join( '' )
			: `<p class="ecs-drb-tp-empty">${ esc( L10N.noFields ) }</p>`;

		// Reset search.
		picker.querySelector( '.ecs-drb-tp-search-input' ).value = '';
		picker.querySelectorAll( '.ecs-drb-tp-item' ).forEach( i => i.style.display = '' );

		// Bind item clicks.
		list.querySelectorAll( '.ecs-drb-tp-item' ).forEach( btn => {
			btn.addEventListener( 'mousedown', e => {
				e.preventDefault(); // prevent input blur before insert
				insertTag( btn.dataset.key );
			} );
		} );

		// Position below anchor.
		const rect = anchorEl.getBoundingClientRect();
		picker.style.top  = ( rect.bottom + window.scrollY + 4 ) + 'px';
		picker.style.left = ( rect.left + window.scrollX ) + 'px';

		picker.classList.add( 'ecs-drb-tp-open' );
		picker.querySelector( '.ecs-drb-tp-search-input' ).focus();
	}

	function closePicker() {
		const picker = document.getElementById( 'ecs-drb-tag-picker' );
		if ( picker ) picker.classList.remove( 'ecs-drb-tp-open' );
		pickerTargetInput = null;
	}

	function insertTag( fieldKey ) {
		if ( ! pickerTargetInput ) return;
		const tag   = `{${ fieldKey }}`;
		const input = pickerTargetInput;
		const start = input.selectionStart ?? input.value.length;
		const end   = input.selectionEnd   ?? input.value.length;
		input.value = input.value.slice( 0, start ) + tag + input.value.slice( end );
		input.selectionStart = input.selectionEnd = start + tag.length;
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.focus();
		closePicker();
	}

	// ── Mapping Tab ───────────────────────────────────────────────────────────

	function renderMappingTab() {
		if ( state.repeaterFields.length === 0 ) {
			return `<div class="ecs-drb-mapping-tab">
				<p class="ecs-drb-hint">${ esc( L10N.noFields ) }</p>
			</div>`;
		}

		const hasSourceFields = state.sourceFields.length > 0;

		const rows = state.repeaterFields.map( rf => {
			const rule = state.mapping.find( m => m.repeater_field === rf.key ) || {
				repeater_field: rf.key, source_field: '', fallback: '', skip_if_empty: false,
			};

			return `
				<div class="ecs-drb-map-row" data-rfield="${ esc( rf.key ) }">
					<div class="ecs-drb-map-rfield">
						<span class="ecs-drb-map-rfield-label">${ esc( rf.label || rf.key ) }</span>
						<span class="ecs-drb-map-rfield-key">${ esc( rf.key ) }</span>
					</div>
					<div class="ecs-drb-map-expr-wrap">
						<input
							class="ecs-drb-map-expr"
							type="text"
							placeholder="e.g. {post_title} or static text"
							value="${ esc( rule.source_field ) }"
							spellcheck="false"
						>
						<button class="ecs-drb-map-tag-btn" type="button" title="Insert dynamic tag"${ ! hasSourceFields ? ' disabled' : '' }>{…}</button>
					</div>
					<div class="ecs-drb-map-opts">
						<input class="ecs-drb-map-fallback" type="text" placeholder="Fallback" value="${ esc( rule.fallback ) }">
						<label class="ecs-drb-map-skip">
							<input type="checkbox" class="ecs-drb-map-skip-cb"${ rule.skip_if_empty ? ' checked' : '' }>
							Skip if empty
						</label>
					</div>
				</div>
			`;
		} ).join( '' );

		return `
			<div class="ecs-drb-mapping-tab">
				<div class="ecs-drb-mapping-toolbar">
					<button class="ecs-drb-btn-secondary ecs-drb-btn-fetch-fields">${ esc( L10N.fetchFields ) }</button>
					<button class="ecs-drb-btn-secondary ecs-drb-btn-auto-suggest">${ esc( L10N.autoSuggest ) }</button>
				</div>
				<div class="ecs-drb-map-header">
					<span>Repeater Field</span>
					<span>Expression <em class="ecs-drb-map-hint">use {field} tags</em></span>
					<span>Fallback / Options</span>
				</div>
				<div class="ecs-drb-map-rows">${ rows }</div>
			</div>
		`;
	}

	function bindMappingTab( body ) {
		body.querySelector( '.ecs-drb-btn-fetch-fields' )?.addEventListener( 'click', () => {
			loadAvailableFields().then( () => setActiveTab( 'mapping' ) );
		} );

		body.querySelector( '.ecs-drb-btn-auto-suggest' )?.addEventListener( 'click', () => {
			const run = () => {
				state.mapping = autoSuggestMappings( state.repeaterFields, state.sourceFields );
				setActiveTab( 'mapping' );
				setStatus( state.mapping.filter( r => r.source_field ).length + ' fields mapped automatically.' );
			};
			if ( state.sourceFields.length > 0 ) {
				run();
			} else {
				loadAvailableFields().then( run );
			}
		} );

		body.querySelectorAll( '.ecs-drb-map-row' ).forEach( row => bindMappingRow( row ) );
	}

	function bindMappingRow( rowEl ) {
		const rfield  = rowEl.dataset.rfield;
		const exprEl  = rowEl.querySelector( '.ecs-drb-map-expr' );
		const tagBtn  = rowEl.querySelector( '.ecs-drb-map-tag-btn' );

		// Open picker on tag button click.
		tagBtn?.addEventListener( 'click', () => {
			if ( picker_isOpen() && pickerTargetInput === exprEl ) {
				closePicker();
			} else {
				openPicker( tagBtn, exprEl );
			}
		} );

		const update = () => {
			const idx  = state.mapping.findIndex( m => m.repeater_field === rfield );
			const rule = {
				repeater_field : rfield,
				source_field   : exprEl?.value                                          || '',
				fallback       : rowEl.querySelector( '.ecs-drb-map-fallback' )?.value  || '',
				skip_if_empty  : rowEl.querySelector( '.ecs-drb-map-skip-cb' )?.checked || false,
			};
			if ( idx >= 0 ) state.mapping[ idx ] = rule;
			else            state.mapping.push( rule );
		};

		rowEl.querySelectorAll( 'input[type="text"], input[type="checkbox"]' ).forEach( el => {
			el.addEventListener( 'change', update );
			el.addEventListener( 'input',  update );
		} );
	}

	function picker_isOpen() {
		return !! document.getElementById( 'ecs-drb-tag-picker' )?.classList.contains( 'ecs-drb-tp-open' );
	}

	// ── Options Tab ───────────────────────────────────────────────────────────

	function renderOptionsTab() {
		return `
			<div class="ecs-drb-options-tab">
				<div class="ecs-drb-field">
					<label>Apply Mode</label>
					<div class="ecs-drb-radio-group">
						<label><input type="radio" name="ecs_drb_apply_mode" value="replace"${ state.applyMode === 'replace' ? ' checked' : '' }> Replace existing rows</label>
						<label><input type="radio" name="ecs_drb_apply_mode" value="append"${  state.applyMode === 'append'  ? ' checked' : '' }> Append to existing rows</label>
					</div>
				</div>

				<div class="ecs-drb-field">
					<label>Empty State (when source returns no data)</label>
					<select class="ecs-drb-opt-empty-state">
						<option value="keep"${ state.emptyState === 'keep'  ? ' selected' : '' }>Keep existing repeater data</option>
						<option value="clear"${ state.emptyState === 'clear' ? ' selected' : '' }>Clear repeater (0 rows)</option>
					</select>
				</div>

				<div class="ecs-drb-field ecs-drb-field--toggle">
					<label>
						<input type="checkbox" class="ecs-drb-opt-cache"${ state.useCache ? ' checked' : '' }>
						Cache source results
					</label>
				</div>

				<div class="ecs-drb-field ecs-drb-cache-ttl-wrap${ state.useCache ? '' : ' ecs-drb-hidden' }">
					<label>Cache TTL (seconds)</label>
					<input type="number" class="ecs-drb-opt-cache-ttl" value="${ esc( state.cacheTtl ) }" min="60" max="86400">
				</div>
			</div>
		`;
	}

	function bindOptionsTab( body ) {
		body.querySelectorAll( 'input[name="ecs_drb_apply_mode"]' ).forEach( el => {
			el.addEventListener( 'change', () => { state.applyMode = el.value; } );
		} );

		body.querySelector( '.ecs-drb-opt-empty-state' )?.addEventListener( 'change', e => {
			state.emptyState = e.target.value;
		} );

		const cacheToggle = body.querySelector( '.ecs-drb-opt-cache' );
		const cacheTtlWrap = body.querySelector( '.ecs-drb-cache-ttl-wrap' );
		cacheToggle?.addEventListener( 'change', () => {
			state.useCache = cacheToggle.checked;
			cacheTtlWrap?.classList.toggle( 'ecs-drb-hidden', ! state.useCache );
		} );

		body.querySelector( '.ecs-drb-opt-cache-ttl' )?.addEventListener( 'change', e => {
			state.cacheTtl = parseInt( e.target.value ) || 300;
		} );
	}

	// ── Presets Tab ───────────────────────────────────────────────────────────

	function renderPresetsTab() {
		const items = state.presets.length
			? state.presets.map( p => `
				<div class="ecs-drb-preset-item" data-id="${ esc( p.id ) }">
					<span class="ecs-drb-preset-name">${ esc( p.name ) }</span>
					<button class="ecs-drb-btn-load-preset">${ esc( L10N.loadPreset ) }</button>
					<button class="ecs-drb-btn-delete-preset">✕</button>
				</div>
			` ).join( '' )
			: '<p class="ecs-drb-hint">No presets saved yet.</p>';

		return `
			<div class="ecs-drb-presets-tab">
				<div class="ecs-drb-save-preset">
					<input class="ecs-drb-preset-name-input" type="text" placeholder="Preset name…">
					<button class="ecs-drb-btn-save-preset">${ esc( L10N.savePreset ) }</button>
				</div>
				<div class="ecs-drb-preset-list">${ items }</div>
			</div>
		`;
	}

	function bindPresetsTab( body ) {
		body.querySelector( '.ecs-drb-btn-save-preset' )?.addEventListener( 'click', () => {
			const name = body.querySelector( '.ecs-drb-preset-name-input' )?.value?.trim();
			if ( ! name ) { setStatus( 'Enter a preset name', true ); return; }
			savePreset( name );
		} );

		body.querySelectorAll( '.ecs-drb-btn-load-preset' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				const id = btn.closest( '[data-id]' )?.dataset.id;
				const preset = state.presets.find( p => p.id === id );
				if ( preset ) loadPreset( preset );
			} );
		} );

		body.querySelectorAll( '.ecs-drb-btn-delete-preset' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				const id = btn.closest( '[data-id]' )?.dataset.id;
				if ( id ) deletePreset( id );
			} );
		} );
	}

	// ── Binding Tab ───────────────────────────────────────────────────────────

	function renderBindingTab() {
		const statusMsg = state.isBound ? L10N.bound : L10N.notBound;
		const statusCls = state.isBound ? 'ecs-drb-binding-status--active' : '';

		return `
			<div class="ecs-drb-binding-tab">
				<div class="ecs-drb-binding-status ${ statusCls }">
					${ esc( statusMsg ) }
				</div>
				<div class="ecs-drb-binding-actions">
					${ ! state.isBound
						? `<button class="ecs-drb-btn-primary ecs-drb-btn-bind">${ esc( L10N.bindToSource ) }</button>`
						: `<button class="ecs-drb-btn-secondary ecs-drb-btn-refresh-now">${ esc( L10N.refreshNow ) }</button>
						   <button class="ecs-drb-btn-danger ecs-drb-btn-break-sync">${ esc( L10N.breakSync ) }</button>`
					}
				</div>
				<p class="ecs-drb-binding-help">
					When bound, this repeater is populated dynamically on every frontend page load using the configured source and mapping.
					The Elementor editor always shows the static repeater data for editing.
				</p>
			</div>
		`;
	}

	function bindBindingTab( body ) {
		body.querySelector( '.ecs-drb-btn-bind' )?.addEventListener( 'click', handleBind );
		body.querySelector( '.ecs-drb-btn-refresh-now' )?.addEventListener( 'click', handleRefreshNow );
		body.querySelector( '.ecs-drb-btn-break-sync' )?.addEventListener( 'click', handleBreakSync );
	}

	// ── Actions ───────────────────────────────────────────────────────────────

	function loadSourceSchema( sourceType ) {
		setLoading( true );
		post( 'ecs_drb_get_source_schema', {
			source_type  : sourceType,
			source_config: JSON.stringify( state.sourceConfig ),
		} ).then( data => {
			state.sourceSchema = data.schema || [];
			state.sourceFields = data.fields || [];
			setActiveTab( 'source' );
		} ).catch( err => {
			setStatus( err, true );
		} ).finally( () => setLoading( false ) );
	}

	function loadAvailableFields() {
		setLoading( true );
		return post( 'ecs_drb_get_available_fields', {
			source_type  : state.sourceType,
			source_config: JSON.stringify( state.sourceConfig ),
		} ).then( data => {
			state.sourceFields = data.fields || [];
			if ( state.mapping.length === 0 ) {
				state.mapping = autoSuggestMappings( state.repeaterFields, state.sourceFields );
			}
			setStatus( `${ state.sourceFields.length } fields available` );
		} ).catch( err => {
			setStatus( err, true );
		} ).finally( () => setLoading( false ) );
	}

	function handleGenerate() {
		if ( state.isLoading ) return;
		setLoading( true );
		setStatus( L10N.loading );

		post( 'ecs_drb_generate', {
			source_type  : state.sourceType,
			source_config: JSON.stringify( state.sourceConfig ),
			mapping      : JSON.stringify( state.mapping ),
			field_types  : JSON.stringify( getFieldTypes() ),
			use_cache    : state.useCache ? '1' : '0',
			cache_ttl    : state.cacheTtl,
		} ).then( data => {
			state.previewRows = data.rows || [];
			const cachedLabel = data.cached ? ' ' + L10N.cached : '';

			let msg;
			if ( data.result_count > 0 ) {
				msg = L10N.previewRows.replace( '%d', data.result_count ) + cachedLabel;
			} else if ( data.source_count > 0 ) {
				msg = data.source_count + ' records found — go to Mapping tab to map fields.';
			} else {
				msg = 'No records found for the selected source configuration.';
			}
			setStatus( msg );

			const applyBtn = document.querySelector( '.ecs-drb-btn-apply' );
			if ( applyBtn ) applyBtn.disabled = data.result_count === 0;
		} ).catch( err => {
			setStatus( err, true );
		} ).finally( () => setLoading( false ) );
	}

	function makeId() {
		return Math.random().toString( 36 ).substr( 2, 7 );
	}

	function handleApply() {
		if ( ! state.previewRows || state.isLoading ) return;

		const rows = state.previewRows;
		const cKey = state.controlKey;
		const cont = state.container;
		const mode = state.applyMode;

		let finalRows = rows;

		if ( mode === 'append' ) {
			const existing    = cont?.settings?.get( cKey );
			const existingArr = existing?.models
				? existing.models.map( m => m.attributes )
				: ( Array.isArray( existing ) ? existing : [] );
			finalRows = [ ...existingArr, ...rows ];
		}

		// Elementor repeater items need a _id so the Backbone collection
		// can identify them across re-renders (e.g. switching Style/Content tabs).
		finalRows = finalRows.map( row => ( { _id: makeId(), ...row } ) );

		try {
			$e.run( 'document/elements/settings', {
				containers : [ cont ],
				settings   : { [ cKey ]: finalRows },
				options    : { render: true },
			} );
		} catch ( _ ) {
			cont?.settings?.set( cKey, finalRows );
			cont?.render?.();
		}

		// Mark document as changed so Elementor knows to save.
		try { window.elementor.saver.setFlagEditorChange(); } catch ( _ ) {}

		setStatus( L10N.appliedRows.replace( '%d', finalRows.length ) );
	}

	function loadPresets() {
		post( 'ecs_drb_get_presets', {} )
			.then( data => { state.presets = data.presets || []; } )
			.catch( () => {} );
	}

	function savePreset( name ) {
		const config = {
			source_type  : state.sourceType,
			source_config: state.sourceConfig,
			mapping      : state.mapping,
			apply_mode   : state.applyMode,
			empty_state  : state.emptyState,
			use_cache    : state.useCache,
			cache_ttl    : state.cacheTtl,
		};
		post( 'ecs_drb_save_preset', { name, config: JSON.stringify( config ) } )
			.then( data => {
				state.presets = data.presets || [];
				setActiveTab( 'presets' );
				setStatus( 'Preset saved' );
			} )
			.catch( err => setStatus( err, true ) );
	}

	function loadPreset( preset ) {
		const c = preset.config || {};
		state.sourceType   = c.source_type   || state.sourceType;
		state.sourceConfig = c.source_config  || {};
		state.mapping      = c.mapping        || [];
		state.applyMode    = c.apply_mode     || 'replace';
		state.emptyState   = c.empty_state    || 'keep';
		state.useCache     = c.use_cache      ?? true;
		state.cacheTtl     = c.cache_ttl      || 300;
		state.sourceSchema = [];
		loadSourceSchema( state.sourceType );
		setStatus( 'Preset loaded: ' + preset.name );
	}

	function deletePreset( id ) {
		post( 'ecs_drb_delete_preset', { id } )
			.then( data => {
				state.presets = data.presets || [];
				setActiveTab( 'presets' );
			} )
			.catch( err => setStatus( err, true ) );
	}

	function loadBindingStatus() {
		if ( ! state.postId ) return;
		post( 'ecs_drb_get_bindings', { post_id: state.postId } )
			.then( data => {
				const key = state.container?.model?.get( 'id' ) + ':' + state.controlKey;
				const binding = ( data.bindings || [] ).find(
					b => b.element_id + ':' + b.control_key === key
				);
				state.isBound       = !! binding;
				state.bindingConfig = binding?.config || null;
			} )
			.catch( () => {} );
	}

	function handleBind() {
		const config = {
			source_type  : state.sourceType,
			source_config: state.sourceConfig,
			mapping      : state.mapping,
			field_types  : getFieldTypes(),
			apply_mode   : state.applyMode,
			use_cache    : state.useCache,
			cache_ttl    : state.cacheTtl,
		};
		post( 'ecs_drb_save_binding', {
			post_id    : state.postId,
			element_id : state.container?.model?.get( 'id' ),
			control_key: state.controlKey,
			config     : JSON.stringify( config ),
		} ).then( () => {
			state.isBound = true;
			setActiveTab( 'binding' );
			setStatus( 'Binding saved. Data will be generated at runtime.' );
		} ).catch( err => setStatus( err, true ) );
	}

	function handleRefreshNow() {
		handleGenerate();
	}

	function handleBreakSync() {
		post( 'ecs_drb_break_binding', {
			post_id    : state.postId,
			element_id : state.container?.model?.get( 'id' ),
			control_key: state.controlKey,
		} ).then( () => {
			state.isBound       = false;
			state.bindingConfig = null;
			setActiveTab( 'binding' );
			setStatus( 'Binding removed.' );
		} ).catch( err => setStatus( err, true ) );
	}

	// ── Button injection (same pattern as JSON PowerEdit) ─────────────────────

	function injectButton( controlEl ) {
		if ( controlEl.dataset.ecsDrb ) return;
		controlEl.dataset.ecsDrb = '1';

		const classes = controlEl.className;
		const match   = classes.match( /elementor-control-([a-zA-Z0-9_]+)/ );
		if ( ! match ) return;

		const controlKey = match[1];

		const btn = document.createElement( 'button' );
		btn.type      = 'button';
		btn.className = 'ecs-drb-inject-btn';
		btn.innerHTML = '&#10022; ' + ( L10N.btnLabel || 'Dynamic Populate' );

		btn.addEventListener( 'click', () => {
			const container = getActiveContainer();
			if ( ! container ) return;
			const widgetLabel = container.model?.get( 'settings' )?.get( '_title' )
				|| container.model?.get( 'widgetType' )
				|| 'Widget';
			openModal( controlKey, widgetLabel, container );
		} );

		const addBtn = controlEl.querySelector( '.elementor-repeater-add' );
		if ( addBtn ) {
			addBtn.insertAdjacentElement( 'afterend', btn );
		} else {
			controlEl.appendChild( btn );
		}
	}

	function scanPanel() {
		document.querySelectorAll( '.elementor-control-type-repeater:not([data-ecs-drb])' )
			.forEach( injectButton );
	}

	// ── Init ──────────────────────────────────────────────────────────────────

	function init() {
		if ( ! window.elementor ) return;

		const observer = new MutationObserver( () => scanPanel() );
		const panel = document.getElementById( 'elementor-panel' );
		if ( panel ) {
			observer.observe( panel, { childList: true, subtree: true } );
		}

		scanPanel();
	}

	let initDone = false;
	$( window ).on( 'elementor:init', () => {
		if ( initDone ) return;
		initDone = true;
		init();
	} );

	// Polling fallback (elementor:init fires before this script on some setups).
	const poll = setInterval( () => {
		if ( window.elementor && window.elementor.getPanelView ) {
			clearInterval( poll );
			if ( ! initDone ) { initDone = true; init(); }
		}
	}, 200 );

} )( jQuery );
