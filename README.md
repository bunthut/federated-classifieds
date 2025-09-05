# Federated Classifieds

A minimal WordPress plugin providing a `listing` custom post type, JSON-LD markup and automatic expiration for federated classified ads.

## Features

- Registers `listing` custom post type.
- Adds default expiration 60 days after publish and moves listings to an `expired` status via daily cron.
- Outputs [schema.org](https://schema.org) Offer data as JSON-LD for each listing.
- Intended to work alongside companion plugins such as [ActivityPub](https://wordpress.org/plugins/activitypub/) and [WebSub](https://wordpress.org/plugins/websub-publisher/).

## Installation

Copy `fed-classifieds.php` to `wp-content/plugins/fed-classifieds/` and activate the plugin in your WordPress admin.

