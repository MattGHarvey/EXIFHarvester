<?php
/**
 * EXIF Harvester Helper Functions
 * 
 * Contains utility functions for EXIF data processing, camera/lens formatting,
 * GPS calculations, date/time processing, and other helper methods adapted
 * from the original content-single.php implementation.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extract metadata from WebP files (both EXIF and XMP)
 * 
 * WebP files can contain both EXIF data in a separate chunk and XMP metadata.
 * This function extracts both and merges them into a single array.
 * 
 * @param string $filepath Path to the WebP file
 * @return array|false Array of metadata or false on failure
 */
function exif_harvester_extract_webp_metadata($filepath) {
    if (!file_exists($filepath)) {
        error_log('EXIF Harvester WebP: File does not exist: ' . $filepath);
        return false;
    }
    
    $metadata = array();
    
    // First, try to extract EXIF data from WebP EXIF chunk
    $exif_data = exif_harvester_extract_exif_from_webp($filepath);
    if ($exif_data && is_array($exif_data)) {
        $metadata = array_merge($metadata, $exif_data);
        error_log('EXIF Harvester WebP: Found ' . count($exif_data) . ' EXIF fields');
    }
    
    // Then, extract XMP data (for IPTC location info)
    $xmp_data = exif_harvester_extract_xmp_from_webp($filepath);
    if ($xmp_data && is_array($xmp_data)) {
        // XMP data takes precedence for IPTC fields
        $metadata = array_merge($metadata, $xmp_data);
        error_log('EXIF Harvester WebP: Found ' . count($xmp_data) . ' XMP fields');
    }
    
    if (empty($metadata)) {
        error_log('EXIF Harvester WebP: No metadata found');
        return false;
    }
    
    error_log('EXIF Harvester WebP: Total metadata fields: ' . count($metadata));
    return $metadata;
}

/**
 * Extract EXIF data from WebP EXIF chunk
 * 
 * WebP files can contain a separate EXIF chunk with camera metadata.
 * This function extracts that data using exif_read_data on a temporary file.
 * 
 * @param string $filepath Path to the WebP file
 * @return array|false Array of EXIF data or false on failure
 */
function exif_harvester_extract_exif_from_webp($filepath) {
    if (!file_exists($filepath)) {
        return false;
    }
    
    // Read the file contents
    $contents = file_get_contents($filepath);
    if ($contents === false) {
        error_log('EXIF Harvester WebP EXIF: Failed to read file');
        return false;
    }
    
    // Look for EXIF chunk in WebP
    // WebP format: RIFF....WEBP then chunks like VP8, VP8L, VP8X, EXIF, XMP
    $exif_pos = strpos($contents, 'EXIF');
    
    if ($exif_pos === false) {
        error_log('EXIF Harvester WebP EXIF: No EXIF chunk found');
        return false;
    }
    
    // Extract EXIF chunk (skip "EXIF" marker and size bytes)
    // The EXIF data starts after the chunk ID and size (8 bytes total)
    $exif_start = $exif_pos + 8;
    
    // Get chunk size (4 bytes before EXIF marker)
    $size_bytes = substr($contents, $exif_pos + 4, 4);
    $exif_size = unpack('V', $size_bytes)[1]; // Little-endian unsigned long
    
    // Extract raw EXIF data
    $exif_data = substr($contents, $exif_start, $exif_size - 4);
    
    // Create a temporary file with TIFF header + EXIF data
    // EXIF data in WebP starts with byte offset, we need to add TIFF header
    $temp_file = tempnam(sys_get_temp_dir(), 'webp_exif_');
    if ($temp_file === false) {
        error_log('EXIF Harvester WebP EXIF: Failed to create temp file');
        return false;
    }
    
    // Write TIFF header + EXIF data
    file_put_contents($temp_file, $exif_data);
    
    // Read EXIF data using PHP's exif_read_data
    $exif_array = @exif_read_data($temp_file, null, true);
    
    // Clean up temp file
    unlink($temp_file);
    
    if (!$exif_array) {
        error_log('EXIF Harvester WebP EXIF: Failed to parse EXIF data');
        return false;
    }
    
    // Flatten the EXIF array (it's multi-dimensional)
    $flattened = array();
    foreach ($exif_array as $section => $data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $flattened[$key] = $value;
            }
        }
    }
    
    error_log('EXIF Harvester WebP EXIF: Extracted ' . count($flattened) . ' EXIF fields');
    return $flattened;
}

/**
 * Extract XMP metadata from WebP files
 * 
 * WebP files can contain XMP metadata which includes EXIF-like information.
 * This function extracts and parses XMP data, converting it to a format
 * compatible with standard EXIF arrays.
 * 
 * @param string $filepath Path to the WebP file
 * @return array|false Array of EXIF-like data or false on failure
 */
function exif_harvester_extract_xmp_from_webp($filepath) {
    if (!file_exists($filepath)) {
        error_log('EXIF Harvester XMP: File does not exist: ' . $filepath);
        return false;
    }
    
    // Read the file contents
    $contents = file_get_contents($filepath);
    if ($contents === false) {
        error_log('EXIF Harvester XMP: Failed to read file: ' . $filepath);
        return false;
    }
    
    error_log('EXIF Harvester XMP: File read successfully, size: ' . strlen($contents) . ' bytes');
    
    // Look for XMP metadata in the WebP file
    // XMP data is typically stored in a 'XMP ' chunk
    $xmp_data = exif_harvester_extract_xmp_from_binary($contents);
    
    if (!$xmp_data) {
        error_log('EXIF Harvester XMP: No XMP data found in WebP file');
        return false;
    }
    
    error_log('EXIF Harvester XMP: XMP data extracted, length: ' . strlen($xmp_data) . ' bytes');
    
    // Parse XMP and convert to EXIF-like array
    $result = exif_harvester_parse_xmp_to_exif($xmp_data);
    
    if ($result && is_array($result)) {
        error_log('EXIF Harvester XMP: Successfully parsed ' . count($result) . ' metadata fields');
    } else {
        error_log('EXIF Harvester XMP: Failed to parse XMP data or no fields found');
    }
    
    return $result;
}

/**
 * Extract XMP data from binary file contents
 * 
 * @param string $contents Binary file contents
 * @return string|false XMP data as XML string or false if not found
 */
function exif_harvester_extract_xmp_from_binary($contents) {
    // Look for XMP packet markers
    $xmp_start = strpos($contents, '<x:xmpmeta');
    $xmp_end = strpos($contents, '</x:xmpmeta>');
    
    if ($xmp_start === false || $xmp_end === false) {
        // Try alternative XMP start marker
        $xmp_start = strpos($contents, '<?xpacket');
        if ($xmp_start !== false) {
            $xmp_end = strpos($contents, '<?xpacket end=');
            if ($xmp_end !== false) {
                $xmp_end = strpos($contents, '?>', $xmp_end) + 2;
            }
        }
    } else {
        $xmp_end += strlen('</x:xmpmeta>');
    }
    
    if ($xmp_start === false || $xmp_end === false || $xmp_end <= $xmp_start) {
        return false;
    }
    
    return substr($contents, $xmp_start, $xmp_end - $xmp_start);
}

/**
 * Parse XMP XML data and convert to EXIF-like array
 * 
 * @param string $xmp_data XMP data as XML string
 * @return array EXIF-like array with metadata
 */
function exif_harvester_parse_xmp_to_exif($xmp_data) {
    // Suppress XML parsing errors
    $use_errors = libxml_use_internal_errors(true);
    
    $xml = simplexml_load_string($xmp_data);
    if ($xml === false) {
        error_log('EXIF Harvester XMP: Failed to parse XMP as XML');
        libxml_use_internal_errors($use_errors);
        return false;
    }
    
    // Register XMP namespaces
    $namespaces = array(
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'exif' => 'http://ns.adobe.com/exif/1.0/',
        'tiff' => 'http://ns.adobe.com/tiff/1.0/',
        'xmp' => 'http://ns.adobe.com/xap/1.0/',
        'aux' => 'http://ns.adobe.com/exif/1.0/aux/',
        'photoshop' => 'http://ns.adobe.com/photoshop/1.0/',
        'Iptc4xmpCore' => 'http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/',
        'dc' => 'http://purl.org/dc/elements/1.1/'
    );
    
    foreach ($namespaces as $prefix => $uri) {
        $xml->registerXPathNamespace($prefix, $uri);
    }
    
    $exif_array = array();
    
    // Get the rdf:Description element - this is where attributes are stored
    $descriptions = $xml->xpath('//rdf:Description');
    if (empty($descriptions)) {
        error_log('EXIF Harvester XMP: No rdf:Description found in XMP');
        libxml_use_internal_errors($use_errors);
        return false;
    }
    
    // Get the first Description element and extract attributes
    $desc = $descriptions[0];
    
    // Camera Make
    $tiff_attrs = $desc->attributes($namespaces['tiff']);
    if (isset($tiff_attrs['Make'])) {
        $exif_array['Make'] = (string)$tiff_attrs['Make'];
    }
    
    // Camera Model
    if (isset($tiff_attrs['Model'])) {
        $exif_array['Model'] = (string)$tiff_attrs['Model'];
    }
    
    // Lens Model
    $aux_attrs = $desc->attributes($namespaces['aux']);
    if (isset($aux_attrs['Lens'])) {
        $exif_array['UndefinedTag:0xA434'] = (string)$aux_attrs['Lens'];
    }
    
    // EXIF data
    $exif_attrs = $desc->attributes($namespaces['exif']);
    
    // ISO
    if (isset($exif_attrs['ISO'])) {
        $exif_array['ISOSpeedRatings'] = (string)$exif_attrs['ISO'];
    } elseif (isset($exif_attrs['ISOSpeedRatings'])) {
        $exif_array['ISOSpeedRatings'] = (string)$exif_attrs['ISOSpeedRatings'];
    }
    
    // Focal Length
    if (isset($exif_attrs['FocalLength'])) {
        $exif_array['FocalLength'] = (string)$exif_attrs['FocalLength'];
    }
    
    // Aperture (F-Number)
    if (isset($exif_attrs['FNumber'])) {
        $exif_array['FNumber'] = (string)$exif_attrs['FNumber'];
        $exif_array['ApertureValue'] = (string)$exif_attrs['FNumber'];
    } elseif (isset($exif_attrs['ApertureValue'])) {
        $exif_array['ApertureValue'] = (string)$exif_attrs['ApertureValue'];
    }
    
    // Shutter Speed
    if (isset($exif_attrs['ExposureTime'])) {
        $exif_array['ExposureTime'] = (string)$exif_attrs['ExposureTime'];
        $exif_array['ShutterSpeedValue'] = (string)$exif_attrs['ExposureTime'];
    } elseif (isset($exif_attrs['ShutterSpeedValue'])) {
        $exif_array['ShutterSpeedValue'] = (string)$exif_attrs['ShutterSpeedValue'];
    }
    
    // Date/Time Original
    if (isset($exif_attrs['DateTimeOriginal'])) {
        $exif_array['DateTimeOriginal'] = (string)$exif_attrs['DateTimeOriginal'];
    }
    
    // GPS Data
    if (isset($exif_attrs['GPSLatitude'])) {
        $lat_string = (string)$exif_attrs['GPSLatitude'];
        $exif_array['GPSLatitude'] = exif_harvester_parse_gps_coordinate($lat_string);
    }
    if (isset($exif_attrs['GPSLatitudeRef'])) {
        $exif_array['GPSLatitudeRef'] = (string)$exif_attrs['GPSLatitudeRef'];
    }
    if (isset($exif_attrs['GPSLongitude'])) {
        $lon_string = (string)$exif_attrs['GPSLongitude'];
        $exif_array['GPSLongitude'] = exif_harvester_parse_gps_coordinate($lon_string);
    }
    if (isset($exif_attrs['GPSLongitudeRef'])) {
        $exif_array['GPSLongitudeRef'] = (string)$exif_attrs['GPSLongitudeRef'];
    }
    if (isset($exif_attrs['GPSAltitude'])) {
        $exif_array['GPSAltitude'] = (string)$exif_attrs['GPSAltitude'];
    }
    if (isset($exif_attrs['GPSAltitudeRef'])) {
        $exif_array['GPSAltitudeRef'] = (string)$exif_attrs['GPSAltitudeRef'];
    }
    
    // IPTC Location data from photoshop namespace
    $ps_attrs = $desc->attributes($namespaces['photoshop']);
    if (isset($ps_attrs['City'])) {
        $exif_array['IPTC_City'] = (string)$ps_attrs['City'];
    }
    if (isset($ps_attrs['State'])) {
        $exif_array['IPTC_State'] = (string)$ps_attrs['State'];
    }
    if (isset($ps_attrs['Country'])) {
        $exif_array['IPTC_Country'] = (string)$ps_attrs['Country'];
    }
    
    // IPTC Location from Iptc4xmpCore
    $iptc_attrs = $desc->attributes($namespaces['Iptc4xmpCore']);
    if (isset($iptc_attrs['Location'])) {
        $exif_array['IPTC_Location'] = (string)$iptc_attrs['Location'];
    }
    
    // Description/Caption - this is often in a child element
    $description = $xml->xpath('//dc:description/rdf:Alt/rdf:li');
    if (!empty($description)) {
        $exif_array['ImageDescription'] = (string)$description[0];
    }
    
    libxml_use_internal_errors($use_errors);
    
    return !empty($exif_array) ? $exif_array : false;
}

/**
 * Parse GPS coordinate string to EXIF format array
 * 
 * @param string $coord_string GPS coordinate string (e.g., "34,7.5N" or "118,15.2W")
 * @return array GPS coordinate in EXIF format [degrees, minutes, seconds]
 */
function exif_harvester_parse_gps_coordinate($coord_string) {
    // Remove any reference indicators (N, S, E, W)
    $coord_string = preg_replace('/[NSEW]/i', '', $coord_string);
    
    // Handle comma-separated decimal degrees
    if (strpos($coord_string, ',') !== false) {
        $parts = explode(',', $coord_string);
        if (count($parts) >= 2) {
            $degrees = floatval($parts[0]);
            $minutes = floatval($parts[1]);
            $seconds = 0;
            if (count($parts) >= 3) {
                $seconds = floatval($parts[2]);
            }
            return array($degrees . '/1', $minutes . '/1', $seconds . '/1');
        }
    }
    
    // Handle decimal degrees
    if (is_numeric($coord_string)) {
        $decimal = floatval($coord_string);
        $degrees = floor(abs($decimal));
        $minutes = floor((abs($decimal) - $degrees) * 60);
        $seconds = ((abs($decimal) - $degrees - $minutes / 60) * 3600);
        return array($degrees . '/1', $minutes . '/1', round($seconds * 100) . '/100');
    }
    
    // Return as-is if already in proper format
    return $coord_string;
}

/**
 * Helper function to find greatest common divisor for aspect ratio calculation
 */
if (!function_exists('gcd')) {
    function gcd($a, $b) {
        while ($b != 0) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }
        return $a;
    }
}

/**
 * Format camera model names for better display
 */
function exif_harvester_pretty_print_camera($model) {
    global $wpdb;
    
    if (empty($model)) {
        return 'Camera Information Not Available';
    }
    
    $camera_table = $wpdb->prefix . 'exif_harvester_cameras';
    
    $pretty_name = $wpdb->get_var($wpdb->prepare(
        "SELECT pretty_name FROM $camera_table WHERE raw_name = %s",
        $model
    ));
    
    if ($pretty_name) {
        // Decode HTML entities in camera names
        return html_entity_decode($pretty_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // If not found in database, return original or default message
    return $model ?: 'Camera Information Not Available';
}

/**
 * Format lens information for better display
 */
function exif_harvester_pretty_print_lens($lens) {
    global $wpdb;
    
    if (empty($lens)) {
        return 'Lens Information Not Available';
    }
    
    $lens_table = $wpdb->prefix . 'exif_harvester_lenses';
    
    $pretty_name = $wpdb->get_var($wpdb->prepare(
        "SELECT pretty_name FROM $lens_table WHERE raw_name = %s",
        $lens
    ));
    
    if ($pretty_name) {
        // Decode HTML entities in lens names
        return html_entity_decode($pretty_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // If not found in database, return original or default message
    return $lens ?: 'Lens Information Not Available';
}

/**
 * Convert EXIF fraction to decimal
 */
function exif_harvester_convert_to_decimal($fraction) {
    if (strpos($fraction, '/') !== false) {
        $parts = explode('/', $fraction);
        if (count($parts) === 2 && $parts[1] != 0) {
            return $parts[0] / $parts[1];
        }
    }
    return (float) $fraction;
}

/**
 * Get float value from EXIF data
 */
function exif_harvester_exif_get_float($value) {
    $pos = strpos($value, '/');
    if ($pos === false) {
        return (float) $value;
    }
    $a = (float) substr($value, 0, $pos);
    $b = (float) substr($value, $pos + 1);
    return ($b == 0) ? ($a) : ($a / $b);
}

/**
 * Get shutter speed from EXIF data
 */
function exif_harvester_exif_get_shutter($exif) {
    if (!isset($exif['ShutterSpeedValue'])) {
        return false;
    }
    $apex = exif_harvester_exif_get_float($exif['ShutterSpeedValue']);
    $shutter = pow(2, -$apex);
    if ($shutter == 0) {
        return false;
    }
    if ($shutter >= 1) {
        return round($shutter) . 's';
    }
    return '1/' . round(1 / $shutter) . 's';
}

/**
 * Get f-stop from EXIF data
 */
function exif_harvester_exif_get_fstop($exif) {
    if (!isset($exif['ApertureValue'])) {
        return false;
    }
    $apex = exif_harvester_exif_get_float($exif['ApertureValue']);
    $fstop = pow(2, $apex / 2);
    if ($fstop == 0) {
        return false;
    }
    return 'ƒ/' . round($fstop, 1);
}

/**
 * Convert GPS coordinates from EXIF format to decimal degrees
 */
function exif_harvester_get_gps($coordinate, $hemisphere) {
    if (!is_array($coordinate) || count($coordinate) !== 3) {
        return 0;
    }
    
    $degrees = exif_harvester_exif_get_float($coordinate[0]);
    $minutes = exif_harvester_exif_get_float($coordinate[1]);
    $seconds = exif_harvester_exif_get_float($coordinate[2]);
    
    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
    
    if ($hemisphere === 'S' || $hemisphere === 'W') {
        $decimal = -$decimal;
    }
    
    return $decimal;
}

/**
 * Convert decimal degrees to DMS (Degrees, Minutes, Seconds) format
 */
function exif_harvester_convert_to_dms($decimal, $isLatitude = true) {
    $decimal = abs($decimal);
    $degrees = floor($decimal);
    $minutes = floor(($decimal - $degrees) * 60);
    $seconds = round((($decimal - $degrees) * 60 - $minutes) * 60, 2);
    
    $hemisphere = '';
    if ($isLatitude) {
        $hemisphere = ($decimal >= 0) ? 'N' : 'S';
    } else {
        $hemisphere = ($decimal >= 0) ? 'E' : 'W';
    }
    
    return $degrees . '° ' . $minutes . '\' ' . $seconds . '" ' . $hemisphere;
}

/**
 * Process date/time original from EXIF
 */
function exif_harvester_process_datetime_original($post_id, $dateTimeOriginal) {
    if (empty($dateTimeOriginal)) {
        return;
    }
    
    // Store the original datetime
    if (!metadata_exists('post', $post_id, 'dateTimeOriginal')) {
        add_post_meta($post_id, 'dateTimeOriginal', $dateTimeOriginal);
    }
    
    try {
        $date = DateTime::createFromFormat('Y:m:d H:i:s', $dateTimeOriginal);
        if ($date) {
            // Store individual date/time components
            $components = array(
                'dateOriginal' => $date->format('Y-m-d'),
                'yearOriginal' => $date->format('Y'),
                'monthOriginal' => $date->format('m'),
                'monthNameOriginal' => $date->format('F'),
                'dayOriginal' => $date->format('d'),
                'dayOfWeekOriginal' => $date->format('l'),
                'hourOriginal' => $date->format('H'),
                'minuteOriginal' => $date->format('i'),
                'timeOriginal' => $date->format('H:i')
            );
            
            foreach ($components as $key => $value) {
                if (!metadata_exists('post', $post_id, $key)) {
                    add_post_meta($post_id, $key, $value);
                }
            }
            
            // Calculate time of day context
            $timeOfDayContext = exif_harvester_calculate_time_of_day_context($date->format('H:i'));
            if (!metadata_exists('post', $post_id, 'timeOfDayContext')) {
                add_post_meta($post_id, 'timeOfDayContext', $timeOfDayContext);
            }
            
            // Convert to Unix timestamp
            $unixTime = $date->getTimestamp();
            if (!metadata_exists('post', $post_id, 'unixTime')) {
                add_post_meta($post_id, 'unixTime', $unixTime);
            }
            
            // GMT offset and timezone will be calculated later in ensure_gmt_offset()
            // after GPS data is processed
        }
    } catch (Exception $e) {
        // Log error if needed
        error_log('EXIF Harvester: Error processing datetime: ' . $e->getMessage());
    }
}

/**
 * Calculate time of day context from time string
 */
function exif_harvester_calculate_time_of_day_context($timeString) {
    $time = DateTime::createFromFormat('H:i', $timeString);
    if (!$time) {
        return '';
    }
    
    $hour = (int) $time->format('H');
    
    if ($hour >= 5 && $hour < 12) {
        return 'Morning';
    } elseif ($hour >= 12 && $hour < 17) {
        return 'Afternoon';
    } elseif ($hour >= 17 && $hour < 21) {
        return 'Evening';
    } else {
        return 'Night';
    }
}

/**
 * Process GPS data from EXIF
 */
function exif_harvester_process_gps_data($post_id, $exif_data) {
    $lon = exif_harvester_get_gps($exif_data['GPSLongitude'], $exif_data['GPSLongitudeRef']);
    $lat = exif_harvester_get_gps($exif_data['GPSLatitude'], $exif_data['GPSLatitudeRef']);
    
    if ($lat != 0 && $lon != 0) {
        // Store GPS coordinates
        if (!metadata_exists('post', $post_id, 'GPS')) {
            add_post_meta($post_id, 'GPS', $lat . ',' . $lon);
        }
        if (!metadata_exists('post', $post_id, 'GPSLat')) {
            add_post_meta($post_id, 'GPSLat', $lat);
        }
        if (!metadata_exists('post', $post_id, 'GPSLon')) {
            add_post_meta($post_id, 'GPSLon', $lon);
        }
        
        // Generate geohash
        $geoHash = exif_harvester_get_geohash($lat, $lon);
        if ($geoHash && !metadata_exists('post', $post_id, 'geoHash')) {
            add_post_meta($post_id, 'geoHash', $geoHash);
        }
        
        // Generate Google Plus Code
        $gpCode = exif_harvester_get_google_plus_code($lat, $lon);
        if ($gpCode && !metadata_exists('post', $post_id, 'GPCode')) {
            add_post_meta($post_id, 'GPCode', $gpCode);
        }
    }
}

/**
 * Process photo dimensions
 * Works with JPEG, PNG, WebP, and other image formats supported by getimagesize()
 */
function exif_harvester_process_photo_dimensions($post_id, $fullsize_path) {
    if (empty($fullsize_path) || !file_exists($fullsize_path)) {
        return;
    }
    
    // getimagesize() supports WebP starting from PHP 7.1
    $image_size = getimagesize($fullsize_path);
    if (!$image_size) {
        return;
    }
    
    $width = $image_size[0];
    $height = $image_size[1];
    
    // Store dimensions
    if (!metadata_exists('post', $post_id, 'photo_width')) {
        add_post_meta($post_id, 'photo_width', $width);
    }
    if (!metadata_exists('post', $post_id, 'photo_height')) {
        add_post_meta($post_id, 'photo_height', $height);
    }
    
    // Store combined dimensions
    $dimensions_value = $width . 'x' . $height;
    if (!metadata_exists('post', $post_id, 'photo_dimensions')) {
        add_post_meta($post_id, 'photo_dimensions', $dimensions_value);
    }
    
    // Calculate and store megapixels
    $megapixels = round(($width * $height) / 1000000, 2);
    if (!metadata_exists('post', $post_id, 'photo_megapixels')) {
        add_post_meta($post_id, 'photo_megapixels', $megapixels);
    }
    
    // Calculate and store aspect ratio
    $gcd_value = gcd($width, $height);
    $aspect_ratio = ($width/$gcd_value) . ':' . ($height/$gcd_value);
    if (!metadata_exists('post', $post_id, 'photo_aspect_ratio')) {
        add_post_meta($post_id, 'photo_aspect_ratio', $aspect_ratio);
    }
}

/**
 * Extract first image from post content (fallback method)
 */
function exif_harvester_catch_that_image($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        if (is_user_logged_in() && current_user_can('manage_options')) {
            error_log('EXIF Harvester: catch_that_image - post not found for ID ' . $post_id);
        }
        return '';
    }
    
    $content = $post->post_content;
    
    if (is_user_logged_in() && current_user_can('manage_options')) {
        error_log('EXIF Harvester: catch_that_image - checking content for post ' . $post_id . ' (content length: ' . strlen($content) . ')');
    }
    
    // Look for images in various formats
    $patterns = array(
        '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i',
        '/\[img[^\]]*src=[\'"]([^\'"]+)[\'"][^\]]*\]/i',
        '/\[gallery[^\]]*\]/i'
    );
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            if (isset($matches[1])) {
                if (is_user_logged_in() && current_user_can('manage_options')) {
                    error_log('EXIF Harvester: catch_that_image - found image: ' . $matches[1]);
                }
                return $matches[1];
            }
        }
    }
    
    if (is_user_logged_in() && current_user_can('manage_options')) {
        error_log('EXIF Harvester: catch_that_image - no images found in post content');
    }
    
    return '';
}

/**
 * Process caption from post content
 */
function exif_harvester_process_caption($post_id) {
    $existing_caption = get_post_meta($post_id, 'caption', true);
    if (!empty($existing_caption)) {
        return; // Caption already exists
    }
    
    $post_content = get_post_field('post_content', $post_id);
    if (empty($post_content)) {
        return;
    }
    
    // Remove shortcodes and HTML tags, but preserve text content
    $caption_text = wp_strip_all_tags(do_shortcode($post_content));
    
    // Remove image-related content patterns
    $caption_text = preg_replace('/\[img[^\]]*\]/i', '', $caption_text);
    $caption_text = preg_replace('/\[caption[^\]]*\].*?\[\/caption\]/is', '', $caption_text);
    $caption_text = preg_replace('/\[gallery[^\]]*\]/i', '', $caption_text);
    
    // Decode HTML entities (convert &nbsp; &amp; etc. to proper characters)
    $caption_text = html_entity_decode($caption_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Clean up whitespace
    $caption_text = trim(preg_replace('/\s+/', ' ', $caption_text));
    
    // Only save if we have meaningful content
    if (!empty($caption_text) && strlen($caption_text) > 3) {
        add_post_meta($post_id, 'caption', $caption_text);
    }
}

/**
 * Simple geohash generation (basic implementation)
 * For production use, consider using a more robust geohash library
 */
function exif_harvester_get_geohash($lat, $lon, $precision = 12) {
    $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
    $idx = 0;
    $bit = 0;
    $evenBit = true;
    $geohash = '';
    
    $latMin = -90.0;
    $latMax = 90.0;
    $lonMin = -180.0;
    $lonMax = 180.0;
    
    while (strlen($geohash) < $precision) {
        if ($evenBit) {
            // longitude
            $mid = ($lonMin + $lonMax) / 2;
            if ($lon >= $mid) {
                $idx = ($idx << 1) + 1;
                $lonMin = $mid;
            } else {
                $idx = $idx << 1;
                $lonMax = $mid;
            }
        } else {
            // latitude
            $mid = ($latMin + $latMax) / 2;
            if ($lat >= $mid) {
                $idx = ($idx << 1) + 1;
                $latMin = $mid;
            } else {
                $idx = $idx << 1;
                $latMax = $mid;
            }
        }
        
        $evenBit = !$evenBit;
        
        if (++$bit == 5) {
            $geohash .= $base32[$idx];
            $bit = 0;
            $idx = 0;
        }
    }
    
    return $geohash;
}

/**
 * Generate Google Plus Code (simplified implementation)
 * For production use, consider using Google's official library
 */
function exif_harvester_get_google_plus_code($lat, $lon) {
    // This is a very simplified implementation
    // For production, you should use Google's official Plus Codes library
    
    // Basic validation
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        return '';
    }
    
    // Simple approximation - not a real Plus Code implementation
    // You should replace this with proper Plus Code generation
    $lat_encoded = base_convert(abs(intval($lat * 1000000)), 10, 20);
    $lon_encoded = base_convert(abs(intval($lon * 1000000)), 10, 20);
    
    return substr($lat_encoded . $lon_encoded, 0, 11);
}

/**
 * Set default lens for specific cameras
 */
function exif_harvester_set_default_lens($camera, $post_id) {
    $default_lenses = array(
        'Google Pixel 2 XL' => 'Google Pixel 2 XL f/1.8 1.9mm',
        'DJI Mavic Mini' => 'DJI Mavic Mini f/2.8 4.5mm',
        'Fujifilm FinePix X100' => 'Fujifilm Fujinon f/2 23mm',
        'Fujifilm X100vi' => 'Fujifilm Fujinon f/2 23mm',
        'Olympus Stylus 600' => 'Olympus f/3.1-5.2 5.8-17.4mm',
        'Olympus C4040Z' => 'Olympus f/1.8-f/2.6 7.1-21.3 mm'
    );
    
    if (isset($default_lenses[$camera])) {
        if (!metadata_exists('post', $post_id, 'lens')) {
            add_post_meta($post_id, 'lens', $default_lenses[$camera]);
        }
    }
}

/**
 * Convert temperature from Fahrenheit to Celsius
 */
function exif_harvester_fahrenheit_to_celsius($fahrenheit) {
    return round(($fahrenheit - 32) * 5 / 9, 2);
}

/**
 * Convert temperature from Celsius to Fahrenheit
 */
function exif_harvester_celsius_to_fahrenheit($celsius) {
    return round(($celsius * 9 / 5) + 32, 2);
}

/**
 * Add or update weather summary metadata for a post
 */
function exif_harvester_add_or_update_wx_summary($wx, $post_id) {
    if (!metadata_exists('post', $post_id, 'wXSummary')) {
        add_post_meta($post_id, 'wXSummary', $wx);
    } else {
        update_post_meta($post_id, 'wXSummary', $wx);
    }
}

/**
 * Add or update temperature metadata for a post
 */
function exif_harvester_add_or_update_temperature($temp, $post_id) {
    if (!metadata_exists('post', $post_id, 'temperature')) {
        add_post_meta($post_id, 'temperature', $temp);
    } else {
        update_post_meta($post_id, 'temperature', $temp);
    }
}

/**
 * Get weather data for specific coordinates and time using PirateWeather API
 */
function exif_harvester_get_weather($lat, $lon, $time, $post_id, $api_key) {
    // Always log weather attempts for debugging
    $readable_time = date('Y-m-d H:i:s', $time);
    $age_days = round((time() - $time) / 86400, 1);
    error_log('EXIF Harvester: Starting weather lookup for post ' . $post_id . ' (lat: ' . $lat . ', lon: ' . $lon . ', time: ' . $time . ' [' . $readable_time . '], age: ' . $age_days . ' days)');
    
    if (empty($api_key)) {
        error_log('EXIF Harvester: Weather API key is empty for post ' . $post_id);
        return false;
    }
    
    // Validate coordinates
    if ($lat == 0 || $lon == 0 || empty($time)) {
        error_log('EXIF Harvester: Invalid coordinates or time for post ' . $post_id . ' (lat: ' . $lat . ', lon: ' . $lon . ', time: ' . $time . ')');
        return false;
    }
    
    // Use the same working logic as scratch/weather-functions.php:
    // Try timemachine API first (works for all data), then regular API as fallback
    $endpoints = array(
        'https://timemachine.pirateweather.net/forecast/' . $api_key . '/' . $lat . ',' . $lon . ',' . $time . '?exclude=minutely,hourly,daily,alerts',
        'https://api.pirateweather.net/forecast/' . $api_key . '/' . $lat . ',' . $lon . ',' . $time . '?exclude=minutely,hourly,daily,alerts'
    );
    
    error_log('EXIF Harvester: Using proven endpoint order for post ' . $post_id . ' - timemachine first, then regular API fallback (age: ' . $age_days . ' days)');
    error_log('EXIF Harvester: API key length for masking: ' . strlen($api_key) . ' characters');
    
    // Store the API URLs for debugging purposes (with REAL API key for easy browser testing)
    update_post_meta($post_id, '_weather_api_urls', $endpoints);
    error_log('EXIF Harvester: Storing API URLs with real keys for debugging post ' . $post_id);
    
    foreach ($endpoints as $index => $reqURL) {
        error_log('EXIF Harvester: Trying weather endpoint ' . ($index + 1) . ' for post ' . $post_id . ': ' . $reqURL);
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $reqURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'EXIF Harvester WordPress Plugin/1.0.0');
        
        // Execute the request
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        // Log all response details for debugging
        error_log('EXIF Harvester: Endpoint ' . ($index + 1) . ' response for post ' . $post_id . ' - HTTP: ' . $http_code . ', cURL error: ' . ($curl_error ?: 'none') . ', data length: ' . ($data ? strlen($data) : 0));
        
        // Check for cURL errors
        if ($data === false || !empty($curl_error)) {
            error_log('EXIF Harvester: cURL error on endpoint ' . ($index + 1) . ' for post ' . $post_id . ': ' . $curl_error);
            continue; // Try next endpoint
        }
        
        // Check for HTTP errors
        if ($http_code < 200 || $http_code >= 300) {
            error_log('EXIF Harvester: HTTP error ' . $http_code . ' on endpoint ' . ($index + 1) . ' for post ' . $post_id . ': ' . substr($data, 0, 500));
            continue; // Try next endpoint
        }
        
        // Log successful HTTP response
        error_log('EXIF Harvester: Endpoint ' . ($index + 1) . ' returned HTTP ' . $http_code . ' for post ' . $post_id);
        
        // Decode JSON response
        $weather_data = json_decode($data);
        $json_error = json_last_error();
        
        if ($json_error !== JSON_ERROR_NONE) {
            error_log('EXIF Harvester: JSON decode error on endpoint ' . ($index + 1) . ' for post ' . $post_id . ': ' . json_last_error_msg() . ' - Data: ' . substr($data, 0, 500));
            continue; // Try next endpoint
        }
        
        // Check if JSON is valid and has the data we need (match scratch/weather-functions.php logic)
        if ($weather_data !== null && isset($weather_data->currently)) {
            $currently = $weather_data->currently;
            
            // Extract weather summary and temperature (match working format)
            $weather_summary = $currently->summary;
            $temperature_f = $currently->temperature;
            
            error_log('EXIF Harvester: SUCCESS! Weather data retrieved from endpoint ' . ($index + 1) . ' for post ' . $post_id . ' - Summary: ' . $weather_summary . ', Temp: ' . $temperature_f . '°F');
            
            // Store weather summary and temperature (match scratch functions)
            exif_harvester_add_or_update_wx_summary($weather_summary, $post_id);
            exif_harvester_add_or_update_temperature(exif_harvester_fahrenheit_to_celsius($temperature_f), $post_id);
            
            // Create readable weather string matching scratch format
            $weather_string = $weather_summary . '& Temperature: ' . exif_harvester_fahrenheit_to_celsius($temperature_f) . ' °C (' . $temperature_f . ' °F)';
            
            return $weather_string;
        } else {
            error_log('EXIF Harvester: Invalid JSON structure from endpoint ' . ($index + 1) . ' for post ' . $post_id . ' - Missing "currently" section. Data: ' . substr($data, 0, 500));
            continue; // Try next endpoint
        }
    }
    
    // All endpoints failed
    error_log('EXIF Harvester: All weather API endpoints failed for post ' . $post_id);
    return false;
}

/**
 * Convert Unix timestamp to GMT adjusted timestamp
 */
function exif_harvester_convert_to_gmt($unixTime, $gmtOffset) {
    if (empty($unixTime) || $gmtOffset === null || $gmtOffset === '') {
        error_log('EXIF Harvester: GMT conversion skipped - unixTime: ' . $unixTime . ', gmtOffset: ' . var_export($gmtOffset, true));
        return $unixTime; // Return original time if no offset available
    }
    
    // Convert GMT offset hours to seconds and apply
    // Note: PirateWeather API expects UTC timestamps
    // If local time is UTC+5, we subtract 5 hours to get UTC
    $offsetSeconds = $gmtOffset * 3600;
    $gmt_time = $unixTime - $offsetSeconds;
    
    error_log('EXIF Harvester: GMT conversion - Original: ' . $unixTime . ' (' . date('Y-m-d H:i:s', $unixTime) . '), Offset: ' . $gmtOffset . 'h, GMT: ' . $gmt_time . ' (' . date('Y-m-d H:i:s', $gmt_time) . ')');
    
    return $gmt_time;
}

/**
 * Get timezone information using TimezoneDB API
 */
function exif_harvester_get_timezone($lat, $lon, $unixTime, $api_key = '') {
    if (empty($api_key)) {
        error_log('EXIF Harvester: TimezoneDB API key is empty, timezone data will be inaccurate');
        return 'UTC+0'; // Fallback
    }
    
    $ch = curl_init();
    $reqURL = 'https://api.timezonedb.com/v2.1/get-time-zone?key=' . urlencode($api_key) . '&format=json&by=position&lat=' . urlencode($lat) . '&lng=' . urlencode($lon) . '&time=' . urlencode($unixTime);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $reqURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    // Execute the request
    $data = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        error_log('EXIF Harvester: TimezoneDB cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return 'UTC+0'; // Fallback
    }
    
    curl_close($ch);
    
    // Decode the JSON response
    $finaldata = json_decode($data);
    
    // Check if JSON decoding was successful and timezone data exists
    if ($finaldata !== null && isset($finaldata->zoneName)) {
        error_log('EXIF Harvester: TimezoneDB Success - Timezone: ' . $finaldata->zoneName);
        return $finaldata->zoneName;
    } else {
        error_log('EXIF Harvester: TimezoneDB Error: Invalid response or timezone not found. Response: ' . substr($data, 0, 500));
        return 'UTC+0'; // Fallback
    }
}

/**
 * Get GMT offset using TimezoneDB API
 */
function exif_harvester_get_gmt_offset($lat, $lon, $unixTime, $api_key = '') {
    if (empty($api_key)) {
        error_log('EXIF Harvester: TimezoneDB API key is empty, GMT offset will be inaccurate');
        return 0; // Fallback to UTC
    }
    
    $ch = curl_init();
    $reqURL = 'https://api.timezonedb.com/v2.1/get-time-zone?key=' . urlencode($api_key) . '&format=json&by=position&lat=' . urlencode($lat) . '&lng=' . urlencode($lon) . '&time=' . urlencode($unixTime);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $reqURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    // Execute the request
    $data = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        error_log('EXIF Harvester: TimezoneDB cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return 0; // Fallback to UTC
    }
    
    curl_close($ch);
    
    // Decode the JSON response
    $finaldata = json_decode($data);
    
    // Check if JSON decoding was successful and gmtOffset exists
    if ($finaldata !== null && isset($finaldata->gmtOffset)) {
        // Convert seconds to hours
        $gmt_offset_hours = $finaldata->gmtOffset / 3600;
        error_log('EXIF Harvester: TimezoneDB Success - GMT Offset: ' . $gmt_offset_hours . ' hours (from ' . $finaldata->gmtOffset . ' seconds)');
        return $gmt_offset_hours;
    } else {
        error_log('EXIF Harvester: TimezoneDB Error: Invalid response or gmtOffset not found. Response: ' . substr($data, 0, 500));
        return 0; // Fallback to UTC
    }
}

/**
 * Extract location information from image IPTC metadata with corrections lookup
 * 
 * @param string $path Path to the image file
 * @return string|null The location string or null if not found
 */
function exif_harvester_get_location($path) {
    if (!file_exists($path)) {
        return null; // File does not exist
    }

    global $wpdb;
    $location = null;
    
    // Check if file is WebP - extract from XMP
    $is_webp = (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp');
    
    if ($is_webp) {
        // Extract location from XMP in WebP
        $xmp_data = exif_harvester_extract_xmp_from_webp($path);
        if ($xmp_data && isset($xmp_data['IPTC_Location'])) {
            $location = $xmp_data['IPTC_Location'];
        }
    } else {
        // Try to get IPTC location directly for JPEG files
        $iptc = iptcparse(file_get_contents($path));

        if (is_array($iptc) && isset($iptc['2#092'][0]) && !empty($iptc['2#092'][0])) {
            $location = $iptc['2#092'][0];
        } else {
            // Fallback: getimagesize for APP13
            $image = getimagesize($path, $info);
            if (isset($info["APP13"])) {
                $iptc = iptcparse($info["APP13"]);
                if (is_array($iptc) && isset($iptc['2#092'][0]) && !empty($iptc['2#092'][0])) {
                    $location = $iptc['2#092'][0];
                }
            }
        }
    }

    // Fix known truncated values using database
    if ($location) {
        $location_table = $wpdb->prefix . 'exif_harvester_location_corrections';
        $corrected = $wpdb->get_var($wpdb->prepare(
            "SELECT full_name FROM $location_table WHERE truncated_name = %s",
            $location
        ));
        
        if ($corrected) {
            return $corrected;
        }
    }

    return $location;
}

/**
 * Extract city information from image IPTC metadata
 * 
 * @param string $path Path to the image file
 * @return string|null The city string or null if not found
 */
function exif_harvester_get_city($path) {
    if (!file_exists($path)) {
        return null; // File does not exist
    }
    
    $city = null;
    
    // Check if file is WebP - extract from XMP
    $is_webp = (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp');
    
    if ($is_webp) {
        // Extract city from XMP in WebP
        $xmp_data = exif_harvester_extract_xmp_from_webp($path);
        if ($xmp_data && isset($xmp_data['IPTC_City'])) {
            $city = $xmp_data['IPTC_City'];
        }
    } else {
        // Parse IPTC metadata from the file contents for JPEG
        $iptc = iptcparse(file_get_contents($path));

        // Check for the '2#090' tag (City) in the IPTC metadata
        if (is_array($iptc) && isset($iptc['2#090'][0]) && !empty($iptc['2#090'][0])) {
            $city = $iptc['2#090'][0]; // Return the city
        }

        // Fallback: Parse IPTC data from APP13 segment using getimagesize
        if (!$city) {
            $image = getimagesize($path, $info);
            if (isset($info['APP13'])) {
                $iptc = iptcparse($info['APP13']);
                if (is_array($iptc) && isset($iptc['2#090'][0]) && !empty($iptc['2#090'][0])) {
                    $city = $iptc['2#090'][0]; // Return the city
                }
            }
        }
    }
    
    // Apply city corrections if needed (Las Vegas Valley -> Las Vegas)
    if ($city === 'Las Vegas Valley') {
        $city = 'Las Vegas';
    }
    
    return $city;
}

/**
 * Extract state/region information from image IPTC metadata
 * 
 * @param string $path Path to the image file
 * @return string|null The state string or null if not found
 */
function exif_harvester_get_state($path) {
    if (!file_exists($path)) {
        return null; // File does not exist
    }

    // Check if file is WebP - extract from XMP
    $is_webp = (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp');
    
    if ($is_webp) {
        // Extract state from XMP in WebP
        $xmp_data = exif_harvester_extract_xmp_from_webp($path);
        if ($xmp_data && isset($xmp_data['IPTC_State'])) {
            return $xmp_data['IPTC_State'];
        }
        return null;
    }

    // Attempt to parse IPTC metadata from the file directly for JPEG
    $iptc = iptcparse(file_get_contents($path));

    // Check for '2#095' (Region/State) tag in IPTC metadata
    if (is_array($iptc) && isset($iptc['2#095'][0]) && !empty($iptc['2#095'][0])) {
        return $iptc['2#095'][0]; // Return the state
    }

    // Fallback: Parse IPTC data from the APP13 segment
    $image = getimagesize($path, $info);
    if (isset($info['APP13'])) {
        $iptc = iptcparse($info['APP13']);
        if (is_array($iptc) && isset($iptc['2#095'][0]) && !empty($iptc['2#095'][0])) {
            return $iptc['2#095'][0]; // Return the state
        }
    }

    // No state found
    return null;
}

/**
 * Extract country information from image IPTC metadata
 * 
 * @param string $path Path to the image file
 * @return string|null The country string or null if not found
 */
function exif_harvester_get_country($path) {
    if (!file_exists($path)) {
        return null; // File does not exist
    }

    // Check if file is WebP - extract from XMP
    $is_webp = (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp');
    
    if ($is_webp) {
        // Extract country from XMP in WebP
        $xmp_data = exif_harvester_extract_xmp_from_webp($path);
        if ($xmp_data && isset($xmp_data['IPTC_Country'])) {
            return $xmp_data['IPTC_Country'];
        }
        return null;
    }

    // Attempt to parse IPTC metadata directly for JPEG
    $iptc = iptcparse(file_get_contents($path));

    // Check for '2#101' (Country/Primary Location Name) tag
    if (is_array($iptc) && isset($iptc['2#101'][0]) && !empty($iptc['2#101'][0])) {
        return $iptc['2#101'][0];
    }

    // Fallback: Parse IPTC data from the APP13 segment
    $image = getimagesize($path, $info);
    if (isset($info['APP13'])) {
        $iptc = iptcparse($info['APP13']);
        if (is_array($iptc) && isset($iptc['2#101'][0]) && !empty($iptc['2#101'][0])) {
            return $iptc['2#101'][0];
        }
    }

    // No country found
    return null;
}

/**
 * Assign hierarchical place terms to a post
 * Creates hierarchical taxonomy terms (country > state > city > location)
 * and assigns only the most specific term to the post.
 * 
 * @param int $post_id Post ID to assign terms to
 * @param string $location Location name
 * @param string $city City name
 * @param string $state State name
 * @param string $country Country name
 */
function exif_harvester_assign_place_terms($post_id, $location, $city, $state, $country) {
    // Don't process if post status is trash
    $post = get_post($post_id);
    if ($post && $post->post_status === 'trash') {
        return;
    }
    
    // Check if taxonomy exists
    if (!taxonomy_exists('place')) {
        error_log('EXIF Harvester: Place taxonomy does not exist when trying to assign terms');
        return;
    }
    
    $terms = [
        'country' => $country,
        'state'   => $state,
        'city'    => $city,
        'location'=> $location,
    ];

    $parent_id = 0;
    $last_term_id = null;

    foreach ($terms as $level => $term_name) {
        if (empty($term_name)) {
            continue;
        }

        // Check for existing term with specified parent
        $existing_terms = get_terms([
            'taxonomy' => 'place',
            'hide_empty' => false,
            'name' => $term_name,
            'parent' => $parent_id,
            'fields' => 'ids',
        ]);

        if (is_wp_error($existing_terms)) {
            error_log('EXIF Harvester: Error getting terms: ' . $existing_terms->get_error_message());
            continue;
        }

        if (!empty($existing_terms)) {
            $term_id = $existing_terms[0];
        } else {
            // Create the term under the current parent
            $new_term = wp_insert_term($term_name, 'place', ['parent' => $parent_id]);

            if (is_wp_error($new_term)) {
                error_log('EXIF Harvester: Error creating term "' . $term_name . '": ' . $new_term->get_error_message());
                continue;
            }

            $term_id = $new_term['term_id'];
        }

        $parent_id = $term_id;     // Use this as parent for the next term
        $last_term_id = $term_id;  // Track the lowest-level term
    }

    // Assign only the last (most specific) term
    if ($last_term_id) {
        $result = wp_set_post_terms($post_id, [$last_term_id], 'place', false);
        if (is_wp_error($result)) {
            error_log('EXIF Harvester: Error setting post terms: ' . $result->get_error_message());
        }
    }
}