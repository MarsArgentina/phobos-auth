<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

define( 'um_path', '' );
define( 'ultimatemember_version', '' );

class User {
	/**
	 * This method lets you auto sign-in a user to your site.
	 *
	 * @param int $id
	 * @param int|bool $rememberme
	 * @return void
	 */
	public function auto_login ($id, $rememberme = 0) {}
}

class Dependencies {
	/**
	 * True if UltimateMember is active.
	 *
	 * @return bool
	 */
	public function ultimatemember_active_check() {}
}

class Permalinks {
	/**
	 * Gets the current URL
	 * @return string
	 */
	public function get_current_url ($no_query_params = false) {}
}

class UltimateMember {
	/**
	 * @return User
	 */
	public function user () {}

	/**
	 * @return Dependencies
	 */
	public function dependencies () {}

	/**
	 * @return Permalinks
	 */
	public function permalinks() {}
}

/**
 * Get a field of the current UltimateMember user
 *
 * @param string $field
 * @return mixed
 */
function um_user ($field) {}

/**
 * Fetch an user by its ID and set it as the current UltimateMember user.
 *
 * @param int $id
 * @return void
 */
function um_fetch_user($id) {}

/**
 * Get the URL to the profile of the current UltimateMember user.
 *
 * @return string
 */
function um_user_profile_url() {}

/**
 * Get core page url
 *
 * @param $slug
 * @param bool $updated
 *
 * @return bool|false|mixed|string|void
 */
function um_get_core_page($name, $updated = false) {}

/**
 * Get the current instance of UltimateMember
 *
 * @return UltimateMember
 */
function UM () {}
