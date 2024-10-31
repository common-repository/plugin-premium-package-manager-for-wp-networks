<?php
/*
Plugin Name: Domain Mapping Expiry Redirect
Plugin URI: http://wpmututorials.com/
Description: For use in conjunction with the Add Domain Mapping Menu plugin - redirect to non mapped blog when the Add Domain Mapping Menu plugin is not active
Version: 0.2.4
Author: Ron Rennick
Author URI: http://ronandandrea.com/
Network: true
*/

add_action( 'plugins_loaded', 'rapm_dm_maybe_redirect' );

function rapm_dm_maybe_redirect() {
	if( !function_exists( 'rapm_add_dm_user_menu_item' ) ) {
		add_filter( 'pre_site_option_dm_redirect_admin', create_function( '', "return '1';" ) );		
		remove_action( 'template_redirect', 'redirect_to_mapped_domain' );
		
		if( defined( 'DOMAIN_MAPPING' ) && '1' == DOMAIN_MAPPING )
			dm_redirect_admin();
	}
}
