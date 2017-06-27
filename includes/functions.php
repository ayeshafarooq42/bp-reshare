<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Builds the argument of the reshared activity
 *
 * @package BP Reshare
 * @since    1.0
 *
 * @param  integer $activity_id the activity id
 * @uses   bp_activity_get_specific() to fetch the specific activity
 * @uses   bp_activity_get_meta() to get some meta infos about the activity
 * @uses   bp_loggedin_user_id() to get current user id
 * @uses   bp_activity_update_meta() to save some meta infos for the activity
 * @uses   bp_core_fetch_avatar() to build the avatar for the user
 * @uses   bp_core_get_userlink() to build the user link
 * @uses   bp_core_current_time() to date the reshare
 * @uses   apply_filters() at various places to let plugins/themes override values
 * @return array the reshared activity arguments
 */
function buddyreshare_prepare_reshare( $activity_id = 0 ) {

	$activity_to_reshare = bp_activity_get_specific( array( 'activity_ids' => $activity_id ) );

	if( empty( $activity_to_reshare ) )
		return array( 'error' => __( 'OOps, looks like the activity does not exist anymore', 'bp-reshare' ) );

	$activity = $activity_to_reshare['activities'][0];

	$reshared_by = bp_activity_get_meta( $activity_id, 'reshared_by' );

	if( is_array( $reshared_by ) && in_array( bp_loggedin_user_id(), $reshared_by ) )
		return array( 'error' => __( 'OOps, looks like you already reshared this activity', 'bp-reshare' ) );

	if( $activity->user_id == bp_loggedin_user_id() )
		return array( 'error' => __( 'OOps, looks like you are trying to reshare your own activity', 'bp-reshare' ) );

	/* get and increment reshared count */
	$rs_count = bp_activity_get_meta( $activity_id, 'reshared_count' );
	$rs_count = !empty( $rs_count ) ? (int)$rs_count + 1 : 1;
	bp_activity_update_meta( $activity_id, 'reshared_count', $rs_count );

	if( is_array( $reshared_by ) && !in_array( bp_loggedin_user_id(), $reshared_by ) )
		$reshared_by[] = bp_loggedin_user_id();
	else
		$reshared_by[] = bp_loggedin_user_id();

	bp_activity_update_meta( $activity_id, 'reshared_by', $reshared_by );

	$secondary_avatar = bp_core_fetch_avatar( array( 'item_id' => $activity->user_id, 'object' => 'user', 'type' => 'thumb', 'class' => 'avatar', 'width' => 20, 'height' => 20 ) );

	$component = $activity->component;
	$item_id = $activity->item_id;

	if( $component != 'activity' ){

		$user_link = bp_core_get_userlink( $activity->user_id );

		if( strpos( $activity->primary_link, $user_link ) === false ) {

			$action = apply_filters( 'buddyreshare_prepare_reshare_content', sprintf(
				__( '%s reshared a <a href="%s">content</a> originally shared by %s', 'bp-reshare' ),
				bp_core_get_userlink( bp_loggedin_user_id() ),
				$activity->primary_link,
				bp_core_get_userlink( $activity->user_id )
			), $activity );

		} else {
			$action = apply_filters( 'buddyreshare_prepare_reshare_nocontent', sprintf(
				__( '%s reshared some content originally shared by %s', 'bp-reshare' ),
				bp_core_get_userlink( bp_loggedin_user_id() ),
				bp_core_get_userlink( $activity->user_id )
			), $activity );
		}

	} else {

		$action = apply_filters( 'buddyreshare_prepare_reshare_activity', sprintf(
			__( '%s reshared an activity originally shared by %s', 'bp-reshare' ),
			bp_core_get_userlink( bp_loggedin_user_id() ),
			$secondary_avatar . bp_core_get_userlink( $activity->user_id )
		), $activity );

	}

	$reshared_args = array(
		'action'            => apply_filters( 'bp_reshare_action_parent_activity' , $action, $activity->type ),
		'content'           => $activity->content,
		'component'         => $component,
		'type'              => 'reshare_update',
		'user_id'           => bp_loggedin_user_id(),
		'secondary_item_id' => $activity_id,
		'recorded_time'     => bp_core_current_time(),
		'hide_sitewide'     => $activity->hide_sitewide
	);

	if( !empty( $item_id ) )
		$reshared_args['item_id'] = $item_id;

	return apply_filters( 'buddyreshare_prepare_reshare', $reshared_args, $activity_id );
}

/**
 * In case of a reshare delete, reset some activity metas
 *
 * @package BP Reshare
 * @since    1.0
 *
 * @param  integer $activity_id the reshared activity id
 * @param  integer $user_id     the user id
 * @uses   bp_activity_get_meta() to get some meta infos about the activity
 * @uses   bp_activity_delete_meta() to delete some meta infos for the activity
 * @uses   bp_activity_update_meta() to save some meta infos for the activity
 * @return boolean true
 */
function buddyreshare_reset_metas( $activity_id = 0, $user_id = 0 ) {
	if( empty( $activity_id ) || empty( $user_id ) )
		return false;

	$count = bp_activity_get_meta( $activity_id, 'reshared_count' );
	$count = $count - 1;
	$reshared_by = bp_activity_get_meta( $activity_id, 'reshared_by' );

	if( $count == 0 ) {
		// if count is null, then we can delete all metas !
		bp_activity_delete_meta( $activity_id, 'reshared_count' );
		bp_activity_delete_meta( $activity_id, 'reshared_by' );
	} else {
		foreach( $reshared_by as $key => $val ) {
			if( $user_id  == $val )
				unset( $reshared_by[$key] );
		}
		bp_activity_update_meta( $activity_id, 'reshared_count', $count );
		bp_activity_update_meta( $activity_id, 'reshared_by', $reshared_by );
	}

	return true;

}

/**
 * Returns the total reshares for a given user
 *
 * @package BP Reshare
 * @since    1.0
 *
 * @param  integer $user_id [description]
 * @uses   bp_get_user_meta() to get the number of reshares of the user
 * @return integer the number of reshares of the user
 */
function buddyreshare_get_total_reshares_count( $user_id = 0 ) {
	return intval( bp_get_user_meta( $user_id, 'buddyreshare_count', true ) );
}

/**
 * Can this activity be reshared
 *
 * @package BP Reshare
 * @since    1.0
 *
 * @param  BP_Activity_Activity $activity the activity object
 * @uses   is_user_logged_in() to check we have a logged in user
 * @uses   buddyreshare_activity_types() to get the "resharable" activities
 * @uses   buddyreshare_is_user_profile_reshares() to check for the user reshare tab of his profile
 * @uses   bp_is_my_profile() to check if the displayed profile is the one of the loggedin user
 * @return boolean true|false
 */
function buddyreshare_can_reshare( $activity = null ) {
	if( empty( $activity ) )
		return false;

	if( ! is_user_logged_in() )
		return false;

	if ( ! empty( $activity->hide_sitewide ) )
		return false;

	if( ! in_array( $activity->type, buddyreshare_activity_types() ) )
		return false;

	if( buddyreshare_is_user_profile_reshares() && ! bp_is_my_profile() )
		return false;

	return true;
}


/**
 * Returns the reshared css class in case an activity has been reshared by the user
 *
 * @package BP Reshare
 * @since    1.0
 *
 * @param  BP_Activity_Activity $activity the activity object
 * @param  integer $activity_first_id the reshared activity id
 * @uses   bp_loggedin_user_id() to get current user id
 * @uses   bp_activity_get_meta() to get some meta infos about the activity
 * @uses   bp_activity_get_specific() to fetch the specific activity
 * @return string the reshared css class
 */
function buddyreshare_get_class( $activity = null, $activity_first_id = 0 ) {
	if( empty( $activity ) )
		return false;

	if( bp_loggedin_user_id() == $activity->user_id )
		return 'reshared';

	$reshared_by = bp_activity_get_meta( $activity_first_id, 'reshared_by' );

	if( is_array( $reshared_by ) && in_array( bp_loggedin_user_id(), $reshared_by ) )
		return 'reshared';

	// is the loggedin_user the original author ?
	$originally_shared = bp_activity_get_specific( array( 'activity_ids' => $activity_first_id ) );

	if( $originally_shared['activities'][0]->user_id == bp_loggedin_user_id() )
		return 'reshared';
}

function buddyreshare_rest_get_all_items( WP_REST_Request $request ) {
	global $wpdb;

	$table = bp_core_get_table_prefix() . 'bp_activity_user_reshares';
	$query = "SELECT activity_id, user_id  FROM {$table}";

	$activities = $request->get_param( 'activities' );
	if ( $activities ) {
		$query .= ' WHERE activity_id IN (' . join( ',', wp_parse_id_list( $activities ) ) . ')';
	}

	$reshares = $wpdb->get_results( $query );

	$result = array();
	foreach ( $reshares as $reshare ) {
		if ( ! isset( $result[ $reshare->activity_id ] ) ) {
			$result[ $reshare->activity_id ] = array( 'id' => $reshare->activity_id, 'users' => array( $reshare->user_id ) );
		} else {
			$result[ $reshare->activity_id ]['users'] = array_merge( $result[ $reshare->activity_id ]['users'], array( $reshare->user_id ) );
		}
	}

	return rest_ensure_response( array_values( $result ) );
}

function buddyreshare_rest_get_all_items_permissions_check( WP_REST_Request $request ) {
	return true;
}

function buddyreshare_rest_get_items( WP_REST_Request $request ) {
	$activity_id = (int) $request->get_param( 'id' );
	$user_id     = (int) $request->get_param( 'user_id' );

	$reshares = bp_activity_get_meta( $activity_id, 'reshared_by' );
	if ( ! is_array( $reshares ) && '' === $reshares ) {
		$reshares = array();
	}

	$count = count( $reshares );

	if ( empty( $reshares ) || ! in_array( $user_id, $reshares, true ) ) {
		$result = array(
			'link'  => 'addLink',
			'text'  => 'addReshare',
			'count' => $count,
		);
	} else {
		$result = array(
			'link'  => 'removeLink',
			'text'  => 'removeReshare',
			'count' => $count,
		);
	}

	return rest_ensure_response( $result );
}

function buddyreshare_rest_get_items_permissions_check( WP_REST_Request $request ) {
	return true;
}

function buddyreshare_rest_update_item( WP_REST_Request $request ) {
	global $wpdb;

	$table = bp_core_get_table_prefix() . 'bp_activity_user_reshares';
	$args = $request->get_params();

	if ( isset( $args['id'] ) ) {
		$args['activity_id'] = (int) $args['id'];
	}

	$defaults = array(
		'user_id'       => get_current_user_id(),
		'activity_id'   => 0,
		'date_reshared' => current_time( 'mysql' ),
	);

	$r = array_intersect_key( wp_parse_args( $args, $defaults ), $defaults );

	if ( empty( $r['user_id'] ) || empty( $r['activity_id'] ) || empty( $r['date_reshared'] ) ) {
		return new WP_Error( 'bp_reshare_missing_argument', __( 'Missing argument' ), array( 'status' => 500 ) );
	}

	$reshared = $wpdb->insert(
		$table,
		$r
	);

	return rest_ensure_response( array( 'reshared' => (bool) $reshared ) );
}

function buddyreshare_rest_update_item_permissions_check( WP_REST_Request $request ) {
	return true;
}

function buddyreshare_rest_delete_item( WP_REST_Request $request ) {
	return $request->get_params();
}

function buddyreshare_rest_delete_item_permissions_check( WP_REST_Request $request ) {
	return true;
}

function buddyreshare_rest_routes() {
	$buddyreshare = buddyreshare();

	$namespace = sprintf( '%1$s/%2$s', $buddyreshare->rest->namespace, $buddyreshare->rest->version );

	register_rest_route( $namespace, '/(?P<id>[\d]+)', array(
		'args' => array(
			'id' => array(
				'description' => __( 'Unique identifier for the object.' ),
				'type'        => 'integer',
				'validate_callback' => function( $param, $request, $key ) {
					return is_numeric( $param );
				}
			),
		),
		array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'buddyreshare_rest_get_items',
			'permission_callback' => 'buddyreshare_rest_get_items_permissions_check',
			'args'     => array(
				'count' => array(
					'type' => 'boolean',
					'default' => false,
					'description' => __( 'Whether to get only the count or not' ),
				),
			),
		),
		array(
			'methods'  => WP_REST_Server::EDITABLE,
			'callback' => 'buddyreshare_rest_update_item',
			'permission_callback' => 'buddyreshare_rest_update_item_permissions_check',
			'args'     => array(
				'user_id' => array(
					'type'        => 'integer',
					'default'     => 0,
					'description' => __( 'The user ID.' ),
				),
			),
		),
		array(
			'methods'  => WP_REST_Server::DELETABLE,
			'callback' => 'buddyreshare_rest_delete_item',
			'permission_callback' => 'buddyreshare_rest_delete_item_permissions_check',
			'args'     => array(
				'user_id' => array(
					'type'        => 'integer',
					'default'     => 0,
					'description' => __( 'The user ID.' ),
				),
			),
		),
	) );

	register_rest_route( $namespace, '/all', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'buddyreshare_rest_get_all_items',
			'permission_callback' => 'buddyreshare_rest_get_all_items_permissions_check',
			'args'     => array(
				'activities' => array(
					'page' => array(
					'type' => 'string',
					'default' => '',
					'description' => __( 'comma separated list of activity ids to fetch' ),
				),
				'page' => array(
					'type' => 'integer',
					'default' => 1,
					'description' => __( 'The number of the page to fetch' ),
				),
				'per_page' => array(
					'type' => 'integer',
					'default' => 20,
					'description' => __( 'The amount of reshares per page to fetch' ),
				),
			),
		) ) );
}
add_action( 'rest_api_init', 'buddyreshare_rest_routes' );