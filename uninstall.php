<?php
/**
 * Uninstall script for EXIF Harvester plugin
 * 
 * This file is executed when the plugin is uninstalled (deleted) from WordPress.
 * It cleans up plugin settings, database tables, and optionally removes custom field data.
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete plugin settings
delete_option('exif_harvester_settings');

// Clean up transients
delete_transient('exif_harvester_activation_notice');

// Drop custom database tables
$camera_table = $wpdb->prefix . 'exif_harvester_cameras';
$lens_table = $wpdb->prefix . 'exif_harvester_lenses';

$wpdb->query("DROP TABLE IF EXISTS $camera_table");
$wpdb->query("DROP TABLE IF EXISTS $lens_table");

// Note: We don't automatically delete custom field data as it may be valuable to users
// Users can manually remove custom fields if desired through the WordPress admin
// or by running custom SQL queries if they want to completely remove all EXIF data

// If you want to completely remove all EXIF custom fields, uncomment the following code:
/*
// List of custom fields to remove
$custom_fields = array(
    'camera', 'caption', 'dateOriginal', 'dateTimeOriginal', 'dayOfWeekOriginal',
    'dayOriginal', 'focallength', 'fstop', 'gmtOffset', 'GPS', 'GPCode',
    'GPSAlt', 'GPSLat', 'GPSLon', 'hourOriginal', 'iso', 'lens',
    'minuteOriginal', 'monthNameOriginal', 'monthOriginal', 'photo_aspect_ratio',
    'photo_dimensions', 'photo_height', 'photo_megapixels', 'photo_width',
    'shutterspeed', 'temperature', 'timeOfDayContext', 'timeOriginal', 'timeZone',
    'unixTime', 'wXSummary', 'yearOriginal'
);

// Remove all custom fields created by the plugin
foreach ($custom_fields as $field) {
    $wpdb->delete(
        $wpdb->postmeta,
        array('meta_key' => $field),
        array('%s')
    );
}
*/