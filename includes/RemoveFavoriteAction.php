<?php
declare( strict_types = 1 );

use MediaWiki\Actions\FormlessAction;

class RemoveFavoriteAction extends FormlessAction {

	public function getName(): string {
		return 'removefavorite';
	}

	public function onView(): string {
		return '';
	}

	public function show(): void {
		$user  = $this->getUser();
		$title = $this->getTitle();

		if ( !$user->isRegistered() ) {
			$this->getOutput()->redirect(
				\SpecialPage::getTitleFor( 'Userlogin' )->getFullURL()
			);
			return;
		}

		$pageId   = $title->getArticleID();
		$pageName = $title->getPrefixedText();

		if ( $pageId > 0 ) {
			FavoritesDB::removeFavorite( $user, $pageId );
		}

		// Guardar notificación en sesión para mostrarla tras el redirect
		$session = $this->getRequest()->getSession();
		$session->set( 'favoritesNotification', [
			'type' => 'removed',
			'page' => $pageName,
		] );
		$session->save();

		$this->getOutput()->redirect( $title->getFullURL() );
	}
}
