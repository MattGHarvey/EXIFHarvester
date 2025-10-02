<?php
/**
 * EXIF Data Overview Admin Interface for EXIF Harvester
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * EXIF Data Overview List Table
 */
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class EXIF_Data_Overview_List_Table extends WP_List_Table {
    
    private $exif_harvester;
    
    public function __construct($exif_harvester) {
        $this->exif_harvester = $exif_harvester;
        
        parent::__construct(array(
            'singular' => 'post',
            'plural' => 'posts',
            'ajax' => true
        ));
    }
    
    /**
     * Get list of columns
     */
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'title' => __('Post Title', 'exif-harvester'),
            'post_date' => __('Date', 'exif-harvester'),
            'camera' => __('Camera', 'exif-harvester'),
            'lens' => __('Lens', 'exif-harvester'),
            'gps' => __('GPS', 'exif-harvester'),
            'location' => __('Location', 'exif-harvester'),
            'weather' => __('Weather', 'exif-harvester'),
            'datetime_original' => __('Date Taken', 'exif-harvester'),
            'actions' => __('Actions', 'exif-harvester')
        );
        
        return $columns;
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'title' => array('title', false),
            'post_date' => array('post_date', true),
            'camera' => array('camera', false),
            'lens' => array('lens', false),
            'datetime_original' => array('datetime_original', false)
        );
    }
    
    /**
     * Prepare items for display
     */
    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        // Handle search
        $search = isset($_REQUEST['s']) ? trim($_REQUEST['s']) : '';
        
        // Handle sorting
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'post_date';
        $order = isset($_REQUEST['order']) && $_REQUEST['order'] === 'asc' ? 'ASC' : 'DESC';
        
        // Handle filtering
        $filter_missing = isset($_REQUEST['filter_missing']) ? $_REQUEST['filter_missing'] : '';
        
        // Build query
        $query = "
            SELECT DISTINCT p.ID, p.post_title, p.post_date, p.post_status,
                   camera.meta_value as camera,
                   lens.meta_value as lens,
                   gps.meta_value as gps,
                   location.meta_value as location,
                   weather.meta_value as weather,
                   datetime_original.meta_value as datetime_original
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} camera ON p.ID = camera.post_id AND camera.meta_key = 'camera'
            LEFT JOIN {$wpdb->postmeta} lens ON p.ID = lens.post_id AND lens.meta_key = 'lens'
            LEFT JOIN {$wpdb->postmeta} gps ON p.ID = gps.post_id AND gps.meta_key = 'GPS'
            LEFT JOIN {$wpdb->postmeta} location ON p.ID = location.post_id AND location.meta_key = 'location'
            LEFT JOIN {$wpdb->postmeta} weather ON p.ID = weather.post_id AND weather.meta_key = 'wXSummary'
            LEFT JOIN {$wpdb->postmeta} datetime_original ON p.ID = datetime_original.post_id AND datetime_original.meta_key = 'dateTimeOriginal'
            WHERE p.post_status IN ('publish', 'draft', 'private')
        ";
        
        // Add post type filter
        $enabled_post_types = $this->exif_harvester->get_settings()['enabled_post_types'];
        if (!empty($enabled_post_types)) {
            $placeholders = implode(',', array_fill(0, count($enabled_post_types), '%s'));
            $query .= $wpdb->prepare(" AND p.post_type IN ($placeholders)", $enabled_post_types);
        } else {
            // If no post types are enabled, show posts anyway (fallback to 'post' type)
            $query .= " AND p.post_type = 'post'";
        }
        
        // Add search
        if (!empty($search)) {
            $query .= $wpdb->prepare(" AND p.post_title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        }
        
        // Add filters for missing data
        if ($filter_missing === 'camera') {
            $query .= " AND (camera.meta_value IS NULL OR camera.meta_value = '')";
        } elseif ($filter_missing === 'lens') {
            $query .= " AND (lens.meta_value IS NULL OR lens.meta_value = '')";
        } elseif ($filter_missing === 'gps') {
            $query .= " AND (gps.meta_value IS NULL OR gps.meta_value = '')";
        } elseif ($filter_missing === 'weather') {
            $query .= " AND (weather.meta_value IS NULL OR weather.meta_value = '')";
        } elseif ($filter_missing === 'any') {
            $query .= " AND (
                (camera.meta_value IS NULL OR camera.meta_value = '') OR
                (lens.meta_value IS NULL OR lens.meta_value = '') OR
                (gps.meta_value IS NULL OR gps.meta_value = '') OR
                (weather.meta_value IS NULL OR weather.meta_value = '')
            )";
        }
        
        // Add sorting
        switch ($orderby) {
            case 'title':
                $query .= " ORDER BY p.post_title $order";
                break;
            case 'camera':
                $query .= " ORDER BY camera.meta_value $order, p.post_date DESC";
                break;
            case 'lens':
                $query .= " ORDER BY lens.meta_value $order, p.post_date DESC";
                break;
            case 'datetime_original':
                $query .= " ORDER BY datetime_original.meta_value $order, p.post_date DESC";
                break;
            case 'post_date':
            default:
                $query .= " ORDER BY p.post_date $order";
                break;
        }
        
        // Get total count for pagination
        $total_query = str_replace(
            'SELECT DISTINCT p.ID, p.post_title, p.post_date, p.post_status,
                   camera.meta_value as camera,
                   lens.meta_value as lens,
                   gps.meta_value as gps,
                   location.meta_value as location,
                   weather.meta_value as weather,
                   datetime_original.meta_value as datetime_original',
            'SELECT COUNT(DISTINCT p.ID)',
            $query
        );
        
        // Remove ORDER BY from count query
        $total_query = preg_replace('/ORDER BY.*$/', '', $total_query);
        
        $total_items = $wpdb->get_var($total_query);
        
        // Add pagination
        $offset = ($current_page - 1) * $per_page;
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);
        
        $this->items = $wpdb->get_results($query);
        
        // Log SQL errors if any occur
        if ($wpdb->last_error) {
            error_log('EXIF Overview SQL Error: ' . $wpdb->last_error);
        }
        
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        // Ensure column headers are properly set
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
    }
    
    /**
     * Column: Checkbox
     */
    protected function column_cb($item) {
        return '<input type="checkbox" name="post[]" value="' . esc_attr($item->ID) . '" />';
    }
    
    /**
     * Column: Title
     */
    protected function column_title($item) {
        $edit_url = get_edit_post_link($item->ID);
        $view_url = get_permalink($item->ID);
        
        $title = '<strong><a href="' . esc_url($edit_url) . '">' . esc_html($item->post_title ?: '(No title)') . '</a></strong>';
        
        $actions = array(
            'edit' => '<a href="' . esc_url($edit_url) . '">' . __('Edit') . '</a>',
            'view' => '<a href="' . esc_url($view_url) . '">' . __('View') . '</a>'
        );
        
        return $title . $this->row_actions($actions);
    }
    
    /**
     * Default column display method
     */
    protected function column_default($item, $column_name) {
        switch($column_name) {
            case 'post_date':
                return get_the_date('Y-m-d H:i', $item->ID);
            case 'camera':
                return $item->camera ? esc_html($item->camera) : '<span style="color: #999;">—</span>';
            case 'lens':
                return $item->lens ? esc_html($item->lens) : '<span style="color: #999;">—</span>';
            case 'gps':
                if ($item->gps) {
                    $gps_parts = explode(',', $item->gps);
                    if (count($gps_parts) === 2) {
                        $lat = trim($gps_parts[0]);
                        $lng = trim($gps_parts[1]);
                        return '<span title="' . esc_attr($item->gps) . '">' . 
                               number_format(floatval($lat), 4) . ', ' . 
                               number_format(floatval($lng), 4) . '</span>';
                    }
                    return esc_html($item->gps);
                }
                return '<span style="color: #999;">—</span>';
            case 'location':
                return $item->location ? esc_html($item->location) : '<span style="color: #999;">—</span>';
            case 'weather':
                return $item->weather ? esc_html($item->weather) : '<span style="color: #999;">—</span>';
            case 'datetime_original':
                return $item->datetime_original ? esc_html($item->datetime_original) : '<span style="color: #999;">—</span>';
            case 'actions':
                return '<button type="button" class="button refresh-exif-data" data-post-id="' . esc_attr($item->ID) . '">' . 
                       __('Refresh EXIF', 'exif-harvester') . '</button>';
            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
        }
    }
    
    /**
     * Get bulk actions
     */
    protected function get_bulk_actions() {
        return array(
            'refresh_exif' => __('Refresh EXIF Data', 'exif-harvester')
        );
    }
    
    /**
     * Message to display when no items are found
     */
    public function no_items() {
        $enabled_types = $this->exif_harvester->get_settings()['enabled_post_types'];
        if (empty($enabled_types)) {
            _e('No posts found. Make sure you have configured enabled post types in the EXIF Harvester settings.', 'exif-harvester');
        } else {
            printf(
                __('No posts found for the enabled post types: %s. Try adjusting your filters or check that you have published posts of these types.', 'exif-harvester'),
                implode(', ', $enabled_types)
            );
        }
    }
    
    /**
     * Extra table navigation
     */
    protected function extra_tablenav($which) {
        if ($which === 'top') {
            ?>
            <div class="alignleft actions">
                <select name="filter_missing">
                    <option value=""><?php _e('Show All Posts', 'exif-harvester'); ?></option>
                    <option value="camera" <?php selected(isset($_REQUEST['filter_missing']) && $_REQUEST['filter_missing'] === 'camera'); ?>><?php _e('Missing Camera Data', 'exif-harvester'); ?></option>
                    <option value="lens" <?php selected(isset($_REQUEST['filter_missing']) && $_REQUEST['filter_missing'] === 'lens'); ?>><?php _e('Missing Lens Data', 'exif-harvester'); ?></option>
                    <option value="gps" <?php selected(isset($_REQUEST['filter_missing']) && $_REQUEST['filter_missing'] === 'gps'); ?>><?php _e('Missing GPS Data', 'exif-harvester'); ?></option>
                    <option value="weather" <?php selected(isset($_REQUEST['filter_missing']) && $_REQUEST['filter_missing'] === 'weather'); ?>><?php _e('Missing Weather Data', 'exif-harvester'); ?></option>
                    <option value="any" <?php selected(isset($_REQUEST['filter_missing']) && $_REQUEST['filter_missing'] === 'any'); ?>><?php _e('Missing Any Data', 'exif-harvester'); ?></option>
                </select>
                <?php submit_button(__('Filter', 'exif-harvester'), 'secondary', 'filter_action', false, array('id' => 'post-query-submit')); ?>
            </div>
            <?php
        }
        
        if ($which === 'bottom') {
            ?>
            <div class="alignleft actions">
                <small style="color: #646970; font-style: italic;">
                    <?php _e('Tip: Use the filters above to quickly find posts missing specific EXIF data, then use bulk actions or individual refresh buttons to update.', 'exif-harvester'); ?>
                </small>
            </div>
            <?php
        }
    }
}