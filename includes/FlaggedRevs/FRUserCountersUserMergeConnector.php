<?php
//FlaggedRevs
//TODO: Remove this on later versions, cause this was added already - see:
//https://gerrit.wikimedia.org/r/#/c/146025/5
class FRUserCountersUserMergeConnector extends FRUserCounters{
	public static function deleteUserParams( User $user ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'flaggedrevs_promote',
			array( 'frp_user_id' => $user->getId() ),
			__METHOD__
		);
	}

	public static function mergeUserParams( User $oldUser, User $newUser ) {
		$oldParams = self::getUserParams( $oldUser->getId(), FR_MASTER );
		$newParams = self::getUserParams( $newUser->getId(), FR_MASTER );
		$newParams['uniqueContentPages'] = array_unique( array_merge(
			$newParams['uniqueContentPages'],
			$oldParams['uniqueContentPages']
		) );
		sort( $newParams['uniqueContentPages'] );
		$newParams['totalContentEdits'] += $oldParams['totalContentEdits'];
		$newParams['editComments'] += $oldParams['editComments'];
		$newParams['revertedEdits'] += $oldParams['revertedEdits'];

		self::saveUserParams( $newUser->getId(), $newParams );
	}

	public static function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = array( 'flaggedrevs', 'fr_user' );

		return true;
	}

	public static function onMergeAccountFromTo( User &$oldUser, User &$newUser ) {
		// Don't merge into anonymous users...
		if ( $newUser->getId() !== 0 ) {
			FRUserCountersUserMergeConnector::mergeUserParams( $oldUser, $newUser );
		}

		return true;
	}

	public static function onDeleteAccount( User $oldUser ) {
		FRUserCountersUserMergeConnector::deleteUserParams( $oldUser );

		return true;
	}
}