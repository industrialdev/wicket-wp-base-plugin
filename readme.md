# Wicket WordPress Base Plugin

## Installation ##

This plugin is not available in the WordPress.org plugin repository. It is distributed to Wicket clients for implementation by a developer who will add the plugin according to the project code process.
If using a non-composerized install of wordpress (i.e. bedrock), you must follow these steps to gain the vendor folder within the plugin.

Add this to the bottom of the object in composer.json at the root of the plugin:
```
  "repositories": [
  {
    "type": "git",
    "url": "https://github.com/industrialdev/wicket-sdk-php.git"
  }
 ]
```
Then run "composer install" within the plugin directory. You may have to remove the composer.lock first if this doesnt run. You'll know when this worked when you look in vendor and see the "industrialdev" folder

## Style Notes
There is a placeholder theme.json file in the root of the plugin folder that is only there to provide an easy 'default styles' reference to Tailwind (WordPress should ignore it just fine), should we need to use fallback styles on a site that isn't running a Wicket theme. There is a similar fallback enqueue for Alpine in that scenario as well.
