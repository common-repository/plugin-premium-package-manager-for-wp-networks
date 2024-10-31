=== Plugin Premium Package Manager for WP Networks ===
Contributors: wpmuguru
Tags: plugins, wordpress, network, premium, packages 
Requires at least: 3.1
Tested up to: 3.1
Stable tag: 0.2.3

Lightweight plugin, menu and premium package manager for WordPress networks.

== Description ==

A collection of six plugins which allow a Super Admin of a WordPress network to 

*	manage plugin availability in the Site Admin Plugins screen across the network
*	manage menus in the Site Admin Dashboard across the network
*	implement premium/freemium packages

This plugin is currently unsupported and has no documentation beyond this readme. The six plugins are

*	Super Admin Plugin Manager

*Adds links to the Network Plugins page to control plugin visibility on the site plugins page*

When the plugin manager is initially activated in a WP 3.1 network the plugins list in the Site (blog) Admin area contains no plugins for non-Super Admins. Super Admins still have access to all plugins on any Site Admin Plugin screen.

In the Network Admin Plugins screen an action link (Make Visible) is added to each plugin that normally shows on the Site Admin Plugin screen. Clicking the Make Visible link adds that plugin to the Site Admin Plugin list for non-Super Admins. If a plugin is made visible on the Site Admin Plugin screen, the action link in the network admin plugins screen shows Make Invisible. The two versions of the link are in effect a toggle of the visibility on the Site Admin Plugin screen.

*	Super Admin Menu Manager

*Manage menu visibility in the site dashboard*

The Menu Manager does not affect menu visibility for Super Admins. When the Menu Manager is activated it adds a Manage Menus item to Site Admin Tools menu for Super Admins. On the main site of the network, the menu Manager allows Super Admins to edit the default menu visibility for Site Admins across the network. On any subsite, the Menu Manager allows Super Admins to set the menu visibility for that subsite.

*	Unlimited Space

*Allows a site to have unlimited media upload space*

When Unlimited Space is active on any site on the network, it allows that site to have unlimited media upload space.

*	Add Domain Mapping Menu

*Adds the user domain mapping menu item under the Tools menu if Donncha's Domain Mapping plugin is installed*

Requires [Domain Mapping](http://wordpress.org/extend/plugins/wordpress-mu-domain-mapping/)

The Domain Mapping plugin has the option to turn off the User domain mapping screen. When Add Domain Mapping Menu is active on a subsite, the User domain mapping screen is available on that subsite.

*	Domain Mapping Expiry Redirect

*For use in conjunction with the Add Domain Mapping Menu plugin - redirect to non mapped blog when the Add Domain Mapping Menu plugin is not active*

Requires [Domain Mapping](http://wordpress.org/extend/plugins/wordpress-mu-domain-mapping/)

When Domain Mapping Expiry Redirect is active on the network, mapped domains on subsites where the Add Domain Mapping Menu is not active are redirected to the unmapped subsite URL.

*	Premium Packages

*An addon for eShop for managing premium packages in a WordPress network.*

Requires [eShop](http://wordpress.org/extend/plugins/eshop/) which only needs to be active on the main site

Adds a Premium Packages custom post type to the main site in a WordPress network. Custom Metaboxes on Premium Packages are

- eShop Product Entry
- Package Menus (See Menu Manager above)
- Attributes (See Display Order below)
- eShop Options (for mapping eShop product options to length of time the package is active)
- Package Plugins (Plugins to activate with this package)
- Package Themes (Themes to enable with this package)

Adds a Premium screen to Network Settings in the Network Admin area. This screen displays published Premium Packages ordered by Display Order. Currently the only option is 1 or 2 columns. The Update Packages button save the current state of the packages to be shown to Site Admins.

Adds a My Account screen to the Dashboard menu on subsites. The My Account screen handles the eShop shopping cart. Shortly after a purchase has been made & the transaction has been processed through eShop's transaction handling, the sub site will be automattically updated to reflect the purchases (themes enabled, plugins activated, etc.). 

Current limitations:

- PayPal has to be used as the payment gateway
- automated subscriptions haven't been implemented at this time
- normal eShop usage is removed on the main site

This plugin was written by [Ron Rennick](http://ronandandrea.com/) in collaboration with the [EmmanuelPress](http://emmanuelpress.com/).

== Installation ==

1. To install in the plugins folder Upload the `plugin-premium-package-manager-for-wp-networks` folder to the `/wp-content/plugins/` directory. Plugins will be listed in Network Admin Plugins screen.
1. Activate the network plugins through the Network Admin 'Plugins' screen in WordPress
1. Activate the optional site plugins as needed on the Site Admin 'Plugins' screen in WordPress

== Changelog ==

= 0.2.3 =
* Allow plugins in packages when plugin menu is disabled.
* Remove Visible/Invisible links when plugin menu is disabled.
* Add package status to network admin sites screen.
* Allow links in package post content.
* Fix initial dismiss of admin notice.
* Fix blog id hash calculation.
* Fix checks for menu manager.
* Show admin notices only to admins.

= 0.2.2 =
* Add support for multiple time periods.

= 0.2.1 =
* Add the premium package manager.

= 0.2 =
* Add the menu manager, domain redirect & unlimited space plugins.

= 0.1 =
* Original version.

