<?php

class UserMergeConnector {
	private static $aCheckedBSUpdateFields = null;

	protected static function getBSUpdateFields() {
		if( !is_null(self::$aCheckedBSUpdateFields) ) {
			return self::$aCheckedBSUpdateFields;
		}
		self::$aCheckedBSUpdateFields = static::checkBSUpdateFields(
			$GLOBALS['bsgUserMergeConnectorUpdateFields']
		);
		return self::$aCheckedBSUpdateFields;
	}

	protected static function checkBSUpdateFields( $aFields, $aReturn = array() ) {
		$oDBr = wfGetDB( DB_SLAVE );
		foreach( $aFields as $aFieldInfo ) {
			if( !$oDBr->tableExists($aFieldInfo[0]) ) {
				continue;
			}
			$aReturn[] = $aFieldInfo;
		}
		return $aReturn;
	}

	public static function UserMergeAccountFields( &$aUpdateFields ) {
		$aUpdateFields = array_merge(
			$aUpdateFields,
			static::getBSUpdateFields()
		);
		return true;
	}

	/**
	 * ReviewTemplates use a list of id in the field 'revt_user'
	 * @param User $oldUser
	 * @param User $newUser
	 * @return boolean
	 */
	public static function onMergeAccountFromToManageReviewTemplates( User &$oldUser, User &$newUser ) {
		$oDBr = wfGetDB( DB_SLAVE );
		if( !class_exists('Review') ) {
			return true;
		}
		if( !$oDBr->tableExists('bs_review_templates') ) {
			return true;
		}
		$oRes = $oDBr->select(
			'bs_review_templates',
			array( 'revt_id', 'revt_user' ),
			'',
			__METHOD__
		);
		if( !$oRes ) {
			//something went wrong
			return true;
		}

		$aUpdateIDs = array();
		foreach( $oRes as $o ) {
			$aIDs = explode( ',', $o->revt_user );
			if( !in_array($oldUser->getId(), $aIDs) ) {
				continue;
			}

			$aUpdateIDs[$o->revt_id] = array_replace($aIDs,
				array_fill_keys(
					array_keys($aIDs, $oldUser->getId()),
					$newUser->getId()
				)
			);
		}

		if( empty($aUpdateIDs) ) {
			return true;
		}
		$oDBw = wfGetDB( DB_MASTER );
		foreach( $aUpdateIDs as $iID => $aValues ) {
			$oDBw->update(
				'bs_review_templates',
				array( 'revt_user' => implode(',', $aValues) ),
				array( 'revt_id' => $iID )
			);
		}
		return true;
	}

	/**
	 * PageAssignments use a users name in the table field 'pa_assignee_key'
	 * @param User $oldUser
	 * @param User $newUser
	 * @return boolean
	 */
	public static function onMergeAccountFromToManagePageAssignments( User &$oldUser, User &$newUser ) {
		$sTable = 'bs_pageassignments';
		if( !wfGetDB( DB_SLAVE )->tableExists( $sTable ) ) {
			return true;
		}
		$aFields = ['pa_assignee_type', 'pa_page_id', 'pa_assignee_key'];
		$aConditions = [
			'pa_assignee_type' => 'user',
			'pa_assignee_key' => $oldUser->getName()
		];
		$oRes = wfGetDB( DB_SLAVE )->select(
			$sTable,
			$aFields,
			$aConditions,
			__METHOD__
		);
		foreach( $oRes as $oRow ) {
			$aOldConds = array();
			foreach( $aFields as $sField ) {
				$aOldConds[$sField] = $oRow->{$sField};
			}
			$aNewConds = array_merge(
				$aOldConds,
				['pa_assignee_key' => $newUser->getName()]
			);

			$bPANewExists = wfGetDB( DB_SLAVE )->selectRow(
				$sTable,
				'*',
				$aNewConds
			);
			//Just delete the old users assignment, when the new user is already
			//assigned to the same page
			if( $bPANewExists ) {
				$bRes = wfGetDB( DB_MASTER )->delete(
					$sTable,
					$aOldConds,
					__METHOD__
				);
				continue;
			}
			$bRes = wfGetDB( DB_MASTER )->update(
				$sTable,
				$aNewConds,
				$aOldConds,
				__METHOD__
			);
		}
		return true;
	}

	public static function onMergeAccountFromToManageBSSocial( User &$oldUser, User &$newUser ) {
		if( !class_exists('BSSocial') ) {
			return true;
		}

		$oEntity = BSSocialEntityProfile::newFromUser( $newUser );
		if( $oEntity && $oEntity->exists() ) {
			$oEntity->delete();
		}

		$oRes = wfGetDB( DB_SLAVE )->select(
			'bs_social_entity',
			'bsse_id',
			array('bsse_ownerid' => (int) $oldUser->getId()),
			__METHOD__
		);

		foreach( $oRes as $o ) {
			if( !$oEntity = BSSocialEntity::newFromID($o->bsse_id) ) {
				continue;
			}
			$oEntity
				->setOwnerID( (int) $newUser->getId() )
				->save()
			;
		}
		return true;
	}
}