<?php
/*
Plugin Name: Super Admin Menu Manager
Plugin URI: http://wpmututorials.com/
Description: Manage menu visibility in the site dashboard
Version: 0.2.4
Author: Ron Rennick
Author URI: http://ronandandrea.com/
Network: true
*/

function rapm_menu_manager_admin_menu() {
	if( !is_super_admin() ) {
		$hidden = rapm_menu_manager_get_hidden();
		foreach( $hidden as $menu => $items ) {
			if( !empty( $items[0] ) )
				remove_menu_page( $menu );
			else {
				foreach( array_keys( $items ) as $sub )
					remove_submenu_page( $menu, $sub );
			}
		}
	} else
		add_management_page( __( 'Manage Menus', 'premium-manager' ), __( 'Manage Menus', 'premium-manager' ), 'manage_network_options', 'ra-menu-manager', 'rapm_menu_manager_page' );
}

add_action( 'admin_menu', 'rapm_menu_manager_admin_menu', 1000 );

function rapm_menu_manager_css() {
	if( $_REQUEST['page'] == 'ra-menu-manager' || basename( $_SERVER['SCRIPT_NAME'] ) == 'post.php' )
		wp_enqueue_style( 'ra-menu-manager', plugin_dir_url( __FILE__ ) . 'css/menu-manager.css' );
}
add_action( 'admin_init', 'rapm_menu_manager_css' );

function rapm_menu_manager_page() {
	global $menu, $submenu;
	echo "<div id='ra-menu-manager' class='wrap'>";
	if( !empty( $_POST['menu_manager'] ) ) {
		rapm_menu_manager_update( stripslashes_deep( $_POST['menu_manager'] ) );
		echo '<div class="updated">' . __( 'Menus Updated', 'premium-manager' ) . '</div>';
	} elseif( !empty( $_POST ) ) {
		rapm_menu_manager_update( array() );
		echo '<div class="updated">' . __( 'Menus Cleared', 'premium-manager' ) . '</div>';
	}
	$hidden = rapm_menu_manager_get_hidden();
	
	echo '<h2>' . __( 'Manage Menus', 'premium-manager' ) . "</h2>\n";
	echo '<em>' . __( 'Checked Menus are not shown to Site Admins', 'premium-manager' ) . "</em><form method='post'>\n";
	wp_nonce_field( 'ra_menu_manager' );	
	echo "<p><input type='submit' class='button-secondary' value='" . __( 'Update', 'premium-manager' ) . "' />";
	echo '&nbsp;<label><input type="checkbox" name="menu-reset" value="1" />&nbsp;<strong>' . sprintf( __( 'Reset Default Menus for %s', 'premium-manager' ), is_main_site() ? 'Network' : 'Site' ) . "</strong></label>\n";
	if( !is_main_site() ) 
		echo '&nbsp;<label><input type="checkbox" name="menu-exclude" value="1" ' . checked( true, empty( $hidden ), false ) . ' />&nbsp;<strong>' . __( 'Show all menus on this site', 'premium-manager' ) . "</strong></label>\n";
	echo "</p><ul>\n";
	foreach( $menu as $m ) {
		if( empty( $m[0] ) )
			continue;
		printf( '<li><label><input type="checkbox" name="menu_manager[%1$s][0]" id="menu_manager-%1$s" value="1" %2$s/>&nbsp;%3$s</label>', $m[2], checked( $hidden[$m[2]][0], 1, false ), $m[0] );
		if( !empty( $submenu[$m[2]] ) ) {
			echo "<ul>\n";
			foreach( $submenu[$m[2]] as $s )
				printf( '<li><label><input type="checkbox" name="menu_manager[%1$s][%2$s]" id="menu_manager-%1$s-%2$s" value="1" %3$s/>&nbsp;%4$s</label></li>', $m[2], $s[2], checked( $hidden[$m[2]][$s[2]], 1, false ), $s[0] );
			echo "</ul>\n";
		} 
		echo "<div style='clear: left;'>&nbsp;</div></li>\n";
	}
	echo "</ul></form></div>\n";
}
function rapm_menu_manager_update( $hidden ) {
	check_admin_referer( 'ra_menu_manager' );
	if( !empty( $_POST['menu-reset'] ) && '1' == $_POST['menu-reset'] )
		$hidden = array();
		
	if( !is_multisite() || is_main_site() )
		update_site_option( 'ra_hidden_menus', $hidden );
	else {
		if( !empty( $_POST['menu-exclude'] ) && '1' == $_POST['menu-exclude'] )
			update_option( 'ra_hidden_menus', array() );
		elseif( empty( $hidden ) )
			delete_option( 'ra_hidden_menus' );
		else
			update_option( 'ra_hidden_menus', $hidden );
	}
}
function rapm_menu_manager_get_hidden() {
	if( !is_multisite() || is_main_site() || ( $hidden = get_option( 'ra_hidden_menus' ) ) === false )
		$hidden = get_site_option( 'ra_hidden_menus', array() );
		
	return $hidden;
}