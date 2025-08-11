<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['school_id'])) {
    header('Location: ../login.php');
    exit;
}

// Set page title
$pageTitle = 'Results';

// Get current exam year or selected year
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : null;
if (!$selectedYear) {
    $result = $conn->query("SELECT exam_year FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC LIMIT 1");
    $selectedYear = $result ? $result->fetch_row()[0] : date('Y');
}

// Get exam year ID
$stmt = $conn->prepare("SELECT id FROM exam_years WHERE exam_year = ? AND status = 'Active'");
$stmt->bind_param("i", $selectedYear);
$stmt->execute();
$examYearResult = $stmt->get_result();
$examYearData = $examYearResult->fetch_assoc();
$examYearId = $examYearData ? $examYearData['id'] : 1;
$stmt->close();

// Get available exam years for dropdown
$availableYears = [];
$result = $conn->query("SELECT exam_year FROM exam_years WHERE status = 'Active' ORDER BY exam_year DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $availableYears[] = $row['exam_year'];
    }
}

// Start output buffering for content
ob_start();
?>

<div class="results-page">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" id="statsCards">
        <!-- Loading state -->
        <div class="col-span-full">
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <i class="fas fa-spinner fa-spin text-blue-600 text-2xl mb-2"></i>
                <p class="text-gray-600">Loading statistics...</p>
            </div>
        </div>
    </div>

    <!-- Results Actions -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Results Management</h3>
            <p class="text-sm text-gray-600 mt-1">Download and manage examination results</p>
        </div>
        <div class="p-6">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Download Results</label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <button onclick="downloadResults('pdf')" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded flex items-center justify-center transition-colors">
                            <i class="fas fa-file-pdf mr-2"></i>
                            Download PDF
                        </button>
                        <button onclick="downloadResults('excel')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded flex items-center justify-center transition-colors">
                            <i class="fas fa-file-excel mr-2"></i>
                            Download Excel
                        </button>
                    </div>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quick Actions</label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <button onclick="refreshStats()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center justify-center transition-colors">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Refresh Stats
                        </button>
                        <a href="./uploads.php?year=<?php echo $selectedYear; ?>" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded flex items-center justify-center transition-colors text-center">
                            <i class="fas fa-upload mr-2"></i>
                            Upload Results
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Results Table -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Results Overview</h3>
            <p class="text-sm text-gray-600 mt-1">Examination results for <?php echo $selectedYear; ?></p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Division</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Candidates</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="resultsTableBody">
                    <!-- Will be populated by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let currentExamYearId = <?php echo $examYearId; ?>;
let currentSchoolId = <?php echo $_SESSION['school_id']; ?>;

// Load statistics when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadResultsStats();
    
    // Update stats when year changes
    const yearSelect = document.querySelector('select[name="year"]');
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            const selectedYear = this.value;
            // Get exam year ID for the selected year
            fetch('get_exam_year_id.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ year: selectedYear })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentExamYearId = data.exam_year_id;
                    loadResultsStats();
                }
            })
            .catch(error => {
                console.error('Error getting exam year ID:', error);
            });
        });
    }
});

function loadResultsStats() {
    fetch('get_results_stats.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            school_id: currentSchoolId,
            exam_year_id: currentExamYearId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateStatsCards(data.stats);
            updateResultsTable(data.stats);
        } else {
            showError('Failed to load statistics: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error loading stats:', error);
        showError('Failed to load statistics');
    });
}

function updateStatsCards(stats) {
    const cardsContainer = document.getElementById('statsCards');
    const totalCandidates = stats.total_candidates || 0;
    
    cardsContainer.innerHTML = `
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-2xl font-bold text-gray-900">${totalCandidates}</h4>
                    <p class="text-sm text-gray-600">Total Candidates</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-2xl font-bold text-gray-900">${stats.processed_results || 0}</h4>
                    <p class="text-sm text-gray-600">Processed Results</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-trophy text-xl"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-2xl font-bold text-gray-900">${stats.div1 || 0}</h4>
                    <p class="text-sm text-gray-600">Division 1</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                    <i class="fas fa-calculator text-xl"></i>
                </div>
                <div class="ml-4">
                    <h4 class="text-2xl font-bold text-gray-900">${stats.avg_aggregate || 'N/A'}</h4>
                    <p class="text-sm text-gray-600">Average Aggregate</p>
                </div>
            </div>
        </div>
    `;
}

function updateResultsTable(stats) {
    const tableBody = document.getElementById('resultsTableBody');
    const totalCandidates = stats.total_candidates || 0;
    
    const divisions = [
        { name: 'Division 1', count: stats.div1 || 0, color: 'text-green-600', bgColor: 'bg-green-100' },
        { name: 'Division 2', count: stats.div2 || 0, color: 'text-blue-600', bgColor: 'bg-blue-100' },
        { name: 'Division 3', count: stats.div3 || 0, color: 'text-yellow-600', bgColor: 'bg-yellow-100' },
        { name: 'Division 4', count: stats.div4 || 0, color: 'text-orange-600', bgColor: 'bg-orange-100' },
        { name: 'Ungraded', count: stats.ungraded || 0, color: 'text-gray-600', bgColor: 'bg-gray-100' },
        { name: 'Absent/X', count: stats.absent || 0, color: 'text-red-600', bgColor: 'bg-red-100' }
    ];
    
    let tableHTML = '';
    
    divisions.forEach(division => {
        const percentage = totalCandidates > 0 ? ((division.count / totalCandidates) * 100).toFixed(1) : '0.0';
        const performanceWidth = totalCandidates > 0 ? (division.count / totalCandidates) * 100 : 0;
        
        tableHTML += `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${division.bgColor} ${division.color}">
                            ${division.name}
                        </span>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    ${division.count}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${percentage}%
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                            <div class="h-2 rounded-full ${division.bgColor.replace('bg-', 'bg-')} transition-all duration-300" style="width: ${performanceWidth}%"></div>
                        </div>
                        <span class="text-xs text-gray-500 min-w-[35px]">${percentage}%</span>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = tableHTML;
}

function downloadResults(format) {
    if (!['pdf', 'excel'].includes(format)) {
        showError('Invalid download format');
        return;
    }
    
    // Show loading state
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating...';
    button.disabled = true;
    
    // Create download URL
    const downloadUrl = `generate_school_results.php?exam_year_id=${currentExamYearId}&format=${format}`;
    
    // Create hidden iframe for download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = downloadUrl;
    document.body.appendChild(iframe);
    
    // Reset button after a delay
    setTimeout(() => {
        button.innerHTML = originalHTML;
        button.disabled = false;
        document.body.removeChild(iframe);
    }, 3000);
}

function refreshStats() {
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Refreshing...';
    button.disabled = true;
    
    loadResultsStats();
    
    setTimeout(() => {
        button.innerHTML = originalHTML;
        button.disabled = false;
    }, 1000);
}

function showError(message) {
    const cardsContainer = document.getElementById('statsCards');
    cardsContainer.innerHTML = `
        <div class="col-span-full">
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl mb-2"></i>
                <p class="text-red-800">${message}</p>
                <button onclick="loadResultsStats()" class="mt-3 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">
                    Try Again
                </button>
            </div>
        </div>
    `;
}

// Additional helper function to show success messages
function showSuccess(message) {
    // Create a temporary success notification
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50 transition-all duration-300';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>