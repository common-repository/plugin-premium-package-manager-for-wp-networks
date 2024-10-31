<?php
/*
Plugin Name: Add Domain Mapping Menu
Plugin URI: http://wpmututorials.com/
Description: Adds the user domain mapping menu item under the Tools menu if Donncha's Domain Mapping plugin is installed
Version: 0.2.4
Author: Ron Rennick
Author URI: http://ronandandrea.com/
*/

function rapm_add_dm_user_menu_item( $setting ) {
	return ( is_network_admin() || !function_exists( 'dm_sunrise_warning' ) ? $setting : '1' );
}
add_filter( 'pre_site_option_dm_user_settings', 'rapm_add_dm_user_menu_item' );
