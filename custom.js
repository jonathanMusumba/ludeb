$(document).ready(function() {
    // Fetch and display the board name and logged in user
    $('#boardName').text('Board Name'); // Fetch dynamically from your backend
    $('#loggedInUser').text('Username'); // Fetch dynamically from your session

    // Fetch current exam year and populate dropdown
    $.ajax({
        url: 'get_exam_years.php', // Endpoint to get exam years
        method: 'GET',
        success: function(data) {
            $('#currentExamYear').text(data.currentYear);
            data.years.forEach(function(year) {
                $('#examYearOptions').append(`<li><a class="dropdown-item" href="#">${year}</a></li>`);
            });
        }
    });

    // Load dashboard data
    function loadDashboardData() {
        $.ajax({
            url: 'get_dashboard_data.php', // Endpoint to get dashboard data
            method: 'GET',
            success: function(data) {
                $('#totalSchools').text(data.totalSchools);
                $('#totalCandidates').text(data.totalCandidates);
                $('#declaredMarksSchools').text(data.declaredMarksSchools);
                $('#missingSchools').text(data.missingSchools);

                // Load the line chart
                loadLineChart(data.chartData);
            }
        });
    }

    function loadLineChart(chartData) {
        const ctx = document.getElementById('dailyLineChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Marks Entry',
                    data: chartData.data,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Handle sidebar navigation
    $('#dashboardLink').click(function() {
        // Load the dashboard content
        loadDashboardData();
        $('#breadcrumbs').html('<li class="breadcrumb-item active" aria-current="page">Dashboard</li>');
    });

    $('#schoolsLink').click(function() {
        // Load schools content
        $('#mainContent').load('list_schools.php', function() {
            $('#breadcrumbs').html('<li class="breadcrumb-item"><a href="#">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">Schools</li>');
        });
    });

    $('#resultsLink').click(function() {
        // Load results capture content
        $('#mainContent').load('capture_results.php', function() {
            $('#breadcrumbs').html('<li class="breadcrumb-item"><a href="#">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">Capture Results</li>');
        });
    });

    // Initial load
    loadDashboardData();
});