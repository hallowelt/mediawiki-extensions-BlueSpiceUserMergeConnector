<?php
$bsgUserMergeConnectorUpdateFields = array(
	//BlueSpiceExtensions
	array( 'bs_dashboards_configs', 'dc_identifier' ),
	array( 'bs_readers', 'readers_user_id', 'readers_user_name' ),
	array( 'bs_responsible_editors', 're_user_id' ),
	array( 'bs_review', 'rev_owner' ),
	array( 'bs_review_steps', 'revs_user_id' ),
	array( 'bs_review_templates', 'revt_owner' ),
	//needs more functionality: see below
	//array( 'bs_review_templates', 'revt_user' ),
	//needs more functionality. Also BS does not care on userDelete
	//array( 'bs_saferedit', 'se_user_name' ), needs more functionality
	array( 'bs_searchstats', 'stats_user' ),
	array( 'bs_shoutbox', 'sb_user_id', 'sb_user_name' ),
	//needs more functionality. Also BS does not care on userDelete
	//array( 'bs_whoisonline', 'wo_user_id', 'wo_user_name' ),

	//BlueSpiceDistribution
	array( 'echo_email_batch', 'eeb_user_id' ),
	array( 'echo_event', 'event_agent_id' ),
	array( 'echo_notification', 'notification_user' ),

	//BlueSpiceTeamwork
	array( 'bs_reminder', 'rem_user_id' ),

	//BlueSpiceRating
	array( 'bs_rating', 'rat_userid', 'rat_userip' ),

	//BlueSpiceArticelPermissions
	array( 'bs_user_admission', 'ua_user_id' ),

	//BlueSpiceRentALink
	array( 'bs_ad_banners_customers', 'adbc_user_id' ),
);

$wgExtensionCredits['other'][] = array(
	'path'           => __FILE__,
	'name'           => 'UserMergeConnector',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:BlueSpice',
	'author'         => array( 'Patric Wirth' ),
	'descriptionmsg' => 'Connects BlueSpice to UserMerge',
	'version'        => '2.24.0'
);

$wgGroupPermissions['bureaucrat']['usermerge'] = true;
$wgGroupPermissions['sysop']['usermerge'] = true;

$wgUserMergeProtectedGroups = array();

$wgAutoloadClasses['UserMergeConnector']
	= __DIR__."/UserMergeConnector.class.php";

$wgHooks['UserMergeAccountFields'][]
	= 'UserMergeConnector::UserMergeAccountFields';

$wgHooks['MergeAccountFromTo'][]
	= 'UserMergeConnector::onMergeAccountFromToManageReviewTemplates';

$wgHooks['MergeAccountFromTo'][]
	= 'UserMergeConnector::onMergeAccountFromToManageBSSocial';

//FlaggedRevs
//TODO: Remove this on later versions, cause this was added already - see:
//https://gerrit.wikimedia.org/r/#/c/146025/5
if( class_exists('FRUserCounters') ) {
	$wgAutoloadLocalClasses['FRUserCountersUserMergeConnector']
		= __DIR__."/includes/FlaggedRevs/FRUserCountersUserMergeConnector.php";

	#UserMerge hooks
	$wgHooks['UserMergeAccountFields'][]
		= 'FRUserCountersUserMergeConnector::onUserMergeAccountFields';
	$wgHooks['MergeAccountFromTo'][]
		= 'FRUserCountersUserMergeConnector::onMergeAccountFromTo';
	$wgHooks['DeleteAccount'][]
		= 'FRUserCountersUserMergeConnector::onDeleteAccount';
}