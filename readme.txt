=== Fed Classifieds ===
Contributors: thomi, amis
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 0.1.2
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A minimal plugin providing a `listing` custom post type, JSON-LD markup, automatic expiration, and a frontend form that can forward listings to an ActivityPub inbox.
On activation it also creates a submission page with required price and location fields.

== Description ==
This plugin registers a "listing" custom post type with an expiration date and outputs structured JSON-LD data for each listing.

On activation a "Classifieds" page is created and its ID stored in the `fed_classifieds_page_id` option. A separate "Submit Listing" page with the `[fed_classifieds_form]` shortcode is also generated. The bundled template displays local listings alongside ActivityPub objects that arrive through the REST inbox.

The plugin provides two REST API endpoints for federation:

* `POST /wp-json/fed-classifieds/v1/inbox` – accepts ActivityPub objects or `Create` activities and stores them as `ap_object` posts.
* `GET /wp-json/fed-classifieds/v1/listings` – returns an ActivityStreams collection containing local listings and stored objects.

Any objects delivered to the inbox appear on the Classifieds page and in the listings endpoint.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/fed-classifieds` directory or use the ZIP file with "Upload Plugin".
2. Activate the plugin through the "Plugins" menu in WordPress.

== Changelog ==
= 0.1.2 =
* Added price and location fields to the submission form and created a default "Submit Listing" page.

= 0.1.1 =
* Added frontend listing submission shortcode with ActivityPub forwarding.
* Created default categories on activation.
= 0.1.0 =
* Initial release.
