<?php
/*
Plugin Name: Premium Packages
Plugin URI: http://wpmututorials.com/
Description: An addon for eShop for managing premium packages in a WordPress network.
Author: Ron Rennick
Version: 0.2.4
Author URI: http://ronandandrea.com/
Network: true

This plugin is a collaboration project with contributions from Success Creeations (http://successcreeations.com/)
*/
/* Copyright:   (C) 2010 Ron Rennick, All rights reserved.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
// @todo - add .po file

if( !is_multisite() )
		return;
		
if( defined( 'DOING_CRON' ) && DOING_CRON )
	require_once( plugin_dir_path( __FILE__ ) . 'lib/class.cron.php' );
	
if( is_main_site() && $_REQUEST['pkgreportback'] == 'true' ) {
	require_once( plugin_dir_path( __FILE__ ) . 'lib/class.packages.php' );
	add_action( 'eshop_on_success', array( 'RA_Premium_Packages', 'reportback' ) );
}

function rapm_packages_manager_init() {
	global $ra_premium_packages;
	if( !is_admin() )
		return; 
		
	if( empty( $ra_premium_packages ) ) {
		$folder = basename( dirname( __FILE__ ) );
		load_plugin_textdomain( 'premium-manager', false, "/$folder/languages/" );	
		require_once( plugin_dir_path( __FILE__ ) . 'lib/class.packages.php' );
	}	 
	$ra_premium_packages->init();
}
add_action( 'init', 'rapm_packages_manager_init' );
