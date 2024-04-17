=== Wicket - Base plugin for WordPress ===
Contributors: 
Tags: wicket
Requires at least: 6.0
Tested up to: 6.3
Requires PHP: 8.1
Stable tag: 6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Connect your WordPress to Wicket.io. This official Wicket plugin includes core functionality, standard features and developer tools for integrating the Wicket member data platform into a WordPress installation.

= Features =
* Connect to Wicket API
* Developer tools and helper functions

= Links =
* [Website](https://wicket.io/)
* [API Documentation](https://wicketapi.docs.apiary.io/)
* [Support](https://support.wicket.io/)

== Installation ==

This plugin is not available in the WordPress.org plugin repository. It is distributed to Wicket clients for implementation by a developer who will add the plugin according to the project code process.
If using a non-composerized install of wordpress (i.e. bedrock), you must follow these steps to gain the vendor folder within the plugin.

Add this to the bottom of the object in composer.json at the root of the plugin:
  "repositories": [
  {
    "type": "git",
    "url": "https://github.com/industrialdev/wicket-sdk-php.git"
  }
 ]
Then run "composer install" within the plugin directory. You may have to remove the composer.lock first if this doesnt run. You'll know when this worked when you look in vendor and see the "industrialdev" folder



== Changelog ==

= 0.0.2 =
*Release Date 17th Sept 2023*

* Development - this is a placeholder release during initial plugin development.

= 0.0.1 =
*Release Date 16th Sept 2023*

* Enhancement - placeholder description
* Fix - placeholder description
* i18n - placeholder description
* Development - initial plugin setup

[View the full changelog](https://www.wicket.io/wordpress/changelog/)
