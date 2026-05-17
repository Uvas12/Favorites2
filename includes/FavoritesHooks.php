<?php
declare( strict_types = 1 );

class FavoritesHooks {

	public static function onBeforePageDisplay( $out, $skin ): void {
		$useIcon = isset( $GLOBALS['wgUseIconFavorite'] ) ? (bool)$GLOBALS['wgUseIconFavorite'] : true;
		$out->addModules( 'ext.favorites' );
		$out->addJsConfigVars( 'wgUseIconFavorite', $useIcon );

		// Pasar estado inicial del favorito al JS — evita AJAX para saber si ya es favorito
		$user   = $skin->getUser();
		$title  = $out->getTitle();
		$pageId = $title ? $title->getArticleID() : 0;
		if ( $user->isRegistered() && $pageId > 0 && $useIcon ) {
			$out->addJsConfigVars( 'wgIsFavorite', FavoritesDB::isFavorite( $user, $pageId ) );
		}

		// Notificación de sesión
		$session = $out->getRequest()->getSession();
		$notif   = $session->get( 'favoritesNotification' );
		if ( $notif ) {
			$session->remove( 'favoritesNotification' );
			$session->save();
			$out->addJsConfigVars( 'wgFavoritesNotification', $notif );
		}
	}

	public static function onSkinTemplateNavigation( $skin, array &$links ): void {
		$useIcon     = isset( $GLOBALS['wgUseIconFavorite'] ) ? (bool)$GLOBALS['wgUseIconFavorite'] : true;
		$personalURL = isset( $GLOBALS['wgFavoritesPersonalURL'] ) ? (bool)$GLOBALS['wgFavoritesPersonalURL'] : true;

		$user = $skin->getUser();
		if ( !$user->isRegistered() ) return;

		// Enlace "Mis favoritos" en menú de usuario
		if ( $personalURL ) {
			$favTitle = \SpecialPage::getTitleFor( 'Favorites' );
			$newMenu  = [];
			$inserted = false;
			foreach ( $links['user-menu'] ?? [] as $key => $item ) {
				$newMenu[$key] = $item;
				if ( !$inserted && in_array( $key, [ 'mycollections', 'userpage', 'mytalk' ], true ) ) {
					$newMenu['favorites'] = [
						'text'  => $skin->msg( 'favorites-personal-link' )->text(),
						'href'  => $favTitle->getFullURL(),
						'class' => 'mw-favorites-personal-link',
					];
					$inserted = true;
				}
			}
			if ( !$inserted ) {
				$newMenu = [ 'favorites' => [
					'text'  => $skin->msg( 'favorites-personal-link' )->text(),
					'href'  => $favTitle->getFullURL(),
					'class' => 'mw-favorites-personal-link',
				] ] + $newMenu;
			}
			$links['user-menu'] = $newMenu;
		}

		// Modo pestaña texto (solo cuando useIcon = false)
		if ( !$useIcon && !$skin->getTitle()->isSpecialPage() ) {
			$title  = $skin->getTitle();
			$pageId = $title->getArticleID();
			if ( $pageId > 0 ) {
				$isFav  = FavoritesDB::isFavorite( $user, $pageId );
				$action = $isFav ? 'removefavorite' : 'addfavorite';
				$msgKey = $isFav ? 'favorites-remove' : 'favorites-add';
				$links['actions']['favorite'] = [
					'text'  => $skin->msg( $msgKey )->text(),
					'href'  => $skin->getTitle()->getFullURL( [ 'action' => $action ] ),
					'class' => 'mw-favorite-tab' . ( $isFav ? ' is-favorite' : '' ),
				];
			}
		}
	}

	public static function onLoadExtensionSchemaUpdates( $updater ): void {
		$updater->addExtensionTable(
			'favorites',
			dirname( __DIR__ ) . '/sql/favorites.sql'
		);
	}
}
