<?php
declare( strict_types = 1 );

use MediaWiki\MediaWikiServices;

class FavoritesDB {

    /**
     * Check if a page is already in the user's favorites.
     *
     * @param User $user
     * @param int $pageId
     * @return bool
     */
    public static function isFavorite( $user, int $pageId ): bool {
        if ( !$user || !$user->isRegistered() || $pageId <= 0 ) {
            return false;
        }

        $dbr = MediaWikiServices::getInstance()
            ->getDBLoadBalancer()
            ->getConnection( DB_REPLICA );

        $row = $dbr->newSelectQueryBuilder()
            ->select( 'favorite_id' )
            ->from( 'favorites' )
            ->where( [
                'favorite_user_id' => $user->getId(),
                'favorite_page_id' => $pageId
            ] )
            ->caller( __METHOD__ )
            ->fetchRow();

        return (bool)$row;
    }

    /**
     * Add a page to the user's favorites.
     *
     * @param User $user
     * @param int $pageId
     * @return bool
     */
    public static function addFavorite( $user, int $pageId ): bool {
        if ( !$user || !$user->isRegistered() || $pageId <= 0 ) {
            return false;
        }

        if ( self::isFavorite( $user, $pageId ) ) {
            return true;
        }

        $dbw = MediaWikiServices::getInstance()
            ->getDBLoadBalancer()
            ->getConnection( DB_PRIMARY );

        $dbw->newInsertQueryBuilder()
            ->insertInto( 'favorites' )
            ->row( [
                'favorite_user_id' => $user->getId(),
                'favorite_page_id' => $pageId,
                'favorite_timestamp' => $dbw->timestamp()
            ] )
            ->caller( __METHOD__ )
            ->execute();

        return true;
    }

    /**
     * Remove a page from the user's favorites.
     *
     * @param User $user
     * @param int $pageId
     * @return bool
     */
    public static function removeFavorite( $user, int $pageId ): bool {
        if ( !$user || !$user->isRegistered() || $pageId <= 0 ) {
            return false;
        }

        $dbw = MediaWikiServices::getInstance()
            ->getDBLoadBalancer()
            ->getConnection( DB_PRIMARY );

        $dbw->newDeleteQueryBuilder()
            ->deleteFrom( 'favorites' )
            ->where( [
                'favorite_user_id' => $user->getId(),
                'favorite_page_id' => $pageId
            ] )
            ->caller( __METHOD__ )
            ->execute();

        return true;
    }

    /**
     * Remove all favorite records associated with a page.
     *
     * Used by:
     * - PageDeleteComplete for normal MediaWiki page deletions.
     * - MediaWikiPerformAction for DeletePagesForGood permanent deletions.
     *
     * @param int $pageId
     * @return void
     */
    public static function removeAllFavoritesForPage( int $pageId ): void {
        if ( $pageId <= 0 ) {
            return;
        }

        $dbw = MediaWikiServices::getInstance()
            ->getDBLoadBalancer()
            ->getConnection( DB_PRIMARY );

        $dbw->newDeleteQueryBuilder()
            ->deleteFrom( 'favorites' )
            ->where( [
                'favorite_page_id' => $pageId
            ] )
            ->caller( __METHOD__ )
            ->execute();
    }

    /**
     * Remove orphan favorite records.
     *
     * This deletes favorites whose favorite_page_id no longer exists
     * in the page table.
     *
     * Useful for pages permanently deleted by extensions that may bypass
     * MediaWiki's normal PageDeleteComplete hook.
     *
     * @return void
     */
    public static function purgeOrphanFavorites(): void {
        $dbw = MediaWikiServices::getInstance()
            ->getDBLoadBalancer()
            ->getConnection( DB_PRIMARY );

        $favoritesTable = $dbw->tableName( 'favorites' );
        $pageTable = $dbw->tableName( 'page' );

        $dbw->query(
            "DELETE f
            FROM $favoritesTable f
            LEFT JOIN $pageTable p ON p.page_id = f.favorite_page_id
            WHERE p.page_id IS NULL",
            __METHOD__
        );
    }

    /**
     * Get the user's favorites grouped by namespace.
     *
     * Used by Special:Favorites.
     *
     * @param User $user
     * @return array
     */
    public static function getFavoritesGrouped( $user ): array {
        if ( !$user || !$user->isRegistered() ) {
            return [];
        }

        $dbr = MediaWikiServices::getInstance()
            ->getDBLoadBalancer()
            ->getConnection( DB_REPLICA );

        $res = $dbr->newSelectQueryBuilder()
            ->select( [
                'favorite_id',
                'favorite_page_id',
                'favorite_timestamp',
                'page_namespace',
                'page_title'
            ] )
            ->from( 'favorites' )
            ->join( 'page', null, 'page_id = favorite_page_id' )
            ->where( [
                'favorite_user_id' => $user->getId()
            ] )
            ->orderBy( [
                'page_namespace',
                'page_title'
            ] )
            ->caller( __METHOD__ )
            ->fetchResultSet();

        $grouped = [];

        foreach ( $res as $row ) {
            $namespace = (int)$row->page_namespace;

            if ( !isset( $grouped[$namespace] ) ) {
                $grouped[$namespace] = [];
            }

            $grouped[$namespace][] = [
                'favorite_id' => (int)$row->favorite_id,
                'page_id' => (int)$row->favorite_page_id,
                'favorite_timestamp' => $row->favorite_timestamp,
                'page_namespace' => (int)$row->page_namespace,
                'page_title' => $row->page_title
            ];
        }

        return $grouped;
    }
}