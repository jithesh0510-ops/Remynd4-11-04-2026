/**
 * Client-side validation and AJAX enhancements for upload forms.
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.coachReportingUploads = {
    attach: function (context, settings) {
      // Initialize form validation
      this.initializeFormValidation(context);
      this.initializeFileValidation(context);
      this.initializeProgressIndicators(context);
    },

    /**
     * Initialize form validation.
     */
    initializeFormValidation: function (context) {
      var self = this;
      
      // Add real-time validation for company/program selection
      once('form-validation', '.crs-card form', context).forEach(function (form) {
        var $form = $(form);
        
        // Company selection validation
        $form.find('select[name*="company"]').on('change', function () {
          var $companySelect = $(this);
          var $programSelect = $form.find('select[name*="program"]');
          
          if ($companySelect.val()) {
            $companySelect.removeClass('error');
            $companySelect.siblings('.error-message').remove();
          } else {
            self.showFieldError($companySelect, 'Please select a company');
          }
        });
        
        // Program selection validation
        $form.find('select[name*="program"]').on('change', function () {
          var $programSelect = $(this);
          
          if ($programSelect.val()) {
            $programSelect.removeClass('error');
            $programSelect.siblings('.error-message').remove();
          } else {
            self.showFieldError($programSelect, 'Please select a program');
          }
        });
        
        // Month selection validation
        $form.find('select[name="month"]').on('change', function () {
          var $monthSelect = $(this);
          
          if ($monthSelect.val()) {
            $monthSelect.removeClass('error');
            $monthSelect.siblings('.error-message').remove();
          } else {
            self.showFieldError($monthSelect, 'Please select a month');
          }
        });
      });
    },

    /**
     * Initialize file validation.
     */
    initializeFileValidation: function (context) {
      var self = this;
      
      once('file-validation', 'input[type="file"]', context).forEach(function (fileInput) {
        var $fileInput = $(fileInput);
        
        $fileInput.on('change', function () {
          var files = this.files;
          var maxSize = 25 * 1024 * 1024; // 25MB
          var allowedTypes = ['text/csv', 'application/csv'];
          
          if (files.length > 0) {
            var file = files[0];
            
            // Check file size
            if (file.size > maxSize) {
              self.showFieldError($fileInput, 'File size must be less than 25MB');
              this.value = '';
              return;
            }
            
            // Check file type
            if (allowedTypes.indexOf(file.type) === -1 && !file.name.toLowerCase().endsWith('.csv')) {
              self.showFieldError($fileInput, 'Please upload a CSV file');
              this.value = '';
              return;
            }
            
            // Clear any previous errors
            $fileInput.removeClass('error');
            $fileInput.siblings('.error-message').remove();
            
            // Show success message
            self.showFieldSuccess($fileInput, 'File selected: ' + file.name);
          }
        });
      });
    },

    /**
     * Initialize progress indicators.
     */
    initializeProgressIndicators: function (context) {
      var self = this;
      
      once('progress-indicators', '.crs-card form', context).forEach(function (form) {
        var $form = $(form);
        
        // Add loading state to submit buttons
        $form.on('submit', function (e) {
          var $submitBtn = $form.find('input[type="submit"], button[type="submit"]');
          
          if ($submitBtn.length) {
            $submitBtn.prop('disabled', true);
            $submitBtn.data('original-text', $submitBtn.val());
            $submitBtn.val('Processing...');
            
            // Add loading spinner
            $submitBtn.after('<span class="loading-spinner">⏳</span>');
          }
        });
        
        // Add progress indicator for AJAX requests
        $(document).ajaxStart(function () {
          $form.find('.ajax-progress').show();
        }).ajaxStop(function () {
          $form.find('.ajax-progress').hide();
        });
      });
    },

    /**
     * Show field error message.
     */
    showFieldError: function ($field, message) {
      $field.addClass('error');
      $field.siblings('.error-message').remove();
      $field.after('<div class="error-message" style="color: #dc2626; font-size: 12px; margin-top: 4px;">' + message + '</div>');
    },

    /**
     * Show field success message.
     */
    showFieldSuccess: function ($field, message) {
      $field.addClass('success');
      $field.siblings('.success-message').remove();
      $field.after('<div class="success-message" style="color: #059669; font-size: 12px; margin-top: 4px;">' + message + '</div>');
    }
  };

  /**
   * Custom AJAX command to show validation results.
   */
  Drupal.AjaxCommands.prototype.showValidationResults = function (ajax, response, status) {
    if (response.data && response.data.errors) {
      $.each(response.data.errors, function (field, message) {
        var $field = $('[name="' + field + '"]');
        if ($field.length) {
          Drupal.behaviors.coachReportingUploads.showFieldError($field, message);
        }
      });
    }
  };

  /**
   * Custom AJAX command to show success message.
   */
  Drupal.AjaxCommands.prototype.showSuccessMessage = function (ajax, response, status) {
    if (response.data && response.data.message) {
      var $message = $('<div class="messages messages--status">' + response.data.message + '</div>');
      $('.crs-uploads-grid').prepend($message);
      
      // Auto-hide after 5 seconds
      setTimeout(function () {
        $message.fadeOut();
      }, 5000);
    }
  };

})(jQuery, Drupal, drupalSettings);
