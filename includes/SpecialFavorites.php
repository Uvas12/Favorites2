<?php
declare( strict_types = 1 );

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class SpecialFavorites extends SpecialPage {

	public function __construct() {
		// Sin permiso especial → visible para todos en Special:SpecialPages
		parent::__construct( 'Favorites' );
	}

	public function execute( $par ): void {
		$user = $this->getUser();
		if ( !$user->isRegistered() ) {
			$this->requireLogin();
			return;
		}

		$this->setHeaders();
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'favorites-page-title' )->text() );
		$out->addModules( 'ext.favorites' );

		$grouped = FavoritesDB::getFavoritesGrouped( $user );

		if ( empty( $grouped ) ) {
			$out->addHTML( Html::element( 'p', [ 'class' => 'mw-favorites-empty' ],
				$this->msg( 'favorites-empty' )->text() ) );
			return;
		}

		$nsNames = $this->getLanguage()->getNamespaces();
		$html    = Html::openElement( 'div', [ 'class' => 'mw-favorites-page' ] );

		foreach ( $grouped as $nsId => $pages ) {
			$nsLabel = ( $nsId === NS_MAIN )
				? $this->msg( 'blanknamespace' )->text()
				: str_replace( '_', ' ', $nsNames[ $nsId ] ?? "Namespace $nsId" );

			$html .= Html::openElement( 'div', [ 'class' => 'mw-favorites-namespace-section' ] );
			$html .= Html::element( 'h3', [ 'class' => 'mw-favorites-namespace-header' ], $nsLabel );
			$html .= Html::openElement( 'ul', [ 'class' => 'mw-favorites-list-ul' ] );

			foreach ( $pages as $page ) {
				$title      = Title::makeTitle( $page['page_namespace'], $page['page_title'] );
				$pageLink   = $this->getLinkRenderer()->makeLink( $title );
				$editLink   = $this->getLinkRenderer()->makeLink(
					$title, $this->msg( 'editlink' )->text(), [], [ 'action' => 'edit' ]
				);
				$removeLink = Html::element( 'a', [
					'href'           => '#',
					'class'          => 'mw-favorites-remove-btn',
					'data-page-id'   => $page['page_id'],
					'data-page-name' => str_replace( '_', ' ', $page['page_title'] ),
				], $this->msg( 'favorites-remove' )->text() );

				$html .= Html::openElement( 'li' );
				$html .= Html::rawElement( 'span', [ 'class' => 'mw-favorites-page-link' ], $pageLink );
				$html .= Html::rawElement( 'span', [ 'class' => 'mw-favorites-actions' ],
					'(' . $editLink . ' | ' . $removeLink . ')' );
				$html .= Html::closeElement( 'li' );
			}

			$html .= Html::closeElement( 'ul' );
			$html .= Html::closeElement( 'div' );
		}

		$html .= Html::closeElement( 'div' );
		$out->addHTML( $html );
	}

	/** Aparecer en Special:SpecialPages */
	public function isListed(): bool {
		return true;
	}

	/** Sección "Otras páginas especiales" — grupo que siempre existe en MW */
	protected function getGroupName(): string {
		return 'other';
	}
}
