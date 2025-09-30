<?php
/**
 * SEO Meta Description Generation for EXIF Harvester
 * 
 * This system automatically generates SEO-optimized meta descriptions for posts with
 * image attachments by analyzing EXIF data, location information, and post content.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper functions to get location data using proper field names
 * These functions abstract the numbered field storage and provide clean interfaces
 */

/**
 * Get location name for a post
 * 
 * @param int $post_id Post ID
 * @return string|null Location name or null if not found
 */
function exif_harvester_get_post_location($post_id) {
    return get_post_meta($post_id, 'location', true) ?: null;
}

/**
 * Get city name for a post
 * 
 * @param int $post_id Post ID
 * @return string|null City name or null if not found
 */
function exif_harvester_get_post_city($post_id) {
    return get_post_meta($post_id, 'city', true) ?: null;
}

/**
 * Get state name for a post
 * 
 * @param int $post_id Post ID
 * @return string|null State name or null if not found
 */
function exif_harvester_get_post_state($post_id) {
    return get_post_meta($post_id, 'state', true) ?: null;
}

/**
 * Get country name for a post
 * 
 * @param int $post_id Post ID
 * @return string|null Country name or null if not found
 */
function exif_harvester_get_post_country($post_id) {
    return get_post_meta($post_id, 'country', true) ?: null;
}

/**
 * Check if post has any location data
 * 
 * @param int $post_id Post ID
 * @return bool True if has location data
 */
function exif_harvester_post_has_location_data($post_id) {
    return !empty(exif_harvester_get_post_location($post_id)) || 
           !empty(exif_harvester_get_post_city($post_id)) || 
           !empty(exif_harvester_get_post_state($post_id)) || 
           !empty(exif_harvester_get_post_country($post_id));
}





/**
 * Build optimal location string for SEO with state priority
 * @param string $location Main location
 * @param string $city City name
 * @param string $state State name
 * @param string $country Country name
 * @param int $max_length Maximum length for the location string
 * @return string Optimized location string
 */
function exif_harvester_build_seo_location($location, $city, $state, $country, $max_length = 40) {
    // Priority order for SEO: City + State, then Location + State, then fallbacks
    
    // Option 1: City, State (ideal for SEO)
    if ($city && $state) {
        $city_state = $city . ", " . $state;
        if (strlen($city_state) <= $max_length) {
            return $city_state;
        }
    }
    
    // Option 2: Location + State (if location is short)
    if ($location && $state && strlen($location) <= 20) {
        $location_state = $location . ", " . $state;
        if (strlen($location_state) <= $max_length) {
            return $location_state;
        }
    }
    
    // Option 3: Just State (if others are too long but state is valuable)
    if ($state && strlen($state) <= $max_length) {
        return $state;
    }
    
    // Option 4: City only (if state not available)
    if ($city && strlen($city) <= $max_length) {
        return $city;
    }
    
    // Option 5: Location only (fallback)
    if ($location && strlen($location) <= $max_length) {
        return $location;
    }
    
    // Option 6: Truncated location
    if ($location) {
        return strlen($location) > $max_length ? substr($location, 0, $max_length - 3) . "..." : $location;
    }
    
    return '';
}

/**
 * Generate word variations for better tag matching
 * @param string $word Base word
 * @return array Array of word variations
 */
function exif_harvester_get_word_variations($word) {
    $variations = [$word];
    
    // Handle plural/singular variations
    if (strlen($word) > 3) {
        // Add plural if not already plural
        if (!preg_match('/(s|es|ies)$/i', $word)) {
            if (preg_match('/[aeiou]y$/i', $word)) {
                // happy -> happies (but not for -ey endings)
                if (!preg_match('/ey$/i', $word)) {
                    $variations[] = substr($word, 0, -1) . 'ies';
                } else {
                    $variations[] = $word . 's';
                }
            } elseif (preg_match('/(ch|sh|s|x|z)$/i', $word)) {
                $variations[] = $word . 'es';
            } else {
                $variations[] = $word . 's';
            }
        } else {
            // Try to make singular
            if (preg_match('/ies$/i', $word)) {
                $variations[] = substr($word, 0, -3) . 'y';
            } elseif (preg_match('/(ch|sh|s|x|z)es$/i', $word)) {
                $variations[] = substr($word, 0, -2);
            } elseif (preg_match('/s$/i', $word) && !preg_match('/(ss)$/i', $word)) {
                $variations[] = substr($word, 0, -1);
            }
        }
    }
    
    // Handle common photography term variations
    $special_cases = [
        // Maritime/Water
        'ship' => ['ships', 'boat', 'boats', 'vessel', 'vessels'],
        'ships' => ['ship', 'boat', 'boats', 'vessel', 'vessels'],
        'boat' => ['boats', 'ship', 'ships', 'vessel', 'vessels'],
        'boats' => ['boat', 'ship', 'ships', 'vessel', 'vessels'],
        'ocean' => ['sea', 'water', 'marine'],
        'sea' => ['ocean', 'water', 'marine'],
        
        // Landscape/Nature
        'mountain' => ['mountains', 'peak', 'peaks', 'hill', 'hills', 'summit'],
        'mountains' => ['mountain', 'peak', 'peaks', 'hill', 'hills', 'summit'],
        'landscape' => ['landscapes', 'scenery', 'scenic', 'vista', 'view'],
        'landscapes' => ['landscape', 'scenery', 'scenic', 'vista', 'view'],
        'forest' => ['woods', 'woodland', 'trees', 'grove'],
        'beach' => ['shore', 'coast', 'coastal', 'shoreline'],
        'waterfall' => ['falls', 'cascade'],
        
        // Wildlife/Animals  
        'wildlife' => ['animal', 'animals', 'fauna'],
        'bird' => ['birds', 'avian', 'birding'],
        'birds' => ['bird', 'avian', 'birding'],
        
        // Architecture/Urban
        'architecture' => ['building', 'buildings', 'structure', 'structures', 'architectural'],
        'building' => ['buildings', 'architecture', 'structure', 'structures'],
        'buildings' => ['building', 'architecture', 'structure', 'structures'],
        'street' => ['urban', 'city', 'downtown'],
        'cityscape' => ['skyline', 'urban landscape'],
        
        // Photography Styles
        'portrait' => ['portraits', 'portraiture'],
        'portraits' => ['portrait', 'portraiture'],
        'macro' => ['close-up', 'close up', 'detail'],
        'abstract' => ['abstraction', 'conceptual'],
        
        // Time/Light
        'sunset' => ['sunsets', 'golden hour', 'dusk', 'evening'],
        'sunrise' => ['sunrises', 'dawn', 'morning'],
        'night' => ['nighttime', 'evening', 'nocturnal'],
        
        // Weather/Atmosphere
        'storm' => ['storms', 'stormy', 'tempest'],
        'fog' => ['foggy', 'mist', 'misty'],
        'snow' => ['snowy', 'winter', 'snowfall'],
        
        // Travel/Tourism
        'travel' => ['tourism', 'destination', 'journey'],
        'historic' => ['historical', 'heritage', 'vintage'],
    ];
    
    $word_lower = strtolower($word);
    if (isset($special_cases[$word_lower])) {
        $variations = array_merge($variations, $special_cases[$word_lower]);
    }
    
    return array_unique($variations);
}

/**
 * Get blacklisted tags and patterns that should be excluded from meta descriptions
 * @return array Array with 'exact' and 'patterns' keys
 */
function exif_harvester_get_meta_description_tag_blacklist() {
    // Default blacklist - can be filtered by themes/plugins
    $blacklist = [
        'exact' => [
            // Generic/low-value terms
            'photo', 'picture', 'image', 'photography', 'photographer', 'photos', 'pictures', 'images',
            'camera', 'lens', 'shot', 'shots', 'capture', 'captured', 'shooting',
            
            // Camera brands and models (no SEO value)
            'fuji', 'fujifilm', 'canon', 'nikon', 'sony', 'panasonic', 'olympus', 'leica',
            'fujifilm x-t5', 'canon eos', 'nikon d850', 'sony alpha', 'aps-c', 'full frame',
            'mirrorless', 'dslr',
            
            // Specific camera model tags (keep general brand names)
            'fujifilm x-t5', 'canon eos', 'nikon d850', 'sony alpha', 'aps-c',
            
            // Technical terms that don't add SEO value
            'digital', 'raw', 'jpeg', 'jpg', 'editing', 'processed', 'postprocessing', 'lightroom', 'photoshop',
            'aperture', 'shutter', 'speed', 'iso', 'exposure', 'focal', 'length', 'focus', 'autofocus', 'manual focus',
            'metering', 'flash', 'white balance', 'histogram', 'overexposed', 'underexposed', 'stops', 'ev',
            'exif', 'metadata', 'file', 'size', 'resolution', 'megapixel', 'mp', 'dpi', 'pixel', 'pixels',
            'sensor', 'crop', 'full-frame', 'aps-c', 'micro four thirds', 'm43', 'format',
            
            // Overly generic descriptors
            'beautiful', 'amazing', 'stunning', 'incredible', 'awesome', 'perfect', 'great', 'nice', 'cool',
            'best', 'good', 'bad', 'new', 'old', 'big', 'small', 'large', 'tiny',
            
            // Generic visual terms that lack subject matter specificity
            'sky', 'blue sky', 'clouds', 'light', 'shadow', 'color', 'colors', 'bright', 'dark',
            'view', 'scene', 'background', 'foreground', 'detail', 'details', 'texture', 'pattern',
            
            // Time-related terms (no SEO value compared to subject matter)
            'morning', 'afternoon', 'evening', 'night', 'daytime', 'nighttime', 'dawn', 'dusk',
            'early', 'late', 'midday', 'noon', 'midnight', 'today', 'yesterday', 'weekend',
            
            // Social media related
            'instagram', 'facebook', 'twitter', 'hashtag', 'social', 'viral', 'trending',
            
            // Only truly generic photography terms (keep subject-specific terms)
            'bokeh', 'dof', 'depth of field', 'exposure', 'composition',
            
            // Generic location terms (too vague)
            'travel', 'trip', 'vacation', 'journey', 'adventure', 'explore', 'wanderlust',
            
            // Time-related terms that may conflict with metadata
            'today', 'yesterday', 'weekend', 'week', 'month', 'season',
        ],
        'patterns' => [
            // Patterns for partial matches  
            'canon eos', 'nikon d', 'sony alpha', 'fujifilm x', 'olympus om', // camera models
            'mm f/', 'f/', 'iso ', 'f/1', 'f/2', 'f/3', 'f/4', 'f/5', 'f/6', // technical specs
            'ttartisan', 'sigma', 'tamron', 'tokina', 'zeiss', 'voigtlander', // lens manufacturers
            '1/', '2/', '3/', '4/', '5/', '6/', '7/', '8/', // shutter speeds
            'mm', 'sec', 'ms', // measurement units
            'iso', 'ev', 'wb', // technical abbreviations
            '#', '@', // social media artifacts
            'http', 'www.', // URLs
            'copyright', '©', // copyright info
        ]
    ];
    
    // Merge in custom blacklist entries from admin UI
    $custom_blacklist = get_option('exif_harvester_seo_custom_blacklist', ['exact' => [], 'patterns' => []]);
    if (!empty($custom_blacklist['exact'])) {
        $blacklist['exact'] = array_merge($blacklist['exact'], $custom_blacklist['exact']);
    }
    if (!empty($custom_blacklist['patterns'])) {
        $blacklist['patterns'] = array_merge($blacklist['patterns'], $custom_blacklist['patterns']);
    }
    
    // Allow further customization via WordPress filter
    return apply_filters('exif_harvester_seo_meta_description_tag_blacklist', $blacklist);
}

/**
 * Check if a tag is blacklisted for meta description use
 * @param string $tag_lower Tag name in lowercase
 * @return bool True if tag should be excluded
 */
function exif_harvester_is_tag_blacklisted($tag_lower) {
    $blacklist = exif_harvester_get_meta_description_tag_blacklist();
    
    // Check exact matches
    if (in_array($tag_lower, $blacklist['exact'])) {
        return true;
    }
    
    // Check pattern matches
    foreach ($blacklist['patterns'] as $pattern) {
        if (strpos($tag_lower, strtolower($pattern)) !== false) {
            return true;
        }
    }
    
    return false;
}

// Temporal redundancy function removed - no longer using time context for SEO

/**
 * Check if a tag would be redundant with location information
 * @param string $tag_lower Tag name in lowercase
 * @param string $location Location metadata
 * @param string $city City metadata
 * @param string $state State metadata  
 * @param string $country Country metadata
 * @return bool True if tag is redundant with location data
 */
function exif_harvester_is_location_redundant_tag($tag_lower, $location, $city, $state, $country) {
    // Collect all location components
    $location_parts = array_filter([$location, $city, $state, $country]);
    
    if (empty($location_parts)) {
        return false; // No location data, so no redundancy
    }
    
    // Split tag into words for better matching
    $tag_words = preg_split('/[\s\-_&,]+/', $tag_lower, -1, PREG_SPLIT_NO_EMPTY);
    
    // Check each tag word against location components
    foreach ($tag_words as $word) {
        if (strlen($word) <= 2) continue; // Skip very short words
        
        foreach ($location_parts as $location_part) {
            if (empty($location_part)) continue;
            
            $location_lower = strtolower($location_part);
            
            // Exact match
            if ($word === $location_lower) {
                return true;
            }
            
            // Word appears in location
            if (strpos($location_lower, $word) !== false) {
                return true;
            }
            
            // Location appears in word (for compound location names)
            if (strpos($word, $location_lower) !== false && strlen($location_lower) > 3) {
                return true;
            }
        }
    }
    
    // Special cases for common location-related terms that might be redundant
    $location_keywords = ['downtown', 'uptown', 'old town', 'city center', 'historic district', 'waterfront', 'harbor', 'port'];
    
    foreach ($location_keywords as $keyword) {
        // If the tag contains a location keyword and we have city/location data, it might be redundant
        if (strpos($tag_lower, $keyword) !== false && (!empty($city) || !empty($location))) {
            // But only if the keyword also appears in our location data or content would naturally include it
            $combined_location = strtolower(implode(' ', $location_parts));
            if (strpos($combined_location, $keyword) !== false) {
                return true;
            }
        }
    }
    
    // Check for state/country abbreviations and full names
    $location_mappings = [
        'texas' => ['tx', 'dallas', 'houston', 'austin', 'san antonio'],
        'california' => ['ca', 'los angeles', 'san francisco', 'san diego'],
        'new york' => ['ny', 'nyc', 'manhattan', 'brooklyn'],
        'florida' => ['fl', 'miami', 'orlando', 'tampa'],
        'usa' => ['united states', 'america', 'us'],
        'uk' => ['united kingdom', 'britain', 'england', 'london'],
        'mexico' => ['yucatan', 'cancun', 'mexico city'],
    ];
    
    foreach ($location_mappings as $place => $variants) {
        if (in_array($tag_lower, $variants) || $tag_lower === $place) {
            // Check if this location is already represented
            $combined = strtolower(implode(' ', $location_parts));
            if (strpos($combined, $place) !== false) {
                return true;
            }
            foreach ($variants as $variant) {
                if (strpos($combined, $variant) !== false) {
                    return true;
                }
            }
        }
    }
    
    return false;
}

/**
 * Filter out tags that are substrings of other tags to avoid redundancy
 * Keeps the more specific (longer) tags and removes generic substrings
 * 
 * @param array $tags Array of tag names
 * @return array Filtered array with substring tags removed
 */
function exif_harvester_filter_substring_tags($tags) {
    if (empty($tags) || count($tags) < 2) {
        return $tags;
    }
    
    $filtered_tags = [];
    
    // Create array of lowercase tags for comparison, but keep original case for output
    $tag_pairs = [];
    foreach ($tags as $tag) {
        $tag_pairs[] = [
            'original' => $tag,
            'lower' => strtolower(trim($tag)),
            'length' => strlen($tag)
        ];
    }
    
    // Sort by length (longest first) to prioritize more specific tags
    usort($tag_pairs, function($a, $b) {
        return $b['length'] - $a['length'];
    });
    
    foreach ($tag_pairs as $current_tag) {
        $is_substring = false;
        
        // Check if this tag is a substring of any longer tag we've already kept
        foreach ($filtered_tags as $kept_tag) {
            $kept_lower = strtolower($kept_tag);
            
            // Skip if they're the same tag
            if ($current_tag['lower'] === $kept_lower) {
                $is_substring = true;
                break;
            }
            
            // Check if current tag is a substring of a longer, more specific tag
            if (strlen($current_tag['lower']) < strlen($kept_lower)) {
                // Check if current tag appears as a word in the longer tag
                if (strpos($kept_lower, $current_tag['lower']) !== false) {
                    // Additional check: make sure it's a meaningful substring, not just coincidence
                    // Only exclude if the shorter tag is at least 3 characters and appears as a word boundary
                    if (strlen($current_tag['lower']) >= 3) {
                        // Check for word boundary match (whole word within the longer tag)
                        if (preg_match('/\b' . preg_quote($current_tag['lower'], '/') . '\b/', $kept_lower)) {
                            $is_substring = true;
                            break;
                        }
                    }
                }
            }
        }
        
        // Only add if it's not a substring of an existing tag
        if (!$is_substring) {
            $filtered_tags[] = $current_tag['original'];
        }
    }
    
    return $filtered_tags;
}

/**
 * Clean redundant location parts to avoid repetition
 * Example: ["Downtown Dallas", "Dallas", "Texas"] -> ["Downtown Dallas", "Texas"]
 * @param array $location_parts Array of location strings
 * @return array Cleaned array with redundant parts removed
 */
function exif_harvester_clean_redundant_location_parts($location_parts) {
    if (empty($location_parts) || count($location_parts) < 2) {
        return $location_parts;
    }
    
    $cleaned = [];
    
    foreach ($location_parts as $part) {
        $part_lower = strtolower($part);
        $is_redundant = false;
        
        // Check if this location part is contained in any other part
        foreach ($location_parts as $other_part) {
            if ($part === $other_part) continue; // Skip self
            
            $other_lower = strtolower($other_part);
            
            // If this part is contained within another part, it's redundant
            if (strpos($other_lower, $part_lower) !== false && strlen($part_lower) < strlen($other_lower)) {
                $is_redundant = true;
                break;
            }
        }
        
        if (!$is_redundant) {
            $cleaned[] = $part;
        }
    }
    
    return $cleaned;
}

/**
 * Score tags based on content relevance using multiple factors
 * @param int $post_id Post ID  
 * @param array $tags Array of tag names
 * @return array Sorted array of tags by relevance score (highest first)
 */
function exif_harvester_score_tags_by_relevance($post_id, $tags) {
    $post = get_post($post_id);
    if (!$post) return [];
    
    // Get content for scoring
    $title = strtolower($post->post_title);
    $content = strtolower(strip_tags($post->post_content));
    $excerpt = strtolower($post->post_excerpt);
    
    // Get metadata for context scoring
    $location = strtolower(get_post_meta($post_id, 'location', true));
    $city = strtolower(get_post_meta($post_id, 'city', true));
    $state = strtolower(get_post_meta($post_id, 'state', true));
    $country = strtolower(get_post_meta($post_id, 'country', true));
    $weather = strtolower(get_post_meta($post_id, 'wXSummary', true));
    
    // Combine all searchable text
    $all_text = $title . ' ' . $content . ' ' . $excerpt . ' ' . $location . ' ' . $city . ' ' . $state . ' ' . $country;
    $metadata_text = $weather; // Only weather, no time context
    
    // Remove tags that are substrings of other tags (less specific tags)
    $filtered_tags = exif_harvester_filter_substring_tags($tags);
    
    // Debug: Log filtering steps
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("EXIF SEO Debug - Post $post_id: After substring filtering: " . implode(', ', $filtered_tags));
    }
    
    $scored_tags = [];
    
    foreach ($filtered_tags as $tag) {
        $tag_lower = strtolower($tag);
        $score = 0;
        
        // Skip blacklisted tags
        if (exif_harvester_is_tag_blacklisted($tag_lower)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EXIF SEO Debug - Post $post_id: Tag '$tag' blacklisted");
            }
            continue;
        }
        
        // Skip tags that are too short/long
        if (strlen($tag) <= 2 || strlen($tag) >= 25) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EXIF SEO Debug - Post $post_id: Tag '$tag' length invalid (" . strlen($tag) . " chars)");
            }
            continue;
        }
        
        // Check for location redundancy - exclude tags that duplicate location info
        $location_redundant = exif_harvester_is_location_redundant_tag($tag_lower, $location, $city, $state, $country);
        if ($location_redundant) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EXIF SEO Debug - Post $post_id: Tag '$tag' location redundant");
            }
            continue; // Skip this tag to avoid repetition
        }
        
        // Skip temporal redundancy check - not using time context for SEO
        
        // Get user-managed standard bonus terms (fallback to defaults if empty)
        $high_value_terms = get_option('exif_harvester_seo_standard_terms', [
            // Photography Genres & Styles (high search volume)
            'landscape', 'nature', 'wildlife', 'macro', 'portrait', 'documentary', 'street photography',
            'aerial', 'drone', 'panoramic', 'black and white', 'monochrome', 'HDR', 'long exposure',
            'night photography', 'astrophotography', 'milky way', 'stars', 'sunrise', 'sunset', 'golden hour',
            'fine art', 'conceptual', 'abstract', 'minimalism', 'minimalist',
            
            // Architecture & Urban (specific search terms)
            'architecture', 'architectural', 'modern', 'contemporary', 'mid-century modern', 'brutalist',
            'art deco', 'gothic', 'victorian', 'industrial', 'urban', 'cityscape', 'skyline',
            'skyscrapers', 'office buildings', 'downtown', 'business district', 'historic district',
            
            // Nature & Outdoor Photography
            'mountains', 'peaks', 'summit', 'valley', 'forest', 'trees', 'wilderness', 'national park',
            'state park', 'hiking', 'trail', 'waterfall', 'river', 'lake', 'ocean', 'beach', 'coastline',
            'desert', 'canyon', 'rock formation', 'geological', 'seasonal', 'autumn', 'fall foliage',
            'spring', 'winter', 'snow', 'ice', 'frozen',
            
            // Wildlife & Animals (high interest)
            'birds', 'bird photography', 'raptors', 'eagles', 'hawks', 'owls', 'waterfowl', 'songbirds',
            'mammals', 'deer', 'elk', 'bear', 'mountain goat', 'bighorn sheep', 'wildlife refuge',
            'migration', 'nesting', 'feeding', 'behavior',
            
            // Cultural & Historical (tourism/travel searches)
            'historic', 'heritage', 'landmark', 'monument', 'museum', 'cultural', 'traditional',
            'archaeological', 'ruins', 'vintage', 'antique', 'restoration', 'preservation',
            
            // Events & Activities (people search for these)
            'festival', 'concert', 'performance', 'sports', 'recreation', 'outdoor recreation',
            'camping', 'backpacking', 'climbing', 'skiing', 'cycling', 'running', 'marathon',
            
            // Artistic & Creative (art buyers/enthusiasts)
            'art', 'artistic', 'creative', 'design', 'sculpture', 'mural', 'street art', 'graffiti',
            'installation', 'gallery', 'exhibition', 'studio', 'workshop',
            
            // Travel & Tourism (huge search category)
            'travel', 'tourism', 'destination', 'scenic', 'viewpoint', 'overlook', 'vista',
            'roadtrip', 'adventure', 'exploration', 'discovery', 'hidden gem', 'local',
            
            // Weather & Atmospheric (mood searches)
            'storm', 'lightning', 'rainbow', 'fog', 'mist', 'dramatic', 'moody', 'atmospheric',
            'reflection', 'silhouette', 'backlit', 'dramatic lighting',
            
            // Seasonal & Holiday (timely searches)
            'christmas', 'holiday', 'seasonal decorations', 'festival of lights', 'celebration',
            'memorial day', 'independence day', 'thanksgiving', 'new year'
        ]);
        
        // Tiered bonus system for SEO value with variation support - user manageable
        $premium_terms = get_option('exif_harvester_seo_premium_terms', ['landscape', 'nature', 'wildlife', 'portrait', 'macro', 'architecture', 'sunset', 'sunrise']);
        $high_terms = get_option('exif_harvester_seo_high_terms', ['mountain', 'forest', 'waterfall', 'beach', 'cityscape', 'historic', 'travel', 'bird']);
        
        // Helper function to check term variations
        $matches_term_variations = function($tag_lower, $term) {
            // Direct match
            if (strpos($tag_lower, $term) !== false) return true;
            
            // Check variations
            $variations = exif_harvester_get_word_variations($term);
            foreach ($variations as $variation) {
                if (strpos($tag_lower, strtolower($variation)) !== false) return true;
            }
            
            // Check if tag contains the term as a word boundary
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $tag_lower)) return true;
            
            return false;
        };
        
        // Premium bonus (15 points) - highest search volume photography terms
        foreach ($premium_terms as $premium_term) {
            if ($matches_term_variations($tag_lower, $premium_term)) {
                $score += 15;
                break;
            }
        }
        
        // High value bonus (12 points) - strong search terms  
        foreach ($high_terms as $high_term) {
            if ($matches_term_variations($tag_lower, $high_term)) {
                $score += 12;
                break;
            }
        }
        
        // Standard bonus (8 points) - all other valuable photography terms
        foreach ($high_value_terms as $valuable_term) {
            if ($matches_term_variations($tag_lower, $valuable_term)) {
                $score += 8;
                break;
            }
        }
        
        // Score based on different content areas (weighted by importance)
        
        // Enhanced matching with substring and word variation support
        
        // Split tag into individual words for better matching
        $tag_words = preg_split('/[\s\-_&,]+/', $tag_lower, -1, PREG_SPLIT_NO_EMPTY);
        
        // Title matches (highest weight - 25 points for full tag, 15 per word)
        if (strpos($title, $tag_lower) !== false) {
            $score += 25; // Full tag match in title
        } else {
            // Check individual words in title
            foreach ($tag_words as $word) {
                if (strlen($word) > 2 && strpos($title, $word) !== false) {
                    $score += 15;
                    break; // Only count one word match to avoid double scoring
                }
            }
        }
        
        // Content matching with word variations
        $content_score = 0;
        
        // Check for full tag match first (15 points)
        if (preg_match('/\b' . preg_quote($tag_lower, '/') . '\b/', $content)) {
            $content_score = 15;
        } elseif (strpos($content, $tag_lower) !== false) {
            $content_score = 12; // Partial full tag match
        } else {
            // Check individual words and their variations
            foreach ($tag_words as $word) {
                if (strlen($word) <= 2) continue; // Skip very short words
                
                // Check for exact word match
                if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $content)) {
                    $content_score = max($content_score, 10);
                }
                
                // Check for word variations (singular/plural, common suffixes)
                $variations = exif_harvester_get_word_variations($word);
                foreach ($variations as $variation) {
                    if (preg_match('/\b' . preg_quote($variation, '/') . '\b/', $content)) {
                        $content_score = max($content_score, 8);
                        break;
                    }
                }
                
                // Check for substring match as last resort
                if ($content_score == 0 && strpos($content, $word) !== false) {
                    $content_score = max($content_score, 5);
                }
            }
        }
        $score += $content_score;
        
        // Excerpt matches with similar logic (12 points for full, 8 for words)
        if (strpos($excerpt, $tag_lower) !== false) {
            $score += 12;
        } else {
            foreach ($tag_words as $word) {
                if (strlen($word) > 2 && strpos($excerpt, $word) !== false) {
                    $score += 8;
                    break; // Only count one word match to avoid double scoring
                }
            }
        }
        
        // Metadata context matches (lower weight - 5 points each)
        if (strpos($metadata_text, $tag_lower) !== false) {
            $score += 5;
        }
        
        // Bonus for photography-specific terms (if not blacklisted)
        $photography_terms = [
            'landscape', 'portrait', 'macro', 'wildlife', 'street', 'architecture', 
            'sunset', 'sunrise', 'ocean', 'mountain', 'nature', 'urban', 'cityscape',
            'seascape', 'forest', 'desert', 'river', 'lake', 'beach', 'cliff',
            'building', 'bridge', 'tower', 'church', 'historic', 'modern',
            'golden hour', 'blue hour', 'storm', 'clouds', 'reflection'
        ];
        if (in_array($tag_lower, $photography_terms)) {
            $score += 3;
        }
        
        // Give minimum score to photography-related tags even if no content matches
        if ($score == 0) {
            $photography_bonus_terms = [
                'landscape', 'landscapes', 'portrait', 'macro', 'wildlife', 'street', 'architecture', 
                'sunset', 'sunrise', 'sunrises', 'ocean', 'mountain', 'mountains', 'nature', 'urban', 'cityscape',
                'seascape', 'forest', 'forests', 'desert', 'river', 'lake', 'beach', 'cliff',
                'building', 'bridge', 'tower', 'church', 'historic', 'modern', 'trees', 'tree',
                'golden hour', 'blue hour', 'storm', 'clouds', 'sky', 'reflection', 'water',
                'evergreens', 'pine', 'dawn', 'evening', 'morning', 'afternoon'
            ];
            if (in_array($tag_lower, $photography_bonus_terms)) {
                $score = 1; // Minimum score for relevant photography tags
            }
        }
        
        // Debug: Log tag scoring
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EXIF SEO Debug - Post $post_id: Tag '$tag' scored $score points");
        }
        
        // Only include tags with meaningful scores
        if ($score > 0) {
            $scored_tags[] = [
                'tag' => $tag,
                'score' => $score
            ];
        }
    }
    
    // Sort by score (highest first)
    usort($scored_tags, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Return just the tag names in order
    return array_map(function($item) {
        return $item['tag'];
    }, $scored_tags);
}

/**
 * Generate SEO-optimized meta description based on post metadata and content
 * @param int $post_id Post ID
 * @return string Optimized meta description
 */
function exif_harvester_generate_seo_meta_description($post_id) {
    $elements = [];
    $max_length = 155; // Google's recommended meta description length
    
    // Get location information using helper functions
    $location = exif_harvester_get_post_location($post_id);
    $city = exif_harvester_get_post_city($post_id);
    $state = exif_harvester_get_post_state($post_id);
    $country = exif_harvester_get_post_country($post_id);
    
    // Build location string with redundancy removal
    $location_parts = [];
    if ($location) {
        $location_parts[] = $location;
    }
    if ($city) {
        $location_parts[] = $city;
    }
    if ($state) {
        $location_parts[] = $state;
    }
    
    // Clean redundant location components (e.g., remove "Dallas" if "Downtown Dallas" exists)
    $cleaned_location_parts = exif_harvester_clean_redundant_location_parts($location_parts);
    $location_string = implode(', ', array_filter($cleaned_location_parts));
    
    // Skip time context - not valuable for SEO compared to tags/location
    
    // Get weather conditions
    $weather = get_post_meta($post_id, 'wXSummary', true);
    if ($weather && $weather !== 'Unknown' && $weather !== 'Clear' && trim($weather) !== '') {
        $weather = strtolower(trim($weather));
    } else {
        $weather = null; // Normalize non-useful weather values
    }
    
    // Skip camera/lens info - not useful for SEO descriptions
    
    // Skip technical details - not useful for SEO descriptions
    
    // Get tags and score them by content relevance
    $tags = wp_get_post_tags($post_id, ['fields' => 'names']);
    $relevant_tags = [];
    if (!empty($tags)) {
        $scored_tags = exif_harvester_score_tags_by_relevance($post_id, $tags);
        // Get top 7 most relevant tags for maximum subject matter coverage
        $relevant_tags = array_slice($scored_tags, 0, 7);
        
        // Debug: Log tag processing for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EXIF SEO Debug - Post $post_id: Raw tags: " . implode(', ', $tags));
            error_log("EXIF SEO Debug - Post $post_id: Scored tags: " . implode(', ', $scored_tags));
            error_log("EXIF SEO Debug - Post $post_id: Relevant tags: " . implode(', ', $relevant_tags));
        }
    } else {
        // Debug: Log when no tags found
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EXIF SEO Debug - Post $post_id: No tags found");
        }
    }
    
    // Build description variants and choose the best one
    $variants = [];
    
    // Variant 1: Maximum tags (5 tags when available) - HIGHEST PRIORITY
    if (count($relevant_tags) >= 3) {
        $tag_string = implode(', ', array_slice($relevant_tags, 0, 5));
        $desc = ucfirst($tag_string) . " photography";
        if ($location_string && strlen($desc) < 100) {
            $seo_location = exif_harvester_build_seo_location($location, $city, $state, $country, 30);
            if ($seo_location) {
                $desc .= " from " . $seo_location;
            }
        }
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Variant 1.5: Four tags variant
    if (count($relevant_tags) >= 4) {
        $tag_string = implode(', ', array_slice($relevant_tags, 0, 4));
        $desc = ucfirst($tag_string) . " photography";
        if (($city || $state) && strlen($desc) < 120) {
            $seo_location = exif_harvester_build_seo_location($location, $city, $state, $country, 35);
            if ($seo_location) {
                $desc .= " from " . $seo_location;
            }
        }
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Variant 2: Tags + Location (when good tags available) - NO TIME CONTEXT
    if (!empty($relevant_tags) && $location_string) {
        $tag_string = implode(' and ', array_slice($relevant_tags, 0, 2));
        $desc = ucfirst($tag_string) . " photography from " . $location_string;
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Variant 2.1: Three tags + location (prioritize subject matter)
    if (count($relevant_tags) >= 3 && $location_string) {
        $tag_string = implode(', ', array_slice($relevant_tags, 0, 3));
        $desc = ucfirst($tag_string) . " photography from " . $location_string;
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Variant 2: Tags + Context focus (equipment secondary)
    if (!empty($relevant_tags)) {
        $tag_string = implode(' and ', array_slice($relevant_tags, 0, 2));
        $desc = ucfirst($tag_string) . " photography";
        if ($location_string) {
            // Use shorter location format for tags if original is too long
            $location_for_tags = $location_string;
            if (strlen($location_string) > 40 && $city && $state) {
                $location_for_tags = $city . ", " . $state;
            } elseif (strlen($location_string) > 50 && $city) {
                $location_for_tags = $city;
            }
            $desc .= " from " . $location_for_tags;
        }
        // More space available without time context - could add more tags or keep concise
        if ($weather && strlen($desc) < 140) {
            $desc .= " with " . $weather . " conditions";
        }
        // Skip camera equipment - no SEO value
        $desc .= ".";
        $variants[] = $desc;
        
        // Debug: Log variant 2 creation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EXIF SEO Debug - Post $post_id: Created Variant 2 (Tags+Context): $desc");
        }
    }
    
    // Variant 2.1: Rich tag variant (3 tags when available and high scoring)
    if (count($relevant_tags) >= 3 && !empty($location_string)) {
        $tag_string = implode(', ', array_slice($relevant_tags, 0, 3));
        $desc = ucfirst($tag_string) . " photography";
        
        // Add location if short enough
        if ($city && strlen($city) < 20) {
            $desc .= " from " . $city;
        } elseif (strlen($location_string) < 30) {
            $desc .= " from " . $location_string;
        }
        
        // More space for additional content without time context
        
        $desc .= ".";
        $variants[] = $desc;
        
        // Debug: Log variant 2.1 creation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EXIF SEO Debug - Post $post_id: Created Variant 2.1 (3-Tags): $desc");
        }
    }
    
    // Variant 2.5: More tags with minimal location - MAXIMUM TAG FOCUS
    if (count($relevant_tags) >= 4) {
        $tag_string = implode(', ', array_slice($relevant_tags, 0, 4));
        $desc = ucfirst($tag_string) . " photography";
        
        // Add most concise location if space allows
        if (($city || $state) && strlen($desc) < 130) {
            $seo_location = exif_harvester_build_seo_location($location, $city, $state, $country, 20);
            if ($seo_location) {
                $desc .= " from " . $seo_location;
            }
        }
        
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Variant 3: Location-first with multiple tags (when location is strong)
    if ($location_string && count($relevant_tags) >= 3) {
        $tag_string = implode(', ', array_slice($relevant_tags, 0, 3));
        $desc = $location_string . " " . $tag_string . " photography";
        $desc .= ".";
        $variants[] = $desc;
    }
        
    
    // Variant 4: Enhanced tag-focused with optimal location (more space without time context)
    if (count($relevant_tags) >= 2) {
        // Use more tags since we have more space
        $tag_count = min(4, count($relevant_tags));
        if ($tag_count >= 3) {
            $tag_string = implode(', ', array_slice($relevant_tags, 0, $tag_count));
        } else {
            $tag_string = implode(' and ', array_slice($relevant_tags, 0, $tag_count));
        }
        $desc = "Beautiful " . $tag_string . " photography";
        if (($city || $state) && strlen($desc) < 120) {
            $seo_location = exif_harvester_build_seo_location($location, $city, $state, $country, 30);
            if ($seo_location) {
                $desc .= " from " . $seo_location;
            }
        }
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Variant 5: Ultra tag-focused (maximize tag usage without time context)
    if (count($relevant_tags) >= 5) {
        $tag_string = implode(', ', array_slice($relevant_tags, 0, 7)); // Use up to 7 tags
        $desc = "Photography featuring " . $tag_string;
        if (($city || $state) && strlen($desc) < 140) {
            $seo_location = exif_harvester_build_seo_location($location, $city, $state, $country, 20);
            if ($seo_location) {
                $desc .= " from " . $seo_location;
            }
        }
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Variant 6: Tag + State focus (prioritize broader geographic reach)
    if (count($relevant_tags) >= 2 && $state) {
        $tag_string = implode(' and ', array_slice($relevant_tags, 0, 2));
        $desc = ucfirst($tag_string) . " photography from " . $state;
        if (strlen($desc) < 120 && count($relevant_tags) >= 3) {
            $additional_tag = $relevant_tags[2];
            $desc = ucfirst($tag_string) . " and " . $additional_tag . " photography from " . $state;
        }
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Variant 7: Maximum tag utilization - NO TIME/WEATHER CONTEXT
    if (!empty($relevant_tags)) {
        if (count($relevant_tags) >= 5) {
            $desc = ucfirst(implode(', ', array_slice($relevant_tags, 0, 5))) . " photography";
        } elseif (count($relevant_tags) >= 3) {
            $desc = ucfirst(implode(', ', array_slice($relevant_tags, 0, 4))) . " photography";
        } elseif (count($relevant_tags) >= 2) {
            $desc = ucfirst(implode(' and ', array_slice($relevant_tags, 0, 3))) . " photography";
        } else {
            $desc = ucfirst($relevant_tags[0]) . " photography";
        }
        
        if ($location_string && strlen($desc) < 130) {
            $seo_location = exif_harvester_build_seo_location($location, $city, $state, $country, 40);
            if ($seo_location) {
                $desc .= " from " . $seo_location;
            }
        }
        
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Final fallback: Use any available tags, then location, then title
    if (empty($variants)) {
        if (!empty($relevant_tags)) {
            $tag_string = implode(' and ', array_slice($relevant_tags, 0, 2));
            $desc = ucfirst($tag_string) . " photography";
            if ($location_string && strlen($desc) < 120) {
                $seo_location = exif_harvester_build_seo_location($location, $city, $state, $country, 40);
                if ($seo_location) {
                    $desc .= " from " . $seo_location;
                }
            }
        } elseif ($location_string) {
            $seo_location = exif_harvester_build_seo_location($location, $city, $state, $country, 50);
            $desc = "Photography from " . ($seo_location ?: $location_string);
        } else {
            $post_title = get_the_title($post_id);
            $desc = "Photography: " . wp_trim_words($post_title, 10);
        }
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Choose the best variant (prioritize concise descriptions with good location data)
    $best_description = '';
    $best_score = 0;
    
    foreach ($variants as $variant) {
        $length = strlen($variant);
        $score = 0;
        
        // Heavy penalty for exceeding 155 characters
        if ($length > $max_length) {
            $score -= 10;
        }
        
        // Bonus for optimal length (120-155 characters)
        if ($length >= 120 && $length <= $max_length) {
            $score += 5;
        } elseif ($length >= 100 && $length <= 155) {
            $score += 3;
        } elseif ($length >= 80 && $length <= 155) {
            $score += 1;
        }
        
        // Score based on information richness - TAGS GET HIGHEST WEIGHT
        // Check for tag inclusion (highest priority)
        $tag_count = 0;
        foreach ($relevant_tags as $tag) {
            if (stripos($variant, $tag) !== false) {
                $tag_count++;
            }
        }
        $score += $tag_count * 12; // MAXIMUM weight for tags (12 points each - subject matter is king!)
        
        // Location and other metadata scoring
        if (strpos($variant, $location_string) !== false) $score += 6; // Good weight for location
        
        // Extra bonus for including state information (great for local SEO)
        if ($state && strpos($variant, $state) !== false) $score += 3;
        
        // Extra bonus for city + state combo (ideal for local SEO)
        if ($city && $state && strpos($variant, $city) !== false && strpos($variant, $state) !== false) $score += 2;
        
        // Minimal weather scoring only (no time context at all)
        if ($weather && strpos($variant, $weather) !== false) $score += 0.3; // Reduced from 0.5
        // Removed camera scoring - no SEO value
        
        // Combination bonuses - prioritize tags heavily
        if ($tag_count > 0 && strpos($variant, $location_string) !== false) {
            $score += 8; // HUGE bonus for tags + location (subject matter + place)
        }
        if ($tag_count >= 3) {
            $score += 5; // Extra bonus for multiple relevant tags
        }
        // Removed camera/equipment bonuses since camera info has no SEO value
        
        // Penalty for generic endings
        if (strpos($variant, 'Explore detailed photography') !== false) $score -= 3;
        if (strpos($variant, 'Experience the mood') !== false) $score -= 2;
        if (strpos($variant, 'High-resolution images') !== false) $score -= 2;
        
        // Prefer more specific location info
        if ($city && $state && (strpos($variant, $city) !== false || strpos($variant, $state) !== false)) {
            $score += 1;
        }
        
        if ($score > $best_score) {
            $best_score = $score;
            $best_description = $variant;
        }
        
        // Debug: Log variant scoring
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EXIF SEO Debug - Post $post_id: Variant '$variant' scored $score points (tags: $tag_count, length: $length)");
        }
    }
    
    // If no variant fits, truncate the best one
    if (empty($best_description) && !empty($variants)) {
        $best_description = $variants[0];
        if (strlen($best_description) > $max_length) {
            $best_description = wp_trim_words($best_description, 23, '...');
        }
    }
    
    // Debug: Log final selection
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("EXIF SEO Debug - Post $post_id: Final selection '$best_description' with score $best_score");
    }
    
    return $best_description;
}