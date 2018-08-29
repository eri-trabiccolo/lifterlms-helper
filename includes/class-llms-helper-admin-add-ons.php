<?php
defined( 'ABSPATH' ) || exit;

/**
 * Modify the admin add-ons page
 * @since    3.0.0
 * @version  3.0.2
 */
class LLMS_Helper_Admin_Add_Ons {

	/**
	 * Caches current state of the sites keys
	 * Use $this->has_keys() to retrieve the value
	 * @var  bool
	 */
	private $has_keys = null;

	/**
	 * Constructor
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function __construct() {

		add_action( 'admin_init', array( $this, 'handle_actions' ) );

		// output navigation items
		add_action( 'lifterlms_before_addons_nav', array( $this, 'output_navigation_items' ) );

		// output the license manager interface button / dropdown
		add_action( 'llms_addons_page_after_title', array( $this, 'output_license_manager' ) );

		// filter current section default
		add_filter( 'llms_admin_add_ons_get_current_section', array( $this, 'filter_get_current_section' ) );

		// filter the content display for a section
		add_filter( 'llms_admin_add_ons_get_current_section_default_content', array( $this, 'filter_get_current_section_content' ), 10, 2 );

		// add install & update actions to the list of available management actions powered by the bulk actions functions in core
		add_filter( 'llms_admin_add_ons_manage_actions', array( $this, 'filter_manage_actions' ) );

		// output html for helper-powered actions (install & update)
		add_action( 'llms_add_ons_single_item_actions', array( $this, 'output_single_install_action' ), 5, 2 );
		add_action( 'llms_add_ons_single_item_after_actions', array( $this, 'output_single_update_action' ), 5, 2 );

		add_filter( 'llms_admin_addon_features_exclude_ids', array( $this, 'filter_feature_exclude_ids' ) );

	}

	/**
	 * Change the default section from "All" to "Mine"
	 * But only if license keys have been saved
	 * @param    string     $section  section slug
	 * @return   string
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function filter_get_current_section( $section ) {

		if ( 'all' === $section && empty( $_GET['section'] ) && $this->has_keys() ) {
			return 'mine';
		}

		return $section;

	}

	/**
	 * Add "mine" tab content
	 * @param    array      $content  default items to display
	 * @param    string     $section  current tab slug
	 * @return   array
	 * @since    3.0.0
	 * @version  3.0.2
	 */
	public function filter_get_current_section_content( $content, $section ) {

		if ( 'mine' === $section ) {
			$mine = llms_helper_get_available_add_ons();
			$addons = llms_get_add_ons();
			if ( ! is_wp_error( $addons ) && isset( $addons['items'] ) ) {
				foreach ( $addons['items'] as $item ) {
					if ( in_array( $item['id'], $mine ) ) {
						$content[] = $item;
					}
				}
			}
		}

		return $content;

	}

	/**
	 * Exclude IDs for all add-ons that are currently available on the site
	 * @param    array     $ids  existing product ids to exclude
	 * @return   array
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function filter_feature_exclude_ids( $ids ) {
		return array_unique( array_merge( $ids, llms_helper_get_available_add_ons( false ) ) );
	}

	/**
	 * Add installatino & update actions to the list of available management actions
	 * @param    array     $actions  list of available actions, the action should correspond to a method in the LLMS_Helper_Add_On class
	 * @return   array
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function filter_manage_actions( $actions ) {
		return array_merge( array( 'install', 'update' ), $actions );
	}

	/**
	 * Handle form submission actions
	 * @return   void
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function handle_actions() {

		// license key addition & removal
		if ( llms_verify_nonce( '_llms_manage_keys_nonce', 'llms_manage_keys' ) ) {

			if ( isset( $_POST['llms_activate_keys'] ) && ! empty( $_POST['llms_add_keys'] ) ) {

				$this->handle_activations();

			} elseif ( isset( $_POST['llms_deactivate_keys'] ) && ! empty( $_POST['llms_remove_keys'] ) ) {

				$this->handle_deactivations();

			}

			delete_site_transient( 'update_plugins' );
			LLMS_Admin_Notices::output_notices();

		}

	}

	/**
	 * Activate license keys with LifterLMS.com api
	 * Output errors / successes & saves successful keys to the db
	 * @return   void
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	private function handle_activations() {

		$res = LLMS_Helper_Keys::activate_keys( $_POST['llms_add_keys'] );

		if ( is_wp_error( $res ) ) {
			LLMS_Admin_Notices::flash_notice( $res->get_error_message(), 'error' );
			return;
		}

		$data = $res['data'];
		if ( isset( $data['errors'] ) ) {
			foreach ( $data['errors'] as $error ) {
				LLMS_Admin_Notices::flash_notice( make_clickable( $error ), 'error' );
			}
		}

		if ( isset( $data['activations'] ) ) {
			foreach ( $data['activations'] as $activation ) {
				LLMS_Helper_Keys::add_license_key( $activation );
				LLMS_Admin_Notices::flash_notice( sprintf( __( '"%s" has been saved!', 'lifterlms-helper' ), $activation['license_key'] ), 'success' );
			}
		}

	}

	/**
	 * Deactivate license keys with LifterLMS.com api
	 * Output errors / successes & removes keys from the db
	 * @return   void
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	private function handle_deactivations() {

		$res = LLMS_Helper_Keys::deactivate_keys( $_POST['llms_remove_keys'] );

		if ( is_wp_error( $res ) ) {
			LLMS_Admin_Notices::flash_notice( $res->get_error_message(), 'error' );
			return;
		}

		foreach ( $_POST['llms_remove_keys'] as $key ) {
			LLMS_Helper_Keys::remove_license_key( $key );
			/* Translators: %s = License Key */
			LLMS_Admin_Notices::flash_notice( sprintf( __( 'License key "%s" was removed from this site.', 'lifterlms-helper' ), $key ), 'info' );
		}

		if ( isset( $data['errors'] ) ) {
			foreach ( $data['errors'] as $error ) {
				LLMS_Admin_Notices::flash_notice( make_clickable( $error ), 'error' );
			}
		}

	}

	/**
	 * Determine if the current site has active license keys
	 * @return   bool
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function has_keys() {

		if ( is_null( $this->has_keys ) ) {
			$this->has_keys = ( count( llms_helper_options()->get_license_keys() ) );
		}

		return $this->has_keys;

	}

	/**
	 * Output the HTML for the license manager area
	 * @return   void
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function output_license_manager() {

		$my_keys = llms_helper_options()->get_license_keys();
		if ( $my_keys ) {
			wp_enqueue_style( 'plugin-install' );
			wp_enqueue_script( 'plugin-install' );
			add_thickbox();
		}

		?>
 		<section class="llms-licenses">
			<button class="llms-button-primary" id="llms-active-keys-toggle">
				<?php _e( 'My License Keys', 'lifterlms-helper' ); ?>
				<i class="fa fa-chevron-down" aria-hidden="true"></i>
			</button>

			<form action="" class="llms-key-field" id="llms-key-field-form" method="POST">

				<?php if ( $my_keys ) : ?>
					<h4 class="llms-license-header"><?php _e( 'Manage Saved License Keys', 'lifterlms-helper' ); ?></h4>
					<ul class="llms-active-keys">
					<?php foreach ( $my_keys as $key ) : ?>
						<li>
							<label for="llms_key_<?php echo esc_attr( $key['license_key'] ); ?>">
								<input id="llms_key_<?php echo esc_attr( $key['license_key'] ); ?>" name="llms_remove_keys[]" type="checkbox" value="<?php echo esc_attr( $key['license_key'] ); ?>">
								<span><?php echo $key['license_key']; ?></span>
							</label>
						</li>

					<?php endforeach; ?>
					</ul>
					<button class="llms-button-danger small" name="llms_deactivate_keys" type="submit"><?php _e( 'Remove Selected', 'lifterlms-helper' ); ?></button>
				<?php endif; ?>

				<label for="llms_keys_field">
					<h4 class="llms-license-header"><?php _e( 'Add New License Keys', 'lifterlms-helper' ); ?></h4>
					<textarea name="llms_add_keys" id="llms_keys_field" placeholder="<?php esc_attr_e( 'Enter each license on a new line', 'lifterlms-helper' ); ?>"></textarea>
				</label>
				<button class="llms-button-primary small" name="llms_activate_keys" type="submit"><?php _e( 'Add New', 'lifterlms-helper' ); ?></button>
				<?php wp_nonce_field( 'llms_manage_keys', '_llms_manage_keys_nonce' ); ?>
			</form>
		</section>

		<?php
	}

	/**
	 * Output html for installation action
	 * Does not output for "featured" items on general settings
	 * @param    obj     $addon     LLMS_Add_On instance
	 * @param    string  $curr_tab  slug of the current tab being viewed
	 * @return   void
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function output_single_install_action( $addon, $curr_tab ) {

		if ( 'featured' === $curr_tab ) {
			return;
		}

		if ( $addon->is_installable() && ! $addon->is_installed() && $addon->is_licensed() ) {
			?>
			<label class="llms-status-icon status--<?php echo esc_attr( $addon->get_install_status() ); ?>" for="<?php echo esc_attr( sprintf( '%s-install', $addon->get( 'id' ) ) ); ?>">
				<input class="llms-bulk-check" data-action="install" name="llms_install[]" id="<?php echo esc_attr( sprintf( '%s-install', $addon->get( 'id' ) ) ); ?>" type="checkbox" value="<?php echo esc_attr( $addon->get( 'id' ) ); ?>">
				<i class="fa fa-check-square-o" aria-hidden="true"></i>
				<i class="fa fa-cloud-download" aria-hidden="true"></i>
				<span class="llms-status-text"><?php _e( 'Install', 'lifterlms-helper' ); ?></span>
			</label>
			<a href="<?php echo admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $addon->get( 'id' ) . '&section=changelog&TB_iframe=true&width=600&height=800' ); ?>" class="thickbox open-plugin-details-modal tip--bottom-left" data-tip="<?php esc_attr_e( 'View add-on details', 'lifterlms-helper' ); ?>">
				<i class="fa fa-info-circle" aria-hidden="true"></i>
			</span>
			<?php
		}

	}

	/**
	 * Output html for update action
	 * Does not output for "featured" items on general settings
	 * @param    obj     $addon     LLMS_Add_On instance
	 * @param    string  $curr_tab  slug of the current tab being viewed
	 * @return   void
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function output_single_update_action( $addon, $curr_tab ) {

		if ( 'featured' === $curr_tab ) {
			return;
		}

		if ( $addon->is_installable() && $addon->is_installed() && $addon->is_licensed() && $addon->has_available_update() ) {
			?>
			<label class="llms-status-icon status--update-available" for="<?php echo esc_attr( sprintf( '%s-update', $addon->get( 'id' ) ) ); ?>">
				<input class="llms-bulk-check" data-action="update" name="llms_update[]" id="<?php echo esc_attr( sprintf( '%s-update', $addon->get( 'id' ) ) ); ?>" type="checkbox" value="<?php echo esc_attr( $addon->get( 'id' ) ); ?>">
				<i class="fa fa-check-square-o" aria-hidden="true"></i>
				<i class="fa fa-arrow-circle-up" aria-hidden="true"></i>
				<span class="llms-status-text"><?php _e( 'Update', 'lifterlms-helper' ); ?>
			</label>
			<a href="<?php echo admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $addon->get( 'id' ) . '&section=changelog&TB_iframe=true&width=600&height=800' ); ?>" class="thickbox open-plugin-details-modal tip--bottom-left" data-tip="<?php esc_attr_e( 'View update details', 'lifterlms-helper' ); ?>">
				<i class="fa fa-info-circle" aria-hidden="true"></i>
			</span>
			<?php
		}

	}

	/**
	 * Output additional navigation items
	 * @param    string     $current_section  current section slug
	 * @return   void
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public function output_navigation_items( $current_section ) {

		if ( ! $this->has_keys() ) {
			return;
		}

		?>
		<li class="llms-nav-item<?php echo ( 'mine' === $current_section ) ? ' llms-active' : ''; ?>">
			<a class="llms-nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=llms-add-ons&section=mine' ) ); ?>"><?php _e( 'My Add-Ons', 'lifterlms-helper' ); ?></a>
		</li>
		<?php

	}

}

return new LLMS_Helper_Admin_Add_Ons();
