<?php
ob_start();
require_once 'db_connect.php';

// Define base and root URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/ludeb/admin/';
$root_url = $protocol . $host . '/ludeb/';

// Restrict to authorized users
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Examination Administrator', 'Data Entrant'])) {
    error_log("Unauthorized access attempt, User ID: " . ($_SESSION['user_id'] ?? 'Not set'), 3, 'C:\xampp\htdocs\ludeb\setup_errors.log');
    ob_clean();
    header("Location: $root_url" . "login.php");
    exit;
}

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = htmlspecialchars($_SESSION['username']);

// Log page access
$conn->query("CALL log_action('Chat Access', $user_id, 'Accessed team chat page')");

// Fetch all users for private chats (exclude self)
$users = [];
$user_result = $conn->query("SELECT id, username, role FROM system_users WHERE id != $user_id ORDER BY username");
while ($row = $user_result->fetch_assoc()) {
    $users[] = $row;
}

// Fetch board name and exam year
$stmt = $conn->query("SELECT s.board_name, e.exam_year 
                      FROM settings s 
                      JOIN exam_years e ON s.exam_year_id = e.id 
                      ORDER BY s.id DESC LIMIT 1");
$row = $stmt->fetch_assoc();
$board_name = $row['board_name'] ?? 'Luuka Examination Board';
$exam_year = $row['exam_year'] ?? date('Y');

// Set page title
$page_title = "Team Chat";

// Define content
ob_start();
?>
<style>
    .chat-sidebar {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        padding: 20px;
        height: calc(100vh - 100px);
        overflow-y: auto;
    }
    .chat-sidebar h5 {
        color: #ffd700;
        font-weight: 600;
    }
    .chat-container {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        height: calc(100vh - 100px);
        display: flex;
        flex-direction: column;
    }
    .chat-header {
        background: #1d3557;
        border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        padding: 15px 20px;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .chat-header h5 {
        margin: 0;
        color: #ffd700;
        font-weight: 600;
    }
    .chat-messages {
        flex-grow: 1;
        overflow-y: auto;
        padding: 20px;
    }
    .chat-messages::-webkit-scrollbar {
        width: 6px;
    }
    .chat-messages::-webkit-scrollbar-thumb {
        background: #adb5bd;
        border-radius: 10px;
    }
    .message {
        margin-bottom: 15px;
        display: flex;
        flex-direction: column;
        animation: fadeIn 0.3s ease-in;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .message.sent {
        align-items: flex-end;
    }
    .message.received {
        align-items: flex-start;
    }
    .message.admin .bubble {
        background: #28a745;
        color: white;
        border-bottom-left-radius: 5px;
    }
    .message .bubble {
        padding: 10px 15px;
        border-radius: 15px;
        max-width: 70%;
        position: relative;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        transition: background 0.3s;
    }
    .message.sent .bubble {
        background: #ffd700;
        color: #1d3557;
        border-bottom-right-radius: 5px;
    }
    .message.received .bubble {
        background: rgba(255, 255, 255, 0.9);
        color: #2d3436;
        border-bottom-left-radius: 5px;
    }
    .message .bubble:hover {
        filter: brightness(1.05);
    }
    .message-meta {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.85em;
        margin-top: 5px;
        color: #f1f3f5;
    }
    .message.sent .message-meta {
        justify-content: flex-end;
    }
    .avatar {
        width: 32px;
        height: 32px;
        background: #ffd700;
        color: #1d3557;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.9em;
        margin-right: 10px;
    }
    .chat-footer {
        background: #1d3557;
        border-top: 1px solid rgba(255, 255, 255, 0.3);
        padding: 15px 20px;
        border-bottom-left-radius: 15px;
        border-bottom-right-radius: 15px;
    }
    .input-group {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 25px;
        overflow: hidden;
    }
    .chat-room {
        padding: 10px 15px;
        border-radius: 10px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: background 0.3s;
        position: relative;
        color: #2d3436;
    }
    .chat-room:hover {
        background: #f0f0f0;
    }
    .chat-room.active {
        background: #ffd700;
        color: #1d3557;
        font-weight: 600;
    }
    .chat-room .badge {
        position: absolute;
        top: 8px;
        right: 8px;
        background: #e63946;
        color: white;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 0.75em;
    }
    .reply-box {
        display: none;
        background: rgba(255, 255, 255, 0.9);
        padding: 10px 15px;
        border-radius: 10px;
        margin-bottom: 10px;
        color: #2d3436;
    }
    .status-sending {
        color: #6c757d;
        font-style: italic;
    }
    .status-sent {
        color: #28a745;
    }
    .status-failed {
        color: #e63946;
    }
    #toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        min-width: 250px;
        background: #1d3557;
        color: #ffd700;
        padding: 10px 15px;
        border-radius: 5px;
        display: none;
        z-index: 2000;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
</style>
<div class="page-header">
    <h1 class="page-title">Team Chat</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="Dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Team Chat</li>
        </ol>
    </nav>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="chat-sidebar">
            <h5><i class="fas fa-users me-2"></i> Chat Rooms</h5>
            <div class="mb-3">
                <h6 class="fw-semibold">Personal Chats</h6>
                <div onclick="loadChat('group', 0)" class="chat-room" data-type="group" data-id="0">
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
            <?php if ($role === 'System Admin'): ?>
                <div class="mb-3">
                    <h6 class="fw-semibold">All Chats</h6>
                    <div onclick="loadAllChats()" class="chat-room" id="all-chats-room">
                        View All Chats
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-8">
        <div class="chat-container">
            <div class="chat-header">
                <i class="fas fa-comments me-2"></i>
                <h5 id="chat-title">Select a chat to start messaging</h5>
            </div>
            <div class="chat-messages" id="chat-messages"></div>
            <div class="chat-footer">
                <div id="reply-box" class="reply-box">
                    <p class="text-muted small mb-1">Replying to message #<span id="reply-id"></span></p>
                    <div class="input-group">
                        <input type="text" class="form-control" id="reply-message" placeholder="Type your reply...">
                        <button onclick="sendReply()" class="btn btn-enhanced btn-success">Reply</button>
                        <button onclick="cancelReply()" class="btn btn-enhanced btn-danger">Cancel</button>
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
<div id="toast">New message received!</div>

<script>
jQuery.noConflict();
(function($) {
    let currentUserId = <?php echo $user_id; ?>;
    let currentChatType = 'group';
    let selectedUserId = 0;
    let selectedParentId = null;
    let pollInterval = null;
    let role = '<?php echo $role; ?>';
    let lastMessages = [];
    let allChatsMode = false;
    let csrfToken = '<?php echo htmlspecialchars($csrf_token); ?>';

    function loadChat(chatType, userId) {
        allChatsMode = false;
        currentChatType = chatType;
        selectedUserId = userId;
        $('#chat-title').text(chatType === 'group' ? 'General Chat' : `Chat with User #${userId}`);
        $('.chat-room').removeClass('active');
        $(`.chat-room[data-type="${chatType}"][data-id="${userId}"]`).addClass('active');
        clearInterval(pollInterval);
        fetchMessages();
        pollInterval = setInterval(fetchMessagesSilently, 5000);
    }

    function loadAllChats() {
        allChatsMode = true;
        $('#chat-title').text('All Chats');
        $('.chat-room').removeClass('active');
        $('#all-chats-room').addClass('active');
        clearInterval(pollInterval);
        fetchMessages();
        pollInterval = setInterval(fetchMessagesSilently, 5000);
    }

    function displayMessage(msg, isNew = false) {
        let isSent = msg.sender_id == currentUserId;
        let bubbleClass = isSent ? 'sent' : 'received';
        let adminClass = msg.sender_role === 'System Admin' && role === 'System Admin' ? 'admin' : '';
        let statusClass = isNew ? 'status-sending' : (msg.status === 'failed' ? 'status-failed' : 'status-sent');
        let statusText = isNew ? 'Sending...' : (msg.status === 'failed' ? 'Failed' : 'Sent');
        let replyInfo = msg.parent_id ? `<small class="text-muted d-block mb-1">[Replying to #${msg.parent_id}]</small>` : '';
        let parentExists = msg.parent_id ? $(`#chat-messages .message[data-message-id="${msg.parent_id}"]`).length > 0 : true;
        if (msg.parent_id && !parentExists) {
            replyInfo = `<small class="text-muted d-block mb-1">[Original message deleted]</small>`;
        }

        let html = `
            <div class="message ${bubbleClass} ${adminClass}" data-message-id="${msg.id}">
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
            url: 'data/chat_data.php',
            method: 'GET',
            data: {
                action: 'fetch',
                chat_type: currentChatType,
                receiver_id: selectedUserId,
                all_chats: allChatsMode
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
                console.error('Failed to fetch messages');
            }
        });
    }

    function fetchMessagesSilently() {
        $.ajax({
            url: 'data/chat_data.php',
            method: 'GET',
            data: {
                action: 'fetch',
                chat_type: currentChatType,
                receiver_id: selectedUserId,
                all_chats: allChatsMode
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
                console.error('Failed to fetch messages silently');
            }
        });
    }

    function sendMessage(chatType = currentChatType, receiverId = selectedUserId, messageText = $('#message').val().trim(), parentId = null) {
        if (!messageText) return;
        if (chatType !== 'group' && !receiverId) {
            alert('Please select a user to message.');
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
            status: 'sending',
            sender_role: role
        };
        displayMessage(tempMessage, true);
        $('#message').val('');

        $.ajax({
            url: 'data/chat_data.php',
            method: 'POST',
            data: {
                action: 'send',
                chat_type: chatType,
                receiver_id: receiverId,
                message: messageText,
                parent_id: parentId,
                csrf_token: csrfToken
            },
            success: function(response) {
                if (response.success) {
                    updateMessageStatus(tempMessageId, 'sent');
                    fetchMessages();
                } else {
                    updateMessageStatus(tempMessageId, 'failed');
                    if (response.error === 'Invalid CSRF token') {
                        console.error('CSRF token validation failed');
                        alert('Security error: Invalid CSRF token. Please refresh the page.');
                    }
                }
            },
            error: function() {
                updateMessageStatus(tempMessageId, 'failed');
                console.error('Failed to send message');
            }
        });
    }

    function sendReply() {
        let replyMessage = $('#reply-message').val().trim();
        if (!replyMessage || !selectedParentId) return;
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
        if (role === 'System Admin' && !allChatsMode) {
            if (unread > 0) {
                $('#private-badge-' + selectedUserId).text(unread);
            }
            $.ajax({
                url: 'data/chat_data.php',
                method: 'GET',
                data: {
                    action: 'fetch',
                    chat_type: 'private',
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

    $(document).ready(function() {
        console.log('Chat page loaded with jQuery version:', $.fn.jquery);
        loadChat('group', 0); // Default to General Chat
    });
})(jQuery);
</script>
<?php
$content = ob_get_clean();

// Include layout
require_once 'layout.php';
?>