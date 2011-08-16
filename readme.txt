=== Vault Docs ===
Plugin Name: Vault Docs
Contributors: yuji.od
Author URI: http://factage.com/yu-ji/
Plugin URI: http://factage.com/yu-ji/tag/vault-docs
Tags: admin, backup, pages, posts
Requires at least: 2.9
Tested up to: 3.2
Stable tag: 0.9.2

== Description ==

This plugin automatically backup your entry to Google Docs.

Support the following languages.

* English
* Japanese

= Requirements =

* PHP 5(tested up to PHP 5.1.6)
* allow_url_fopen is enabled on PHP
* OpenSSL support on PHP
* SimpleXML support on PHP
* Your server ecessary to be able to connect it with www.google.com and docs.google.com

= Limitation of current version =

I plan to provide it in by the upcoming version.

* Backup entry category is not supported
* Backup entry tag is not supported
* Backup attachment is not supported

== Installation ==

1. Upload `vault-docs` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Authentication to Google from `Vault Docs` menu

== Screenshots ==

1. Options
1. Backup for manually.(When the entry is saved, the backup is usually done by the automatic operation)
1. Restore

== Changelog ==

= Version 0.9.2 =
* Fixed cannot authentication bug on AuthSub.

= Version 0.9.1 =
* Fixed minor bugs.

= Version 0.9.0 =
* First beta release.
