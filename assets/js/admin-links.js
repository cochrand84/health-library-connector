/**
 * Settings → Pulsegrid → Sidebar links: editable table.
 *
 * Rows live in the DOM; on submit they are serialized to JSON in a hidden
 * field and sanitized server-side (PGCC_Care_Links::clean_row()).
 */
( function () {
	'use strict';

	var form = document.getElementById( 'pgcc-links-form' );
	if ( ! form ) {
		return;
	}
	var body = document.getElementById( 'pgcc-links-body' );
	var data = [];
	try {
		data = JSON.parse( document.getElementById( 'pgcc-links-data' ).textContent ) || [];
	} catch ( e ) {}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			if ( 'text' === k ) {
				node.textContent = attrs[ k ];
			} else {
				node.setAttribute( k, attrs[ k ] );
			}
		} );
		( children || [] ).forEach( function ( c ) {
			node.appendChild( c );
		} );
		return node;
	}

	function linkRow( link ) {
		var label = el( 'input', { type: 'text', class: 'pgcc-link-label', placeholder: 'Label' } );
		var url = el( 'input', { type: 'url', class: 'pgcc-link-url code', placeholder: 'https://' } );
		label.value = ( link && link.label ) || '';
		url.value = ( link && link.url ) || '';
		var remove = el( 'button', { type: 'button', class: 'button-link pgcc-remove-link', 'aria-label': 'Remove link', text: '×' } );
		remove.addEventListener( 'click', function () {
			wrap.remove();
			markDirty();
		} );
		var wrap = el( 'div', { class: 'pgcc-link' }, [ label, url, remove ] );
		return wrap;
	}

	function linkList( links, kind ) {
		var list = el( 'div', { class: 'pgcc-link-list-edit', 'data-kind': kind } );
		( links || [] ).forEach( function ( l ) {
			list.appendChild( linkRow( l ) );
		} );
		var add = el( 'button', { type: 'button', class: 'button-link pgcc-add-link', text: '+ Add link' } );
		add.addEventListener( 'click', function () {
			list.insertBefore( linkRow(), add );
			list.querySelectorAll( '.pgcc-link-label' )[ list.querySelectorAll( '.pgcc-link-label' ).length - 1 ].focus();
			markDirty();
		} );
		list.appendChild( add );
		return list;
	}

	function rowNode( row ) {
		row = row || { ids: [], topic: '', provider: [ {} ], services: [ {} ] };
		var tr = el( 'tr', { class: 'pgcc-row' } );

		var order = el( 'td', { class: 'col-order' } );
		var num = el( 'span', { class: 'pgcc-num' } );
		var up = el( 'button', { type: 'button', class: 'button-link', 'aria-label': 'Move up', text: '▲' } );
		var down = el( 'button', { type: 'button', class: 'button-link', 'aria-label': 'Move down', text: '▼' } );
		up.addEventListener( 'click', function () {
			if ( tr.previousElementSibling ) {
				body.insertBefore( tr, tr.previousElementSibling );
				renumber();
				markDirty();
			}
		} );
		down.addEventListener( 'click', function () {
			if ( tr.nextElementSibling ) {
				body.insertBefore( tr.nextElementSibling, tr );
				renumber();
				markDirty();
			}
		} );
		order.appendChild( num );
		order.appendChild( el( 'div', { class: 'pgcc-move' }, [ up, down ] ) );

		var topic = el( 'input', { type: 'text', class: 'pgcc-topic', placeholder: 'e.g. Gastroenterology' } );
		topic.value = row.topic || '';
		var ids = el( 'input', { type: 'text', class: 'pgcc-ids code', placeholder: '1452; 2926 or *' } );
		ids.value = ( row.ids || [] ).join( '; ' );

		var remove = el( 'button', { type: 'button', class: 'button-link-delete', text: 'Remove' } );
		remove.addEventListener( 'click', function () {
			tr.remove();
			renumber();
			markDirty();
		} );

		tr.appendChild( order );
		tr.appendChild( el( 'td', { class: 'col-topic' }, [ topic ] ) );
		tr.appendChild( el( 'td', { class: 'col-ids' }, [ ids ] ) );
		tr.appendChild( el( 'td', { class: 'col-links' }, [ linkList( row.provider, 'provider' ) ] ) );
		tr.appendChild( el( 'td', { class: 'col-links' }, [ linkList( row.services, 'services' ) ] ) );
		tr.appendChild( el( 'td', { class: 'col-actions' }, [ remove ] ) );
		return tr;
	}

	function renumber() {
		var rows = body.querySelectorAll( '.pgcc-row' );
		rows.forEach( function ( tr, i ) {
			tr.querySelector( '.pgcc-num' ).textContent = i + 1;
		} );
		var count = document.getElementById( 'pgcc-links-count' );
		if ( count ) {
			count.textContent = rows.length + ( 1 === rows.length ? ' row' : ' rows' );
		}
	}

	function collect() {
		var out = [];
		body.querySelectorAll( '.pgcc-row' ).forEach( function ( tr ) {
			var row = {
				ids: tr.querySelector( '.pgcc-ids' ).value,
				topic: tr.querySelector( '.pgcc-topic' ).value,
				provider: [],
				services: []
			};
			tr.querySelectorAll( '.pgcc-link-list-edit' ).forEach( function ( list ) {
				list.querySelectorAll( '.pgcc-link' ).forEach( function ( l ) {
					var url = l.querySelector( '.pgcc-link-url' ).value.trim();
					if ( url ) {
						row[ list.getAttribute( 'data-kind' ) ].push( {
							label: l.querySelector( '.pgcc-link-label' ).value.trim(),
							url: url
						} );
					}
				} );
			} );
			out.push( row );
		} );
		return out;
	}

	var dirty = false;
	function markDirty() {
		dirty = true;
	}
	window.addEventListener( 'beforeunload', function ( e ) {
		if ( dirty ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );
	body.addEventListener( 'input', markDirty );

	data.forEach( function ( row ) {
		body.appendChild( rowNode( row ) );
	} );
	renumber();

	document.getElementById( 'pgcc-add-row' ).addEventListener( 'click', function () {
		var tr = rowNode();
		body.appendChild( tr );
		renumber();
		markDirty();
		tr.querySelector( '.pgcc-topic' ).focus();
	} );

	var filter = document.getElementById( 'pgcc-links-filter' );
	filter.addEventListener( 'input', function () {
		var q = filter.value.trim().toLowerCase();
		body.querySelectorAll( '.pgcc-row' ).forEach( function ( tr ) {
			var text = Array.prototype.map.call( tr.querySelectorAll( 'input' ), function ( i ) {
				return i.value;
			} ).join( ' ' ).toLowerCase();
			tr.style.display = ! q || -1 !== text.indexOf( q ) ? '' : 'none';
		} );
	} );

	form.addEventListener( 'submit', function () {
		document.getElementById( 'pgcc-rows-json' ).value = JSON.stringify( collect() );
		dirty = false;
	} );
}() );
