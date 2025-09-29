# EXIF Harvester Installation Guide

## Quick Start

1. **Upload the Plugin**
   - Download/copy the `exif-harvester` folder to your WordPress `/wp-content/plugins/` directory
   - Or upload the plugin zip file through WordPress Admin > Plugins > Add New > Upload Plugin

2. **Activate the Plugin**
   - Go to WordPress Admin > Plugins
   - Find "EXIF Harvester" in the list
   - Click "Activate"

3. **Configure Settings**
   - Go to WordPress Admin > Settings > EXIF Harvester
   - Select which post types should have EXIF data processed
   - Configure other options as needed
   - Click "Save Changes"

4. **Test the Plugin**
   - Create or edit a post with an image
   - Save the post
   - Check if custom fields were created with EXIF data

## Detailed Installation Steps

### Step 1: Upload Plugin Files

#### Method A: Manual Upload via FTP
```bash
# Upload the entire exif-harvester folder to:
/wp-content/plugins/exif-harvester/
```

#### Method B: WordPress Admin Upload
1. Zip the `exif-harvester` folder
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin"
4. Choose your zip file and click "Install Now"

### Step 2: Verify Requirements

Before activation, ensure your server meets these requirements:

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher  
- **PHP EXIF Extension**: Must be enabled

To check PHP EXIF extension:
```php
<?php
// Add this to a temporary PHP file and run it
if (extension_loaded('exif')) {
    echo "✅ EXIF extension is loaded";
} else {
    echo "❌ EXIF extension is not loaded";
}
?>
```

### Step 3: Activate Plugin

1. Navigate to **Plugins** in your WordPress admin
2. Find **EXIF Harvester** in the plugin list
3. Click **Activate**
4. You should see a success notice

### Step 4: Configure Plugin Settings

1. Go to **Settings > EXIF Harvester**
2. Configure these settings:

   **Enabled Post Types**
   - ✅ Select "Posts" (default)
   - ✅ Select any other post types you want to process
   
   **Delete Existing Data on Update**
   - ✅ Check this if you want to refresh EXIF data when posts are updated
   - ❌ Leave unchecked to preserve existing data
   
   **Process Featured Images**
   - ✅ Check this to extract EXIF from featured images (recommended)
   
   **Process Attached Images** 
   - ✅ Check this to extract EXIF from attached images (recommended)

3. Click **Save Changes**

### Step 5: Test the Installation

#### Method A: Using the Test Script
1. Add the contents of `test-functions.php` to your theme's `functions.php` file temporarily
2. Visit any page on your site with `?exif_test=1` added to the URL
3. You'll see a test report overlay
4. Remove the test code after testing

#### Method B: Manual Testing
1. Create a new post or edit an existing one
2. Add an image (as featured image or upload to post)
3. Ensure the image is a JPEG with EXIF data
4. Save the post
5. Check for custom fields in the post edit screen (under "Custom Fields" meta box)

### Step 6: Verify Custom Fields

After saving a post with an image, you should see these custom fields populated:

**Camera Settings:**
- `camera` - Camera model
- `lens` - Lens information  
- `fstop` - Aperture (e.g., "ƒ/2.8")
- `shutterspeed` - Shutter speed (e.g., "1/125s")
- `iso` - ISO setting (e.g., "400 ISO")
- `focallength` - Focal length (e.g., "85mm")

**Image Properties:**
- `photo_width` - Width in pixels
- `photo_height` - Height in pixels
- `photo_dimensions` - Combined (e.g., "1920x1080")
- `photo_megapixels` - Calculated megapixels
- `photo_aspect_ratio` - Ratio (e.g., "16:9")

**GPS Data (if available):**
- `GPS` - Coordinates (lat,lon)
- `GPSLat` - Latitude
- `GPSLon` - Longitude
- `GPSAlt` - Altitude

**Date/Time:**
- `dateTimeOriginal` - Original timestamp
- `yearOriginal`, `monthOriginal`, `dayOriginal` - Date components
- `timeOriginal` - Time (HH:MM)
- `timeOfDayContext` - Morning/Afternoon/Evening/Night

## Troubleshooting Installation

### Plugin Won't Activate
- Check WordPress and PHP version requirements
- Verify file permissions (755 for directories, 644 for files)
- Check for PHP errors in your error log

### No EXIF Data Extracted
- Ensure images are JPEG format (PNG/GIF don't contain EXIF)
- Verify PHP EXIF extension is loaded
- Check that images actually contain EXIF data
- Confirm the post type is enabled in settings

### Custom Fields Not Showing
- Enable "Custom Fields" in Screen Options on post edit screen
- Try saving the post after adding an image
- Check if the image file exists and is accessible

### Permission Issues
- Ensure WordPress can read uploaded image files
- Check file permissions on uploads directory
- Verify user has permission to edit posts

## Next Steps

Once installed and working:

1. **Customize Display**: Add EXIF data display to your theme templates
2. **Bulk Processing**: For existing posts, you may need to re-save them to process EXIF data
3. **Backup**: Consider backing up your database before bulk operations
4. **Monitor**: Check error logs for any issues during processing

## Getting Help

If you encounter issues:

1. Check the WordPress debug log for errors
2. Verify all requirements are met
3. Test with a simple JPEG image with known EXIF data
4. Try deactivating other plugins to check for conflicts

## Uninstalling

To completely remove the plugin:

1. Deactivate the plugin
2. Delete the plugin files
3. Optionally remove custom field data (not done automatically)
4. Remove any custom code you added to your theme