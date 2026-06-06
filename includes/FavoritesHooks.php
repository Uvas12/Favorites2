<?php
declare( strict_types = 1 );

class FavoritesHooks {

    public static function onBeforePageDisplay( $out, $skin ): void {
        $useIcon = isset( $GLOBALS['wgUseIconFavorite'] ) ? (bool)$GLOBALS['wgUseIconFavorite'] : true;

        $user = $skin->getUser();
        $title = $out->getTitle();
        $request = $out->getRequest();

        if ( !$user->isRegistered() ) {
            return;
        }

        if ( !$title ) {
            return;
        }

        /*
         * Load the module also in edit/preview mode.
         *
         * The favorite navigation item is rendered by PHP.
         * JavaScript only enhances #ca-favorites2 and is idempotent,
         * so WikiEditor preview should not duplicate the icon.
         */
        $out->addModules( 'ext.favorites' );
        $out->addJsConfigVars( 'wgUseIconFavorite', $useIcon );
        $out->addJsConfigVars( 'wgFavorites2ServerSideNav', true );

        /*
         * Pass initial favorite state to JavaScript.
         *
         * This avoids an extra AJAX request just to know whether the
         * current page is already marked as favorite.
         */
        if ( !$title->isSpecialPage() ) {
            $pageId = $title->getArticleID();

            if ( $pageId > 0 ) {
                $out->addJsConfigVars(
                    'wgIsFavorite',
                    FavoritesDB::isFavorite( $user, $pageId )
                );
            }
        }

        /*
         * Session notification.
         */
        $session = $request->getSession();
        $notif = $session->get( 'favoritesNotification' );

        if ( $notif ) {
            $session->remove( 'favoritesNotification' );
            $session->save();
            $out->addJsConfigVars( 'wgFavoritesNotification', $notif );
        }
    }

    public static function onSkinTemplateNavigation( $skin, array &$links ): void {
        $useIcon = isset( $GLOBALS['wgUseIconFavorite'] ) ? (bool)$GLOBALS['wgUseIconFavorite'] : true;
        $personalURL = isset( $GLOBALS['wgFavoritesPersonalURL'] ) ? (bool)$GLOBALS['wgFavoritesPersonalURL'] : true;

        $user = $skin->getUser();

        if ( !$user->isRegistered() ) {
            self::removeFavoriteNavigationItems( $links );
            return;
        }

        /*
         * Add "My favorites" link to the user menu.
         */
        if ( $personalURL ) {
            self::addPersonalFavoritesLink( $skin, $links );
        }

        $title = $skin->getTitle();

        /*
         * Remove possible previous Favorites2 entries before inserting
         * the current one. This prevents duplicates.
         */
        self::removeFavoriteNavigationItems( $links );

        if ( !$title || $title->isSpecialPage() ) {
            return;
        }

        $pageId = $title->getArticleID();

        if ( $pageId <= 0 ) {
            return;
        }

        $isFav = FavoritesDB::isFavorite( $user, $pageId );
        $action = $isFav ? 'removefavorite' : 'addfavorite';
        $msgKey = $isFav ? 'favorites-remove' : 'favorites-add';

        $class = 'mw-favorite-tab';

        if ( $isFav ) {
            $class .= ' is-favorite';
        } else {
            $class .= ' is-not-favorite';
        }

        if ( $useIcon ) {
            $class .= ' mw-favorite-icon';
        }

        /*
         * In icon mode, render a simple heart immediately from PHP.
         * JavaScript can later replace/enhance it if needed.
         *
         * In text mode, render the normal message.
         */
        $favoriteLink = [
            'text' => $useIcon ? ( $isFav ? '♥' : '♡' ) : $skin->msg( $msgKey )->text(),
            'href' => $title->getFullURL( [ 'action' => $action ] ),
            'class' => $class,
            'id' => 'ca-favorites2',
        ];

        if ( $useIcon ) {
            /*
             * Icon mode:
             * Put Favorites2 in "views", next to Read/Edit/History.
             *
             * This keeps the icon out of the "More" menu even when
             * $wgVectorUseIconWatch = false.
             */
            if ( !isset( $links['views'] ) || !is_array( $links['views'] ) ) {
                $links['views'] = [];
            }

            $links['views'] = self::insertAfterKey(
                $links['views'],
                'history',
                'favorite',
                $favoriteLink
            );
        } else {
            /*
             * Text mode:
             * Put Favorites2 in the "More" menu.
             *
             * This matches the expected behavior when the icon mode is disabled.
             */
            if ( !isset( $links['actions'] ) || !is_array( $links['actions'] ) ) {
                $links['actions'] = [];
            }

            $links['actions']['favorite'] = $favoriteLink;
        }
    }

    public static function onLoadExtensionSchemaUpdates( $updater ): void {
        $updater->addExtensionTable(
            'favorites',
            dirname( __DIR__ ) . '/sql/favorites.sql'
        );
    }

    /**
     * Add the "My favorites" link to the user menu.
     *
     * @param SkinTemplate $skin
     * @param array &$links
     * @return void
     */
    private static function addPersonalFavoritesLink( $skin, array &$links ): void {
        $favTitle = \SpecialPage::getTitleFor( 'Favorites' );
        $newMenu = [];
        $inserted = false;

        foreach ( $links['user-menu'] ?? [] as $key => $item ) {
            $newMenu[$key] = $item;

            if ( !$inserted && in_array( $key, [ 'mycollections', 'userpage', 'mytalk' ], true ) ) {
                $newMenu['favorites'] = [
                    'text' => $skin->msg( 'favorites-personal-link' )->text(),
                    'href' => $favTitle->getFullURL(),
                    'class' => 'mw-favorites-personal-link',
                ];

                $inserted = true;
            }
        }

        if ( !$inserted ) {
            $newMenu = [
                'favorites' => [
                    'text' => $skin->msg( 'favorites-personal-link' )->text(),
                    'href' => $favTitle->getFullURL(),
                    'class' => 'mw-favorites-personal-link',
                ]
            ] + $newMenu;
        }

        $links['user-menu'] = $newMenu;
    }

    /**
     * Remove Favorites2 navigation entries from all possible navigation sections.
     *
     * This prevents duplicate favorite icons/tabs.
     *
     * @param array &$links
     * @return void
     */
    private static function removeFavoriteNavigationItems( array &$links ): void {
        $keys = [
            'favorite',
            'unfavorite',
            'favorites',
            'favorites2',
            'ca-favorite',
            'ca-unfavorite',
            'ca-favorites2'
        ];

        $sections = [
            'views',
            'actions',
            'namespaces',
            'variants'
        ];

        foreach ( $sections as $section ) {
            if ( !isset( $links[$section] ) || !is_array( $links[$section] ) ) {
                continue;
            }

            foreach ( $keys as $key ) {
                if ( isset( $links[$section][$key] ) ) {
                    unset( $links[$section][$key] );
                }
            }
        }
    }

    /**
     * Insert an item after a specific key in an associative array.
     *
     * If the key does not exist, the new item is appended.
     *
     * @param array $array
     * @param string $afterKey
     * @param string $newKey
     * @param array $newValue
     * @return array
     */
    private static function insertAfterKey( array $array, string $afterKey, string $newKey, array $newValue ): array {
        $result = [];
        $inserted = false;

        foreach ( $array as $key => $value ) {
            $result[$key] = $value;

            if ( $key === $afterKey ) {
                $result[$newKey] = $newValue;
                $inserted = true;
            }
        }

        if ( !$inserted ) {
            $result[$newKey] = $newValue;
        }

        return $result;
    }
}