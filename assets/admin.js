/**
 * EXIF Harvester Admin JavaScript
 */
jQuery(document).ready(function($) {
    
    // Camera mappings functionality
    $('#add-camera-form').on('submit', function(e) {
        e.preventDefault();
        
        var rawName = $('#camera-raw-name').val();
        var prettyName = $('#camera-pretty-name').val();
        
        if (!rawName || !prettyName) {
            alert('Both raw name and pretty name are required.');
            return;
        }
        
        $.ajax({
            url: exifHarvester.ajax_url,
            type: 'POST',
            data: {
                action: 'exif_harvester_save_camera',
                nonce: exifHarvester.nonce,
                raw_name: rawName,
                pretty_name: prettyName
            },
            success: function(response) {
                if (response.success) {
                    // Add new row to table
                    var newRow = '<tr data-id="' + response.data.id + '">' +
                        '<td>' +
                            '<span class="camera-raw-name">' + escapeHtml(response.data.raw_name) + '</span>' +
                            '<input type="text" class="camera-raw-name-edit regular-text" value="' + escapeHtml(response.data.raw_name) + '" style="display: none;" />' +
                        '</td>' +
                        '<td>' +
                            '<span class="camera-pretty-name">' + escapeHtml(response.data.pretty_name) + '</span>' +
                            '<input type="text" class="camera-pretty-name-edit regular-text" value="' + escapeHtml(response.data.pretty_name) + '" style="display: none;" />' +
                        '</td>' +
                        '<td>' +
                            '<button type="button" class="button edit-camera">Edit</button>' +
                            '<button type="button" class="button save-camera" style="display: none;">Save</button>' +
                            '<button type="button" class="button cancel-edit-camera" style="display: none;">Cancel</button>' +
                            '<button type="button" class="button delete-camera" style="color: #a00;">Delete</button>' +
                        '</td>' +
                    '</tr>';
                    
                    // Remove "no mappings" row if it exists
                    $('.wp-list-table tbody tr').filter(function() {
                        return $(this).find('td').length === 1 && $(this).find('td').attr('colspan') === '3';
                    }).remove();
                    
                    $('.wp-list-table tbody').append(newRow);
                    
                    // Clear form
                    $('#camera-raw-name, #camera-pretty-name').val('');
                    
                    showNotice(exifHarvester.strings.success_saved, 'success');
                } else {
                    showNotice(response.data || exifHarvester.strings.error_occurred, 'error');
                }
            },
            error: function() {
                showNotice(exifHarvester.strings.error_occurred, 'error');
            }
        });
    });
    
    // Lens mappings functionality
    $('#add-lens-form').on('submit', function(e) {
        e.preventDefault();
        
        var rawName = $('#lens-raw-name').val();
        var prettyName = $('#lens-pretty-name').val();
        
        if (!rawName || !prettyName) {
            alert('Both raw name and pretty name are required.');
            return;
        }
        
        $.ajax({
            url: exifHarvester.ajax_url,
            type: 'POST',
            data: {
                action: 'exif_harvester_save_lens',
                nonce: exifHarvester.nonce,
                raw_name: rawName,
                pretty_name: prettyName
            },
            success: function(response) {
                if (response.success) {
                    // Add new row to table
                    var newRow = '<tr data-id="' + response.data.id + '">' +
                        '<td>' +
                            '<span class="lens-raw-name">' + escapeHtml(response.data.raw_name) + '</span>' +
                            '<input type="text" class="lens-raw-name-edit regular-text" value="' + escapeHtml(response.data.raw_name) + '" style="display: none;" />' +
                        '</td>' +
                        '<td>' +
                            '<span class="lens-pretty-name">' + escapeHtml(response.data.pretty_name) + '</span>' +
                            '<input type="text" class="lens-pretty-name-edit regular-text" value="' + escapeHtml(response.data.pretty_name) + '" style="display: none;" />' +
                        '</td>' +
                        '<td>' +
                            '<button type="button" class="button edit-lens">Edit</button>' +
                            '<button type="button" class="button save-lens" style="display: none;">Save</button>' +
                            '<button type="button" class="button cancel-edit-lens" style="display: none;">Cancel</button>' +
                            '<button type="button" class="button delete-lens" style="color: #a00;">Delete</button>' +
                        '</td>' +
                    '</tr>';
                    
                    // Remove "no mappings" row if it exists
                    $('.wp-list-table tbody tr').filter(function() {
                        return $(this).find('td').length === 1 && $(this).find('td').attr('colspan') === '3';
                    }).remove();
                    
                    $('.wp-list-table tbody').append(newRow);
                    
                    // Clear form
                    $('#lens-raw-name, #lens-pretty-name').val('');
                    
                    showNotice(exifHarvester.strings.success_saved, 'success');
                } else {
                    showNotice(response.data || exifHarvester.strings.error_occurred, 'error');
                }
            },
            error: function() {
                showNotice(exifHarvester.strings.error_occurred, 'error');
            }
        });
    });
    
    // Edit camera mapping
    $(document).on('click', '.edit-camera', function() {
        var row = $(this).closest('tr');
        row.find('.camera-raw-name, .camera-pretty-name').hide();
        row.find('.camera-raw-name-edit, .camera-pretty-name-edit').show();
        row.find('.edit-camera, .delete-camera').hide();
        row.find('.save-camera, .cancel-edit-camera').show();
    });
    
    // Cancel edit camera mapping
    $(document).on('click', '.cancel-edit-camera', function() {
        var row = $(this).closest('tr');
        
        // Reset values
        row.find('.camera-raw-name-edit').val(row.find('.camera-raw-name').text());
        row.find('.camera-pretty-name-edit').val(row.find('.camera-pretty-name').text());
        
        // Toggle visibility
        row.find('.camera-raw-name, .camera-pretty-name').show();
        row.find('.camera-raw-name-edit, .camera-pretty-name-edit').hide();
        row.find('.edit-camera, .delete-camera').show();
        row.find('.save-camera, .cancel-edit-camera').hide();
    });
    
    // Save camera mapping
    $(document).on('click', '.save-camera', function() {
        var row = $(this).closest('tr');
        var id = row.data('id');
        var rawName = row.find('.camera-raw-name-edit').val();
        var prettyName = row.find('.camera-pretty-name-edit').val();
        
        if (!rawName || !prettyName) {
            alert('Both raw name and pretty name are required.');
            return;
        }
        
        $.ajax({
            url: exifHarvester.ajax_url,
            type: 'POST',
            data: {
                action: 'exif_harvester_save_camera',
                nonce: exifHarvester.nonce,
                id: id,
                raw_name: rawName,
                pretty_name: prettyName
            },
            success: function(response) {
                if (response.success) {
                    // Update display values
                    row.find('.camera-raw-name').text(rawName);
                    row.find('.camera-pretty-name').text(prettyName);
                    
                    // Toggle visibility
                    row.find('.camera-raw-name, .camera-pretty-name').show();
                    row.find('.camera-raw-name-edit, .camera-pretty-name-edit').hide();
                    row.find('.edit-camera, .delete-camera').show();
                    row.find('.save-camera, .cancel-edit-camera').hide();
                    
                    showNotice(exifHarvester.strings.success_saved, 'success');
                } else {
                    showNotice(response.data || exifHarvester.strings.error_occurred, 'error');
                }
            },
            error: function() {
                showNotice(exifHarvester.strings.error_occurred, 'error');
            }
        });
    });
    
    // Delete camera mapping
    $(document).on('click', '.delete-camera', function() {
        if (!confirm(exifHarvester.strings.confirm_delete)) {
            return;
        }
        
        var row = $(this).closest('tr');
        var id = row.data('id');
        
        $.ajax({
            url: exifHarvester.ajax_url,
            type: 'POST',
            data: {
                action: 'exif_harvester_delete_camera',
                nonce: exifHarvester.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    row.remove();
                    showNotice(exifHarvester.strings.success_deleted, 'success');
                } else {
                    showNotice(response.data || exifHarvester.strings.error_occurred, 'error');
                }
            },
            error: function() {
                showNotice(exifHarvester.strings.error_occurred, 'error');
            }
        });
    });
    
    // Edit lens mapping
    $(document).on('click', '.edit-lens', function() {
        var row = $(this).closest('tr');
        row.find('.lens-raw-name, .lens-pretty-name').hide();
        row.find('.lens-raw-name-edit, .lens-pretty-name-edit').show();
        row.find('.edit-lens, .delete-lens').hide();
        row.find('.save-lens, .cancel-edit-lens').show();
    });
    
    // Cancel edit lens mapping
    $(document).on('click', '.cancel-edit-lens', function() {
        var row = $(this).closest('tr');
        
        // Reset values
        row.find('.lens-raw-name-edit').val(row.find('.lens-raw-name').text());
        row.find('.lens-pretty-name-edit').val(row.find('.lens-pretty-name').text());
        
        // Toggle visibility
        row.find('.lens-raw-name, .lens-pretty-name').show();
        row.find('.lens-raw-name-edit, .lens-pretty-name-edit').hide();
        row.find('.edit-lens, .delete-lens').show();
        row.find('.save-lens, .cancel-edit-lens').hide();
    });
    
    // Save lens mapping
    $(document).on('click', '.save-lens', function() {
        var row = $(this).closest('tr');
        var id = row.data('id');
        var rawName = row.find('.lens-raw-name-edit').val();
        var prettyName = row.find('.lens-pretty-name-edit').val();
        
        if (!rawName || !prettyName) {
            alert('Both raw name and pretty name are required.');
            return;
        }
        
        $.ajax({
            url: exifHarvester.ajax_url,
            type: 'POST',
            data: {
                action: 'exif_harvester_save_lens',
                nonce: exifHarvester.nonce,
                id: id,
                raw_name: rawName,
                pretty_name: prettyName
            },
            success: function(response) {
                if (response.success) {
                    // Update display values
                    row.find('.lens-raw-name').text(rawName);
                    row.find('.lens-pretty-name').text(prettyName);
                    
                    // Toggle visibility
                    row.find('.lens-raw-name, .lens-pretty-name').show();
                    row.find('.lens-raw-name-edit, .lens-pretty-name-edit').hide();
                    row.find('.edit-lens, .delete-lens').show();
                    row.find('.save-lens, .cancel-edit-lens').hide();
                    
                    showNotice(exifHarvester.strings.success_saved, 'success');
                } else {
                    showNotice(response.data || exifHarvester.strings.error_occurred, 'error');
                }
            },
            error: function() {
                showNotice(exifHarvester.strings.error_occurred, 'error');
            }
        });
    });
    
    // Delete lens mapping
    $(document).on('click', '.delete-lens', function() {
        if (!confirm(exifHarvester.strings.confirm_delete)) {
            return;
        }
        
        var row = $(this).closest('tr');
        var id = row.data('id');
        
        $.ajax({
            url: exifHarvester.ajax_url,
            type: 'POST',
            data: {
                action: 'exif_harvester_delete_lens',
                nonce: exifHarvester.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    row.remove();
                    showNotice(exifHarvester.strings.success_deleted, 'success');
                } else {
                    showNotice(response.data || exifHarvester.strings.error_occurred, 'error');
                }
            },
            error: function() {
                showNotice(exifHarvester.strings.error_occurred, 'error');
            }
        });
    });
    
    // Location corrections functionality
    $('#add-location-form').on('submit', function(e) {
        e.preventDefault();
        
        var truncatedName = $('#location-truncated-name').val();
        var fullName = $('#location-full-name').val();
        
        if (!truncatedName || !fullName) {
            alert('Both truncated name and full name are required.');
            return;
        }
        
        if (truncatedName.length > 32) {
            alert('Truncated location name cannot exceed 32 characters.');
            return;
        }
        
        // Debug logging
        console.log('EXIF Harvester Debug: exifHarvester object:', exifHarvester);
        console.log('EXIF Harvester Debug: ajax_url:', exifHarvester.ajax_url);
        console.log('EXIF Harvester Debug: nonce:', exifHarvester.nonce);
        console.log('EXIF Harvester Debug: truncatedName:', truncatedName);
        console.log('EXIF Harvester Debug: fullName:', fullName);
        
        $.ajax({
            url: exifHarvester.ajax_url,
            type: 'POST',
            data: {
                action: 'exif_harvester_save_location',
                nonce: exifHarvester.nonce,
                truncated_name: truncatedName,
                full_name: fullName
            },
            success: function(response) {
                if (response.success) {
                    // Add new row to table
                    var newRow = '<tr data-id="' + response.data.id + '">' +
                        '<td>' +
                            '<span class="location-truncated-name">' + escapeHtml(response.data.truncated_name) + '</span>' +
                            '<input type="text" class="location-truncated-name-edit regular-text" value="' + escapeHtml(response.data.truncated_name) + '" maxlength="32" style="display: none;" />' +
                        '</td>' +
                        '<td>' +
                            '<span class="location-full-name">' + escapeHtml(response.data.full_name) + '</span>' +
                            '<input type="text" class="location-full-name-edit regular-text" value="' + escapeHtml(response.data.full_name) + '" style="display: none;" />' +
                        '</td>' +
                        '<td>' +
                            '<button type="button" class="button edit-location">Edit</button>' +
                            '<button type="button" class="button save-location" style="display: none;">Save</button>' +
                            '<button type="button" class="button cancel-edit-location" style="display: none;">Cancel</button>' +
                            '<button type="button" class="button delete-location" style="color: #a00;">Delete</button>' +
                        '</td>' +
                    '</tr>';
                    
                    // Remove "no corrections" row if it exists
                    $('.wp-list-table tbody tr').filter(function() {
                        return $(this).find('td').length === 1 && $(this).find('td').attr('colspan') === '3';
                    }).remove();
                    
                    $('.wp-list-table tbody').append(newRow);
                    
                    // Clear form
                    $('#location-truncated-name').val('');
                    $('#location-full-name').val('');
                    
                    showNotice('Location correction added successfully!', 'success');
                } else {
                    showNotice(response.data || 'An error occurred while adding the location correction.', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while processing the request.', 'error');
            }
        });
    });
    

    
    // Edit location correction
    $(document).on('click', '.edit-location', function() {
        var row = $(this).closest('tr');
        
        row.find('.location-truncated-name, .location-full-name').hide();
        row.find('.location-truncated-name-edit, .location-full-name-edit').show();
        
        $(this).hide();
        row.find('.save-location, .cancel-edit-location').show();
        row.find('.delete-location').hide();
    });
    
    // Cancel edit location correction
    $(document).on('click', '.cancel-edit-location', function() {
        var row = $(this).closest('tr');
        
        // Reset values
        var originalTruncated = row.find('.location-truncated-name').text();
        var originalFull = row.find('.location-full-name').text();
        row.find('.location-truncated-name-edit').val(originalTruncated);
        row.find('.location-full-name-edit').val(originalFull);
        
        row.find('.location-truncated-name, .location-full-name').show();
        row.find('.location-truncated-name-edit, .location-full-name-edit').hide();
        
        row.find('.edit-location, .delete-location').show();
        $(this).hide();
        row.find('.save-location').hide();
    });
    
    // Save location correction
    $(document).on('click', '.save-location', function() {
        var row = $(this).closest('tr');
        var id = row.data('id');
        var truncatedName = row.find('.location-truncated-name-edit').val();
        var fullName = row.find('.location-full-name-edit').val();
        
        if (!truncatedName || !fullName) {
            alert('Both truncated name and full name are required.');
            return;
        }
        
        if (truncatedName.length > 32) {
            alert('Truncated location name cannot exceed 32 characters.');
            return;
        }
        
        $.ajax({
            url: exifHarvester.ajax_url,
            type: 'POST',
            data: {
                action: 'exif_harvester_save_location',
                nonce: exifHarvester.nonce,
                id: id,
                truncated_name: truncatedName,
                full_name: fullName
            },
            success: function(response) {
                if (response.success) {
                    // Update displayed values
                    row.find('.location-truncated-name').text(truncatedName);
                    row.find('.location-full-name').text(fullName);
                    
                    row.find('.location-truncated-name, .location-full-name').show();
                    row.find('.location-truncated-name-edit, .location-full-name-edit').hide();
                    
                    row.find('.edit-location, .delete-location').show();
                    row.find('.save-location, .cancel-edit-location').hide();
                    
                    showNotice('Location correction updated successfully!', 'success');
                } else {
                    showNotice(response.data || 'An error occurred while updating the location correction.', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while processing the request.', 'error');
            }
        });
    });
    
    // Delete location correction
    $(document).on('click', '.delete-location', function() {
        if (!confirm('Are you sure you want to delete this location correction?')) {
            return;
        }
        
        var row = $(this).closest('tr');
        var id = row.data('id');
        
        $.ajax({
            url: exifHarvester.ajax_url,
            type: 'POST',
            data: {
                action: 'exif_harvester_delete_location',
                nonce: exifHarvester.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        row.remove();
                        
                        // If no more rows, show "no corrections" message
                        if ($('.wp-list-table tbody tr').length === 0) {
                            $('.wp-list-table tbody').html('<tr><td colspan="3">No location corrections found.</td></tr>');
                        }
                    });
                    
                    showNotice('Location correction deleted successfully!', 'success');
                } else {
                    showNotice(response.data || 'An error occurred while deleting the location correction.', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while processing the request.', 'error');
            }
        });
    });
    
    // Character counter for truncated location name
    $(document).on('input', '#location-truncated-name, .location-truncated-name-edit', function() {
        var maxLength = 32;
        var currentLength = $(this).val().length;
        var remaining = maxLength - currentLength;
        
        // Find or create counter element
        var counter = $(this).siblings('.char-counter');
        if (counter.length === 0) {
            counter = $('<span class="char-counter" style="font-size: 11px; color: #666;"></span>');
            $(this).after(counter);
        }
        
        // Update counter display
        if (remaining < 0) {
            counter.text('(' + Math.abs(remaining) + ' characters over limit)').css('color', '#d63638');
        } else {
            counter.text('(' + remaining + ' characters remaining)').css('color', '#666');
        }
        
        // Hide counter if field is empty
        if (currentLength === 0) {
            counter.hide();
        } else {
            counter.show();
        }
    });
    
    // Helper functions
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            notice.fadeOut(500, function() {
                notice.remove();
            });
        }, 3000);
    }
});