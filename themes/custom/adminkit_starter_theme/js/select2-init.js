/**
 * @file
 * Select2 initialization and enhancement for AdminKit theme.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';
  
  // Import once from Drupal core
  const once = Drupal.once || window.once;

  /**
   * Initialize Select2 widgets with enhanced styling.
   */
  Drupal.behaviors.adminKitSelect2 = {
    attach: function (context, settings) {
      // Initialize Select2 on all select elements
      once('adminKitSelect2', 'select', context).forEach(function (element) {
        var $select = $(element);
        
        // Skip if already initialized
        if ($select.hasClass('select2-hidden-accessible')) {
          return;
        }

        // Configure Select2 options
        var select2Options = {
          theme: 'default',
          width: '100%',
          placeholder: $select.attr('placeholder') || 'Select an option...',
          allowClear: true,
          closeOnSelect: !$select.prop('multiple'),
          dropdownParent: $select.closest('.form-item, .field-group, .fieldset'),
          language: {
            noResults: function() {
              return "No results found";
            },
            searching: function() {
              return "Searching...";
            },
            loadingMore: function() {
              return "Loading more results...";
            }
          }
        };

        // Add specific options for multiple selects
        if ($select.prop('multiple')) {
          select2Options.tags = false;
          select2Options.tokenSeparators = [',', ' '];
        }

        // Add specific options for entity reference fields
        if ($select.hasClass('entity-reference')) {
          select2Options.minimumInputLength = 0;
          select2Options.ajax = {
            url: '/entity-reference-autocomplete',
            dataType: 'json',
            delay: 250,
            data: function (params) {
              return {
                q: params.term,
                field_name: $select.data('field-name') || '',
                entity_type: $select.data('entity-type') || ''
              };
            },
            processResults: function (data) {
              return {
                results: data
              };
            }
          };
        }

        // Initialize Select2
        $select.select2(select2Options);

        // Add custom classes for styling
        $select.on('select2:open', function () {
          $('.select2-dropdown').addClass('adminKit-select2-dropdown');
        });

        // Handle focus states
        $select.on('select2:open select2:close', function () {
          $(this).closest('.form-item').toggleClass('select2-focused');
        });

        // Handle validation states
        $select.on('change', function () {
          var $formItem = $(this).closest('.form-item');
          $formItem.removeClass('error success');
          
          if ($(this).val()) {
            $formItem.addClass('success');
          }
        });
      });

      // Enhanced styling for existing Select2 instances
      once('adminKitSelect2Style', '.select2-container', context).forEach(function (element) {
        var $container = $(element);
        
        // Add theme-specific classes
        $container.addClass('adminKit-select2-container');
        
        // Handle responsive behavior
        $(window).on('resize', function () {
          $container.select2('destroy');
          $container.select2({
            width: '100%',
            dropdownParent: $container.closest('.form-item, .field-group, .fieldset')
          });
        });
      });
    }
  };

  /**
   * Handle Select2 in forms with conditional fields.
   */
  Drupal.behaviors.adminKitSelect2Conditional = {
    attach: function (context, settings) {
      // Reinitialize Select2 when conditional fields change
      $(document).on('conditionalFieldsUpdated', function () {
        $('select', context).each(function () {
          var $select = $(this);
          if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
            $select.select2({
              width: '100%',
              theme: 'default'
            });
          }
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);

