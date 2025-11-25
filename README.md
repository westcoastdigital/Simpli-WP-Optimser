# WP Optimiser by SimpliWeb

A comprehensive WordPress optimisation toolkit that helps you analyse, clean up, and maintain your WordPress site with four powerful features.

## Features

### 1. Post Relationship Visualiser
Analyse your site's internal linking structure to improve SEO and content discoverability.

- Scans all posts, pages, and custom post types
- Identifies orphaned content (posts with no incoming links)
- Shows top 20 most connected posts
- Displays "Links To" and "Linked By" counts
- One-click access to edit posts

### 2. Transient Manager
View and manage WordPress transients to optimise database performance.

- Lists up to 500 transients with details
- Shows expiration date and status for each transient
- Displays individual and total size information
- Delete expired transients with one click
- Delete all transients when needed (with confirmation)
- Free up database space quickly

### 3. Shortcode Finder
Scan all content to find and verify shortcodes before they break.

- Finds all shortcodes used across your site
- Identifies registered vs orphaned (broken) shortcodes
- Shows usage count per shortcode
- Lists all posts using each shortcode
- Essential before deactivating plugins
- Displays all currently registered shortcodes

### 4. Media Library Source Tracker
Automatically track where media files are uploaded from.

- Tracks upload source automatically (no configuration needed)
- Adds "Upload Source" column to Media Library
- Shows which post/page the media was uploaded from
- Displays source post type
- Shows last 200 uploads with tracking data
- Helps identify and clean up unused media

## Installation

### Method 1: Upload via WordPress Admin

1. Download the plugin folder
2. Create a zip file of the `simpliweb-wp-optimiser` folder
3. In WordPress admin, go to **Plugins > Add New > Upload Plugin**
4. Choose the zip file and click **Install Now**
5. Click **Activate Plugin**

### Method 2: Manual FTP Upload

1. Download the plugin folder
2. Upload the entire `simpliweb-wp-optimiser` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin
4. Find "WP Optimiser by SimpliWeb" and click **Activate**

## Usage

### Accessing the Plugin

After activation, find the **Optimiser** menu item (with hammer icon) in your WordPress admin sidebar.

### Post Relationships

1. Navigate to **Optimiser > Post Relationships**
2. Click **Scan Relationships** button
3. Wait for the scan to complete (may take 30-60 seconds on large sites)
4. Review the results:
   - Total posts scanned
   - Posts with outgoing links
   - Orphaned posts list
   - Link map of top 20 most connected posts
5. Click **Edit** on any post to improve internal linking

### Transient Manager

1. Navigate to **Optimiser > Transient Manager**
2. View transient statistics and list
3. Options:
   - **Delete Expired Transients** - Removes only expired entries (recommended)
   - **Delete All Transients** - Clears all transients (may temporarily slow your site)
4. Refresh the page to see updated statistics

### Shortcode Finder

1. Navigate to **Optimiser > Shortcode Finder**
2. View automatically generated report showing:
   - Total unique shortcodes found
   - Orphaned shortcodes (not registered)
   - Posts with shortcodes
3. Check the status column (✓ Registered or ✗ Not Registered)
4. Expand details to see which posts use each shortcode
5. Fix or remove broken shortcodes before users see them

### Media Library Source Tracker

This feature works automatically once activated.

1. Navigate to **Optimiser > Media Library Source Tracker** to view tracking report
2. Or go to **Media > Library** to see the new "Upload Source" column
3. Upload images as normal - source is tracked automatically
4. View statistics on tracked vs untracked uploads

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Administrator user role

## Performance Notes

- **Post Relationship Visualiser**: May take 30-60 seconds on large sites (1000+ posts). Uses AJAX to prevent timeouts.
- **Transient Manager**: Instant deletion. Site may be slightly slower after clearing all transients as they rebuild.
- **Shortcode Finder**: Queries database once per page load. Results are always current (not cached).
- **Media Library Source Tracker**: Minimal performance impact. Only fires on upload.

## Database Storage

The plugin stores minimal data:
- Media source tracking uses postmeta: `_upload_source_post`, `_upload_source_url`, `_upload_date`
- No custom database tables are created

## Image Sizes

The plugin registers one custom image size:
- **simpli-thumbbail**: 50x50px (hard cropped)
  - Used for displaying media thumbnails in the plugin interface
  - Automatically generated when images are uploaded

## Security

All features include:
- `manage_options` capability requirement (Administrator only)
- WordPress nonce verification for CSRF protection
- Proper output escaping
- Prepared statements for database queries
- Permission checks on all AJAX requests

## Frequently Asked Questions

### Why are some posts showing as orphaned?

Orphaned posts have no incoming internal links from other content on your site. This can hurt SEO and discoverability. Consider adding internal links to these posts from relevant content.

### Is it safe to delete all transients?

Yes, but your site may be temporarily slower as WordPress rebuilds needed transients. It's usually better to delete only expired transients.

### What happens to shortcodes marked as "Not Registered"?

These shortcodes won't work and will appear as plain text (e.g., `[shortcode_name]`) to visitors. Fix them before deactivating the plugin that provided them.

### Does Media Source Tracker work for old uploads?

No, it only tracks uploads made after the plugin is activated. Old uploads won't have source information.

### Can I export the relationship data?

Currently, no export feature is included. The data is displayed on-screen for review and action.

## Changelog

### 1.0.0 (Initial Release)
- Post Relationship Visualiser with AJAX scanning
- Transient Manager with expired/all deletion options
- Shortcode Finder with registration status
- Media Library Source Tracker with automatic tracking
- Admin interface with native WordPress styling
- Security features (nonces, capability checks, escaping)

## Support

For support, feature requests, or bug reports:

- Author: Jon Mather
- Website: [https://jonmather.au](https://jonmather.au)
- GitHub: [https://github.com/westcoastdigital/Simpli-WP-Optimser](https://github.com/westcoastdigital/Simpli-WP-Optimser)

## Credits

Developed by Jon Mather at SimpliWeb

## License

GPL v2 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.