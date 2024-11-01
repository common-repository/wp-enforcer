=== WP Enforcer ===
Contributors: (wpenforcer)
Donate link: https://wpenforcer.com
Tags: security, audit, uptime, monitoring, spam
Requires at least: 5.3
Tested up to: 5.8
Stable tag: 1.3.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit the activity of your site and get uptime statistics all from a central platform.

== Description ==

Keep an eye on the most important aspects of your WordPress site from one central location. Our plugin automatically sends activity that interacts with your database for things like posts, comments, authentication, and more to the cloud in realtime. This gives you peace of mind that you know what bots, spam, or other illegitimate traffic is interacting with your site.

Enable WP Enforcer to analyze your site for suspicious traffic patterns. With our vast database of IP addresses we can determine if the threat is coming from unwanted proxies, TOR network, nefarious web crawlers, or generally considered a threat to the community.

Check to see the current uptime percentage and response time of your site with 1 minute health checks. Get alerted when your site is down!

Take back control of where your traffic comes from. When using WP Enforcer you can decide to block certain traffic signatures and send them to our "safe zone". That has been proven to help eliminate spam and malicious traffic.

Many agencies and successful WordPress users will run multiple sites. With our innovative cloud based platform we allow you to add our plugin to monitor more than one site and interact with it all from one dashboard for convenience.

== Frequently Asked Questions ==

= Do I need to sign up for an account to use WP Enforcer? =

Yes, you need to sign up at [WP Enforcer](https://wpenforcer.com). From there you will get the *access key* that you need to put in your plugin settings.

= Is WP Enforcer free? =

Absolutely! We do offer a paid version but if you want to use it in an audit only mode that is totally free.

= What does enabling blocklist protection do? =

This will allow you to determine what traffic can interact with your site. If you are a premium customer then you can use this for our automated traffic filter or set up rules yourself.

= Does blocklist protection block all traffic? =

No. The protection only looks at traffic that is interacting with your database. Things like comments, page updates, logins etc. General traffic to your site will continue as normal. We only care about the spammers and hackers that try to gain access to your site via nefarious methods.

= What kind of info do you collect on site visitors? =

The only thing we collect is the IP address of the visiting user. This is used to determine if the IP is known to be of a high threat level. We also only collect this information when there is some type of database interaction (i.e. commenting). This is typically called C (create) R (read) U (update) D (delete) operations except we don't worry about the "R" because that typically isn't a signature of hacking.

= How do you handle SPAM detection? =

We partnered with Akismet who is the leader in SPAM detection. We utilize their API to detect SPAM and block.

== Screenshots ==

1. View of the WP Enforcer dashboard.
2. Configuration and at a glance view from your WordPress installation.

== Changelog ==

= 1.3.0 =
* Refreshed UI.

= 1.2.0 =
* Added SPAM detection in partnership with Akismet.

= 1.1.1 =
* Bug fix for undefined variable.

= 1.1.0 =
* Added additional auditing capabilities.

= 1.0.0 =
* Initial release.

 == Upgrade Notice ==
* Initial release.
