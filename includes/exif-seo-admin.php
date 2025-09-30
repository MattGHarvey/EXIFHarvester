<?php
/**
 * SEO Meta Descriptions Admin Interface for EXIF Harvester
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Meta Descriptions submenu to EXIF Harvester admin menu
 */
function exif_harvester_add_seo_admin_menu() {
    add_submenu_page(
        'exif-harvester', // Parent slug (EXIF Harvester main menu)
        'SEO Meta Descriptions', 
        'SEO Meta Descriptions', 
        'manage_options', 
        'exif-harvester-seo', 
        'exif_harvester_render_seo_admin_page'
    );
}

/**
 * Initialize SEO admin interface hooks
 */
function exif_harvester_init_seo_admin() {
    add_action('admin_menu', 'exif_harvester_add_seo_admin_menu', 11); // Priority 11 to ensure it comes after main menu
    add_action('wp_ajax_exif_harvester_generate_single_seo_description', 'exif_harvester_ajax_generate_single_seo_description');
}

/**
 * Render the SEO admin page
 */
function exif_harvester_render_seo_admin_page() {
    // Handle AJAX actions
    if (isset($_POST['ajax_action'])) {
        exif_harvester_handle_seo_ajax_admin_actions();
        return;
    }
    
    // Handle bulk actions
    if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'exif_harvester_seo_admin')) {
        $results = exif_harvester_handle_seo_bulk_actions();
        if ($results) {
            echo '<div class="notice notice-success"><p>' . $results . '</p></div>';
        }
    }
    
    // Handle blacklist management
    if (isset($_POST['blacklist_action']) && wp_verify_nonce($_POST['_wpnonce'], 'exif_harvester_seo_blacklist')) {
        $results = exif_harvester_handle_seo_blacklist_actions();
        if ($results) {
            echo '<div class="notice notice-success"><p>' . $results . '</p></div>';
        }
    }
    
    // Get current page and per page settings
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    
    // Get statistics
    $stats = exif_harvester_get_seo_statistics();
    ?>
    
    <div class="wrap">
        <h1>EXIF Harvester - SEO Meta Descriptions</h1>
        
        <!-- Statistics Dashboard -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0; color: #1d2327;">📊 Total Posts</h3>
                <div style="font-size: 24px; font-weight: 600; color: #2271b1;"><?php echo $stats['total_posts']; ?></div>
                <small>Photography posts with metadata</small>
            </div>
            <div class="stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0; color: #1d2327;">✅ With SEO Descriptions</h3>
                <div style="font-size: 24px; font-weight: 600; color: #00a32a;"><?php echo $stats['with_descriptions']; ?></div>
                <small>Posts with SEO meta descriptions</small>
            </div>
            <div class="stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0; color: #1d2327;">⚠️ Missing Descriptions</h3>
                <div style="font-size: 24px; font-weight: 600; color: #d63301;"><?php echo $stats['without_descriptions']; ?></div>
                <small>Posts that need descriptions</small>
            </div>
            <div class="stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0; color: #1d2327;">📈 Coverage</h3>
                <div style="font-size: 24px; font-weight: 600; color: #2271b1;">
                    <?php 
                    $coverage = $stats['total_posts'] > 0 ? round(($stats['with_descriptions'] / $stats['total_posts']) * 100) : 0;
                    echo $coverage . '%'; 
                    ?>
                </div>
                <small>SEO description coverage</small>
            </div>
        </div>
        
        <!-- Tag Blacklist Management -->
        <div class="blacklist-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
            <h2 style="margin: 0 0 15px 0;">🚫 Tag Blacklist Management</h2>
            <p>Tags in the blacklist will be excluded from SEO meta description generation to improve quality.</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                <div>
                    <h4 style="margin: 0 0 10px 0;">Default Blacklisted Terms</h4>
                    <div id="blacklist-display" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                        <?php
                        // Get default blacklist (without custom entries)
                        $default_blacklist = [
                            'exact' => [
                                'photo', 'picture', 'image', 'photography', 'photographer', 'photos', 'pictures', 'images',
                                'camera', 'lens', 'shot', 'shots', 'capture', 'captured', 'shooting',
                                'digital', 'raw', 'jpeg', 'jpg', 'editing', 'processed', 'postprocessing', 'lightroom', 'photoshop',
                                'beautiful', 'amazing', 'stunning', 'incredible', 'awesome', 'perfect', 'great', 'nice', 'cool',
                                'best', 'good', 'bad', 'new', 'old', 'big', 'small', 'large', 'tiny',
                                'instagram', 'facebook', 'twitter', 'hashtag', 'social', 'viral', 'trending',
                                'canon', 'nikon', 'sony', 'fujifilm', 'fuji', 'olympus', 'panasonic', 'leica', 'pentax'
                            ],
                            'patterns' => [
                                'iso', 'f/', 'mm', '/mm', 'f/1', 'f/2', 'f/4', 'f/8', 'f/11', 'f/16',
                                '24mm', '35mm', '50mm', '85mm', '70-200', '16-35', '24-70',
                                '1/', 'sec', 'shutter', 'aperture', 'exposure', 'ev+', 'ev-'
                            ]
                        ];
                        
                        echo '<strong>Default Exact Matches (' . count($default_blacklist['exact']) . '):</strong><br>';
                        echo '<small style="color: #666;">' . implode(', ', array_slice($default_blacklist['exact'], 0, 15));
                        if (count($default_blacklist['exact']) > 15) echo '... +' . (count($default_blacklist['exact']) - 15) . ' more';
                        echo '</small><br><br>';
                        
                        echo '<strong>Default Patterns (' . count($default_blacklist['patterns']) . '):</strong><br>';
                        echo '<small style="color: #666;">' . implode(', ', $default_blacklist['patterns']) . '</small>';
                        ?>
                    </div>
                </div>
                
                <div>
                    <h4 style="margin: 0 0 10px 0;">Custom Blacklist Management</h4>
                    
                    <!-- Add New Blacklist Entry -->
                    <form id="add-blacklist-form" method="post" style="margin-bottom: 20px;">
                        <?php wp_nonce_field('exif_harvester_seo_blacklist'); ?>
                        <input type="hidden" name="blacklist_action" value="add">
                        
                        <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
                            <input type="text" name="blacklist_tag" placeholder="Enter tag or pattern to blacklist" 
                                   style="flex: 1; min-width: 200px;" required>
                            
                            <select name="blacklist_type" style="width: 120px;">
                                <option value="exact">Exact Match</option>
                                <option value="pattern">Pattern</option>
                            </select>
                            
                            <button type="submit" class="button button-primary">Add to Blacklist</button>
                        </div>
                        
                        <small style="color: #666;">
                            <strong>Exact Match:</strong> Excludes tags with this exact name<br>
                            <strong>Pattern:</strong> Excludes tags containing this text (e.g., "f/" excludes "f/1.4", "f/2.8")
                        </small>
                    </form>
                    
                    <!-- Custom Blacklist Display -->
                    <div id="custom-blacklist">
                        <h5 style="margin: 15px 0 5px 0;">Your Custom Blacklist:</h5>
                        <?php
                        $custom_blacklist = get_option('exif_harvester_seo_custom_blacklist', ['exact' => [], 'patterns' => []]);
                        if (!empty($custom_blacklist['exact']) || !empty($custom_blacklist['patterns'])) {
                            echo '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; max-height: 150px; overflow-y: auto;">';
                            
                            if (!empty($custom_blacklist['exact'])) {
                                echo '<div style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Exact Matches:</strong></div>';
                                foreach ($custom_blacklist['exact'] as $index => $tag) {
                                    echo '<div style="padding: 4px 8px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f0;">';
                                    echo '<span>' . esc_html($tag) . '</span>';
                                    echo '<form method="post" style="margin: 0;">';
                                    wp_nonce_field('exif_harvester_seo_blacklist');
                                    echo '<input type="hidden" name="blacklist_action" value="remove">';
                                    echo '<input type="hidden" name="blacklist_type" value="exact">';
                                    echo '<input type="hidden" name="blacklist_index" value="' . $index . '">';
                                    echo '<button type="submit" class="button-link-delete" style="color: #d63638; text-decoration: none; padding: 2px 5px;">Remove</button>';
                                    echo '</form></div>';
                                }
                            }
                            
                            if (!empty($custom_blacklist['patterns'])) {
                                echo '<div style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Patterns:</strong></div>';
                                foreach ($custom_blacklist['patterns'] as $index => $pattern) {
                                    echo '<div style="padding: 4px 8px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f0;">';
                                    echo '<span>' . esc_html($pattern) . '</span>';
                                    echo '<form method="post" style="margin: 0;">';
                                    wp_nonce_field('exif_harvester_seo_blacklist');
                                    echo '<input type="hidden" name="blacklist_action" value="remove">';
                                    echo '<input type="hidden" name="blacklist_type" value="patterns">';
                                    echo '<input type="hidden" name="blacklist_index" value="' . $index . '">';
                                    echo '<button type="submit" class="button-link-delete" style="color: #d63638; text-decoration: none; padding: 2px 5px;">Remove</button>';
                                    echo '</form></div>';
                                }
                            }
                            echo '</div>';
                        } else {
                            echo '<p style="color: #666; font-style: italic;">No custom blacklist entries yet. Add some above!</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bulk Actions -->
        <div class="bulk-actions-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
            <h2 style="margin: 0 0 15px 0;">🚀 Bulk Actions</h2>
            <form method="post" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <?php wp_nonce_field('exif_harvester_seo_admin'); ?>
                <button type="submit" name="action" value="bulk_generate_missing" class="button button-primary">
                    Generate Missing SEO Descriptions
                </button>
                <button type="submit" name="action" value="bulk_regenerate_all" class="button button-secondary" 
                        onclick="return confirm('This will regenerate ALL SEO meta descriptions. Continue?')">
                    Regenerate All SEO Descriptions
                </button>
                <span style="color: #666; font-size: 13px;">
                    Generate SEO descriptions for posts that don't have them, or regenerate all existing ones.
                </span>
            </form>
        </div>
        
        <?php 
        // Get posts for the table
        $posts_data = exif_harvester_get_posts_with_seo_descriptions($current_page, $per_page);
        $posts = $posts_data['posts'];
        $total_posts = $posts_data['total'];
        $total_pages = ceil($total_posts / $per_page);
        ?>
        
        <!-- Posts Management Table -->
        <div class="posts-table-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <div style="padding: 20px; border-bottom: 1px solid #c3c4c7;">
                <h2 style="margin: 0;">📝 Posts & SEO Meta Descriptions</h2>
                <p style="margin: 10px 0 0 0; color: #666;">
                    Manage SEO meta descriptions for all your photography posts. Click "Generate" to create new descriptions or "Regenerate" to update existing ones.
                </p>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 300px;">Post Title</th>
                            <th>SEO Meta Description</th>
                            <th style="width: 120px;">Length</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="posts-table-body">
                        <?php foreach ($posts as $post): ?>
                        <tr data-post-id="<?php echo $post->ID; ?>">
                            <td>
                                <strong>
                                    <a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank">
                                        <?php echo esc_html($post->post_title); ?>
                                    </a>
                                </strong>
                                <div style="color: #666; font-size: 12px;">
                                    ID: <?php echo $post->ID; ?> | 
                                    <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">View</a>
                                </div>
                            </td>
                            <td>
                                <div class="seo-description-cell" style="max-width: 400px;">
                                    <?php if (!empty($post->seo_description)): ?>
                                        <div class="description-text" style="font-size: 13px; line-height: 1.4;">
                                            <?php echo esc_html($post->seo_description); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #d63301; font-style: italic;">No SEO description set</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="description-length" style="<?php 
                                    $length = strlen($post->seo_description ?? '');
                                    $color = $length == 0 ? '#d63301' : ($length <= 155 ? '#00a32a' : '#d63301');
                                    echo 'color: ' . $color . ';';
                                ?>">
                                    <?php echo $length; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($post->seo_description)): ?>
                                    <button class="button button-small regenerate-btn" data-post-id="<?php echo $post->ID; ?>">
                                        🔄 Regenerate
                                    </button>
                                <?php else: ?>
                                    <button class="button button-small button-primary generate-btn" data-post-id="<?php echo $post->ID; ?>">
                                        ✨ Generate
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($posts)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: #666;">
                                No photography posts found with metadata.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="padding: 20px; border-top: 1px solid #c3c4c7; text-align: center;">
                <?php
                $page_links = paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '« Previous',
                    'next_text' => 'Next »',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'type' => 'plain'
                ));
                echo $page_links;
                ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- JavaScript for SEO management -->
        <script>
        jQuery(document).ready(function($) {
            $('.generate-btn, .regenerate-btn').on('click', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var row = button.closest('tr');
                var isRegenerate = button.hasClass('regenerate-btn');
                
                button.prop('disabled', true).text(isRegenerate ? '🔄 Regenerating...' : '✨ Generating...');
                
                $.post(ajaxurl, {
                    action: 'exif_harvester_generate_single_seo_description',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('exif_harvester_seo_admin'); ?>'
                }, function(response) {
                    if (response.success && response.data.description) {
                        // Update the description cell
                        var descCell = row.find('.seo-description-cell');
                        descCell.html('<div class="description-text" style="font-size: 13px; line-height: 1.4;">' + 
                                     response.data.description + '</div>');
                        
                        // Update length
                        var length = response.data.description.length;
                        var color = length <= 155 ? '#00a32a' : '#d63301';
                        row.find('.description-length').text(length).css('color', color);
                        
                        // Update button
                        button.removeClass('button-primary generate-btn')
                              .addClass('regenerate-btn')
                              .text('🔄 Regenerate');
                        
                        // Show success feedback
                        var notification = $('<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 8px; border-radius: 3px; margin-top: 5px; font-size: 12px;">✓ SEO description generated successfully!</div>');
                        descCell.append(notification);
                        setTimeout(function() {
                            notification.fadeOut(function() { notification.remove(); });
                        }, 3000);
                        
                    } else {
                        alert('Error: ' + (response.data || 'Failed to generate SEO description'));
                        button.text(isRegenerate ? '🔄 Regenerate' : '✨ Generate');
                    }
                }).fail(function() {
                    alert('Network error. Please try again.');
                    button.text(isRegenerate ? '🔄 Regenerate' : '✨ Generate');
                }).always(function() {
                    button.prop('disabled', false);
                });
            });
        });
        </script>
    </div>
    
    <?php
}

/**
 * Get posts with their SEO descriptions for the admin table
 */
function exif_harvester_get_posts_with_seo_descriptions($page = 1, $per_page = 20) {
    global $wpdb;
    
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $total = $wpdb->get_var("
        SELECT COUNT(DISTINCT p.ID) 
        FROM {$wpdb->posts} p 
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        WHERE p.post_type = 'post' 
        AND p.post_status = 'publish' 
        AND pm.meta_key IN ('camera', 'location', 'city', 'state', 'country', 'GPSLat', 'dateTimeOriginal')
    ");
    
    // Get posts with pagination
    $posts = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT p.ID, p.post_title, 
               (SELECT pm2.meta_value FROM {$wpdb->postmeta} pm2 
                WHERE pm2.post_id = p.ID AND pm2.meta_key = 'seo_description' LIMIT 1) as seo_description
        FROM {$wpdb->posts} p 
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
        WHERE p.post_type = 'post' 
        AND p.post_status = 'publish' 
        AND pm.meta_key IN ('camera', 'location', 'city', 'state', 'country', 'GPSLat', 'dateTimeOriginal')
        ORDER BY p.post_date DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset));
    
    return array(
        'posts' => $posts,
        'total' => (int) $total
    );
}

/**
 * Handle blacklist management actions
 */
function exif_harvester_handle_seo_blacklist_actions() {
    if (!isset($_POST['blacklist_action'])) {
        return false;
    }
    
    $action = $_POST['blacklist_action'];
    $custom_blacklist = get_option('exif_harvester_seo_custom_blacklist', ['exact' => [], 'patterns' => []]);
    
    if ($action === 'add') {
        $tag = sanitize_text_field($_POST['blacklist_tag']);
        $type = sanitize_text_field($_POST['blacklist_type']);
        
        if (empty($tag)) {
            return "Error: Tag cannot be empty.";
        }
        
        $tag = strtolower(trim($tag));
        
        // Validate type
        if (!in_array($type, ['exact', 'patterns'])) {
            return "Error: Invalid blacklist type.";
        }
        
        // Check if already exists
        if (in_array($tag, $custom_blacklist[$type])) {
            return "Tag '{$tag}' is already in the {$type} blacklist.";
        }
        
        // Add to blacklist
        $custom_blacklist[$type][] = $tag;
        update_option('exif_harvester_seo_custom_blacklist', $custom_blacklist);
        
        return "Successfully added '{$tag}' to {$type} blacklist.";
        
    } elseif ($action === 'remove') {
        $type = sanitize_text_field($_POST['blacklist_type']);
        $index = intval($_POST['blacklist_index']);
        
        if (!in_array($type, ['exact', 'patterns'])) {
            return "Error: Invalid blacklist type.";
        }
        
        if (!isset($custom_blacklist[$type][$index])) {
            return "Error: Blacklist entry not found.";
        }
        
        $removed_tag = $custom_blacklist[$type][$index];
        unset($custom_blacklist[$type][$index]);
        
        // Re-index array
        $custom_blacklist[$type] = array_values($custom_blacklist[$type]);
        
        update_option('exif_harvester_seo_custom_blacklist', $custom_blacklist);
        
        return "Successfully removed '{$removed_tag}' from {$type} blacklist.";
    }
    
    return false;
}

/**
 * Handle bulk actions from the admin form
 */
function exif_harvester_handle_seo_bulk_actions() {
    if (!isset($_POST['action'])) {
        return false;
    }
    
    $action = $_POST['action'];
    
    if ($action === 'bulk_generate_missing') {
        $results = exif_harvester_bulk_generate_seo_descriptions(false); // Don't force regenerate
        return sprintf(
            'Generated SEO descriptions for %d posts. (Processed: %d, Skipped: %d)', 
            $results['generated'], 
            $results['processed'], 
            $results['skipped']
        );
    } elseif ($action === 'bulk_regenerate_all') {
        $results = exif_harvester_bulk_generate_seo_descriptions(true); // Force regenerate
        return sprintf(
            'Regenerated all SEO descriptions! (Processed: %d, Generated: %d)', 
            $results['processed'], 
            $results['generated']
        );
    }
    
    return false;
}

/**
 * AJAX handler for generating single SEO description
 */
function exif_harvester_ajax_generate_single_seo_description() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'exif_harvester_seo_admin')) {
        wp_die('Security check failed');
    }
    
    $post_id = intval($_POST['post_id']);
    
    if (!current_user_can('edit_post', $post_id)) {
        wp_die('Permission denied');
    }
    
    try {
        $new_description = exif_harvester_generate_seo_meta_description($post_id);
        
        if (!empty($new_description)) {
            // Save the description
            update_post_meta($post_id, 'seo_description', $new_description);
            
            wp_send_json_success(array(
                'description' => $new_description,
                'length' => strlen($new_description),
                'post_id' => $post_id
            ));
        } else {
            wp_send_json_error('Could not generate SEO description for this post');
        }
    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}

/**
 * Bulk generation function for SEO descriptions
 */
function exif_harvester_bulk_generate_seo_descriptions($force_regenerate = false) {
    global $wpdb;
    
    $processed = 0;
    $generated = 0;
    $skipped = 0;
    $errors = array();
    
    // Get posts to process based on whether we're forcing regeneration
    if ($force_regenerate) {
        // Get all photography posts
        $query = "
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish' 
            AND pm.meta_key IN ('camera', 'location', 'city', 'state', 'country', 'GPSLat', 'dateTimeOriginal')
        ";
    } else {
        // Get only posts without SEO descriptions
        $query = "
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'seo_description'
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish' 
            AND pm.meta_key IN ('camera', 'location', 'city', 'state', 'country', 'GPSLat', 'dateTimeOriginal')
            AND (pm2.meta_value IS NULL OR pm2.meta_value = '')
        ";
    }
    
    $post_ids = $wpdb->get_col($query);
    
    foreach ($post_ids as $post_id) {
        $processed++;
        
        try {
            $description = exif_harvester_generate_seo_meta_description($post_id);
            
            if (!empty($description)) {
                update_post_meta($post_id, 'seo_description', $description);
                $generated++;
            } else {
                $skipped++;
            }
        } catch (Exception $e) {
            $errors[] = "Post {$post_id}: " . $e->getMessage();
            $skipped++;
        }
    }
    
    return array(
        'processed' => $processed,
        'generated' => $generated,
        'skipped' => $skipped,
        'errors' => $errors
    );
}

/**
 * Get statistics about SEO descriptions
 */
function exif_harvester_get_seo_statistics() {
    global $wpdb;
    
    // Get total posts with photography metadata
    $total_posts = $wpdb->get_var("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_status = 'publish'
        AND p.post_type = 'post'
        AND pm.meta_key IN ('camera', 'location', 'city', 'state', 'country', 'GPSLat', 'dateTimeOriginal')
    ");
    
    // Get posts with SEO descriptions
    $posts_with_descriptions = $wpdb->get_var("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
        WHERE p.post_status = 'publish'
        AND p.post_type = 'post'
        AND pm.meta_key IN ('camera', 'location', 'city', 'state', 'country', 'GPSLat', 'dateTimeOriginal')
        AND pm2.meta_key = 'seo_description'
        AND pm2.meta_value != ''
        AND pm2.meta_value IS NOT NULL
    ");
    
    return array(
        'total_posts' => intval($total_posts),
        'with_descriptions' => intval($posts_with_descriptions),
        'without_descriptions' => intval($total_posts) - intval($posts_with_descriptions),
    );
}

// Initialize admin hooks when WordPress is loaded
if (function_exists('add_action') && is_admin()) {
    exif_harvester_init_seo_admin();
}