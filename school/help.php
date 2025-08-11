<?php
require_once 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['school_id'])) {
    header('Location: ../login.php');
    exit();
}

$schoolId = $_SESSION['school_id'];
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get recent announcements for sidebar
function getRecentAnnouncementsForHelp($conn, $year, $limit = 5) {
    $sql = "SELECT a.*, u.username as uploader_name
            FROM announcements a
            LEFT JOIN system_users u ON a.uploader_id = u.id
            WHERE YEAR(a.created_at) = ?
            ORDER BY a.created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $year, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get recent resources for sidebar
function getRecentResourcesForHelp($conn, $year, $limit = 5) {
    $sql = "SELECT r.*, u.username as uploader_name,
            COALESCE(dl.download_count, 0) as download_count
            FROM resources r 
            LEFT JOIN system_users u ON r.uploader_id = u.id
            LEFT JOIN (
                SELECT resource_id, COUNT(*) as download_count 
                FROM download_logs 
                WHERE action = 'download'
                GROUP BY resource_id
            ) dl ON r.id = dl.resource_id
            WHERE r.approved = 1 AND YEAR(r.created_at) = ?
            ORDER BY r.created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $year, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$recent_announcements = getRecentAnnouncementsForHelp($conn, $year);
$recent_resources = getRecentResourcesForHelp($conn, $year);

$pageTitle = 'Help & Support';

ob_start();
?>

<!-- Three Column Layout -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Left Sidebar - Announcements & Resources -->
    <div class="lg:col-span-1 space-y-6">
        <!-- Recent Announcements -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-bullhorn text-green-600 mr-2"></i>Recent Announcements
                    </h3>
                    <a href="./announcements.php?year=<?php echo $year; ?>" class="text-green-600 hover:text-green-800 text-sm font-medium">
                        View All
                    </a>
                </div>
            </div>
            <div class="p-4">
                <?php if (empty($recent_announcements)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bullhorn text-2xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500 text-sm">No recent announcements</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach (array_slice($recent_announcements, 0, 3) as $announcement): ?>
                        <div class="border-l-3 border-green-500 pl-3 py-2">
                            <p class="text-sm font-medium text-gray-800 mb-1">
                                <?php echo htmlspecialchars(substr($announcement['content'], 0, 40)) . (strlen($announcement['content']) > 40 ? '...' : ''); ?>
                            </p>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars(ucfirst($announcement['category'])); ?>
                                </span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs 
                                    <?php echo $announcement['priority'] === 'high' ? 'bg-red-100 text-red-800' : ($announcement['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                    <?php echo htmlspecialchars(ucfirst($announcement['priority'])); ?>
                                </span>
                            </div>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-clock mr-1"></i><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <a href="./announcements.php?year=<?php echo $year; ?>" class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-bullhorn mr-1"></i>View All
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Resources -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-folder-open text-blue-600 mr-2"></i>Recent Resources
                    </h3>
                    <a href="./resources.php?year=<?php echo $year; ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View All
                    </a>
                </div>
            </div>
            <div class="p-4">
                <?php if (empty($recent_resources)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-folder-open text-2xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500 text-sm">No recent resources</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach (array_slice($recent_resources, 0, 3) as $resource): ?>
                        <div class="border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow">
                            <h4 class="font-medium text-gray-800 text-sm mb-2"><?php echo htmlspecialchars(substr($resource['title'], 0, 30)) . (strlen($resource['title']) > 30 ? '...' : ''); ?></h4>
                            <div class="flex flex-wrap gap-1 mb-2">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($resource['class']); ?>
                                </span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-800">
                                    <?php echo htmlspecialchars($resource['category']); ?>
                                </span>
                            </div>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-download mr-1"></i><?php echo $resource['download_count']; ?> downloads
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <a href="./resources.php?year=<?php echo $year; ?>" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-folder-open mr-1"></i>View All
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="lg:col-span-3">
        <!-- Help Content -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="p-6 border-b border-gray-200">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-life-ring text-blue-600 mr-3"></i>LUDEB Portal Help & Support
                </h1>
                <p class="text-gray-600 mt-2">Comprehensive guidance for navigating and using the LUDEB Portal effectively</p>
            </div>
            
            <div class="p-6">
                <!-- Quick Navigation -->
                <div class="bg-blue-50 rounded-lg p-4 mb-6">
                    <h3 class="text-lg font-semibold text-blue-800 mb-3 flex items-center">
                        <i class="fas fa-compass mr-2"></i>Quick Navigation
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <a href="./index.php" class="flex items-center p-3 bg-white rounded-lg hover:bg-gray-50 transition-colors group">
                            <i class="fas fa-home text-blue-600 mr-3 group-hover:text-blue-700"></i>
                            <div>
                                <div class="font-medium text-gray-800">Dashboard</div>
                                <div class="text-sm text-gray-600">Overview & summaries</div>
                            </div>
                        </a>
                        <a href="./resources.php?year=<?php echo $year; ?>" class="flex items-center p-3 bg-white rounded-lg hover:bg-gray-50 transition-colors group">
                            <i class="fas fa-folder-open text-blue-600 mr-3 group-hover:text-blue-700"></i>
                            <div>
                                <div class="font-medium text-gray-800">Resources</div>
                                <div class="text-sm text-gray-600">Educational materials</div>
                            </div>
                        </a>
                        <a href="./announcements.php?year=<?php echo $year; ?>" class="flex items-center p-3 bg-white rounded-lg hover:bg-gray-50 transition-colors group">
                            <i class="fas fa-bullhorn text-green-600 mr-3 group-hover:text-green-700"></i>
                            <div>
                                <div class="font-medium text-gray-800">Announcements</div>
                                <div class="text-sm text-gray-600">Important notices</div>
                            </div>
                        </a>
                        <a href="./Uploads.php?year=<?php echo $year; ?>" class="flex items-center p-3 bg-white rounded-lg hover:bg-gray-50 transition-colors group">
                            <i class="fas fa-cloud-upload-alt text-purple-600 mr-3 group-hover:text-purple-700"></i>
                            <div>
                                <div class="font-medium text-gray-800">Uploads</div>
                                <div class="text-sm text-gray-600">Manage your files</div>
                            </div>
                        </a>
                        <a href="./feedback.php" class="flex items-center p-3 bg-white rounded-lg hover:bg-gray-50 transition-colors group">
                            <i class="fas fa-comment-dots text-orange-600 mr-3 group-hover:text-orange-700"></i>
                            <div>
                                <div class="font-medium text-gray-800">Feedback</div>
                                <div class="text-sm text-gray-600">Submit feedback</div>
                            </div>
                        </a>
                        <a href="./results.php?year=<?php echo $year; ?>" class="flex items-center p-3 bg-white rounded-lg hover:bg-gray-50 transition-colors group">
                            <i class="fas fa-chart-line text-indigo-600 mr-3 group-hover:text-indigo-700"></i>
                            <div>
                                <div class="font-medium text-gray-800">Results</div>
                                <div class="text-sm text-gray-600">View & download</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Getting Started Section -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-rocket text-green-600 mr-2"></i>Getting Started
                    </h2>
                    <div class="space-y-4">
                        <div class="border-l-4 border-blue-500 pl-4">
                            <h4 class="font-medium text-gray-800 mb-2"><i class="fas fa-tachometer-alt mr-2 text-blue-600"></i>Dashboard Overview</h4>
                            <p class="text-gray-700 mb-2">Your dashboard provides a comprehensive overview of your school's activities including candidate statistics, results processing progress, and recent updates.</p>
                            <ul class="list-disc pl-6 text-gray-700 text-sm space-y-1">
                                <li>View total candidates and results processed for selected exam years</li>
                                <li>Monitor pending marks and upload activities</li>
                                <li>Access quick actions for common tasks</li>
                                <li>Download results in PDF or Excel format</li>
                            </ul>
                        </div>
                        
                        <div class="border-l-4 border-green-500 pl-4">
                            <h4 class="font-medium text-gray-800 mb-2"><i class="fas fa-folder-open mr-2 text-blue-600"></i>Resources Management</h4>
                            <p class="text-gray-700 mb-2">Access and share educational materials with the LUDEB community.</p>
                            <ul class="list-disc pl-6 text-gray-700 text-sm space-y-1">
                                <li>Browse resources by class, category, and subject</li>
                                <li>Filter between free and premium content</li>
                                <li>Download materials for offline use</li>
                                <li>Upload and share your own resources</li>
                            </ul>
                        </div>
                        
                        <div class="border-l-4 border-purple-500 pl-4">
                            <h4 class="font-medium text-gray-800 mb-2"><i class="fas fa-bullhorn mr-2 text-green-600"></i>Announcements</h4>
                            <p class="text-gray-700 mb-2">Stay informed with important notices and updates from LUDEB.</p>
                            <ul class="list-disc pl-6 text-gray-700 text-sm space-y-1">
                                <li>View announcements by priority (High, Medium, Low)</li>
                                <li>Filter by categories (general, academic, administrative)</li>
                                <li>Receive timely updates on examination schedules and procedures</li>
                                <li>Access archived announcements by year</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- How-to Guides -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-book text-indigo-600 mr-2"></i>How-to Guides
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Submitting Feedback -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-comment-dots text-orange-600 mr-2"></i>Submitting Feedback
                            </h4>
                            <ol class="list-decimal pl-6 text-gray-700 text-sm space-y-2">
                                <li>Navigate to the <a href="./feedback.php" class="text-blue-600 hover:underline">Send Feedback</a> page</li>
                                <li>Enter your feedback message clearly and concisely</li>
                                <li>Select appropriate priority level (High, Medium, Low)</li>
                                <li>Submit the form to receive a unique ticket number</li>
                                <li>Monitor the feedback table for responses from LUDEB team</li>
                                <li>Follow up if needed using your ticket number</li>
                            </ol>
                        </div>

                        <!-- Downloading Results -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-download text-indigo-600 mr-2"></i>Downloading Results
                            </h4>
                            <ol class="list-decimal pl-6 text-gray-700 text-sm space-y-2">
                                <li>Go to Dashboard or Results section</li>
                                <li>Select the desired exam year from dropdown</li>
                                <li>Choose your preferred format (PDF or Excel)</li>
                                <li>Review the results summary preview</li>
                                <li>Click "Download Results" to generate the file</li>
                                <li>Save the file to your preferred location</li>
                            </ol>
                        </div>

                        <!-- Uploading Resources -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-upload text-purple-600 mr-2"></i>Uploading Resources
                            </h4>
                            <ol class="list-decimal pl-6 text-gray-700 text-sm space-y-2">
                                <li>Access the upload section from Dashboard</li>
                                <li>Fill in resource details (title, description, category)</li>
                                <li>Select appropriate class and subject</li>
                                <li>Choose resource type (free or premium)</li>
                                <li>Upload your file (ensure proper format)</li>
                                <li>Wait for approval from administrators</li>
                            </ol>
                        </div>

                        <!-- Managing Profile -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-user-cog text-blue-600 mr-2"></i>Managing Profile
                            </h4>
                            <ol class="list-decimal pl-6 text-gray-700 text-sm space-y-2">
                                <li>Click on your school name in the top navigation</li>
                                <li>Select "My Profile" from the dropdown menu</li>
                                <li>Update your school information as needed</li>
                                <li>Change password if required</li>
                                <li>Save changes to apply updates</li>
                                <li>Verify changes have been saved successfully</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Troubleshooting -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-tools text-red-600 mr-2"></i>Troubleshooting
                    </h2>
                    <div class="bg-red-50 rounded-lg p-4">
                        <h4 class="font-semibold text-red-800 mb-3">Common Issues & Solutions</h4>
                        <div class="space-y-4">
                            <div class="border-l-4 border-red-500 pl-4">
                                <h5 class="font-medium text-red-800">Cannot Access Portal</h5>
                                <ul class="list-disc pl-6 text-red-700 text-sm mt-1">
                                    <li>Verify your login credentials are correct</li>
                                    <li>Ensure your school account is active</li>
                                    <li>Clear browser cache and cookies</li>
                                    <li>Try using a different web browser</li>
                                </ul>
                            </div>
                            
                            <div class="border-l-4 border-orange-500 pl-4">
                                <h5 class="font-medium text-orange-800">Pages Loading Slowly</h5>
                                <ul class="list-disc pl-6 text-orange-700 text-sm mt-1">
                                    <li>Check your internet connection</li>
                                    <li>Clear browser cache</li>
                                    <li>Close other browser tabs/applications</li>
                                    <li>Try refreshing the page</li>
                                </ul>
                            </div>
                            
                            <div class="border-l-4 border-yellow-500 pl-4">
                                <h5 class="font-medium text-yellow-800">File Upload Issues</h5>
                                <ul class="list-disc pl-6 text-yellow-700 text-sm mt-1">
                                    <li>Check file size limits (usually 10MB max)</li>
                                    <li>Ensure file format is supported</li>
                                    <li>Verify stable internet connection</li>
                                    <li>Try uploading during off-peak hours</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-phone text-green-600 mr-2"></i>Contact Us
                </h2>
                <p class="text-gray-600 mt-1">Get in touch with our support team for assistance</p>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Phone Support -->
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                            <i class="fas fa-phone text-2xl text-green-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800 mb-3">Phone Support</h4>
                        <div class="space-y-2 text-sm text-gray-700">
                            <div><a href="tel:+256787842061" class="text-green-600 hover:underline">0787842061</a></div>
                            <div><a href="tel:+256777115678" class="text-green-600 hover:underline">0777115678</a></div>
                            <div><a href="tel:+256743470506" class="text-green-600 hover:underline">0743470506</a></div>
                            <div><a href="tel:+256758697337" class="text-green-600 hover:underline">0758697337</a></div>
                        </div>
                    </div>

                    <!-- WhatsApp Support -->
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                            <i class="fab fa-whatsapp text-2xl text-green-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800 mb-3">WhatsApp</h4>
                        <div class="space-y-2 text-sm text-gray-700">
                            <div><a href="https://wa.me/256777115678" target="_blank" class="text-green-600 hover:underline">0777115678</a></div>
                            <div><a href="https://wa.me/256787842061" target="_blank" class="text-green-600 hover:underline">0787842061</a></div>
                            <div><a href="https://wa.me/256758697337" target="_blank" class="text-green-600 hover:underline">0758697337</a></div>
                        </div>
                    </div>

                    <!-- Email Support -->
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                            <i class="fas fa-envelope text-2xl text-blue-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800 mb-3">Email Support</h4>
                        <div class="space-y-2 text-sm text-gray-700">
                            <div><a href="mailto:ilabsuganda@gmail.com" class="text-blue-600 hover:underline">ilabsuganda@gmail.com</a></div>
                        </div>
                    </div>
                </div>

                <!-- Social Links -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h4 class="font-semibold text-gray-800 mb-4 text-center">Connect With Us</h4>
                    <div class="flex justify-center space-x-6">
                        <a href="https://linkedin.com/in/musumba-jonathan" target="_blank" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fab fa-linkedin mr-2"></i>Musumba Jonathan
                        </a>
                        <a href="https://github.com/jonathanMusumba" target="_blank" class="flex items-center px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition-colors">
                            <i class="fab fa-github mr-2"></i>GitHub
                        </a>
                    </div>
                </div>

                <!-- Response Time -->
                <div class="mt-6 bg-blue-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-clock text-blue-600 mr-3"></i>
                        <div>
                            <h5 class="font-medium text-blue-800">Response Time</h5>
                            <p class="text-sm text-blue-700">We typically respond within 2-4 hours during business hours (8 AM - 6 PM, Monday to Friday)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming ERP Services -->
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg shadow-md">
            <div class="p-6 border-b border-purple-200">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-rocket text-purple-600 mr-2"></i>Coming Soon: ERP Services
                </h2>
                <p class="text-gray-600 mt-1">Comprehensive school management system</p>
            </div>
            
            <div class="p-6">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-purple-100 rounded-full mb-4">
                        <i class="fas fa-cogs text-3xl text-purple-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">LUDEB ERP System</h3>
                    <p class="text-gray-600">Complete digital transformation for your school operations</p>
                </div>

                <!-- Feature Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-money-bill-wave text-purple-600 mr-2"></i>
                            <h4 class="font-semibold text-gray-800">Financial Management</h4>
                        </div>
                        <p class="text-sm text-gray-600">Fee collection, budgeting, and financial reporting</p>
                    </div>

                    <div class="bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-calendar-alt text-orange-600 mr-2"></i>
                            <h4 class="font-semibold text-gray-800">Timetable Management</h4>
                        </div>
                        <p class="text-sm text-gray-600">Automated scheduling and class management</p>
                    </div>

                    <div class="bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-chart-line text-indigo-600 mr-2"></i>
                            <h4 class="font-semibold text-gray-800">Academic Analytics</h4>
                        </div>
                        <p class="text-sm text-gray-600">Performance tracking and detailed reporting</p>
                    </div>

                    <div class="bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-bell text-red-600 mr-2"></i>
                            <h4 class="font-semibold text-gray-800">Communication Hub</h4>
                        </div>
                        <p class="text-sm text-gray-600">SMS, email, and portal notifications</p>
                    </div>

                    <div class="bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-book text-teal-600 mr-2"></i>
                            <h4 class="font-semibold text-gray-800">Library Management</h4>
                        </div>
                        <p class="text-sm text-gray-600">Digital catalog and book tracking system</p>
                    </div>

                    <div class="bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-clipboard-check text-cyan-600 mr-2"></i>
                            <h4 class="font-semibold text-gray-800">Examination System</h4>
                        </div>
                        <p class="text-sm text-gray-600">Online assessments and result processing</p>
                    </div>
                </div>

                <!-- Key Benefits -->
                <div class="bg-white rounded-lg p-6 mb-6">
                    <h4 class="font-bold text-gray-800 mb-4 text-center">Why Choose LUDEB ERP?</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-gray-700">Cloud-based accessibility from anywhere</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-gray-700">Mobile-responsive design for all devices</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-gray-700">Automated report generation</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-gray-700">Real-time data synchronization</span>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-gray-700">Secure data encryption and backup</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-gray-700">24/7 technical support</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-gray-700">Customizable modules for your needs</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-600 mr-3"></i>
                                <span class="text-gray-700">Integration with LUDEB examination system</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="bg-white rounded-lg p-6 mb-6">
                    <h4 class="font-bold text-gray-800 mb-4 text-center">Development Timeline</h4>
                    <div class="relative">
                        <div class="absolute left-1/2 transform -translate-x-1/2 w-1 h-full bg-purple-200"></div>
                        <div class="space-y-8">
                            <div class="flex items-center">
                                <div class="flex-1 text-right pr-8">
                                    <h5 class="font-semibold text-gray-800">Phase 1: Core Modules</h5>
                                    <p class="text-sm text-gray-600">Student & Staff Management, Basic Reporting</p>
                                    <span class="text-xs text-purple-600 font-medium">Q2 2025</span>
                                </div>
                                <div class="w-4 h-4 bg-purple-600 rounded-full border-4 border-white shadow"></div>
                                <div class="flex-1 pl-8"></div>
                            </div>
                            
                            <div class="flex items-center">
                                <div class="flex-1 pr-8"></div>
                                <div class="w-4 h-4 bg-purple-400 rounded-full border-4 border-white shadow"></div>
                                <div class="flex-1 text-left pl-8">
                                    <h5 class="font-semibold text-gray-800">Phase 2: Advanced Features</h5>
                                    <p class="text-sm text-gray-600">Financial Management, Timetabling, Communication</p>
                                    <span class="text-xs text-purple-600 font-medium">Q3 2025</span>
                                </div>
                            </div>
                            
                            <div class="flex items-center">
                                <div class="flex-1 text-right pr-8">
                                    <h5 class="font-semibold text-gray-800">Phase 3: Full Launch</h5>
                                    <p class="text-sm text-gray-600">Complete ERP Suite with Mobile Apps</p>
                                    <span class="text-xs text-purple-600 font-medium">Q4 2025</span>
                                </div>
                                <div class="w-4 h-4 bg-purple-300 rounded-full border-4 border-white shadow"></div>
                                <div class="flex-1 pl-8"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="text-center">
                    <h4 class="font-bold text-gray-800 mb-3">Be Among the First to Experience LUDEB ERP</h4>
                    <p class="text-gray-600 mb-6">Register your interest to get early access and special launch pricing</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="./feedback.php" class="inline-flex items-center px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium">
                            <i class="fas fa-hand-point-up mr-2"></i>Register Interest
                        </a>
                        <button class="inline-flex items-center px-6 py-3 bg-white text-purple-600 border-2 border-purple-600 rounded-lg hover:bg-purple-50 transition-colors font-medium" onclick="requestDemo()">
                            <i class="fas fa-play-circle mr-2"></i>Request Demo
                        </button>
                    </div>
                </div>

                <!-- Special Offer Badge -->
                <div class="mt-6 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-lg p-4 text-center">
                    <div class="flex items-center justify-center mb-2">
                        <i class="fas fa-star text-white text-xl mr-2"></i>
                        <span class="text-white font-bold text-lg">Early Bird Offer</span>
                        <i class="fas fa-star text-white text-xl ml-2"></i>
                    </div>
                    <p class="text-white font-medium">First 100 schools get 50% discount on setup fees!</p>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-question-circle text-blue-600 mr-2"></i>Frequently Asked Questions
                </h2>
            </div>
            
            <div class="p-6">
                <div class="space-y-4">
                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full text-left p-4 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg" onclick="toggleFAQ(this)">
                            <div class="flex justify-between items-center">
                                <h4 class="font-medium text-gray-800">How do I reset my password?</h4>
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </div>
                        </button>
                        <div class="hidden p-4 pt-0">
                            <p class="text-gray-600">Contact your system administrator or use the feedback system to request a password reset. Include your school name and username in the request.</p>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full text-left p-4 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg" onclick="toggleFAQ(this)">
                            <div class="flex justify-between items-center">
                                <h4 class="font-medium text-gray-800">Why can't I see results for my exam year?</h4>
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </div>
                        </button>
                        <div class="hidden p-4 pt-0">
                            <p class="text-gray-600">Results may not be available yet if processing is still ongoing, or your school may not have any candidates registered for that exam year. Contact LUDEB support for clarification.</p>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full text-left p-4 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg" onclick="toggleFAQ(this)">
                            <div class="flex justify-between items-center">
                                <h4 class="font-medium text-gray-800">How long does it take to get a response to feedback?</h4>
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </div>
                        </button>
                        <div class="hidden p-4 pt-0">
                            <p class="text-gray-600">We typically respond within 2-4 hours during business hours (8 AM - 6 PM, Monday to Friday). High priority issues are addressed faster.</p>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full text-left p-4 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg" onclick="toggleFAQ(this)">
                            <div class="flex justify-between items-center">
                                <h4 class="font-medium text-gray-800">Can I access the portal from my mobile device?</h4>
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </div>
                        </button>
                        <div class="hidden p-4 pt-0">
                            <p class="text-gray-600">Yes, the LUDEB Portal is fully responsive and works on all devices including smartphones and tablets. Use any modern web browser for the best experience.</p>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full text-left p-4 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg" onclick="toggleFAQ(this)">
                            <div class="flex justify-between items-center">
                                <h4 class="font-medium text-gray-800">When will the ERP system be available?</h4>
                                <i class="fas fa-chevron-down transform transition-transform"></i>
                            </div>
                        </button>
                        <div class="hidden p-4 pt-0">
                            <p class="text-gray-600">The LUDEB ERP system is planned for release in phases starting Q2 2025. Early access will be available to beta testers. Register your interest through the feedback system.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// FAQ Toggle Function
function toggleFAQ(button) {
    const content = button.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.style.transform = 'rotate(180deg)';
    } else {
        content.classList.add('hidden');
        icon.style.transform = 'rotate(0deg)';
    }
}

// Request Demo Function
function requestDemo() {
    alert('Demo request feature coming soon! For now, please contact us directly or submit feedback expressing your interest in the ERP system demo.');
}

// Smooth scrolling for internal links
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling to all internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add hover effects to navigation cards
    document.querySelectorAll('.group').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });

    // Animate numbers in ERP timeline
    const timelineItems = document.querySelectorAll('.space-y-8 > div');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });

    timelineItems.forEach(item => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        item.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(item);
    });
});

// Add click tracking for contact links
document.querySelectorAll('a[href^="tel:"], a[href^="mailto:"], a[href^="https://wa.me/"]').forEach(link => {
    link.addEventListener('click', function() {
        console.log('Contact link clicked:', this.href);
        // You can add analytics tracking here if needed
    });
});
</script>

<style>
/* Additional custom styles for the help page */
.border-l-3 {
    border-left-width: 3px;
}

.transition-shadow {
    transition: box-shadow 0.2s ease;
}

/* FAQ accordion animations */
.transform {
    transition: transform 0.2s ease;
}

/* Timeline styles */
.relative::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #9333ea, #ec4899);
    transform: translateX(-50%);
}

/* Mobile responsive adjustments */
@media (max-width: 768px) {
    .lg\:col-span-1 {
        order: 2;
    }
    
    .lg\:col-span-3 {
        order: 1;
    }
    
    .grid.grid-cols-1.lg\:grid-cols-4 {
        gap: 1.5rem;
    }
    
    /* Timeline mobile adjustments */
    .relative::before {
        left: 1rem;
    }
    
    .flex.items-center > div:first-child {
        display: none;
    }
    
    .flex.items-center > div:last-child {
        padding-left: 3rem;
        text-align: left;
    }
    
    .w-4.h-4 {
        position: absolute;
        left: 0.75rem;
        transform: none;
    }
}

/* Hover effects for contact cards */
.hover\:shadow-md:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Gradient text effect */
.bg-gradient-to-r {
    background: linear-gradient(to right, var(--tw-gradient-stops));
}

/* Custom scrollbar for sidebar */
.lg\:col-span-1 {
    max-height: calc(100vh - 8rem);
    overflow-y: auto;
}

.lg\:col-span-1::-webkit-scrollbar {
    width: 4px;
}

.lg\:col-span-1::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.lg\:col-span-1::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 2px;
}

.lg\:col-span-1::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>

<?php
$content = ob_get_clean();
include 'layout.php';
?>