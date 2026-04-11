/**
 * @file
 * JavaScript to make dropbutton actions display inline as buttons.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Convert dropbutton to inline buttons.
   */
  Drupal.behaviors.dropbuttonInline = {
    attach: function (context, settings) {
      once('dropbutton-inline', '.dropbutton-wrapper', context).forEach(function (element) {
        var dropbutton = element.querySelector('.dropbutton');
        if (!dropbutton) {
          return;
        }

        // Show all actions
        var actions = dropbutton.querySelectorAll('.dropbutton-action');
        actions.forEach(function (action) {
          action.style.display = 'inline-block';
          action.style.visibility = 'visible';
          action.style.opacity = '1';
          action.style.position = 'static';
        });

        // Hide the toggle button
        var toggle = dropbutton.querySelector('.dropbutton-toggle');
        if (toggle) {
          toggle.style.display = 'none';
        }

        // Remove any classes that might hide secondary actions
        dropbutton.classList.remove('dropbutton-multiple');
        dropbutton.classList.add('dropbutton-inline');
      });
    }
  };

  /**
   * Override Drupal dropbutton behavior to keep actions visible.
   */
  if (Drupal.behaviors.dropbutton) {
    var originalAttach = Drupal.behaviors.dropbutton.attach;
    Drupal.behaviors.dropbutton.attach = function (context, settings) {
      // Call original behavior
      if (originalAttach) {
        originalAttach(context, settings);
      }

      // Then make all actions visible
      once('dropbutton-inline-override', '.dropbutton-wrapper', context).forEach(function (element) {
        var dropbutton = element.querySelector('.dropbutton');
        if (!dropbutton) {
          return;
        }

        // Force all actions to be visible
        var actions = dropbutton.querySelectorAll('.dropbutton-action');
        actions.forEach(function (action) {
          action.style.display = 'inline-block';
          action.style.visibility = 'visible';
          action.style.opacity = '1';
          action.style.position = 'static';
        });

        // Hide toggle
        var toggle = dropbutton.querySelector('.dropbutton-toggle');
        if (toggle) {
          toggle.style.display = 'none';
        }
      });
    };
  }

})(Drupal, once);

