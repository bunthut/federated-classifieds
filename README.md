# Federated Classifieds

A minimal WordPress plugin providing a `listing` custom post type, JSON-LD markup and automatic expiration for federated classified ads. A companion aggregator plugin is also included for setting up a standalone listings page.

## Features

- Registers `listing` custom post type.
- Adds default expiration 60 days after publish and moves listings to an `expired` status via daily cron.
- Outputs [schema.org](https://schema.org) Offer data as JSON-LD for each listing.
- Intended to work alongside companion plugins such as [ActivityPub](https://wordpress.org/plugins/activitypub/) and [WebSub](https://wordpress.org/plugins/websub-publisher/).
- Provides a `[fed_classifieds_form]` shortcode for frontend submissions that can forward listings to a configurable ActivityPub inbox.
- On activation some default categories common to classifieds sites are created for convenience.

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
