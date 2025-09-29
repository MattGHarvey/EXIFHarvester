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
 */
function exif_harvester_process_photo_dimensions($post_id, $fullsize_path) {
    if (empty($fullsize_path) || !file_exists($fullsize_path)) {
        return;
    }
    
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
    
    // Try to get IPTC location directly
    $iptc = iptcparse(file_get_contents($path));
    $location = null;

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
    
    // Parse IPTC metadata from the file contents
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

    // Attempt to parse IPTC metadata from the file directly
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

    // Attempt to parse IPTC metadata directly
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