<?php
/**
 * Gravity Forms ability handlers (registered only when Gravity Forms is active).
 *
 * @package HLB\MCP
 */

namespace HLB\MCP\Handlers;

use GFAPI;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Form and entry reads, plus entry status, notes and deletion via GFAPI.
 */
class GravityForms {

	/**
	 * Field types that hold no submitted value.
	 *
	 * @var string[]
	 */
	const DISPLAY_TYPES = [ 'html', 'section', 'page', 'captcha' ];

	/**
	 * Entry statuses Gravity Forms supports.
	 *
	 * @var string[]
	 */
	const STATUSES = [ 'active', 'spam', 'trash' ];

	/**
	 * Whether Gravity Forms is loaded.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'GFAPI' ) && class_exists( 'GFCommon' );
	}

	/**
	 * Whether Gravity Forms is active on the current blog.
	 *
	 * In network mode the classes stay loaded after switch_to_blog(), even on a
	 * subsite where the plugin (and its tables) are absent.
	 *
	 * @return bool
	 */
	public static function active_here() {
		if ( ! self::is_active() ) {
			return false;
		}
		if ( ! is_multisite() || ! defined( 'GF_PLUGIN_BASENAME' ) ) {
			return true;
		}
		$network = (array) get_site_option( 'active_sitewide_plugins', [] );
		if ( isset( $network[ GF_PLUGIN_BASENAME ] ) ) {
			return true;
		}
		return in_array( GF_PLUGIN_BASENAME, (array) get_option( 'active_plugins', [] ), true );
	}

	/**
	 * List forms with entry counts.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public static function list_forms( array $input ) {
		$error = self::unavailable();
		if ( $error ) {
			return $error;
		}

		$active = null;
		if ( isset( $input['active'] ) && 'any' !== $input['active'] ) {
			$active = 'inactive' !== $input['active'];
		}

		$items = [];
		foreach ( GFAPI::get_forms( $active, false, 'title' ) as $form ) {
			$items[] = [
				'id'           => (int) $form['id'],
				'title'        => $form['title'],
				'is_active'    => (bool) $form['is_active'],
				'date_created' => isset( $form['date_created'] ) ? $form['date_created'] : null,
				'entry_count'  => (int) GFAPI::count_entries( $form['id'], [ 'status' => 'active' ] ),
			];
		}

		return [
			'items' => $items,
			'total' => count( $items ),
		];
	}

	/**
	 * Get a form's structure (fields only; no notifications, confirmations or feeds).
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public static function get_form( array $input ) {
		$error = self::unavailable();
		if ( $error ) {
			return $error;
		}

		$form = self::find_form( isset( $input['id'] ) ? $input['id'] : 0 );
		if ( ! $form ) {
			return self::not_found( __( 'Form not found.', 'hlb-ability-registry-mcp' ) );
		}

		$fields = [];
		foreach ( $form['fields'] as $field ) {
			$fields[] = self::shape_field( $field );
		}

		return [
			'id'           => (int) $form['id'],
			'title'        => $form['title'],
			'description'  => isset( $form['description'] ) ? $form['description'] : '',
			'is_active'    => (bool) $form['is_active'],
			'date_created' => isset( $form['date_created'] ) ? $form['date_created'] : null,
			'entry_count'  => (int) GFAPI::count_entries( $form['id'], [ 'status' => 'active' ] ),
			'fields'       => $fields,
		];
	}

	/**
	 * List entries, optionally limited to one form.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public static function list_entries( array $input ) {
		$error = self::unavailable();
		if ( $error ) {
			return $error;
		}

		$per_page = isset( $input['per_page'] ) ? max( 1, min( 100, (int) $input['per_page'] ) ) : 20;
		$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$form_id  = isset( $input['form_id'] ) ? (int) $input['form_id'] : 0;

		if ( $form_id && ! self::find_form( $form_id ) ) {
			return self::not_found( __( 'Form not found.', 'hlb-ability-registry-mcp' ) );
		}

		$status   = isset( $input['status'] ) && in_array( $input['status'], self::STATUSES, true ) ? $input['status'] : 'active';
		$criteria = [ 'status' => $status ];
		if ( ! empty( $input['search'] ) ) {
			$criteria['field_filters'] = [
				[ 'value' => sanitize_text_field( $input['search'] ) ],
			];
		}
		foreach ( [ 'start_date', 'end_date' ] as $key ) {
			if ( ! empty( $input[ $key ] ) ) {
				$criteria[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		$total   = 0;
		$entries = GFAPI::get_entries(
			$form_id,
			$criteria,
			[
				'key'        => 'id',
				'direction'  => 'DESC',
				'is_numeric' => true,
			],
			[
				'offset'    => ( $page - 1 ) * $per_page,
				'page_size' => $per_page,
			],
			$total
		);
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}

		$forms = [];
		$items = [];
		foreach ( $entries as $entry ) {
			$fid = (int) $entry['form_id'];
			if ( ! array_key_exists( $fid, $forms ) ) {
				$forms[ $fid ] = self::find_form( $fid );
			}
			$items[] = self::shape_entry( $entry, $forms[ $fid ] );
		}

		return [
			'items'       => $items,
			'total'       => (int) $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	/**
	 * Get a single entry.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public static function get_entry( array $input ) {
		$error = self::unavailable();
		if ( $error ) {
			return $error;
		}

		$entry = self::find_entry( isset( $input['id'] ) ? $input['id'] : 0 );
		if ( ! $entry ) {
			return self::not_found( __( 'Entry not found.', 'hlb-ability-registry-mcp' ) );
		}

		return self::shape_entry( $entry, self::find_form( $entry['form_id'] ) );
	}

	/**
	 * Update an entry's status, read or starred flag.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public static function update_entry_status( array $input ) {
		$error = self::unavailable();
		if ( $error ) {
			return $error;
		}

		$entry = self::find_entry( isset( $input['id'] ) ? $input['id'] : 0 );
		if ( ! $entry ) {
			return self::not_found( __( 'Entry not found.', 'hlb-ability-registry-mcp' ) );
		}

		$changes = [];
		if ( isset( $input['status'] ) ) {
			if ( ! in_array( $input['status'], self::STATUSES, true ) ) {
				return new WP_Error( 'hlb_mcp_invalid_status', __( 'Invalid entry status.', 'hlb-ability-registry-mcp' ), [ 'status' => 400 ] );
			}
			$changes['status'] = $input['status'];
		}
		foreach ( [ 'is_read', 'is_starred' ] as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				$changes[ $flag ] = $input[ $flag ] ? 1 : 0;
			}
		}
		if ( empty( $changes ) ) {
			return new WP_Error( 'hlb_mcp_no_changes', __( 'Nothing to update.', 'hlb-ability-registry-mcp' ), [ 'status' => 400 ] );
		}

		foreach ( $changes as $property => $value ) {
			if ( false === GFAPI::update_entry_property( $entry['id'], $property, $value ) ) {
				return new WP_Error( 'hlb_mcp_update_failed', __( 'Could not update the entry.', 'hlb-ability-registry-mcp' ), [ 'status' => 500 ] );
			}
		}

		$entry = GFAPI::get_entry( $entry['id'] );
		return self::shape_entry( $entry, self::find_form( $entry['form_id'] ) );
	}

	/**
	 * Add a note to an entry, authored by the current user.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public static function add_entry_note( array $input ) {
		$error = self::unavailable();
		if ( $error ) {
			return $error;
		}

		$entry = self::find_entry( isset( $input['id'] ) ? $input['id'] : 0 );
		if ( ! $entry ) {
			return self::not_found( __( 'Entry not found.', 'hlb-ability-registry-mcp' ) );
		}

		$note = isset( $input['note'] ) ? trim( sanitize_textarea_field( $input['note'] ) ) : '';
		if ( '' === $note ) {
			return new WP_Error( 'hlb_mcp_invalid_note', __( 'Note text is required.', 'hlb-ability-registry-mcp' ), [ 'status' => 400 ] );
		}

		$user    = wp_get_current_user();
		$note_id = GFAPI::add_note( $entry['id'], $user->ID, $user->display_name, $note );
		if ( is_wp_error( $note_id ) ) {
			return $note_id;
		}

		return [
			'entry_id' => (int) $entry['id'],
			'note_id'  => (int) $note_id,
			'note'     => $note,
		];
	}

	/**
	 * Permanently delete an entry.
	 *
	 * @param array $input Ability input.
	 * @return array|WP_Error
	 */
	public static function delete_entry( array $input ) {
		$error = self::unavailable();
		if ( $error ) {
			return $error;
		}

		$entry = self::find_entry( isset( $input['id'] ) ? $input['id'] : 0 );
		if ( ! $entry ) {
			return self::not_found( __( 'Entry not found.', 'hlb-ability-registry-mcp' ) );
		}

		$result = GFAPI::delete_entry( $entry['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'id'      => (int) $entry['id'],
			'deleted' => true,
		];
	}

	/**
	 * Error to return when Gravity Forms is not usable on the current blog.
	 *
	 * @return WP_Error|null
	 */
	private static function unavailable() {
		if ( self::active_here() ) {
			return null;
		}
		return new WP_Error( 'hlb_mcp_unavailable', __( 'Gravity Forms is not active.', 'hlb-ability-registry-mcp' ), [ 'status' => 501 ] );
	}

	/**
	 * Not-found error.
	 *
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private static function not_found( $message ) {
		return new WP_Error( 'hlb_mcp_not_found', $message, [ 'status' => 404 ] );
	}

	/**
	 * A non-trashed form by id, or null.
	 *
	 * @param int $id Form id.
	 * @return array|null
	 */
	private static function find_form( $id ) {
		$id   = (int) $id;
		$form = $id ? GFAPI::get_form( $id ) : false;
		if ( ! $form || ! empty( $form['is_trash'] ) ) {
			return null;
		}
		return $form;
	}

	/**
	 * An entry by id whose form still exists, or null.
	 *
	 * @param int $id Entry id.
	 * @return array|null
	 */
	private static function find_entry( $id ) {
		$id    = (int) $id;
		$entry = $id ? GFAPI::get_entry( $id ) : false;
		if ( ! $entry || is_wp_error( $entry ) || ! self::find_form( $entry['form_id'] ) ) {
			return null;
		}
		return $entry;
	}

	/**
	 * Shape a form field into a plain array.
	 *
	 * @param \GF_Field $field Field.
	 * @return array
	 */
	private static function shape_field( $field ) {
		$data = [
			'id'          => (int) $field->id,
			'type'        => $field->type,
			'label'       => $field->label,
			'description' => (string) $field->description,
			'required'    => (bool) $field->isRequired, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- GF property.
		];

		if ( is_array( $field->choices ) ) {
			$data['choices'] = array_map(
				static function ( $choice ) {
					return [
						'text'  => isset( $choice['text'] ) ? $choice['text'] : '',
						'value' => isset( $choice['value'] ) ? $choice['value'] : '',
					];
				},
				$field->choices
			);
		}

		if ( is_array( $field->inputs ) ) {
			$data['inputs'] = [];
			foreach ( $field->inputs as $sub ) {
				if ( empty( $sub['isHidden'] ) ) {
					$data['inputs'][] = [
						'id'    => (string) $sub['id'],
						'label' => isset( $sub['label'] ) ? $sub['label'] : '',
					];
				}
			}
		}

		return $data;
	}

	/**
	 * Shape an entry into a plain array, values keyed by field.
	 *
	 * @param array      $entry Entry.
	 * @param array|null $form  The entry's form.
	 * @return array
	 */
	private static function shape_entry( array $entry, $form ) {
		$fields = [];
		if ( $form ) {
			foreach ( $form['fields'] as $field ) {
				$display_only = $field->displayOnly; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- GF property.
				if ( $display_only || in_array( $field->type, self::DISPLAY_TYPES, true ) ) {
					continue;
				}
				$fields[] = [
					'id'    => (int) $field->id,
					'label' => $field->label,
					'type'  => $field->type,
					'value' => $field->get_value_export( $entry, '', true ),
				];
			}
		}

		return [
			'id'           => (int) $entry['id'],
			'form_id'      => (int) $entry['form_id'],
			'status'       => $entry['status'],
			'is_read'      => (bool) $entry['is_read'],
			'is_starred'   => (bool) $entry['is_starred'],
			'date_created' => $entry['date_created'],
			'source_url'   => $entry['source_url'],
			'created_by'   => $entry['created_by'] ? (int) $entry['created_by'] : null,
			'fields'       => $fields,
		];
	}
}
