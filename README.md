# Federated Classifieds

A minimal WordPress plugin providing a `listing` custom post type, JSON-LD markup and automatic expiration for federated classified ads. A companion aggregator plugin is also included for setting up a standalone listings page.

## Features

- Registers `listing` custom post type.
- Adds default expiration 60 days after publish and moves listings to an `expired` status via daily cron.
- Outputs [schema.org](https://schema.org) Offer data as JSON-LD for each listing.
- Intended to work alongside companion plugins such as [ActivityPub](https://wordpress.org/plugins/activitypub/) and [WebSub](https://wordpress.org/plugins/websub-publisher/).
- Provides a `[fed_classifieds_form]` shortcode for frontend submissions that can forward listings to a configurable ActivityPub inbox.
- On activation some default categories common to classifieds sites are created for convenience.
- Automatically creates a "Submit Listing" page with the shortcode, including required price and location fields.
- Exposes `/wp-json/fed-classifieds/v1/inbox` (POST) for incoming ActivityPub objects and `/wp-json/fed-classifieds/v1/listings` (GET) to retrieve them together with local listings.
- Creates a "Classifieds" page on activation and stores its ID in the `fed_classifieds_page_id` option.

## Build

Run the build script to create distributable plugin archives:

```bash
./build-zip.sh
```

This produces `fed-classifieds.zip` containing `fed-classifieds.php` and `fed-classifieds-aggregator.zip` for the standalone aggregator.

## Installation

1. In your WordPress dashboard, go to **Plugins → Add New → Upload Plugin**.
2. Choose the generated `fed-classifieds.zip` file and click **Install Now**.
3. Activate the plugin.

Alternatively, copy `fed-classifieds.php` to `wp-content/plugins/fed-classifieds/` and activate it in your WordPress admin.

## Classifieds Page and REST API

On activation the plugin creates a **Classifieds** page. Its ID is stored in the `fed_classifieds_page_id` option and can be changed to use an existing page.

The page uses a bundled template that lists local `listing` posts along with any ActivityPub objects that were `POST`ed to `/wp-json/fed-classifieds/v1/inbox`. All listings and received objects are also exposed as an ActivityStreams collection via `GET /wp-json/fed-classifieds/v1/listings`.
