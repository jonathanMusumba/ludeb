<?php
session_start();
require_once 'db_connect.php';

// Restrict to Examination Administrators
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Examination Administrator') {
    header("Location: ../login.php");
    exit();
}

$center_no = isset($_GET['center_no']) ? $_GET['center_no'] : '';
$exam_year_id = isset($_GET['exam_year_id']) ? (int)$_GET['exam_year_id'] : 0;

if (!$center_no || !$exam_year_id) {
    header("Location: list_schools.php");
    exit();
}

// Fetch school and exam details for preview
$school_query = "SELECT center_no, school_name FROM schools WHERE center_no = ?";
$stmt = $conn->prepare($school_query);
$stmt->bind_param('s', $center_no);
$stmt->execute();
$school_result = $stmt->get_result();
$school = $school_result->num_rows > 0 ? $school_result->fetch_assoc() : null;
$stmt->close();

$exam_year_query = "SELECT exam_year FROM exam_years WHERE id = ?";
$stmt = $conn->prepare($exam_year_query);
$stmt->bind_param('i', $exam_year_id);
$stmt->execute();
$exam_year_result = $stmt->get_result();
$exam_year = ($exam_year_result->num_rows > 0) ? $exam_year_result->fetch_assoc()['exam_year'] : 'N/A';
$stmt->close();

// Count total results
$count_query = "
    SELECT COUNT(DISTINCT sr.candidate_id) as total_candidates
    FROM school_results sr
    WHERE sr.school_id = (SELECT id FROM schools WHERE center_no = ? LIMIT 1)
    AND sr.exam_year_id = ?
";
$stmt = $conn->prepare($count_query);
$stmt->bind_param('si', $center_no, $exam_year_id);
$stmt->execute();
$count_result = $stmt->get_result();
$total_candidates = $count_result->fetch_assoc()['total_candidates'];
$stmt->close();

if (!$school || $total_candidates == 0) {
    header("Location: view_school_details.php?center_no=" . urlencode($center_no));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate PDF Results Slips</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            padding-top: 50px;
            padding-bottom: 50px;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .info-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 600;
            opacity: 0.9;
        }
        
        .info-value {
            font-weight: 700;
            font-size: 1.1em;
        }
        
        .btn-generate {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            border-radius: 50px;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }
        
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(79, 172, 254, 0.6);
            color: white;
        }
        
        .btn-back {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            transform: translateY(-1px);
            color: #333;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 8px 0;
            display: flex;
            align-items: center;
        }
        
        .feature-list li i {
            color: #28a745;
            margin-right: 10px;
            width: 20px;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            max-width: 400px;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .icon-large {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .container {
                padding-top: 20px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .btn-generate {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h2 class="mb-0 text-center">
                            <i class="fas fa-file-pdf me-2"></i>
                            Generate PDF Results Slips
                        </h2>
                    </div>
                    
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="fas fa-graduation-cap icon-large"></i>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-item">
                                <span class="info-label">School Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($school['school_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Center Number:</span>
                                <span class="info-value"><?php echo htmlspecialchars($school['center_no']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Examination Year:</span>
                                <span class="info-value"><?php echo htmlspecialchars($exam_year); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Candidates:</span>
                                <span class="info-value"><?php echo $total_candidates; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Pages:</span>
                                <span class="info-value"><?php echo ceil($total_candidates / 3); ?> pages</span>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3"><i class="fas fa-check-circle text-success me-2"></i>PDF Features:</h5>
                                <ul class="feature-list">
                                    <li><i class="fas fa-check"></i>Ready for printing</li>
                                    <li><i class="fas fa-check"></i>Automatic download</li>
                                </ul>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3"><i class="fas fa-info-circle text-info me-2"></i>File Details:</h5>
                                <ul class="feature-list">
                                    <li><i class="fas fa-file-pdf"></i>Format: PDF Document</li>
                                    <li><i class="fas fa-ruler"></i>Size: A4 Portrait</li>
                                    <li><i class="fas fa-download"></i>Auto-download enabled</li>
                                    <li><i class="fas fa-shield-alt"></i>Secure generation</li>
                                    <li><i class="fas fa-clock"></i>Timestamp included</li>
                                    <li><i class="fas fa-tag"></i>Named: Slips_<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $school['school_name']); ?>_<?php echo $exam_year; ?>.pdf</li>
                                </ul>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <button onclick="generatePDF()" class="btn btn-generate me-3">
                                <i class="fas fa-download me-2"></i>
                                Generate & Download PDF
                            </button>
                            <button onclick="goBack()" class="btn btn-back">
                                <i class="fas fa-arrow-left me-2"></i>
                                Back to School Details
                            </button>
                        </div>
                        
                        <div class="mt-4 text-center text-muted">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                The PDF will be automatically downloaded to your device once generated.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h4>Generating PDF...</h4>
            <p class="text-muted mb-0">Please wait while we create your results slips</p>
            <small class="text-muted">This may take a few moments</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function generatePDF() {
            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            // Create download URL
            const downloadUrl = `generate_pdf_slips.php?center_no=<?php echo urlencode($center_no); ?>&exam_year_id=<?php echo $exam_year_id; ?>`;
            
            // Method 1: Direct window.location (most reliable)
            setTimeout(() => {
                window.location.href = downloadUrl;
                
                // Hide loading overlay
                setTimeout(() => {
                    document.getElementById('loadingOverlay').style.display = 'none';
                    showSuccessMessage();
                }, 2000);
            }, 1000);
        }
        
        // Alternative method using fetch for better error handling
        function generatePDFAlternative() {
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            const downloadUrl = `generate_pdf_slips.php?center_no=<?php echo urlencode($center_no); ?>&exam_year_id=<?php echo $exam_year_id; ?>`;
            
            fetch(downloadUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.blob();
                })
                .then(blob => {
                    // Create blob URL and trigger download
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = `Slips_<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $school['school_name']); ?>_<?php echo $exam_year; ?>.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    document.getElementById('loadingOverlay').style.display = 'none';
                    showSuccessMessage();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loadingOverlay').style.display = 'none';
                    showErrorMessage();
                });
        }
        
        function showSuccessMessage() {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alert.style.top = '20px';
            alert.style.right = '20px';
            alert.style.zIndex = '10000';
            alert.style.minWidth = '300px';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong> PDF has been generated and downloaded.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alert);
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 5000);
        }
        
        function showErrorMessage() {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
            alert.style.top = '20px';
            alert.style.right = '20px';
            alert.style.zIndex = '10000';
            alert.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error!</strong> Failed to generate PDF. Please try again.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alert);
        }
        
        function goBack() {
            window.location.href = `view_school_details.php?center_no=<?php echo urlencode($center_no); ?>`;
        }
        
        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                generatePDF();
            }
            if (e.key === 'Escape') {
                goBack();
            }
        });
        
        // Auto-focus generate button
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.btn-generate').focus();
        });
    </script>
</body>
</html>