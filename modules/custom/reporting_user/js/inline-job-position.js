/**
 * @file
 * Inline job position assignment functionality for employee views.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Handle inline job position dropdown updates.
   */
  Drupal.behaviors.inlineJobPositionField = {
    attach: function (context, settings) {
      console.log('inlineJobPositionField behavior attached', context);
      
      var selects = once('inline-job-position', '.job-position-select', context);
      console.log('Found job-position-select elements:', selects.length);
      
      selects.forEach(function (element) {
        var $select = $(element);
        var employeeId = $select.data('id');
        var updateAction = $select.data('val');
        var isUpdating = false;
        var previousValue = $select.val() || '';
        var $wrapper = $select.closest('.inline-job-position-field-wrapper');
        
        // Variable to track pending saves.
        var changeTimeout;
        var hasChanges = false;
        
        console.log('Initializing inline job position field for employee:', employeeId);
        
        var formatJobOption = function(option) {
          if (!option.id) {
            return option.text;
          }
          var isSelected = option.selected || (option.element && option.element.selected);
          var $container = $(
            '<span class="select2-checkbox-option">' +
              '<span class="checkbox-icon" aria-hidden="true"></span>' +
              '<span class="checkbox-label"></span>' +
            '</span>'
          );
          if (isSelected) {
            $container.addClass('is-checked');
          }
          $container.find('.checkbox-label').text(option.text);
          return $container;
        };
        
        // Initialize Select2 if available.
        if (typeof $.fn.select2 !== 'undefined') {
          // Get default value from data attribute or current value.
          var defaultValue = $select.data('default-value') || $select.val() || '';
          
          $select.select2({
            width: '100%',
            placeholder: 'Select job position...',
            allowClear: true,
            theme: 'default',
            dropdownAutoWidth: false,
            minimumResultsForSearch: 0,
            templateResult: formatJobOption,
            templateSelection: function(option) {
              return option.text || option.id;
            },
            escapeMarkup: function(markup) {
              return markup;
            }
          });
          
          // Set the value after Select2 initialization to ensure it's selected.
          if (defaultValue) {
            $select.val(defaultValue).trigger('change');
          }
          
          console.log('Select2 initialized for job position field with default value:', defaultValue);
          
          // Handle Select2 specific events for better save triggering.
          $select.on('select2:select select2:unselect', function() {
            console.log('Select2 selection changed');
            hasChanges = true;
            
            // Clear any pending save.
            if (changeTimeout) {
              clearTimeout(changeTimeout);
              changeTimeout = null;
            }
            
            // Auto-save after a delay when changes are made.
            changeTimeout = setTimeout(function() {
              if (hasChanges) {
                console.log('Auto-saving after Select2 change...');
                saveJobPosition();
                hasChanges = false;
              }
            }, 500);
          });
          
          // Handle Select2 close event - save when dropdown closes.
          $select.on('select2:close', function() {
            console.log('Select2 dropdown closed');
            // Small delay to allow select2:select/unselect events to fire first.
            setTimeout(function() {
              if (hasChanges) {
                console.log('Saving on Select2 close...');
                if (changeTimeout) {
                  clearTimeout(changeTimeout);
                  changeTimeout = null;
                }
                saveJobPosition();
                hasChanges = false;
              }
            }, 200);
          });
        }

        // Function to save job position.
        var saveJobPosition = function() {
          console.log('saveJobPosition called');
          
          // Prevent multiple simultaneous updates.
          if (isUpdating) {
            console.log('Save already in progress, skipping...');
            return;
          }

          var selectedJobPosition = $select.val() || '';
          console.log('Selected job position:', selectedJobPosition);
          
          // Check if value actually changed.
          if (selectedJobPosition === previousValue) {
            console.log('No changes detected');
            hasChanges = false;
            return;
          }

          console.log('Starting save process...');
          isUpdating = true;
          
          // Show full page loader.
          console.log('Showing full page loader');
          Drupal.behaviors.inlineJobPositionField.showFullPageLoader();
          
          // Show loading state.
          $select.prop('disabled', true);
          $wrapper.addClass('loading');
          
          // Prepare data for AJAX request.
          var data = {
            employee_id: employeeId,
            job_position_id: selectedJobPosition
          };

          console.log('Saving job position:', data);

          // Get CSRF token for authenticated requests.
          var csrfToken = null;
          
          // Try to get token from drupalSettings first (most reliable).
          if (typeof drupalSettings !== 'undefined' && drupalSettings.user && drupalSettings.user.csrf_token) {
            csrfToken = drupalSettings.user.csrf_token;
          }
          
          // Fallback: try to get token from meta tag.
          if (!csrfToken) {
            var $tokenMeta = $('meta[name="csrf-token"]');
            if ($tokenMeta.length) {
              csrfToken = $tokenMeta.attr('content');
            }
          }

          // Make AJAX request.
          var ajaxOptions = {
            url: '/reporting-user/update-job-position',
            type: 'POST',
            data: data,
            dataType: 'json',
            beforeSend: function(xhr) {
              // Add CSRF token header if available.
              if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-Token', csrfToken);
              }
            },
            success: function (response) {
              if (response.success) {
                // Show success message.
                Drupal.behaviors.inlineJobPositionField.showMessage('Job position updated successfully.', 'status');
                
                // Update previous value.
                previousValue = selectedJobPosition;
                
                // Update the display if job position name is provided.
                if (response.job_position_name !== undefined) {
                  var $display = $wrapper.find('.job-position-display .current-job-position');
                  if (response.job_position_name) {
                    if ($display.length) {
                      $display.text(response.job_position_name);
                    } else {
                      $wrapper.append('<div class="job-position-display"><div class="current-job-position">' + 
                        response.job_position_name + '</div></div>');
                    }
                  } else {
                    $wrapper.find('.job-position-display').remove();
                  }
                }
              } else {
                Drupal.behaviors.inlineJobPositionField.showMessage(response.message || 'Failed to update job position.', 'error');
                // Revert selection on error
                $select.val(previousValue);
                if (typeof $.fn.select2 !== 'undefined' && $select.hasClass('select2-hidden-accessible')) {
                  $select.trigger('change');
                }
              }
            },
            error: function (xhr, status, error) {
              var errorMessage = 'An error occurred while updating job position.';
              if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
              }
              Drupal.behaviors.inlineJobPositionField.showMessage(errorMessage, 'error');
              // Revert selection on error
              $select.val(previousValue);
              if (typeof $.fn.select2 !== 'undefined' && $select.hasClass('select2-hidden-accessible')) {
                $select.trigger('change');
              }
            },
            complete: function () {
              // Hide full page loader.
              Drupal.behaviors.inlineJobPositionField.hideFullPageLoader();
              
              $select.prop('disabled', false);
              $wrapper.removeClass('loading');
              isUpdating = false;
            }
          };

          // If no CSRF token found, fetch it first, then make the request.
          if (!csrfToken) {
            $.get('/session/token').done(function(token) {
              csrfToken = token.trim();
              ajaxOptions.beforeSend = function(xhr) {
                xhr.setRequestHeader('X-CSRF-Token', csrfToken);
              };
              $.ajax(ajaxOptions);
            }).fail(function(xhr, status, error) {
              console.error('Failed to get CSRF token:', error);
              // Try without token (might fail, but let the server handle it).
              $.ajax(ajaxOptions);
            });
          } else {
            // Token found, make request immediately.
            $.ajax(ajaxOptions);
          }
        };
        
        // Handle change event - mark that changes were made.
        $select.on('change', function () {
          console.log('Job position selection changed');
          hasChanges = true;
          
          // Clear any pending save.
          if (changeTimeout) {
            clearTimeout(changeTimeout);
            changeTimeout = null;
          }
          
          // Auto-save after a delay when changes are made.
          changeTimeout = setTimeout(function() {
            if (hasChanges) {
              console.log('Auto-saving after change...');
              saveJobPosition();
              hasChanges = false;
            }
          }, 500);
        });
        
        // Handle blur event - save when dropdown loses focus.
        $select.on('blur', function () {
          console.log('Select box blurred');
          // Small delay to allow change event to fire first.
          changeTimeout = setTimeout(function() {
            if (hasChanges) {
              console.log('Saving on blur...');
              if (changeTimeout) {
                clearTimeout(changeTimeout);
                changeTimeout = null;
              }
              saveJobPosition();
              hasChanges = false;
            }
          }, 200);
        });
        
        // Also handle clicks outside the select box.
        $(document).on('click.inline-job-position-' + employeeId, function(e) {
          if (!$wrapper.is(e.target) && $wrapper.has(e.target).length === 0) {
            if (hasChanges) {
              console.log('Saving on outside click...');
              if (changeTimeout) {
                clearTimeout(changeTimeout);
                changeTimeout = null;
              }
              saveJobPosition();
              hasChanges = false;
            }
          }
        });
      });
    },

    /**
     * Show a message to the user.
     */
    showMessage: function (message, type) {
      type = type || 'status';
      var messageClass = type === 'error' ? 'messages--error' : 'messages--status';
      
      // Remove existing messages.
      $('#return_msg').remove();
      
      // Create message element.
      var $message = $('<div id="return_msg" class="messages ' + messageClass + '">' + 
        '<div class="messages__content">' + Drupal.checkPlain(message) + '</div>' +
        '</div>');
      
      // Insert at the top of the form or view.
      $('.box-body').prepend($message);
      
      // Auto-hide after 5 seconds.
      setTimeout(function () {
        $message.fadeOut(function () {
          $(this).remove();
        });
      }, 5000);
    },

    /**
     * Show full page loader overlay.
     */
    showFullPageLoader: function () {
      // Remove existing loader if any.
      $('#job-position-save-full-loader').remove();
      
      // Create full page loader overlay.
      var $loader = $('<div id="job-position-save-full-loader" class="job-position-save-full-loader">' +
        '<div class="job-position-save-loader-content">' +
        '<div class="job-position-save-spinner"></div>' +
        '<div class="job-position-save-text">Saving job position...</div>' +
        '</div>' +
        '</div>');
      
      // Append to body.
      $('body').append($loader);
      
      // Fade in.
      setTimeout(function() {
        $loader.addClass('active');
      }, 10);
    },

    /**
     * Hide full page loader overlay.
     */
    hideFullPageLoader: function () {
      var $loader = $('#job-position-save-full-loader');
      if ($loader.length) {
        $loader.removeClass('active');
        setTimeout(function() {
          $loader.remove();
        }, 300);
      }
    }
  };

  // Helper function for escaping HTML (if not available).
  if (typeof Drupal.checkPlain === 'undefined') {
    Drupal.checkPlain = function (str) {
      return $('<div>').text(str).html();
    };
  }

})(jQuery, Drupal);

