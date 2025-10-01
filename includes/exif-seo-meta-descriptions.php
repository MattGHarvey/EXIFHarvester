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
 * Get location name for a post from hierarchical place taxonomy first, then meta fallback
 * 
 * @param int $post_id Post ID
 * @return string|null Location name or null if not found
 */
function exif_harvester_get_post_location($post_id) {
    // First check place taxonomy - handle hierarchical structure
    $place_terms = wp_get_post_terms($post_id, 'place', array('orderby' => 'parent'));
    if (!empty($place_terms)) {
        // Debug hierarchical taxonomy structure
        error_log("EXIF SEO - Post $post_id place terms found: " . count($place_terms));
        foreach ($place_terms as $term) {
            error_log("EXIF SEO - Term: " . $term->name . " (ID: " . $term->term_id . ", Parent: " . $term->parent . ")");
        }
        
        // Find the most specific location (deepest level, no children)
        $most_specific = null;
        foreach ($place_terms as $term) {
            // Check if this term has children
            $children = get_term_children($term->term_id, 'place');
            if (empty($children) || is_wp_error($children)) {
                // No children, this is likely the most specific location
                $most_specific = $term;
                error_log("EXIF SEO - Most specific location found: " . $term->name);
                break;
            }
        }
        
        if ($most_specific) {
            return $most_specific->name;
        } else {
            // Fallback to first term if we can't determine specificity
            return $place_terms[0]->name;
        }
    }
    
    // Fallback to post meta if no place taxonomy
    $location = get_post_meta($post_id, 'location', true);
    if ($location) {
        return $location;
    }
    
    return null;
}

/**
 * Get city name for a post from hierarchical place taxonomy first, then meta fallback
 * 
 * @param int $post_id Post ID
 * @return string|null City name or null if not found
 */
function exif_harvester_get_post_city($post_id) {
    // First check place taxonomy - find city level in hierarchy
    $place_terms = wp_get_post_terms($post_id, 'place', array('orderby' => 'parent'));
    if (!empty($place_terms)) {
        // Get the full hierarchy for the most specific term
        $most_specific = null;
        foreach ($place_terms as $term) {
            $children = get_term_children($term->term_id, 'place');
            if (empty($children) || is_wp_error($children)) {
                $most_specific = $term;
                break;
            }
        }
        
        if ($most_specific) {
            // Get ancestors to build hierarchy: Country > State > City > Location
            $ancestors = get_ancestors($most_specific->term_id, 'place');
            $hierarchy = array_reverse($ancestors); // Reverse to get top-down order
            $hierarchy[] = $most_specific->term_id; // Add the specific location
            
            // Debug hierarchy structure
            error_log("EXIF SEO - Post $post_id hierarchy for city lookup:");
            foreach ($hierarchy as $index => $term_id) {
                $term = get_term($term_id, 'place');
                if ($term && !is_wp_error($term)) {
                    error_log("EXIF SEO - Level $index: " . $term->name . " (ID: $term_id)");
                }
            }
            
            // Assuming hierarchy: Country (0) > State (1) > City (2) > Location (3)
            // City should be at index 2 (third level)
            if (isset($hierarchy[2])) {
                $city_term = get_term($hierarchy[2], 'place');
                if ($city_term && !is_wp_error($city_term)) {
                    error_log("EXIF SEO - Post $post_id found city: " . $city_term->name);
                    return $city_term->name;
                }
            }
        }
    }
    
    // Fallback to post meta if no place taxonomy
    $city = get_post_meta($post_id, 'city', true);
    if ($city) {
        return $city;
    }
    
    return null;
}

/**
 * Get state name for a post from hierarchical place taxonomy first, then meta fallback
 * 
 * @param int $post_id Post ID
 * @return string|null State name or null if not found
 */
function exif_harvester_get_post_state($post_id) {
    // First check place taxonomy - find state level in hierarchy
    $place_terms = wp_get_post_terms($post_id, 'place', array('orderby' => 'parent'));
    if (!empty($place_terms)) {
        // Get the full hierarchy for the most specific term
        $most_specific = null;
        foreach ($place_terms as $term) {
            $children = get_term_children($term->term_id, 'place');
            if (empty($children) || is_wp_error($children)) {
                $most_specific = $term;
                break;
            }
        }
        
        if ($most_specific) {
            // Get ancestors to build hierarchy: Country > State > City > Location
            $ancestors = get_ancestors($most_specific->term_id, 'place');
            $hierarchy = array_reverse($ancestors);
            $hierarchy[] = $most_specific->term_id;
            
            // State should be at index 1 (second level)
            if (isset($hierarchy[1])) {
                $state_term = get_term($hierarchy[1], 'place');
                if ($state_term && !is_wp_error($state_term)) {
                    error_log("EXIF SEO - Post $post_id found state: " . $state_term->name);
                    return $state_term->name;
                }
            }
        }
    }
    
    // Fallback to post meta if no place taxonomy
    $state = get_post_meta($post_id, 'state', true);
    if ($state) {
        return $state;
    }
    
    return null;
}

/**
 * Get country name for a post from hierarchical place taxonomy first, then meta fallback
 * 
 * @param int $post_id Post ID
 * @return string|null Country name or null if not found
 */
function exif_harvester_get_post_country($post_id) {
    // First check place taxonomy - find country level in hierarchy
    $place_terms = wp_get_post_terms($post_id, 'place', array('orderby' => 'parent'));
    if (!empty($place_terms)) {
        // Get the full hierarchy for the most specific term
        $most_specific = null;
        foreach ($place_terms as $term) {
            $children = get_term_children($term->term_id, 'place');
            if (empty($children) || is_wp_error($children)) {
                $most_specific = $term;
                break;
            }
        }
        
        if ($most_specific) {
            // Get ancestors to build hierarchy: Country > State > City > Location
            $ancestors = get_ancestors($most_specific->term_id, 'place');
            $hierarchy = array_reverse($ancestors);
            $hierarchy[] = $most_specific->term_id;
            
            // Country should be at index 0 (top level)
            if (isset($hierarchy[0])) {
                $country_term = get_term($hierarchy[0], 'place');
                if ($country_term && !is_wp_error($country_term)) {
                    return $country_term->name;
                }
            }
        }
    }
    
    // Fallback to post meta if no place taxonomy
    $country = get_post_meta($post_id, 'country', true);
    if ($country) {
        return $country;
    }
    
    return null;
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
 * Check if a tag has SEO bonus value
 * @param string $tag_lower Lowercase tag to check
 * @return bool True if tag has bonus value
 */
function exif_harvester_tag_has_seo_bonus($tag_lower) {
    $premium_terms = get_option('exif_harvester_seo_premium_terms', ['landscape', 'nature', 'wildlife', 'portrait', 'macro', 'architecture', 'sunset', 'sunrise']);
    $high_terms = get_option('exif_harvester_seo_high_terms', ['mountain', 'forest', 'waterfall', 'beach', 'cityscape', 'historic', 'travel', 'bird']);
    $standard_terms = get_option('exif_harvester_seo_standard_terms', []);
    
    $all_bonus_terms = array_merge($premium_terms, $high_terms, $standard_terms);
    foreach ($all_bonus_terms as $bonus_term) {
        if (strpos($tag_lower, strtolower($bonus_term)) !== false || 
            strpos(strtolower($bonus_term), $tag_lower) !== false ||
            preg_match('/\b' . preg_quote(strtolower($bonus_term), '/') . '\b/', $tag_lower) ||
            preg_match('/\b' . preg_quote($tag_lower, '/') . '\b/', strtolower($bonus_term))) {
            return true;
        }
    }
    return false;
}

/**
 * Build hierarchical location string from place taxonomy for deeper SEO context
 * Prioritizes "Location, City, State" format from taxonomy hierarchy
 * 
 * @param int $post_id Post ID
 * @return string|null Hierarchical location string or null if can't build
 */
function exif_harvester_build_hierarchical_location($post_id) {
    // Get place taxonomy terms
    $place_terms = wp_get_post_terms($post_id, 'place', array('orderby' => 'parent'));
    if (empty($place_terms)) {
        error_log("EXIF SEO - Post $post_id: No place terms found");
        return null;
    }
    
    // Find the most specific term (leaf node)
    $most_specific = null;
    foreach ($place_terms as $term) {
        $children = get_term_children($term->term_id, 'place');
        if (empty($children) || is_wp_error($children)) {
            $most_specific = $term;
            break;
        }
    }
    
    if (!$most_specific) {
        error_log("EXIF SEO - Post $post_id: No specific term found");
        return null;
    }
    
    // Get the full hierarchy: Country > State > City > Location
    $ancestors = get_ancestors($most_specific->term_id, 'place');
    $hierarchy = array_reverse($ancestors); // Top-down order
    $hierarchy[] = $most_specific->term_id; // Add the specific location
    
    error_log("EXIF SEO - Post $post_id hierarchical location build:");
    $hierarchy_terms = [];
    foreach ($hierarchy as $index => $term_id) {
        $term = get_term($term_id, 'place');
        if ($term && !is_wp_error($term)) {
            $hierarchy_terms[] = $term->name;
            error_log("EXIF SEO - Level $index: " . $term->name . " (ID: $term_id)");
        }
    }
    
    // Build "Location, City, State" from the lowest 3 levels with redundancy detection
    // Hierarchy typically: [Country, State, City, Location]
    $total_levels = count($hierarchy_terms);
    
    if ($total_levels >= 3) {
        // Take the bottom 3: Location (last), City (second to last), State (third to last)
        $location_part = $hierarchy_terms[$total_levels - 1]; // Most specific (location)
        $city_part = $hierarchy_terms[$total_levels - 2];     // City
        $state_part = $hierarchy_terms[$total_levels - 3];    // State
        
        // Check for redundancy - prioritize more specific location over city
        $final_parts = [$location_part];
        
        // Add city only if it doesn't create redundancy with location
        if (!exif_harvester_is_location_term_redundant($location_part, $city_part)) {
            $final_parts[] = $city_part;
            error_log("EXIF SEO - Post $post_id: City '$city_part' added (not redundant with location '$location_part')");
        } else {
            error_log("EXIF SEO - Post $post_id: City '$city_part' SKIPPED (redundant with location '$location_part')");
        }
        
        // Always add state (least likely to be redundant and important for SEO)
        $final_parts[] = $state_part;
        
        $result = implode(', ', $final_parts);
        error_log("EXIF SEO - Post $post_id built hierarchical location: " . $result);
        return $result;
    } elseif ($total_levels == 2) {
        // Only 2 levels: check for redundancy between them
        $term1 = $hierarchy_terms[1]; // More specific
        $term2 = $hierarchy_terms[0]; // Less specific
        
        if (!exif_harvester_is_location_term_redundant($term1, $term2)) {
            $result = $term1 . ', ' . $term2;
            error_log("EXIF SEO - Post $post_id built 2-level location: " . $result);
            return $result;
        } else {
            // Use only the more specific term
            error_log("EXIF SEO - Post $post_id: Using only specific term '$term1' (redundant with '$term2')");
            return $term1;
        }
    } elseif ($total_levels == 1) {
        // Only 1 level: use it but try to enhance with meta data
        $single_term = $hierarchy_terms[0];
        
        // Try to enhance with city and state from meta if available (with redundancy check)
        $meta_city = get_post_meta($post_id, 'city', true);
        $meta_state = get_post_meta($post_id, 'state', true);
        
        $final_parts = [$single_term];
        
        if (!empty($meta_city) && !exif_harvester_is_location_term_redundant($single_term, $meta_city)) {
            $final_parts[] = $meta_city;
            error_log("EXIF SEO - Post $post_id: Added city meta '$meta_city' (not redundant)");
        } elseif (!empty($meta_city)) {
            error_log("EXIF SEO - Post $post_id: Skipped city meta '$meta_city' (redundant with '$single_term')");
        }
        
        if (!empty($meta_state)) {
            $final_parts[] = $meta_state;
            error_log("EXIF SEO - Post $post_id: Added state meta '$meta_state'");
        }
        
        $result = implode(', ', $final_parts);
        error_log("EXIF SEO - Post $post_id enhanced single term result: " . $result);
        return $result;
    }
    
    error_log("EXIF SEO - Post $post_id: Could not build hierarchical location");
    return null;
}

/**
 * Check if two location terms are redundant (one contains the other)
 * Prioritizes more specific terms over general ones
 * 
 * @param string $specific_term The more specific location term
 * @param string $general_term The more general location term  
 * @return bool True if terms are redundant
 */
function exif_harvester_is_location_term_redundant($specific_term, $general_term) {
    if (empty($specific_term) || empty($general_term)) {
        return false;
    }
    
    $specific_lower = strtolower(trim($specific_term));
    $general_lower = strtolower(trim($general_term));
    
    // Exact match - definitely redundant
    if ($specific_lower === $general_lower) {
        return true;
    }
    
    // Check if the general term is contained within the specific term
    // Example: "Downtown Dallas" contains "Dallas" 
    if (strpos($specific_lower, $general_lower) !== false) {
        return true;
    }
    
    // Check for word-level containment to avoid false positives
    // Split both terms into words and check for complete word matches
    $specific_words = preg_split('/[\s,\-\.]+/', $specific_lower, -1, PREG_SPLIT_NO_EMPTY);
    $general_words = preg_split('/[\s,\-\.]+/', $general_lower, -1, PREG_SPLIT_NO_EMPTY);
    
    // If general term is a subset of specific term's words, it's redundant
    $general_word_count = count($general_words);
    $matching_words = 0;
    
    foreach ($general_words as $general_word) {
        foreach ($specific_words as $specific_word) {
            if ($general_word === $specific_word) {
                $matching_words++;
                break;
            }
        }
    }
    
    // If most or all words from general term appear in specific term, consider redundant
    $redundancy_threshold = $general_word_count > 1 ? 0.8 : 1.0; // 80% for multi-word, 100% for single word
    if ($matching_words / $general_word_count >= $redundancy_threshold) {
        return true;
    }
    
    return false;
}

/**
 * Build optimal location string for SEO with state priority, avoiding terms already used as tags
 * @param string $location Main location
 * @param string $city City name
 * @param string $state State name
 * @param string $country Country name
 * @param array $used_tags Tags already included in description
 * @param int $max_length Maximum length for the location string
 * @return string Optimized location string
 */
function exif_harvester_build_seo_location($location, $city, $state, $country, $max_length = 40, $used_tags = []) {
    // Helper function to check if location component is already covered by tags
    $is_covered_by_tags = function($location_part) use ($used_tags) {
        if (empty($location_part) || empty($used_tags)) return false;
        
        $location_lower = strtolower($location_part);
        foreach ($used_tags as $tag) {
            $tag_lower = strtolower($tag);
            // Check if location is substantially covered by this tag
            if (strpos($tag_lower, $location_lower) !== false || 
                strpos($location_lower, $tag_lower) !== false) {
                return true;
            }
        }
        return false;
    };
    
    // Helper function to check if city info is already in location
    $city_in_location = function($location, $city) {
        if (empty($location) || empty($city)) return false;
        return strpos(strtolower($location), strtolower($city)) !== false;
    };
    
    // Helper function to check if state info is already in location
    $state_in_location = function($location, $state) {
        if (empty($location) || empty($state)) return false;
        $location_lower = strtolower($location);
        $state_lower = strtolower($state);
        // Check full state name and common abbreviations
        $state_variations = [$state_lower];
        $state_abbrevs = [
            'texas' => 'tx', 'california' => 'ca', 'new york' => 'ny', 
            'florida' => 'fl', 'illinois' => 'il', 'pennsylvania' => 'pa',
            'ohio' => 'oh', 'georgia' => 'ga', 'north carolina' => 'nc',
            'michigan' => 'mi', 'new jersey' => 'nj', 'virginia' => 'va'
        ];
        if (isset($state_abbrevs[$state_lower])) {
            $state_variations[] = $state_abbrevs[$state_lower];
        }
        foreach ($state_variations as $variant) {
            if (strpos($location_lower, $variant) !== false) {
                return true;
            }
        }
        return false;
    };
    
    $city_covered = $is_covered_by_tags($city);
    $location_covered = $is_covered_by_tags($location);
    $city_already_in_location = $city_in_location($location, $city);
    $state_already_in_location = $state_in_location($location, $state);
    
    // Smart location building with redundancy detection - PRIORITIZE DEEP CONTEXT
    
    // PRIORITY 1: Always try for full "Location, City, State" when all available (best SEO)
    if ($location && $city && $state && !$city_covered && !$location_covered && 
        !$city_already_in_location && !$state_already_in_location) {
        
        // Check for redundancy between location and city - prioritize location
        $final_parts = [$location];
        if (!exif_harvester_is_location_term_redundant($location, $city)) {
            $final_parts[] = $city;
        }
        $final_parts[] = $state;
        
        $optimized_location = implode(", ", $final_parts);
        if (strlen($optimized_location) <= $max_length) {
            return $optimized_location;
        }
    }
    
    // Option 1: Location already contains city info, but add state if missing
    if ($location && $city_already_in_location && $state && !$state_already_in_location && !$location_covered) {
        $location_state = $location . ", " . $state;
        if (strlen($location_state) <= $max_length) {
            return $location_state;
        }
        // If too long, try just city + state instead
        if (!$city_covered) {
            $city_state = $city . ", " . $state;
            if (strlen($city_state) <= $max_length) {
                return $city_state;
            }
        }
    }
    
    // Option 2: Location doesn't contain city - add city, state if beneficial (with redundancy check)
    if ($location && !$city_already_in_location && $city && $state && !$city_covered && !$location_covered) {
        // Build with redundancy detection
        $final_parts = [$location];
        if (!exif_harvester_is_location_term_redundant($location, $city)) {
            $final_parts[] = $city;
        }
        $final_parts[] = $state;
        
        $optimized_location = implode(", ", $final_parts);
        if (strlen($optimized_location) <= $max_length) {
            return $optimized_location;
        }
        
        // If too long, try shorter location name
        if (strlen($location) > 15) {
            $short_location = substr($location, 0, 15);
            $last_space = strrpos($short_location, ' ');
            if ($last_space !== false) {
                $short_location = substr($short_location, 0, $last_space);
            }
            
            $shortened_parts = [$short_location];
            if (!exif_harvester_is_location_term_redundant($short_location, $city)) {
                $shortened_parts[] = $city;
            }
            $shortened_parts[] = $state;
            
            $shortened_full = implode(", ", $shortened_parts);
            if (strlen($shortened_full) <= $max_length) {
                return $shortened_full;
            }
        }
        
        // Fall back to City, State (most important for SEO) 
        $city_state = $city . ", " . $state;
        if (strlen($city_state) <= $max_length) {
            return $city_state;
        }
    }
    
    // Option 3: City, State (ideal for SEO) - if city not already covered by tags
    if ($city && $state && !$city_covered) {
        $city_state = $city . ", " . $state;
        if (strlen($city_state) <= $max_length) {
            return $city_state;
        }
    }
    
    // Option 4: Location + State (if location is concise and not redundant)
    if ($location && $state && strlen($location) <= 25 && !$location_covered && !$state_already_in_location) {
        $location_state = $location . ", " . $state;
        if (strlen($location_state) <= $max_length) {
            return $location_state;
        }
    }
    
    // Option 5: Just State (always valuable for local SEO)
    if ($state && strlen($state) <= $max_length) {
        return $state;
    }
    
    // Option 6: City only (if state not available and city not covered)
    if ($city && strlen($city) <= $max_length && !$city_covered) {
        return $city;
    }
    
    // Option 7: Location only (if not covered by tags and concise)
    if ($location && strlen($location) <= $max_length && !$location_covered) {
        return $location;
    }
    
    // Option 8: Fallback to any available location if nothing else works
    if ($city && strlen($city) <= $max_length) {
        return $city; // Even if covered, include for SEO if no other options
    }
    
    // Option 9: Truncated location
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
function exif_harvester_is_location_redundant_tag($tag_lower, $location, $city, $state, $country, $final_location_string = '', $original_tag = '') {
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("EXIF SEO - Location redundancy START: Tag '$tag_lower' vs Final location '$final_location_string'");
    }
    
    // FIRST PRIORITY: Direct check against final location string - most accurate
    if (!empty($final_location_string)) {
        $final_location_lower = strtolower($final_location_string);
        
        // Check 1: Exact tag match in final location
        if (strpos($final_location_lower, $tag_lower) !== false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EXIF SEO - Location redundancy: DIRECT MATCH - Tag '$tag_lower' found in final location '$final_location_string'");
            }
            return true;
        }
        
        // Check 2: Tag as complete word in final location (handles cases like "McKinney" in "Honey Creek, McKinney, Texas")
        if (preg_match('/\b' . preg_quote($tag_lower, '/') . '\b/i', $final_location_lower)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EXIF SEO - Location redundancy: WORD MATCH - Tag '$tag_lower' found as complete word in final location '$final_location_string'");
            }
            return true;
        }
        
        // Check 3: Individual components of comma-separated final location vs tag
        $location_components = array_map('trim', explode(',', $final_location_lower));
        foreach ($location_components as $component) {
            if ($component === $tag_lower) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("EXIF SEO - Location redundancy: COMPONENT MATCH - Tag '$tag_lower' matches location component '$component'");
                }
                return true;
            }
        }
    }
    
    // Check if this tag has SEO bonus value
    $has_seo_bonus = exif_harvester_tag_has_seo_bonus($tag_lower);
    
    // Collect all location components
    $location_parts = array_filter([$location, $city, $state, $country]);
    
    // Add final location string if provided (this is what will actually appear in SEO description)
    if (!empty($final_location_string)) {
        $location_parts[] = $final_location_string;
    }
    
    if (empty($location_parts)) {
        return false; // No location data, so no redundancy
    }
    
        // Enhanced redundancy check: if tag appears in final location string, it's definitely redundant
        // This overrides SEO bonus for truly redundant cases
        if (!empty($final_location_string)) {
            $final_location_lower = strtolower($final_location_string);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EXIF SEO - Location redundancy check: Tag '$tag_lower' vs Final location '$final_location_string'");
            }
            
            // First, extract location parts from tags with complex formats like "Elliott Bay (Seattle), Washington"
            $tag_location_parts = [];
            
            // Extract text in parentheses (e.g., "Seattle" from "Elliott Bay (Seattle)")
            if (preg_match('/\(([^)]+)\)/', $tag_lower, $matches)) {
                $tag_location_parts[] = trim($matches[1]);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("EXIF SEO - Location redundancy: Extracted parenthetical location '" . trim($matches[1]) . "' from tag '$tag_lower'");
                }
            }
            
            // Split by commas to find location components (e.g., "Washington" from "Elliott Bay (Seattle), Washington")
            $comma_parts = array_map('trim', explode(',', $tag_lower));
            foreach ($comma_parts as $part) {
                // Remove parenthetical content for cleaner matching
                $clean_part = preg_replace('/\([^)]*\)/', '', $part);
                $clean_part = trim($clean_part);
                if (strlen($clean_part) > 2) { // Lowered from 3 to 2 to catch state abbreviations
                    $tag_location_parts[] = $clean_part;
                }
            }
            
            // Check if any extracted location parts match the final location string
            foreach ($tag_location_parts as $location_part) {
                if (strpos($final_location_lower, $location_part) !== false && strlen($location_part) > 2) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("EXIF SEO - Location redundancy: Tag location part '$location_part' found in final location string '$final_location_string'");
                    }
                    return true; // Location part found - redundant!
                }
            }
            
            // Split tag into words for better matching
            $tag_words = preg_split('/[\s\-_&,\(\)]+/', $tag_lower, -1, PREG_SPLIT_NO_EMPTY);
            
            foreach ($tag_words as $word) {
                if (strlen($word) <= 3) continue; // Skip very short words (increased from 2 to 3)
                
                // Skip common non-location words that might appear in both tags and locations
                $common_words = ['bay', 'river', 'lake', 'mountain', 'park', 'city', 'town', 'county'];
                if (in_array($word, $common_words)) continue;
                
                // Check if any significant word from the tag appears in the final location string
                if (strpos($final_location_lower, $word) !== false && strlen($word) > 3) {
                    // Additional check: make sure this is actually a location word, not just a coincidence
                    // Look for common location indicators
                    $location_indicators = ['seattle', 'washington', 'texas', 'california', 'new york', 'florida', 'colorado', 'oregon', 'elliott', 'puget', 'sound', 'bay', 'united', 'states', 'america'];
                    $is_location_word = false;
                    foreach ($location_indicators as $indicator) {
                        if (strpos($word, $indicator) !== false || strpos($indicator, $word) !== false) {
                            $is_location_word = true;
                            break;
                        }
                    }
                    
                    // Also check if the word appears to be a proper noun (capitalized in original tag)
                    if (!$is_location_word && !empty($original_tag)) {
                        // Get original case version of the word from the original tag
                        $original_tag_words = preg_split('/[\s\-_&,\(\)]+/', $original_tag, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($original_tag_words as $orig_word) {
                            if (strtolower($orig_word) === $word && ctype_upper($orig_word[0])) {
                                $is_location_word = true;
                                break;
                            }
                        }
                    }
                    
                    if ($is_location_word) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("EXIF SEO - Location redundancy: Tag location word '$word' found in final location string '$final_location_string'");
                        }
                        return true; // Tag word found in final location string - redundant!
                    }
                }
            }
            
            // Check for standalone location tags that match parts of the final location
            $known_locations = ['seattle', 'washington', 'texas', 'california', 'oregon', 'colorado', 'florida', 'new york', 'united states', 'usa', 'america', 'elliott', 'bay'];
            foreach ($known_locations as $known_location) {
                if ($tag_lower === $known_location && strpos($final_location_lower, $known_location) !== false) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("EXIF SEO - Location redundancy: Standalone location tag '$tag_lower' matches known location in final location string '$final_location_string'");
                    }
                    return true;
                }
            }
            
            // More aggressive check: if the entire tag (when cleaned) appears as a word in the final location
            $clean_tag = preg_replace('/[^a-z0-9\s]/', ' ', $tag_lower);
            $clean_tag = trim(preg_replace('/\s+/', ' ', $clean_tag));
            $tag_words_clean = explode(' ', $clean_tag);
            
            // Also extract individual words from the final location string for comparison
            $final_location_words = [];
            if (!empty($final_location_string)) {
                $clean_location = preg_replace('/[^a-z0-9\s]/', ' ', $final_location_lower);
                $clean_location = trim(preg_replace('/\s+/', ' ', $clean_location));
                $final_location_words = array_filter(explode(' ', $clean_location), function($word) {
                    return strlen($word) > 2; // Only consider words longer than 2 characters
                });
            }
            
            foreach ($tag_words_clean as $word) {
                if (strlen($word) <= 2) continue; // Skip very short words
                
                // Check if this word appears as a complete word in the final location
                if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $final_location_lower)) {
                    // First check: is this word one of the actual words from the final location?
                    if (in_array($word, $final_location_words)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("EXIF SEO - Location redundancy: Tag word '$word' is a direct match with final location component in '$final_location_string'");
                        }
                        return true; // Direct word match with final location - definitely redundant!
                    }
                    
                    // Second check: Extra verification using known location patterns for edge cases
                    $location_word_patterns = [
                        'washington', 'texas', 'california', 'oregon', 'colorado', 'florida', 'nevada', 'arizona',
                        'seattle', 'portland', 'denver', 'phoenix', 'las vegas', 'san francisco', 'los angeles',
                        'dallas', 'houston', 'austin', 'miami', 'chicago', 'new york', 'boston',
                        'bay', 'sound', 'river', 'lake', 'mountain', 'peak', 'valley', 'canyon',
                        'park', 'national', 'state', 'county', 'city', 'town', 'beach', 'island',
                        'elliott', 'puget', 'columbia', 'mississippi', 'colorado', 'snake', 'yellowstone',
                        'mckinney', 'honey', 'creek', 'plano', 'frisco', 'allen', 'richardson'
                    ];
                    
                    $is_location_word = false;
                    foreach ($location_word_patterns as $pattern) {
                        if (strpos($word, $pattern) !== false || strpos($pattern, $word) !== false) {
                            $is_location_word = true;
                            break;
                        }
                    }
                    
                    if ($is_location_word) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("EXIF SEO - Location redundancy: Tag word '$word' is a known location word found in final location string '$final_location_string'");
                        }
                        return true;
                    }
                }
            }
            
            // Also check if the entire tag appears in the location string
            if (strpos($final_location_lower, $tag_lower) !== false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("EXIF SEO - Location redundancy: Entire tag '$tag_lower' found in final location string '$final_location_string'");
                }
                return true;
            }
        }    // If tag has SEO bonus and isn't found in final location string, prefer it as a tag
    if ($has_seo_bonus) {
        return false; // Keep bonus terms as tags - they're more valuable than location metadata
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
function exif_harvester_score_tags_by_relevance($post_id, $tags, $final_location_string = '') {
    // CUSTOM DEBUG LOG - Write to our own file
    $debug_file = EXIF_HARVESTER_PLUGIN_DIR . 'debug-seo.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_file, "[$timestamp] FUNCTION ENTRY - exif_harvester_score_tags_by_relevance() CALLED for post $post_id with " . count($tags) . " input tags\n", FILE_APPEND);
    file_put_contents($debug_file, "[$timestamp] INPUT TAGS: " . implode(', ', $tags) . "\n", FILE_APPEND);
    
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
    
    // PLACE TAXONOMY EXCLUSION SYSTEM - Extract all location components from place taxonomy
    $place_exclusions = [];
    $place_terms = wp_get_post_terms($post_id, 'place', array('orderby' => 'parent'));
    
    // CUSTOM DEBUG LOG - Write to our own file
    $debug_file = EXIF_HARVESTER_PLUGIN_DIR . 'debug-seo.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_file, "[$timestamp] PLACE TAXONOMY LOOKUP - Found " . (is_array($place_terms) ? count($place_terms) : 0) . " direct terms for post $post_id\n", FILE_APPEND);
    
    if (!empty($place_terms) && !is_wp_error($place_terms)) {
        foreach ($place_terms as $term) {
            file_put_contents($debug_file, "[$timestamp] DIRECT PLACE TERM FOUND: '" . $term->name . "' (ID: " . $term->term_id . ")\n", FILE_APPEND);
            // Add each term name as an exclusion (case insensitive)
            $place_exclusions[] = strtolower(trim($term->name));
            
            // WALK UP THE HIERARCHY - Get all ancestor terms
            $ancestors = get_ancestors($term->term_id, 'place');
            file_put_contents($debug_file, "[$timestamp] Found " . count($ancestors) . " ancestors for term '" . $term->name . "'\n", FILE_APPEND);
            
            foreach ($ancestors as $ancestor_id) {
                $ancestor_term = get_term($ancestor_id, 'place');
                if ($ancestor_term && !is_wp_error($ancestor_term)) {
                    file_put_contents($debug_file, "[$timestamp] ANCESTOR TERM FOUND: '" . $ancestor_term->name . "' (ID: " . $ancestor_term->term_id . ")\n", FILE_APPEND);
                    $place_exclusions[] = strtolower(trim($ancestor_term->name));
                }
            }
        }
        
        // Remove duplicates and log final exclusions
        $place_exclusions = array_unique($place_exclusions);
        file_put_contents($debug_file, "[$timestamp] COMPLETE PLACE TAXONOMY EXCLUSIONS (including ancestors): " . implode(', ', $place_exclusions) . "\n", FILE_APPEND);
    } else {
        file_put_contents($debug_file, "[$timestamp] NO PLACE TAXONOMY FOUND - falling back to metadata\n", FILE_APPEND);
        // If no place taxonomy, create exclusions from metadata
        $location = exif_harvester_get_post_location($post_id);
        $city = exif_harvester_get_post_city($post_id);  
        $state = exif_harvester_get_post_state($post_id);
        $country = exif_harvester_get_post_country($post_id);
        
        file_put_contents($debug_file, "[$timestamp] METADATA FALLBACK - location: '$location', city: '$city', state: '$state', country: '$country'\n", FILE_APPEND);
        
        if ($location) {
            $place_exclusions[] = strtolower(trim($location));
            file_put_contents($debug_file, "[$timestamp] Added LOCATION metadata exclusion: '$location'\n", FILE_APPEND);
        }
        if ($city) {
            $place_exclusions[] = strtolower(trim($city));
            file_put_contents($debug_file, "[$timestamp] Added CITY metadata exclusion: '$city'\n", FILE_APPEND);
        }
        if ($state) {
            $place_exclusions[] = strtolower(trim($state));
            file_put_contents($debug_file, "[$timestamp] Added STATE metadata exclusion: '$state'\n", FILE_APPEND);
        }
        if ($country) {
            $place_exclusions[] = strtolower(trim($country));
            file_put_contents($debug_file, "[$timestamp] Added COUNTRY metadata exclusion: '$country'\n", FILE_APPEND);
        }
        
        if (!empty($place_exclusions)) {
            file_put_contents($debug_file, "[$timestamp] METADATA-BASED EXCLUSIONS: " . implode(', ', $place_exclusions) . "\n", FILE_APPEND);
        } else {
            file_put_contents($debug_file, "[$timestamp] NO EXCLUSIONS CREATED - no place taxonomy or metadata found\n", FILE_APPEND);
        }
    }
    
    // Filter out tags that match place taxonomy components
    if (!empty($place_exclusions)) {
        file_put_contents($debug_file, "[$timestamp] STARTING TAG EXCLUSION FILTERING with " . count($filtered_tags) . " tags\n", FILE_APPEND);
        file_put_contents($debug_file, "[$timestamp] INITIAL TAGS: " . implode(', ', $filtered_tags) . "\n", FILE_APPEND);
        
        $filtered_tags = array_filter($filtered_tags, function($tag) use ($place_exclusions, $post_id, $debug_file, $timestamp) {
            $tag_lower = strtolower(trim($tag));
            file_put_contents($debug_file, "[$timestamp] CHECKING TAG '$tag_lower' against " . count($place_exclusions) . " exclusions\n", FILE_APPEND);
            
            // Check if tag matches any place exclusion exactly or as word boundary
            foreach ($place_exclusions as $exclusion) {
                // Exact match
                if ($tag_lower === $exclusion) {
                    file_put_contents($debug_file, "[$timestamp] TAG '$tag' EXCLUDED - EXACT MATCH with place component '$exclusion'\n", FILE_APPEND);
                    return false;
                }
                
                // Word boundary match (tag contains the exclusion as a whole word)
                if (preg_match('/\b' . preg_quote($exclusion, '/') . '\b/i', $tag_lower)) {
                    file_put_contents($debug_file, "[$timestamp] TAG '$tag' EXCLUDED - CONTAINS place component '$exclusion' as word\n", FILE_APPEND);
                    return false;
                }
                
                // Additional check: if exclusion contains the tag as a word (reverse check)
                if (preg_match('/\b' . preg_quote($tag_lower, '/') . '\b/i', $exclusion)) {
                    file_put_contents($debug_file, "[$timestamp] TAG '$tag' EXCLUDED - tag appears as word in place component '$exclusion'\n", FILE_APPEND);
                    return false;
                }
            }
            
            file_put_contents($debug_file, "[$timestamp] TAG '$tag' KEPT - no exclusion match found\n", FILE_APPEND);
            return true; // Keep the tag
        });
        
        file_put_contents($debug_file, "[$timestamp] AFTER EXCLUSION FILTERING: " . count($filtered_tags) . " tags remain\n", FILE_APPEND);
        file_put_contents($debug_file, "[$timestamp] FINAL FILTERED TAGS: " . implode(', ', $filtered_tags) . "\n", FILE_APPEND);
    } else {
        file_put_contents($debug_file, "[$timestamp] NO EXCLUSIONS TO APPLY - keeping all " . count($filtered_tags) . " tags\n", FILE_APPEND);
    }
    
    // Debug: Log filtering steps
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("EXIF SEO Debug - Post $post_id: After substring filtering: " . implode(', ', $filtered_tags));
        error_log("EXIF SEO Debug - Post $post_id: After place taxonomy exclusions: " . implode(', ', $filtered_tags));
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
        
        // Check for location redundancy - backup check for any remaining location duplicates
        $location_redundant = exif_harvester_is_location_redundant_tag($tag_lower, $location, $city, $state, $country, $final_location_string, $tag);
        if ($location_redundant) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EXIF SEO Debug - Post $post_id: Tag '$tag' location redundant (backup check - found in final location string: '$final_location_string')");
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
    // CUSTOM DEBUG LOG - Write to our own file
    $debug_file = EXIF_HARVESTER_PLUGIN_DIR . 'debug-seo.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_file, "\n[$timestamp] ===== MAIN SEO GENERATION FUNCTION CALLED for post $post_id =====\n", FILE_APPEND);
    
    $elements = [];
    $max_length = 155; // Google's recommended meta description length
    
    // Get location information using helper functions
    $location = exif_harvester_get_post_location($post_id);
    $city = exif_harvester_get_post_city($post_id);
    $state = exif_harvester_get_post_state($post_id);
    $country = exif_harvester_get_post_country($post_id);
    
    // ALWAYS log location results for debugging
    error_log('EXIF Harvester SEO: Location data - Location: ' . ($location ?: 'empty') . ', City: ' . ($city ?: 'empty') . ', State: ' . ($state ?: 'empty') . ', Country: ' . ($country ?: 'empty'));
    
    // Debug logging to see what location data we're getting
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("EXIF SEO Debug - Post $post_id location data:");
        error_log("  Location: " . ($location ?: 'empty'));
        error_log("  City: " . ($city ?: 'empty'));
        error_log("  State: " . ($state ?: 'empty'));
        error_log("  Country: " . ($country ?: 'empty'));
        
        // Also check place taxonomy directly
        $place_terms = wp_get_post_terms($post_id, 'place');
        if (!empty($place_terms)) {
            error_log("  Place taxonomy: " . $place_terms[0]->name);
        } else {
            error_log("  Place taxonomy: empty");
        }
        
        // Check metadata fields directly 
        $meta_location = get_post_meta($post_id, 'location', true);
        $meta_city = get_post_meta($post_id, 'city', true);  
        $meta_state = get_post_meta($post_id, 'state', true);
        $meta_country = get_post_meta($post_id, 'country', true);
        error_log("  Meta location: " . ($meta_location ?: 'empty'));
        error_log("  Meta city: " . ($meta_city ?: 'empty'));
        error_log("  Meta state: " . ($meta_state ?: 'empty'));
        error_log("  Meta country: " . ($meta_country ?: 'empty'));
    }
    
    // Build optimal location string - prioritize place taxonomy for richer context
    $location_string = '';
    
    // Check if we have place taxonomy - if so, use more complete context
    $place_terms = wp_get_post_terms($post_id, 'place');
    if (!empty($place_terms)) {
        // First try to build from hierarchical structure for deeper context
        $hierarchical_location = exif_harvester_build_hierarchical_location($post_id);
        
        if (!empty($hierarchical_location)) {
            $location_string = $hierarchical_location;
            error_log("EXIF SEO - Post $post_id using hierarchical location: " . $location_string);
        } else {
            // Fall back to original method if hierarchical fails
            $full_place = $place_terms[0]->name;
            $parts = explode(',', $full_place);
            
            if (count($parts) >= 3) {
                // For place taxonomy with 3+ parts, use "Location, City, State" format
                // e.g., "Pecan Grove Cemetery, McKinney, Texas" from "Pecan Grove Cemetery, McKinney, Texas, United States"
                $location_string = trim($parts[0]) . ', ' . trim($parts[1]) . ', ' . trim($parts[2]);
            } elseif (count($parts) == 2) {
                // Use both parts
                $location_string = trim($parts[0]) . ', ' . trim($parts[1]);
            } else {
                // Single part
                $location_string = $full_place;
            }
            
            // Ensure reasonable length for SEO (increased limit since we now remove redundancy)
            if (strlen($location_string) > 80) {
                // If too long, fall back to first two parts
                if (count($parts) >= 2) {
                    $location_string = trim($parts[0]) . ', ' . trim($parts[1]);
                } else {
                    $location_string = trim($parts[0]);
                }
            }
            error_log("EXIF SEO - Post $post_id using fallback location: " . $location_string);
        }
    } else {
        // No place taxonomy, use traditional location building
        $location_string = exif_harvester_build_seo_location($location, $city, $state, $country, 80, []);
    }
    
    // Always log location string result for debugging
    error_log("EXIF SEO - Post $post_id final location string: " . ($location_string ?: 'empty'));
    
    // Skip time context - not valuable for SEO compared to tags/location
    // Skip weather conditions - not valuable for SEO compared to tags/location
    
    // Skip camera/lens info - not useful for SEO descriptions
    
    // Skip technical details - not useful for SEO descriptions
    
    // Get tags and score them by content relevance
    $tags = wp_get_post_tags($post_id, ['fields' => 'names']);
    $relevant_tags = [];
    if (!empty($tags)) {
        $scored_tags = exif_harvester_score_tags_by_relevance($post_id, $tags, $location_string);
        // Get top 7 most relevant tags for maximum subject matter coverage
        $relevant_tags = array_slice($scored_tags, 0, 7);
        
        // Debug: Log tag processing for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EXIF SEO Debug - Post $post_id: Raw tags: " . implode(', ', $tags));
            error_log("EXIF SEO Debug - Post $post_id: Scored tags: " . implode(', ', $scored_tags));
            error_log("EXIF SEO Debug - Post $post_id: Relevant tags: " . implode(', ', $relevant_tags));
            error_log("EXIF SEO Debug - Post $post_id: Final location string: " . ($location_string ?: 'empty'));
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
        $used_tags_for_variant = array_slice($relevant_tags, 0, 5);
        $tag_string = implode(', ', $used_tags_for_variant);
        $desc = ucfirst($tag_string) . " photography";
        
        // Always try to include location if available - location has high SEO value
        if ($location_string) {
            $potential_desc = $desc . " from " . $location_string . ".";
            // Only skip location if it would make description too long (>155 chars)
            if (strlen($potential_desc) <= 155) {
                $desc .= " from " . $location_string;
            } else {
                // Try with just the first part of location if too long
                $location_parts = explode(',', $location_string);
                $short_location = trim($location_parts[0]);
                if ($short_location && strlen($desc . " from " . $short_location . ".") <= 155) {
                    $desc .= " from " . $short_location;
                }
            }
        }
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Variant 1.5: Four tags variant
    if (count($relevant_tags) >= 4) {
        $used_tags_for_variant = array_slice($relevant_tags, 0, 4);
        $tag_string = implode(', ', $used_tags_for_variant);
        $desc = ucfirst($tag_string) . " photography";
        if ($location_string && strlen($desc) < 120) {
            if (strlen($desc . " from " . $location_string . ".") <= 155) {
                $desc .= " from " . $location_string;
            }
        }
        $desc .= ".";
        $variants[] = $desc;
    }
    
    // Variant 2: Tags + Location (when good tags available) - NO TIME CONTEXT
    if (!empty($relevant_tags) && $location_string) {
        $used_tags_for_variant = array_slice($relevant_tags, 0, 2);
        $tag_string = implode(' and ', $used_tags_for_variant);
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
        // Skip weather conditions - focus on tags and location for better SEO
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
        $helper_city = exif_harvester_get_post_city($post_id);
        $helper_state = exif_harvester_get_post_state($post_id);
        $helper_country = exif_harvester_get_post_country($post_id);
        $helper_location = exif_harvester_get_post_location($post_id);
        if (($helper_city || $helper_state) && strlen($desc) < 130) {
            $seo_location = exif_harvester_build_seo_location($helper_location, $helper_city, $helper_state, $helper_country, 35);
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
        $helper_city = exif_harvester_get_post_city($post_id);
        $helper_state = exif_harvester_get_post_state($post_id);
        if (($helper_city || $helper_state) && strlen($desc) < 120) {
            $helper_country = exif_harvester_get_post_country($post_id);
            $helper_location = exif_harvester_get_post_location($post_id);
            $seo_location = exif_harvester_build_seo_location($helper_location, $helper_city, $helper_state, $helper_country, 45);
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
        $helper_city = exif_harvester_get_post_city($post_id);
        $helper_state = exif_harvester_get_post_state($post_id);
        if (($helper_city || $helper_state) && strlen($desc) < 140) {
            $helper_country = exif_harvester_get_post_country($post_id);
            $helper_location = exif_harvester_get_post_location($post_id);
            $seo_location = exif_harvester_build_seo_location($helper_location, $helper_city, $helper_state, $helper_country, 35);
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
            $helper_city = exif_harvester_get_post_city($post_id);
            $helper_state = exif_harvester_get_post_state($post_id);
            $helper_country = exif_harvester_get_post_country($post_id);
            $helper_location = exif_harvester_get_post_location($post_id);
            $seo_location = exif_harvester_build_seo_location($helper_location, $helper_city, $helper_state, $helper_country, 55);
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
                $helper_city = exif_harvester_get_post_city($post_id);
                $helper_state = exif_harvester_get_post_state($post_id);
                $helper_country = exif_harvester_get_post_country($post_id);
                $helper_location = exif_harvester_get_post_location($post_id);
                $seo_location = exif_harvester_build_seo_location($helper_location, $helper_city, $helper_state, $helper_country, 55);
                if ($seo_location) {
                    $desc .= " from " . $seo_location;
                }
            }
        } elseif ($location_string) {
            $helper_city = exif_harvester_get_post_city($post_id);
            $helper_state = exif_harvester_get_post_state($post_id);
            $helper_country = exif_harvester_get_post_country($post_id);
            $helper_location = exif_harvester_get_post_location($post_id);
            $seo_location = exif_harvester_build_seo_location($helper_location, $helper_city, $helper_state, $helper_country, 65);
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
        
        // Skip weather scoring - focus on tags and location for SEO value
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