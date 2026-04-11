/**
 * @file
 * Drupal Professional JavaScript Standards Implementation
 * 
 * This file demonstrates and implements Drupal professional JavaScript standards
 * including proper behaviors, once API, accessibility, and performance best practices.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Professional Drupal JavaScript Standards Implementation
   * 
   * This behavior demonstrates proper Drupal JavaScript patterns including:
   * - Proper use of Drupal.behaviors
   * - Once API for performance
   * - Accessibility features
   * - Error handling
   * - Performance optimization
   * - Mobile responsiveness
   */
  Drupal.behaviors.drupalProfessionalStandards = {
    attach: function (context, settings) {
      // Initialize all professional features
      this.initializeAccessibility(context, settings);
      this.initializePerformanceOptimizations(context, settings);
      this.initializeMobileResponsiveness(context, settings);
      this.initializeErrorHandling(context, settings);
      this.initializeAccessibilityFeatures(context, settings);
    },

    /**
     * Initialize accessibility features following WCAG guidelines.
     */
    initializeAccessibility: function (context, settings) {
      // Skip links functionality
      once('skip-links', '.skip-link', context).forEach(function (element) {
        var $element = $(element);
        $element.on('click', function (e) {
          e.preventDefault();
          var target = $element.attr('href');
          if (target && target.startsWith('#')) {
            var $target = $(target);
            if ($target.length) {
              $target.attr('tabindex', '-1').focus();
              $('html, body').animate({
                scrollTop: $target.offset().top - 20
              }, 300);
            }
          }
        });
      });

      // Focus management for modals and dropdowns
      once('focus-management', '[data-focus-trap]', context).forEach(function (element) {
        var $element = $(element);
        var $focusableElements = $element.find('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
        var $firstFocusable = $focusableElements.first();
        var $lastFocusable = $focusableElements.last();

        $element.on('keydown', function (e) {
          if (e.key === 'Tab') {
            if (e.shiftKey) {
              if (document.activeElement === $firstFocusable[0]) {
                e.preventDefault();
                $lastFocusable.focus();
              }
            } else {
              if (document.activeElement === $lastFocusable[0]) {
                e.preventDefault();
                $firstFocusable.focus();
              }
            }
          }
        });
      });

      // ARIA live regions for dynamic content
      once('aria-live', '[data-aria-live]', context).forEach(function (element) {
        var $element = $(element);
        var politeness = $element.data('aria-live') || 'polite';
        $element.attr('aria-live', politeness);
      });
    },

    /**
     * Initialize performance optimizations.
     */
    initializePerformanceOptimizations: function (context, settings) {
      // Lazy loading for images
      once('lazy-loading', 'img[data-src]', context).forEach(function (element) {
        var $img = $(element);
        var observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              var $entryImg = $(entry.target);
              $entryImg.attr('src', $entryImg.data('src'));
              $entryImg.removeClass('lazy');
              observer.unobserve(entry.target);
            }
          });
        });
        observer.observe(element);
      });

      // Debounced resize handler
      var resizeTimeout;
      $(window).on('resize', function () {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function () {
          $(document).trigger('drupal:resize');
        }, 250);
      });

      // Throttled scroll handler
      var scrollTimeout;
      $(window).on('scroll', function () {
        if (!scrollTimeout) {
          scrollTimeout = setTimeout(function () {
            $(document).trigger('drupal:scroll');
            scrollTimeout = null;
          }, 16); // ~60fps
        }
      });
    },

    /**
     * Initialize mobile responsiveness features.
     */
    initializeMobileResponsiveness: function (context, settings) {
      // Touch-friendly interactions
      once('touch-friendly', '[data-touch-action]', context).forEach(function (element) {
        var $element = $(element);
        var touchAction = $element.data('touch-action') || 'manipulation';
        $element.css('touch-action', touchAction);
      });

      // Mobile menu handling
      once('mobile-menu', '.mobile-menu-toggle', context).forEach(function (element) {
        var $toggle = $(element);
        var $menu = $($toggle.data('target'));
        
        $toggle.on('click', function (e) {
          e.preventDefault();
          $menu.toggleClass('mobile-open');
          $toggle.toggleClass('active');
          $('body').toggleClass('mobile-menu-open');
        });

        // Close on escape
        $(document).on('keydown', function (e) {
          if (e.key === 'Escape' && $menu.hasClass('mobile-open')) {
            $menu.removeClass('mobile-open');
            $toggle.removeClass('active');
            $('body').removeClass('mobile-menu-open');
          }
        });
      });

      // Viewport detection
      var isMobile = window.innerWidth <= 768;
      $('body').toggleClass('is-mobile', isMobile);
      
      $(window).on('resize', function () {
        var newIsMobile = window.innerWidth <= 768;
        if (newIsMobile !== isMobile) {
          isMobile = newIsMobile;
          $('body').toggleClass('is-mobile', isMobile);
          $(document).trigger('drupal:mobile-change', [isMobile]);
        }
      });
    },

    /**
     * Initialize comprehensive error handling.
     */
    initializeErrorHandling: function (context, settings) {
      // Global error handler
      window.addEventListener('error', function (e) {
        console.error('JavaScript Error:', e.error);
        Drupal.behaviors.drupalProfessionalStandards.logError('JavaScript Error', e.error);
      });

      // Unhandled promise rejection handler
      window.addEventListener('unhandledrejection', function (e) {
        console.error('Unhandled Promise Rejection:', e.reason);
        Drupal.behaviors.drupalProfessionalStandards.logError('Promise Rejection', e.reason);
      });

      // AJAX error handling
      $(document).ajaxError(function (event, xhr, settings, error) {
        console.error('AJAX Error:', error);
        Drupal.behaviors.drupalProfessionalStandards.logError('AJAX Error', error);
      });
    },

    /**
     * Initialize advanced accessibility features.
     */
    initializeAccessibilityFeatures: function (context, settings) {
      // High contrast mode detection
      if (window.matchMedia && window.matchMedia('(prefers-contrast: high)').matches) {
        $('body').addClass('high-contrast');
      }

      // Reduced motion detection
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        $('body').addClass('reduced-motion');
        // Disable animations for users who prefer reduced motion
        $('*').css({
          'animation-duration': '0.01ms !important',
          'animation-iteration-count': '1 !important',
          'transition-duration': '0.01ms !important'
        });
      }

      // Keyboard navigation enhancements
      once('keyboard-nav', '[data-keyboard-nav]', context).forEach(function (element) {
        var $element = $(element);
        $element.on('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $element.trigger('click');
          }
        });
      });

      // Screen reader announcements
      this.initializeScreenReaderAnnouncements(context, settings);
    },

    /**
     * Initialize screen reader announcements.
     */
    initializeScreenReaderAnnouncements: function (context, settings) {
      // Create announcement region if it doesn't exist
      if (!$('#drupal-announcements').length) {
        $('body').append('<div id="drupal-announcements" class="sr-only" aria-live="polite" aria-atomic="true"></div>');
      }

      // Listen for announcement events
      $(document).on('drupal:announce', function (e, message) {
        $('#drupal-announcements').text(message);
      });
    },

    /**
     * Log errors with proper context.
     */
    logError: function (type, error) {
      var errorData = {
        type: type,
        message: error.message || error,
        stack: error.stack,
        timestamp: new Date().toISOString(),
        userAgent: navigator.userAgent,
        url: window.location.href
      };

      // Log to console in development
      if (drupalSettings && drupalSettings.drupalProfessional && drupalSettings.drupalProfessional.debug) {
        console.error('Drupal Professional Error:', errorData);
      }

      // Send to server in production (if configured)
      if (drupalSettings && drupalSettings.drupalProfessional && drupalSettings.drupalProfessional.errorReporting) {
        this.sendErrorToServer(errorData);
      }
    },

    /**
     * Send error data to server for logging.
     */
    sendErrorToServer: function (errorData) {
      $.ajax({
        url: '/api/error-log',
        method: 'POST',
        data: JSON.stringify(errorData),
        contentType: 'application/json',
        timeout: 5000
      }).fail(function () {
        console.warn('Failed to send error data to server');
      });
    }
  };

  /**
   * Professional Form Enhancements
   * 
   * Demonstrates proper form handling with accessibility and validation.
   */
  Drupal.behaviors.professionalFormEnhancements = {
    attach: function (context, settings) {
      // Form validation with accessibility
      this.initializeFormValidation(context, settings);
      this.initializeFormAccessibility(context, settings);
      this.initializeFormPerformance(context, settings);
    },

    /**
     * Initialize form validation with proper error handling.
     */
    initializeFormValidation: function (context, settings) {
      once('form-validation', 'form', context).forEach(function (form) {
        var $form = $(form);
        var $fields = $form.find('input, select, textarea');
        
        // Real-time validation
        $fields.on('blur', function () {
          var $field = $(this);
          var isValid = this.checkValidity();
          
          if (isValid) {
            $field.removeClass('error').addClass('valid');
            $field.attr('aria-invalid', 'false');
            $field.siblings('.error-message').remove();
          } else {
            $field.removeClass('valid').addClass('error');
            $field.attr('aria-invalid', 'true');
            this.showFieldError($field, this.validationMessage);
          }
        });

        // Form submission validation
        $form.on('submit', function (e) {
          var isValid = true;
          var $firstInvalid = null;

          $fields.each(function () {
            if (!this.checkValidity()) {
              isValid = false;
              if (!$firstInvalid) {
                $firstInvalid = $(this);
              }
              $(this).addClass('error').attr('aria-invalid', 'true');
            }
          });

          if (!isValid) {
            e.preventDefault();
            if ($firstInvalid) {
              $firstInvalid.focus();
              $firstInvalid[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          }
        });
      });
    },

    /**
     * Initialize form accessibility features.
     */
    initializeFormAccessibility: function (context, settings) {
      once('form-accessibility', 'form', context).forEach(function (form) {
        var $form = $(form);
        
        // Add ARIA labels and descriptions
        $form.find('input, select, textarea').each(function () {
          var $field = $(this);
          var $label = $form.find('label[for="' + this.id + '"]');
          var $description = $field.siblings('.description');
          
          if ($label.length) {
            $field.attr('aria-labelledby', $label.attr('id'));
          }
          
          if ($description.length) {
            $field.attr('aria-describedby', $description.attr('id'));
          }
        });

        // Enhanced keyboard navigation
        $form.find('input, select, textarea').on('keydown', function (e) {
          if (e.key === 'Enter' && !$(this).is('textarea')) {
            e.preventDefault();
            var $nextField = $(this).closest('.form-item').nextAll('.form-item').first().find('input, select, textarea');
            if ($nextField.length) {
              $nextField.focus();
            } else {
              $form.find('input[type="submit"], button[type="submit"]').first().focus();
            }
          }
        });
      });
    },

    /**
     * Initialize form performance optimizations.
     */
    initializeFormPerformance: function (context, settings) {
      // Auto-save functionality
      once('form-autosave', 'form[data-autosave]', context).forEach(function (form) {
        var $form = $(form);
        var autoSaveInterval = $form.data('autosave') || 30000;
        var saveTimeout;
        
        $form.find('input, select, textarea').on('input change', function () {
          clearTimeout(saveTimeout);
          saveTimeout = setTimeout(function () {
            Drupal.behaviors.professionalFormEnhancements.saveFormData($form);
          }, autoSaveInterval);
        });
      });
    },

    /**
     * Save form data locally.
     */
    saveFormData: function ($form) {
      var formData = {};
      $form.find('input, select, textarea').each(function () {
        var $field = $(this);
        var name = $field.attr('name');
        if (name && $field.val()) {
          formData[name] = $field.val();
        }
      });
      
      localStorage.setItem('form_autosave_' + $form.attr('id'), JSON.stringify(formData));
      this.showSaveIndicator($form);
    },

    /**
     * Show save indicator.
     */
    showSaveIndicator: function ($form) {
      var $indicator = $('<div class="form-save-indicator" role="status" aria-live="polite">Draft saved</div>');
      $form.append($indicator);
      
      setTimeout(function () {
        $indicator.fadeOut(300, function () {
          $indicator.remove();
        });
      }, 2000);
    },

    /**
     * Show field error with proper accessibility.
     */
    showFieldError: function ($field, message) {
      $field.addClass('error');
      $field.attr('aria-invalid', 'true');
      $field.siblings('.error-message').remove();
      
      var $error = $('<div class="error-message" role="alert" aria-live="polite">' + message + '</div>');
      $field.after($error);
      $field.attr('aria-describedby', $error.attr('id') || 'error-' + Math.random().toString(36).substr(2, 9));
    }
  };

  /**
   * Professional AJAX Enhancements
   * 
   * Demonstrates proper AJAX handling with accessibility and performance.
   */
  Drupal.behaviors.professionalAjaxEnhancements = {
    attach: function (context, settings) {
      this.initializeAjaxAccessibility(context, settings);
      this.initializeAjaxPerformance(context, settings);
      this.initializeAjaxErrorHandling(context, settings);
    },

    /**
     * Initialize AJAX accessibility features.
     */
    initializeAjaxAccessibility: function (context, settings) {
      // Loading indicators with screen reader support
      $(document).ajaxStart(function () {
        $('body').addClass('ajax-loading');
        $('#drupal-announcements').text('Loading...');
      }).ajaxStop(function () {
        $('body').removeClass('ajax-loading');
        $('#drupal-announcements').text('Content loaded');
      });
    },

    /**
     * Initialize AJAX performance optimizations.
     */
    initializeAjaxPerformance: function (context, settings) {
      // Request caching
      var requestCache = {};
      
      $(document).on('click', '[data-ajax-cache]', function (e) {
        var $link = $(this);
        var url = $link.attr('href');
        var cacheKey = url;
        
        if (requestCache[cacheKey]) {
          e.preventDefault();
          this.loadCachedContent($link, requestCache[cacheKey]);
        }
      });
    },

    /**
     * Initialize AJAX error handling.
     */
    initializeAjaxErrorHandling: function (context, settings) {
      // Global AJAX error handler
      $(document).ajaxError(function (event, xhr, settings, error) {
        var errorMessage = 'An error occurred while loading content. Please try again.';
        
        if (xhr.status === 404) {
          errorMessage = 'The requested content was not found.';
        } else if (xhr.status === 500) {
          errorMessage = 'A server error occurred. Please try again later.';
        }
        
        $('#drupal-announcements').text(errorMessage);
        console.error('AJAX Error:', error, xhr);
      });
    }
  };

  /**
   * Message Close Buttons
   * 
   * Adds close buttons to all Drupal messages and alerts.
   */
  Drupal.behaviors.messageCloseButtons = {
    attach: function (context, settings) {
      // Find all messages that don't already have a close button
      once('message-close', '.messages:not(.has-close-button), .alert:not(.has-close-button), [data-drupal-messages] .messages:not(.has-close-button)', context).forEach(function (element) {
        var $message = $(element);
        
        // Skip if already has a close button
        if ($message.find('.messages__close, .close').length) {
          return;
        }
        
        // Create close button
        var $closeButton = $('<button type="button" class="messages__close" aria-label="Close message" title="Close"></button>');
        
        // Add close button to message
        $message.append($closeButton);
        $message.addClass('has-close-button');
        
        // Handle close button click
        $closeButton.on('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          
          // Animate out
          $message.fadeOut(300, function () {
            var $wrapper = $message.closest('.messages__wrapper');
            $message.remove();
            
            // Remove wrapper if empty
            if ($wrapper.length && $wrapper.find('.messages').length === 0) {
              $wrapper.remove();
            }
            
            // Remove data-drupal-messages container if empty
            var $container = $message.closest('[data-drupal-messages]');
            if ($container.length && $container.find('.messages').length === 0) {
              $container.remove();
            }
            
            // Announce removal to screen readers
            if ($('#drupal-announcements').length) {
              $('#drupal-announcements').text('Message closed');
            }
          });
        });
      });
      
      // Handle Escape key for visible messages
      $(document).off('keydown.message-close').on('keydown.message-close', function (e) {
        if (e.key === 'Escape') {
          var $visibleMessage = $('.messages.has-close-button:visible, .alert.has-close-button:visible').first();
          if ($visibleMessage.length) {
            $visibleMessage.find('.messages__close, .close').first().trigger('click');
          }
        }
      });
    }
  };

  /**
   * Professional Theme Enhancements
   * 
   * Demonstrates proper theme JavaScript with accessibility and performance.
   */
  Drupal.behaviors.professionalThemeEnhancements = {
    attach: function (context, settings) {
      this.initializeThemeToggle(context, settings);
      this.initializeResponsiveFeatures(context, settings);
      this.initializeAnimationControls(context, settings);
    },

    /**
     * Initialize theme toggle with proper state management.
     */
    initializeThemeToggle: function (context, settings) {
      once('theme-toggle', '.theme-toggle, #theme-toggle', context).forEach(function (element) {
        var $toggle = $(element);
        var $body = $('body');
        
        // Check for saved theme preference
        var savedTheme = localStorage.getItem('theme') || 'light';
        $body.attr('data-theme', savedTheme);
        
        $toggle.on('click', function (e) {
          e.preventDefault();
          
          var currentTheme = $body.attr('data-theme');
          var newTheme = currentTheme === 'dark' ? 'light' : 'dark';
          
          $body.attr('data-theme', newTheme);
          localStorage.setItem('theme', newTheme);
          
          // Announce theme change
          $('#drupal-announcements').text('Theme changed to ' + newTheme + ' mode');
          
          // Trigger custom event
          $(document).trigger('drupal:theme-change', [newTheme]);
        });
      });
    },

    /**
     * Initialize responsive features.
     */
    initializeResponsiveFeatures: function (context, settings) {
      // Responsive navigation
      once('responsive-nav', '.responsive-nav-toggle', context).forEach(function (element) {
        var $toggle = $(element);
        var $nav = $($toggle.data('target'));
        
        $toggle.on('click', function (e) {
          e.preventDefault();
          $nav.toggleClass('nav-open');
          $toggle.toggleClass('active');
          $('body').toggleClass('nav-open');
        });
      });

      // Responsive tables
      once('responsive-tables', 'table', context).forEach(function (table) {
        var $table = $(table);
        if ($table[0].scrollWidth > $table.parent().width()) {
          $table.addClass('table-responsive');
        }
      });
    },

    /**
     * Initialize animation controls.
     */
    initializeAnimationControls: function (context, settings) {
      // Respect user's motion preferences
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        $('body').addClass('reduced-motion');
      }

      // Animation controls
      once('animation-controls', '[data-animation]', context).forEach(function (element) {
        var $element = $(element);
        var animation = $element.data('animation');
        
        if (!$('body').hasClass('reduced-motion')) {
          $element.addClass('animate-' + animation);
        }
      });
    }
  };

})(jQuery, Drupal, drupalSettings, once);



