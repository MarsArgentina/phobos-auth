=== Phobos Auth ===
Contributors: The Mars Society Argentina
Donate link: https://tmsa.ar
Tags: social login, discord, ultimate member
Requires at least: 5.7.0
Tested up to: 5.8.0
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

This plugin was made for The Mars Society Argentina. It adds social login options to Ultimate Member.

== Description ==

This plugin is intended to add Single Sign On options to Ultimate Member without using the paid Add-On.

Currently it only supports Discord but more OAuth2 providers can be added.

This plugin uses [The PHP League OAuth2 Client library](https://oauth2-client.thephpleague.com/) internally so all of the [official providers](https://oauth2-client.thephpleague.com/providers/league/) and [third-party providers](https://oauth2-client.thephpleague.com/providers/thirdparty/) can potentially be added to this plugin.

== Installation ==

Before you install this plugin you should install the following plugins:
* Ultimate Member
* Native PHP Sessions
* Phobos

Once you have installed and activated these plugins, follow the next steps:
1. Upload the `phobos-auth` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Start configuring your settings

== Frequently Asked Questions ==

= What OAuth2 providers are implemented? =

Currently only Discord, more can be added in the future.

= How is this better than the SSO add-on to Ultimate Member? =

It's probably not better, but it's free.

== Screenshots ==

1. Settings for this plugin.
2. Connecting a Discord account.
3. User information for a connected account.
4. Login with Discord.

== Changelog ==

= 1.0.0 =
* First working release

== Upgrade Notice ==

= 1.0.0 =
No upgrade is needed

== Connecting to Discord ==

In order to connect this plugin to Discod you need to perform the following steps:

1. Start by registering an Application in the [Discord Developer Portal](https://discord.com/developers/applications).
2. Head over to the OAuth2 tab, and grab your Client ID and Client Secret so that you can paste them in the Discord Settings of this plugin.
3. Grab the Redirects you can find in the Discord Settings of this plugin, and add them to the Redirects list of the OAuth2 tab on your Discord Application.

Enjoy!
