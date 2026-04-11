/**
 * @file
 * Enhanced form functionality for professional forms.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Professional form enhancements.
   */
  Drupal.behaviors.professionalForms = {
    attach: function (context, settings) {
      // Initialize form enhancements
      this.initializeFormValidation(context);
      this.initializeFormStyling(context);
      this.initializeFormAccessibility(context);
      this.initializeFormInteractions(context);
    },

    /**
     * Initialize form validation styling.
     */
    initializeFormValidation: function(context) {
      // Real-time validation feedback
      once('formValidation', 'input, select, textarea', context).forEach(function(element) {
        var $field = $(element);
        var $formItem = $field.closest('.form-item');
        
        // Add validation classes on blur
        $field.on('blur', function() {
          if ($field.val() && $field[0].checkValidity()) {
            $formItem.removeClass('form-item--error').addClass('form-item--success');
          } else if ($field.val() && !$field[0].checkValidity()) {
            $formItem.removeClass('form-item--success').addClass('form-item--error');
          } else {
            $formItem.removeClass('form-item--error form-item--success');
          }
        });

        // Clear validation on focus
        $field.on('focus', function() {
          $formItem.removeClass('form-item--error form-item--success');
        });
      });

      // Form submission validation
      once('formSubmissionValidation', 'form', context).forEach(function(element) {
        var $form = $(element);
        
        $form.on('submit', function(e) {
          var isValid = true;
          var $firstInvalid = null;
          
          // Check all required fields
          $form.find('input[required], select[required], textarea[required]').each(function() {
            var $field = $(this);
            var $formItem = $field.closest('.form-item');
            
            if (!$field.val() || !$field[0].checkValidity()) {
              $formItem.addClass('form-item--error');
              isValid = false;
              if (!$firstInvalid) {
                $firstInvalid = $field;
              }
            } else {
              $formItem.removeClass('form-item--error').addClass('form-item--success');
            }
          });

          if (!isValid) {
            e.preventDefault();
            if ($firstInvalid) {
              $firstInvalid.focus();
              $firstInvalid.closest('.form-item')[0].scrollIntoView({
                behavior: 'smooth',
                block: 'center'
              });
            }
          }
        });
      });
    },

    /**
     * Initialize form styling enhancements.
     */
    initializeFormStyling: function(context) {
      // Add floating labels for better UX
      once('floatingLabels', '.form-item', context).forEach(function(element) {
        var $formItem = $(element);
        var $field = $formItem.find('input, select, textarea');
        var $label = $formItem.find('label');
        
        if ($field.length && $label.length) {
          // Add floating label class
          $formItem.addClass('form-item--floating-label');
          
          // Check if field has value on load
          if ($field.val()) {
            $formItem.addClass('form-item--has-value');
          }
          
          // Toggle floating state
          $field.on('focus blur input change', function() {
            if ($field.val() || $field.is(':focus')) {
              $formItem.addClass('form-item--has-value');
            } else {
              $formItem.removeClass('form-item--has-value');
            }
          });
        }
      });

      // Enhanced file upload styling
      once('fileUploadEnhancement', '.form-file', context).forEach(function(element) {
        var $fileContainer = $(element);
        var $fileInput = $fileContainer.find('input[type="file"]');
        var $fileLabel = $fileContainer.find('.form-file-label');
        
        if ($fileInput.length) {
          $fileInput.on('change', function() {
            var fileName = this.files[0] ? this.files[0].name : 'No file chosen';
            $fileLabel.text(fileName);
            
            if (this.files[0]) {
              $fileContainer.addClass('form-file--has-file');
            } else {
              $fileContainer.removeClass('form-file--has-file');
            }
          });
        }
      });

      // Password strength indicator
      once('passwordStrength', 'input[type="password"]', context).forEach(function(element) {
        var $password = $(element);
        var $formItem = $password.closest('.form-item');
        
        if (!$formItem.find('.password-strength').length) {
          var strengthHtml = '<div class="password-strength"><div class="password-strength__meter"><div class="password-strength__indicator"></div></div><div class="password-strength__text"></div></div>';
          $formItem.append(strengthHtml);
        }
        
        $password.on('input', function() {
          var password = $password.val();
          var strength = calculatePasswordStrength(password);
          updatePasswordStrength($formItem, strength);
        });
      });
    },

    /**
     * Initialize accessibility enhancements.
     */
    initializeFormAccessibility: function(context) {
      // Add ARIA labels and descriptions
      once('ariaEnhancement', '.form-item', context).forEach(function(element) {
        var $formItem = $(element);
        var $field = $formItem.find('input, select, textarea');
        var $label = $formItem.find('label');
        var $description = $formItem.find('.description');
        var $error = $formItem.find('.form-item__error-message');
        
        if ($field.length) {
          // Add ARIA attributes
          if ($label.length) {
            $field.attr('aria-labelledby', $label.attr('id') || $label.attr('for'));
          }
          
          if ($description.length) {
            $field.attr('aria-describedby', $description.attr('id'));
          }
          
          if ($error.length) {
            $field.attr('aria-invalid', 'true');
            $field.attr('aria-describedby', $error.attr('id'));
          }
        }
      });

      // Enhanced keyboard navigation
      once('keyboardNavigation', 'form', context).forEach(function(element) {
        var $form = $(element);
        
        // Tab order enhancement
        $form.find('input, select, textarea, button').attr('tabindex', function(index) {
          return index + 1;
        });
        
        // Enter key handling for form submission
        $form.find('input, select, textarea').on('keydown', function(e) {
          if (e.key === 'Enter' && !$(this).is('textarea')) {
            e.preventDefault();
            var $nextField = $(this).closest('.form-item').nextAll('.form-item').first().find('input, select, textarea');
            if ($nextField.length) {
              $nextField.focus();
            } else {
              $form.find('input[type="submit"], .form-submit, button[type="submit"]').first().focus();
            }
          }
        });
      });
    },

    /**
     * Initialize form interactions.
     */
    initializeFormInteractions: function(context) {
      // Auto-save functionality
      once('autoSave', 'form[data-auto-save]', context).forEach(function(element) {
        var $form = $(element);
        var autoSaveInterval = $form.data('auto-save') || 30000; // 30 seconds default
        var saveTimeout;
        
        $form.find('input, select, textarea').on('input change', function() {
          clearTimeout(saveTimeout);
          saveTimeout = setTimeout(function() {
            saveFormData($form);
          }, autoSaveInterval);
        });
      });

      // Form progress indicator
      once('progressIndicator', 'form[data-progress]', context).forEach(function(element) {
        var $form = $(element);
        var $progressBar = $('<div class="form-progress"><div class="form-progress__bar"></div></div>');
        $form.prepend($progressBar);
        
        updateFormProgress($form);
        
        $form.find('input, select, textarea').on('input change', function() {
          updateFormProgress($form);
        });
      });

      // Conditional field visibility
      once('conditionalFields', '.form-item[data-depends-on]', context).forEach(function(element) {
        var $formItem = $(element);
        var dependsOn = $formItem.data('depends-on');
        var $dependsField = $formItem.closest('form').find('[name="' + dependsOn + '"]');
        
        if ($dependsField.length) {
          function toggleVisibility() {
            if ($dependsField.val()) {
              $formItem.slideDown(300);
            } else {
              $formItem.slideUp(300);
            }
          }
          
          $dependsField.on('change', toggleVisibility);
          toggleVisibility(); // Initial state
        }
      });
    }
  };

  /**
   * Calculate password strength.
   */
  function calculatePasswordStrength(password) {
    var strength = 0;
    var checks = {
      length: password.length >= 8,
      lowercase: /[a-z]/.test(password),
      uppercase: /[A-Z]/.test(password),
      numbers: /\d/.test(password),
      symbols: /[^A-Za-z0-9]/.test(password)
    };
    
    Object.keys(checks).forEach(function(check) {
      if (checks[check]) strength++;
    });
    
    return {
      score: strength,
      percentage: (strength / 5) * 100,
      checks: checks
    };
  }

  /**
   * Update password strength indicator.
   */
  function updatePasswordStrength($formItem, strength) {
    var $indicator = $formItem.find('.password-strength__indicator');
    var $text = $formItem.find('.password-strength__text');
    
    $indicator.css('width', strength.percentage + '%');
    
    var strengthText = '';
    var strengthClass = '';
    
    if (strength.score <= 1) {
      strengthText = 'Very Weak';
      strengthClass = 'password-strength--very-weak';
    } else if (strength.score <= 2) {
      strengthText = 'Weak';
      strengthClass = 'password-strength--weak';
    } else if (strength.score <= 3) {
      strengthText = 'Fair';
      strengthClass = 'password-strength--fair';
    } else if (strength.score <= 4) {
      strengthText = 'Good';
      strengthClass = 'password-strength--good';
    } else {
      strengthText = 'Strong';
      strengthClass = 'password-strength--strong';
    }
    
    $text.text(strengthText);
    $formItem.removeClass('password-strength--very-weak password-strength--weak password-strength--fair password-strength--good password-strength--strong')
             .addClass(strengthClass);
  }

  /**
   * Save form data locally.
   */
  function saveFormData($form) {
    var formData = {};
    $form.find('input, select, textarea').each(function() {
      var $field = $(this);
      var name = $field.attr('name');
      if (name) {
        formData[name] = $field.val();
      }
    });
    
    localStorage.setItem('form_auto_save_' + $form.attr('id'), JSON.stringify(formData));
    
    // Show save indicator
    showSaveIndicator($form);
  }

  /**
   * Show save indicator.
   */
  function showSaveIndicator($form) {
    var $indicator = $('<div class="form-save-indicator">Draft saved</div>');
    $form.append($indicator);
    
    setTimeout(function() {
      $indicator.fadeOut(300, function() {
        $indicator.remove();
      });
    }, 2000);
  }

  /**
   * Update form progress.
   */
  function updateFormProgress($form) {
    var totalFields = $form.find('input[required], select[required], textarea[required]').length;
    var filledFields = $form.find('input[required], select[required], textarea[required]').filter(function() {
      return $(this).val() !== '';
    }).length;
    
    var percentage = totalFields > 0 ? (filledFields / totalFields) * 100 : 0;
    
    $form.find('.form-progress__bar').css('width', percentage + '%');
  }

  /**
   * Enhanced form validation with custom messages.
   */
  Drupal.behaviors.enhancedFormValidation = {
    attach: function (context, settings) {
      // Custom validation messages
      var validationMessages = {
        required: 'This field is required.',
        email: 'Please enter a valid email address.',
        url: 'Please enter a valid URL.',
        tel: 'Please enter a valid phone number.',
        number: 'Please enter a valid number.',
        minlength: 'Please enter at least {0} characters.',
        maxlength: 'Please enter no more than {0} characters.',
        pattern: 'Please enter a valid value.'
      };

      // Override default validation messages
      once('customValidation', 'input, select, textarea', context).forEach(function(element) {
        var $field = $(element);
        
        $field.on('invalid', function(e) {
          var validity = this.validity;
          var message = '';
          
          if (validity.valueMissing) {
            message = validationMessages.required;
          } else if (validity.typeMismatch) {
            if (this.type === 'email') {
              message = validationMessages.email;
            } else if (this.type === 'url') {
              message = validationMessages.url;
            } else if (this.type === 'tel') {
              message = validationMessages.tel;
            }
          } else if (validity.tooShort) {
            message = validationMessages.minlength.replace('{0}', this.minLength);
          } else if (validity.tooLong) {
            message = validationMessages.maxlength.replace('{0}', this.maxLength);
          } else if (validity.patternMismatch) {
            message = validationMessages.pattern;
          }
          
          this.setCustomValidity(message);
        });
        
        $field.on('input', function() {
          this.setCustomValidity('');
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
