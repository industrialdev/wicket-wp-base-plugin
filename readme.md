# Wicket WordPress Base Plugin

## Installation ##

This plugin is not available in the WordPress.org plugin repository. It is distributed to Wicket clients for implementation by a developer who will add the plugin according to the project code process.

## Style Notes
There is a placeholder theme.json file in the root of the plugin folder that is only there to provide an easy 'default styles' reference to Tailwind (WordPress should ignore it just fine), should we need to use fallback styles on a site that isn't running a Wicket theme. There is a similar fallback enqueue for Alpine in that scenario as well.

## SSO Tip
When running the wp-cassify plugin for SSO, you can bypass the SSO login if needed using this URL
https://localhost/wp/wp-login.php?wp_cassify_bypass=bypass

Rarely do we recommend logging into a Wicket-powered site directly without going through SSO, but there might be cases when that is needed, such as to rescue a site or reconfigure SSO/Wicket plugin settings locally after bringing down a production DB.
