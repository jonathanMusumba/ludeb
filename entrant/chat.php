<?php
session_start();
require_once 'db_connect.php';

// Restrict to Data Entrants
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Data Entrant') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = htmlspecialchars($_SESSION['username']);

// Fetch all users for private chats (exclude self and restrict to System Admins/Examination Administrators)
$users = [];
$stmt = $conn->prepare("SELECT id, username, role FROM system_users WHERE id != ? AND role IN ('System Admin', 'Examination Administrator') ORDER BY username");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
while ($row = $user_result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Chat - Results Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #f59e0b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --sidebar-width: 280px;
            --border-radius: 12px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #1f2937;
        }

        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
        }

        .sidebar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: #6b7280;
            text-decoration: none;
            border-radius: 0 25px 25px 0;
            margin-right: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--primary-color);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
            transform: translateX(8px);
        }

        .nav-link:hover::before,
        .nav-link.active::before {
            transform: scaleY(1);
        }

        .nav-link i {
            margin-right: 0.75rem;
            font-size: 1.125rem;
            width: 20px;
            text-align: center;
        }

        .collapse-menu {
            padding-left: 1rem;
        }

        .collapse-menu .nav-link {
            padding: 0.5rem 1.5rem;
            font-size: 0.875rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Top Bar */
        .topbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .topbar-left {
            display: flex;
            align-items: center;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--dark-color);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: var(--light-color);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--dark-color);
        }

        .topbar-info span {
            background: rgba(var(--primary-color), 0.1);
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            border: 1px solid rgba(var(--primary-color), 0.2);
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            color: white;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
            background: rgba(255, 255, 255, 0.05);
            min-height: calc(100vh - 80px);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 0.5rem 1rem;
            margin: 0;
        }

        .breadcrumb-item {
            color: rgba(255, 255, 255, 0.8);
        }

        .breadcrumb-item.active {
            color: white;
        }

        /* Chat Sidebar */
        .chat-sidebar {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            height: calc(100vh - 100px);
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .chat-sidebar h5 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }

        .chat-room {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chat-room:hover {
            background: rgba(79, 70, 229, 0.1);
            transform: translateX(4px);
        }

        .chat-room.active {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
        }

        .chat-room .badge {
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
        }

        .avatar {
            width: 32px;
            height: 32px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9em;
        }

        /* Chat Container */
        .chat-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .chat-header {
            background: linear-gradient(135deg, #f8fafc, #e5e7eb);
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chat-header h5 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .chat-messages {
            flex-grow: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        .message {
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
            animation: fadeIn 0.3s ease-in;
        }

        .message.sent {
            align-items: flex-end;
        }

        .message.received {
            align-items: flex-start;
        }

        .message .bubble {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            max-width: 70%;
            position: relative;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: background 0.3s;
        }

        .message.sent .bubble {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.received .bubble {
            background: #e5e7eb;
            color: var(--dark-color);
            border-bottom-left-radius: 4px;
        }

        .message .bubble:hover {
            filter: brightness(1.05);
        }

        .message-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .message.sent .message-meta {
            justify-content: flex-end;
        }

        .status-sending {
            color: #6b7280;
            font-style: italic;
        }

        .status-sent {
            color: var(--success-color);
        }

        .status-failed {
            color: var(--danger-color);
        }

        .chat-footer {
            background: linear-gradient(135deg, #f8fafc, #e5e7eb);
            border-top: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
            border-bottom-left-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
        }

        .input-group {
            background: #f1f3f5;
            border-radius: 25px;
            overflow: hidden;
        }

        .form-control {
            border: none;
            background: transparent;
            padding: 0.75rem 1rem;
            border-radius: 25px;
        }

        .form-control:focus {
            box-shadow: none;
            background: transparent;
        }

        .btn-enhanced {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            color: white;
        }

        .btn-success-enhanced {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }

        .btn-success-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .btn-danger-enhanced {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
        }

        .btn-danger-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            color: white;
        }

        .reply-box {
            display: none;
            background: #f0f2f5;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            min-width: 250px;
            background: var(--dark-color);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            display: none;
            z-index: 2000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        /* Loading States */
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
            height: 1.5rem;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .topbar-info {
                display: none;
            }

            .topbar-info.mobile {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }

            .content-area {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .chat-sidebar {
                padding: 1rem;
                height: auto;
                max-height: 50vh;
            }

            .chat-container {
                height: auto;
                min-height: 50vh;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3 class="sidebar-brand">
                    <i class="fas fa-graduation-cap"></i>
                    RMS Dashboard
                </h3>
            </div>
            <div class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="home.php">
                            <i class="fas fa-chart-line"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#schoolsMenu">
                            <i class="fas fa-school"></i>
                            Schools
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="schoolsMenu" class="collapse">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link" href="schools.php">
                                        <i class="fas fa-list"></i>
                                        List Schools
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#marksMenu">
                            <i class="fas fa-edit"></i>
                            Capture Marks
                            <i class="fas fa-chevron-down ms-auto"></i>
                        </a>
                        <div id="marksMenu" class="collapse">
                            <ul class="nav flex-column collapse-menu">
                                <li class="nav-item">
                                    <a class="nav-link" href="marks.php">
                                        <i class="fas fa-plus"></i>
                                        Enter Marks
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="see_marks.php">
                                        <i class="fas fa-eye"></i>
                                        View Results
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="check_missing.php">
                                        <i class="fas fa-search"></i>
                                        Check Missing
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="chat.php">
                            <i class="fas fa-comments"></i>
                            Team Chat
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="topbar-left">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                <div class="topbar-right">
                    <div class="topbar-info">
                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($board_name); ?></span>
                        <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($exam_year); ?></span>
                        <span><i class="fas fa-user"></i> <?php echo $username; ?></span>
                    </div>
                    <a href="../logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">
                        <i class="fas fa-comments"></i>
                        Team Chat
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="home.php">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Team Chat</li>
                        </ol>
                    </nav>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="chat-sidebar">
                            <h5><i class="fas fa-users"></i> Chat Rooms</h5>
                            <div class="mb-3">
                                <h6 class="fw-semibold">Personal Chats</h6>
                                <div onclick="loadChat('group', 0)" class="chat-room" data-type="group" data-id="0">
                                    <i class="fas fa-users"></i>
                                    General Chat
                                    <span class="badge" id="group-badge"></span>
                                </div>
                                <div class="user-list">
                                    <?php foreach ($users as $user): ?>
                                        <div onclick="loadChat('private', <?php echo $user['id']; ?>)" class="chat-room" data-type="private" data-id="<?php echo $user['id']; ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                                                <span><?php echo htmlspecialchars($user['username']); ?> (<?php echo $user['role']; ?>)</span>
                                            </div>
                                            <span class="badge" id="private-badge-<?php echo $user['id']; ?>"></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="chat-container">
                            <div class="chat-header">
                                <i class="fas fa-comments"></i>
                                <h5 id="chat-title">Select a chat to start messaging</h5>
                            </div>
                            <div class="chat-messages" id="chat-messages"></div>
                            <div class="chat-footer">
                                <div id="reply-box" class="reply-box">
                                    <p class="text-muted small mb-1">Replying to message #<span id="reply-id"></span></p>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="reply-message" placeholder="Type your reply...">
                                        <button onclick="sendReply()" class="btn btn-success-enhanced">Reply</button>
                                        <button onclick="cancelReply()" class="btn btn-danger-enhanced">Cancel</button>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="message" placeholder="Type your message...">
                                    <button onclick="sendMessage()" class="btn btn-enhanced">Send</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="toast" class="toast">New message received!</div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentUserId = <?php echo $user_id; ?>;
        let currentChatType = 'group';
        let selectedUserId = 0;
        let selectedParentId = null;
        let pollInterval = null;
        let lastMessages = [];

        $(document).ready(function() {
            // Sidebar toggle functionality
            $('#sidebarToggle').click(function() {
                const sidebar = $('#sidebar');
                const mainContent = $('#mainContent');
                sidebar.toggleClass('show');
                mainContent.toggleClass('expanded');
            });

            // Close sidebar when clicking outside on mobile
            $(document).on('click', function(event) {
                if (window.innerWidth <= 768 && 
                    !$('#sidebar').get(0).contains(event.target) && 
                    !$('#sidebarToggle').get(0).contains(event.target)) {
                    $('#sidebar').removeClass('show');
                    $('#mainContent').removeClass('expanded');
                }
            });

            // Chat room animations
            $('.chat-room').each(function() {
                this.style.animation = 'fadeIn 0.3s ease';
            });

            // Enhanced notification system
            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    ${message}
                `;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                    color: white;
                    padding: 1rem 1.5rem;
                    border-radius: 8px;
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                    z-index: 9999;
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    max-width: 300px;
                `;
                document.body.appendChild(notification);
                setTimeout(() => {
                    notification.style.transform = 'translateX(0)';
                }, 100);
                setTimeout(() => {
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        document.body.removeChild(notification);
                    }, 300);
                }, 3000);
            }

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    const sidebar = $('#sidebar');
                    const mainContent = $('#mainContent');
                    if (sidebar.hasClass('show')) {
                        sidebar.removeClass('show');
                        mainContent.removeClass('expanded');
                    }
                }
            });

            // Chat functionality
            function loadChat(chatType, userId) {
                currentChatType = chatType;
                selectedUserId = userId;
                $('#chat-title').text(chatType === 'group' ? 'General Chat' : `Chat with User #${userId}`);
                $('.chat-room').removeClass('active');
                $(`.chat-room[data-type="${chatType}"][data-id="${userId}"]`).addClass('active');
                clearInterval(pollInterval);
                fetchMessages();
                pollInterval = setInterval(fetchMessagesSilently, 5000);
            }

            function displayMessage(msg, isNew = false) {
                let isSent = msg.sender_id == currentUserId;
                let bubbleClass = isSent ? 'sent' : 'received';
                let statusClass = isNew ? 'status-sending' : (msg.status === 'failed' ? 'status-failed' : 'status-sent');
                let statusText = isNew ? 'Sending...' : (msg.status === 'failed' ? 'Failed' : 'Sent');
                let replyInfo = msg.parent_id ? `<small class="text-muted d-block mb-1">[Replying to #${msg.parent_id}]</small>` : '';
                let parentExists = msg.parent_id ? $(`#chat-messages .message[data-message-id="${msg.parent_id}"]`).length > 0 : true;
                if (msg.parent_id && !parentExists) {
                    replyInfo = `<small class="text-muted d-block mb-1">[Original message deleted]</small>`;
                }

                let html = `
                    <div class="message ${bubbleClass}" data-message-id="${msg.id}">
                        <div class="bubble">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <strong>${msg.sender}</strong>
                            </div>
                            ${replyInfo}
                            <p class="mb-0">${msg.message}</p>
                        </div>
                        <div class="message-meta">
                            <span class="text-muted">${msg.sent_at}</span>
                            ${msg.read_at && isSent ? `<span class="text-muted"> • Read ${msg.read_at}</span>` : ''}
                            ${isSent ? `<span class="message-status ${statusClass}">${statusText}</span>` : ''}
                            <button onclick="showReplyBox(${msg.id})" class="btn btn-link text-primary p-0 ms-2"><i class="fas fa-reply"></i></button>
                        </div>
                    </div>
                `;
                if (isNew && !$('#chat-messages .message[data-message-id="' + msg.id + '"]').length) {
                    $('#chat-messages').append(html);
                    showToast();
                } else if (!isNew) {
                    $('#chat-messages').append(html);
                }
                $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
            }

            function updateMessageStatus(messageId, status) {
                let messageElement = $(`#chat-messages .message[data-message-id="${messageId}"]`);
                let statusElement = messageElement.find('.message-status');
                statusElement.removeClass('status-sending status-sent status-failed');
                if (status === 'sent') {
                    statusElement.addClass('status-sent').text('Sent');
                } else if (status === 'failed') {
                    statusElement.addClass('status-failed').text('Failed');
                }
            }

            function fetchMessages() {
                $.ajax({
                    url: '../admin/data/chat_data.php',
                    method: 'GET',
                    data: {
                        action: 'fetch',
                        chat_type: currentChatType,
                        receiver_id: selectedUserId
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#chat-messages').empty();
                            response.messages.forEach(msg => displayMessage(msg));
                            lastMessages = response.messages.map(m => m.id);
                            updateBadges(response.unread);
                        }
                    },
                    error: function() {
                        showNotification('Failed to fetch messages', 'error');
                    }
                });
            }

            function fetchMessagesSilently() {
                $.ajax({
                    url: '../admin/data/chat_data.php',
                    method: 'GET',
                    data: {
                        action: 'fetch',
                        chat_type: currentChatType,
                        receiver_id: selectedUserId
                    },
                    success: function(response) {
                        if (response.success) {
                            let newMessages = response.messages.filter(m => !lastMessages.includes(m.id));
                            newMessages.forEach(msg => displayMessage(msg, true));
                            lastMessages = response.messages.map(m => m.id);
                            updateBadges(response.unread);
                        }
                    },
                    error: function() {
                        showNotification('Failed to fetch messages silently', 'error');
                    }
                });
            }

            function sendMessage(chatType = currentChatType, receiverId = selectedUserId, messageText = $('#message').val().trim(), parentId = null) {
                if (!messageText) {
                    showNotification('Please enter a message', 'error');
                    return;
                }
                if (chatType !== 'group' && !receiverId) {
                    showNotification('Please select a user to message', 'error');
                    return;
                }

                let tempMessageId = Date.now();
                let tempMessage = {
                    id: tempMessageId,
                    sender_id: currentUserId,
                    sender: '<?php echo $username; ?>',
                    message: messageText,
                    sent_at: 'now',
                    parent_id: parentId,
                    status: 'sending'
                };
                displayMessage(tempMessage, true);
                $('#message').val('');

                $.ajax({
                    url: '../admin/data/chat_data.php',
                    method: 'POST',
                    data: {
                        action: 'send',
                        chat_type: chatType,
                        receiver_id: receiverId,
                        message: messageText,
                        parent_id: parentId
                    },
                    success: function(response) {
                        if (response.success) {
                            updateMessageStatus(tempMessageId, 'sent');
                            fetchMessages();
                            showNotification('Message sent successfully', 'success');
                        } else {
                            updateMessageStatus(tempMessageId, 'failed');
                            showNotification('Failed to send message', 'error');
                        }
                    },
                    error: function() {
                        updateMessageStatus(tempMessageId, 'failed');
                        showNotification('Failed to send message', 'error');
                    }
                });
            }

            function sendReply() {
                let replyMessage = $('#reply-message').val().trim();
                if (!replyMessage || !selectedParentId) {
                    showNotification('Please enter a reply message', 'error');
                    return;
                }
                sendMessage(currentChatType, selectedUserId, replyMessage, selectedParentId);
                cancelReply();
            }

            function showReplyBox(parentId) {
                selectedParentId = parentId;
                $('#reply-id').text(parentId);
                $('#reply-box').slideDown();
                $('#reply-message').focus();
            }

            function cancelReply() {
                selectedParentId = null;
                $('#reply-message').val('');
                $('#reply-box').slideUp();
            }

            function updateBadges(unread) {
                $('.badge').text('');
                if (unread > 0) {
                    $('#private-badge-' + selectedUserId).text(unread);
                }
                $.ajax({
                    url: '../admin/data/chat_data.php',
                    method: 'GET',
                    data: {
                        action: 'fetch',
                        chat_type: 'group',
                        receiver_id: 0
                    },
                    success: function(response) {
                        if (response.success) {
                            let totalUnread = response.messages.filter(m => m.receiver_id === currentUserId && !m.read_at).length;
                            if (totalUnread > 0) {
                                $('#group-badge').text(totalUnread);
                            }
                        }
                    }
                });
            }

            function showToast() {
                let toast = $('#toast');
                toast.fadeIn(200).delay(3000).fadeOut(200);
            }

            $('#message').on('keypress', function(e) {
                if (e.which == 13 && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            loadChat('group', 0); // Default to General Chat
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>