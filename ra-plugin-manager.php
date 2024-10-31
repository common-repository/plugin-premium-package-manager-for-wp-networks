<?php
/*
Plugin Name: Super Admin Plugin Manager
Plugin URI: http://wpmututorials.com/
Description: Adds links to the Network Plugins page to control plugin visibility on the site plugins page
Version: 0.2.4
Author: Ron Rennick
Author URI: http://ronandandrea.com/
Network: true

*/

function rapm_all_plugins_filter( $plugins ) {
	if( ( defined('DOING_CRON') && DOING_CRON ) || current_user_can( 'manage_network_plugins' ) ) 
		return $plugins;

	$visible = get_site_option( 'ra_visible_plugins', array() );
	$allowed_plugins = array();
	if( !empty( $visible ) ) {
		foreach( $visible as $plugin ) {
			if( !empty( $plugins[$plugin] ) )
				$allowed_plugins[$plugin] = $plugins[$plugin];
		}
	}
	return $allowed_plugins;
}
add_filter( 'all_plugins', 'rapm_all_plugins_filter' );

function rapm_network_admin_plugin_actions( $actions, $plugin_file, $plugin_data, $context ) {
	global $page, $s;
	static $visible = false;

	if( $context == 'all' && !$plugin_data['Network'] && !is_plugin_active_for_network( $plugin_file ) ) {
		if( $visible === false )
			$visible = get_site_option( 'ra_visible_plugins', array() );
		if( in_array( $plugin_file, $visible ) )
			$actions['ra_hide_plugin'] = '<a href="' . wp_nonce_url( rapm_network_admin_plugin_action_url( 'hide', $plugin_file, $context, $page, $s ), 'ra_hide-plugin_' . $plugin_file ) . '" title="' . __( 'Hide this plugin on the site plugin screens', 'premium-manager' ) . '">' . __( 'Make invisible', 'premium-manager' ) . '</a>';
		else
			$actions['ra_show_plugin'] = '<a href="' . wp_nonce_url( rapm_network_admin_plugin_action_url( 'show', $plugin_file, $context, $page, $s ), 'ra_show-plugin_' . $plugin_file ) . '" title="' . __( 'Show this plugin on the site plugin screens', 'premium-manager' ) . '">' . __( 'Make visible', 'premium-manager' ) . '</a>';
	}
	return $actions;
}
add_filter( 'network_admin_plugin_action_links', 'rapm_network_admin_plugin_actions', 10, 6 );

function rapm_handle_network_admin_plugin_actions() {
	global $parent_file;
	if( 'plugins.php' == $parent_file && WP_NETWORK_ADMIN && current_user_can( 'manage_network_plugins' ) ) {
		$action = $_GET['action'];
		$plugin = $_GET['plugin'];
		$nonce = $_GET['_wpnonce'];
		$visible = get_site_option( 'ra_visible_plugins', array() );

		if( $action == 'hide_plugin' && in_array( $plugin, $visible ) ) {
			if( !wp_verify_nonce( $nonce, 'ra_hide-plugin_' . $plugin ) )
				return;

			foreach( $visible as $k => $v ) {
				if( $plugin == $v )
					unset( $visible[$k] );
			}
		} elseif( $action == 'show_plugin' && !in_array( $plugin, $visible ) ) {
			if( !wp_verify_nonce( $nonce, 'ra_show-plugin_' . $plugin ) )
				return;
			
			$visible[] = stripslashes( $plugin );
		} else
			return;

		update_site_option( 'ra_visible_plugins', $visible );
		wp_redirect( self_admin_url("plugins.php?plugin_status=$status&paged=$page&s=$s") );
	}
}
add_action( 'admin_init', 'rapm_handle_network_admin_plugin_actions' );

function rapm_network_admin_plugin_action_url( $action, $plugin_file, $context, $page, $s ) {
	return network_admin_url( 'plugins.php?action=' . $action . '_plugin&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s );
}
