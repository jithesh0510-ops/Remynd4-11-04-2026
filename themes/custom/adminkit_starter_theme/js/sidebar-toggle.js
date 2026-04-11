/**
 * @file
 * Sidebar toggle functionality for AdminKit theme.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';
  
  // Import once from Drupal core
  const once = Drupal.once || window.once;

  /**
   * Sidebar toggle functionality.
   */
  Drupal.behaviors.sidebarToggle = {
    attach: function (context, settings) {
      // Initialize sidebar toggle
      once('sidebarToggle', '.js-sidebar-toggle', context).forEach(function (element) {
        var $toggle = $(element);
        var $sidebar = $('.sidebar');
        var $main = $('.main');
        var $body = $('body');
        
        // Check for saved state
        var isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        
        // Set initial state
        if (isCollapsed) {
          $body.addClass('sidebar-collapsed');
          $sidebar.addClass('collapsed');
          $main.addClass('sidebar-collapsed');
        }
        
        // Toggle functionality
        $toggle.on('click', function (e) {
          e.preventDefault();
          
          var isCurrentlyCollapsed = $body.hasClass('sidebar-collapsed');
          
          if (isCurrentlyCollapsed) {
            // Expand sidebar
            $body.removeClass('sidebar-collapsed');
            $sidebar.removeClass('collapsed');
            $main.removeClass('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', 'false');
          } else {
            // Collapse sidebar
            $body.addClass('sidebar-collapsed');
            $sidebar.addClass('collapsed');
            $main.addClass('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', 'true');
          }
          
          // Trigger custom event
          $(document).trigger('sidebar:toggle', [!isCurrentlyCollapsed]);
        });
      });
    }
  };

  /**
   * Mobile sidebar functionality.
   */
  Drupal.behaviors.mobileSidebar = {
    attach: function (context, settings) {
      var $sidebar = $('.sidebar');
      var $overlay = $('<div class="sidebar-overlay"></div>');
      var $body = $('body');
      
      // Add overlay if it doesn't exist
      if (!$('.sidebar-overlay').length) {
        $body.append($overlay);
      }
      
      // Mobile toggle
      once('mobileSidebar', '.js-sidebar-toggle', context).forEach(function (element) {
        var $toggle = $(element);
        
        $toggle.on('click', function (e) {
          e.preventDefault();
          
          if ($(window).width() <= 768) {
            if ($sidebar.hasClass('mobile-open')) {
              // Close mobile sidebar
              $sidebar.removeClass('mobile-open');
              $overlay.removeClass('active');
              $body.removeClass('sidebar-mobile-open');
            } else {
              // Open mobile sidebar
              $sidebar.addClass('mobile-open');
              $overlay.addClass('active');
              $body.addClass('sidebar-mobile-open');
            }
          }
        });
      });
      
      // Close sidebar when clicking overlay
      $overlay.on('click', function () {
        $sidebar.removeClass('mobile-open');
        $overlay.removeClass('active');
        $body.removeClass('sidebar-mobile-open');
      });
      
      // Close sidebar on escape key
      $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $sidebar.hasClass('mobile-open')) {
          $sidebar.removeClass('mobile-open');
          $overlay.removeClass('active');
          $body.removeClass('sidebar-mobile-open');
        }
      });
      
      // Handle window resize
      $(window).on('resize', function () {
        if ($(window).width() > 768) {
          $sidebar.removeClass('mobile-open');
          $overlay.removeClass('active');
          $body.removeClass('sidebar-mobile-open');
        }
      });
    }
  };

  /**
   * Navigation menu toggle functionality.
   */
  Drupal.behaviors.navMenuToggle = {
    attach: function (context, settings) {
      // Handle navigation menu toggles
      once('navMenuToggle', '.nav-toggle, .js-nav-toggle', context).forEach(function (element) {
        var $toggle = $(element);
        var $menu = $toggle.siblings('.nav-menu, .nav').first();
        
        if ($menu.length) {
          $toggle.on('click', function (e) {
            e.preventDefault();
            $menu.toggleClass('show');
            $toggle.toggleClass('active');
          });
        }
      });
      
      // Handle dropdown toggles
      once('dropdownToggle', '.dropdown-toggle, .js-dropdown-toggle', context).forEach(function (element) {
        var $toggle = $(element);
        var $menu = $toggle.next('.dropdown-menu, .nav-dropdown').first();
        
        if ($menu.length) {
          $toggle.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Close other dropdowns
            $('.dropdown-menu, .nav-dropdown').not($menu).removeClass('show');
            $('.dropdown-toggle, .js-dropdown-toggle').not($toggle).removeClass('active');
            
            // Toggle current dropdown
            $menu.toggleClass('show');
            $toggle.toggleClass('active');
          });
        }
      });
      
      // Close dropdowns when clicking outside
      $(document).on('click', function (e) {
        if (!$(e.target).closest('.dropdown, .nav-item').length) {
          $('.dropdown-menu, .nav-dropdown').removeClass('show');
          $('.dropdown-toggle, .js-dropdown-toggle').removeClass('active');
        }
      });
    }
  };

  /**
   * Theme toggle functionality.
   */
  Drupal.behaviors.themeToggle = {
    attach: function (context, settings) {
      once('themeToggle', '.theme-toggle, .js-theme-toggle, #theme-toggle', context).forEach(function (element) {
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
          
          // Trigger custom event
          $(document).trigger('theme:change', [newTheme]);
        });
      });
    }
  };

  /**
   * Collapse/expand functionality for panels and sections.
   */
  Drupal.behaviors.collapseToggle = {
    attach: function (context, settings) {
      once('collapseToggle', '.collapse-toggle, .js-collapse-toggle', context).forEach(function (element) {
        var $toggle = $(element);
        var targetSelector = $toggle.data('target') || $toggle.attr('href');
        var $target = $(targetSelector);
        
        if ($target.length) {
          // Set initial state
          var isCollapsed = $target.hasClass('collapsed') || !$target.hasClass('show');
          $toggle.attr('aria-expanded', !isCollapsed);
          $target.attr('aria-hidden', isCollapsed);
          
          $toggle.on('click', function (e) {
            e.preventDefault();
            
            var isCurrentlyCollapsed = $target.hasClass('collapsed') || !$target.hasClass('show');
            
            if (isCurrentlyCollapsed) {
              // Expand
              $target.removeClass('collapsed').addClass('show');
              $toggle.attr('aria-expanded', 'true');
              $target.attr('aria-hidden', 'false');
            } else {
              // Collapse
              $target.addClass('collapsed').removeClass('show');
              $toggle.attr('aria-expanded', 'false');
              $target.attr('aria-hidden', 'true');
            }
          });
        }
      });
    }
  };

  /**
   * Tab toggle functionality.
   */
  Drupal.behaviors.tabToggle = {
    attach: function (context, settings) {
      once('tabToggle', '.tab-toggle, .js-tab-toggle', context).forEach(function (element) {
        var $toggle = $(element);
        var targetSelector = $toggle.data('target') || $toggle.attr('href');
        var $target = $(targetSelector);
        var $tabContainer = $toggle.closest('.tabs, .tab-container');
        
        if ($target.length && $tabContainer.length) {
          $toggle.on('click', function (e) {
            e.preventDefault();
            
            // Remove active class from all tabs and content
            $tabContainer.find('.tab-toggle, .js-tab-toggle').removeClass('active');
            $tabContainer.find('.tab-content, .tab-panel').removeClass('active show');
            
            // Add active class to clicked tab and target content
            $toggle.addClass('active');
            $target.addClass('active show');
          });
        }
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
