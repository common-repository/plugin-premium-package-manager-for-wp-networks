<?php
/*
This class has all the code related to the premim packages post type which is only registered & used on the main site 
*/

class RA_Premium_Post_Type {
	var $post_type_name = 'ra_package';
	var $post_meta = null;
	var $packages = null;
	var $parent = null;
	var $eshop_installed = false;
	var $columns = 0;
	
	var $post_type = array(
		'menu_position' => '1',
		'taxonomies' => array(),
		'public' => false,
		'show_ui' => true,
		'map_meta_cap' => true,
		'rewrite' => false,
		'query_var' => false,
		'supports' => array( 'title', 'editor', 'thumbnail' )
		);

	function RA_Premium_Post_Type( &$parent, &$packages, $eshop_installed ) {
		return $this->__construct( &$parent, &$packages, $eshop_installed );
	}

	function  __construct( &$parent, &$packages, $eshop_installed ) {
		$this->parent = &$parent;
		$this->packages = &$packages;
		$this->eshop_installed = $eshop_installed;
		$this->cap = 'edit_' . $this->post_type_name . 's';
		$this->post_type['label'] = __( 'Premium Packages', 'premium-manager' );
		$this->post_type['singular_label'] = __( 'Premium Package', 'premium-manager' );
		$this->post_type['description'] = $this->post_type['singular_label'];
		$this->post_type['capability_type'] = $this->post_type_name;
		
		if( $this->eshop_installed ) {
			$this->post_type['menu_icon'] = WP_PLUGIN_URL.'/eshop/eshop.png';
			wp_enqueue_style( 'ra-premium-pkg', WP_PLUGIN_URL . '/premium-manager/css/eshop.css' );
		}

		$this->post_type['labels'] = array(
			'name' => $this->post_type['label'],
			'singular_name' => $this->post_type["singular_label"],
			'add_new' => 'Add ' . $this->post_type["singular_label"],
			'add_new_item' => 'Add New ' . $this->post_type["singular_label"],
			'edit' => 'Edit',
			'edit_item' => 'Edit ' . $this->post_type["singular_label"],
			'new_item' => 'New ' . $this->post_type["singular_label"],
			'search_items' => 'Search ' . $this->post_type["singular_label"],
			'not_found' => 'No ' . $this->post_type["singular_label"] . ' Found',
			'not_found_in_trash' => 'No ' . $this->post_type["singular_label"] . ' Found in Trash'
			);
		register_post_type( $this->post_type_name, $this->post_type );
		
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 20 );
		add_action( 'network_admin_menu', array( &$this, 'network_admin_menu' ) );
		add_action( 'save_post', array( &$this, 'save_post' ), 12, 2 );
		add_filter( 'eshop_post_types', array( &$this, 'eshop_post_types' ) );
		add_filter( 'map_meta_cap', array( &$this, 'map_meta_cap' ), 10, 3 );
	}
	function admin_menu() {
		add_action( 'do_meta_boxes', array( &$this, 'add_metabox' ), 9 );
	}
	function network_admin_menu() {
		add_submenu_page( 'settings.php', __( 'Premium', 'premium-manager' ), __( 'Premium', 'premium-manager' ), 'manage_options', 'ra_premium_network_page', array( &$this, 'network_admin_page' ) );
	}
	function network_admin_page() {
		if( !empty( $_REQUEST['columns'] ) )
			$this->columns = ( 1 == (int)$_REQUEST['columns'] ? 1 : 2 );
		else 
			$this->columns = ( !empty( $this->packages ) ? count( $this->packages ) : 2 );
		
		$this->get_packages();
		$screen_title = __( 'Premium Packages', 'premium-manager' );
		if( $_GET['action'] == 'update' && wp_verify_nonce( $_GET['_wpnonce'], 'update-package' ) ) {
			update_site_option( 'ra_premium_packages', $this->packages );
			echo '<div class="updated">Updated</div>';
		}
			
		$this->columns = ( !empty( $this->packages ) ? count( $this->packages ) : 2 );
		$this->parent->set_packages( &$this->packages );
		$this->parent->package_screen( $screen_title, $this->columns );
	}
	function add_metabox() {
		global $post;
		if( empty( $post ) || $this->post_type_name != $post->post_type )
			return;

		add_meta_box( 'pageparentdiv', __( 'Attributes', 'premium-manager' ), array( &$this, 'menu_order_metabox' ), $this->post_type_name, 'side' );
		add_meta_box( 'pkgshopdiv', __( 'eShop Options', 'premium-manager' ), array( &$this, 'package_eshop_metabox' ), $this->post_type_name, 'side' );
		add_meta_box( 'pkgspacediv', __( 'Account Upgrade', 'premium-manager' ), array( &$this, 'package_space_metabox' ), $this->post_type_name, 'side' );
		add_meta_box( 'pkgmenudiv', __( 'Package Menus', 'premium-manager' ), array( &$this, 'package_menus_metabox' ), $this->post_type_name, 'normal' );
		add_meta_box( 'pkgplugintdiv', __( 'Package Plugins', 'premium-manager' ), array( &$this, 'package_plugins_metabox' ), $this->post_type_name, 'side' );
		add_meta_box( 'pkgthemediv', __( 'Package Themes', 'premium-manager' ), array( &$this, 'package_themes_metabox' ), $this->post_type_name, 'side' );
	}
	function menu_order_metabox() { 
		global $post; ?>
<p><strong><?php _e( 'Display Order', 'premium-manager' ) ?></strong></p>
<p><label class="screen-reader-text" for="menu_order"><?php _e( 'Display Order', 'premium-manager' ); ?></label><input name="menu_order" type="text" size="4" id="menu_order" value="<?php echo esc_attr($post->menu_order) ?>" /></p>
<?php	}
	function package_space_metabox() {
		$ra_pkg_space = (array) $this->get_post_meta( 'space' ); ?>
		
		<p><strong><?php _e( 'Increase media space with this package to', 'premium-manager' ) ?></strong></p>
		<p>
			<input type="text" size="5" name="ra_pkg_space[amount]" id="ra_pkg_space-amount" value="<?php echo ( empty( $ra_pkg_space['amount'] ) ? '' : $ra_pkg_space['amount'] ); ?>" />&nbsp;
			<select name="ra_pkg_space[unit]" id="ra_pkg_space-unit">
<?php		foreach( array( 'MB', 'GB' ) as $u ) {
			printf( "<option value='%s' %s>%s</option>\n", esc_attr( $u ), selected( $u, $ra_pkg_space['unit'], false ), esc_html( $u ) );
		} ?>
			</select>
		</p>
<?php	}
	function package_eshop_metabox() {
		global $eshopoptions;
		
		$num_options = (int) $eshopoptions['options_num'];
		if( !$num_options ) {
			echo '<p><strong>' . __( 'eShop options not set', 'premium-manager' ) . '</strong></p>';
			return;
		}
		$ra_eshop = (array) $this->get_post_meta( 'eshop' );
		
		for( $i = 1; $i <= $num_options; $i++ ) { ?>
		
		<p><strong><?php printf( __( 'Option %d package length', 'premium-manager' ), $i ); ?></strong></p>
		<p>
			<input type="text" size="5" name="ra_pkg_eshop[amount][<?php echo $i; ?>]" id="ra_pkg_eshop-amount-<?php echo $i; ?>" value="<?php echo ( empty( $ra_eshop['amount'][$i] ) ? '' : $ra_eshop['amount'][$i] ); ?>" />&nbsp;
			<select name="ra_pkg_eshop[unit][<?php echo $i; ?>]" id="ra_pkg_eshop-unit-<?php echo $i; ?>">
<?php			foreach( array( 'day' => __( 'Days', 'premium-manager' ), 'month' => __( 'Months', 'premium-manager' ), 'year' => __( 'Years', 'premium-manager' ) ) as $u => $text ) {
				printf( "<option value='%s' %s>%s</option>\n", esc_attr( $u ), selected( $u, $ra_eshop['unit'][$i], false ), esc_html( $text ) );
			} ?>
			</select>
		</p>
<?php		}
	}
	function package_plugins_metabox() {
		$net_menus = get_site_option( 'menu_items' );
		$plug_enabled = ( empty( $net_menus['plugins'] ) ? false : (bool)$net_menus['plugins'] );
		if( $plug_enabled && !function_exists( 'rapm_all_plugins_filter' ) ) {
			$this->show_activation_link( 'ra-plugin-manager.php', __( '<a href="%s">Activate</a> the plugin manager to use this feature', 'premium-manager' ) );
			return;	
		} ?>
		<p><strong><?php _e( 'Plugins to activate with this package', 'premium-manager' ) ?></strong></p>
<?php		$plugins = get_plugins();
		$visible = ( $plug_enabled ? get_site_option( 'ra_visible_plugins', array() ) : array() );
		$ra_pkg_plugins = (array) $this->get_post_meta( 'plugins' );
		echo '<p><ul>';
		foreach( $plugins as $k => $v ) {
			if( !in_array( $k, $visible ) && !$v['Network'] && !is_plugin_active_for_network( $k ) ) {
				printf( '<li><input type="checkbox" name="ra_pkg_plugins[%s]" id="ra_pkg_plugins-%s" value="1" %s />', esc_attr( $k ), esc_attr( $k ), checked( in_array( $k, $ra_pkg_plugins ), true, false ) );
				printf( "<label for='ra_pkg_plugins[%s]'>%s</label></li>\n", esc_attr( $k ), esc_html( $v['Name'] ) );
			}
		}
		echo '</ul></p>';
	}
	function package_themes_metabox() { ?>

		<p><strong><?php _e( 'Themes to enable with this package', 'premium-manager' ) ?></strong></p>
<?php		$themes = get_themes();
		$allowed = get_site_option( 'allowedthemes', array() );
		$ra_pkg_themes = (array) $this->get_post_meta( 'themes' );
		echo '<p><ul>';
		foreach( $themes as $k => $v ) {
			$stylesheet = $v['Stylesheet'];
			if( empty( $allowed[$stylesheet] ) || !$allowed[$stylesheet] ) {
				printf( '<li><input type="checkbox" name="ra_pkg_themes[%s]" id="ra_pkg_themes-%s" value="1" %s />', esc_attr( $stylesheet ), esc_attr( $stylesheet ), checked( in_array( $stylesheet, $ra_pkg_themes ), true, false ) );
				printf( "<label for='ra_pkg_themes[%s]'>%s</label></li>\n", $stylesheet, esc_html( $k ) );
			}
		}
		echo '</ul></p>';
	}
	function package_menus_metabox() {
		global $menu, $submenu;
		if( !function_exists( 'rapm_menu_manager_get_hidden' ) ) {
			$this->show_activation_link( 'ra-menu-manager.php', __( '<a href="%s">Activate</a> the menu manager to use this feature', 'premium-manager' ) );
			return;	
		} ?>
		<p><strong><?php _e( 'Menus to enable with this package', 'premium-manager' ) ?></strong></p>
		<em><?php _e( 'Checked Menus are not shown to Site Admins', 'premium-manager' ); ?></em>
<?php		$hidden = rapm_menu_manager_get_hidden();
		if( ( $package = $this->get_post_meta( 'menus' ) ) === null )
			$package = $hidden;
		
		echo "</p><ul>\n";
		foreach( $menu as $m ) {
			if( empty( $m[0] ) || empty( $hidden[$m[2]] ) )
				continue;
			printf( '<li><label><input type="checkbox" name="menu_manager[%1$s][0]" id="menu_manager-%1$s" value="1" %2$s/>&nbsp;%3$s</label>', $m[2], checked( $package[$m[2]][0], 1, false ), $m[0] );
			if( !empty( $submenu[$m[2]] ) ) {
				echo "<ul>\n";
				foreach( $submenu[$m[2]] as $s )
					printf( '<li><label><input type="checkbox" name="menu_manager[%1$s][%2$s]" id="menu_manager-%1$s-%2$s" value="1" %3$s/>&nbsp;%4$s</label></li>', $m[2], $s[2], checked( $package[$m[2]][$s[2]], 1, false ), $s[0] );
				echo "</ul>\n";
			}
		}
		echo '</ul><div class="clear"></div></p>';
	}
	function save_post( $post_id, $post ) {
		global $wpdb;
		if( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || ( defined('DOING_AJAX') && DOING_AJAX ) || ( defined('DOING_CRON') && DOING_CRON ) )
			return;
		if( $post->post_type != $this->post_type_name || !current_user_can( $this->cap ) ) 
			return;

		$this->post_meta = array();
		$amount = (int) $_POST['ra_pkg_space']['amount'];
		$unit = ( $_POST['ra_pkg_space']['unit'] == 'GB' ? 'GB' : 'MB' );
		if( $amount )
			$this->post_meta['space'] = compact( 'amount', 'unit' );
		if( !empty( $_POST['ra_pkg_plugins'] ) )
			$this->post_meta['plugins'] = stripslashes_deep( array_keys( $_POST['ra_pkg_plugins'] ) );
		if( !empty( $_POST['ra_pkg_themes'] ) )
			$this->post_meta['themes'] = stripslashes_deep( array_keys( $_POST['ra_pkg_themes'] ) );
		if( !empty( $_POST['ra_pkg_eshop'] ) )
			$this->post_meta['eshop'] = stripslashes_deep( $_POST['ra_pkg_eshop'] );
		
		if( function_exists( 'rapm_menu_manager_get_hidden' ) ) {
			$pkg_menus = stripslashes_deep( $_POST['menu_manager'] );
			$hidden = rapm_menu_manager_get_hidden();
			$update = false;
			foreach( $hidden as $menu => $submenus ) {
				foreach( array_keys( $submenus ) as $submenu ) {
					if( !empty( $pkg_menus[$menu][$submenu] ) )
						continue;
						
					$update = true;
					break;
				}
				if( $update ) {
					$this->post_meta['menus'] = $pkg_menus;
					break;
				}
			}
		}
		if( empty( $this->post_meta ) )
			delete_post_meta( $post_id, '_ra_pkg' );
		else
			update_post_meta( $post_id, '_ra_pkg', $this->post_meta );
			
		$where = $wpdb->prepare( "WHERE post_status = 'publish' AND post_type = %s", $this->post_type_name );
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM {$wpdb->posts} {$where} AND menu_order = %d", $post->menu_order ) );
		if( $count > 1 )
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET menu_order = menu_order + 1 {$where} AND menu_order >= %d AND ID != %d", $post->menu_order, $post->ID ) );
	}
	// build the packages list based on the current published premium package posts in the main site
	function get_packages() {
		global $wp_query;
		if( !$this->eshop_installed ) {
			echo 'eShop not installed'; 
			return;
		}
		// fake eShop into thinking this is on the front of the site 		
		require_once( WP_PLUGIN_DIR . '/eshop/public-functions.php' );
		require_once( WP_PLUGIN_DIR . '/eshop/eshop-add-cart.php' );
		require_once( WP_PLUGIN_DIR . '/eshop/eshop-shortcodes.php' );
		add_filter( 'query_vars', 'add_eshop_query_vars' );
		eshop_cart_process();
		eshop_bits_and_bobs();
		// pull the package posts
		$wp_query = new WP_Query( 'showposts=0&post_status=publish&orderby=menu_order&order=ASC&post_type=' . $this->post_type_name );
		// fake eShop into thinking this is a single post 
		$wp_query->is_single = true;
		$index = 0;
		// split the packages into arrays for easy implementation in meta boxes
		if( !$this->columns )
			$this->columns = ( !empty( $this->packages ) ? count( $this->packages ) : 2 );
			
		$this->packages = array();
		for( $i = 0; $i < $this->columns; $i++ )
			$this->packages[$i] = array();

		while( have_posts() ) {
			$p = array();
			the_post();
			$p['id'] = get_the_ID();
			$p['title'] = get_the_title();
			$p['thumbnail'] = get_the_post_thumbnail( null, 'thumbnail' );
			$p['content'] = eshop_boing( get_the_content() );
			$p['premium'] = (array) maybe_unserialize( get_post_meta( $p['id'], '_ra_pkg', true ) );
			$product = (array) maybe_unserialize( get_post_meta( $p['id'], '_eshop_product', true ) );
			if( !empty( $product['sku'] ) ) {
				$p['sku'] = $product['sku'];
				$this->packages[$index % $this->columns][] = $p;
				$index++;
			}
		}
	}
	// a few utility, hook or filter functions 
	function eshop_post_types( $types ) {
//@todo: give super admins an option as to what post types are on/off
		if( is_main_site() )
			return array( $this->post_type_name );
			
		return $types;
	}
	function get_post_meta( $key ) {
		global $post;
		if( $this->post_meta === null )
			$this->post_meta = (array) maybe_unserialize( get_post_meta( $post->ID, '_ra_pkg', true ) );
			
		return ( is_array( $this->post_meta[$key] ) ? $this->post_meta[$key] : null );
	}
	function map_meta_cap( $caps, $cap, $user_id ) {
		if( $cap == $this->cap && !is_super_admin( $user_id ) )
			$caps[] = 'do_not_allow';
			
		return $caps;
	}
	function show_activation_link( $plugin_basename, $message ) {
		$url = network_admin_url( 'plugins.php' );
		$path = plugin_dir_path( __FILE__ );
		if( is_file( $path . '/' . $plugin_basename ) ) {
			$man = str_replace( WP_PLUGIN_DIR . '/', '', $path ) . '/' . $plugin_basename;
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'activate', 'networkwide' => '1', 'plugin' => urlencode( $man ), 'plugin_status' => 'all' ), $url ), 'activate-plugin_' . $man );
		}
		echo '<p><strong>' . sprintf( $message, $url ) . '</strong></p>';
	}
}
