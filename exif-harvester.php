<?php
/**
 * Plugin Name: EXIF Harvester
 * Plugin URI: https://www.75centralphotography.com
 * Description: Automatically extracts and stores EXIF metadata from images when posts are saved or edited. Stores data in custom fields for camera, lens, GPS coordinates, date/time information, and more.
 * Version: 1.2.0
 * Author: Matt Harvey
 * Author URI: https://www.75centralphotography.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: exif-harvester
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EXIF_HARVESTER_VERSION', '1.0.0');
define('EXIF_HARVESTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EXIF_HARVESTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EXIF_HARVESTER_PLUGIN_FILE', __FILE__);

/**
 * Main EXIF Harvester Plugin Class
 */
class EXIFHarvester {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * List of custom fields to process
     */
    private $custom_fields = array(
        'camera',
        'caption', 
        'dateOriginal',
        'dateTimeOriginal',
        'dayOfWeekOriginal',
        'dayOriginal',
        'focallength',
        'fstop',
        'gmtOffset',
        'GPS',
        'GPCode',
        'GPSAlt',
        'GPSLat',
        'GPSLon',
        'hourOriginal',
        'iso',
        'lens',
        'location',
        'city',
        'state',
        'country',
        'minuteOriginal',
        'monthNameOriginal',
        'monthOriginal',
        'photo_aspect_ratio',
        'photo_dimensions',
        'photo_height',
        'photo_megapixels',
        'photo_width',
        'seo_description',
        'shutterspeed',
        'temperature',
        'timeOfDayContext',
        'timeOriginal',
        'timeZone',
        'unixTime',
        'wXSummary',
        'yearOriginal'
    );
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // ALWAYS log when plugin loads - test logging
        error_log('EXIF Harvester: Plugin constructor called - BASIC TEST LOG');
        
        $this->load_includes();
        $this->init_hooks();
        $this->load_settings();
        
        // ALWAYS log when plugin is fully loaded - test logging
        error_log('EXIF Harvester: Plugin fully loaded - BASIC TEST LOG');
        
        // Add test hooks to see if ANY WordPress actions are firing
        add_action('init', function() {
            error_log('EXIF Harvester: WordPress init action fired - BASIC TEST LOG');
        });
        
        add_action('wp_loaded', function() {
            error_log('EXIF Harvester: WordPress wp_loaded action fired - BASIC TEST LOG');
        });
        
        // Add hooks to catch ANY post activity
        add_action('save_post', function($post_id) {
            error_log('EXIF Harvester: ANY save_post hook fired for post ' . $post_id . ' - BASIC TEST LOG');
        }, 1);
        
        add_action('wp_after_insert_post', function($post_id) {
            error_log('EXIF Harvester: ANY wp_after_insert_post hook fired for post ' . $post_id . ' - BASIC TEST LOG');
        }, 1);
        
        add_action('wp_insert_post', function($post_id) {
            error_log('EXIF Harvester: ANY wp_insert_post hook fired for post ' . $post_id . ' - BASIC TEST LOG');
        }, 1);
    }
    
    /**
     * Load required files
     */
    private function load_includes() {
        require_once EXIF_HARVESTER_PLUGIN_DIR . 'includes/exif-harvester-functions.php';
        require_once EXIF_HARVESTER_PLUGIN_DIR . 'includes/exif-seo-meta-descriptions.php';
        require_once EXIF_HARVESTER_PLUGIN_DIR . 'includes/exif-seo-admin.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Post save/update hooks (only for creation and updates, not deletions)
        add_action('wp_after_insert_post', array($this, 'process_post_exif'), 10, 4);
        
        // Also hook into save_post to catch content changes that might not trigger wp_after_insert_post
        add_action('save_post', array($this, 'process_post_save'), 10, 3);
        
        // Hook for Quick Edit specifically
        add_action('wp_ajax_inline-save', array($this, 'process_quick_edit'), 10);
        add_action('wp_ajax_nopriv_inline-save', array($this, 'process_quick_edit'), 10);
        
        // AJAX handlers - register early so they're available for AJAX requests
        add_action('wp_ajax_exif_harvester_save_camera', array($this, 'ajax_save_camera'));
        add_action('wp_ajax_exif_harvester_delete_camera', array($this, 'ajax_delete_camera'));
        add_action('wp_ajax_exif_harvester_save_lens', array($this, 'ajax_save_lens'));
        add_action('wp_ajax_exif_harvester_delete_lens', array($this, 'ajax_delete_lens'));
        add_action('wp_ajax_exif_harvester_save_location', array($this, 'ajax_save_location'));
        add_action('wp_ajax_exif_harvester_delete_location', array($this, 'ajax_delete_location'));
        add_action('wp_ajax_exif_harvester_manual_process', array($this, 'ajax_manual_process'));
        add_action('wp_ajax_exif_harvester_get_post_data', array($this, 'ajax_get_post_data'));
        
        // Register async weather processing hook
        add_action('exif_harvester_async_weather_processing', array($this, 'async_weather_processing_callback'));
        
        // Register AJAX handler for async weather processing fallback
        add_action('wp_ajax_exif_harvester_async_weather', array($this, 'ajax_async_weather_processing'));
        add_action('wp_ajax_nopriv_exif_harvester_async_weather', array($this, 'ajax_async_weather_processing'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Metabox hooks
        add_action('add_meta_boxes', array($this, 'add_exif_metabox'));
        add_action('add_meta_boxes', array($this, 'add_seo_metabox'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Load text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Register custom taxonomies
        add_action('init', array($this, 'register_place_taxonomy'));
        
        // Sync place taxonomy to location metadata
        add_action('set_object_terms', array($this, 'sync_place_taxonomy_to_metadata'), 10, 6);
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $defaults = array(
            'enabled_post_types' => array('post'),
            'delete_on_update' => true,
            'require_logged_in' => false,
            'weather_api_enabled' => false,
            'pirate_weather_api_key' => '',
            'timezone_api_enabled' => true,
            'timezonedb_api_key' => ''
        );
        
        $this->settings = wp_parse_args(get_option('exif_harvester_settings', array()), $defaults);
        
        // ALWAYS log settings for debugging - test logging
        error_log('EXIF Harvester: Settings loaded - Enabled post types: ' . implode(',', $this->settings['enabled_post_types']) . ', Weather enabled: ' . ($this->settings['weather_api_enabled'] ? 'yes' : 'no') . ' - BASIC TEST LOG');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_database_tables();
        
        // Populate default camera and lens mappings
        $this->populate_default_mappings();
        
        // Create default settings
        if (!get_option('exif_harvester_settings')) {
            update_option('exif_harvester_settings', $this->settings);
        }
        
        // Set activation notice flag
        set_transient('exif_harvester_activation_notice', true, 30);
        
        // Clear any caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients
        delete_transient('exif_harvester_activation_notice');
        
        // Clear any caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Create database tables for camera and lens mappings
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Camera mappings table
        $camera_table = $wpdb->prefix . 'exif_harvester_cameras';
        $camera_sql = "CREATE TABLE $camera_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            raw_name varchar(255) NOT NULL,
            pretty_name varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY raw_name (raw_name)
        ) $charset_collate;";
        
        // Lens mappings table
        $lens_table = $wpdb->prefix . 'exif_harvester_lenses';
        $lens_sql = "CREATE TABLE $lens_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            raw_name varchar(255) NOT NULL,
            pretty_name varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY raw_name (raw_name)
        ) $charset_collate;";
        
        // Location corrections table
        $location_table = $wpdb->prefix . 'exif_harvester_location_corrections';
        $location_sql = "CREATE TABLE $location_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            truncated_name varchar(32) NOT NULL,
            full_name varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY truncated_name (truncated_name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Handle potential duplicate entries before schema changes
        $this->cleanup_duplicate_entries();
        
        // Execute schema changes with error handling
        $camera_result = dbDelta($camera_sql);
        $lens_result = dbDelta($lens_sql);
        
        // Handle location table separately due to potential column size change issues
        $location_table = $wpdb->prefix . 'exif_harvester_location_corrections';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$location_table'") == $location_table;
        
        if (!$table_exists) {
            // Table doesn't exist, create it normally
            dbDelta($location_sql);
        } else {
            // Table exists, handle column modification carefully
            $column_info = $wpdb->get_row("SHOW COLUMNS FROM $location_table LIKE 'truncated_name'");
            
            if ($column_info && strpos($column_info->Type, 'varchar(31)') !== false) {
                // Column is still varchar(31), need to modify it
                // First, ensure no duplicates at 32 char level
                $this->cleanup_duplicate_entries();
                
                // Handle specific problematic entries
                $this->fix_problematic_location_entries();
                
                // Now attempt the column modification
                $modify_result = $wpdb->query("ALTER TABLE $location_table MODIFY COLUMN truncated_name varchar(32) NOT NULL");
                
                if ($modify_result === false) {
                    error_log('EXIF Harvester: Could not modify truncated_name column to varchar(32)');
                }
            } else {
                // Try normal dbDelta
                dbDelta($location_sql);
            }
        }
    }
    
    /**
     * Clean up duplicate entries before schema changes
     */
    private function cleanup_duplicate_entries() {
        global $wpdb;
        
        $location_table = $wpdb->prefix . 'exif_harvester_location_corrections';
        
        // Check if the table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$location_table'") == $location_table;
        
        if ($table_exists) {
            // Check current column size
            $column_info = $wpdb->get_row("SHOW COLUMNS FROM $location_table LIKE 'truncated_name'");
            
            if ($column_info) {
                // If column exists, clean up potential duplicates that would occur at 32 chars
                // First, find entries that would be duplicates when extended to 32 chars
                $duplicates = $wpdb->get_results("
                    SELECT truncated_name, COUNT(*) as count, MIN(id) as keep_id
                    FROM $location_table 
                    GROUP BY LEFT(truncated_name, 32)
                    HAVING COUNT(*) > 1
                ");
                
                foreach ($duplicates as $duplicate) {
                    // Delete all but the first occurrence
                    $wpdb->query($wpdb->prepare("
                        DELETE FROM $location_table 
                        WHERE LEFT(truncated_name, 32) = LEFT(%s, 32) 
                        AND id != %d
                    ", $duplicate->truncated_name, $duplicate->keep_id));
                }
                
                // Also remove exact duplicates
                $wpdb->query("
                    DELETE t1 FROM $location_table t1
                    INNER JOIN $location_table t2 
                    WHERE t1.id > t2.id 
                    AND t1.truncated_name = t2.truncated_name
                ");
            }
        }
    }
    
    /**
     * Fix specific problematic location entries that cause duplicates at 32 chars
     */
    private function fix_problematic_location_entries() {
        global $wpdb;
        
        $location_table = $wpdb->prefix . 'exif_harvester_location_corrections';
        
        // Get entries that would be "Glacier Bay National Park & Pres" at 32 chars
        $glacier_entries = $wpdb->get_results("
            SELECT * FROM $location_table 
            WHERE LEFT(truncated_name, 32) = 'Glacier Bay National Park & Pres'
            ORDER BY id ASC
        ");
        
        if (count($glacier_entries) > 1) {
            // Keep the first one, delete the rest
            for ($i = 1; $i < count($glacier_entries); $i++) {
                $wpdb->delete($location_table, array('id' => $glacier_entries[$i]->id), array('%d'));
            }
        }
        
        // Check for any other potential 32-char conflicts
        $potential_conflicts = $wpdb->get_results("
            SELECT LEFT(truncated_name, 32) as truncated_32, COUNT(*) as count, GROUP_CONCAT(id) as ids
            FROM $location_table 
            GROUP BY LEFT(truncated_name, 32)
            HAVING COUNT(*) > 1
        ");
        
        foreach ($potential_conflicts as $conflict) {
            $ids = explode(',', $conflict->ids);
            // Keep the first ID, delete the rest
            for ($i = 1; $i < count($ids); $i++) {
                $wpdb->delete($location_table, array('id' => (int)$ids[$i]), array('%d'));
            }
        }
    }
    
    /**
     * Populate default camera, lens, and location correction mappings
     */
    private function populate_default_mappings() {
        global $wpdb;
        
        $camera_table = $wpdb->prefix . 'exif_harvester_cameras';
        $lens_table = $wpdb->prefix . 'exif_harvester_lenses';
        $location_table = $wpdb->prefix . 'exif_harvester_location_corrections';
        
        // Check if tables are already populated
        $camera_count = $wpdb->get_var("SELECT COUNT(*) FROM $camera_table");
        $lens_count = $wpdb->get_var("SELECT COUNT(*) FROM $lens_table");
        $location_count = $wpdb->get_var("SELECT COUNT(*) FROM $location_table");
        
        if ($camera_count == 0) {
            $this->insert_default_cameras();
        }
        
        if ($lens_count == 0) {
            $this->insert_default_lenses();
        }
        
        if ($location_count == 0) {
            $this->insert_default_location_corrections();
        }
    }
    
    /**
     * Insert default camera mappings
     */
    private function insert_default_cameras() {
        global $wpdb;
        
        $camera_table = $wpdb->prefix . 'exif_harvester_cameras';
        
        $default_cameras = array(
            // Canon cameras
            array('Canon EOS 60D', 'Canon EOS 60D'),
            array('Canon PowerShot S90', 'Canon PowerShot S90'),
            array('Canon EOS DIGITAL REBEL XTi', 'Canon EOS Digital Rebel XTi/400D'),
            
            // Sony cameras
            array('ILCE-7RM2', 'Sony a7RII'),
            array('DSC-RX100M7', 'Sony RX100 Vii'),
            
            // Fujifilm cameras
            array('X100VI', 'Fujifilm X100vi'),
            array('X-T5', 'Fujifilm X-T5'),
            array('X-T30 II', 'Fujifilm X-T30 II'),
            array('X-E5', 'Fujifilm X-E5'),
            array('FinePix X100', 'Fujifilm FinePix X100'),
            
            // Panasonic cameras
            array('DMC-GX8', 'Panasonic Lumix GX8'),
            array('DMC-GM1', 'Panasonic Lumix GM1'),
            array('DMC-G6', 'Panasonic Lumix G6'),
            
            // Apple iPhone cameras
            array('iPhone 5s', 'Apple iPhone 5s'),
            array('iPhone 6', 'Apple iPhone'),
            array('iPhone 6s', 'Apple iPhone 6s'),
            array('iPhone 7 Plus', 'Apple iPhone 7 Plus'),
            array('iPhone 11 Pro Max', 'Apple iPhone 11 Pro Max'),
            array('iPhone 13 Pro Max', 'Apple iPhone 13 Pro Max'),
            array('iPhone 15 Pro Max', 'Apple iPhone 15 Pro Max'),
            array('iPhone', 'Apple iPhone'),
            
            // Google Pixel
            array('Pixel 2 XL', 'Google Pixel 2 XL'),
            
            // Samsung
            array('SM-G930T', 'Samsung Galaxy S7'),
            
            // DJI Drones
            array('FC7203', 'DJI Mavic Mini'),
            array('FC300C', 'DJI Phantom 3'),
            
            // Olympus
            array('C4040Z', 'Olympus C4040Z'),
            array('uD600,S600', 'Olympus Stylus 600')
        );
        
        foreach ($default_cameras as $camera) {
            // Check if camera already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $camera_table WHERE raw_name = %s",
                $camera[0]
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $camera_table,
                    array(
                        'raw_name' => $camera[0],
                        'pretty_name' => $camera[1]
                    ),
                    array('%s', '%s')
                );
            }
        }
    }
    
    /**
     * Insert default lens mappings
     */
    private function insert_default_lenses() {
        global $wpdb;
        
        $lens_table = $wpdb->prefix . 'exif_harvester_lenses';
        
        $default_lenses = array(
            // Canon lenses
            array('EF-S18-55mm f/3.5-5.6', 'Canon EF-S 18-55mm f/3.5-5.6'),
            array('EF-S55-250mm f/4-5.6 IS', 'Canon EF-S 55-250mm f/4-5.6 IS'),
            array('EF100-400mm f/4.5-5.6L IS USM', 'Canon EF 100-400mm f/4.5-5.6L IS USM'),
            array('EF100mm f/2.8 Macro USM', 'Canon EF 100mm f/2.8 Macro USM'),
            array('EF24-105mm f/4L IS USM', 'Canon EF 24-105mm f/4L IS USM'),
            array('EF28-90mm f/4-5.6', 'Canon EF 28-90mm f/4-5.6'),
            array('EF50mm f/1.8', 'Canon EF50mm f/1.8'),
            array('EF50mm f/1.8 II', 'Canon EF50mm f/1.8 II'),
            array('EF75-300mm f/4-5.6', 'Canon EF 75-300mm f/4-5.6'),
            array('6.0-22.5 mm', 'Canon s90 6.0-22.5 mm'),
            
            // Sony lenses
            array('FE 28-70mm F3.5-5.6 OSS', 'Sony FE 28-70mm f/3.5-5.6 OSS'),
            array('FE 50mm F1.8', 'Sony FE 50mm f/1.8'),
            
            // Fujifilm lenses
            array('XF16-55mmF2.8 R LM WR', 'Fujifilm XF 16-55mm f/2.8 R LM WR'),
            array('XF8-16mmF2.8 R LM WR', 'Fujifilm XF 8-16mm f/2.8 R LM WR'),
            array('XF23mmF1.4 R', 'Fujifilm XF 23mm f/1.4 R'),
            array('XF35mmF1.4 R', 'Fujifilm XF 35mm f/1.4 R'),
            array('XF56mmF1.2 R', 'Fujifilm XF 56mm f/1.2 R'),
            array('XF10-24mmF4 R OIS', 'Fujifilm XF 10-24mm f/4 R OIS'),
            array('XF50-140mmF2.8 R LM OIS WR', 'Fujifilm XF 50-140mm f/2.8 R LM OIS WR'),
            array('XF18-135mmF3.5-5.6 R LM OIS WR', 'Fujifilm XF 18-135mm f/3.5-5.6 R LM OIS WR'),
            
            // Panasonic lenses
            array('LUMIX G 14/F2.5', 'Panasonic Lumix G 14/F2.5'),
            array('LUMIX G 20/F1.7 II', 'Panasonic Lumix G 20/F1.7 II'),
            array('LUMIX G VARIO 12-32/F3.5-5.6', 'Panasonic Lumix G Vario 12-32/F3.5-5.6'),
            array('LUMIX G VARIO 12-35/F 3.5-5.6', 'Panasonic Lumix G VARIO 12-35/F 3.5-5.6'),
            array('LUMIX G VARIO 12-35/F2.8', 'Panasonic Lumix G Vario 12-35/F2.8'),
            array('LUMIX G VARIO 14-42/F3.5-5.6 II', 'Panasonic Lumix G Vario 14-42/F3.5-5.6 II'),
            array('LUMIX G VARIO 35-100/F2.8', 'Panasonic Lumix G Vario 35-100/F2.8'),
            array('LUMIX G VARIO 45-200/F4.0-5.6', 'Panasonic Lumix G Vario 45-200/F4.0-5.6'),
            
            // Tamron lenses
            array('E 17-28mm F2.8-2.8', 'Tamron E 17-28mm F2.8'),
            array('E 28-200mm F2.8-5.6 A071', 'Tamron E 28-200mm F2.8-5.6'),
            array('E 28-75mm F2.8-2.8', 'Tamron E 28-75mm F2.8'),
            array('E 70-180mm F2.8 A056', 'Tamron E 70-180mm F2.8'),
            array('18-300mm F/3.5-6.3 DiIII-A VC VXD B061X', 'Tamron 18-300mm F/3.5-6.3 DiIII-A VC VXD'),
            
            // Sigma lenses
            array('18-200mm', 'Sigma 18-200mm 3.5-6.3 DC HSM OS'),
            array('18-50mm', 'Sigma 18-50mm F2.8-4.5 DC OS HSM'),
            array('Sigma 18-200mm f/3.5-6.3 II DC OS HSM', 'Sigma 18-200mm f/3.5-6.3 II DC OS HSM'),
            
            // Other specialized lenses
            array('-- mm f/--', 'Olympus 5.8-17.4mm'),
            array('AF 13/1.4 XF', 'Viltrox AF 13mm F1.4'),
            array('20.7 mm', 'DJI 20 mm f/2.8'),
            array('24-200mm F2.8-4.5', 'Sony RX100 24-200mm F2.8-4.5'),
            array('LEICA DG 50-200/F2.8-4.0', 'Leica DG 50-200/F2.8-4.0'),
            array('Samsung Galaxy S7 Rear Camera', 'Samsung Galaxy Rear Camera S7 F1.7'),
            array('Samyang 7.5mm 1:3.5 UMC Fish-eye MFT', 'Samyang 7.5mm f3.5 UMC Fish-eye'),
            array('AF 27/2.8', 'TTArtisan 27mm F2.8'),
            
            // iPhone camera modules (fixed lenses)
            array('iPhone 5s back camera 4.12mm f/2.2', 'Apple iPhone 5s back camera 4.12mm f/2.2'),
            array('iPhone 6 back camera 4.15mm f/2.2', 'Apple iPhone 6s back camera 4.15mm f/2.2'),
            array('iPhone 6s back camera 4.15mm f/2.2', 'Apple iPhone 6s back camera 4.15mm f/2.2'),
            array('iPhone 7 Plus back camera 3.99mm f/1.8', 'Apple iPhone 7 Plus back camera 3.99mm f/1.8'),
            array('iPhone 7 Plus back camera 6.6mm f/2.8', 'Apple iPhone 7 Plus back camera 6.6mm f/2.8'),
            array('iPhone 11 Pro Max back camera 4.25mm f/1.8', 'Apple iPhone 11 Pro Max back camera 4.25mm f/1.8'),
            array('iPhone 11 Pro Max back camera 6mm f/2', 'Apple iPhone 11 Pro Max back camera 6mm f/2'),
            array('iPhone 11 Pro Max back triple camera 1.54mm f/2.4', 'Apple iPhone 11 Pro Max back camera 1.54mm f/2.4'),
            array('iPhone 11 Pro Max back triple camera 4.25mm f/1.8', 'Apple iPhone 11 Pro Max back camera 4.25mm f/1.8'),
            array('iPhone 13 Pro Max back camera 1.57mm f/1.8', 'Apple iPhone 13 Pro Max back camera 1.57mm f/1.8'),
            array('iPhone 13 Pro Max back camera 5.7mm f/1.5', 'Apple iPhone 13 Pro Max back camera 5.7mm f/1.5'),
            array('iPhone 13 Pro Max back triple camera 1.57mm f/1.8', 'Apple iPhone 13 Pro Max back camera 1.57mm f/1.8'),
            array('iPhone 13 Pro Max back triple camera 5.7mm f/1.5', 'Apple iPhone 13 Pro Max back camera 5.7mm f/1.5'),
            array('iPhone 13 Pro Max back triple camera 9mm f/2.8', 'Apple iPhone 13 Pro Max back camera 9mm f/2.8'),
            array('iPhone 15 Pro Max back triple camera 2.22mm f/2.2', 'Apple iPhone 15 Pro Max back camera 2.22mm f/2.2'),
            array('iPhone 15 Pro Max back camera 15.66mm f/2.8', 'Apple iPhone 15 Pro Max back camera 15.66mm f/2.8'),
            array('iPhone 15 Pro Max back camera 6.86mm f/1.78', 'Apple iPhone 15 Pro Max back camera 6.86mm f/1.78'),
            array('iPhone 15 Pro Max back triple camera 15.66mm f/2.8', 'Apple iPhone 15 Pro Max back camera 15.66mm f/2.8'),
            array('iPhone 15 Pro Max back camera 6.765mm f/1.78', 'Apple iPhone 15 Pro Max back camera 6.765mm f/1.78'),
            array('iPhone 15 Pro Max back triple camera 6.765mm f/1.78', 'Apple iPhone 15 Pro Max back camera 6.765mm f/1.78'),
            array('iPhone 15 Pro Max back camera 2.22mm f/2.2', 'Apple iPhone 15 Pro Max back camera 2.22mm f/2.2'),
            
            // Fujifilm lenses
            array('XF16-55mmF2.8 R LM WR', 'Fujifilm XF 16-55mm f/2.8 R LM WR'),
            array('XF8-16mmF2.8 R LM WR', 'Fujifilm XF 8-16mmF2.8 R LM WR'),
            array('XF23mmF1.4 R', 'Fujifilm Fujinon XF23mmF1.4 R LM WR')
        );
        
        foreach ($default_lenses as $lens) {
            // Check if lens already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $lens_table WHERE raw_name = %s",
                $lens[0]
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $lens_table,
                    array(
                        'raw_name' => $lens[0],
                        'pretty_name' => $lens[1]
                    ),
                    array('%s', '%s')
                );
            }
        }
    }
    
    /**
     * Insert default location corrections
     */
    private function insert_default_location_corrections() {
        global $wpdb;
        
        $location_table = $wpdb->prefix . 'exif_harvester_location_corrections';
        
        $default_corrections = array(
            array('Fort Worth Stockyards National H', 'Fort Worth Stockyards National Historic District'),
            array('Penn Farm Agricultural History C', 'Penn Farm Agricultural History Center'),
            array('Chixculub Crater - Gulf of Mexic', 'Chixculub Crater - Gulf of Mexico'),
            array('Enchanted Rock State Natural Are', 'Enchanted Rock State Natural Area'),
            array('Ray Roberts State Park - Isle du', 'Ray Roberts State Park - Isle du Bois'),
            array('Cima Dome & Volcanic Field Natio', 'Cima Dome & Volcanic Field National Natural Landmark'),
            array('San Jacinto Battleground State H', 'San Jacinto Battleground State Historic Site'),
            array('Red Rock Canyon National Conserv', 'Red Rock Canyon National Conservation Area'),
            array('Cavanaugh Flight Museum (Defunct', 'Cavanaugh Flight Museum (Defunct)'),
            array('Bathhouse Row - Hot Springs Nati', 'Bathhouse Row - Hot Springs National Park'),
            array('Texas & Pacific Freight Warehous', 'Texas & Pacific Freight Warehouse'),
            array('Garland Power & Light - Ray Olin', 'Garland Power & Light - Ray Olinger Power Plant'),
            array('Fowler-Hughes-Keathley Housing C', 'Fowler-Hughes-Keathley Housing Complex'),
            array('Central Avenue - Hot Springs Nat', 'Central Avenue - Hot Springs National Park'),
            array('Perot Museum of Nature and Scien', 'Perot Museum of Nature and Science'),
            array('Hagerman National Wildlife Refug', 'Hagerman National Wildlife Refuge'),
            array('Kay Bailey Hutchison Convention ', 'Kay Bailey Hutchison Convention Center'),
            array('Glacier Bay National Park & Pres', 'Glacier Bay National Park & Preserve'),
            array('George H.W. Bush Presidential Li', 'George H.W. Bush Presidential Library'),
            array('Fort Davis National Historic Sit', 'Fort Davis National Historic Site'),
            array('Conoco Tower Station & U-Drop-In', 'Conoco Tower Station & U-Drop Inn'),
            array('San Francisco Maritime National ', 'San Francisco Maritime National Historical Park'),
            array('Texas Freshwater Fisheries Cente', 'Texas Freshwater Fisheries Center'),
            array('Hall Office Park/Texas Sculpture', 'Hall Office Park/Texas Sculpture Garden'),
            array('Holiday Inn Resort - Galveston-o', 'Holiday Inn Resort - Galveston-on-the-Beach'),
            array('Yaquina Head Outstanding Natural', 'Yaquina Head Outstanding Natural Area'),
            array('Army Corps of Engineers - Lavon ', 'Army Corps of Engineers - Lavon Lake Dam'),
            array('Las Vegas Valley', 'Las Vegas'),
            array('Glacier Bay National Park and Pr', 'Glacier Bay National Park and Preserve'),
            array('Winnetka Heights Historic Distri', 'Winnetka Heights Historic District'),
            array('Alamogordo Lumber Company Mill (', 'Alamogordo Lumber Company Mill (Abandoned)'),
            array('Historical Aviation Memorial Mus', 'Historical Aviation Memorial Museum'),
            array('Lake Texomaland Fun Park (Abando', 'Lake Texomaland Fun Park (Abandoned)'),
            array('Grey Wolf Ranch - Home of Bronco', 'Grey Wolf Ranch - Home of Bronco Off-Roadeo Texas'),
            array('Totem Bight State Historical Par', 'Totem Bight State Historical Park'),
            array('Arrecife Coralino (Coral Reefs M', 'Arrecife Coralino (Coral Reefs Monument)'),
            array('Historic Aviation Memorial Museu', 'Historic Aviation Memorial Museum'),
            array('Great Smoky Mountains National P', 'Great Smoky Mountains National Park'),
            array('Darrell K Royal – Texas Memori', 'Darrell K Royal – Texas Memorial Stadium'),
            array('West Mountain - Hot Springs Nati', 'West Mountain - Hot Springs National Park'),
            array('Tarrant County College - Trinity', 'Tarrant County College - Trinity River Campus East'),
            array('Timber Ridge Sleepy Hollow Estat', 'Timber Ridge Sleepy Hollow Estates'),
            array('McKinney Cotton Mill Arts & Desi', 'McKinney Cotton Mill Arts & Design District'),
            array('Lower Colorado River Authority -', 'Lower Colorado River Authority - Buchanan Dam'),
            array('Houston Museum of Natural Scienc', 'Houston Museum of Natural Science'),
            array('Otter Crest State Scenic Viewpoi', 'Otter Crest State Scenic Viewpoint'),
            array('Mountainaire Hotel Historic Dist', 'Mountainaire Hotel Historic District'),
            array('The Broadmoor Manitou and Pikes ', 'The Broadmoor Manitou and Pikes Peak Cog Railway'),
            array('US Coast Guard Station - Galvest', 'US Coast Guard Station - Galveston'),
        );
        
        foreach ($default_corrections as $correction) {
            // Check if location correction already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $location_table WHERE truncated_name = %s",
                $correction[0]
            ));
            
            if (!$exists) {
                $wpdb->insert(
                    $location_table,
                    array(
                        'truncated_name' => $correction[0],
                        'full_name' => $correction[1]
                    ),
                    array('%s', '%s')
                );
            }
        }
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('exif-harvester', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        error_log('EXIF Harvester: add_admin_menu() called - about to register AJAX handlers');
        
        // Main menu page
        $main_page = add_menu_page(
            __('EXIF Harvester', 'exif-harvester'),
            __('EXIF Harvester', 'exif-harvester'),
            'manage_options',
            'exif-harvester',
            array($this, 'admin_page'),
            'dashicons-camera',
            30
        );
        
        // Settings submenu (rename main page)
        add_submenu_page(
            'exif-harvester',
            __('EXIF Harvester Settings', 'exif-harvester'),
            __('Settings', 'exif-harvester'),
            'manage_options',
            'exif-harvester',
            array($this, 'admin_page')
        );
        
        // Camera mappings submenu
        add_submenu_page(
            'exif-harvester',
            __('Camera Mappings', 'exif-harvester'),
            __('Camera Mappings', 'exif-harvester'),
            'manage_options',
            'exif-harvester-cameras',
            array($this, 'camera_mappings_page')
        );
        
        // Lens mappings submenu
        add_submenu_page(
            'exif-harvester',
            __('Lens Mappings', 'exif-harvester'),
            __('Lens Mappings', 'exif-harvester'),
            'manage_options',
            'exif-harvester-lenses',
            array($this, 'lens_mappings_page')
        );
        
        // Location corrections submenu
        add_submenu_page(
            'exif-harvester',
            __('Location Corrections', 'exif-harvester'),
            __('Location Corrections', 'exif-harvester'),
            'manage_options',
            'exif-harvester-locations',
            array($this, 'location_corrections_page')
        );
        
        // EXIF Data Overview submenu
        add_submenu_page(
            'exif-harvester',
            __('EXIF Data Overview', 'exif-harvester'),
            __('Data Overview', 'exif-harvester'),
            'manage_options',
            'exif-harvester-overview',
            array($this, 'exif_data_overview_page')
        );
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('exif_harvester_settings', 'exif_harvester_settings', array($this, 'sanitize_settings'));
        
        add_settings_section(
            'exif_harvester_general',
            __('General Settings', 'exif-harvester'),
            array($this, 'general_section_callback'),
            'exif-harvester'
        );
        
        add_settings_section(
            'exif_harvester_weather',
            __('Weather Data Settings', 'exif-harvester'),
            array($this, 'weather_section_callback'),
            'exif-harvester'
        );
        
        add_settings_field(
            'enabled_post_types',
            __('Enabled Post Types', 'exif-harvester'),
            array($this, 'post_types_callback'),
            'exif-harvester',
            'exif_harvester_general'
        );
        
        add_settings_field(
            'delete_on_update',
            __('Delete Existing Data on Update', 'exif-harvester'),
            array($this, 'delete_on_update_callback'),
            'exif-harvester',
            'exif_harvester_general'
        );
        

        

        
        add_settings_field(
            'weather_api_enabled',
            __('Enable Weather Data', 'exif-harvester'),
            array($this, 'weather_api_enabled_callback'),
            'exif-harvester',
            'exif_harvester_weather'
        );
        
        add_settings_field(
            'pirate_weather_api_key',
            __('PirateWeather API Key', 'exif-harvester'),
            array($this, 'pirate_weather_api_key_callback'),
            'exif-harvester',
            'exif_harvester_weather'
        );
        
        add_settings_field(
            'timezonedb_api_key',
            __('TimezoneDB API Key', 'exif-harvester'),
            array($this, 'timezonedb_api_key_callback'),
            'exif-harvester',
            'exif_harvester_weather'
        );
    }
    
    /**
     * Admin page display
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('exif_harvester_settings');
                do_settings_sections('exif-harvester');
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2><?php _e('Supported Custom Fields', 'exif-harvester'); ?></h2>
                <p><?php _e('The following custom fields will be automatically populated when EXIF data is found:', 'exif-harvester'); ?></p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 15px;">
                    <div>
                        <h4><?php _e('Camera & Technical', 'exif-harvester'); ?></h4>
                        <ul style="list-style-type: disc; margin-left: 20px;">
                            <li><code>camera</code></li>
                            <li><code>lens</code></li>
                            <li><code>fstop</code></li>
                            <li><code>shutterspeed</code></li>
                            <li><code>iso</code></li>
                            <li><code>focallength</code></li>
                        </ul>
                        
                        <h4><?php _e('Image Properties', 'exif-harvester'); ?></h4>
                        <ul style="list-style-type: disc; margin-left: 20px;">
                            <li><code>photo_width</code></li>
                            <li><code>photo_height</code></li>
                            <li><code>photo_dimensions</code></li>
                            <li><code>photo_megapixels</code></li>
                            <li><code>photo_aspect_ratio</code></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4><?php _e('GPS & Location', 'exif-harvester'); ?></h4>
                        <ul style="list-style-type: disc; margin-left: 20px;">
                            <li><code>GPS</code></li>
                            <li><code>GPSLat</code></li>
                            <li><code>GPSLon</code></li>
                            <li><code>GPSAlt</code></li>
                            <li><code>GPCode</code></li>
                            <li><code>timeZone</code></li>
                            <li><code>gmtOffset</code></li>
                        </ul>
                        
                        <h4><?php _e('Weather Data', 'exif-harvester'); ?> <?php if (!$this->settings['weather_api_enabled']): ?><small style="color: #666;">(<?php _e('disabled', 'exif-harvester'); ?>)</small><?php endif; ?></h4>
                        <ul style="list-style-type: disc; margin-left: 20px;">
                            <li><code>temperature</code> <?php if ($this->settings['weather_api_enabled']): ?><span style="color: green;">✓</span><?php endif; ?></li>
                            <li><code>wXSummary</code> <?php if ($this->settings['weather_api_enabled']): ?><span style="color: green;">✓</span><?php endif; ?></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4><?php _e('Date & Time', 'exif-harvester'); ?></h4>
                        <ul style="list-style-type: disc; margin-left: 20px;">
                            <li><code>dateTimeOriginal</code></li>
                            <li><code>dateOriginal</code></li>
                            <li><code>yearOriginal</code></li>
                            <li><code>monthOriginal</code></li>
                            <li><code>monthNameOriginal</code></li>
                            <li><code>dayOriginal</code></li>
                            <li><code>dayOfWeekOriginal</code></li>
                            <li><code>hourOriginal</code></li>
                            <li><code>minuteOriginal</code></li>
                            <li><code>timeOriginal</code></li>
                            <li><code>timeOfDayContext</code></li>
                            <li><code>unixTime</code></li>
                        </ul>
                        
                        <h4><?php _e('Content', 'exif-harvester'); ?></h4>
                        <ul style="list-style-type: disc; margin-left: 20px;">
                            <li><code>caption</code></li>
                        </ul>
                    </div>
                </div>
                
                <?php if ($this->settings['weather_api_enabled'] && !empty($this->settings['pirate_weather_api_key'])): ?>
                    <div style="margin-top: 15px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
                        <strong><?php _e('Weather Data Enabled:', 'exif-harvester'); ?></strong> <?php _e('Temperature and weather summary will be automatically retrieved for photos with GPS coordinates and timestamps.', 'exif-harvester'); ?>
                    </div>
                <?php elseif ($this->settings['weather_api_enabled']): ?>
                    <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">
                        <strong><?php _e('Weather Data Configured but Missing API Key:', 'exif-harvester'); ?></strong> <?php _e('Please enter your PirateWeather API key above to enable weather data collection.', 'exif-harvester'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure how EXIF Harvester processes your posts.', 'exif-harvester') . '</p>';
    }
    
    /**
     * Weather section callback
     */
    public function weather_section_callback() {
        echo '<p>' . __('Configure weather data extraction using the PirateWeather API. Weather data will be retrieved based on GPS coordinates and photo timestamp when available.', 'exif-harvester') . '</p>';
        echo '<p><strong>' . __('Note:', 'exif-harvester') . '</strong> ' . __('Weather data requires GPS coordinates and date/time information in the EXIF data. Get your free API key from', 'exif-harvester') . ' <a href="https://pirate-weather.apiable.io/" target="_blank">PirateWeather</a>.</p>';
    }
    
    /**
     * Camera mappings admin page
     */
    public function camera_mappings_page() {
        global $wpdb;
        
        $camera_table = $wpdb->prefix . 'exif_harvester_cameras';
        $cameras = $wpdb->get_results("SELECT * FROM $camera_table ORDER BY raw_name");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Camera Mappings', 'exif-harvester'); ?></h1>
            <p><?php _e('Manage how camera model names are displayed. Raw names are extracted from EXIF data, and pretty names are shown to users.', 'exif-harvester'); ?></p>
            
            <div class="card">
                <h2><?php _e('Add New Camera Mapping', 'exif-harvester'); ?></h2>
                <form id="add-camera-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Raw Camera Name', 'exif-harvester'); ?></th>
                            <td>
                                <input type="text" name="raw_name" id="camera-raw-name" class="regular-text" required />
                                <p class="description"><?php _e('The exact camera model name as it appears in EXIF data', 'exif-harvester'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Pretty Camera Name', 'exif-harvester'); ?></th>
                            <td>
                                <input type="text" name="pretty_name" id="camera-pretty-name" class="regular-text" required />
                                <p class="description"><?php _e('The formatted name to display to users', 'exif-harvester'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php wp_nonce_field('exif_harvester_camera_nonce', 'camera_nonce'); ?>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Add Camera Mapping', 'exif-harvester'); ?>" />
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h2><?php _e('Existing Camera Mappings', 'exif-harvester'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Raw Camera Name', 'exif-harvester'); ?></th>
                            <th><?php _e('Pretty Camera Name', 'exif-harvester'); ?></th>
                            <th><?php _e('Actions', 'exif-harvester'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cameras)): ?>
                            <tr>
                                <td colspan="3"><?php _e('No camera mappings found.', 'exif-harvester'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cameras as $camera): ?>
                                <tr data-id="<?php echo esc_attr($camera->id); ?>">
                                    <td>
                                        <span class="camera-raw-name"><?php echo esc_html($camera->raw_name); ?></span>
                                        <input type="text" class="camera-raw-name-edit regular-text" value="<?php echo esc_attr($camera->raw_name); ?>" style="display: none;" />
                                    </td>
                                    <td>
                                        <span class="camera-pretty-name"><?php echo esc_html($camera->pretty_name); ?></span>
                                        <input type="text" class="camera-pretty-name-edit regular-text" value="<?php echo esc_attr($camera->pretty_name); ?>" style="display: none;" />
                                    </td>
                                    <td>
                                        <button type="button" class="button edit-camera"><?php _e('Edit', 'exif-harvester'); ?></button>
                                        <button type="button" class="button save-camera" style="display: none;"><?php _e('Save', 'exif-harvester'); ?></button>
                                        <button type="button" class="button cancel-edit-camera" style="display: none;"><?php _e('Cancel', 'exif-harvester'); ?></button>
                                        <button type="button" class="button delete-camera" style="color: #a00;"><?php _e('Delete', 'exif-harvester'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Lens mappings admin page
     */
    public function lens_mappings_page() {
        global $wpdb;
        
        $lens_table = $wpdb->prefix . 'exif_harvester_lenses';
        $lenses = $wpdb->get_results("SELECT * FROM $lens_table ORDER BY raw_name");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Lens Mappings', 'exif-harvester'); ?></h1>
            <p><?php _e('Manage how lens names are displayed. Raw names are extracted from EXIF data, and pretty names are shown to users.', 'exif-harvester'); ?></p>
            
            <div class="card">
                <h2><?php _e('Add New Lens Mapping', 'exif-harvester'); ?></h2>
                <form id="add-lens-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Raw Lens Name', 'exif-harvester'); ?></th>
                            <td>
                                <input type="text" name="raw_name" id="lens-raw-name" class="regular-text" required />
                                <p class="description"><?php _e('The exact lens name as it appears in EXIF data', 'exif-harvester'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Pretty Lens Name', 'exif-harvester'); ?></th>
                            <td>
                                <input type="text" name="pretty_name" id="lens-pretty-name" class="regular-text" required />
                                <p class="description"><?php _e('The formatted name to display to users', 'exif-harvester'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php wp_nonce_field('exif_harvester_lens_nonce', 'lens_nonce'); ?>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Add Lens Mapping', 'exif-harvester'); ?>" />
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h2><?php _e('Existing Lens Mappings', 'exif-harvester'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Raw Lens Name', 'exif-harvester'); ?></th>
                            <th><?php _e('Pretty Lens Name', 'exif-harvester'); ?></th>
                            <th><?php _e('Actions', 'exif-harvester'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lenses)): ?>
                            <tr>
                                <td colspan="3"><?php _e('No lens mappings found.', 'exif-harvester'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lenses as $lens): ?>
                                <tr data-id="<?php echo esc_attr($lens->id); ?>">
                                    <td>
                                        <span class="lens-raw-name"><?php echo esc_html($lens->raw_name); ?></span>
                                        <input type="text" class="lens-raw-name-edit regular-text" value="<?php echo esc_attr($lens->raw_name); ?>" style="display: none;" />
                                    </td>
                                    <td>
                                        <span class="lens-pretty-name"><?php echo esc_html($lens->pretty_name); ?></span>
                                        <input type="text" class="lens-pretty-name-edit regular-text" value="<?php echo esc_attr($lens->pretty_name); ?>" style="display: none;" />
                                    </td>
                                    <td>
                                        <button type="button" class="button edit-lens"><?php _e('Edit', 'exif-harvester'); ?></button>
                                        <button type="button" class="button save-lens" style="display: none;"><?php _e('Save', 'exif-harvester'); ?></button>
                                        <button type="button" class="button cancel-edit-lens" style="display: none;"><?php _e('Cancel', 'exif-harvester'); ?></button>
                                        <button type="button" class="button delete-lens" style="color: #a00;"><?php _e('Delete', 'exif-harvester'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Location corrections admin page
     */
    public function location_corrections_page() {
        global $wpdb;
        
        $location_table = $wpdb->prefix . 'exif_harvester_location_corrections';
        $locations = $wpdb->get_results("SELECT * FROM $location_table ORDER BY truncated_name");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Location Corrections', 'exif-harvester'); ?></h1>
            <p><?php _e('Manage location name corrections. Truncated names are often found in IPTC metadata, and full names provide complete location information.', 'exif-harvester'); ?></p>
            
            <div class="card">
                <h2><?php _e('Add New Location Correction', 'exif-harvester'); ?></h2>
                <form id="add-location-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Truncated Location Name', 'exif-harvester'); ?></th>
                            <td>
                                <input type="text" name="truncated_name" id="location-truncated-name" class="regular-text" required maxlength="32" />
                                <p class="description"><?php _e('The incomplete location name as it appears in IPTC metadata (max 32 characters)', 'exif-harvester'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Full Location Name', 'exif-harvester'); ?></th>
                            <td>
                                <input type="text" name="full_name" id="location-full-name" class="regular-text" required />
                                <p class="description"><?php _e('The complete location name to display', 'exif-harvester'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php wp_nonce_field('exif_harvester_ajax_nonce', 'nonce'); ?>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Add Location Correction', 'exif-harvester'); ?>" />
                    </p>
                </form>
            </div>
            
            <div class="card">
                <h2><?php _e('Existing Location Corrections', 'exif-harvester'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Truncated Location Name', 'exif-harvester'); ?></th>
                            <th><?php _e('Full Location Name', 'exif-harvester'); ?></th>
                            <th><?php _e('Actions', 'exif-harvester'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($locations)): ?>
                            <tr>
                                <td colspan="3"><?php _e('No location corrections found.', 'exif-harvester'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($locations as $location): ?>
                                <tr data-id="<?php echo esc_attr($location->id); ?>">
                                    <td>
                                        <span class="location-truncated-name"><?php echo esc_html($location->truncated_name); ?></span>
                                        <input type="text" class="location-truncated-name-edit regular-text" value="<?php echo esc_attr($location->truncated_name); ?>" maxlength="32" style="display: none;" />
                                    </td>
                                    <td>
                                        <span class="location-full-name"><?php echo esc_html($location->full_name); ?></span>
                                        <input type="text" class="location-full-name-edit regular-text" value="<?php echo esc_attr($location->full_name); ?>" style="display: none;" />
                                    </td>
                                    <td>
                                        <button type="button" class="button edit-location"><?php _e('Edit', 'exif-harvester'); ?></button>
                                        <button type="button" class="button save-location" style="display: none;"><?php _e('Save', 'exif-harvester'); ?></button>
                                        <button type="button" class="button cancel-edit-location" style="display: none;"><?php _e('Cancel', 'exif-harvester'); ?></button>
                                        <button type="button" class="button delete-location" style="color: #a00;"><?php _e('Delete', 'exif-harvester'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * EXIF Data Overview page
     */
    public function exif_data_overview_page() {
        // Include the overview functionality
        require_once plugin_dir_path(__FILE__) . 'includes/exif-data-overview.php';
        
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] === 'refresh_exif' && !empty($_POST['post'])) {
            check_admin_referer('bulk-posts');
            
            $post_ids = array_map('intval', $_POST['post']);
            $processed = 0;
            
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                if ($post) {
                    $this->process_post_exif($post_id, $post, true, null, true);
                    $processed++;
                }
            }
            
            echo '<div class="notice notice-success"><p>' . 
                 sprintf(__('Processed EXIF data for %d posts.', 'exif-harvester'), $processed) . 
                 '</p></div>';
        }
        
        // Create and display the table
        $list_table = new EXIF_Data_Overview_List_Table($this);
        $list_table->prepare_items();
        
        // Get statistics for dashboard
        global $wpdb;
        $enabled_post_types = $this->settings['enabled_post_types'];
        
        // Fallback if no post types are enabled
        if (empty($enabled_post_types)) {
            $enabled_post_types = array('post');
        }
        
        $type_placeholders = implode(',', array_fill(0, count($enabled_post_types), '%s'));
        
        $total_posts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('publish', 'draft', 'private') AND post_type IN ($type_placeholders)",
            $enabled_post_types
        ));
        
        $posts_with_camera = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_status IN ('publish', 'draft', 'private') AND p.post_type IN ($type_placeholders)
             AND pm.meta_key = 'camera' AND pm.meta_value != ''",
            $enabled_post_types
        ));
        
        $posts_with_weather = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_status IN ('publish', 'draft', 'private') AND p.post_type IN ($type_placeholders)
             AND pm.meta_key = 'wXSummary' AND pm.meta_value != ''",
            $enabled_post_types
        ));
        
        $posts_with_gps = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_status IN ('publish', 'draft', 'private') AND p.post_type IN ($type_placeholders)
             AND pm.meta_key = 'GPS' AND pm.meta_value != ''",
            $enabled_post_types
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('EXIF Data Overview', 'exif-harvester'); ?></h1>
            <p><?php _e('View and manage EXIF/IPTC data across all your posts. Use the filters to find posts missing specific data, and use the refresh buttons to reprocess EXIF data.', 'exif-harvester'); ?></p>
            

            
            <!-- Statistics Dashboard -->
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 14px;">📊 Total Posts</h3>
                    <div style="font-size: 24px; font-weight: 600; color: #2271b1;"><?php echo number_format($total_posts); ?></div>
                    <small style="color: #646970;">Eligible posts</small>
                </div>
                <div class="stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 14px;">📷 With Camera Data</h3>
                    <div style="font-size: 24px; font-weight: 600; color: #00a32a;"><?php echo number_format($posts_with_camera); ?></div>
                    <small style="color: #646970;"><?php echo $total_posts > 0 ? round(($posts_with_camera / $total_posts) * 100) : 0; ?>% coverage</small>
                </div>
                <div class="stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 14px;">🌍 With GPS Data</h3>
                    <div style="font-size: 24px; font-weight: 600; color: #00a32a;"><?php echo number_format($posts_with_gps); ?></div>
                    <small style="color: #646970;"><?php echo $total_posts > 0 ? round(($posts_with_gps / $total_posts) * 100) : 0; ?>% coverage</small>
                </div>
                <div class="stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h3 style="margin: 0 0 8px 0; color: #1d2327; font-size: 14px;">🌤️ With Weather Data</h3>
                    <div style="font-size: 24px; font-weight: 600; color: <?php echo $this->settings['weather_api_enabled'] ? '#00a32a' : '#646970'; ?>;">
                        <?php echo number_format($posts_with_weather); ?>
                    </div>
                    <small style="color: #646970;">
                        <?php if ($this->settings['weather_api_enabled']): ?>
                            <?php echo $total_posts > 0 ? round(($posts_with_weather / $total_posts) * 100) : 0; ?>% coverage
                        <?php else: ?>
                            Weather API disabled
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            
            <form method="post" id="exif-overview-form">
                <?php
                $list_table->search_box(__('Search posts', 'exif-harvester'), 'post');
                $list_table->display();
                wp_nonce_field('bulk-posts');
                ?>
            </form>
            
            <style>
                .refresh-exif-data {
                    font-size: 11px;
                    padding: 4px 8px;
                    border-radius: 3px;
                    background: #f0f0f1;
                    border: 1px solid #c3c4c7;
                    color: #2c3338;
                    cursor: pointer;
                    transition: all 0.15s ease-in-out;
                }
                .refresh-exif-data:hover {
                    background: #0073aa;
                    color: #fff;
                    border-color: #0073aa;
                }
                .refresh-exif-data:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }
                .refresh-exif-data.loading {
                    background-image: url('<?php echo admin_url('images/spinner.gif'); ?>');
                    background-repeat: no-repeat;
                    background-position: 4px center;
                    background-size: 16px 16px;
                    padding-left: 26px;
                    background-color: #f0f6fc;
                    border-color: #0073aa;
                }
                .refresh-exif-data.success {
                    background-color: #d4edda;
                    color: #155724;
                    border-color: #c3e6cb;
                }
                .refresh-exif-data.error {
                    background-color: #f8d7da;
                    color: #721c24;
                    border-color: #f5c6cb;
                }
                .wp-list-table th.sortable a, .wp-list-table th.sorted a {
                    text-decoration: none;
                }
                .tablenav .actions select {
                    margin-right: 10px;
                }
                .stats-grid .stat-card:hover {
                    box-shadow: 0 2px 4px rgba(0,0,0,.1);
                }
                .wp-list-table .column-gps {
                    width: 140px;
                }
                .wp-list-table .column-camera {
                    width: 120px;
                }
                .wp-list-table .column-lens {
                    width: 120px;
                }
                .wp-list-table .column-weather {
                    width: 100px;
                }
                .wp-list-table .column-actions {
                    width: 100px;
                    text-align: center;
                }
                .wp-list-table .column-datetime_original {
                    width: 120px;
                }
                .exif-overview-notice {
                    margin: 15px 0;
                    padding: 10px 15px;
                    border-radius: 4px;
                    font-weight: 500;
                }
                .exif-overview-notice.success {
                    background-color: #d4edda;
                    color: #155724;
                    border-left: 4px solid #28a745;
                }
                .exif-overview-notice.info {
                    background-color: #d1ecf1;
                    color: #0c5460;
                    border-left: 4px solid #17a2b8;
                }
            </style>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                
                // Function to update a specific row with fresh data
                function updateRowData(postId, row) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'exif_harvester_get_post_data',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce('exif_harvester_metabox_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                var data = response.data;
                                
                                // Update each column with fresh data
                                row.find('.column-camera').html(data.camera || '<span style="color: #999;">—</span>');
                                row.find('.column-lens').html(data.lens || '<span style="color: #999;">—</span>');
                                row.find('.column-gps').html(data.gps || '<span style="color: #999;">—</span>');
                                row.find('.column-location').html(data.location || '<span style="color: #999;">—</span>');
                                row.find('.column-weather').html(data.weather || '<span style="color: #999;">—</span>');
                                row.find('.column-datetime_original').html(data.datetime_original || '<span style="color: #999;">—</span>');
                                
                                // Add a subtle flash effect to show the row was updated
                                row.animate({backgroundColor: '#d4edda'}, 200).animate({backgroundColor: ''}, 800);
                            }
                        },
                        error: function() {
                            // If individual update fails, fall back to full page reload preserving URL
                            var currentUrl = window.location.href;
                            window.location.href = currentUrl;
                        }
                    });
                }
                // Handle individual refresh buttons
                $('.refresh-exif-data').on('click', function(e) {
                    e.preventDefault();
                    
                    var button = $(this);
                    var postId = button.data('post-id');
                    var row = button.closest('tr');
                    
                    button.prop('disabled', true).addClass('loading').removeClass('success error')
                          .text('<?php _e('Processing...', 'exif-harvester'); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'exif_harvester_manual_process',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce('exif_harvester_metabox_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                button.removeClass('loading').addClass('success')
                                      .text('<?php _e('Success!', 'exif-harvester'); ?>');
                                      
                                // Show success notice
                                var notice = $('<div class="exif-overview-notice success"><?php _e('EXIF data updated successfully!', 'exif-harvester'); ?></div>');
                                $('#exif-overview-form').prepend(notice);
                                
                                // Update the row data by making an AJAX call to get fresh data
                                updateRowData(postId, row);
                                
                                // Auto-hide notice after 3 seconds
                                setTimeout(function() {
                                    notice.fadeOut();
                                }, 3000);
                            } else {
                                button.removeClass('loading').addClass('error')
                                      .text('<?php _e('Error', 'exif-harvester'); ?>');
                                      
                                var errorMsg = response.data || '<?php _e('Unknown error occurred', 'exif-harvester'); ?>';
                                var notice = $('<div class="exif-overview-notice error"><strong><?php _e('Error:', 'exif-harvester'); ?></strong> ' + errorMsg + '</div>');
                                $('#exif-overview-form').prepend(notice);
                            }
                        },
                        error: function(xhr, status, error) {
                            button.removeClass('loading').addClass('error')
                                  .text('<?php _e('Error', 'exif-harvester'); ?>');
                                  
                            var notice = $('<div class="exif-overview-notice error"><strong><?php _e('Ajax Error:', 'exif-harvester'); ?></strong> ' + error + '</div>');
                            $('#exif-overview-form').prepend(notice);
                        },
                        complete: function() {
                            setTimeout(function() {
                                if (!button.hasClass('success')) {
                                    button.prop('disabled', false)
                                          .removeClass('loading error')
                                          .text('<?php _e('Refresh EXIF', 'exif-harvester'); ?>');
                                }
                            }, 3000);
                        }
                    });
                });
                
                // Handle filter changes
                $('select[name="filter_missing"]').on('change', function() {
                    $('#post-query-submit').click();
                });
                
                // Handle bulk actions
                $('#doaction, #doaction2').on('click', function(e) {
                    var action = $(this).siblings('select').val();
                    var selected = $('input[name="post[]"]:checked').length;
                    
                    if (action === 'refresh_exif') {
                        if (selected === 0) {
                            alert('<?php _e('Please select posts to refresh.', 'exif-harvester'); ?>');
                            e.preventDefault();
                            return false;
                        }
                        
                        if (!confirm('<?php _e('Are you sure you want to refresh EXIF data for the selected posts? This may take a while.', 'exif-harvester'); ?>')) {
                            e.preventDefault();
                            return false;
                        }
                        
                        // Show processing notice
                        var notice = $('<div class="exif-overview-notice info"><?php _e('Processing EXIF data for selected posts. Please wait...', 'exif-harvester'); ?></div>');
                        $('#exif-overview-form').prepend(notice);
                    }
                });
                
                // Auto-hide notices after 5 seconds
                setTimeout(function() {
                    $('.exif-overview-notice').fadeOut();
                }, 5000);
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Get plugin settings
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Post types field callback
     */
    public function post_types_callback() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $enabled_types = $this->settings['enabled_post_types'];
        
        foreach ($post_types as $post_type) {
            $checked = in_array($post_type->name, $enabled_types) ? 'checked="checked"' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="exif_harvester_settings[enabled_post_types][]" value="' . esc_attr($post_type->name) . '" ' . $checked . ' />';
            echo ' ' . esc_html($post_type->label) . ' (' . esc_html($post_type->name) . ')';
            echo '</label>';
        }
        echo '<p class="description">' . __('Select which post types should have EXIF data automatically processed.', 'exif-harvester') . '</p>';
    }
    
    /**
     * Delete on update field callback
     */
    public function delete_on_update_callback() {
        $checked = $this->settings['delete_on_update'] ? 'checked="checked"' : '';
        echo '<label>';
        echo '<input type="checkbox" name="exif_harvester_settings[delete_on_update]" value="1" ' . $checked . ' />';
        echo ' ' . __('Delete existing EXIF metadata when posts are updated', 'exif-harvester');
        echo '</label>';
        echo '<p class="description">' . __('When enabled, existing EXIF metadata will be removed before processing new data on post updates.', 'exif-harvester') . '</p>';
    }
    

    

    
    /**
     * Weather API enabled field callback
     */
    public function weather_api_enabled_callback() {
        $checked = $this->settings['weather_api_enabled'] ? 'checked="checked"' : '';
        echo '<label>';
        echo '<input type="checkbox" name="exif_harvester_settings[weather_api_enabled]" value="1" ' . $checked . ' id="weather_api_enabled" />';
        echo ' ' . __('Enable weather data extraction', 'exif-harvester');
        echo '</label>';
        echo '<p class="description">' . __('When enabled, weather data will be retrieved for photos with GPS coordinates and timestamps. Requires a PirateWeather API key.', 'exif-harvester') . '</p>';
        
        // Add JavaScript to show/hide API key field
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var checkbox = document.getElementById("weather_api_enabled");
            var apiKeyRow = document.querySelector(\'input[name="exif_harvester_settings[pirate_weather_api_key]"]\').closest("tr");
            
            function toggleApiKeyField() {
                if (checkbox.checked) {
                    apiKeyRow.style.display = "";
                } else {
                    apiKeyRow.style.display = "none";
                }
            }
            
            checkbox.addEventListener("change", toggleApiKeyField);
            toggleApiKeyField(); // Initial state
        });
        </script>';
    }
    
    /**
     * PirateWeather API key field callback
     */
    public function pirate_weather_api_key_callback() {
        $api_key = isset($this->settings['pirate_weather_api_key']) ? $this->settings['pirate_weather_api_key'] : '';
        echo '<input type="text" name="exif_harvester_settings[pirate_weather_api_key]" value="' . esc_attr($api_key) . '" class="regular-text" placeholder="Enter your PirateWeather API key" />';
        echo '<p class="description">' . __('Your PirateWeather API key. Get one free at', 'exif-harvester') . ' <a href="https://pirate-weather.apiable.io/" target="_blank">pirate-weather.apiable.io</a>. ' . __('This will be used to fetch weather conditions and temperature data based on photo location and time.', 'exif-harvester') . '</p>';
        
        if (!empty($api_key)) {
            echo '<p class="description" style="color: green;">✅ ' . __('API key is configured', 'exif-harvester') . '</p>';
        } else {
            echo '<p class="description" style="color: orange;">⚠️ ' . __('API key required for weather data', 'exif-harvester') . '</p>';
        }
    }
    
    public function timezonedb_api_key_callback() {
        $api_key = isset($this->settings['timezonedb_api_key']) ? $this->settings['timezonedb_api_key'] : '';
        echo '<input type="text" name="exif_harvester_settings[timezonedb_api_key]" value="' . esc_attr($api_key) . '" class="regular-text" placeholder="Enter your TimezoneDB API key" />';
        echo '<p class="description">' . __('Your TimezoneDB API key. Get one free at', 'exif-harvester') . ' <a href="https://timezonedb.com/api" target="_blank">timezonedb.com/api</a>. ' . __('This will be used to fetch accurate timezone information for photo timestamps.', 'exif-harvester') . '</p>';
        
        if (!empty($api_key)) {
            echo '<p class="description" style="color: green;">✅ ' . __('API key is configured', 'exif-harvester') . '</p>';
        } else {
            echo '<p class="description" style="color: red;">⚠️ ' . __('WARNING: Without an API key, timezone and weather accuracy may be reduced as the plugin will use fallback methods.', 'exif-harvester') . '</p>';
        }
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize enabled post types
        if (isset($input['enabled_post_types']) && is_array($input['enabled_post_types'])) {
            $sanitized['enabled_post_types'] = array_map('sanitize_text_field', $input['enabled_post_types']);
        } else {
            $sanitized['enabled_post_types'] = array();
        }
        
        // Sanitize boolean settings
        $sanitized['delete_on_update'] = isset($input['delete_on_update']) ? (bool) $input['delete_on_update'] : false;
        $sanitized['weather_api_enabled'] = isset($input['weather_api_enabled']) ? (bool) $input['weather_api_enabled'] : false;
        $sanitized['timezone_api_enabled'] = isset($input['timezone_api_enabled']) ? (bool) $input['timezone_api_enabled'] : true;
        
        // Sanitize API keys
        $sanitized['pirate_weather_api_key'] = isset($input['pirate_weather_api_key']) ? sanitize_text_field($input['pirate_weather_api_key']) : '';
        $sanitized['timezonedb_api_key'] = isset($input['timezonedb_api_key']) ? sanitize_text_field($input['timezonedb_api_key']) : '';
        
        return $sanitized;
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        if (get_transient('exif_harvester_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('EXIF Harvester has been activated! Configure your settings under Settings > EXIF Harvester.', 'exif-harvester'); ?></p>
            </div>
            <?php
            delete_transient('exif_harvester_activation_notice');
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook_suffix) {
        // Load on plugin pages
        $is_plugin_page = strpos($hook_suffix, 'exif-harvester') !== false;
        
        // Also load on post edit screens where our metabox appears
        $is_post_edit = in_array($hook_suffix, array('post.php', 'post-new.php'));
        
        if (!$is_plugin_page && !$is_post_edit) {
            return;
        }
        
        wp_enqueue_script(
            'exif-harvester-admin',
            EXIF_HARVESTER_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            EXIF_HARVESTER_VERSION,
            true
        );
        
        wp_localize_script('exif-harvester-admin', 'exifHarvester', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('exif_harvester_ajax_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this mapping?', 'exif-harvester'),
                'error_occurred' => __('An error occurred. Please try again.', 'exif-harvester'),
                'success_saved' => __('Mapping saved successfully!', 'exif-harvester'),
                'success_deleted' => __('Mapping deleted successfully!', 'exif-harvester')
            )
        ));
        
        wp_enqueue_style(
            'exif-harvester-admin',
            EXIF_HARVESTER_PLUGIN_URL . 'assets/admin.css',
            array(),
            EXIF_HARVESTER_VERSION
        );
    }
    
    /**
     * Add EXIF metabox to post edit screens
     */
    public function add_exif_metabox() {
        $enabled_post_types = $this->settings['enabled_post_types'];
        
        foreach ($enabled_post_types as $post_type) {
            add_meta_box(
                'exif_harvester_metabox',
                __('EXIF Data', 'exif-harvester'),
                array($this, 'display_exif_metabox'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Display EXIF metabox content
     */
    public function display_exif_metabox($post) {
        // Add nonce for security
        wp_nonce_field('exif_harvester_metabox_nonce', 'exif_harvester_metabox_nonce');
        
        // Get current EXIF data
        $camera = get_post_meta($post->ID, 'camera', true);
        $lens = get_post_meta($post->ID, 'lens', true);
        $gps = get_post_meta($post->ID, 'GPS', true);
        $gps_alt = get_post_meta($post->ID, 'GPSAlt', true);
        $weather = get_post_meta($post->ID, 'wXSummary', true);
        $temperature = get_post_meta($post->ID, 'temperature', true);
        $datetime_original = get_post_meta($post->ID, 'dateTimeOriginal', true);
        $weather_api_urls = get_post_meta($post->ID, '_weather_api_urls', true);
        
        echo '<div id="exif-metabox-content">';
        echo '<div class="exif-data-display">';
        
        if ($camera || $lens || $gps || $gps_alt || $weather || $datetime_original) {
            echo '<h4>' . __('Current EXIF Data:', 'exif-harvester') . '</h4>';
            
            if ($camera) {
                echo '<p><strong>' . __('Camera:', 'exif-harvester') . '</strong><br>' . esc_html($camera) . '</p>';
            }
            
            if ($lens) {
                echo '<p><strong>' . __('Lens:', 'exif-harvester') . '</strong><br>' . esc_html($lens) . '</p>';
            }
            
            if ($datetime_original) {
                echo '<p><strong>' . __('Date Taken:', 'exif-harvester') . '</strong><br>' . esc_html($datetime_original) . '</p>';
            }
            
            if ($gps) {
                echo '<p><strong>' . __('GPS:', 'exif-harvester') . '</strong><br>' . esc_html($gps) . '</p>';
            }
            
            if ($gps_alt) {
                echo '<p><strong>' . __('Altitude:', 'exif-harvester') . '</strong><br>' . esc_html($gps_alt) . '</p>';
            }
            
            if ($weather) {
                $temp_display = $temperature ? ' (' . $temperature . '°C)' : '';
                echo '<p><strong>' . __('Weather:', 'exif-harvester') . '</strong><br>' . esc_html($weather) . $temp_display . '</p>';
            }
        } else {
            echo '<p class="no-exif-data">' . __('No EXIF data found for this post.', 'exif-harvester') . '</p>';
        }
        
        // Show API URLs that were attempted (for debugging)
        if ($this->settings['weather_api_enabled']) {
            echo '<div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd;">';
            echo '<p style="color: #646970; font-size: 11px; margin-bottom: 5px;"><strong>🔧 Weather API Debug URLs:</strong></p>';
            
            if (!empty($weather_api_urls) && is_array($weather_api_urls)) {
                echo '<div style="background: #f0f8ff; border: 1px solid #0073aa; padding: 8px; border-radius: 3px; font-size: 11px; font-family: monospace; word-break: break-all;">';
                foreach ($weather_api_urls as $index => $url) {
                    $endpoint_type = (strpos($url, 'timemachine') !== false) ? 'Time Machine' : 'Current API';
                    echo '<div style="margin-bottom: 8px; color: #0073aa;">';
                    echo '<strong>' . ($index + 1) . '. ' . esc_html($endpoint_type) . ':</strong><br>';
                    echo '<span style="color: #333; user-select: all; cursor: text; padding: 2px; background: rgba(255,255,255,0.7); border-radius: 2px; display: inline-block; width: 100%; box-sizing: border-box;">' . esc_html($url) . '</span>';
                    echo '</div>';
                }
                echo '</div>';
                echo '<p style="color: #d63638; font-size: 10px; margin-top: 5px; font-style: italic;">⚠️ These URLs contain your real API key - don\'t share publicly!</p>';
            } else {
                echo '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 8px; border-radius: 3px; font-size: 11px; color: #999; font-style: italic;">';
                echo 'No weather API URLs recorded yet. Click "Refresh EXIF Data" to see the testable URLs.';
                echo '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<div class="exif-actions">';
        echo '<p><button type="button" id="refresh-exif-btn" class="button button-secondary" data-post-id="' . esc_attr($post->ID) . '">';
        echo __('Refresh EXIF Data', 'exif-harvester');
        echo '</button></p>';
        
        echo '<p class="description">';
        echo __('Click to extract EXIF data from the first image in this post. This will also attempt to fetch weather data if GPS coordinates are available.', 'exif-harvester');
        echo '</p>';
        
        echo '<div id="exif-refresh-status" style="display: none;"></div>';
        echo '</div>';
        echo '</div>';
        
        // Enqueue scripts for this specific screen
        wp_enqueue_script('jquery');
        add_action('admin_footer', array($this, 'exif_metabox_script'));
    }
    
    /**
     * Output JavaScript for EXIF metabox functionality
     */
    public function exif_metabox_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#refresh-exif-btn').on('click', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var statusDiv = $('#exif-refresh-status');
                
                // Disable button and show loading
                button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'exif-harvester')); ?>');
                statusDiv.html('<p class="notice notice-info inline" style="margin: 10px 0; padding: 5px;"><strong><?php echo esc_js(__('Processing EXIF data...', 'exif-harvester')); ?></strong></p>').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'exif_harvester_manual_process',
                        post_id: postId,
                        nonce: $('#exif_harvester_metabox_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update the display with new data
                            statusDiv.html('<p class="notice notice-success inline" style="margin: 10px 0; padding: 5px;"><strong><?php echo esc_js(__('EXIF data refreshed successfully!', 'exif-harvester')); ?></strong></p>');
                            
                            // Reload the metabox content
                            if (response.data.html) {
                                $('.exif-data-display').html(response.data.html);
                            }
                        } else {
                            statusDiv.html('<p class="notice notice-error inline" style="margin: 10px 0; padding: 5px;"><strong><?php echo esc_js(__('Error:', 'exif-harvester')); ?></strong> ' + (response.data || '<?php echo esc_js(__('An unknown error occurred.', 'exif-harvester')); ?>') + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        statusDiv.html('<p class="notice notice-error inline" style="margin: 10px 0; padding: 5px;"><strong><?php echo esc_js(__('Error:', 'exif-harvester')); ?></strong> <?php echo esc_js(__('Failed to communicate with server.', 'exif-harvester')); ?></p>');
                        console.log('EXIF refresh error:', xhr, status, error);
                    },
                    complete: function() {
                        // Re-enable button
                        button.prop('disabled', false).text('<?php echo esc_js(__('Refresh EXIF Data', 'exif-harvester')); ?>');
                        
                        // Hide status message after 5 seconds
                        setTimeout(function() {
                            statusDiv.fadeOut();
                        }, 5000);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for manual EXIF processing
     */
    public function ajax_manual_process() {
        // Security checks
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'exif-harvester'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'exif_harvester_metabox_nonce')) {
            wp_send_json_error(__('Security check failed.', 'exif-harvester'));
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID.', 'exif-harvester'));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(__('Post not found.', 'exif-harvester'));
        }
        
        // Process EXIF data (skip async weather processing for manual refresh)
        $this->process_post_exif($post_id, $post, true, null, true);
        
        // Process weather data synchronously for manual refresh with error capture
        $weather_error = null;
        if ($this->settings['weather_api_enabled'] && !empty($this->settings['pirate_weather_api_key'])) {
            try {
                // Force immediate weather processing and capture any errors
                $weather_error = $this->process_weather_data_sync($post_id);
            } catch (Exception $e) {
                $weather_error = 'Weather processing exception: ' . $e->getMessage();
            }
        }
        
        // Get updated EXIF data
        $camera = get_post_meta($post_id, 'camera', true);
        $lens = get_post_meta($post_id, 'lens', true);
        $gps = get_post_meta($post_id, 'GPS', true);
        $gps_alt = get_post_meta($post_id, 'GPSAlt', true);
        $weather = get_post_meta($post_id, 'wXSummary', true);
        $temperature = get_post_meta($post_id, 'temperature', true);
        $datetime_original = get_post_meta($post_id, 'dateTimeOriginal', true);
        $weather_api_urls = get_post_meta($post_id, '_weather_api_urls', true);
        
        // Generate updated HTML
        $html = '';
        if ($camera || $lens || $gps || $gps_alt || $weather || $datetime_original) {
            $html .= '<h4>' . __('Current EXIF Data:', 'exif-harvester') . '</h4>';
            
            if ($camera) {
                $html .= '<p><strong>' . __('Camera:', 'exif-harvester') . '</strong><br>' . esc_html($camera) . '</p>';
            }
            
            if ($lens) {
                $html .= '<p><strong>' . __('Lens:', 'exif-harvester') . '</strong><br>' . esc_html($lens) . '</p>';
            }
            
            if ($datetime_original) {
                $html .= '<p><strong>' . __('Date Taken:', 'exif-harvester') . '</strong><br>' . esc_html($datetime_original) . '</p>';
            }
            
            if ($gps) {
                $html .= '<p><strong>' . __('GPS:', 'exif-harvester') . '</strong><br>' . esc_html($gps) . '</p>';
            }
            
            if ($gps_alt) {
                $html .= '<p><strong>' . __('Altitude:', 'exif-harvester') . '</strong><br>' . esc_html($gps_alt) . '</p>';
            }
            
            if ($weather) {
                $temp_display = $temperature ? ' (' . $temperature . '°C)' : '';
                $html .= '<p><strong>' . __('Weather:', 'exif-harvester') . '</strong><br>' . esc_html($weather) . $temp_display . '</p>';
            } elseif ($this->settings['weather_api_enabled'] && $weather_error) {
                // Show weather error if weather is enabled but failed
                $html .= '<p><strong>' . __('Weather:', 'exif-harvester') . '</strong><br><span style="color: #d63638;">' . esc_html($weather_error) . '</span></p>';
            }
        } else {
            $html .= '<p class="no-exif-data">' . __('No EXIF data found for this post.', 'exif-harvester') . '</p>';
        }
        
        // Add weather processing info if weather API is enabled
        if ($this->settings['weather_api_enabled']) {
            if ($weather_error) {
                $html .= '<p class="weather-status" style="color: #d63638; font-style: italic; margin-top: 10px;"><strong>Weather API:</strong> ' . esc_html($weather_error) . '</p>';
            } elseif ($weather && $temperature) {
                $html .= '<p class="weather-status" style="color: #00a32a; font-style: italic; margin-top: 10px;"><strong>Weather API:</strong> Successfully retrieved weather data</p>';
            } elseif ($gps && $datetime_original) {
                $html .= '<p class="weather-status" style="color: #dba617; font-style: italic; margin-top: 10px;"><strong>Weather API:</strong> GPS and date found but no weather data retrieved</p>';
            } else {
                $html .= '<p class="weather-status" style="color: #646970; font-style: italic; margin-top: 10px;"><strong>Weather API:</strong> Missing GPS coordinates or date/time data</p>';
            }
            
            // Show API URLs that were attempted (for debugging)
            if (!empty($weather_api_urls) && is_array($weather_api_urls)) {
                $html .= '<p class="weather-api-urls" style="color: #646970; font-size: 11px; margin-top: 5px; margin-bottom: 0;"><strong>🔧 Debug URLs (copy to browser):</strong></p>';
                $html .= '<div style="background: #f0f8ff; border: 1px solid #0073aa; padding: 8px; margin-top: 5px; border-radius: 3px; font-size: 11px; font-family: monospace; word-break: break-all;">';
                foreach ($weather_api_urls as $index => $url) {
                    // Additional debug info
                    $endpoint_type = (strpos($url, 'timemachine') !== false) ? 'Time Machine' : 'Current API';
                    $html .= '<div style="margin-bottom: 8px; color: #0073aa;">';
                    $html .= '<strong>' . ($index + 1) . '. ' . $endpoint_type . ':</strong><br>';
                    $html .= '<span style="color: #333; user-select: all; cursor: text; padding: 2px; background: rgba(255,255,255,0.7); border-radius: 2px;">' . esc_html($url) . '</span>';
                    $html .= '</div>';
                }
                $html .= '</div>';
                $html .= '<p style="color: #d63638; font-size: 10px; margin-top: 5px; font-style: italic;">⚠️ These URLs contain your real API key - don\'t share publicly!</p>';
            } else {
                // Show when no API URLs are available
                $html .= '<p class="weather-api-urls" style="color: #999; font-size: 11px; margin-top: 5px; font-style: italic;">No weather API URLs recorded yet.</p>';
            }
        }
        
        wp_send_json_success(array(
            'message' => __('EXIF data processed successfully', 'exif-harvester'),
            'html' => $html
        ));
    }
    
    /**
     * AJAX handler to get fresh post data for table row updates
     */
    public function ajax_get_post_data() {
        // Security checks
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'exif-harvester'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'exif_harvester_metabox_nonce')) {
            wp_send_json_error(__('Security check failed.', 'exif-harvester'));
        }
        
        $post_id = intval($_POST['post_id']);
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID.', 'exif-harvester'));
        }
        
        // Get fresh EXIF data
        $camera = get_post_meta($post_id, 'camera', true);
        $lens = get_post_meta($post_id, 'lens', true);
        $gps_raw = get_post_meta($post_id, 'GPS', true);
        $location = get_post_meta($post_id, 'location', true);
        $weather = get_post_meta($post_id, 'wXSummary', true);
        $datetime_original = get_post_meta($post_id, 'dateTimeOriginal', true);
        
        // Format GPS coordinates for display
        $gps_formatted = '';
        if ($gps_raw) {
            $gps_parts = explode(',', $gps_raw);
            if (count($gps_parts) === 2) {
                $lat = trim($gps_parts[0]);
                $lng = trim($gps_parts[1]);
                $gps_formatted = '<span title="' . esc_attr($gps_raw) . '">' . 
                               number_format(floatval($lat), 4) . ', ' . 
                               number_format(floatval($lng), 4) . '</span>';
            } else {
                $gps_formatted = esc_html($gps_raw);
            }
        }
        
        wp_send_json_success(array(
            'camera' => $camera ? esc_html($camera) : null,
            'lens' => $lens ? esc_html($lens) : null,
            'gps' => $gps_formatted ?: null,
            'location' => $location ? esc_html($location) : null,
            'weather' => $weather ? esc_html($weather) : null,
            'datetime_original' => $datetime_original ? esc_html($datetime_original) : null
        ));
    }
    
    /**
     * Add SEO meta description metabox
     */
    public function add_seo_metabox() {
        $enabled_post_types = $this->settings['enabled_post_types'];
        
        foreach ($enabled_post_types as $post_type) {
            add_meta_box(
                'exif_harvester_seo_metabox',
                __('SEO Meta Description', 'exif-harvester'),
                array($this, 'display_seo_metabox'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Display SEO metabox content
     */
    public function display_seo_metabox($post) {
        // Get current SEO description
        $seo_description = get_post_meta($post->ID, 'seo_description', true);
        
        echo '<div id="seo-metabox-content">';
        
        if (!empty($seo_description)) {
            echo '<div class="seo-description-display">';
            echo '<h4>' . __('Current SEO Description:', 'exif-harvester') . '</h4>';
            echo '<p style="background: #f7f7f7; padding: 10px; border-left: 4px solid #2271b1; margin: 10px 0;">';
            echo '<strong>Length:</strong> ' . strlen($seo_description) . ' characters<br>';
            echo '<strong>Description:</strong> ' . esc_html($seo_description);
            echo '</p>';
            
            if (strlen($seo_description) > 155) {
                echo '<p style="color: #d63301;"><strong>Warning:</strong> Description is longer than recommended 155 characters.</p>';
            } else {
                echo '<p style="color: #00a32a;">✓ Good length for SEO</p>';
            }
            echo '</div>';
        } else {
            echo '<p class="no-seo-description">' . __('No SEO description generated yet.', 'exif-harvester') . '</p>';
        }
        
        echo '<div class="seo-actions">';
        if (!empty($seo_description)) {
            echo '<p><button type="button" id="regenerate-seo-btn" class="button button-secondary" data-post-id="' . esc_attr($post->ID) . '">';
            echo __('Regenerate SEO Description', 'exif-harvester');
            echo '</button></p>';
        } else {
            echo '<p><button type="button" id="generate-seo-btn" class="button button-primary" data-post-id="' . esc_attr($post->ID) . '">';
            echo __('Generate SEO Description', 'exif-harvester');
            echo '</button></p>';
        }
        
        echo '<p class="description">';
        echo __('Auto-generates an SEO-optimized meta description based on EXIF data, location, weather, and post tags.', 'exif-harvester');
        echo '</p>';
        
        echo '<div id="seo-generation-status" style="display: none;"></div>';
        echo '</div>';
        echo '</div>';
        
        // Add JavaScript for SEO functionality
        add_action('admin_footer', array($this, 'seo_metabox_script'));
    }
    
    /**
     * Output JavaScript for SEO metabox functionality
     */
    public function seo_metabox_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#generate-seo-btn, #regenerate-seo-btn').on('click', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var statusDiv = $('#seo-generation-status');
                var isRegenerate = button.attr('id') === 'regenerate-seo-btn';
                
                // Debug logging
                console.log('EXIF SEO Debug: Button clicked, post ID:', postId, 'isRegenerate:', isRegenerate);
                
                // Disable button and show loading
                button.prop('disabled', true).text(isRegenerate ? '<?php echo esc_js(__('Regenerating...', 'exif-harvester')); ?>' : '<?php echo esc_js(__('Generating...', 'exif-harvester')); ?>');
                statusDiv.html('<p class="notice notice-info inline" style="margin: 10px 0; padding: 5px;"><strong><?php echo esc_js(__('Generating SEO description...', 'exif-harvester')); ?></strong></p>').show();
                
                console.log('EXIF SEO Debug: Making AJAX call with data:', {
                    action: 'exif_harvester_generate_single_seo_description',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('exif_harvester_seo_admin'); ?>'
                });
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'exif_harvester_generate_single_seo_description',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('exif_harvester_seo_admin'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data.description) {
                            var description = response.data.description;
                            var length = response.data.length;
                            
                            // Update the display
                            var html = '<h4><?php echo esc_js(__('Current SEO Description:', 'exif-harvester')); ?></h4>';
                            html += '<p style="background: #f7f7f7; padding: 10px; border-left: 4px solid #2271b1; margin: 10px 0;">';
                            html += '<strong>Length:</strong> ' + length + ' characters<br>';
                            html += '<strong>Description:</strong> ' + description;
                            html += '</p>';
                            
                            if (length > 155) {
                                html += '<p style="color: #d63301;"><strong>Warning:</strong> Description is longer than recommended 155 characters.</p>';
                            } else {
                                html += '<p style="color: #00a32a;">✓ Good length for SEO</p>';
                            }
                            
                            $('.seo-description-display').remove();
                            $('.no-seo-description').remove();
                            $('#seo-metabox-content').prepend('<div class="seo-description-display">' + html + '</div>');
                            
                            // Update button
                            button.attr('id', 'regenerate-seo-btn').removeClass('button-primary').addClass('button-secondary').text('<?php echo esc_js(__('Regenerate SEO Description', 'exif-harvester')); ?>');
                            
                            statusDiv.html('<p class="notice notice-success inline" style="margin: 10px 0; padding: 5px;"><strong><?php echo esc_js(__('SEO description generated successfully!', 'exif-harvester')); ?></strong></p>');
                        } else {
                            statusDiv.html('<p class="notice notice-error inline" style="margin: 10px 0; padding: 5px;"><strong><?php echo esc_js(__('Error:', 'exif-harvester')); ?></strong> ' + (response.data || '<?php echo esc_js(__('Failed to generate SEO description.', 'exif-harvester')); ?>') + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        statusDiv.html('<p class="notice notice-error inline" style="margin: 10px 0; padding: 5px;"><strong><?php echo esc_js(__('Error:', 'exif-harvester')); ?></strong> <?php echo esc_js(__('Failed to communicate with server.', 'exif-harvester')); ?></p>');
                        console.log('SEO generation error:', xhr, status, error);
                    },
                    complete: function() {
                        // Re-enable button
                        button.prop('disabled', false);
                        if (!button.hasClass('regenerate-seo-btn')) {
                            button.text('<?php echo esc_js(__('Generate SEO Description', 'exif-harvester')); ?>');
                        } else {
                            button.text('<?php echo esc_js(__('Regenerate SEO Description', 'exif-harvester')); ?>');
                        }
                        
                        // Hide status message after 5 seconds
                        setTimeout(function() {
                            statusDiv.fadeOut();
                        }, 5000);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for saving camera mappings
     */
    public function ajax_save_camera() {
        check_ajax_referer('exif_harvester_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'exif-harvester'));
        }
        
        global $wpdb;
        $camera_table = $wpdb->prefix . 'exif_harvester_cameras';
        
        $id = intval($_POST['id'] ?? 0);
        $raw_name = sanitize_text_field($_POST['raw_name'] ?? '');
        $pretty_name = sanitize_text_field($_POST['pretty_name'] ?? '');
        
        if (empty($raw_name) || empty($pretty_name)) {
            wp_send_json_error(__('Both raw name and pretty name are required.', 'exif-harvester'));
        }
        
        $data = array(
            'raw_name' => $raw_name,
            'pretty_name' => $pretty_name
        );
        
        if ($id > 0) {
            // Update existing
            $result = $wpdb->update($camera_table, $data, array('id' => $id), array('%s', '%s'), array('%d'));
        } else {
            // Insert new
            $result = $wpdb->insert($camera_table, $data, array('%s', '%s'));
            $id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'id' => $id,
                'raw_name' => $raw_name,
                'pretty_name' => $pretty_name
            ));
        } else {
            wp_send_json_error(__('Failed to save camera mapping.', 'exif-harvester'));
        }
    }
    
    /**
     * AJAX handler for deleting camera mappings
     */
    public function ajax_delete_camera() {
        check_ajax_referer('exif_harvester_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'exif-harvester'));
        }
        
        global $wpdb;
        $camera_table = $wpdb->prefix . 'exif_harvester_cameras';
        
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            wp_send_json_error(__('Invalid camera ID.', 'exif-harvester'));
        }
        
        $result = $wpdb->delete($camera_table, array('id' => $id), array('%d'));
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete camera mapping.', 'exif-harvester'));
        }
    }
    
    /**
     * AJAX handler for saving lens mappings
     */
    public function ajax_save_lens() {
        check_ajax_referer('exif_harvester_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'exif-harvester'));
        }
        
        global $wpdb;
        $lens_table = $wpdb->prefix . 'exif_harvester_lenses';
        
        $id = intval($_POST['id'] ?? 0);
        $raw_name = sanitize_text_field($_POST['raw_name'] ?? '');
        $pretty_name = sanitize_text_field($_POST['pretty_name'] ?? '');
        
        if (empty($raw_name) || empty($pretty_name)) {
            wp_send_json_error(__('Both raw name and pretty name are required.', 'exif-harvester'));
        }
        
        $data = array(
            'raw_name' => $raw_name,
            'pretty_name' => $pretty_name
        );
        
        if ($id > 0) {
            // Update existing
            $result = $wpdb->update($lens_table, $data, array('id' => $id), array('%s', '%s'), array('%d'));
        } else {
            // Insert new
            $result = $wpdb->insert($lens_table, $data, array('%s', '%s'));
            $id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'id' => $id,
                'raw_name' => $raw_name,
                'pretty_name' => $pretty_name
            ));
        } else {
            wp_send_json_error(__('Failed to save lens mapping.', 'exif-harvester'));
        }
    }
    
    /**
     * AJAX handler for deleting lens mappings
     */
    public function ajax_delete_lens() {
        check_ajax_referer('exif_harvester_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'exif-harvester'));
        }
        
        global $wpdb;
        $lens_table = $wpdb->prefix . 'exif_harvester_lenses';
        
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            wp_send_json_error(__('Invalid lens ID.', 'exif-harvester'));
        }
        
        $result = $wpdb->delete($lens_table, array('id' => $id), array('%d'));
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete lens mapping.', 'exif-harvester'));
        }
    }
    
    /**
     * AJAX handler for saving location corrections
     */
    public function ajax_save_location() {
        error_log('EXIF Harvester: ajax_save_location called');
        error_log('EXIF Harvester: POST data: ' . print_r($_POST, true));
        
        // Check if nonce exists before verifying
        if (!isset($_POST['nonce'])) {
            error_log('EXIF Harvester: No nonce field in POST data');
            wp_send_json_error('No nonce provided');
            return;
        }
        
        error_log('EXIF Harvester: Nonce provided: ' . $_POST['nonce']);
        
        check_ajax_referer('exif_harvester_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            error_log('EXIF Harvester: ajax_save_location - insufficient permissions');
            wp_die(__('Insufficient permissions', 'exif-harvester'));
        }
        
        error_log('EXIF Harvester: ajax_save_location - permissions OK');
        
        global $wpdb;
        $location_table = $wpdb->prefix . 'exif_harvester_location_corrections';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$location_table'") == $location_table;
        error_log('EXIF Harvester: ajax_save_location - table exists: ' . ($table_exists ? 'yes' : 'no'));
        
        if (!$table_exists) {
            error_log('EXIF Harvester: ajax_save_location - table does not exist, creating tables');
            $this->create_database_tables();
        }
        
        $id = intval($_POST['id'] ?? 0);
        $truncated_name = sanitize_text_field($_POST['truncated_name'] ?? '');
        $full_name = sanitize_text_field($_POST['full_name'] ?? '');
        
        error_log('EXIF Harvester: ajax_save_location - truncated_name: ' . $truncated_name . ', full_name: ' . $full_name);
        
        if (empty($truncated_name) || empty($full_name)) {
            error_log('EXIF Harvester: ajax_save_location - empty fields error');
            wp_send_json_error(__('Both truncated and full location names are required.', 'exif-harvester'));
        }
        
        if (strlen($truncated_name) > 32) {
            wp_send_json_error(__('Truncated location name cannot exceed 32 characters.', 'exif-harvester'));
        }
        
        if ($id > 0) {
            // Update existing location correction
            $result = $wpdb->update(
                $location_table,
                array(
                    'truncated_name' => $truncated_name,
                    'full_name' => $full_name
                ),
                array('id' => $id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new location correction
            $result = $wpdb->insert(
                $location_table,
                array(
                    'truncated_name' => $truncated_name,
                    'full_name' => $full_name
                ),
                array('%s', '%s')
            );
            
            if ($result !== false) {
                $id = $wpdb->insert_id;
            }
        }
        
        if ($result !== false) {
            error_log('EXIF Harvester: ajax_save_location - success, returning data');
            $data = array(
                'id' => $id,
                'truncated_name' => $truncated_name,
                'full_name' => $full_name
            );
            wp_send_json_success($data);
        } else {
            error_log('EXIF Harvester: ajax_save_location - database error: ' . $wpdb->last_error);
            wp_send_json_error(__('Failed to save location correction.', 'exif-harvester') . ' Database error: ' . $wpdb->last_error);
        }
    }
    
    /**
     * AJAX handler for deleting location corrections
     */
    public function ajax_delete_location() {
        check_ajax_referer('exif_harvester_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'exif-harvester'));
        }
        
        global $wpdb;
        $location_table = $wpdb->prefix . 'exif_harvester_location_corrections';
        
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            wp_send_json_error(__('Invalid location ID.', 'exif-harvester'));
        }
        
        $result = $wpdb->delete($location_table, array('id' => $id), array('%d'));
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete location correction.', 'exif-harvester'));
        }
    }
    
    /**
     * Main function to process EXIF data when posts are saved
     */
    public function process_post_exif($post_id, $post, $update, $post_before = null, $is_manual_refresh = false) {
        // ALWAYS log when hook is triggered - test logging
        error_log('EXIF Harvester: wp_after_insert_post hook triggered for post ' . $post_id . ' - BASIC TEST LOG');
        
        // Debug: Log when hook is triggered
        if (is_user_logged_in() && current_user_can('manage_options')) {
            error_log('EXIF Harvester: wp_after_insert_post hook triggered for post ' . $post_id . ' (type: ' . $post->post_type . ', status: ' . $post->post_status . ', update: ' . ($update ? 'true' : 'false') . ')');
        }
        
        // Prevent duplicate processing in the same request
        static $processed_posts = array();
        if (in_array($post_id, $processed_posts)) {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Skipping duplicate processing for post ' . $post_id);
            }
            return;
        }
        $processed_posts[] = $post_id;
        
        // Avoid infinite loops and auto-saves
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Skipping autosave/revision for post ' . $post_id);
            }
            return;
        }
        
        // Don't process if post status is trash
        if ($post->post_status === 'trash') {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Skipping trash post ' . $post_id);
            }
            return;
        }
        
        // Check if this post type is enabled
        if (!in_array($post->post_type, $this->settings['enabled_post_types'])) {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Post type "' . $post->post_type . '" not enabled (enabled: ' . implode(', ', $this->settings['enabled_post_types']) . ')');
            }
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: User lacks edit permissions for post ' . $post_id);
            }
            return;
        }
        
        // Check if we should clear metadata
        $should_clear_metadata = false;
        $has_existing_metadata = false;
        
        if ($this->settings['delete_on_update']) {
            if ($update) {
                // This is definitely an update
                $should_clear_metadata = true;
            } else {
                // Check if this post already has EXIF metadata (suggesting it was processed before)
                // If it has metadata but we're processing again, the image likely changed
                $has_existing_metadata = metadata_exists('post', $post_id, 'camera') || 
                                       metadata_exists('post', $post_id, 'GPS') ||
                                       metadata_exists('post', $post_id, 'dateOriginal');
                
                if ($has_existing_metadata) {
                    $should_clear_metadata = true;
                }
            }
        }
        
        if ($should_clear_metadata) {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Deleting existing metadata for post ' . $post_id . ' (update=' . ($update ? 'true' : 'false') . ', has_existing=' . ($has_existing_metadata ? 'true' : 'false') . ')');
            }
            $this->delete_exif_metadata($post_id);
        } else {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: NOT deleting metadata for post ' . $post_id . ' (no clear conditions met)');
            }
        }
        
        // Process EXIF data
        $this->extract_and_store_exif($post_id);
        
        // Check if metadata still exists after processing (to catch if something is re-adding it)
        if (is_user_logged_in() && current_user_can('manage_options')) {
            $metadata_after_processing = array();
            foreach (array('camera', 'location', 'city', 'GPS') as $key_field) {
                if (metadata_exists('post', $post_id, $key_field)) {
                    $value = get_post_meta($post_id, $key_field, true);
                    $metadata_after_processing[] = $key_field . '=' . $value;
                }
            }
            
            if (!empty($metadata_after_processing)) {
                error_log('EXIF Harvester: Metadata after processing: ' . implode(', ', $metadata_after_processing));
            } else {
                error_log('EXIF Harvester: No key metadata found after processing - extraction may have failed');
            }
        }
    }
    
    /**
     * Delete existing EXIF metadata
     */
    private function delete_exif_metadata($post_id) {
        if (is_user_logged_in() && current_user_can('manage_options')) {
            error_log('EXIF Harvester: Starting metadata deletion for post ' . $post_id);
            error_log('EXIF Harvester: Custom fields to delete: ' . implode(', ', $this->custom_fields));
        }
        
        // Delete all standard EXIF custom fields
        $deleted_count = 0;
        foreach ($this->custom_fields as $field) {
            $exists = metadata_exists('post', $post_id, $field);
            if ($exists) {
                $old_value = get_post_meta($post_id, $field, true);
                $all_values = get_post_meta($post_id, $field, false); // Get all values (array)
                
                // Delete all instances of this meta key
                $delete_result = delete_post_meta($post_id, $field);
                
                if (is_user_logged_in() && current_user_can('manage_options')) {
                    $still_exists = metadata_exists('post', $post_id, $field);
                    error_log('EXIF Harvester: Deleting field "' . $field . '" (value_count: ' . count($all_values) . ', old_value: "' . $old_value . '", delete_result: ' . ($delete_result ? 'success' : 'failed') . ', still_exists: ' . ($still_exists ? 'yes' : 'no') . ')');
                }
                
                if ($delete_result) {
                    $deleted_count++;
                }
            }
        }
        
        // Delete weather metadata
        delete_post_meta($post_id, '_weather_last_attempt');
        delete_post_meta($post_id, '_weather_last_failure');
        delete_post_meta($post_id, '_weather_last_success');
        delete_post_meta($post_id, '_weather_gps_used');
        delete_post_meta($post_id, '_weather_datetime_used');
        
        // Clear place taxonomy terms
        if (taxonomy_exists('place')) {
            $term_count = wp_count_terms(array('taxonomy' => 'place', 'object_ids' => $post_id));
            wp_set_post_terms($post_id, array(), 'place');
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Cleared ' . $term_count . ' place taxonomy terms for post ' . $post_id);
            }
        }
        
        // Also try direct database cleanup as a fallback
        global $wpdb;
        $meta_keys = "'" . implode("','", array_map('esc_sql', $this->custom_fields)) . "'";
        $direct_delete_result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ($meta_keys)",
            $post_id
        ));
        
        if (is_user_logged_in() && current_user_can('manage_options')) {
            error_log('EXIF Harvester: Deleted ' . $deleted_count . ' metadata fields for post ' . $post_id . ' (direct_delete: ' . $direct_delete_result . ' rows)');
        }
        
        // Clear WordPress meta cache for this post
        wp_cache_delete($post_id, 'post_meta');
        
        // Double-check that fields are actually deleted
        if (is_user_logged_in() && current_user_can('manage_options')) {
            $still_existing_fields = array();
            foreach ($this->custom_fields as $field) {
                if (metadata_exists('post', $post_id, $field)) {
                    $value = get_post_meta($post_id, $field, true);
                    $still_existing_fields[] = $field . '=' . $value;
                }
            }
            
            if (!empty($still_existing_fields)) {
                error_log('EXIF Harvester: WARNING - Fields still exist after deletion: ' . implode(', ', $still_existing_fields));
            } else {
                error_log('EXIF Harvester: Confirmed - all fields successfully deleted');
            }
            
            // Also check direct database query
            global $wpdb;
            $db_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ('" . implode("','", array_map('esc_sql', $this->custom_fields)) . "')",
                $post_id
            ));
            error_log('EXIF Harvester: Direct database check shows ' . $db_count . ' metadata rows remaining');
        }
    }
    
    /**
     * Handle save_post hook (catches content changes that wp_after_insert_post might miss)
     */
    public function process_post_save($post_id, $post, $update) {
        // ALWAYS log when hook is triggered - test logging
        error_log('EXIF Harvester: save_post hook triggered for post ' . $post_id . ' - BASIC TEST LOG');
        
        // Avoid infinite loops and auto-saves
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Don't process if post status is trash or being trashed
        if ($post->post_status === 'trash') {
            return;
        }
        
        // Don't process if this is a deletion operation
        if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('trash', 'delete', 'bulk_trash', 'bulk_delete'))) {
            return;
        }
        
        // Check if this post type is enabled
        if (!in_array($post->post_type, $this->settings['enabled_post_types'])) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (is_user_logged_in() && current_user_can('manage_options')) {
            error_log('EXIF Harvester: save_post hook triggered for post ' . $post_id . ' (update=' . ($update ? 'true' : 'false') . ')');
        }
        
        // Process with the same logic as wp_after_insert_post
        $this->process_post_exif($post_id, $post, $update, null);
    }
    
    /**
     * Process EXIF data during Quick Edit operations
     */
    public function process_quick_edit() {
        // Verify AJAX request and nonce
        if (!wp_doing_ajax() || !current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to edit posts.'));
        }
        
        // Get post ID from the AJAX request
        $post_id = isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0;
        if (!$post_id) {
            return;
        }
        
        // Get the post object
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, ['post', 'page'])) {
            return;
        }
        
        if (is_user_logged_in() && current_user_can('manage_options')) {
            error_log('EXIF Harvester: Quick Edit triggered for post ' . $post_id);
        }
        
        // Process EXIF data - use true for $update since this is an edit operation
        $this->process_post_exif($post_id, $post, true, null);
    }
    

    
    /**
     * Extract and store EXIF data from post images
     */
    private function extract_and_store_exif($post_id) {
        if (is_user_logged_in() && current_user_can('manage_options')) {
            error_log('EXIF Harvester: Starting EXIF extraction for post ' . $post_id);
        }
        
        $attachment_id = null;
        $fullsize_path = '';
        $exif_data = false;
        
        // Extract the first image from post content
        $image_url = $this->catch_that_image($post_id);
        if (!empty($image_url)) {
            $attachment_id = attachment_url_to_postid($image_url);
            
            // If attachment_id is 0, the image might be a thumbnail - try to find the original
            if ($attachment_id == 0) {
                $attachment_id = $this->find_original_attachment_from_thumbnail($image_url);
            }
            
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Found image in post content: ' . $image_url . ' (attachment_id: ' . $attachment_id . ')');
            }
        } else {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: No image found in post content for post ' . $post_id);
            }
            // Early return if no image found
            return;
        }
        
        // Get file path and read EXIF data
        if ($attachment_id) {
            $fullsize_path = get_attached_file($attachment_id);
            
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Attachment file path: ' . ($fullsize_path ? $fullsize_path : 'empty') . ' (exists: ' . (file_exists($fullsize_path) ? 'yes' : 'no') . ')');
            }
            
            if (!empty($fullsize_path) && file_exists($fullsize_path)) {
                $exif_data = @exif_read_data($fullsize_path);
                if (is_user_logged_in() && current_user_can('manage_options')) {
                    error_log('EXIF Harvester: EXIF data from file path: ' . ($exif_data ? 'found' : 'not found'));
                }
            } else {
                // Fallback: try to read EXIF from image URL
                $image_url = wp_get_attachment_url($attachment_id);
                if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $exif_data = @exif_read_data($image_url);
                    if (is_user_logged_in() && current_user_can('manage_options')) {
                        error_log('EXIF Harvester: EXIF data from URL ' . $image_url . ': ' . ($exif_data ? 'found' : 'not found'));
                    }
                } else {
                    if (is_user_logged_in() && current_user_can('manage_options')) {
                        error_log('EXIF Harvester: No valid image URL found for attachment ' . $attachment_id);
                    }
                }
            }
        } else {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: No attachment ID found for image URL');
            }
        }
        
        // Process EXIF data if found
        if ($exif_data) {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Processing EXIF data for post ' . $post_id);
            }
            $this->process_exif_data($post_id, $exif_data, $fullsize_path);
        } else {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: No EXIF data found to process for post ' . $post_id);
            }
        }
        
        // ALWAYS process location-based data (weather, GMT offset, etc.) regardless of EXIF data availability
        // This ensures weather processing works even when EXIF data is missing but GPS/datetime exists in metadata
        
        // Ensure GMT offset is calculated after both GPS and datetime processing
        $this->ensure_gmt_offset($post_id);
        
        // Process weather data if enabled and API key is available (async for normal saves, sync handled separately for manual refresh)
        if (!$is_manual_refresh) {
            error_log('EXIF Harvester: WEATHER CHECK (post-EXIF) - Enabled: ' . ($this->settings['weather_api_enabled'] ? 'yes' : 'no') . ', API Key: ' . (empty($this->settings['pirate_weather_api_key']) ? 'empty' : 'configured') . ' for post ' . $post_id);
            
            if ($this->settings['weather_api_enabled'] && !empty($this->settings['pirate_weather_api_key'])) {
                try {
                    error_log('EXIF Harvester: Scheduling async weather processing (post-EXIF) for post ' . $post_id);
                    $this->schedule_async_weather_processing($post_id);
                } catch (Exception $e) {
                    error_log('EXIF Harvester: Error scheduling weather data processing (post-EXIF) for post ' . $post_id . ': ' . $e->getMessage());
                }
            } else {
                if (!$this->settings['weather_api_enabled']) {
                    error_log('EXIF Harvester: Weather API is disabled (post-EXIF) for post ' . $post_id);
                }
                if (empty($this->settings['pirate_weather_api_key'])) {
                    error_log('EXIF Harvester: Weather API key is empty (post-EXIF) for post ' . $post_id);
                }
            }
        } else {
            error_log('EXIF Harvester: Skipping async weather processing for manual refresh of post ' . $post_id . ' (handled separately)');
        }
        
        // Process SEO meta description after all other data is processed
        $this->process_seo_meta_description($post_id);
        
        // Always try to process caption from post content
        $this->process_caption($post_id);
    }
    
    /**
     * Process and store EXIF data
     */
    private function process_exif_data($post_id, $exif_data, $fullsize_path) {
        // Process photo dimensions
        exif_harvester_process_photo_dimensions($post_id, $fullsize_path);
        
        // Process camera information
        if (isset($exif_data['Model'])) {
            $camera = exif_harvester_pretty_print_camera($exif_data['Model']);
            $this->add_meta_if_not_exists($post_id, 'camera', $camera);
            
            // Set default lens for certain cameras
            exif_harvester_set_default_lens($camera, $post_id);
            
            // Handle special iPhone cases
            if ($camera == 'Apple iPhone' && isset($exif_data['UndefinedTag:0xA434'])) {
                $lens = $exif_data['UndefinedTag:0xA434'];
                if (strpos($lens, '15 Pro Max') !== false) {
                    $camera = 'Apple iPhone 15 Pro Max';
                    $this->add_meta_if_not_exists($post_id, 'camera', $camera);
                } elseif (strpos($lens, 'iPhone 6s') !== false) {
                    $camera = 'Apple iPhone 6s';
                    $this->add_meta_if_not_exists($post_id, 'camera', $camera);
                }
            }
        }
        
        // Process lens information
        if (isset($exif_data['UndefinedTag:0xA434'])) {
            $lens = exif_harvester_pretty_print_lens($exif_data['UndefinedTag:0xA434']);
            $this->add_meta_if_not_exists($post_id, 'lens', $lens);
        }
        
        // Process f-stop
        if (isset($exif_data['ApertureValue'])) {
            $fstop = exif_harvester_exif_get_fstop($exif_data);
            if ($fstop) {
                $this->add_meta_if_not_exists($post_id, 'fstop', $fstop);
            }
        }
        
        // Process ISO
        if (isset($exif_data['ISOSpeedRatings'])) {
            $iso = $exif_data['ISOSpeedRatings'] . ' ISO';
            $this->add_meta_if_not_exists($post_id, 'iso', $iso);
        }
        
        // Process shutter speed
        if (isset($exif_data['ShutterSpeedValue'])) {
            $shutterspeed = exif_harvester_exif_get_shutter($exif_data);
            if ($shutterspeed) {
                $this->add_meta_if_not_exists($post_id, 'shutterspeed', $shutterspeed);
            }
        }
        
        // Process focal length
        if (isset($exif_data['FocalLength'])) {
            $focallength = $exif_data['FocalLength'];
            if ($focallength !== null && strpos($focallength, '/') !== false) {
                $focallength = exif_harvester_convert_to_decimal($focallength);
            }
            $focallength = round((float)$focallength) . 'mm';
            $this->add_meta_if_not_exists($post_id, 'focallength', $focallength);
        }
        
        // Process date/time information
        if (isset($exif_data['DateTimeOriginal'])) {
            exif_harvester_process_datetime_original($post_id, $exif_data['DateTimeOriginal']);
        }
        
        // Process GPS information
        if (isset($exif_data['GPSLongitude']) && isset($exif_data['GPSLongitudeRef']) && 
            isset($exif_data['GPSLatitude']) && isset($exif_data['GPSLatitudeRef'])) {
            exif_harvester_process_gps_data($post_id, $exif_data);
        }
        
        // Process altitude
        if (isset($exif_data['GPSAltitude'])) {
            $alt = $exif_data['GPSAltitude'];
            $exAlt = explode('/', $alt);
            if (count($exAlt) === 2 && $exAlt[1] != 0) {
                $alt = $exAlt[0] / $exAlt[1];
                $alt = round((float) $alt, 2);
                $this->add_meta_if_not_exists($post_id, 'GPSAlt', $alt);
            }
        }
        
        // Process location information from IPTC metadata
        if (!empty($fullsize_path)) {
            try {
                $this->process_location_data($post_id, $fullsize_path);
            } catch (Exception $e) {
                error_log('EXIF Harvester: Error processing location data for post ' . $post_id . ': ' . $e->getMessage());
            }
        }
        
        // Ensure GMT offset is calculated after both GPS and datetime are processed
        $this->ensure_gmt_offset($post_id);
        
        // Schedule async weather data processing if enabled and API key is available
        error_log('EXIF Harvester: WEATHER CHECK - Enabled: ' . ($this->settings['weather_api_enabled'] ? 'yes' : 'no') . ', API Key: ' . (empty($this->settings['pirate_weather_api_key']) ? 'empty' : 'configured') . ' for post ' . $post_id);
        
        if ($this->settings['weather_api_enabled'] && !empty($this->settings['pirate_weather_api_key'])) {
            try {
                error_log('EXIF Harvester: Scheduling async weather processing for post ' . $post_id);
                $this->schedule_async_weather_processing($post_id);
            } catch (Exception $e) {
                error_log('EXIF Harvester: Error scheduling weather data processing for post ' . $post_id . ': ' . $e->getMessage());
            }
        } else {
            if (!$this->settings['weather_api_enabled']) {
                error_log('EXIF Harvester: Weather API is disabled for post ' . $post_id);
            }
            if (empty($this->settings['pirate_weather_api_key'])) {
                error_log('EXIF Harvester: Weather API key is empty for post ' . $post_id);
            }
        }
        
        // Process SEO meta description after all other data is processed
        $this->process_seo_meta_description($post_id);
    }
    
    /**
     * Process caption from post content
     */
    private function process_caption($post_id) {
        exif_harvester_process_caption($post_id);
    }
    
    /**
     * Extract first image from post content (fallback method)
     */
    private function catch_that_image($post_id) {
        return exif_harvester_catch_that_image($post_id);
    }
    
    /**
     * Ensure GMT offset is calculated and stored for timezone-aware weather lookups
     */
    private function ensure_gmt_offset($post_id) {
        // Check if we already have a GMT offset
        $existing_gmt_offset = get_post_meta($post_id, 'gmtOffset', true);
        if (!empty($existing_gmt_offset) && $existing_gmt_offset !== '') {
            error_log('EXIF Harvester: GMT offset already exists for post ' . $post_id . ': ' . $existing_gmt_offset . ' hours');
            return; // Already calculated
        }
        
        // Get required data
        $gps_coords = get_post_meta($post_id, 'GPS', true);
        $unix_time = get_post_meta($post_id, 'unixTime', true);
        
        if (empty($gps_coords) || empty($unix_time)) {
            error_log('EXIF Harvester: Cannot calculate GMT offset for post ' . $post_id . ' - missing GPS (' . ($gps_coords ? 'exists' : 'missing') . ') or unixTime (' . ($unix_time ? 'exists' : 'missing') . ')');
            return; // Missing required data
        }
        
        // Parse GPS coordinates
        $coords = explode(',', $gps_coords);
        if (count($coords) !== 2) {
            error_log('EXIF Harvester: Invalid GPS format for post ' . $post_id . ': ' . $gps_coords);
            return;
        }
        
        $lat = (float) trim($coords[0]);
        $lon = (float) trim($coords[1]);
        
        if ($lat == 0 || $lon == 0) {
            error_log('EXIF Harvester: Invalid GPS coordinates for post ' . $post_id . ': lat=' . $lat . ', lon=' . $lon);
            return;
        }
        
        error_log('EXIF Harvester: Calculating GMT offset for post ' . $post_id . ' (lat: ' . $lat . ', lon: ' . $lon . ', time: ' . $unix_time . ')');
        
        // Get TimezoneDB API key
        $timezonedb_api_key = isset($this->settings['timezonedb_api_key']) ? $this->settings['timezonedb_api_key'] : '';
        
        // Calculate GMT offset using TimezoneDB API
        $gmt_offset = exif_harvester_get_gmt_offset($lat, $lon, $unix_time, $timezonedb_api_key);
        
        if ($gmt_offset !== null && $gmt_offset !== false) {
            // Store the GMT offset
            add_post_meta($post_id, 'gmtOffset', $gmt_offset);
            error_log('EXIF Harvester: GMT offset saved for post ' . $post_id . ': ' . $gmt_offset . ' hours');
            
            // Also get and store timezone name for reference
            $timezone = exif_harvester_get_timezone($lat, $lon, $unix_time, $timezonedb_api_key);
            if ($timezone && !metadata_exists('post', $post_id, 'timeZone')) {
                add_post_meta($post_id, 'timeZone', $timezone);
                error_log('EXIF Harvester: Timezone saved for post ' . $post_id . ': ' . $timezone);
            }
        } else {
            error_log('EXIF Harvester: Failed to get GMT offset for post ' . $post_id);
        }
    }
    
    /**
     * Process SEO meta description for a post
     */
    private function process_seo_meta_description($post_id) {
        // Skip if already exists (only for automatic processing)
        $existing_description = get_post_meta($post_id, 'seo_description', true);
        if (!empty($existing_description)) {
            error_log('EXIF Harvester: SEO description already exists for post ' . $post_id);
            return;
        }
        
        // Generate SEO description
        try {
            $seo_description = exif_harvester_generate_seo_meta_description($post_id);
            
            if (!empty($seo_description)) {
                $this->add_meta_if_not_exists($post_id, 'seo_description', $seo_description);
                error_log('EXIF Harvester: SEO description generated for post ' . $post_id . ': ' . $seo_description);
            } else {
                error_log('EXIF Harvester: Could not generate SEO description for post ' . $post_id);
            }
        } catch (Exception $e) {
            error_log('EXIF Harvester: Error generating SEO description for post ' . $post_id . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Force regenerate SEO meta description for a post (used by manual actions)
     */
    public function force_regenerate_seo_description($post_id) {
        try {
            $seo_description = exif_harvester_generate_seo_meta_description($post_id);
            
            if (!empty($seo_description)) {
                update_post_meta($post_id, 'seo_description', $seo_description);
                error_log('EXIF Harvester: SEO description regenerated for post ' . $post_id . ': ' . $seo_description);
                return $seo_description;
            } else {
                error_log('EXIF Harvester: Could not regenerate SEO description for post ' . $post_id);
                return false;
            }
        } catch (Exception $e) {
            error_log('EXIF Harvester: Error regenerating SEO description for post ' . $post_id . ': ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Schedule async weather processing for a post
     */
    private function schedule_async_weather_processing($post_id) {
        // Prevent duplicate scheduling in the same request
        static $scheduled_posts = array();
        if (in_array($post_id, $scheduled_posts)) {
            error_log('EXIF Harvester: Weather processing already scheduled for post ' . $post_id . ' in this request');
            return;
        }
        $scheduled_posts[] = $post_id;
        
        // Clear any existing scheduled weather processing for this post
        wp_clear_scheduled_hook('exif_harvester_async_weather_processing', array($post_id));
        
        // Try WordPress cron scheduling first
        $scheduled = wp_schedule_single_event(time(), 'exif_harvester_async_weather_processing', array($post_id));
        
        if ($scheduled === false) {
            error_log('EXIF Harvester: WP Cron scheduling failed for post ' . $post_id . ', trying wp_remote_post spawn');
            
            // Try to spawn async request using wp_remote_post as fallback
            $spawn_success = $this->spawn_async_weather_request($post_id);
            
            if (!$spawn_success) {
                // Final fallback to immediate processing
                error_log('EXIF Harvester: All async methods failed, falling back to immediate weather processing for post ' . $post_id);
                $this->process_weather_data($post_id, true);
            }
        } else {
            error_log('EXIF Harvester: Successfully scheduled async weather processing for post ' . $post_id);
        }
    }
    
    /**
     * Spawn async weather request using wp_remote_post (fallback method)
     */
    private function spawn_async_weather_request($post_id) {
        // Create a nonce for security
        $nonce = wp_create_nonce('exif_harvester_async_weather_' . $post_id);
        
        // Prepare the async request
        $url = admin_url('admin-ajax.php');
        $body = array(
            'action' => 'exif_harvester_async_weather',
            'post_id' => $post_id,
            'nonce' => $nonce
        );
        
        // Make non-blocking request
        $response = wp_remote_post($url, array(
            'timeout' => 0.01, // Very short timeout to avoid blocking
            'blocking' => false, // Don't wait for response
            'body' => $body,
            'cookies' => $_COOKIE // Pass cookies for session
        ));
        
        if (is_wp_error($response)) {
            error_log('EXIF Harvester: Failed to spawn async weather request for post ' . $post_id . ': ' . $response->get_error_message());
            return false;
        } else {
            error_log('EXIF Harvester: Successfully spawned async weather request for post ' . $post_id);
            return true;
        }
    }

    /**
     * AJAX handler for async weather processing (fallback method)
     */
    public function ajax_async_weather_processing() {
        $post_id = intval($_POST['post_id']);
        $nonce = sanitize_text_field($_POST['nonce']);
        
        // Verify nonce
        if (!wp_verify_nonce($nonce, 'exif_harvester_async_weather_' . $post_id)) {
            error_log('EXIF Harvester: Invalid nonce for async weather processing of post ' . $post_id);
            wp_die('Security check failed');
        }
        
        // Verify post exists
        if (!get_post($post_id)) {
            error_log('EXIF Harvester: Post ' . $post_id . ' not found for async weather processing');
            wp_die('Post not found');
        }
        
        // Process weather data
        error_log('EXIF Harvester: Starting AJAX async weather processing for post ' . $post_id);
        $this->process_weather_data($post_id, true);
        
        // Return success (though client won't wait for this)
        wp_die('success');
    }

    /**
     * Async callback for background weather processing
     */
    public function async_weather_processing_callback($post_id) {
        error_log('EXIF Harvester: Starting async weather processing for post ' . $post_id);
        
        // Reload settings in case they changed since scheduling
        $this->load_settings();
        
        // Process weather data in the background
        $this->process_weather_data($post_id, true);
        
        error_log('EXIF Harvester: Completed async weather processing for post ' . $post_id);
    }

    /**
     * Process weather data synchronously for manual operations with error return
     * 
     * @param int $post_id The post ID
     * @return string|null Error message if failed, null if successful
     */
    private function process_weather_data_sync($post_id) {
        // Get required data
        $gps_coords = get_post_meta($post_id, 'GPS', true);
        $unix_time = get_post_meta($post_id, 'unixTime', true);
        $datetime_original = get_post_meta($post_id, 'dateTimeOriginal', true);
        $gmt_offset = get_post_meta($post_id, 'gmtOffset', true);
        
        // Clear existing weather data first
        delete_post_meta($post_id, 'wXSummary');
        delete_post_meta($post_id, 'temperature');
        delete_post_meta($post_id, '_weather_last_attempt');
        delete_post_meta($post_id, '_weather_last_failure');
        delete_post_meta($post_id, '_weather_gps_used');
        delete_post_meta($post_id, '_weather_datetime_used');
        
        // Check for required data
        if (empty($gps_coords)) {
            return 'No GPS coordinates found';
        }
        
        if (empty($unix_time)) {
            return 'No timestamp found';
        }
        
        // Parse GPS coordinates
        $coords = explode(',', $gps_coords);
        if (count($coords) !== 2) {
            return 'Invalid GPS coordinate format';
        }
        
        $lat = (float) trim($coords[0]);
        $lon = (float) trim($coords[1]);
        
        if ($lat == 0 || $lon == 0) {
            return 'Invalid GPS coordinates (0,0)';
        }
        
        // Convert to GMT if we have offset
        $gmt_time = $unix_time;
        if ($gmt_offset !== null && $gmt_offset !== '') {
            $gmt_time = exif_harvester_convert_to_gmt($unix_time, $gmt_offset);
        }
        
        // Record attempt timestamp
        update_post_meta($post_id, '_weather_last_attempt', time());
        
        // Get weather data
        $weather_result = exif_harvester_get_weather(
            $lat, 
            $lon, 
            $gmt_time, 
            $post_id, 
            $this->settings['pirate_weather_api_key']
        );
        
        if ($weather_result) {
            // Success - record what GPS/datetime were used
            update_post_meta($post_id, '_weather_gps_used', $gps_coords);
            update_post_meta($post_id, '_weather_datetime_used', $datetime_original);
            update_post_meta($post_id, '_weather_last_success', time());
            return null; // Success, no error
        } else {
            // Failure - record failure timestamp and return error
            update_post_meta($post_id, '_weather_last_failure', time());
            return 'Failed to retrieve weather data from API';
        }
    }

    /**
     * Process weather data for a post
     * 
     * @param int $post_id The post ID
     * @param bool $force_immediate Whether to force immediate processing (bypass async scheduling)
     */
    private function process_weather_data($post_id, $force_immediate = false) {
        // Allow weather processing - we've cleared existing data above
        error_log('EXIF Harvester: Starting fresh weather processing for post ' . $post_id);
        // ALWAYS clear existing weather data and fetch fresh data on save
        error_log('EXIF Harvester: Clearing existing weather data for post ' . $post_id . ' to fetch fresh data');
        delete_post_meta($post_id, 'wXSummary');
        delete_post_meta($post_id, 'temperature');
        delete_post_meta($post_id, '_weather_last_attempt');
        delete_post_meta($post_id, '_weather_last_failure');
        delete_post_meta($post_id, '_weather_gps_used');
        delete_post_meta($post_id, '_weather_datetime_used');
        
        error_log('EXIF Harvester: Weather data missing for post ' . $post_id . ' (wX: ' . ($wx_exists ? 'exists' : 'missing') . ', temp: ' . ($temp_exists ? 'exists' : 'missing') . ')');
        
        // Check if we've recently failed to get weather data (within last hour)
        $last_weather_attempt = get_post_meta($post_id, '_weather_last_attempt', true);
        $last_weather_failure = get_post_meta($post_id, '_weather_last_failure', true);
        
        if ($last_weather_failure && (time() - $last_weather_failure) < 3600) {
            error_log('EXIF Harvester: Skipping weather retry for post ' . $post_id . ' (last failure: ' . $last_weather_failure . ', cooldown remaining: ' . (3600 - (time() - $last_weather_failure)) . ' seconds)');
            return;
        }
        
        // Get required data for weather lookup
        $gps_coords = get_post_meta($post_id, 'GPS', true);
        $unix_time = get_post_meta($post_id, 'unixTime', true);
        $gmt_offset = get_post_meta($post_id, 'gmtOffset', true);
        
        error_log('EXIF Harvester: Weather data available for post ' . $post_id . ' - GPS: "' . $gps_coords . '", unixTime: "' . $unix_time . '", gmtOffset: "' . $gmt_offset . '"');
        
        if (empty($gps_coords) || empty($unix_time)) {
            error_log('EXIF Harvester: Cannot process weather for post ' . $post_id . ' - missing required data:');
            error_log('  GPS coordinates: ' . ($gps_coords ? '"' . $gps_coords . '"' : 'MISSING'));
            error_log('  Unix timestamp: ' . ($unix_time ? $unix_time . ' (' . date('Y-m-d H:i:s', $unix_time) . ')' : 'MISSING'));
            error_log('  DateTime original: ' . ($datetime_original ? '"' . $datetime_original . '"' : 'MISSING'));
            return; // Missing required data
        }
        
        // Parse GPS coordinates
        $coords = explode(',', $gps_coords);
        if (count($coords) !== 2) {
            return; // Invalid GPS format
        }
        
        $lat = (float) trim($coords[0]);
        $lon = (float) trim($coords[1]);
        
        if ($lat == 0 || $lon == 0) {
            return; // Invalid coordinates
        }
        
        // Convert to GMT if we have offset
        $gmt_time = $unix_time;
        if ($gmt_offset !== null && $gmt_offset !== '') {
            $gmt_time = exif_harvester_convert_to_gmt($unix_time, $gmt_offset);
            error_log('EXIF Harvester: Time conversion for post ' . $post_id . ' - Local: ' . $unix_time . ' (' . date('Y-m-d H:i:s T', $unix_time) . '), GMT Offset: ' . $gmt_offset . 'h, UTC: ' . $gmt_time . ' (' . date('Y-m-d H:i:s T', $gmt_time) . ')');
        } else {
            error_log('EXIF Harvester: No GMT offset available for post ' . $post_id . ', using local time: ' . $unix_time . ' (' . date('Y-m-d H:i:s T', $unix_time) . ')');
        }
        
        // Record attempt timestamp
        update_post_meta($post_id, '_weather_last_attempt', time());
        
        // Always log weather processing attempts
        error_log('EXIF Harvester: Attempting weather lookup for post ' . $post_id . ' (coordinates: ' . $lat . ',' . $lon . ', final_time: ' . $gmt_time . ', api_key_length: ' . strlen($this->settings['pirate_weather_api_key']) . ')');
        
        // Get weather data
        $weather_result = exif_harvester_get_weather(
            $lat, 
            $lon, 
            $gmt_time, 
            $post_id, 
            $this->settings['pirate_weather_api_key']
        );
        
        if ($weather_result) {
            // Success - record what GPS/datetime were used for future reference
            update_post_meta($post_id, '_weather_gps_used', $gps_coords);
            update_post_meta($post_id, '_weather_datetime_used', $datetime_original);
            update_post_meta($post_id, '_weather_last_success', time());
            
            // Always log success
            error_log('EXIF Harvester: Weather data retrieved successfully for post ' . $post_id . ': ' . $weather_result);
        } else {
            // Failure - record failure timestamp
            update_post_meta($post_id, '_weather_last_failure', time());
            
            // Always log failure
            error_log('EXIF Harvester: Failed to retrieve weather data for post ' . $post_id);
        }
    }
    
    /**
     * Force retry weather data for a post (clears failure timestamps)
     */
    private function force_retry_weather_data($post_id) {
        delete_post_meta($post_id, '_weather_last_attempt');
        delete_post_meta($post_id, '_weather_last_failure');
        delete_post_meta($post_id, '_weather_last_success');
        delete_post_meta($post_id, '_weather_gps_used');
        delete_post_meta($post_id, '_weather_datetime_used');
        delete_post_meta($post_id, 'wXSummary');
        delete_post_meta($post_id, 'temperature');
        
        // Now process weather data immediately (this is usually called from manual actions)
        $this->process_weather_data($post_id, true);
    }
    
    /**
     * Process location data from IPTC metadata and assign place terms
     */
    private function process_location_data($post_id, $fullsize_path) {
        // Don't process if post status is trash
        $post = get_post($post_id);
        if ($post && $post->post_status === 'trash') {
            return;
        }
        
        // Extract location information from IPTC metadata
        $location = exif_harvester_get_location($fullsize_path);
        $city = exif_harvester_get_city($fullsize_path);
        $state = exif_harvester_get_state($fullsize_path);
        $country = exif_harvester_get_country($fullsize_path);
        
        // Add location data as post metadata
        if (!empty($location)) {
            $this->add_meta_if_not_exists($post_id, 'location', $location);
        }
        
        if (!empty($city)) {
            $this->add_meta_if_not_exists($post_id, 'city', $city);
        }
        
        if (!empty($state)) {
            $this->add_meta_if_not_exists($post_id, 'state', $state);
        }
        
        if (!empty($country)) {
            $this->add_meta_if_not_exists($post_id, 'country', $country);
        }
        
        // Assign hierarchical place terms if we have location data
        if (!empty($location) || !empty($city) || !empty($state) || !empty($country)) {
            // Check if the place taxonomy exists before trying to assign terms
            if (taxonomy_exists('place')) {
                try {
                    exif_harvester_assign_place_terms($post_id, $location, $city, $state, $country);
                } catch (Exception $e) {
                    error_log('EXIF Harvester: Error assigning place terms for post ' . $post_id . ': ' . $e->getMessage());
                }
            } else {
                error_log('EXIF Harvester: Place taxonomy not found when processing post ' . $post_id);
            }
        }
    }
    
    /**
     * Find original attachment ID from a thumbnail URL
     */
    private function find_original_attachment_from_thumbnail($thumbnail_url) {
        // Extract the base filename by removing size suffixes like -300x200
        $base_url = preg_replace('/-\d+x\d+(?=\.[a-zA-Z]{3,4}$)/', '', $thumbnail_url);
        
        if (is_user_logged_in() && current_user_can('manage_options')) {
            error_log('EXIF Harvester: Trying to find original attachment for: ' . $thumbnail_url . ' -> ' . $base_url);
        }
        
        // Try to get attachment ID from the base URL
        $attachment_id = attachment_url_to_postid($base_url);
        
        if ($attachment_id > 0) {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Found original attachment ID: ' . $attachment_id);
            }
            return $attachment_id;
        }
        
        // Fallback: search the database for attachments with similar filenames
        global $wpdb;
        $filename = basename(parse_url($base_url, PHP_URL_PATH));
        $query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
            '%' . $wpdb->esc_like($filename)
        );
        $result = $wpdb->get_var($query);
        
        if ($result) {
            if (is_user_logged_in() && current_user_can('manage_options')) {
                error_log('EXIF Harvester: Found attachment via database search: ' . $result);
            }
            return intval($result);
        }
        
        if (is_user_logged_in() && current_user_can('manage_options')) {
            error_log('EXIF Harvester: Could not find original attachment for thumbnail');
        }
        
        return 0;
    }
    
    /**
     * Add meta if it doesn't already exist
     */
    private function add_meta_if_not_exists($post_id, $key, $value) {
        if (!metadata_exists('post', $post_id, $key)) {
            add_post_meta($post_id, $key, $value);
        }
    }
    
    /**
     * Sync place taxonomy data to location metadata fields
     * Triggered when place terms are assigned to a post
     */
    public function sync_place_taxonomy_to_metadata($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        // Only process place taxonomy changes for posts
        if ($taxonomy !== 'place' || get_post_type($object_id) !== 'post') {
            return;
        }
        
        // Skip if no terms assigned
        if (empty($terms)) {
            // Clear location metadata if no place terms (but only if not from EXIF)
            // Don't overwrite EXIF-derived location data
            return;
        }
        
        // Get the first place term (assuming one place per post)
        $place_terms = wp_get_post_terms($object_id, 'place', array('fields' => 'names'));
        if (empty($place_terms)) {
            return;
        }
        
        $place_name = $place_terms[0];
        
        // Parse the place name (format: "Location, City, State, Country")
        $parts = array_map('trim', explode(',', $place_name));
        
        // Extract components with fallbacks
        $location = isset($parts[0]) && !empty($parts[0]) ? $parts[0] : '';
        $city = isset($parts[1]) && !empty($parts[1]) ? $parts[1] : '';
        $state = isset($parts[2]) && !empty($parts[2]) ? $parts[2] : '';
        $country = isset($parts[3]) && !empty($parts[3]) ? $parts[3] : '';
        
        // Update metadata fields (only if they don't already exist from EXIF data)
        if (!get_post_meta($object_id, 'location', true) && $location) {
            update_post_meta($object_id, 'location', $location);
        }
        if (!get_post_meta($object_id, 'city', true) && $city) {
            update_post_meta($object_id, 'city', $city);
        }
        if (!get_post_meta($object_id, 'state', true) && $state) {
            update_post_meta($object_id, 'state', $state);
        }
        if (!get_post_meta($object_id, 'country', true) && $country) {
            update_post_meta($object_id, 'country', $country);
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EXIF Harvester: Synced place taxonomy to metadata for post $object_id - Location: $location, City: $city, State: $state, Country: $country");
        }
        
        // Regenerate SEO description with new location data
        if (function_exists('exif_harvester_generate_seo_meta_description')) {
            $seo_description = exif_harvester_generate_seo_meta_description($object_id);
            if ($seo_description) {
                update_post_meta($object_id, 'seo_description', $seo_description);
            }
        }
    }
    
    /**
     * Register the place taxonomy for hierarchical location management
     */
    public function register_place_taxonomy() {
        $labels = array(
            'name'              => _x('Places', 'taxonomy general name', 'exif-harvester'),
            'singular_name'     => _x('Place', 'taxonomy singular name', 'exif-harvester'),
            'search_items'      => __('Search Places', 'exif-harvester'),
            'all_items'         => __('All Places', 'exif-harvester'),
            'parent_item'       => __('Parent Place', 'exif-harvester'),
            'parent_item_colon' => __('Parent Place:', 'exif-harvester'),
            'edit_item'         => __('Edit Place', 'exif-harvester'),
            'update_item'       => __('Update Place', 'exif-harvester'),
            'add_new_item'      => __('Add New Place', 'exif-harvester'),
            'new_item_name'     => __('New Place Name', 'exif-harvester'),
            'menu_name'         => __('Places', 'exif-harvester'),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'place'),
            'public'            => true,
            'show_in_rest'      => true, // Enable for Gutenberg
            'meta_box_cb'       => false, // Disable default meta box since we'll assign programmatically
        );

        register_taxonomy('place', array('post'), $args);
    }
}

// Initialize the plugin
EXIFHarvester::get_instance();