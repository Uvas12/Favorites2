/**
 * Favorites Extension - JavaScript
 */
( function () {
	'use strict';

	var api      = new mw.Api();
	var pageId   = mw.config.get( 'wgArticleId' );
	var useIcon  = mw.config.get( 'wgUseIconFavorite' );
	// Estado inicial viene de PHP — sin AJAX al cargar
	var isFavInit = !!mw.config.get( 'wgIsFavorite' );

	var heartEmpty = "data:image/svg+xml,%3Csvg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z' fill='none' stroke='%2354595d' stroke-width='2'/%3E%3C/svg%3E";
	var heartFull  = "data:image/svg+xml,%3Csvg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z' fill='%23e74c3c' stroke='%23c0392b' stroke-width='1.5'/%3E%3C/svg%3E";

	function showSessionNotification() {
		var notif = mw.config.get( 'wgFavoritesNotification' );
		if ( !notif ) return;
		mw.notification.notify(
			mw.msg( notif.type === 'added' ? 'favorites-added' : 'favorites-removed', notif.page ),
			{ autoHide: true, autoHideSeconds: 5, type: 'success' }
		);
	}

	/** Lanzar corazoncitos pequeños alrededor del corazón grande */
	function burstHearts( img ) {
		// Pop del corazón principal
		img.classList.remove( 'fav-animate' );
		void img.offsetWidth;
		img.classList.add( 'fav-animate' );
		setTimeout( function () { img.classList.remove( 'fav-animate' ); }, 600 );

		// Obtener posición del corazón en la página
		var rect   = img.getBoundingClientRect();
		var cx     = rect.left + rect.width / 2 + window.scrollX;
		var cy     = rect.top  + rect.height / 2 + window.scrollY;

		// 8 corazoncitos en distintas direcciones
		var angles = [ 0, 45, 90, 135, 180, 225, 270, 315 ];
		angles.forEach( function ( angle, i ) {
			var rad    = ( angle * Math.PI ) / 180;
			var dist   = 28 + Math.random() * 18;
			var tx     = Math.cos( rad ) * dist;
			var ty     = Math.sin( rad ) * dist;
			var size   = 8 + Math.random() * 7;
			var delay  = i * 30;

			var h = document.createElement( 'span' );
			h.className = 'fav-burst-heart';
			h.textContent = '❤';
			h.style.cssText = [
				'position:fixed',
				'left:' + ( rect.left + rect.width  / 2 - size / 2 ) + 'px',
				'top:'  + ( rect.top  + rect.height / 2 - size / 2 ) + 'px',
				'font-size:' + size + 'px',
				'color:#e74c3c',
				'pointer-events:none',
				'z-index:9999',
				'opacity:1',
				'transition:transform ' + ( 0.55 + Math.random() * 0.15 ) + 's ease-out, opacity 0.45s ease-out ' + ( delay / 1000 ) + 's',
				'transform:translate(0,0) scale(1)',
				'will-change:transform,opacity',
				'line-height:1'
			].join( ';' );
			document.body.appendChild( h );

			// Forzar reflow y lanzar
			setTimeout( function () {
				h.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(0.2)';
				h.style.opacity   = '0';
			}, delay + 10 );

			// Eliminar del DOM al terminar
			setTimeout( function () {
				if ( h.parentNode ) h.parentNode.removeChild( h );
			}, 800 + delay );
		} );
	}

	function animateHeart( img ) {
		burstHearts( img );
	}

	function initHeartIcon() {
		if ( !pageId ) return;

		// Buscar el li de la estrella para insertar junto a ella
		var watchLi = document.getElementById( 'ca-watch' ) ||
			document.getElementById( 'ca-unwatch' );
		if ( !watchLi ) return;

		// Crear el li del corazón — mismo estilo que ca-watch
		var li  = document.createElement( 'li' );
		var img = document.createElement( 'img' );
		li.id        = 'ca-favorite';
		// Copiar la altura exacta del li de la estrella para alinear al mismo nivel
		li.style.height = watchLi.offsetHeight ? watchLi.offsetHeight + 'px' : '';
		li.className = 'mw-favorite-heart-li' + ( isFavInit ? ' is-favorite' : '' );
		img.className = 'mw-favorite-heart-img';
		img.src       = isFavInit ? heartFull : heartEmpty;
		img.title     = mw.msg( isFavInit ? 'favorites-remove' : 'favorites-add' );
		img.alt       = img.title;
		li.appendChild( img );

		watchLi.parentNode.insertBefore( li, watchLi.nextSibling );

		var isCurrent = isFavInit;
		var loading   = false;
		var pageName  = mw.config.get( 'wgTitle' );

		li.addEventListener( 'click', function () {
			if ( loading ) return;
			loading = true;
			var action = isCurrent ? 'removefavorite' : 'addfavorite';

			api.postWithToken( 'csrf', {
				action: action, pageid: pageId, format: 'json'
			} ).done( function ( data ) {
				loading = false;
				if ( !data || !data[ action ] ) return;

				isCurrent = !isCurrent;
				img.src   = isCurrent ? heartFull : heartEmpty;
				img.title = mw.msg( isCurrent ? 'favorites-remove' : 'favorites-add' );
				img.alt   = img.title;
				li.classList.toggle( 'is-favorite', isCurrent );

				animateHeart( img );

				mw.notification.notify(
					mw.msg( action === 'addfavorite' ? 'favorites-added' : 'favorites-removed', pageName ),
					{ autoHide: true, autoHideSeconds: 5, type: 'success' }
				);
			} ).fail( function () {
				loading = false;
				mw.notification.notify( mw.msg( 'favorites-error' ), { type: 'error' } );
			} );
		} );
	}

	function initSpecialPage() {
		document.querySelectorAll( '.mw-favorites-remove-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var pid   = parseInt( btn.getAttribute( 'data-page-id' ), 10 );
				var pname = btn.getAttribute( 'data-page-name' ) || '';
				var li    = btn.closest( 'li' );
				api.postWithToken( 'csrf', {
					action: 'removefavorite', pageid: pid, format: 'json'
				} ).done( function () {
					if ( li ) {
						li.style.transition = 'opacity 0.3s';
						li.style.opacity    = '0';
						setTimeout( function () {
							var ul = li.parentNode;
							li.remove();
							if ( ul && ul.querySelectorAll( 'li' ).length === 0 ) {
								var s = ul.closest( '.mw-favorites-namespace-section' );
								if ( s ) s.remove();
							}
						}, 300 );
					}
					mw.notification.notify( mw.msg( 'favorites-removed', pname ),
						{ type: 'success', autoHide: true, autoHideSeconds: 5 } );
				} );
			} );
		} );
	}

	mw.hook( 'wikipage.content' ).add( function () {
		showSessionNotification();
		var ns = mw.config.get( 'wgNamespaceNumber' );
		if ( ns === -1 && document.querySelector( '.mw-favorites-page' ) ) {
			initSpecialPage();
			return;
		}
		if ( ns >= 0 && pageId && useIcon ) {
			initHeartIcon();
		}
	} );

}() );
