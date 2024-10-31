<?php
/*
This class handles updating the subsite as packages are purchased, renewed or packages expire 
*/

add_action( 'admin_init', array( 'RA_Premium_Cron', 'admin_init' ) );
add_action( 'admin_notices', array( 'RA_Premium_Cron', 'admin_notices' ) );
add_action( 'ra_package_cron', array( 'RA_Premium_Cron', 'do_cron' ) );

class RA_Premium_Cron {
	function admin_init() {
		if( is_main_site() || !current_user_can( 'manage_options' ) )
			return;

		if( !wp_next_scheduled( 'ra_package_cron' ) )
			wp_schedule_event( time() + 60, 'twicedaily', 'ra_package_cron' );
			
		if( is_admin_bar_showing() )
			add_action( 'admin_bar_menu', array( 'RA_Premium_Cron', 'admin_bar_menu' ), 99 );
	}
	function do_cron() {
		$packages = get_option( 'ra_packages' );
		if( !empty( $packages ) && is_array( $packages ) ) {
			foreach( $packages as $key => $pkg ) {
				$action = $pkg['status'];
				if( !empty( $action ) && is_callable( array( 'RA_Premium_Cron', $action ) ) )
					$packages[$key] = RA_Premium_Cron::$action( $pkg, $key );
			}
			update_option( 	'ra_packages', $packages );		
		}
	}
	function active( $pkg, $id ) {
		if( !empty( $pkg['expires'] ) && ( time() - $pkg['expires'] ) > 86400 ) {
			$pkg['status'] = 'expired';
			if( !empty( $pkg['space'] ) ) 
				delete_option( 'blog_upload_space' );
			if( !empty( $pkg['menus'] ) ) 
				delete_option( 'ra_hidden_menus' );
				
			if( !empty( $pkg['themes'] ) && is_array( $pkg['themes'] ) ) {
				$allowed = get_option( 'allowedthemes', array() );
				foreach( $pkg['themes'] as $theme ) {
					if( !empty( $allowed[$theme] ) && $allowed[$theme] )
						unset( $allowed[$theme] );
				}
				update_option( 'allowedthemes', $allowed );
			}
			
			if( !empty( $pkg['plugins'] ) && is_array( $pkg['plugins'] ) ) {
				$active = get_option( 'active_plugins', array() );
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				$deactivate = array();
				foreach( $active as $plugin ) {
					if( in_array( $plugin, $pkg['plugins'] ) )
						$deactivate[] = $plugin;
				}
				if( !empty( $deactivate ) )
					deactivate_plugins( $deactivate );
			}
			update_option( 'ra_package_notice', $id );
		}
		return $pkg;
	}
	function pending( $pkg ) {
		$pkg['status'] = 'active';
		$pkg['expires'] = time() + $pkg['extend'];
		if( !empty( $pkg['space'] ) ) {
			$amount = (int) $pkg['space']['amount'];
			if( 'GB' == $pkg['space']['unit'] )
				$amount *= 1024;
			update_option( 'blog_upload_space', $amount );
		}
		if( !empty( $pkg['menus'] ) ) 
			update_option( 'ra_hidden_menus', $pkg['menus'] );
			
		if( !empty( $pkg['themes'] ) && is_array( $pkg['themes'] ) ) {
			$allowed = get_option( 'allowedthemes', array() );
			foreach( $pkg['themes'] as $theme )
				$allowed[$theme] = true;
			update_option( 'allowedthemes', $allowed );
		}

		if( !empty( $pkg['plugins'] ) && is_array( $pkg['plugins'] ) ) {
			$active = get_option( 'active_plugins', array() );
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			foreach( $pkg['plugins'] as $plugin ) {
				if( !in_array( $plugin, $active ) )
					activate_plugin( $plugin, '' );
			}
		}
		return $pkg;
	}
	function update( $pkg ) {
		$expired = ( !empty( $pkg['expires'] ) && ( time() - $pkg['expires'] ) > 86400 );
		
		if( !is_array( $pkg['update'] ) )
			$pkg['status'] = ( $expired ? 'expired' : 'active' );
		elseif( $expired )
			$pkg = RA_Premium_Cron::pending( $pkg['update'] );
		else {
			$update = $pkg['update'];
			$update['status'] = 'active';
			$update['expires'] = $pkg['expires'] + $pkg['extend'];
			
			if( !empty( $update['space'] ) ) {
				$amount = (int) $update['space']['amount'];
				if( 'GB' == $update['space']['unit'] )
					$amount *= 1024;
				update_option( 'blog_upload_space', $amount );
			}
			elseif( !empty( $pkg['space'] ) )
				delete_option( 'blog_upload_space' );
				
			if( !empty( $update['menus'] ) ) 
				update_option( 'ra_hidden_menus', $update['menus'] );
			elseif( !empty( $pkg['menus'] ) ) 
				delete_option( 'ra_hidden_menus' );
				
			$allowed = get_option( 'allowedthemes', array() );
			$newthemes = (array) $update['themes'];
			$oldthemes = (array) $pkg['themes'];
			
			foreach( array_keys( $allowed ) as $theme ) {
				if( !in_array( $theme, $newthemes ) && in_array( $theme, $oldthemes ) )
					unset( $allowed[$theme] );				
			}
			foreach( $newthemes as $theme )
				$allowed[$theme] = true;
				
			update_option( 'allowedthemes', $allowed );
			
			$active = get_option( 'active_plugins', array() );
			$newplugins = (array) $update['plugins'];
			$oldplugins = (array) $pkg['plugins'];
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			$deactivate = array();
			foreach( $active as $plugin ) {
				if( !in_array( $plugin, $newplugins ) && in_array( $plugin, $oldplugins ) )
					$deactivate[] = $plugin;
			}
			if( !empty( $deactivate ) )
				deactivate_plugins( $deactivate );
			foreach( $newplugins as $plugin ) {
				if( !in_array( $plugin, $active ) )
					activate_plugin( $plugin, '' );
			}
			
			$pkg = $update;
		}
		if( $pkg['status'] == 'active' )
			update_option( 'ra_package_notice', '0' );

		return $pkg;
	}
	function dismiss( $id ) {
		if( !$id || !wp_verify_nonce( $_GET['_wpnonce'], 'package-dismiss-' . $id ) )
			return;
			
		$notice = get_option( 'ra_package_notice', 'me' );
		if( $id == $notice )
			update_option( 'ra_package_notice', '0' );
	}
	function admin_notices() {
		if( !current_user_can( 'manage_options' ) )
			return;

		$notice = get_option( 'ra_package_notice' );
		$account = admin_url( 'index.php?page=my_account_page' );
		
		if( false === $notice ) {			
			$dismiss = wp_nonce_url( add_query_arg( array( 'dismiss' => 'me' ), $account ), 'package-dismiss-me' ); ?>
	<div id="message" class="updated fade">
		<p><?php printf( __( 'Check out our <a href="%s">Upgrades</a> for more features and enhancements. <a href="%s">Dismiss</a>', 'premium-manager' ), $account, $dismiss ) ?></p>
	</div>
<?php			return;
		}
		if( !$notice ) 
			return;
			
		$packages = get_site_option( 'ra_premium_packages', array() );
		if( empty( $packages ) )
			return;
		
		$title = '';
		foreach( $packages as $side ) {
			foreach( $side as $pkg ) {
				if( $pkg['id'] == $notice ) {
					$title = $pkg['title'];
					break;
				}
			}
			if( $title )
				break;
		}
		if( $title ) {
			$dismiss = wp_nonce_url( add_query_arg( array( 'dismiss' => $notice ), $account ), 'package-dismiss-' . $notice ); ?>
	<div id="message" class="updated fade">
		<p><?php printf( __( 'Your <a href="%s">%s</a> package has expired. <a href="%s">Dismiss</a>', 'premium-manager' ), $account, esc_html( $title ), $dismiss ) ?></p>
	</div>
<?php		}
	}
	function admin_bar_menu() {
		global $wp_admin_bar;
		
		$packages = get_option( 'ra_packages' );
		$active = false;
		if( !empty( $packages ) && is_array( $packages ) ) {
			foreach( $packages as $key => $pkg ) {
				$action = $pkg['status'];
				if( !empty( $pkg['status'] ) && 'active' == $pkg['status'] ) {
					$active = true;
					break;
				}
			}
		}
		$account = admin_url( 'index.php?page=my_account_page' );
		if( $active )
			$wp_admin_bar->add_menu( array( 'id' => 'ra-premium', 'title' => __( 'My Account', 'premium-manager' ), 'href' => $account ) );
		else
			$wp_admin_bar->add_menu( array( 'id' => 'ra-premium', 'title' => __( 'Upgrade Account', 'premium-manager' ), 'href' => $account ) );
	}
}