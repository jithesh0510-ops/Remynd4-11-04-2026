/**
 * @file
 * Inline coach assignment functionality for employee views.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Handle inline coach multi-select updates.
   */
  Drupal.behaviors.inlineCoachField = {
      attach: function (context, settings) {
        console.log('inlineCoachField behavior attached', context);
        
      var selects = once('inline-coach', '.coach-multiselect', context);
      console.log('Found coach-multiselect elements:', selects.length);
    
      selects.forEach(function (element) {
      var $select = $(element);
        var employeeId = $select.data('id');
        var updateAction = $select.data('val');
        var isUpdating = false;
        var previousValue = $select.val() || [];
        var $wrapper = $select.closest('.inline-coach-field-wrapper');
        
        // Variable to track pending saves.
        var changeTimeout;
        var hasChanges = false;
        var select2RetryCount = 0;
        var maxRetries = 50; // Maximum 5 seconds (50 * 100ms)
        
        console.log('Initializing inline coach field for employee:', employeeId);
        
        // Helper to format options with checkbox indicator.
        var formatCoachOption = function(option) {
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
        
        // Function to initialize Select2.
        var initializeSelect2 = function() {
          // Check if Select2 is available.
          if (typeof $.fn.select2 === 'undefined') {
            select2RetryCount++;
            if (select2RetryCount >= maxRetries) {
              console.error('Select2 failed to load after maximum retries. Please ensure select2 library is installed.');
              return;
            }
            console.warn('Select2 not available yet, retrying... (' + select2RetryCount + '/' + maxRetries + ')');
            // Retry after a short delay.
            setTimeout(initializeSelect2, 100);
            return;
          }
          
          // Get default values from data attribute or current value.
          var defaultValueData = $select.data('default-value');
          // Ensure defaultValueStr is always a string.
          var defaultValueStr = (defaultValueData !== undefined && defaultValueData !== null) ? String(defaultValueData) : '';
          var defaultValues = defaultValueStr ? defaultValueStr.split(',').filter(function(v) { return v.trim() !== ''; }) : ($select.val() || []);
          
          console.log('Initializing Select2 for coach field with default values:', defaultValues);
          
          // Check if already initialized.
          if ($select.hasClass('select2-hidden-accessible')) {
            console.log('Select2 already initialized, destroying first...');
            $select.select2('destroy');
          }
          
          $select.select2({
            width: '100%',
            placeholder: 'Select coaches...',
            allowClear: true,
            closeOnSelect: false,
            theme: 'default',
            dropdownAutoWidth: false,
            minimumResultsForSearch: 0,
            templateResult: formatCoachOption,
            templateSelection: function(option) {
              return option.text || option.id;
            },
            escapeMarkup: function(markup) {
              return markup;
            }
          });
          
          // Set the values after Select2 initialization to ensure they're selected.
          if (defaultValues.length > 0) {
            $select.val(defaultValues).trigger('change');
            previousValue = defaultValues.slice(); // Clone array
          }
          
          console.log('Select2 initialized successfully for coach field');
          
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
                saveCoachAssignment();
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
                saveCoachAssignment();
                hasChanges = false;
              }
            }, 200);
          });
        };
        
        // Initialize Select2 (will retry if not available).
        initializeSelect2();

        // Function to save coach assignment.
        var saveCoachAssignment = function() {
          console.log('saveCoachAssignment called');
          
          // Prevent multiple simultaneous updates.
          if (isUpdating) {
            console.log('Save already in progress, skipping...');
            return;
          }

          var selectedCoaches = $select.val() || [];
          console.log('Selected coaches:', selectedCoaches);
          
          // Check if value actually changed.
          var currentValue = selectedCoaches.sort().join(',');
          var prevValue = previousValue.sort().join(',');
          console.log('Current value:', currentValue, 'Previous value:', prevValue);
          
          if (currentValue === prevValue) {
            console.log('No changes detected');
            hasChanges = false;
            return;
          }

          console.log('Starting save process...');
          isUpdating = true;
          
          // Show full page loader.
          console.log('Showing full page loader');
          Drupal.behaviors.inlineCoachField.showFullPageLoader();
          
          // Show loading state.
          $select.prop('disabled', true);
          $wrapper.addClass('loading');
          
          // Prepare data for AJAX request.
          var data = {
            employee_id: employeeId,
            coach_ids: selectedCoaches
          };

          console.log('Saving coach assignment:', data);

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
          
          // Last resort: try to get from session/token endpoint (async).
          // We'll handle this in the AJAX call itself.

          // Make AJAX request.
          var ajaxOptions = {
            url: '/reporting-user/update-coach',
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
                Drupal.behaviors.inlineCoachField.showMessage('Coach assignment updated successfully.', 'status');
                
                // Update previous value.
                previousValue = selectedCoaches;
                
                // Update the display if coach names are provided.
                if (response.coach_names !== undefined) {
                  var $display = $wrapper.find('.coach-display .current-coaches');
                  if (response.coach_names.length > 0) {
                    if ($display.length) {
                      $display.text(response.coach_names.join(', '));
                    } else {
                      $wrapper.append('<div class="coach-display"><div class="current-coaches">' + 
                        response.coach_names.join(', ') + '</div></div>');
                    }
                  } else {
                    $wrapper.find('.coach-display').remove();
                  }
                }
              } else {
                Drupal.behaviors.inlineCoachField.showMessage(response.message || 'Failed to update coach assignment.', 'error');
                // Revert selection on error
                $select.val(previousValue);
                if (typeof $.fn.select2 !== 'undefined' && $select.hasClass('select2-hidden-accessible')) {
                  $select.trigger('change');
                }
              }
            },
            error: function (xhr, status, error) {
              var errorMessage = 'An error occurred while updating coach assignment.';
              if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
              }
              Drupal.behaviors.inlineCoachField.showMessage(errorMessage, 'error');
              // Revert selection on error
              $select.val(previousValue);
              if (typeof $.fn.select2 !== 'undefined' && $select.hasClass('select2-hidden-accessible')) {
                $select.trigger('change');
              }
            },
            complete: function () {
              // Hide full page loader.
              Drupal.behaviors.inlineCoachField.hideFullPageLoader();
              
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
          console.log('Coach selection changed');
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
              saveCoachAssignment();
              hasChanges = false;
            }
          }, 500);
        });
        
        // Handle blur event - save when dropdown loses focus.
        $select.on('blur', function () {
          console.log('Select box blurred');
          // Small delay to allow change event to fire first.
          setTimeout(function() {
            if (hasChanges) {
              console.log('Saving on blur...');
              if (changeTimeout) {
                clearTimeout(changeTimeout);
                changeTimeout = null;
              }
              saveCoachAssignment();
              hasChanges = false;
            }
          }, 100);
        });
        
        // Also handle clicks outside the select box.
        $(document).on('click.inline-coach-' + employeeId, function(e) {
          if (!$wrapper.is(e.target) && $wrapper.has(e.target).length === 0) {
            if (hasChanges) {
              console.log('Saving on outside click...');
              if (changeTimeout) {
                clearTimeout(changeTimeout);
                changeTimeout = null;
              }
              saveCoachAssignment();
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
      $('#coach-save-full-loader').remove();
      
      // Create full page loader overlay.
      var $loader = $('<div id="coach-save-full-loader" class="coach-save-full-loader">' +
        '<div class="coach-save-loader-content">' +
        '<div class="coach-save-spinner"></div>' +
        '<div class="coach-save-text">Saving coach assignment...</div>' +
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
      var $loader = $('#coach-save-full-loader');
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

