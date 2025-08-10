<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['System Admin', 'Examination Administrator', 'Data Entrant'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');

// Function to calculate relative time
function getRelativeTime($datetime) {
    $now = new DateTime();
    $date = new DateTime($datetime);
    $interval = $now->diff($date);

    if ($interval->y > 0) {
        return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    } elseif ($interval->m > 0) {
        return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    } elseif ($interval->d >= 7) {
        $weeks = floor($interval->d / 7);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($interval->d >= 1) {
        return $interval->d === 1 ? 'yesterday' : $interval->d . ' days ago';
    } elseif ($interval->h > 0) {
        return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    } elseif ($interval->i > 0) {
        return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'now';
    }
}

try {
    if ($action === 'fetch') {
        $chat_type = $_GET['chat_type'] ?? 'group';
        $receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
        $all_chats = isset($_GET['all_chats']) && $role === 'System Admin' ? true : false;

        if ($chat_type === 'private' && $receiver_id !== 0 && !$all_chats) {
            $stmt = $conn->prepare("
                SELECT id FROM system_users 
                WHERE id = ? 
                AND id != ? 
                AND (
                    role = 'System Admin' 
                    OR role = 'Examination Administrator' 
                    OR (role = 'Data Entrant' AND ? IN ('System Admin', 'Examination Administrator'))
                )
            ");
            $stmt->bind_param("iis", $receiver_id, $user_id, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid receiver']);
                exit;
            }
            $stmt->close();
        }

        if ($all_chats && $role === 'System Admin') {
            $query = "
                SELECT 
                    m.id,
                    m.sender_id,
                    m.receiver_id,
                    m.chat_type,
                    m.parent_id,
                    m.message,
                    u1.username AS sender,
                    u2.username AS receiver,
                    m.sent_at,
                    m.read_at,
                    u1.role AS sender_role
                FROM messages m
                JOIN system_users u1 ON m.sender_id = u1.id
                LEFT JOIN system_users u2 ON m.receiver_id = u2.id
                ORDER BY m.sent_at DESC LIMIT 50
            ";
            $stmt = $conn->prepare($query);
            $stmt->execute();
        } else {
            $query = "
                SELECT 
                    m.id,
                    m.sender_id,
                    m.receiver_id,
                    m.chat_type,
                    m.parent_id,
                    m.message,
                    u1.username AS sender,
                    u2.username AS receiver,
                    m.sent_at,
                    m.read_at,
                    u1.role AS sender_role
                FROM messages m
                JOIN system_users u1 ON m.sender_id = u1.id
                LEFT JOIN system_users u2 ON m.receiver_id = u2.id
                WHERE m.chat_type = ?
                AND (
                    (m.chat_type = 'group' AND m.receiver_id IS NULL)
                    OR (
                        m.chat_type = 'private' 
                        AND m.receiver_id IS NOT NULL 
                        AND (
                            (m.sender_id = ? AND m.receiver_id = ?) 
                            OR (m.sender_id = ? AND m.receiver_id = ?)
                        )
                    )
                )
                ORDER BY m.sent_at DESC LIMIT 50
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("siiii", $chat_type, $user_id, $receiver_id, $receiver_id, $user_id);
            $stmt->execute();
        }

        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'id' => $row['id'],
                'sender_id' => $row['sender_id'],
                'receiver_id' => $row['receiver_id'],
                'chat_type' => $row['chat_type'],
                'parent_id' => $row['parent_id'],
                'message' => htmlspecialchars($row['message']),
                'sender' => htmlspecialchars($row['sender']),
                'receiver' => $row['receiver'] ? htmlspecialchars($row['receiver']) : null,
                'sent_at' => getRelativeTime($row['sent_at']),
                'read_at' => $row['read_at'] ? getRelativeTime($row['read_at']) : null,
                'sender_role' => $row['sender_role']
            ];
        }
        $stmt->close();

        // Count unread messages for System Admin (only for their personal chats)
        if ($role === 'System Admin' && !$all_chats) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m 
                WHERE m.receiver_id = ? 
                AND m.read_at IS NULL 
                AND m.chat_type = 'private'
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $unread_result = $stmt->get_result();
            $unread = $unread_result->fetch_assoc()['unread'];
            $stmt->close();
        } else {
            $unread = 0;
        }

        if ($chat_type === 'private' && $receiver_id != 0 && $role !== 'System Admin' && !$all_chats) {
            $stmt = $conn->prepare("UPDATE messages SET read_at = NOW() WHERE chat_type = 'private' AND receiver_id = ? AND sender_id = ? AND read_at IS NULL");
            $stmt->bind_param("ii", $user_id, $receiver_id);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['success' => true, 'messages' => $messages, 'unread' => $unread]);
    } elseif ($action === 'send') {
        $conn->begin_transaction();

        $chat_type = $_POST['chat_type'] ?? 'group';
        $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : null;
        $message = trim($_POST['message'] ?? '');
        $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if (!$message) {
            echo json_encode(['success' => false, 'error' => 'Message is required']);
            exit;
        }

        if ($chat_type === 'private') {
            if (!$receiver_id || $receiver_id === 0) {
                echo json_encode(['success' => false, 'error' => 'Receiver ID is required for private chats']);
                exit;
            }
            $stmt = $conn->prepare("
                SELECT id FROM system_users 
                WHERE id = ? 
                AND id != ? 
                AND (
                    role = 'System Admin' 
                    OR role = 'Examination Administrator' 
                    OR (role = 'Data Entrant' AND ? IN ('System Admin', 'Examination Administrator'))
                )
            ");
            $stmt->bind_param("iis", $receiver_id, $user_id, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid receiver']);
                exit;
            }
            $stmt->close();
        } else {
            $receiver_id = null;
        }

        if ($parent_id) {
            $stmt = $conn->prepare("SELECT id FROM messages WHERE id = ?");
            $stmt->bind_param("i", $parent_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $details = "Attempted to reply to non-existent message ID $parent_id";
                $log_stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details) VALUES ('Invalid Reply Attempt', ?, ?)");
                $log_stmt->bind_param("is", $user_id, $details);
                $log_stmt->execute();
                $log_stmt->close();
                $parent_id = null;
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, chat_type, message, parent_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissi", $user_id, $receiver_id, $chat_type, $message, $parent_id);
        if ($stmt->execute()) {
            $new_message_id = $conn->insert_id;

            $details = "Sent message to " . ($chat_type === 'group' ? "group chat" : "user ID $receiver_id");
            if ($parent_id) {
                $details .= " as a reply to message ID $parent_id";
            }
            $log_stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details) VALUES ('Send Message', ?, ?)");
            $log_stmt->bind_param("is", $user_id, $details);
            $log_stmt->execute();
            $log_stmt->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message_id' => $new_message_id]);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'Failed to send message: ' . $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    $conn->rollback();
    $error_message = $e->getMessage();
    $stmt = $conn->prepare("INSERT INTO audit_logs (action, user_id, details) VALUES ('Chat Error', ?, ?)");
    $stmt->bind_param("is", $user_id, $error_message);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $error_message]);
}

$conn->close();
?>