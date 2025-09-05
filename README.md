# Classyfeds
<<<<<<< HEAD
=======
### Federated Classifieds
>>>>>>> main

=== Classifieds ===
Contributors: thomi, amis  
Requires at least: 5.0  
Tested up to: 6.4  
Stable tag: 0.1.2  
License: GPL-2.0+  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

A minimal plugin providing a `listing` custom post type, JSON-LD markup, automatic expiration, and a frontend form that can forward listings to an ActivityPub inbox. On activation, it also creates a submission page with required price and location fields.

== Description ==
This plugin registers a "listing" custom post type with an expiration date and outputs structured JSON-LD data for each listing.

On activation, a "Classifieds" page is created, and its ID is stored in the `fed_classifieds_page_id` option. A separate "Submit Listing" page with the `[fed_classifieds_form]` shortcode is also generated. The bundled template displays local listings alongside ActivityPub objects that arrive through the REST inbox.

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

A minimal WordPress plugin providing a `listing` custom post type, JSON-LD markup, and automatic expiration for federated classified ads. A companion aggregator plugin is also included for setting up a standalone listings page.

## Features

- Registers `listing` custom post type.
- Adds default expiration 60 days after publish and moves listings to an `expired` status via daily cron.
- Outputs [schema.org](https://schema.org) Offer data as JSON-LD for each listing.
- Intended to work alongside companion plugins such as [ActivityPub](https://wordpress.org/plugins/activitypub/) and [WebSub](https://wordpress.org/plugins/websub-publisher/).
<<<<<<< HEAD
- Provides a `[classyfeds_form]` shortcode for frontend submissions that can forward listings to a configurable ActivityPub inbox.
- On activation some default categories common to classifieds sites are created for convenience.
- Exposes `/wp-json/classyfeds/v1/inbox` (POST) for incoming ActivityPub objects and `/wp-json/classyfeds/v1/listings` (GET) to retrieve them together with local listings.
- Creates a "Classifieds" page on activation and stores its ID in the `classyfeds_page_id` option.
=======
- Provides a `[fed_classifieds_form]` shortcode for frontend submissions that can forward listings to a configurable ActivityPub inbox.
- On activation, some default categories common to classifieds sites are created for convenience.
- Automatically creates a "Submit Listing" page with the shortcode, including required price and location fields.
- Exposes `/wp-json/fed-classifieds/v1/inbox` (POST) for incoming ActivityPub objects and `/wp-json/fed-classifieds/v1/listings` (GET) to retrieve them together with local listings.
- Creates a "Classifieds" page on activation and stores its ID in the `fed_classifieds_page_id` option.
- Registers a `publish_listings` capability and settings page to choose which roles can submit listings.

## Capabilities

On activation, the plugin creates a `publish_listings` capability and grants it to the built-in Author role as well as a new "Listing Contributor" role. Only users with this capability can submit listings via the `[fed_classifieds_form]` shortcode. Administrators may assign or revoke this capability for other roles under **Settings → Classifieds**.
>>>>>>> main

## Build

Run the build script to create distributable plugin archives:

```bash
./build-zip.sh
<<<<<<< HEAD
```

This produces `classyfeds.zip` containing `classyfeds.php` and `classyfeds-aggregator.zip` for the standalone aggregator.

## Installation

1. In your WordPress dashboard, go to **Plugins → Add New → Upload Plugin**.
2. Choose the generated `classyfeds.zip` file and click **Install Now**.
3. Activate the plugin.

Alternatively, copy `classyfeds.php` to `wp-content/plugins/classyfeds/` and activate it in your WordPress admin.

## Classifieds Page and REST API

On activation the plugin creates a **Classifieds** page. Its ID is stored in the `classyfeds_page_id` option and can be changed to use an existing page.

The page uses a bundled template that lists local `listing` posts along with any ActivityPub objects that were `POST`ed to `/wp-json/classyfeds/v1/inbox`. All listings and received objects are also exposed as an ActivityStreams collection via `GET /wp-json/classyfeds/v1/listings`.
=======
>>>>>>> main

## Shortcode

Use `[classyfeds_listings]` on any page or post to display the aggregated classifieds listings.
