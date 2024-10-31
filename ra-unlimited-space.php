<?php
/*
Plugin Name: Unlimited Space
Plugin URI: http://wpmututorials.com/
Description: Allows a site to have unlimited media upload space
Version: 0.2.4
Author: Ron Rennick
Author URI: http://ronandandrea.com/
*/

function rapm_disable_space_check( $setting ) {
	return ( is_network_admin() ? setting : '1' );
}
add_filter( 'pre_site_option_upload_space_check_disabled', 'rapm_disable_space_check' );
