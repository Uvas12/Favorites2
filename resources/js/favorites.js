/**
 * Favorites Extension - JavaScript
 */
( function () {
    'use strict';

    var api = new mw.Api();
    var pageId = mw.config.get( 'wgArticleId' );
    var useIcon = mw.config.get( 'wgUseIconFavorite' );

    // Estado inicial viene de PHP — sin AJAX al cargar.
    var isFavInit = !!mw.config.get( 'wgIsFavorite' );

    var notificationShown = false;

    var heartEmpty = "data:image/svg+xml,%3Csvg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z' fill='none' stroke='%2354595d' stroke-width='2'/%3E%3C/svg%3E";
    var heartFull = "data:image/svg+xml,%3Csvg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z' fill='%23e74c3c' stroke='%23c0392b' stroke-width='1.5'/%3E%3C/svg%3E";

    function showSessionNotification() {
        var notif = mw.config.get( 'wgFavoritesNotification' );

        if ( !notif || notificationShown ) {
            return;
        }

        notificationShown = true;

        mw.notification.notify(
            mw.msg(
                notif.type === 'added' ? 'favorites-added' : 'favorites-removed',
                notif.page
            ),
            {
                autoHide: true,
                autoHideSeconds: 5,
                type: 'success'
            }
        );
    }

    /**
     * Lanzar corazoncitos pequeños alrededor del corazón principal.
     *
     * @param {HTMLImageElement} img
     */
    function burstHearts( img ) {
        img.classList.remove( 'fav-animate' );

        // Forzar reflow para reiniciar la animación.
        void img.offsetWidth;

        img.classList.add( 'fav-animate' );

        setTimeout( function () {
            img.classList.remove( 'fav-animate' );
        }, 600 );

        var rect = img.getBoundingClientRect();
        var angles = [ 0, 45, 90, 135, 180, 225, 270, 315 ];

        angles.forEach( function ( angle, i ) {
            var rad = ( angle * Math.PI ) / 180;
            var dist = 28 + Math.random() * 18;
            var tx = Math.cos( rad ) * dist;
            var ty = Math.sin( rad ) * dist;
            var size = 8 + Math.random() * 7;
            var delay = i * 30;

            var h = document.createElement( 'span' );
            h.className = 'fav-burst-heart';
            h.textContent = '❤';

            h.style.cssText = [
                'position:fixed',
                'left:' + ( rect.left + rect.width / 2 - size / 2 ) + 'px',
                'top:' + ( rect.top + rect.height / 2 - size / 2 ) + 'px',
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

            setTimeout( function () {
                h.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(0.2)';
                h.style.opacity = '0';
            }, delay + 10 );

            setTimeout( function () {
                if ( h.parentNode ) {
                    h.parentNode.removeChild( h );
                }
            }, 800 + delay );
        } );
    }

    function animateHeart( img ) {
        burstHearts( img );
    }

    /**
     * Inicializa el botón de favorito que PHP ya insertó en la navegación.
     *
     * Importante:
     * Este JS ya NO crea un nuevo <li> junto a ca-watch/ca-unwatch.
     * Solo usa #ca-favorites2, que debe venir desde SkinTemplateNavigation.
     */
    function initFavoriteNavButton() {
        if ( !pageId ) {
            return;
        }

        var li = document.getElementById( 'ca-favorites2' );

        if ( !li ) {
            return;
        }

        /*
         * Evitar inicializar dos veces.
         *
         * Esto es lo que evita duplicados cuando:
         * - se carga en modo edición;
         * - WikiEditor dispara vista previa;
         * - wikipage.content se dispara más de una vez.
         */
        if ( li.getAttribute( 'data-favorites2-initialized' ) === '1' ) {
            return;
        }

        li.setAttribute( 'data-favorites2-initialized', '1' );

        var link = li.querySelector( 'a' );

        if ( !link ) {
            return;
        }

        var isCurrent = isFavInit;
        var loading = false;
        var pageName = mw.config.get( 'wgTitle' );

        li.classList.toggle( 'is-favorite', isCurrent );

        var img = null;

        /*
         * Modo icono:
         * Se usa el <li> creado por PHP y se reemplaza visualmente el texto
         * por un SVG de corazón.
         */
        if ( useIcon ) {
            li.classList.add( 'mw-favorite-heart-li' );

            link.textContent = '';

            img = document.createElement( 'img' );
            img.className = 'mw-favorite-heart-img';
            img.src = isCurrent ? heartFull : heartEmpty;
            img.title = mw.msg( isCurrent ? 'favorites-remove' : 'favorites-add' );
            img.alt = img.title;

            /*
             * Ajuste visual directo.
             *
             * Esto sube el corazón sin mover la caja azul de la pestaña.
             * Si el corazón queda demasiado arriba, cambia -4px por -3px.
             * Si todavía queda bajo, cambia -4px por -5px.
             */
            img.style.width = '19px';
            img.style.height = '19px';
            img.style.margin = '0';
            img.style.padding = '0';
            img.style.position = 'relative';
            img.style.top = '-4px';
            img.style.verticalAlign = 'middle';

            link.appendChild( img );
            link.title = img.title;
            link.setAttribute( 'aria-label', img.title );
        } else {
            /*
             * Modo texto:
             * Mantiene el texto normal de la pestaña.
             */
            link.textContent = mw.msg( isCurrent ? 'favorites-remove' : 'favorites-add' );
            link.title = link.textContent;
            link.setAttribute( 'aria-label', link.textContent );
        }

        link.addEventListener( 'click', function ( e ) {
            e.preventDefault();

            if ( loading ) {
                return;
            }

            loading = true;

            var action = isCurrent ? 'removefavorite' : 'addfavorite';

            api.postWithToken( 'csrf', {
                action: action,
                pageid: pageId,
                format: 'json'
            } ).done( function ( data ) {
                loading = false;

                if ( !data || !data[ action ] ) {
                    return;
                }

                isCurrent = !isCurrent;

                li.classList.toggle( 'is-favorite', isCurrent );

                var msgKey = isCurrent ? 'favorites-remove' : 'favorites-add';
                var label = mw.msg( msgKey );

                if ( useIcon && img ) {
                    img.src = isCurrent ? heartFull : heartEmpty;
                    img.title = label;
                    img.alt = label;

                    link.title = label;
                    link.setAttribute( 'aria-label', label );

                    animateHeart( img );
                } else {
                    link.textContent = label;
                    link.title = label;
                    link.setAttribute( 'aria-label', label );
                }

                mw.notification.notify(
                    mw.msg(
                        action === 'addfavorite' ? 'favorites-added' : 'favorites-removed',
                        pageName
                    ),
                    {
                        autoHide: true,
                        autoHideSeconds: 5,
                        type: 'success'
                    }
                );
            } ).fail( function () {
                loading = false;

                mw.notification.notify(
                    mw.msg( 'favorites-error' ),
                    {
                        type: 'error'
                    }
                );
            } );
        } );
    }

    function initSpecialPage() {
        document.querySelectorAll( '.mw-favorites-remove-btn' ).forEach( function ( btn ) {
            if ( btn.getAttribute( 'data-favorites2-initialized' ) === '1' ) {
                return;
            }

            btn.setAttribute( 'data-favorites2-initialized', '1' );

            btn.addEventListener( 'click', function ( e ) {
                e.preventDefault();

                var pid = parseInt( btn.getAttribute( 'data-page-id' ), 10 );
                var pname = btn.getAttribute( 'data-page-name' ) || '';
                var li = btn.closest( 'li' );

                api.postWithToken( 'csrf', {
                    action: 'removefavorite',
                    pageid: pid,
                    format: 'json'
                } ).done( function () {
                    if ( li ) {
                        li.style.transition = 'opacity 0.3s';
                        li.style.opacity = '0';

                        setTimeout( function () {
                            var ul = li.parentNode;
                            li.remove();

                            if ( ul && ul.querySelectorAll( 'li' ).length === 0 ) {
                                var section = ul.closest( '.mw-favorites-namespace-section' );

                                if ( section ) {
                                    section.remove();
                                }
                            }
                        }, 300 );
                    }

                    mw.notification.notify(
                        mw.msg( 'favorites-removed', pname ),
                        {
                            type: 'success',
                            autoHide: true,
                            autoHideSeconds: 5
                        }
                    );
                } ).fail( function () {
                    mw.notification.notify(
                        mw.msg( 'favorites-error' ),
                        {
                            type: 'error'
                        }
                    );
                } );
            } );
        } );
    }

    function initFavorites2() {
        showSessionNotification();

        var ns = mw.config.get( 'wgNamespaceNumber' );

        if ( ns === -1 && document.querySelector( '.mw-favorites-page' ) ) {
            initSpecialPage();
            return;
        }

        if ( pageId ) {
            initFavoriteNavButton();
        }
    }

    /*
     * Run on normal page content updates.
     * This can fire more than once, so initFavoriteNavButton() is idempotent.
     */
    mw.hook( 'wikipage.content' ).add( function () {
        initFavorites2();
    } );

    /*
     * Also run on document ready.
     * This makes the heart icon appear in edit mode, where wikipage.content
     * may not behave exactly like normal page view.
     */
    $( function () {
        initFavorites2();
    } );

}() );