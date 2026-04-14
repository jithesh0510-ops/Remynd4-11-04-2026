/**
 * Enhanced Dropdown & Collapse Handler
 * Properly handles opening and closing of dropdowns
 * Requires: core/jquery, core/drupal, core/once
 * Version: 2.0 - Fixed close functionality
 */
(function (Drupal, once, $) {
  'use strict';
  if (typeof $ === 'undefined') { 
    console.error('jQuery is not loaded!');
    return; 
  }

  console.log('Dropdown handler loaded');

  // ----- Helper Functions -----
  function getTarget($toggle) {
    var sel = $toggle.attr('data-bs-target') || $toggle.attr('href');
    if (!sel || sel === '#') return $();
    if (sel.indexOf('#') !== 0) return $();
    return $(sel);
  }

  // ===== Collapse Functions =====
  function setCollapseToggleState($toggle, $target) {
    var open = $target.hasClass('show');
    $toggle.toggleClass('collapsed', !open).attr('aria-expanded', open ? 'true' : 'false');
    var id = $target.attr('id'); 
    if (id) $toggle.attr('aria-controls', id);
  }

  function closePanel($panel) {
    if (!$panel || !$panel.length) return;
    $panel.removeClass('show').addClass('hide').attr('aria-hidden', 'true');
    var id = $panel.attr('id'); 
    if (!id) return;
    $(document).find('[data-bs-toggle="collapse"][data-bs-target="#'+id+'"],[data-bs-toggle="collapse"][href="#'+id+'"]')
      .each(function(){ setCollapseToggleState($(this), $panel); });
  }

  function openPanel($panel) {
    if (!$panel || !$panel.length) return;
    var parentSel = $panel.attr('data-bs-parent');
    if (parentSel) {
      var $parent = $(parentSel);
      if ($parent.length) {
        $parent.find('.collapse.show').not($panel).each(function(){ closePanel($(this)); });
      }
    }
    $panel.removeClass('hide').addClass('show').attr('aria-hidden', 'false');
  }

  // ===== Dropdown Functions =====
  function findMenu($toggle) {
    // Try to find by aria-labelledby first
    var id = $toggle.attr('id');
    if (id) {
      var $byLabel = $('.dropdown-menu[aria-labelledby="'+id+'"]');
      if ($byLabel.length) return $byLabel.eq(0);
    }
    
    // Try next sibling
    var $next = $toggle.next('.dropdown-menu');
    if ($next.length) return $next.eq(0);
    
    // Try parent's child
    var $wrap = $toggle.closest('.dropdown, .nav-item, .dropleft, .dropright, .dropup');
    var $direct = $wrap.children('.dropdown-menu').eq(0);
    if ($direct.length) return $direct;
    
    // Try parent's dropdown-menu anywhere
    var $anyMenu = $wrap.find('.dropdown-menu').eq(0);
    return $anyMenu.length ? $anyMenu : $();
  }

  function setDropdownState($toggle, $menu, open) {
    console.log('Setting dropdown state:', open ? 'OPEN' : 'CLOSED');
    
    // Update toggle
    $toggle
      .attr('aria-expanded', open ? 'true' : 'false')
      .toggleClass('show', open)
      .removeClass(open ? '' : 'show');
    
    // Update menu
    $menu
      .toggleClass('show', open)
      .toggleClass('hide', !open)
      .removeClass(open ? 'hide' : 'show')
      .attr('aria-hidden', open ? 'false' : 'true');
    
    // Update parent container
    var $parent = $toggle.closest('.dropdown, .nav-item, .dropleft, .dropright, .dropup');
    $parent
      .toggleClass('show', open)
      .removeClass(open ? '' : 'show');
  }

  function closeAllDropdowns(except) {
    console.log('Closing all dropdowns...');
    
    $('.dropdown-menu.show, .nav-item.show, .dropdown.show').each(function () {
      var $elem = $(this);
      
      // Find the menu
      var $menu = $elem.hasClass('dropdown-menu') ? $elem : $elem.find('.dropdown-menu');
      if (!$menu.length) return;
      
      // Skip if this is the exception
      if (except && $menu.is(except)) {
        console.log('Skipping exception menu');
        return;
      }
      
      // Find the toggle
      var $toggle = $('[data-bs-toggle="dropdown"]').filter(function () {
        return findMenu($(this)).is($menu);
      }).eq(0);
      
      if ($toggle.length) {
        console.log('Closing dropdown via toggle');
        setDropdownState($toggle, $menu, false);
      } else {
        // Fallback: close manually
        console.log('Closing dropdown manually');
        $menu.removeClass('show').addClass('hide').attr('aria-hidden', 'true');
        $elem.removeClass('show');
      }
    });
  }

  // ===== Main Behavior =====
  Drupal.behaviors.navCollapseDropdown = {
    attach: function (context) {
      console.log('Attaching dropdown behaviors...');
      
      // ===== Collapse =====
      $(once('navCollapseInit', '[data-bs-toggle="collapse"]', context)).each(function () {
        var $toggle = $(this);

        // Let Performance Dashboard manage its own accordion behavior.
        if ($toggle.closest('.programs-accordion').length) {
          return;
        }

        var $target = getTarget($toggle);
        if (!$target.length) return;

        // Normalize initial state
        if ($target.hasClass('show')) { 
          $target.removeClass('hide').attr('aria-hidden','false'); 
        } else { 
          $target.addClass('hide').attr('aria-hidden','true'); 
        }
        setCollapseToggleState($toggle, $target);

        $toggle.on('click.navCollapse keydown.navCollapse', function (e) {
          if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
          if (this.tagName === 'A') e.preventDefault();
          var $t = getTarget($toggle);
          var willOpen = !$t.hasClass('show');
          if (willOpen) openPanel($t); 
          else closePanel($t);
          setCollapseToggleState($toggle, $t);
        });
      });

      // ===== Dropdown =====
      $(once('navDropdownInit', '[data-bs-toggle="dropdown"]', context)).each(function () {
        var $toggle = $(this);
        var $menu = findMenu($toggle);
        
        if (!$menu.length) {
          console.warn('No dropdown menu found for toggle:', $toggle);
          return;
        }

        console.log('Initialized dropdown for:', $toggle.attr('id') || 'unnamed');

        // Set initial state
        setDropdownState($toggle, $menu, $menu.hasClass('show'));

        // Click handler for toggle
        $toggle.on('click.navDrop keydown.navDrop', function (e) {
          if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
          
          e.preventDefault();
          e.stopPropagation();
          
          var isOpen = $menu.hasClass('show');
          console.log('Dropdown clicked, currently:', isOpen ? 'OPEN' : 'CLOSED');
          
          if (isOpen) { 
            // Close this dropdown
            setDropdownState($toggle, $menu, false);
          } else { 
            // Close all others, then open this one
            closeAllDropdowns($menu); 
            setDropdownState($toggle, $menu, true);
          }
        });

        // Prevent dropdown from closing when clicking inside
        $menu.on('click.navDropInside', function (e) { 
          e.stopPropagation(); 
        });
      });

      // ===== Global Close Handlers =====
      // Only attach once to the document
      $(once('navDropdownGlobal', 'html', document)).each(function () {
        console.log('Attaching global close handlers');
        
        // Click outside to close
        $(document).on('click.navDropClose', function (e) {
          var $target = $(e.target);
          
          // Don't close if clicking on toggle or menu
          if ($target.closest('.dropdown-menu').length > 0) {
            console.log('Click inside dropdown menu, not closing');
            return;
          }
          
          if ($target.closest('[data-bs-toggle="dropdown"]').length > 0) {
            console.log('Click on dropdown toggle, handled by toggle');
            return;
          }
          
          // Click outside - close all
          console.log('Click outside dropdowns, closing all');
          closeAllDropdowns();
        });
        
        // ESC key to close
        $(document).on('keydown.navDropEsc', function (e) {
          if (e.key === 'Escape' || e.keyCode === 27) {
            console.log('ESC pressed, closing all dropdowns');
            closeAllDropdowns();
          }
        });
      });

      // ===== Dismissible Drupal Messages =====
      $(once('drupalMessageDismissInit', '[data-drupal-messages] .messages', context)).each(function () {
        var $message = $(this);
        if ($message.find('.messages__close').length) {
          return;
        }

        var label = Drupal.t ? Drupal.t('Close message') : 'Close message';
        var $btn = $('<button type="button" class="messages__close" aria-label="' + label + '"></button>');
        $message.append($btn);
      });

      $(once('drupalMessageDismissClick', 'html', document)).each(function () {
        $(document).on('click.drupalMessageDismiss', '.messages__close', function (e) {
          e.preventDefault();
          var $message = $(this).closest('.messages');
          var $wrapper = $message.closest('.messages__wrapper');
          var $container = $message.closest('[data-drupal-messages]');

          $message.remove();

          if ($wrapper.length && $wrapper.children('.messages').length === 0) {
            $wrapper.remove();
          }
          if ($container.length && $container.find('.messages').length === 0) {
            $container.remove();
          }
        });
      });
    }
  };
})(Drupal, once, (window.jQuery || window.$));

/**
 * Sidebar Toggle Handler
 * Handles sidebar collapse/expand functionality
 * Requires: core/jquery, core/drupal, core/once
 * Version: 1.0
 */
(function (Drupal, once, $) {
  'use strict';
  if (typeof $ === 'undefined') { 
    console.error('jQuery is not loaded!');
    return; 
  }

  /**
   * Sidebar toggle functionality.
   */
  Drupal.behaviors.sidebarToggle = {
    attach: function (context, settings) {
      // Initialize sidebar toggle
      $(once('sidebarToggle', '.js-sidebar-toggle', context)).each(function () {
        var $toggle = $(this);
        var $sidebar = $('.sidebar, #sidebar');
        var $main = $('.main');
        var $body = $('body');
        var $wrapper = $('.wrapper');
        
        // Check for saved state
        var isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        
        // Set initial state
        if (isCollapsed) {
          $body.addClass('sidebar-collapsed');
          $sidebar.addClass('collapsed');
          $main.addClass('sidebar-collapsed');
          if ($wrapper.length) {
            $wrapper.addClass('sidebar-collapsed');
          }
        }
        
        // Toggle functionality
        $toggle.on('click.sidebarToggle', function (e) {
          e.preventDefault();
          e.stopPropagation();
          
          var isCurrentlyCollapsed = $body.hasClass('sidebar-collapsed');
          
          if (isCurrentlyCollapsed) {
            // Expand sidebar
            $body.removeClass('sidebar-collapsed');
            $sidebar.removeClass('collapsed');
            $main.removeClass('sidebar-collapsed');
            if ($wrapper.length) {
              $wrapper.removeClass('sidebar-collapsed');
            }
            localStorage.setItem('sidebar-collapsed', 'false');
          } else {
            // Collapse sidebar
            $body.addClass('sidebar-collapsed');
            $sidebar.addClass('collapsed');
            $main.addClass('sidebar-collapsed');
            if ($wrapper.length) {
              $wrapper.addClass('sidebar-collapsed');
            }
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
      var $sidebar = $('.sidebar, #sidebar');
      var $body = $('body');
      var $overlay = $('.sidebar-overlay');
      
      // Add overlay if it doesn't exist
      if (!$overlay.length) {
        $overlay = $('<div class="sidebar-overlay"></div>');
        $body.append($overlay);
      }
      
      // Mobile toggle
      $(once('mobileSidebar', '.js-sidebar-toggle', context)).each(function () {
        var $toggle = $(this);
        
        $toggle.on('click.mobileSidebar', function (e) {
          e.preventDefault();
          e.stopPropagation();
          
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
      $overlay.on('click.sidebarOverlay', function () {
        $sidebar.removeClass('mobile-open');
        $overlay.removeClass('active');
        $body.removeClass('sidebar-mobile-open');
      });
      
      // Close sidebar on escape key
      $(document).on('keydown.sidebarEsc', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
          if ($sidebar.hasClass('mobile-open')) {
            $sidebar.removeClass('mobile-open');
            $overlay.removeClass('active');
            $body.removeClass('sidebar-mobile-open');
          }
        }
      });
      
      // Handle window resize
      $(window).on('resize.sidebarResize', function () {
        if ($(window).width() > 768) {
          $sidebar.removeClass('mobile-open');
          $overlay.removeClass('active');
          $body.removeClass('sidebar-mobile-open');
        }
      });
    }
  };
})(Drupal, once, (window.jQuery || window.$));

/* Optional CSS if Bootstrap CSS is not included:
.collapse.hide { display:none; }
.collapse.show { display:block; }
.dropdown-menu.hide { display:none; }
.dropdown-menu.show { display:block; }
*/

