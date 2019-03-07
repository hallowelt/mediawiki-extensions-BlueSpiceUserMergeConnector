<?php

namespace BlueSpice\UserMergeConnector;

use User;

class Extension extends \BlueSpice\Extension {
	private static $checkedBSUpdateFields = null;

	/**
	 * extension.json callback
	 * @global array $wgUserMergeProtectedGroups
	 */
	public static function onRegistration() {
		$GLOBALS['wgUserMergeProtectedGroups'] = [];

		$GLOBALS['bsgUserMergeConnectorUpdateFields'] = [
			// BlueSpiceExtensions
			[ 'bs_dashboards_configs', 'dc_identifier' ],
			[ 'bs_readers', 'readers_user_id', 'readers_user_name' ],
			[ 'bs_responsible_editors', 're_user_id' ],
			[ 'bs_review', 'rev_owner' ],
			[ 'bs_review_steps', 'revs_user_id' ],
			[ 'bs_review_templates', 'revt_owner' ],
			// needs more functionality: see below
			// array( 'bs_review_templates', 'revt_user' ),
			// needs more functionality. Also BS does not care on userDelete
			// array( 'bs_saferedit', 'se_user_name' ), needs more functionality
			[ 'bs_searchstats', 'stats_user' ],
			[ 'bs_shoutbox', 'sb_user_id', 'sb_user_name' ],
			// needs more functionality. Also BS does not care on userDelete
			// array( 'bs_whoisonline', 'wo_user_id', 'wo_user_name' ),

			// BlueSpiceDistribution
			[ 'echo_email_batch', 'eeb_user_id' ],
			[ 'echo_event', 'event_agent_id' ],
			[ 'echo_notification', 'notification_user' ],

			// BlueSpiceTeamwork
			[ 'bs_reminder', 'rem_user_id' ],

			// BlueSpiceRating
			[ 'bs_rating', 'rat_userid', 'rat_userip' ],

			// BlueSpiceArticelPermissions
			[ 'bs_user_admission', 'ua_user_id' ],

			// BlueSpiceRentALink
			[ 'bs_ad_banners_customers', 'adbc_user_id' ],

			// ReadConfirmation
			[ 'bs_readconfirmation', 'rc_user_id' ],
		];
	}

	/**
	 *
	 * @return array
	 */
	protected static function getBSUpdateFields() {
		if ( !is_null( self::$checkedBSUpdateFields ) ) {
			return self::$checkedBSUpdateFields;
		}
		self::$checkedBSUpdateFields = static::checkBSUpdateFields(
			$GLOBALS['bsgUserMergeConnectorUpdateFields']
		);
		return self::$checkedBSUpdateFields;
	}

	/**
	 *
	 * @param array $fields
	 * @param array $return
	 * @return array
	 */
	protected static function checkBSUpdateFields( $fields, $return = [] ) {
		$DBr = wfGetDB( DB_REPLICA );
		foreach ( $fields as $fieldInfo ) {
			if ( !$DBr->tableExists( $fieldInfo[0] ) ) {
				continue;
			}
			$return[] = $fieldInfo;
		}
		return $return;
	}

	/**
	 *
	 * @param array &$updateFields
	 * @return bool
	 */
	public static function UserMergeAccountFields( &$updateFields ) {
		$updateFields = array_merge(
			$updateFields,
			static::getBSUpdateFields()
		);
		return true;
	}

	/**
	 * ReviewTemplates use a list of id in the field 'revt_user'
	 * @param User &$oldUser
	 * @param User &$newUser
	 * @return bool
	 */
	public static function onMergeAccountFromToManageReviewTemplates( User &$oldUser, User &$newUser ) {
		$dBr = wfGetDB( DB_REPLICA );
		if ( !class_exists( 'Review' ) ) {
			return true;
		}
		if ( !$dBr->tableExists( 'bs_review_templates' ) ) {
			return true;
		}
		$res = $dBr->select(
			'bs_review_templates',
			[ 'revt_id', 'revt_user' ],
			'',
			__METHOD__
		);
		if ( !$res ) {
			// something went wrong
			return true;
		}

		$updateIDs = [];
		foreach ( $res as $row ) {
			$iDs = explode( ',', $row->revt_user );
			if ( !in_array( $oldUser->getId(), $iDs ) ) {
				continue;
			}

			$updateIDs[$row->revt_id] = array_replace( $iDs,
				array_fill_keys(
					array_keys( $iDs, $oldUser->getId() ),
					$newUser->getId()
				)
			);
		}

		if ( empty( $updateIDs ) ) {
			return true;
		}
		$dBw = wfGetDB( DB_MASTER );
		foreach ( $updateIDs as $iD => $values ) {
			$dBw->update(
				'bs_review_templates',
				[ 'revt_user' => implode( ',', $values ) ],
				[ 'revt_id' => $iD ]
			);
		}
		return true;
	}

	/**
	 * PageAssignments use a users name in the table field 'pa_assignee_key'
	 * @param User &$oldUser
	 * @param User &$newUser
	 * @return bool
	 */
	public static function onMergeAccountFromToManagePageAssignments( User &$oldUser, User &$newUser ) {
		$table = 'bs_pageassignments';
		if ( !wfGetDB( DB_REPLICA )->tableExists( $table ) ) {
			return true;
		}
		$fields = [ 'pa_assignee_type', 'pa_page_id', 'pa_assignee_key' ];
		$conditions = [
			'pa_assignee_type' => 'user',
			'pa_assignee_key' => $oldUser->getName()
		];
		$res = wfGetDB( DB_REPLICA )->select(
			$table,
			$fields,
			$conditions,
			__METHOD__
		);
		foreach ( $res as $row ) {
			$oldConds = [];
			foreach ( $fields as $sField ) {
				$oldConds[$sField] = $row->{$sField};
			}
			$newConds = array_merge(
				$oldConds,
				[ 'pa_assignee_key' => $newUser->getName() ]
			);

			$pANewExists = wfGetDB( DB_REPLICA )->selectRow(
				$table,
				'*',
				$newConds
			);
			// Just delete the old users assignment, when the new user is already
			// assigned to the same page
			if ( $pANewExists ) {
				$res2 = wfGetDB( DB_MASTER )->delete(
					$table,
					$oldConds,
					__METHOD__
				);
				continue;
			}
			$res2 = wfGetDB( DB_MASTER )->update(
				$table,
				$newConds,
				$oldConds,
				__METHOD__
			);
		}
		return true;
	}

	/**
	 * Merge Social Profiles
	 * @param User &$oldUser
	 * @param User &$newUser
	 * @return bool
	 */
	public static function onMergeAccountFromToManageBSSocial( User &$oldUser, User &$newUser ) {
		if ( !class_exists( '\BlueSpice\Social\Profile\Entity\Profile' ) ) {
			return true;
		}

		$entity = \BlueSpice\Social\Profile\Entity\Profile::newFromUser( $newUser );
		if ( $entity && $entity->exists() ) {
			$entity->delete();
		}
/*
		$res = wfGetDB( DB_REPLICA )->select(
			'bs_social_entity',
			'bsse_id',
			[ 'bsse_ownerid' => (int)$oldUser->getId() ],
			__METHOD__
		);
*/
		foreach ( $res as $row ) {
			$entity = \BlueSpice\Social\Entity::newFromID( $row->bsse_id );
			if ( !$entity ) {
				continue;
			}
			$entity
				->setOwnerID( (int)$newUser->getId() )
				->save();
		}
		return true;
	}

}
