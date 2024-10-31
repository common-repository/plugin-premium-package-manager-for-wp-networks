<?php
/*
This class handles the shopping cart in the subsite, purchase reportback & the Network Admin Premium screen
*/
class RA_Premium_Packages {
	var $packages = null;
	var $eshop_installed = false;
	var $checkout = '';
	var $blog_alpha = null;
	var $post_type = null;
	var $account = '';
	var $home = '';

	function RA_Premium_Packages() {}
	function  __construct() {}
	function init() {
		load_plugin_textdomain( 'premium-manager' );
		
		$this->eshop_installed = is_file( WP_PLUGIN_DIR . '/eshop/public-functions.php' );
		$this->packages = get_site_option( 'ra_premium_packages', array() );
		if( is_main_site() ) {
			$this->handle_reprocess();
			require_once( plugin_dir_path( __FILE__ ) . 'class.post-type.php' );
			$this->post_type = new RA_Premium_Post_Type( &$this, &$this->packages, $this->eshop_installed );
			add_filter( 'wpmu_blogs_columns', array( &$this, 'sites_columns' ) );
			add_action( 'manage_sites_custom_column', array( &$this, 'sites_custom_column' ), 10, 3 );
		} else {
			require_once( plugin_dir_path( __FILE__ ) . 'class.cron.php' );
			add_action( 'admin_menu', array( &$this, 'admin_menu' ), 20 );
			if( $this->eshop_installed && 'my_account_page' == $_REQUEST['page'] ) {
				$this->account = admin_url( 'index.php?page=my_account_page' );
				$this->home = home_url() . '/';			
				$this->handle_cart();
				wp_enqueue_style( 'ra-premium-pkg', plugin_dir_url( dirname( __FILE__ ) ) . 'css/eshop.css' );
				if( !empty( $_GET['dismiss'] ) )
					RA_Premium_Cron::dismiss( $_GET['dismiss'] );
			}
		}
	}
	function reportback( $checked ) {
		global $wpdb, $eshopoptions; 
		
		$extend = ( $eshopoptions['status'] == 'live' ? 86400 : 3600 );		
		$prefix = $wpdb->get_blog_prefix();
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT item_id, post_id, option_id FROM {$prefix}eshop_order_items WHERE post_id > 0 AND checkid = %s", $checked ) );
		if( empty( $items ) )
			return;
		
		$ids = array();
		foreach( $items as $item ) 
			$ids[] = $item->post_id;
			
		$posts = $wpdb->get_col( $wpdb->prepare( "SELECT ID from {$wpdb->posts} WHERE ID IN (" . implode( ',', $ids ) . ') AND post_type = %s', 'ra_package' ) );
		if( empty( $posts ) )
			return;
		
		$blog_alpha = '';
		foreach( $items as $key => $item ) {
			if( !in_array( $item->post_id, $posts ) ) {
				unset( $items[$key] );
				continue;
			}
			$item_id = explode( '-', $item->item_id, 2 );
			if( !$blog_alpha )
				$blog_alpha = $item_id[0];				
			elseif( $blog_alpha != $item_id[0] ) {
				unset( $items[$key] );
				continue;
			}
			$items[$key]->package = (array) maybe_unserialize( get_post_meta( $item->post_id, '_ra_pkg', true ) );
			$pkg_eshop = array();
			if( !empty( $items[$key]->package['eshop']['amount'][$item->option_id] ) )
				$pkg_eshop = array( 'unit' => $items[$key]->package['eshop']['unit'][$item->option_id], 'amount' => $items[$key]->package['eshop']['amount'][$item->option_id] );
			$items[$key]->package['extend'] = RA_Premium_Packages::calculate_extend( $extend, $pkg_eshop );
			$items[$key]->package['item_id'] = $item_id;
		}
		if( $blog_alpha ) {
			$blog_id = RA_Premium_Packages::alpha_to_id( $blog_alpha );
			switch_to_blog( $blog_id );
			$packages = get_option( 'ra_packages', array() );
			foreach( $items as $item ) {
				if( empty( $packages[$item->post_id] ) ) {
					$packages[$item->post_id] = $item->package;
					$packages[$item->post_id]['status'] = 'pending';
				} else {
					$packages[$item->post_id]['update'] = $item->package;
					$packages[$item->post_id]['status'] = 'update';
				}
			}
			update_option( 'ra_packages', $packages );
			if( ( $schedule = wp_next_scheduled( 'ra_package_cron' ) ) )
				wp_unschedule_event( $schedule, 'ra_package_cron' );
				
			wp_schedule_event( time() + 10, 'twicedaily', 'ra_package_cron' );
			restore_current_blog();
		}
	}
	function admin_menu() {
		add_submenu_page( 'index.php', __( 'My Account', 'premium-manager' ), __( 'My Account', 'premium-manager' ), 'manage_options', 'my_account_page', array( &$this, 'dashboard_page' ) );
	}
	function dashboard_page() {
		$screen_title = __( 'My Account', 'premium-manager' );
		$columns = ( !empty( $this->packages ) ? count( $this->packages ) : 2 );
		$this->package_screen( $screen_title, $columns );
	}
	// show the package screen in network admin or under My Account on sub sites
	function package_screen( $screen_title, $columns ) {
		global $blog_id; ?>
		<div id="premium-pkg-wrap" class="wrap">
			<h2><?php echo esc_html( $screen_title ); ?></h2>
<?php
		$sku = array();
		if( is_main_site() ) { 
			if( !is_super_admin() ) {
				_e( 'You don\'t have permission to access this screen!', 'premium-manager' );
				exit;
			} else {
				$args = array( 'page' => 'ra_premium_network_page', 'action' => 'update', 'columns' => $columns );
				printf( '<a class="button" href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( $args, network_admin_url( 'settings.php' ) ), 'update-package' ) ), __( 'Update Purchase Packages', 'premium-manager' ) );
				$args = array( 'page' => 'ra_premium_network_page', 'columns' => ( 2 == $columns ? 1 : 2 ) );
				printf( '&nbsp;<a class="button" href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( $args, network_admin_url( 'settings.php' ) ), 'update-package' ) ), sprintf( __( '%d Column', 'premium-manager' ), $args['columns'] ) );
			}
		} else {
			$checkout = add_query_arg( array( 'checkout' => '1' ), $this->account );
			echo '<div id="cart">' . ( !empty( $this->checkout ) ? $this->fix_cart_url( $this->checkout, $this->account ) : $this->get_cart( $checkout, $this->account ) ) . "</div>\n";
			$packages = get_option( 'ra_packages' );
			if( !empty( $packages ) ) {
				foreach( $packages as $p ) {
					$item = explode( ':', $p['item_id'][1] );
					if( empty( $item ) )
						continue;
					$sku[trim( $item[0] )] = array( 'status' => $p['status'], 'expires' => $p['expires'] );
				}
			}
		}
		echo "<div id='account-widgets' class='metabox-holder'>\n";
		$width = ( 1 == $columns ? '98' : '49' ); 
		foreach( $this->packages as $side ) {
			if( !empty( $side ) ) {
				echo "<div class='postbox-container' style='width:{$width}%;'>\n<div class='meta-box-sortables ui-sortable'>\n";
				foreach( $side as $pkg ) {
					$content = '';
					if( !empty( $sku[$pkg['sku']] ) )
						$content = '<h4>' . $this->get_package_status( $sku[$pkg['sku']]['status'], $sku[$pkg['sku']]['expires'] ) . '</h4>'; 
					$content .= ( is_main_site() ? $pkg['content'] : $this->fix_product_info( $pkg['content'], $this->account, true, false ) );					
					echo "<div id='package-{$pkg['id']}' class='postbox'>\n<div class='handlediv' title='Click to toggle'><br /></div>\n";
					echo "<h3 class='hndle'><span>{$pkg['title']}</span></h3>";
					echo "<div class='inside' style='margin:10px;'><div class='alignright'>{$pkg['thumbnail']}</div>\n{$content}</div>\n";
					echo "<br class='clear' /></div>\n";
				} 
				echo "</div>\n</div>\n";			
			}
		}
		echo "</div>\n"; ?>
		</div>		
<?php	}
	// get the shopping cart for the current subsite 
	function handle_cart() {
		global $wp_query, $echoit;
		
		require_once( WP_PLUGIN_DIR . '/eshop/public-functions.php' );
		require_once( WP_PLUGIN_DIR . '/eshop/cart-functions.php' );
		
		if( !session_id() )
			session_start();
			
		if( !empty( $_REQUEST['docart'] ) && '1' == $_REQUEST['docart'] ) 
			$this->checkout = $this->do_eshop( true );
		elseif( !empty( $_REQUEST['checkout'] ) && '1' == $_REQUEST['checkout'] )
			$this->checkout = $this->do_eshop();
		elseif( !empty( $_REQUEST['eshopaction'] ) ) {
			$this->switch_to_main_site();
			$wp_query->set( 'eshopaction', stripslashes( $_REQUEST['eshopaction'] ) );
//@todo: change to work on any payment gateway 
			require_once( WP_PLUGIN_DIR . '/eshop/paypal.php' );
			$this->restore_site();
			$this->checkout = $echoit;
		}
	}
	// this is the default handling of the cart if none of the tested conditions apply
	function get_cart( $checkout, $account ) {
		global $wpdb, $blog_id, $eshopoptions;
		$echo='';
		
		$this->switch_to_main_site();
		require_once( WP_PLUGIN_DIR . '/eshop/eshop-shortcodes.php' );

		//cache
		eshop_cache();
		foreach( array( 'error', 'enote' ) as $key ) {
			if( !empty( $_SESSION['eshopcart'.$blog_id][$key] ) ) {
				$echo .= $_SESSION['eshopcart'.$blog_id][$key];
				unset( $_SESSION['eshopcart'.$blog_id][$key] );
			}
		}
		if( !empty( $_SESSION['eshopcart'.$blog_id] ) ) {
			$echo.= $this->fix_product_info( display_cart( $_SESSION['eshopcart'.$blog_id], 'true', $eshopoptions['checkout'] ), $account, false );
			$echo.='<ul class="continue-proceed"><li class="gotocheckout"><a href="'. esc_url( $checkout ) .'">'.__('Proceed to Checkout &raquo;','eshop').'</a></li></ul>';
		}
		$this->restore_site();
		return $echo;
	}
	// the next 4 functions all replace eshop front end urls in the content for My Account ones in the current site
	function do_eshop( $show_cart = false ) {
		$checkout = false;
		$home = home_url() . '/';
		$this->switch_to_main_site();
		require_once( WP_PLUGIN_DIR . '/eshop/eshop-shortcodes.php' );
		require_once( WP_PLUGIN_DIR . '/eshop/checkout.php' );
		if( $show_cart ) {
			eshop_cart_process();
		} else {
			$checkout = eshop_checkout($_POST);
			$user = wp_get_current_user();
			foreach( array( 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->user_email ) as $k => $v ) {
				$pat = '|^(.*)(input[^\>]+' . $k . '[^\>]+\>)(.*)$|m';
				if( preg_match( $pat, $checkout, $m ) ) {
					$m[2] = str_ireplace( 'value=""', 'value="' . esc_attr( $v ) . '"', $m[2] );
					array_shift( $m );
					$checkout = preg_replace( $pat, implode( '', $m ), $checkout );
				}
			}
			$checkout = $this->fix_product_urls( $checkout );
			$lines = explode( "\n", $checkout );
			$lines[count( $lines ) - 1] = preg_replace( '|href=\"[^\"]+\"|', "href=\"{$this->account}\"", $lines[count( $lines ) - 1] );
			$checkout = implode( "\n", $lines );  
		}
		$this->restore_site();
		return $checkout;
	}
	function fix_cart_url( $cart, $checkout ) {
		$cart_lines = explode( "\n", $cart );
		$pat = '#' .network_home_url() . '.*(eshopaction=(success|cancel|redirect|process))#';
		$reportback = '#' .network_home_url() . '.*(eshopaction=.+)#';
		foreach( $cart_lines as $k => $line ) {
			if( preg_match( $pat, $line ) )
				$cart_lines[$k] = preg_replace( $pat, $checkout . '&amp;$1', $line );
			else
				$cart_lines[$k] = preg_replace( $reportback, network_home_url( '?pkgreportback=true' )  . '&amp;$1', $line );
		}
			
		return implode( "\n", $cart_lines );
	}
	function fix_product_info( $info, $url, $do_pid = true, $fix_product_urls = true ) {
		$lines = explode( "\n", $info );
		$pat = '|(form.*action=")' .network_home_url() . '[^\"]+\"|i';
		$qty = '|^(.*)(input[^\>]+name="qty"[^\>]+\>)(.*)$|i';
		$pid = '|^(.*)(input[^\>]+name="pid"[^\>]+\>)(.*)$|i';

		$action = 0;
		foreach( $lines as $k => $line ) {
			if( !$action && preg_match( $pat, $line ) ) { 
				$lines[$k] = preg_replace( $pat, '$1' . $url . '&amp;docart=1"', $line );
				if( !$do_pid )
					break;
				$action = 1;
			} elseif( $action == 1 && preg_match( $qty, $line, $m ) ) {
				$m[2] = preg_replace( '|(name=\")|i', 'disabled="disabled" $1ra:', $m[2] );
				array_shift( $m );
				$lines[$k] = preg_replace( $qty, implode( '', $m ), $line ) . '<input type="hidden" name="qty" value="1" />';
				$action = 2;
			} elseif( $action == 2 && preg_match( $pid, $line, $m ) ) {
				$m[2] = preg_replace( '|value=\"([^\"]+)"|i', 'value="' . $this->blog_alpha . '-$1"', $m[2] );
				array_shift( $m );
				$lines[$k] = preg_replace( $pid, implode( '', $m ), $line );
				break;
			}
		}
		$cart = implode( "\n", $lines );
		if( $fix_product_urls )
			return $this->fix_product_urls( $cart );
			
		return $cart;
	}
	function fix_product_urls( $cart ) {
		$cart = preg_replace( '|href=\"[^\"]+\"|m', 'href="#"', $cart );
		$cart = preg_replace( '|\<input[^\>]+name="update"[^\>]+\>|m', '', $cart );
		return preg_replace( "|src=\"{$this->home}|m", 'src="' . network_home_url(), $cart );
	}
	// set up for running cart on main site 
	function switch_to_main_site() {
		global $current_site, $blog_id, $eshopoptions;
		$this->blog_alpha = $this->id_to_alpha( $blog_id );
		switch_to_blog( $current_site->blog_id );
		$eshopoptions = get_option( 'eshop_plugin_settings' );
		$_SESSION['eshopcart' . $this->id_to_alpha( $blog_id )] = &$_SESSION['eshopcart' . $blog_id];
		$_SESSION['eshopcart' . $blog_id] = &$_SESSION['eshopcart' . $this->blog_alpha];		
	}
	// back to normal context 
	function restore_site() {
		global $blog_id;
		$_SESSION['eshopcart' . $this->blog_alpha] = &$_SESSION['eshopcart' . $blog_id];		
		$_SESSION['eshopcart' . $blog_id] = &$_SESSION['eshopcart' . $this->id_to_alpha( $blog_id )];
		restore_current_blog();
	}
	// convert a numeric ID to an alphabetic string
	function id_to_alpha( $id ) {
		$chars = 'ABCDEFGHIJKLMNOP';
		$alpha = '';
		$value = (int) $id;
		while( $value ) {
			$alpha = substr( $chars, $value & 15, 1 ) . $alpha;
			$value >>= 4;
		}
		return $alpha;
	}
	// convert alphabetic back to an ID
	function alpha_to_id( $alpha ) {
		$chars = 'ABCDEFGHIJKLMNOP';
		$id = 0;
		$len = strlen( $alpha );
		$index = 0;
		while( $index < $len ) {
			$id <<= 4;
			$c = substr( $alpha, $index, 1 );
			if( ( $n = strpos( $chars, $c ) ) !== false )
				$id += $n;
			$index++;
		}
		return $id;
	}
	function set_packages( &$packages ) {
		$this->packages = &$packages;
	}
	function calculate_extend( $base,  $pkg_eshop ) {
		$multiplier = 1;
		if( !empty( $pkg_eshop['unit'] ) && !empty( $pkg_eshop['amount'] ) ) {
			$pkg_eshop['amount'] = (int) $pkg_eshop['amount'];
			if( $pkg_eshop['amount'] > 1 )
				$multiplier = $pkg_eshop['amount'];
			switch( $pkg_eshop['unit'] ) {
				case 'day': // no extra logic required
					break;
				case 'year': // this logic will need work before the year 2400
					$month = date( 'n' );
					$year = date( 'y' ) % 4;
					for( $i = 0; $i < $pkg_eshop['amount']; $i++ ) {
						$multiplier += 364; // multipler starts at the number of years
						if( ( $year == 3 && $month > 2 ) || ( $year == 0 && $month <= 2 ) )
							$multiplier++;
						++$year;
						$year %= 4;
					}
					break;
				default: // month
					$work = time();
					for( $i = 0; $i < $pkg_eshop['amount']; $i++ ) {
						$days = date( 't', $work );
						$multiplier += $days - 1;
						$work += $days * 86400;
					}
					break;
			}
		}
		return $base * $multiplier;
	}
	function sites_columns( $columns ) {
		$columns[ 'ra-premium' ] = __( 'Premium Packages', 'premium-manager' );
		return $columns;
	}
	function sites_custom_column( $column, $blog_id ) {
		global $wpdb, $current_site;
		static $main_prefix = false;
		static $date_format = '';
		
		if ( $column == 'ra-premium' && !is_main_site( $blog_id ) ) {
			if( !$main_prefix ) {
				$main_prefix = $wpdb->get_blog_prefix( $current_site->blog_id );
				$date_format = get_option( 'date_format' );
			}

			$blog_hash = $this->id_to_alpha( $blog_id ) . '-'; 
			$query = $wpdb->prepare( "SELECT i.item_id,i.post_id,i.checkid,o.edited FROM {$main_prefix}eshop_order_items i JOIN {$main_prefix}eshop_orders o ON o.checkid = i.checkid WHERE i.item_id LIKE %s AND i.option_id > 0 ORDER BY o.edited DESC", $blog_hash . '%' );
			$purchases = $wpdb->get_results( $query );
			if( empty( $purchases ) )
				return;
				
			$posts = array();
			foreach( $purchases as $p ) {
				if( !in_array( $p->post_id, array_keys( $posts ) ) )
					$posts[$p->post_id] = $p;
			}
			if( !empty( $posts ) ) {
				$packages = get_blog_option( $blog_id, 'ra_packages' );
				foreach( $posts as $post_id => $p ) {
					$show_reprocess = true;
					echo '<p>' . substr( $p->item_id, strlen( $blog_hash ) ) . ' - ' . date( $date_format, mysql2date( 'U', $p->edited ) ) . '<br />';
					if( !empty( $packages[$post_id] ) ) {
						echo $this->get_package_status( $packages[$post_id]['status'], $packages[$post_id]['expires'] ) . '<br />';
						$show_reprocess = ( $packages[$post_id]['status'] != 'update' );
					}
					if( $show_reprocess ) {
						$url = add_query_arg( array( 'pkgreportback' => 'reprocess', 'packageid' => $p->checkid ), network_admin_url( 'sites.php' ) );
						printf( __( '<a href="%s">Reprocess Package</a>', 'premium-manager' ), wp_nonce_url( $url, 'reprocess-' . $p->checkid ) );
					}
					echo '</p>';
				}				
			}
		}
	}
	function get_package_status( $status, $expires ) {
		static $date_format;
		static $stati;
		
		if( empty( $stati ) ) {
			$date_format = get_option( 'date_format' );
			$stati = array( 'active' => __( 'Active, expires on %s', 'premium-manager' ),
				'expired' => __( 'Expired on %s', 'premium-manager' ),
				'pending' => __( 'Process pending', 'premium-manager' ),
				'update' => __( 'Update pending', 'premium-manager' )
			);
		}
		return sprintf( $stati[$status], date( $date_format, $expires ) );	
	}
	function handle_reprocess() {
		if( !empty( $_REQUEST['pkgreportback'] ) && $_REQUEST['pkgreportback'] == 'reprocess' && !empty( $_REQUEST['packageid'] ) ) {
			check_admin_referer( 'reprocess-' . $_REQUEST['packageid'] );
			$this->reportback( $_REQUEST['packageid'] );	
			wp_redirect( wp_get_referer() );
		}
	}
}

$ra_premium_packages = new RA_Premium_Packages();
