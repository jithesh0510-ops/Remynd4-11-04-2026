/**
 * @file
 * Performance Dashboard JavaScript with Google Charts
 * 
 * Chart Color: #86f1ff (Cyan)
 * Status Badge Colors (matching report_result.css):
 * - Completed:   rgb(179, 226, 199) - Green
 * - In Progress: rgb(255, 221, 125) - Yellow
 * - Not Started: rgb(249, 89, 89)   - Red
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.performanceDashboard = {
    attach: function (context, settings) {
      // Prevent Select2 from initializing on dashboard selects
      once('dashboard-select-prevent', '.dashboard-select', context).forEach(function(element) {
        // Remove any Select2 initialization
        if (jQuery && jQuery(element).data('select2')) {
          jQuery(element).select2('destroy');
        }
        // Add attribute to prevent future initialization
        element.setAttribute('data-select2-exclude', 'true');
      });

      // Load Google Charts library
      if (typeof google === 'undefined' || typeof google.charts === 'undefined') {
        // Load Google Charts API
        const script = document.createElement('script');
        script.src = 'https://www.gstatic.com/charts/loader.js';
        script.onload = function() {
          google.charts.load('current', {'packages':['corechart', 'line', 'bar']});
          google.charts.setOnLoadCallback(function() {
            initializeCharts(settings);
          });
        };
        document.head.appendChild(script);
      } else {
        google.charts.setOnLoadCallback(function() {
          initializeCharts(settings);
        });
      }

      // Function to load coaches for a company
      function loadCoachesForCompany(companyId) {
        const coachSelect = document.getElementById('coach-select');
        
        if (!companyId) {
          coachSelect.disabled = true;
          coachSelect.innerHTML = '<option value="">-- Select Coach --</option>';
          return;
        }
        
        // Enable coach dropdown and load coaches via AJAX
        coachSelect.disabled = true;
        coachSelect.innerHTML = '<option value="">Loading...</option>';
        
        // AJAX call to get coaches
        fetch('/reports/performance-dashboard/ajax/coaches?company_uid=' + companyId)
          .then(response => response.json())
          .then(data => {
            coachSelect.innerHTML = '<option value="">-- Select Coach --</option>';
            
            if (data.coaches && Object.keys(data.coaches).length > 0) {
              Object.keys(data.coaches).forEach(function(uid) {
                const option = document.createElement('option');
                option.value = uid;
                option.textContent = data.coaches[uid];
                coachSelect.appendChild(option);
              });
              coachSelect.disabled = false;
              
              // If coach is pre-selected (from URL), set it
              const urlParams = new URLSearchParams(window.location.search);
              const selectedCoach = urlParams.get('coach');
              if (selectedCoach && coachSelect.querySelector('option[value="' + selectedCoach + '"]')) {
                coachSelect.value = selectedCoach;
              }
            } else {
              coachSelect.innerHTML = '<option value="">No coaches found</option>';
              coachSelect.disabled = true;
            }
          })
          .catch(error => {
            console.error('Error loading coaches:', error);
            coachSelect.innerHTML = '<option value="">Error loading coaches</option>';
            coachSelect.disabled = true;
          });
      }

      // Company select change - AJAX load coaches
      once('company-select-change', '#company-select', context).forEach(function(element) {
        element.addEventListener('change', function() {
          loadCoachesForCompany(this.value);
        });
        
        // On page load, if company is already selected, load coaches
        if (element.value) {
          loadCoachesForCompany(element.value);
        }
      });

      // Form submission handler - AJAX
      once('filter-form-submit', '#dashboardFilterForm', context).forEach(function(form) {
        form.addEventListener('submit', function(e) {
          e.preventDefault();
          
          const companySelect = document.getElementById('company-select');
          const coachSelect = document.getElementById('coach-select');
          
          const company = companySelect.value;
          const coach = coachSelect.value;
          
          if (!company || !coach) {
            alert('Please select both Company and Coach');
            return;
          }
          
          // Build URL with parameters and reload
          const url = new URL(window.location.pathname, window.location.origin);
          url.searchParams.set('company', company);
          url.searchParams.set('coach', coach);
          
          // Redirect to load results
          window.location.href = url.toString();
        });
      });

      // Accordion functionality - Fixed implementation
      once('accordion-toggle', '.accordion-button', context).forEach(function(button) {
        // Initialize state based on current classes
        const targetId = button.getAttribute('data-bs-target');
        if (targetId) {
          const target = document.querySelector(targetId);
          if (target) {
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            if (isExpanded && !target.classList.contains('show')) {
              target.classList.add('show');
              target.classList.remove('hide');
              target.setAttribute('aria-hidden', 'false');
            }
            if (!isExpanded) {
              target.classList.remove('show');
              target.classList.add('hide');
              target.setAttribute('aria-hidden', 'true');
              button.classList.add('collapsed');
            }
          }
        }

        button.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          // Get target collapse element
          const targetId = this.getAttribute('data-bs-target');
          if (!targetId) return;
          
          const target = document.querySelector(targetId);
          if (!target) return;
          
          // Check if currently expanded
          const isCurrentlyExpanded = target.classList.contains('show');
          
          // Close all other accordions in the same parent (optional - remove if you want multiple open)
          const accordionParent = this.closest('.accordion');
          if (accordionParent) {
            const allButtons = accordionParent.querySelectorAll('.accordion-button');
            const allCollapses = accordionParent.querySelectorAll('.accordion-collapse');
            
            allButtons.forEach(function(btn) {
              if (btn !== button) {
                btn.classList.add('collapsed');
                btn.setAttribute('aria-expanded', 'false');
              }
            });
            
            allCollapses.forEach(function(collapse) {
              if (collapse !== target) {
                collapse.classList.remove('show');
                collapse.classList.add('hide');
                collapse.setAttribute('aria-hidden', 'true');
              }
            });
          }
          
          // Toggle current accordion
          if (isCurrentlyExpanded) {
            // Collapse
            this.classList.add('collapsed');
            this.setAttribute('aria-expanded', 'false');
            target.classList.remove('show');
            target.classList.add('hide');
            target.setAttribute('aria-hidden', 'true');
          } else {
            // Expand
            this.classList.remove('collapsed');
            this.setAttribute('aria-expanded', 'true');
            target.classList.remove('hide');
            target.classList.add('show');
            target.setAttribute('aria-hidden', 'false');
          }
        });
      });

      // Pagination click handler - AJAX
      once('pagination-click', '.pagination-link', context).forEach(function(link) {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          
          const pageItem = this.closest('.page-item');
          if (pageItem && pageItem.classList.contains('disabled')) {
            return;
          }
          
          const page = parseInt(this.getAttribute('data-page'));
          if (isNaN(page) || page < 0) {
            return;
          }
          
          // Get program ID from closest accordion
          const accordionBody = this.closest('.accordion-body');
          if (!accordionBody) return;
          
          const accordionItem = accordionBody.closest('.accordion-item');
          if (!accordionItem) return;
          
          const programId = accordionItem.querySelector('[id^="collapse-"]').id.replace('collapse-', '');
          
          // Load page data via AJAX
          loadUserReportPage(programId, page);
        });
      });

      // Date range toggle - Per program
      once('date-range-toggle', '.btn-date-range', context).forEach(function(element) {
        element.addEventListener('click', function() {
          const programId = this.getAttribute('data-program-id');
          const dateRangeRow = document.getElementById('dateRangeRow_' + programId);
          if (dateRangeRow) {
            dateRangeRow.style.display = dateRangeRow.style.display === 'none' ? 'flex' : 'none';
          }
        });
      });

      // Date submit button - AJAX filter
      once('date-submit', '.date-submit-btn', context).forEach(function(button) {
        button.addEventListener('click', function() {
          const programId = this.getAttribute('data-program-id');
          const fromInput = document.getElementById('dateFrom_' + programId);
          const toInput = document.getElementById('dateTo_' + programId);
          
          if (!fromInput || !toInput) return;
          
          const fromDate = fromInput.value;
          const toDate = toInput.value;
          
          if (!fromDate || !toDate) {
            alert('Please select both From and To dates');
            return;
          }
          
          if (fromDate > toDate) {
            alert('From date cannot be after To date');
            return;
          }
          
          // Get company and coach from Drupal settings
          const companyUid = settings.performanceDashboard?.companyUid;
          const coachUid = settings.performanceDashboard?.coachUid;
          
          if (!companyUid || !coachUid) {
            alert('Missing company or coach information');
            return;
          }
          
          // Show loading state
          showLoadingState(programId);
          
          // AJAX call to get filtered data
          const url = '/reports/performance-dashboard/ajax/program-data?' + 
                     'program_nid=' + programId +
                     '&company_uid=' + companyUid +
                     '&coach_uid=' + coachUid +
                     '&from_date=' + fromDate +
                     '&to_date=' + toDate;
          
          console.log('🔄 Fetching filtered data from:', url);
          
          fetch(url)
            .then(response => response.json())
            .then(data => {
              console.log('📦 AJAX Response received:', data);
              
              if (data.success) {
                console.log('✅ Success! Updating dashboard...');
                console.log('  - Chart data:', data.data?.chart_data);
                console.log('  - Metrics:', data.data?.metrics);
                updateDashboard(programId, data.data);
              } else {
                console.error('❌ AJAX returned error:', data.message);
                alert('Error loading data: ' + (data.message || 'Unknown error'));
              }
              hideLoadingState(programId);
            })
            .catch(error => {
              console.error('❌ AJAX Error:', error);
              alert('Error loading filtered data');
              hideLoadingState(programId);
            });
        });
      });

      // Date reset button - Load last 12 months
      once('date-reset', '.date-reset-btn', context).forEach(function(button) {
        button.addEventListener('click', function() {
          const programId = this.getAttribute('data-program-id');
          const fromInput = document.getElementById('dateFrom_' + programId);
          const toInput = document.getElementById('dateTo_' + programId);
          
          if (fromInput) fromInput.value = '';
          if (toInput) toInput.value = '';
          
          // Get company and coach from Drupal settings
          const companyUid = settings.performanceDashboard?.companyUid;
          const coachUid = settings.performanceDashboard?.coachUid;
          
          if (!companyUid || !coachUid) return;
          
          // Show loading state
          showLoadingState(programId);
          
          // AJAX call to get default data (last 12 months)
          const url = '/reports/performance-dashboard/ajax/program-data?' + 
                     'program_nid=' + programId +
                     '&company_uid=' + companyUid +
                     '&coach_uid=' + coachUid;
          
          fetch(url)
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                updateDashboard(programId, data.data);
              }
              hideLoadingState(programId);
            })
            .catch(error => {
              console.error('Error:', error);
              hideLoadingState(programId);
            });
        });
      });
    }
  };

  /**
   * Initialize all Google Charts - Find all chart containers and render with their data
   */
  function initializeCharts(settings) {
    // Get all chart containers on the page
    const chartContainers = document.querySelectorAll('.chart-container[data-chart-type]');
    
    console.log('🎨 Initializing charts...');
    console.log('  - Found chart containers:', chartContainers.length);
    
    // Try to get data from drupalSettings first, then fall back to settings parameter
    const drupalData = (typeof drupalSettings !== 'undefined' && drupalSettings.performanceDashboard) 
      ? drupalSettings.performanceDashboard 
      : (settings.performanceDashboard || null);
    
    console.log('  - drupalSettings available:', typeof drupalSettings !== 'undefined');
    console.log('  - Performance Dashboard data:', drupalData);
    
    if (chartContainers.length === 0) {
      console.warn('⚠️ No chart containers found on page');
      return;
    }
    
    if (!drupalData) {
      console.error('❌ No performance dashboard data found in drupalSettings or settings!');
      console.log('  - Available drupalSettings keys:', typeof drupalSettings !== 'undefined' ? Object.keys(drupalSettings) : 'undefined');
    }
    
    chartContainers.forEach(function(container) {
      const chartId = container.id;
      const chartType = container.getAttribute('data-chart-type');
      
      console.log(`\n📊 Processing chart: ${chartId} (type: ${chartType})`);
      
      // Extract program ID from chart ID (e.g., "overviewChart_338" -> "338")
      const programId = chartId.match(/_(\d+)$/)?.[1];
      console.log('  - Program ID extracted:', programId || 'none');
      
      let chartData = null;
      let dataSource = 'none';
      
      // Priority 1: Try to get data from drupalSettings for specific program
      if (programId && drupalData?.programs?.[programId]) {
        chartData = drupalData.programs[programId];
        dataSource = 'drupalSettings.programs';
        console.log('  ✅ Using REAL database data for program ' + programId);
        console.log('  - Data source: drupalSettings.performanceDashboard.programs[\'' + programId + '\']');
        console.log('  - Data structure:', Object.keys(chartData));
      } 
      // Priority 2: Try general chart data
      else if (drupalData?.chartData) {
        chartData = drupalData.chartData;
        dataSource = 'drupalSettings.chartData';
        console.log('  ✅ Using general chart data');
        console.log('  - Data source: drupalSettings.performanceDashboard.chartData');
      } 
      // Priority 3: Try legacy Drupal.settings
      else if (typeof Drupal !== 'undefined' && Drupal.settings?.performanceDashboard?.programs?.[programId]) {
        chartData = Drupal.settings.performanceDashboard.programs[programId];
        dataSource = 'Drupal.settings (legacy)';
        console.log('  ⚠️ Using legacy Drupal.settings data for program ' + programId);
      }
      // NO FALLBACK - Force error to be visible
      else {
        console.error('  ❌❌❌ NO DATA FOUND ❌❌❌');
        console.error('  - Program ID:', programId);
        console.error('  - drupalSettings exists?', typeof drupalSettings !== 'undefined');
        console.error('  - drupalSettings.performanceDashboard?', drupalData ? 'yes' : 'no');
        console.error('  - programs?', drupalData?.programs ? Object.keys(drupalData.programs) : 'no');
        console.error('  ');
        console.error('  🔍 THIS IS WHY CHARTS SHOW SAMPLE DATA!');
        console.error('  ');
        console.error('  Solutions:');
        console.error('  1. Make sure you selected company AND coach');
        console.error('  2. Make sure you clicked "View Dashboard"');
        console.error('  3. Clear cache: ddev drush cr');
        console.error('  4. Hard refresh browser 3 times: Cmd+Shift+R');
        
        // Call function that shows error charts
        chartData = getDefaultChartData();
        dataSource = 'ERROR - fallback';
      }
      
      // Validate chart data before drawing
      if (!chartData) {
        console.error(`  ❌ No chart data available for ${chartId}`);
        return;
      }
      
      // Draw the appropriate chart type
      if (chartType === 'overview' && chartData.overview && Array.isArray(chartData.overview)) {
        if (chartData.overview.length === 0) {
          console.log(`  ℹ️ No data available for overview chart`);
          showNoDataMessage(chartId, 'No coaching data available for the selected date range');
        } else {
          console.log(`  🎨 Drawing overview chart with ${chartData.overview.length - 1} data points`);
          console.log('  - Data preview:', chartData.overview.slice(0, 3));
          drawLineChart(chartId, chartData.overview, 'Average Score', '#86f1ff');
        }
      } else if (chartType === 'competency' && chartData.competency && Array.isArray(chartData.competency)) {
        if (chartData.competency.length === 0) {
          console.log(`  ℹ️ No data available for competency chart`);
          showNoDataMessage(chartId, 'No competency data available for the selected date range');
        } else {
          console.log(`  🎨 Drawing competency chart with ${chartData.competency.length - 1} data points`);
          console.log('  - Data preview:', chartData.competency.slice(0, 3));
          drawLineChart(chartId, chartData.competency, 'Score', '#86f1ff');
        }
      } else if (chartType === 'department' && chartData.department && Array.isArray(chartData.department)) {
        if (chartData.department.length === 0) {
          console.log(`  ℹ️ No data available for Stars/Core/Laggards chart`);
          showNoDataMessage(chartId, 'No on-the-job performance data available for the selected date range');
        } else {
          console.log(`  🎨 Drawing Stars/Core/Laggards chart with ${chartData.department.length - 1} categories`);
          console.log('  - Data preview:', chartData.department);
          drawBarChart(chartId, chartData.department, 'Performance %', '#86f1ff');
        }
      } else if (chartType === 'sessions' && chartData.sessions && Array.isArray(chartData.sessions)) {
        if (chartData.sessions.length === 0) {
          console.log(`  ℹ️ No data available for sessions chart`);
          showNoDataMessage(chartId, 'No coaching sessions found for the selected date range');
        } else {
          console.log(`  🎨 Drawing sessions chart with ${chartData.sessions.length - 1} data points`);
          console.log('  - Data preview:', chartData.sessions.slice(0, 3));
          drawBarChart(chartId, chartData.sessions, 'Sessions', '#86f1ff');
        }
      } else {
        console.error(`  ❌ Chart data missing or invalid for ${chartType} in ${chartId}`);
        console.log('  - Available data keys:', chartData ? Object.keys(chartData) : 'null');
        showNoDataMessage(chartId, 'Chart data is not available');
      }
    });
    
    console.log('\n✅ Chart initialization complete!');
  }


  /**
   * Draw a line chart
   */
  function drawLineChart(elementId, chartData, seriesName, color) {
    const data = google.visualization.arrayToDataTable(chartData);

    const options = {
      title: '',
      curveType: 'function',
      legend: { position: 'none' },
      colors: [color],
      backgroundColor: 'transparent',
      chartArea: {
        width: '85%',
        height: '70%'
      },
      hAxis: {
        textStyle: { color: '#666' }
      },
      vAxis: {
        minValue: 0,
        maxValue: 100,
        textStyle: { color: '#666' }
      },
      lineWidth: 3,
      pointSize: 5,
      areaOpacity: 0.1
    };

    const chart = new google.visualization.LineChart(document.getElementById(elementId));
    chart.draw(data, options);
  }

  /**
   * Draw a bar chart
   */
  function drawBarChart(elementId, chartData, seriesName, color) {
    const data = google.visualization.arrayToDataTable(chartData);

    const options = {
      title: '',
      legend: { position: 'none' },
      colors: [color],
      backgroundColor: 'transparent',
      chartArea: {
        width: '75%',
        height: '70%'
      },
      hAxis: {
        textStyle: { color: '#666' }
      },
      vAxis: {
        minValue: 0,
        maxValue: 100,
        textStyle: { color: '#666' }
      }
    };

    const chart = new google.visualization.ColumnChart(document.getElementById(elementId));
    chart.draw(data, options);
  }

  /**
   * Show "No data available" message in chart container
   */
  function showNoDataMessage(elementId, message) {
    const container = document.getElementById(elementId);
    if (container) {
      container.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; min-height: 200px; color: #999; font-size: 14px; text-align: center; padding: 20px;">' +
        '<div>' +
        '<svg style="width: 48px; height: 48px; margin-bottom: 12px; opacity: 0.5;" fill="currentColor" viewBox="0 0 20 20">' +
        '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>' +
        '</svg>' +
        '<div>' + message + '</div>' +
        '</div>' +
        '</div>';
    }
  }

  /**
   * Get default chart data if not provided by Drupal settings
   * REMOVED - DO NOT USE SAMPLE DATA!
   * If this function is called, it means real data is not being passed from PHP.
   */
  function getDefaultChartData() {
    console.error('❌❌❌ CRITICAL ERROR ❌❌❌');
    console.error('getDefaultChartData() was called - this should NEVER happen!');
    console.error('This means real data is NOT being passed from PHP to JavaScript');
    console.error('');
    console.error('Troubleshooting steps:');
    console.error('1. Check if drupalSettings.performanceDashboard exists');
    console.error('2. Check Drupal logs: ddev drush watchdog:show --type=coach_reporting_system');
    console.error('3. Verify database has coaching sessions');
    console.error('4. Clear cache: ddev drush cr');
    console.error('5. Hard refresh browser 3 times: Cmd+Shift+R');
    
    // Return EMPTY arrays only - no error values as requested
    return {
      overview: [],
      competency: [],
      department: [],
      sessions: []
    };
  }

  /**
   * Load user report page via AJAX (placeholder for future implementation)
   */
  function loadUserReportPage(programId, page) {
    console.log('Loading page ' + page + ' for program ' + programId);
    // TODO: Implement AJAX pagination when backend endpoint is ready
    // For now, this is a placeholder
    alert('AJAX pagination for page ' + (page + 1) + ' - Backend endpoint to be implemented');
  }

  /**
   * Show loading state for a program
   */
  function showLoadingState(programId) {
    const accordionBody = document.querySelector('#collapse-' + programId + ' .accordion-body');
    if (!accordionBody) return;
    
    // Add loading overlay
    let overlay = accordionBody.querySelector('.loading-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'loading-overlay';
      overlay.innerHTML = '<div class="loading-spinner"></div><p>Loading data...</p>';
      accordionBody.style.position = 'relative';
      accordionBody.appendChild(overlay);
    }
    overlay.style.display = 'flex';
  }

  /**
   * Hide loading state for a program
   */
  function hideLoadingState(programId) {
    const accordionBody = document.querySelector('#collapse-' + programId + ' .accordion-body');
    if (!accordionBody) return;
    
    const overlay = accordionBody.querySelector('.loading-overlay');
    if (overlay) {
      overlay.style.display = 'none';
    }
  }

  /**
   * Update dashboard with new data from AJAX
   */
  function updateDashboard(programId, data) {
    console.log('🔄 updateDashboard called');
    console.log('  - Program ID:', programId);
    console.log('  - Data:', data);
    
    if (!data) {
      console.error('❌ No data provided to updateDashboard');
      return;
    }
    
    // Update metrics
    if (data.metrics) {
      console.log('📊 Updating metrics...');
      updateMetrics(programId, data.metrics);
    } else {
      console.warn('⚠️ No metrics data');
    }
    
    // Update charts
    if (data.chart_data) {
      console.log('📈 Updating charts...');
      updateCharts(programId, data.chart_data);
    } else {
      console.warn('⚠️ No chart_data');
    }
    
    // Update action report
    if (data.action_report) {
      console.log('📋 Updating action report...');
      updateActionReport(programId, data.action_report);
    } else {
      console.warn('⚠️ No action_report');
    }
    
    // Update users report
    if (data.users_report) {
      console.log('👥 Updating users report...');
      updateUsersReport(programId, data.users_report);
    } else {
      console.warn('⚠️ No users_report');
    }
    
    console.log('✅ updateDashboard complete');
  }

  /**
   * Update metric cards
   */
  function updateMetrics(programId, metrics) {
    const accordionBody = document.querySelector('#collapse-' + programId);
    if (!accordionBody) return;
    
    const metricCards = accordionBody.querySelectorAll('.metric-card');
    let index = 0;
    const metricKeys = ['users_coached', 'coaching_sessions', 'behavioral_progress', 'on_job_progress', 'roi'];
    
    metricCards.forEach(function(card) {
      if (index < metricKeys.length) {
        const metric = metrics[metricKeys[index]];
        if (metric) {
          const valueEl = card.querySelector('.metric-value');
          const changeEl = card.querySelector('.metric-change');
          
          if (valueEl) valueEl.textContent = metric.value;
          if (changeEl) changeEl.textContent = metric.change;
        }
      }
      index++;
    });
  }

  /**
   * Update all charts with new data
   */
  function updateCharts(programId, chartData) {
    console.log('🎨 updateCharts called for program:', programId);
    console.log('  - Chart data received:', chartData);
    
    if (typeof google === 'undefined' || typeof google.charts === 'undefined') {
      console.error('❌ Google Charts not loaded!');
      return;
    }
    
    // Update each chart
    const chartTypes = ['overview', 'competency', 'department', 'sessions'];
    const chartNames = ['overviewChart', 'competencyChart', 'departmentChart', 'sessionsChart'];
    
    chartTypes.forEach(function(type, index) {
      const chartId = chartNames[index] + '_' + programId;
      console.log(`  📊 Processing ${type} chart (${chartId})`);
      
      if (chartData[type]) {
        console.log(`    - Data exists:`, chartData[type].length, 'rows');
        const chartElement = document.getElementById(chartId);
        
        if (chartElement) {
          console.log(`    - Element found, drawing chart...`);
          
          // Handle empty data
          if (Array.isArray(chartData[type]) && chartData[type].length === 0) {
            console.log(`    - Empty data, showing message`);
            showNoDataMessage(chartId, `No ${type} data available for the selected date range`);
          } else {
            // Draw chart with data
            if (type === 'overview' || type === 'competency') {
              drawLineChart(chartId, chartData[type], type === 'overview' ? 'Average Score' : 'Score', '#86f1ff');
            } else if (type === 'department') {
              drawBarChart(chartId, chartData[type], 'Performance %', '#86f1ff');
            } else {
              drawBarChart(chartId, chartData[type], 'Sessions', '#86f1ff');
            }
            console.log(`    ✅ Chart drawn successfully`);
          }
        } else {
          console.error(`    ❌ Element not found: ${chartId}`);
        }
      } else {
        console.warn(`    ⚠️ No data for ${type} chart`);
      }
    });
    
    console.log('✅ updateCharts complete');
  }

  /**
   * Update action report table
   */
  function updateActionReport(programId, actionReport) {
    const accordionBody = document.querySelector('#collapse-' + programId);
    if (!accordionBody) return;
    
    const tbody = accordionBody.querySelector('.data-table tbody');
    if (!tbody || !Array.isArray(actionReport)) return;
    
    tbody.innerHTML = '';
    
    actionReport.forEach(function(item) {
      let statusClass = '';
      if (item.status === 'Completed') statusClass = 'status-completed';
      else if (item.status === 'In Progress') statusClass = 'status-in-progress';
      else if (item.status === 'Not Started') statusClass = 'status-not-started';
      
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${item.competency}</td>
        <td><span class="badge ${statusClass}">${item.status}</span></td>
        <td>${item.due_date}</td>
        <td>
          <div class="progress-container">
            <div class="progress-bar">
              <div class="progress-fill" style="width: ${item.progress}%"></div>
            </div>
            <span class="progress-value">${item.progress}</span>
          </div>
        </td>
      `;
      tbody.appendChild(row);
    });
  }

  /**
   * Update users report table
   */
  function updateUsersReport(programId, usersReport) {
    const accordionBody = document.querySelector('#collapse-' + programId);
    if (!accordionBody) return;
    
    const tbody = accordionBody.querySelector('.users-report-tbody');
    if (!tbody) return;
    
    const users = usersReport.data || usersReport;
    if (!Array.isArray(users)) return;
    
    tbody.innerHTML = '';
    
    users.forEach(function(user) {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${user.number}</td>
        <td>${user.name}</td>
        <td>${user.comparison}</td>
        <td>${user.coach}</td>
        <td>
          <div class="progress-container">
            <div class="progress-bar">
              <div class="progress-fill" style="width: ${user.latest_performance}%"></div>
            </div>
            <span class="progress-value">${user.latest_performance}</span>
          </div>
        </td>
        <td>
          <div class="progress-container">
            <div class="progress-bar">
              <div class="progress-fill" style="width: ${user.previous_performance}%"></div>
            </div>
            <span class="progress-value">${user.previous_performance}</span>
          </div>
        </td>
        <td>${user.next_session}</td>
        <td>${user.last_session}</td>
        <td>${user.first_session}</td>
      `;
      tbody.appendChild(row);
    });
    
    // Update pagination if exists
    if (usersReport.pagination) {
      updatePagination(programId, usersReport.pagination);
    }
  }

  /**
   * Update pagination
   */
  function updatePagination(programId, pagination) {
    const accordionBody = document.querySelector('#collapse-' + programId);
    if (!accordionBody) return;
    
    const paginationContainer = accordionBody.querySelector('.pagination');
    if (!paginationContainer) return;
    
    // Rebuild pagination HTML
    const currentPage = pagination.current_page;
    const totalPages = pagination.total_pages;
    
    let paginationHTML = '<nav><ul class="pagination-list">';
    
    // Previous button
    paginationHTML += `
      <li class="page-item ${currentPage === 0 ? 'disabled' : ''}">
        <a class="page-link pagination-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="24" fill="currentColor" viewBox="0 0 256 256">
            <path d="M165.66,202.34a8,8,0,0,1-11.32,11.32l-80-80a8,8,0,0,1,0-11.32l80-80a8,8,0,0,1,11.32,11.32L91.31,128Z"/>
          </svg>
        </a>
      </li>
    `;
    
    // Page numbers
    for (let i = 0; i < totalPages; i++) {
      paginationHTML += `
        <li class="page-item ${i === currentPage ? 'active' : ''}">
          <a class="page-link pagination-link" href="#" data-page="${i}">${i + 1}</a>
        </li>
      `;
    }
    
    // Next button
    paginationHTML += `
      <li class="page-item ${currentPage >= totalPages - 1 ? 'disabled' : ''}">
        <a class="page-link pagination-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="24" fill="currentColor" viewBox="0 0 256 256">
            <path d="M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z"/>
          </svg>
        </a>
      </li>
    `;
    
    paginationHTML += '</ul></nav>';
    paginationContainer.innerHTML = paginationHTML;
    
    // Re-attach event listeners to new pagination links
    Drupal.attachBehaviors(paginationContainer);
  }

  // Handle window resize to redraw charts
  window.addEventListener('resize', function() {
    if (typeof google !== 'undefined' && typeof google.charts !== 'undefined') {
      const settings = (typeof drupalSettings !== 'undefined') ? drupalSettings : (Drupal.settings || {});
      initializeCharts(settings);
    }
  });

  // Redraw charts when accordion is shown
  document.addEventListener('click', function(e) {
    const accordionButton = e.target.closest('.accordion-button');
    if (accordionButton) {
      // Wait for accordion to fully expand
      setTimeout(function() {
        if (typeof google !== 'undefined' && typeof google.charts !== 'undefined') {
          // Get data from drupalSettings first, then Drupal.settings
          const drupalData = (typeof drupalSettings !== 'undefined' && drupalSettings.performanceDashboard) 
            ? drupalSettings.performanceDashboard 
            : ((Drupal.settings && Drupal.settings.performanceDashboard) || null);
          
          console.log('♻️ Redrawing charts on accordion open');
          
          // Get the target accordion body
          const targetId = accordionButton.getAttribute('data-bs-target');
          if (targetId) {
            const targetBody = document.querySelector(targetId);
            if (targetBody && targetBody.classList.contains('show')) {
              // Only redraw charts in the expanded accordion
              const chartContainers = targetBody.querySelectorAll('.chart-container[data-chart-type]');
              console.log('  - Charts to redraw:', chartContainers.length);
              
              chartContainers.forEach(function(container) {
                const chartId = container.id;
                const chartType = container.getAttribute('data-chart-type');
                const programId = chartId.match(/_(\d+)$/)?.[1];
                
                let chartData = null;
                
                // Try to get real data first
                if (programId && drupalData?.programs?.[programId]) {
                  chartData = drupalData.programs[programId];
                  console.log(`  ✅ Redrawing ${chartType} with REAL data for program ${programId}`);
                } else if (drupalData?.chartData) {
                  chartData = drupalData.chartData;
                  console.log(`  ✅ Redrawing ${chartType} with general data`);
                } else {
                  chartData = getDefaultChartData();
                  console.warn(`  ⚠️ Redrawing ${chartType} with SAMPLE data`);
                }
                
                // Redraw the chart
                if (chartType === 'overview' && chartData.overview) {
                  drawLineChart(chartId, chartData.overview, 'Average Score', '#86f1ff');
                } else if (chartType === 'competency' && chartData.competency) {
                  drawLineChart(chartId, chartData.competency, 'Score', '#86f1ff');
                } else if (chartType === 'department' && chartData.department) {
                  drawBarChart(chartId, chartData.department, 'Score', '#86f1ff');
                } else if (chartType === 'sessions' && chartData.sessions) {
                  drawBarChart(chartId, chartData.sessions, 'Sessions', '#86f1ff');
                }
              });
            }
          }
        }
      }, 400); // Wait for accordion animation
    }
  });

})(Drupal, once);

