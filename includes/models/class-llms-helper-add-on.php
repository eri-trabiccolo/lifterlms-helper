<?php
/**
 * Extends core class to allow interaction with the .com api
 *
 * @package LifterLMS_Helper/Models
 *
 * @since 3.0.0
 * @version 3.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Helper_Add_On
 *
 * @since 3.0.0
 * @since 3.2.0 Moved from `includes/model-llms-helper-add-on.php`.
 */
class LLMS_Helper_Add_On extends LLMS_Add_On {

	/**
	 * Find a license key for the add-on
	 *
	 * @since 3.0.0
	 * @since 3.2.0 Use strict comparison for `in_array()`.
	 *
	 * @return string|false
	 */
	public function find_license() {

		/**
		 * If the addon doesn't require a license return the first found license
		 * this will ensure that the core can be updated via a license when subscribed to a beta channel
		 * and that the helper can always be upgraded.
		 */
		$requires_license = llms_parse_bool( $this->get( 'has_license' ) );

		$id = $this->get( 'id' );
		foreach ( llms_helper_options()->get_license_keys() as $data ) {

			if ( ! $requires_license ) {
				return $data;
			}

			if ( $id === $data['product_id'] || in_array( $id, $data['addons'], true ) ) {
				return $data;
			}
		}

		return false;

	}

	/**
	 * Retrieve the update channel for the addon
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_channel_subscription() {
		$channels = llms_helper_options()->get_channels();
		return isset( $channels[ $this->get( 'id' ) ] ) ? $channels[ $this->get( 'id' ) ] : 'stable';
	}

	/**
	 * Retrieve download information for a licensed add-on
	 *
	 * @since 3.0.0
	 *
	 * @return WP_Error|array
	 */
	public function get_download_info() {

		$key = $this->find_license();

		if ( ! $key ) {
			return new WP_Error( 'no_license', __( 'Unable to locate a license key for the selected add-on.', 'lifterlms-helper' ) );
		}

		$req  = new LLMS_Dot_Com_API(
			'/license/download',
			array(
				'url'         => get_site_url(),
				'license_key' => $key['license_key'],
				'update_key'  => $key['update_key'],
				'add_on_slug' => $this->get( 'slug' ),
				'channel'     => $this->get_channel_subscription(),
			)
		);
		$data = $req->get_result();

		if ( $req->is_error() ) {
			return $data;
		}

		return $data;

	}

	/**
	 * Translate strings
	 *
	 * @since 3.0.0
	 *
	 * @param string $string Untranslated string / key.
	 * @return string
	 */
	public function get_l10n( $string ) {

		$strings = array(

			'active'           => __( 'Active', 'lifterlms-helper' ),
			'inactive'         => __( 'Inactive', 'lifterlms-helper' ),

			'installed'        => __( 'Installed', 'lifterlms-helper' ),
			'uninstalled'      => __( 'Not Installed', 'lifterlms-helper' ),

			'activate'         => __( 'Activate', 'lifterlms-helper' ),
			'deactivate'       => __( 'Deactivate', 'lifterlms-helper' ),
			'install'          => __( 'Install', 'lifterlms-helper' ),

			'none'             => __( 'N/A', 'lifterlms-helper' ),

			'license_active'   => __( 'Licensed', 'lifterlms-helper' ),
			'license_inactive' => __( 'Unlicensed', 'lifterlms-helper' ),

		);

		return $strings[ $string ];

	}

	/**
	 * Determine the status of an addon's license
	 *
	 * @since 3.0.0
	 *
	 * @param bool $translate If true, returns the translated string for on-screen display.
	 * @return string
	 */
	public function get_license_status( $translate = false ) {

		if ( ! llms_parse_bool( $this->get( 'has_license' ) ) ) {
			$ret = 'none';
		} else {
			$ret = $this->is_licensed() ? 'license_active' : 'license_inactive';
		}

		return $translate ? $this->get_l10n( $ret ) : $ret;

	}

	/**
	 * Install the add-on via LifterLMS.com
	 *
	 * @since 3.0.0
	 *
	 * @return string|WP_Error
	 */
	public function install() {

		$ret = LLMS_Helper()->upgrader()->install_addon( $this );

		if ( true === $ret ) {

			/* Translators: %s = Add-on name */
			return sprintf( __( '%s was successfully installed.', 'lifterlms-helper' ), $this->get( 'title' ) );

		} elseif ( is_wp_error( $ret ) ) {

			return $ret;

		}

		/* Translators: %s = Add-on name */
		return new WP_Error( 'activation', sprintf( __( 'Could not install %s.', 'lifterlms-helper' ), $this->get( 'title' ) ) );

	}

	/**
	 * Determines if the add-on is licensed
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public function is_licensed() {
		return ( false !== $this->find_license() );
	}

	/**
	 * Update the addons update channel subscription
	 *
	 * @since 3.0.0
	 *
	 * @param string $channel Channel name [stable|beta].
	 * @return boolean
	 */
	public function subscribe_to_channel( $channel = 'stable' ) {

		$channels                       = llms_helper_options()->get_channels();
		$channels[ $this->get( 'id' ) ] = $channel;
		return llms_helper_options()->set_channels( $channels );

	}

	/**
	 * Install the add-on via LifterLMS.com
	 *
	 * @return string|WP_Error
	 * @since 3.0.0
	 */
	public function update() {

		$ret = LLMS_Helper()->upgrader()->install_addon( $this, 'update' );

		if ( true === $ret ) {

			/* Translators: %s = Add-on name */
			return sprintf( __( '%s was successfully updated.', 'lifterlms-helper' ), $this->get( 'title' ) );

		} elseif ( is_wp_error( $ret ) ) {

			return $ret;

		}

		/* Translators: %s = Add-on name */
		return new WP_Error( 'activation', sprintf( __( 'Could not update %s.', 'lifterlms-helper' ), $this->get( 'title' ) ) );

	}

}
