<?php
/**
 * API module for Favorites2 extension
 *
 * @file
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiUsageException;
use Wikimedia\ParamValidator\ParamValidator;

class ApiFavorites extends ApiBase {

	/**
	 * @throws ApiUsageException
	 */
	public function execute(): void {
		$user = $this->getUser();

		if ( !$user->isRegistered() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'notloggedin' );
		}

		$params = $this->extractRequestParams();
		$pageId = (int)$params['pageid'];
		$subaction = $params['subaction'] ?? null;

		// Verificar que la página existe
		$dbr = \MediaWiki\MediaWikiServices::getInstance()
			->getConnectionProvider()->getReplicaDatabase();
		$page = $dbr->selectRow( 'page', [ 'page_id' ], [ 'page_id' => $pageId ] );
		if ( !$page ) {
			$this->dieWithError( [ 'apierror-nosuchpageid', $pageId ] );
		}

		$action = $this->getModuleName();

		if ( $action === 'addfavorite' ) {
			$this->requirePostedParameters( [ 'pageid' ] );
			$this->checkUserRightsAny( 'read' );
			$added = FavoritesDB::addFavorite( $user, $pageId );
			$this->getResult()->addValue( null, 'addfavorite', [
				'result' => $added ? 'Success' : 'AlreadyFavorite',
				'pageid' => $pageId,
			] );
		} elseif ( $action === 'removefavorite' ) {
			$this->requirePostedParameters( [ 'pageid' ] );
			$this->checkUserRightsAny( 'read' );
			$removed = FavoritesDB::removeFavorite( $user, $pageId );
			$this->getResult()->addValue( null, 'removefavorite', [
				'result' => $removed ? 'Success' : 'NotFavorite',
				'pageid' => $pageId,
			] );
		} elseif ( $action === 'favorites' && $subaction === 'check' ) {
			$isFav = FavoritesDB::isFavorite( $user, $pageId );
			$this->getResult()->addValue( null, 'favorites', [
				'isfavorite' => $isFav,
				'pageid' => $pageId,
			] );
		}
	}

	public function getAllowedParams(): array {
		return [
			'pageid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'subaction' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}

	public function needsToken(): string {
		$action = $this->getModuleName();
		if ( $action === 'favorites' ) {
			return 'never';
		}
		return 'csrf';
	}

	public function isWriteMode(): bool {
		return $this->getModuleName() !== 'favorites';
	}

	public function mustBePosted(): bool {
		return $this->getModuleName() !== 'favorites';
	}
}
