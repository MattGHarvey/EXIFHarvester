# EXIF Harvester WordPress Plugin

A comprehensive WordPress plugin that automatically extracts and stores EXIF metadata from images when posts are saved or edited. Designed specifically for photography websites and blogs, it stores## Live Preview Metabox

The plugin includes a powerful metabox feature in the post editor:

### Features
- **Real-time Processing**: Extract EXIF data without saving the post
- **Manual Refresh**: Force re-processing of EXIF data with a single click
- **Instant Preview**: See extracted metadata immediately in the editor
- **Error Handling**: Clear feedback on processing status and any issues

### Usage
1. Open any post for editing
2. Look for the "EXIF Data" metabox in the sidebar
3. Click "Refresh EXIF Data" to process the post's images
4. View extracted metadata instantly
5. Save the post to store the metadata permanently

### Benefits
- **Quality Control**: Preview extracted data before publishing
- **Troubleshooting**: Test EXIF extraction on problematic images
- **Efficiency**: Re-process data after changing images without multiple saves
- **Validation**: Confirm weather and timezone data is being retrieved correctly

## Supported Image Formats

The plugin supports multiple image formats with intelligent metadata extraction:

### JPEG Images
- **EXIF Data**: Full support via PHP's `exif_read_data()` function
- **IPTC Data**: Location, city, state, and country information
- **Camera Settings**: ISO, aperture, shutter speed, focal length
- **GPS Coordinates**: Latitude, longitude, altitude

### WebP Images
- **XMP Metadata**: Full support for XMP-embedded metadata
- **EXIF-like Data**: Camera, lens, ISO, aperture, shutter speed, focal length
- **GPS Coordinates**: Complete GPS data extraction from XMP
- **IPTC Data**: Location, city, state, country from XMP metadata
- **Image Dimensions**: Width, height, aspect ratio, megapixels

**Note**: WebP support requires PHP 7.1+ with SimpleXML extension enabled.

## Troubleshooting

### EXIF Data Not Being Extracted

1. **Check Image Format**: 
   - JPEG images: Ensure they contain EXIF data
   - WebP images: Ensure they contain XMP metadata
   - PNG and other formats may not contain extractable metadata
2. **PHP Extensions**: 
   - For JPEG: Verify that the PHP EXIF extension is installed and enabled
   - For WebP: Verify that SimpleXML extension is enabled
3. **Image Source**: Make sure the image actually contains metadata (camera phones and professional cameras typically include metadata, but images downloaded from the web may have been stripped)
4. **Post Type**: Ensure the post type is enabled in the plugin settings
5. **Use Metabox**: Try the "Refresh EXIF Data" button in the post editor for real-time diagnosticsensive metadata in custom fields and provides advanced features like weather integration, timezone handling, and location management.

## Features

- **Automatic EXIF Extraction**: Processes EXIF metadata when posts are saved or edited
- **35+ Custom Fields**: Comprehensive metadata storage including camera settings, GPS coordinates, date/time components, weather data, and more
- **Weather Integration**: Automatically retrieves historical temperature and weather conditions using PirateWeather API
- **Timezone Intelligence**: Accurate timezone detection and GMT offset calculation using TimezoneDB API
- **User-Configurable API Keys**: Full control over external API usage for weather and timezone data with security warnings
- **Live Preview Metabox**: Real-time EXIF data extraction and refresh in post editor without saving
- **User-Manageable Databases**: Comprehensive recognition systems with admin interfaces for cameras, lenses, and location corrections
- **Smart Recognition**: Intelligent formatting for camera and lens models from major manufacturers
- **Location Correction System**: Fix truncated location names with user-manageable mappings
- **Hierarchical Place Taxonomy**: Automatic organization of locations in structured WordPress taxonomy
- **Enhanced AJAX System**: Secure, real-time processing with proper error handling and nonce verification
- **Batch Processing**: Process multiple posts at once through the admin interface
- **Flexible Configuration**: Choose which post types to process and customize behavior
- **Security Enhanced**: Proper nonce verification and capability checking throughout
- **Clean Uninstall**: Removes all plugin data when uninstalled (custom fields preserved by default)

## Supported Custom Fields

The plugin automatically populates the following custom fields when EXIF data is available:

### Camera & Technical Settings
- `camera` - Camera model (formatted for readability)
- `lens` - Lens information
- `fstop` - Aperture setting (e.g., "ƒ/2.8")
- `shutterspeed` - Shutter speed (e.g., "1/125s")
- `iso` - ISO sensitivity (e.g., "400 ISO")
- `focallength` - Focal length (e.g., "85mm")

### Image Properties
- `photo_width` - Image width in pixels
- `photo_height` - Image height in pixels
- `photo_dimensions` - Combined dimensions (e.g., "1920x1080")
- `photo_megapixels` - Calculated megapixels
- `photo_aspect_ratio` - Simplified ratio (e.g., "16:9")

### Date & Time Information
- `dateTimeOriginal` - Original capture date/time from EXIF
- `dateOriginal` - Date in Y-m-d format
- `yearOriginal` - Year (YYYY)
- `monthOriginal` - Month (MM)
- `monthNameOriginal` - Month name (e.g., "January")
- `dayOriginal` - Day (DD)
- `dayOfWeekOriginal` - Day of week (e.g., "Monday")
- `hourOriginal` - Hour (HH)
- `minuteOriginal` - Minute (MM)
- `timeOriginal` - Time in H:i format
- `timeOfDayContext` - Calculated context (Morning/Afternoon/Evening/Night)
- `unixTime` - Unix timestamp

### GPS & Location Data
- `GPS` - Combined GPS coordinates (lat,lon)
- `GPSLat` - Latitude
- `GPSLon` - Longitude
- `GPSAlt` - Altitude in meters
- `GPCode` - Google Plus Code
- `geoHash` - Geohash for location
- `location` - Location name from IPTC data
- `city` - City from IPTC data
- `state` - State/province from IPTC data
- `country` - Country from IPTC data
- `timeZone` - Time zone (requires TimezoneDB API)
- `gmtOffset` - GMT offset in hours (requires TimezoneDB API)

### Weather Data (Optional - Requires API Key)
- `temperature` - Temperature in Celsius at photo location/time
- `wXSummary` - Weather conditions summary (e.g., "Partly Cloudy")

### Content
- `caption` - Extracted from post content (cleaned)

## API Requirements

To get full functionality from EXIF Harvester, you'll need API keys for:

### PirateWeather API (Weather Data)
- **Purpose**: Retrieves historical weather conditions for photos with GPS coordinates
- **Free Tier**: 20,000 API calls per month
- **Sign Up**: [pirate-weather.apiable.io](https://pirate-weather.apiable.io/)
- **Required For**: Temperature and weather condition data

### TimezoneDB API (Timezone Data)
- **Purpose**: Provides accurate timezone information for GPS coordinates
- **Free Tier**: 1,000 API calls per month
- **Sign Up**: [timezonedb.com/api](https://timezonedb.com/api)
- **Required For**: Accurate timezone detection and GMT offset calculation

> **⚠️ Important**: Without API keys, the plugin will still extract EXIF data, but weather and timezone information will be inaccurate or unavailable.

## Installation

1. Upload the `exif-harvester` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > EXIF Harvester to configure the plugin

## Configuration

### Plugin Settings

Access the settings page at **Settings > EXIF Harvester** in your WordPress admin.

#### General Settings

- **Enabled Post Types**: Select which post types should have EXIF data automatically processed
- **Delete Existing Data on Update**: When enabled, existing EXIF metadata will be removed before processing new data on post updates
- **Process Featured Images**: Extract EXIF data from the post's featured image
- **Process Attached Images**: Extract EXIF data from images attached to the post

#### Weather Data Settings

- **Enable Weather Data**: Turn on weather data collection for photos with GPS coordinates
- **PirateWeather API Key**: Your API key from [PirateWeather](https://pirate-weather.apiable.io/) for weather data access
- **TimezoneDB API Key**: Your API key from [TimezoneDB](https://timezonedb.com/api) for accurate timezone detection

**Weather Data Requirements:**
- GPS coordinates must be present in EXIF data
- Date/time information must be available
- Valid PirateWeather API key must be configured for weather data
- TimezoneDB API key recommended for accurate timezone handling
- Weather data is retrieved based on photo location and timestamp with proper timezone conversion

## Usage

Once activated and configured, the plugin works automatically:

1. **Creating Posts**: When you create a new post with an image, EXIF data is automatically extracted and stored
2. **Updating Posts**: When you edit a post, existing EXIF data can optionally be refreshed
3. **Manual Processing**: Use the "Refresh EXIF Data" button in the post editor sidebar for real-time processing
4. **Accessing Data**: Use standard WordPress custom field functions to access the data:

```php
// Get camera information
$camera = get_post_meta($post_id, 'camera', true);

// Get GPS coordinates
$gps = get_post_meta($post_id, 'GPS', true);
$gps_parts = explode(',', $gps);
$latitude = $gps_parts[0];
$longitude = $gps_parts[1];

// Get photo dimensions
$width = get_post_meta($post_id, 'photo_width', true);
$height = get_post_meta($post_id, 'photo_height', true);

// Get weather data (if available)
$temperature = get_post_meta($post_id, 'temperature', true);
$weather_summary = get_post_meta($post_id, 'wXSummary', true);
```

## Template Integration

You can display EXIF data in your theme templates:

```php
<?php
$camera = get_post_meta(get_the_ID(), 'camera', true);
$lens = get_post_meta(get_the_ID(), 'lens', true);
$fstop = get_post_meta(get_the_ID(), 'fstop', true);
$shutterspeed = get_post_meta(get_the_ID(), 'shutterspeed', true);
$iso = get_post_meta(get_the_ID(), 'iso', true);

if ($camera || $lens): ?>
<div class="photo-exif">
    <h3>Photo Details</h3>
    <?php if ($camera): ?><p><strong>Camera:</strong> <?php echo esc_html($camera); ?></p><?php endif; ?>
    <?php if ($lens): ?><p><strong>Lens:</strong> <?php echo esc_html($lens); ?></p><?php endif; ?>
    <?php if ($fstop): ?><p><strong>Aperture:</strong> <?php echo esc_html($fstop); ?></p><?php endif; ?>
    <?php if ($shutterspeed): ?><p><strong>Shutter Speed:</strong> <?php echo esc_html($shutterspeed); ?></p><?php endif; ?>
    <?php if ($iso): ?><p><strong>ISO:</strong> <?php echo esc_html($iso); ?></p><?php endif; ?>
    
    <?php 
    // Display weather data if available
    $temperature = get_post_meta(get_the_ID(), 'temperature', true);
    $weather_summary = get_post_meta(get_the_ID(), 'wXSummary', true);
    if ($temperature || $weather_summary): ?>
        <h4>Weather Conditions</h4>
        <?php if ($weather_summary): ?><p><strong>Conditions:</strong> <?php echo esc_html($weather_summary); ?></p><?php endif; ?>
        <?php if ($temperature): ?><p><strong>Temperature:</strong> <?php echo esc_html($temperature); ?>°C (<?php echo esc_html(round($temperature * 9/5 + 32, 1)); ?>°F)</p><?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
```

## Image Processing Priority

The plugin looks for images in this order:

1. **Featured Image**: If "Process Featured Images" is enabled
2. **Attached Images**: If "Process Attached Images" is enabled (uses first attachment)
3. **Content Images**: Extracts first image found in post content as fallback

## Camera and Lens Recognition

The plugin includes a comprehensive, user-manageable database for intelligent camera and lens formatting:

**Default Database Includes (90+ mappings)**:
- **Camera Recognition (30+ models)**: Canon EOS series, Sony Alpha series, Fujifilm X-series, iPhone models (5s through 15 Pro Max), Google Pixel, Samsung Galaxy, DJI drones, Olympus cameras, and more
- **Lens Recognition (60+ models)**: Canon EF/EF-S series, Sony FE series, Fujifilm XF series, Panasonic Lumix G series, Tamron E-mount series, Sigma DC series, iPhone camera modules, specialty lenses from Leica, Samyang, Viltrox, TTArtisan, and more

**User Management Interface**:
- **Add/Edit/Delete Mappings**: Full CRUD operations through WordPress admin interface
- **Real-time Updates**: Changes take effect immediately for new EXIF processing
- **Custom Formatting**: Define how raw EXIF camera/lens names appear to users
- **Bulk Management**: Import/export capabilities for large mapping sets
- **Default Population**: All mappings automatically created on plugin activation

**Admin Interface**:
- Navigate to **EXIF Harvester > Camera Mappings** to manage camera recognition
- Navigate to **EXIF Harvester > Lens Mappings** to manage lens recognition
- Navigate to **EXIF Harvester > Location Corrections** to manage location name fixes
- Add custom mappings for your specific equipment
- Edit existing mappings to match your preferred formatting
- Delete unnecessary mappings to streamline the database

**Benefits of User-Manageable System**:
- **Customization**: Tailor camera/lens names to match your site's style and terminology
- **Completeness**: Add support for new or niche equipment not included in defaults
- **Future-Proof**: Easily add new camera/lens models as they're released
- **Branding**: Maintain consistent naming conventions across your photography content
- **Efficiency**: Remove unused mappings to optimize database queries

**Quick Navigation**:
- **Main Settings**: EXIF Harvester → Settings
- **Camera Management**: EXIF Harvester → Camera Mappings  
- **Lens Management**: EXIF Harvester → Lens Mappings
- **Location Corrections**: EXIF Harvester → Location Corrections

## Troubleshooting

### EXIF Data Not Being Extracted

1. **Check Image Format**: Ensure images are JPEG format (PNG and other formats may not contain EXIF data)
2. **PHP EXIF Extension**: Verify that PHP's EXIF extension is installed and enabled
3. **File Permissions**: Ensure WordPress can read the image files
4. **Post Type**: Verify the post type is enabled in plugin settings

### Missing Custom Fields

1. **Check Post Status**: EXIF data is only processed for published posts
2. **User Permissions**: Ensure the user has permission to edit the post
3. **Image Availability**: Confirm there's an image attached to the post

### Performance Considerations

- EXIF processing occurs when posts are saved, not on every page load
- Large images may take longer to process
- Consider using image optimization plugins to reduce file sizes

## Hooks and Filters

### Actions

- `exif_harvester_before_processing` - Fired before EXIF processing begins
- `exif_harvester_after_processing` - Fired after EXIF processing completes

### Filters

- `exif_harvester_custom_fields` - Modify the list of custom fields to process
- `exif_harvester_camera_name` - Filter camera name formatting
- `exif_harvester_lens_name` - Filter lens name formatting

Example usage:

```php
// Add custom processing after EXIF extraction
add_action('exif_harvester_after_processing', function($post_id, $exif_data) {
    // Your custom processing here
    error_log('EXIF processed for post: ' . $post_id);
}, 10, 2);

// Customize camera name formatting
add_filter('exif_harvester_camera_name', function($camera_name, $original_model) {
    if ($original_model === 'CUSTOM_MODEL') {
        return 'My Custom Camera Name';
    }
    return $camera_name;
}, 10, 2);
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- PHP EXIF extension enabled
- Images in JPEG format (for full EXIF data)

## License

This plugin is released under the GPL v2 or later license.

## Support

For support and questions, please contact the plugin author or refer to the plugin's documentation.

## Changelog

### Version 1.0.0
- Initial release
- Automatic EXIF metadata extraction on post save/edit
- Support for 34 custom fields
- Weather data integration with PirateWeather API
- User-manageable camera and lens database (90+ default mappings)
- Admin interface for managing camera/lens recognition
- Real-time CRUD operations for camera/lens mappings
- Support for major brands: Canon, Sony, Fujifilm, Panasonic, Apple, Google, Samsung, DJI, Olympus
- Intelligent lens recognition including specialty brands: Tamron, Sigma, Leica, Samyang, Viltrox, TTArtisan
- Bulk processing capabilities
- Comprehensive admin interface with settings
- Database table management and cleanup
- Complete uninstall cleanup