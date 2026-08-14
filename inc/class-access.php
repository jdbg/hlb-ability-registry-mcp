<?php
/**
 * Shared access rules applied by ability handlers.
 *
 * @package HLB\MCP
 */

namespace HLB\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Cross-cutting authorization helpers used inside ability handlers.
 *
 * The Abilities API permission callback can only check one coarse capability
 * per ability (`read`, `edit_posts`, …). Anything that depends on the *object*
 * being addressed — its post type, its status, its author — has to be checked
 * by the handler itself, after the input is known. These helpers keep those
 * checks identical across handlers.
 */
class Access {

	/**
	 * Post types an ability may read from or write to.
	 *
	 * A post type qualifies when it is already reachable through the site's own
	 * public surface: either front-end public (`public`) or exposed over the
	 * REST API (`show_in_rest`). Post types that are neither — internal stores
	 * used by plugins for form entries, licences, logs and similar private data
	 * — are never addressable through this plugin, so a coarse `read`
	 * capability can't be used to pull them out by id.
	 *
	 * @return string[] Post type names.
	 */
	public static function post_types() {
		$types = array_values(
			array_unique(
				array_merge(
					array_values( get_post_types( [ 'public' => true ] ) ),
					array_values( get_post_types( [ 'show_in_rest' => true ] ) )
				)
			)
		);

		/**
		 * Filter the post types abilities may address.
		 *
		 * Defaults to every post type that is public or exposed in the REST API.
		 * Adding a private post type here makes it reachable through the enabled
		 * abilities, subject to the usual per-object capability checks.
		 *
		 * @param string[] $types Post type names.
		 */
		$types = apply_filters( 'hlb_mcp_allowed_post_types', $types );

		return array_values( array_filter( array_map( 'strval', (array) $types ) ) );
	}

	/**
	 * Whether a post type may be addressed by an ability.
	 *
	 * @param string $type Post type name.
	 * @return bool
	 */
	public static function post_type_allowed( $type ) {
		return is_string( $type ) && '' !== $type && in_array( $type, self::post_types(), true );
	}

	/**
	 * Whether a post object may be addressed by an ability.
	 *
	 * @param \WP_Post|null $post Post object.
	 * @return bool
	 */
	public static function post_allowed( $post ) {
		return $post instanceof \WP_Post && self::post_type_allowed( $post->post_type );
	}

	/**
	 * Resolve the status an unprivileged caller is allowed to query.
	 *
	 * Non-public statuses (draft, pending, private, trash, and the `any`
	 * wildcard) leak unpublished content, so they are only honoured for callers
	 * who can edit that post type. Everyone else is silently forced back to
	 * published content rather than being handed an error, which keeps the tool
	 * usable for low-privilege agents.
	 *
	 * @param string $status Requested post status.
	 * @param string $type   Post type the status applies to.
	 * @return string Status safe to query as the current user.
	 */
	public static function safe_status( $status, $type ) {
		$status = is_string( $status ) ? sanitize_key( $status ) : '';
		if ( '' === $status ) {
			return 'publish';
		}
		if ( in_array( $status, get_post_stati( [ 'public' => true ] ), true ) ) {
			return $status;
		}

		$type_obj = get_post_type_object( $type );
		if ( $type_obj && current_user_can( $type_obj->cap->edit_posts ) ) {
			return $status;
		}

		return 'publish';
	}
}
