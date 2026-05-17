<?php
/**
 * Database operations for Favorites2 extension
 *
 * @file
 * @license GPL-2.0-or-later
 */

declare( strict_types = 1 );

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

class FavoritesDB {

	/**
	 * Add a page to user's favorites
	 *
	 * @param UserIdentity $user
	 * @param int $pageId
	 * @return bool
	 */
	public static function addFavorite( UserIdentity $user, int $pageId ): bool {
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		// Check if already exists
		$exists = $dbw->selectRow(
			'favorites',
			'1',
			[
				'favorite_user_id' => $user->getId(),
				'favorite_page_id' => $pageId
			]
		);

		if ( $exists ) {
			return false;
		}

		// Insert new favorite
		$dbw->insert(
			'favorites',
			[
				'favorite_user_id' => $user->getId(),
				'favorite_page_id' => $pageId,
				'favorite_timestamp' => $dbw->timestamp()
			]
		);

		return $dbw->affectedRows() > 0;
	}

	/**
	 * Remove a page from user's favorites
	 *
	 * @param UserIdentity $user
	 * @param int $pageId
	 * @return bool
	 */
	public static function removeFavorite( UserIdentity $user, int $pageId ): bool {
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$dbw->delete(
			'favorites',
			[
				'favorite_user_id' => $user->getId(),
				'favorite_page_id' => $pageId
			]
		);

		return $dbw->affectedRows() > 0;
	}

	/**
	 * Check if a page is in user's favorites
	 *
	 * @param UserIdentity $user
	 * @param int $pageId
	 * @return bool
	 */
	public static function isFavorite( UserIdentity $user, int $pageId ): bool {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$result = $dbr->selectRow(
			'favorites',
			'1',
			[
				'favorite_user_id' => $user->getId(),
				'favorite_page_id' => $pageId
			]
		);

		return $result !== false;
	}

	/**
	 * Get all user's favorites
	 *
	 * @param UserIdentity $user
	 * @return array Array of [ 'page_id' => id, 'page_title' => title, 'page_namespace' => ns ]
	 */
	public static function getFavorites( UserIdentity $user ): array {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$result = $dbr->select(
			[ 'favorites', 'page' ],
			[ 'page_id', 'page_title', 'page_namespace' ],
			[ 'favorite_user_id' => $user->getId() ],
			__METHOD__,
			[ 'ORDER BY' => 'favorite_timestamp DESC' ],
			[ 'page' => [ 'INNER JOIN', 'favorite_page_id = page_id' ] ]
		);

		$favorites = [];
		foreach ( $result as $row ) {
			$favorites[] = [
				'page_id' => (int)$row->page_id,
				'page_title' => $row->page_title,
				'page_namespace' => (int)$row->page_namespace,
			];
		}

		return $favorites;
	}

	/**
	 * Get favorites organized by namespace and alphabetically
	 *
	 * @param UserIdentity $user
	 * @return array[ namespace => [ title => [...] ] ]
	 */
	public static function getFavoritesGrouped( UserIdentity $user ): array {
		$favorites = self::getFavorites( $user );
		$grouped = [];

		foreach ( $favorites as $fav ) {
			$ns = $fav['page_namespace'];
			if ( !isset( $grouped[$ns] ) ) {
				$grouped[$ns] = [];
			}
			$grouped[$ns][] = $fav;
		}

		// Sort each namespace alphabetically
		foreach ( $grouped as &$items ) {
			usort( $items, function( $a, $b ) {
				return strcasecmp( $a['page_title'], $b['page_title'] );
			} );
		}

		// Sort namespaces
		ksort( $grouped );

		return $grouped;
	}

	/**
	 * Get count of user's favorites
	 *
	 * @param UserIdentity $user
	 * @return int
	 */
	public static function getFavoriteCount( UserIdentity $user ): int {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$count = $dbr->selectRowCount(
			'favorites',
			'*',
			[ 'favorite_user_id' => $user->getId() ]
		);

		return (int)$count;
	}
}
