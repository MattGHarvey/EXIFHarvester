# WebP Support Implementation

## Overview

EXIF Harvester has been updated to support WebP image format in addition to JPEG. WebP files store metadata in XMP (Extensible Metadata Platform) format rather than traditional EXIF/IPTC, requiring different extraction methods.

## What Changed

### Version Update
- **Version**: 1.2.0 → 1.3.0
- **Description**: Updated to mention support for both JPEG (EXIF/IPTC) and WebP (XMP) formats

### New Functions Added

#### 1. `exif_harvester_extract_xmp_from_webp($filepath)`
- Extracts XMP metadata from WebP files
- Returns EXIF-compatible array structure
- Main entry point for WebP metadata extraction

#### 2. `exif_harvester_extract_xmp_from_binary($contents)`
- Extracts raw XMP data from binary file contents
- Handles multiple XMP packet marker formats
- Returns XMP data as XML string

#### 3. `exif_harvester_parse_xmp_to_exif($xmp_data)`
- Parses XMP XML data using SimpleXML
- Converts XMP namespaces to EXIF-like array structure
- Supports multiple XMP namespaces:
  - `exif`: Camera settings (ISO, focal length, aperture, shutter speed)
  - `tiff`: Camera make and model
  - `aux`: Lens information
  - `photoshop`: Location data (city, state, country)
  - `Iptc4xmpCore`: Location details
  - `dc`: Description/caption

#### 4. `exif_harvester_parse_gps_coordinate($coord_string)`
- Converts GPS coordinate strings from XMP format to EXIF format
- Handles comma-separated and decimal degree formats
- Returns array in EXIF GPS format [degrees/1, minutes/1, seconds/100]

### Modified Functions

#### Main Processing (`exif-harvester.php`)
- **File detection**: Added WebP file type detection using `wp_check_filetype()` and file extension check
- **Conditional extraction**: Routes to XMP extraction for WebP, standard EXIF for JPEG
- **Logging**: Enhanced logging to show which format is being processed

#### IPTC Data Extraction (`exif-harvester-functions.php`)
Updated all IPTC extraction functions to support WebP:

1. **`exif_harvester_get_location($path)`**
   - Checks for WebP format
   - Extracts location from XMP if WebP
   - Falls back to IPTC parsing for JPEG

2. **`exif_harvester_get_city($path)`**
   - WebP: Extracts from `IPTC_City` in XMP
   - JPEG: Uses standard IPTC parsing

3. **`exif_harvester_get_state($path)`**
   - WebP: Extracts from `IPTC_State` in XMP
   - JPEG: Uses standard IPTC parsing

4. **`exif_harvester_get_country($path)`**
   - WebP: Extracts from `IPTC_Country` in XMP
   - JPEG: Uses standard IPTC parsing

5. **`exif_harvester_process_photo_dimensions($post_id, $fullsize_path)`**
   - Updated documentation to note WebP support
   - Already compatible (getimagesize() supports WebP in PHP 7.1+)

## Metadata Mapping

### Camera Information
| XMP Field | EXIF Equivalent | Custom Field |
|-----------|-----------------|--------------|
| `tiff:Make` | `Make` | `camera` (combined) |
| `tiff:Model` | `Model` | `camera` (combined) |
| `aux:Lens` | `UndefinedTag:0xA434` | `lens` |

### Camera Settings
| XMP Field | EXIF Equivalent | Custom Field |
|-----------|-----------------|--------------|
| `exif:ISOSpeedRatings` | `ISOSpeedRatings` | `iso` |
| `exif:FocalLength` | `FocalLength` | `focallength` |
| `exif:FNumber` | `FNumber`, `ApertureValue` | `fstop` |
| `exif:ExposureTime` | `ExposureTime`, `ShutterSpeedValue` | `shutterspeed` |
| `exif:DateTimeOriginal` | `DateTimeOriginal` | Various date/time fields |

### GPS Information
| XMP Field | EXIF Equivalent | Custom Field |
|-----------|-----------------|--------------|
| `exif:GPSLatitude` | `GPSLatitude` | `GPSLat`, `GPS` |
| `exif:GPSLatitudeRef` | `GPSLatitudeRef` | Used for calculation |
| `exif:GPSLongitude` | `GPSLongitude` | `GPSLon`, `GPS` |
| `exif:GPSLongitudeRef` | `GPSLongitudeRef` | Used for calculation |
| `exif:GPSAltitude` | `GPSAltitude` | `GPSAlt` |
| `exif:GPSAltitudeRef` | `GPSAltitudeRef` | Used for calculation |

### Location Information
| XMP Field | EXIF Equivalent | Custom Field |
|-----------|-----------------|--------------|
| `Iptc4xmpCore:Location` | IPTC `2#092` | `location` |
| `photoshop:City` | IPTC `2#090` | `city` |
| `photoshop:State` | IPTC `2#095` | `state` |
| `photoshop:Country` | IPTC `2#101` | `country` |

### Image Description
| XMP Field | EXIF Equivalent | Custom Field |
|-----------|-----------------|--------------|
| `dc:description` | `ImageDescription` | `caption` |

## Requirements

### PHP Version
- **Minimum**: PHP 7.4 (existing requirement)
- **Recommended**: PHP 7.1+ for WebP support in getimagesize()

### PHP Extensions
- **EXIF Extension**: Required for JPEG files (existing requirement)
- **SimpleXML Extension**: Required for WebP XMP parsing (new requirement)
- **Standard**: Usually enabled by default in most PHP installations

### WordPress Version
- **Minimum**: WordPress 5.0 (existing requirement)

## Testing Recommendations

### Test Cases

1. **JPEG Images**
   - Verify existing JPEG functionality still works
   - Test EXIF extraction
   - Test IPTC location data
   - Test GPS coordinates

2. **WebP Images**
   - Test WebP with XMP metadata
   - Verify camera information extraction
   - Verify GPS coordinate extraction
   - Verify location data (city, state, country)
   - Test dimension calculations

3. **Mixed Content**
   - Posts with both JPEG and WebP images
   - Verify correct format detection
   - Verify metadata extraction for both formats

4. **Edge Cases**
   - WebP without XMP metadata
   - JPEG without EXIF data
   - Images with partial metadata
   - Files with corrupted metadata

### Manual Testing Steps

1. **Upload a WebP image with XMP metadata**
   - Create or edit a post
   - Insert a WebP image
   - Save the post
   - Check that EXIF fields are populated

2. **Use the Metabox**
   - Open the post editor
   - Click "Refresh EXIF Data"
   - Verify metadata appears in the preview
   - Check server logs for WebP detection messages

3. **Verify All Metadata Types**
   - Camera and lens information
   - Camera settings (ISO, aperture, shutter, focal length)
   - GPS coordinates and location data
   - Date/time information
   - Image dimensions

## Backward Compatibility

- **100% backward compatible** with existing JPEG functionality
- No changes to database schema
- No changes to custom field names
- No changes to user interface
- Existing JPEG images will continue to work exactly as before

## Known Limitations

1. **XMP in JPEG**: The plugin does not extract XMP from JPEG files; it only uses standard EXIF/IPTC
2. **File Access**: WebP metadata extraction requires local file access (cannot extract from URLs)
3. **XMP Variations**: Some proprietary XMP namespaces may not be supported
4. **PHP SimpleXML**: Requires SimpleXML extension (usually enabled by default)

## Future Enhancements

Potential improvements for future versions:

1. Support XMP extraction from JPEG files
2. Support for additional XMP namespaces
3. Support for PNG with XMP metadata
4. Support for HEIC/HEIF images
5. XMP writing capabilities (not just reading)

## Troubleshooting

### WebP Images Not Being Processed

1. **Check PHP Version**: Ensure PHP 7.1+ for full WebP support
2. **Check SimpleXML**: Verify SimpleXML extension is enabled
   ```php
   <?php phpinfo(); ?>
   // Look for SimpleXML section
   ```
3. **Check File Type**: Confirm the file is actually WebP format
4. **Check XMP Data**: Verify the WebP file contains XMP metadata
5. **Check Logs**: Look for "WebP file detected" messages in error logs

### No Metadata Extracted from WebP

1. The WebP file may not contain XMP metadata
2. XMP metadata may be in a non-standard format
3. File may be corrupted
4. SimpleXML may have issues parsing the XMP

### Debugging

Enable WordPress debug logging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Look for messages like:
- "WebP file detected, extracting XMP metadata"
- "EXIF/XMP data from file path: found (format: WebP)"

## Resources

- [XMP Specification](https://www.adobe.com/devnet/xmp.html)
- [WebP Format Documentation](https://developers.google.com/speed/webp)
- [EXIF Specification](https://www.exif.org/)
- [IPTC Photo Metadata](https://www.iptc.org/standards/photo-metadata/)
