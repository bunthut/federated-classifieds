# Classyfeds

A minimal WordPress plugin providing a `listing` custom post type, JSON-LD markup, automatic expiration, and a frontend form that can forward listings to an ActivityPub inbox. On activation it also creates a Classifieds page and a submission page with required price and location fields.

## Features

- Registers `listing` custom post type.
- Adds default expiration 60 days after publish and moves listings to an `expired` status via daily cron.
- Outputs [schema.org](https://schema.org) Offer data as JSON-LD for each listing.
- Intended to work alongside companion plugins such as [ActivityPub](https://wordpress.org/plugins/activitypub/) and [WebSub](https://wordpress.org/plugins/websub-publisher/).
- Provides a `[classyfeds_form]` shortcode for frontend submissions that can forward listings to a configurable ActivityPub inbox.
- Creates default categories common to classifieds sites for convenience.
- Automatically creates a "Submit Listing" page with the shortcode, including required price and location fields.
- Exposes `/wp-json/classyfeds/v1/inbox` (POST) for incoming ActivityPub objects and `/wp-json/classyfeds/v1/listings` (GET) to retrieve them together with local listings.
- Creates a "Classifieds" page on activation and stores its ID in the `classyfeds_page_id` option.
- Registers a `publish_listings` capability and settings page to choose which roles can submit listings and whether listings appear in standard post queries.
- Stores uploaded listing images in a private `classyfeds` directory outside the Media Library.

## Capabilities

On activation, the plugin creates a `publish_listings` capability and grants it to the built-in Author role as well as a new "Listing Contributor" role. Only users with this capability can submit listings via the `[classyfeds_form]` shortcode. Administrators may assign or revoke this capability for other roles under **Settings → Classifieds**.

## Build

Run the build script to create distributable plugin archives:

```bash
./build-zip.sh
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

## Shortcode

Use `[classyfeds_listings]` on any page or post to display the aggregated classifieds listings.
