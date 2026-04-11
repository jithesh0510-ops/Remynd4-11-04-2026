/**
 * Report result JavaScript functionality using Google Charts.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.reportResult = {
    attach: function (context, settings) {
      var self = this; // Define self to reference the behavior object
      
      // Store settings globally for access in callbacks
      window.coachReportingSettings = settings || drupalSettings;
      
      // Initialize tabs functionality
      this.initializeTabs(context);
      
      // Load Google Charts from CDN if not already loaded
      this.loadGoogleCharts(context);
      
      // Initialize coaching impact charts
      this.initializeCoachingImpactCharts(context);
      
      // Listen for tab shown events to reinitialize charts
      $(context).on('tab:shown', '.tab-pane', function() {
        // Reinitialize charts when tab is shown
        self.initializeCoachingImpactCharts(context);
      });
    },

    /**
     * Initialize tabs functionality with hide/show logic.
     */
    initializeTabs: function (context) {
      var self = this;
      
      // Handle tab clicks with enhanced hide/show logic
      $(context).find('.nav-tabs .nav-link').on('click', function (e) {
          e.preventDefault();
          
        var $this = $(this);
        var target = $this.attr('href') || $this.data('target');
        
        if (target) {
          // Hide all tab panes first
          self.hideAllTabPanes(context);
          
          // Remove active class from all tabs
          $this.closest('.nav-tabs').find('.nav-link').removeClass('active');
          
          // Add active class to clicked tab
          $this.addClass('active');
          
          // Show target pane with animation
          self.showTabPane(target);
          
          // Update URL fragment
          if (target.startsWith('#')) {
            window.location.hash = target;
          }
        }
      });
      
      // Handle hash-based tab switching
      if (window.location.hash) {
        var hash = window.location.hash;
        var $targetTab = $(context).find('.nav-tabs .nav-link[href="' + hash + '"]');
        if ($targetTab.length) {
          $targetTab.trigger('click');
        }
      }
      
      // Initialize first tab if no hash
      if (!window.location.hash) {
        var $firstTab = $(context).find('.nav-tabs .nav-link').first();
        if ($firstTab.length) {
          $firstTab.trigger('click');
        }
      }
    },

    /**
     * Hide all tab panes.
     */
    hideAllTabPanes: function (context) {
      $(context).find('.tab-pane').each(function () {
        var $pane = $(this);
        $pane.removeClass('show active').hide();
        console.log('Hiding tab pane:', $pane.attr('id'));
      });
    },

    /**
     * Show specific tab pane.
     */
    showTabPane: function (target) {
      var $targetPane = $(target);
      if ($targetPane.length) {
        $targetPane.addClass('show active').show();
        console.log('Showing tab pane:', target);
        
        // Trigger any charts or other content that needs to be reinitialized
        $targetPane.trigger('tab:shown');
      }
    },

    /**
     * Toggle tab visibility.
     */
    toggleTabVisibility: function (tabId, show) {
      var $tab = $('#' + tabId);
      if (show) {
        this.showTabPane('#' + tabId);
      } else {
        this.hideTabPane('#' + tabId);
      }
    },

    /**
     * Hide specific tab pane.
     */
    hideTabPane: function (target) {
      var $targetPane = $(target);
      if ($targetPane.length) {
        $targetPane.removeClass('show active').hide();
        console.log('Hiding tab pane:', target);
      }
    },

    /**
     * Get currently active tab.
     */
    getActiveTab: function (context) {
      return $(context).find('.nav-tabs .nav-link.active');
    },

    /**
     * Get currently visible tab pane.
     */
    getVisibleTabPane: function (context) {
      return $(context).find('.tab-pane.show.active');
    },

    /**
     * Load Google Charts library from CDN.
     */
    loadGoogleCharts: function (context) {
      if (typeof google === 'undefined' || !google.charts) {
        var self = this;
        var script = document.createElement('script');
        script.src = 'https://www.gstatic.com/charts/loader.js';
        script.onload = function() {
          google.charts.load('current', {'packages':['corechart']});
          google.charts.setOnLoadCallback(function() {
            // Trigger any pending chart renders
            $(document).trigger('googlecharts:loaded');
            // Re-initialize charts after Google Charts loads
            self.initializeCoachingImpactCharts(context);
          });
        };
        script.onerror = function() {
          console.error('Failed to load Google Charts library');
        };
        document.head.appendChild(script);
      } else {
        // Google Charts is already loaded, initialize immediately
        this.initializeCoachingImpactCharts(context);
      }
    },

    /**
     * Initialize coaching impact charts using Google Charts.
     */
    initializeCoachingImpactCharts: function (context) {
      // Use global settings or drupalSettings as fallback
      var chartSettings = window.coachReportingSettings || drupalSettings;
      
      if (typeof google === 'undefined' || !google.charts || !chartSettings.coachingImpact) {
        console.log('Google Charts not ready or no coaching data available');
        console.log('Available settings:', chartSettings);
        return;
      }

      var coachingData = chartSettings.coachingImpact;
      console.log('Initializing charts with data:', coachingData);
      
      // Add a small delay to ensure DOM elements are ready
      setTimeout(function() {
        // Behavioral Progress Chart
        if (coachingData.behavioralChartId && document.getElementById(coachingData.behavioralChartId)) {
          console.log('Creating behavioral chart for element:', coachingData.behavioralChartId);
          
          // Prepare data with monthly periods and color coding
          var behavioralDataArray = [['Month', 'Behavioral Progress', { role: 'style' }]];
          
          // Helper function to get color based on performance
          function getPerformanceColor(value) {
            if (value >= 100) return '#b3e2c7'; // Green - Stars
            if (value >= 60) return '#FFDD7D';  // Yellow - Core
            return '#F95959';                    // Red - Laggards
          }
          
          if (coachingData.monthlyPeriods && coachingData.behavioralData) {
            // Use monthly data if available
            for (var i = 0; i < coachingData.monthlyPeriods.length; i++) {
              var value = coachingData.behavioralData[i] || 0;
              var color = getPerformanceColor(value);
              behavioralDataArray.push([
                coachingData.monthlyPeriods[i],
                value,
                color
              ]);
            }
          } else {
            // Fallback to single period
            var value = coachingData.behavioralPercentage || 0;
            var color = getPerformanceColor(value);
            behavioralDataArray.push([
              coachingData.periodDisplay || 'Current',
              value,
              color
            ]);
          }
          
          var behavioralData = google.visualization.arrayToDataTable(behavioralDataArray);

          var behavioralOptions = {
            title: 'Behavioral Progress',
            titleTextStyle: {
              fontSize: 10,
              color: '#74788d',
              bold: false
            },
            height: 290,
            width: '100%',
            backgroundColor: 'transparent',
            chartArea: {
              left: 60,
              top: 50,
              width: '85%',
              height: '65%',
              right: 20
            },
            hAxis: {
              title: '',
              textStyle: {
                fontSize: 12,
                color: '#373d3f'
              },
              slantedText: false
            },
            vAxis: {
              title: '',
              minValue: 0,
              maxValue: 100,
              textStyle: {
                fontSize: 11,
                color: '#373d3f'
              },
              gridlines: {
                color: '#f1f1f1'
              },
              baselineColor: '#373d3f'
            },
            legend: 'none', // Hide legend since we're using style column for colors
            bar: {
              groupWidth: '70%'
            },
            isStacked: false
          };

          try {
            var behavioralChart = new google.visualization.ColumnChart(
              document.getElementById(coachingData.behavioralChartId)
            );
            behavioralChart.draw(behavioralData, behavioralOptions);
            console.log('Behavioral chart created successfully');
          } catch (error) {
            console.error('Error creating behavioral chart:', error);
          }
        } else {
          console.log('Behavioral chart element not found:', coachingData.behavioralChartId);
        }

        // On-The-Job Progress Chart
        if (coachingData.onjobChartId && document.getElementById(coachingData.onjobChartId)) {
          console.log('Creating on-the-job chart for element:', coachingData.onjobChartId);
          
          // Prepare data with monthly periods and color coding
          var onjobDataArray = [['Month', 'On-The-Job Progress', { role: 'style' }]];
          
          // Helper function to get color based on performance
          function getPerformanceColor(value) {
            if (value >= 100) return '#b3e2c7'; // Green - Stars
            if (value >= 60) return '#FFDD7D';  // Yellow - Core
            return '#F95959';                    // Red - Laggards
          }
          
          if (coachingData.monthlyPeriods && coachingData.onjobData) {
            // Use monthly data if available
            for (var i = 0; i < coachingData.monthlyPeriods.length; i++) {
              var value = coachingData.onjobData[i] || 0;
              var color = getPerformanceColor(value);
              onjobDataArray.push([
                coachingData.monthlyPeriods[i],
                value,
                color
              ]);
            }
          } else {
            // Fallback to single period
            var value = coachingData.onjobPercentage || 0;
            var color = getPerformanceColor(value);
            onjobDataArray.push([
              coachingData.periodDisplay || 'Current',
              value,
              color
            ]);
          }
          
          var onjobData = google.visualization.arrayToDataTable(onjobDataArray);

          var onjobOptions = {
            title: 'On-The-Job Progress',
            titleTextStyle: {
              fontSize: 10,
              color: '#74788d',
              bold: false
            },
            height: 290,
            width: '100%',
            backgroundColor: 'transparent',
            chartArea: {
              left: 60,
              top: 50,
              width: '85%',
              height: '65%',
              right: 20
            },
            hAxis: {
              title: '',
              textStyle: {
                fontSize: 12,
                color: '#373d3f'
              },
              slantedText: false
            },
            vAxis: {
              title: '',
              minValue: 0,
              maxValue: 100,
              textStyle: {
                fontSize: 11,
                color: '#373d3f'
              },
              gridlines: {
                color: '#f1f1f1'
              },
              baselineColor: '#373d3f'
            },
            legend: 'none', // Hide legend since we're using style column for colors
            bar: {
              groupWidth: '70%'
            },
            isStacked: false
          };

          try {
            var onjobChart = new google.visualization.ColumnChart(
              document.getElementById(coachingData.onjobChartId)
            );
            onjobChart.draw(onjobData, onjobOptions);
            console.log('On-the-job chart created successfully');
          } catch (error) {
            console.error('Error creating on-the-job chart:', error);
          }
        } else {
          console.log('On-the-job chart element not found:', coachingData.onjobChartId);
        }
      }, 100); // 100ms delay to ensure DOM is ready
    }
  };

})(jQuery, Drupal, drupalSettings);