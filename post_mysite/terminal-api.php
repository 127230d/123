<?php
/**
 * Terminal API Handler
 * معالج واجهة برمجة التطبيقات للأوامر الطرفية
 */

require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once 'dssssssssb.php';

// Set JSON response header
header('Content-Type: application/json');

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$command = $input['command'] ?? '';
$args = $input['args'] ?? [];
$username = $_SESSION['username'];

// Get user data
$user_query = mysqli_prepare($con, "SELECT * FROM login WHERE username = ?");
mysqli_stmt_bind_param($user_query, "s", $username);
mysqli_stmt_execute($user_query);
$user_result = mysqli_stmt_get_result($user_query);
$user_data = mysqli_fetch_assoc($user_result);

$is_admin = isset($user_data['subscription']) && $user_data['subscription'] === 'admin';

// Command handler
$response = ['success' => false, 'output' => '', 'error' => ''];

try {
    switch ($command) {
        case 'whoami':
            $response = [
                'success' => true,
                'output' => $username,
                'type' => 'success'
            ];
            break;

        case 'pwd':
            $response = [
                'success' => true,
                'output' => '/home/' . $username,
                'type' => 'success'
            ];
            break;

        case 'ls':
            $path = $args[0] ?? '/home/' . $username;
            $files = [];
            
            // Simulate file listing
            $files = [
                'documents/',
                'uploads/',
                'shared_files/',
                'config.json',
                'readme.txt'
            ];
            
            $response = [
                'success' => true,
                'output' => implode("\n", $files),
                'type' => 'output'
            ];
            break;

        case 'cat':
            if (empty($args)) {
                $response = [
                    'success' => false,
                    'error' => 'Usage: cat <filename>',
                    'type' => 'error'
                ];
            } else {
                $filename = $args[0];
                $response = [
                    'success' => true,
                    'output' => "Contents of {$filename}:\nThis is a sample file content...",
                    'type' => 'output'
                ];
            }
            break;

        case 'date':
            $response = [
                'success' => true,
                'output' => date('Y-m-d H:i:s'),
                'type' => 'success'
            ];
            break;

        case 'uptime':
            $response = [
                'success' => true,
                'output' => 'System uptime: 2 days, 14 hours, 32 minutes',
                'type' => 'info'
            ];
            break;

        case 'ps':
            $processes = [
                'PID    COMMAND',
                '1234   node server.js',
                '5678   php-fpm',
                '9012   nginx',
                '3456   mysql'
            ];
            
            $response = [
                'success' => true,
                'output' => implode("\n", $processes),
                'type' => 'output'
            ];
            break;

        case 'top':
            $stats = [
                'System Resource Usage:',
                'CPU: 25%',
                'Memory: 60%',
                'Disk: 45%',
                'Network: 10%'
            ];
            
            $response = [
                'success' => true,
                'output' => implode("\n", $stats),
                'type' => 'info'
            ];
            break;

        case 'files':
            // Get available files from database
            $files_query = mysqli_prepare($con, "
                SELECT sf.id, sf.filename, sf.file_size, sf.price, sf.created_at, 
                       u.username as owner_name
                FROM shared_files sf
                JOIN login u ON sf.original_owner_id = u.username
                WHERE sf.is_available = 1
                ORDER BY sf.created_at DESC
                LIMIT 20
            ");
            mysqli_stmt_execute($files_query);
            $files_result = mysqli_stmt_get_result($files_query);
            
            $files_output = ['Available Files in Exchange System:', 'ID  Name                Size    Price  Owner'];
            
            while ($file = mysqli_fetch_assoc($files_result)) {
                $size = formatFileSize($file['file_size']);
                $price = number_format($file['price']);
                $name = substr($file['filename'], 0, 15);
                $owner = substr($file['owner_name'], 0, 10);
                
                $files_output[] = sprintf("%-3d  %-15s  %-6s  %-5s  %s", 
                    $file['id'], $name, $size, $price, $owner);
            }
            
            $response = [
                'success' => true,
                'output' => implode("\n", $files_output),
                'type' => 'output'
            ];
            break;

        case 'balance':
            $balance_info = [
                'User Balance:',
                'Points: ' . number_format($user_data['points'] ?? 0),
                'Username: ' . $username,
                'Subscription: ' . ($user_data['subscription'] ?? 'user')
            ];
            
            $response = [
                'success' => true,
                'output' => implode("\n", $balance_info),
                'type' => 'success'
            ];
            break;

        case 'transactions':
            // Get user transactions
            $transactions_query = mysqli_prepare($con, "
                SELECT fp.*, sf.filename
                FROM file_purchases fp
                LEFT JOIN shared_files sf ON fp.file_id = sf.id
                WHERE fp.buyer_username = ? OR fp.seller_username = ?
                ORDER BY fp.purchase_date DESC
                LIMIT 10
            ");
            mysqli_stmt_bind_param($transactions_query, "ss", $username, $username);
            mysqli_stmt_execute($transactions_query);
            $transactions_result = mysqli_stmt_get_result($transactions_query);
            
            $transactions_output = ['Recent Transactions:', 'Date       Type      Amount  Description'];
            
            while ($transaction = mysqli_fetch_assoc($transactions_result)) {
                $type = $transaction['buyer_username'] === $username ? 'Purchase' : 'Sale';
                $amount = number_format($transaction['price']);
                $date = date('Y-m-d', strtotime($transaction['purchase_date']));
                $filename = substr($transaction['filename'] ?? 'Unknown', 0, 15);
                
                $transactions_output[] = sprintf("%-10s  %-8s  %-6s  %s", 
                    $date, $type, $amount, $filename);
            }
            
            $response = [
                'success' => true,
                'output' => implode("\n", $transactions_output),
                'type' => 'output'
            ];
            break;

        case 'purchase':
            if (empty($args)) {
                $response = [
                    'success' => false,
                    'error' => 'Usage: purchase <file_id>',
                    'type' => 'error'
                ];
            } else {
                $file_id = intval($args[0]);
                
                // Get file details
                $file_query = mysqli_prepare($con, "SELECT * FROM shared_files WHERE id = ? AND is_available = 1");
                mysqli_stmt_bind_param($file_query, "i", $file_id);
                mysqli_stmt_execute($file_query);
                $file_result = mysqli_stmt_get_result($file_query);
                $file_data = mysqli_fetch_assoc($file_result);
                
                if (!$file_data) {
                    $response = [
                        'success' => false,
                        'error' => 'File not found or not available',
                        'type' => 'error'
                    ];
                } elseif ($user_data['points'] < $file_data['price']) {
                    $response = [
                        'success' => false,
                        'error' => 'Insufficient points',
                        'type' => 'error'
                    ];
                } else {
                    // Process purchase (simplified)
                    $response = [
                        'success' => true,
                        'output' => "Purchase successful! File: {$file_data['filename']}",
                        'type' => 'success'
                    ];
                }
            }
            break;

        case 'ping':
            if (empty($args)) {
                $response = [
                    'success' => false,
                    'error' => 'Usage: ping <host>',
                    'type' => 'error'
                ];
            } else {
                $host = $args[0];
                $response = [
                    'success' => true,
                    'output' => "Pinging {$host}...\nPONG - 25ms",
                    'type' => 'success'
                ];
            }
            break;

        case 'users':
            if (!$is_admin) {
                $response = [
                    'success' => false,
                    'error' => 'Access denied. Admin privileges required.',
                    'type' => 'error'
                ];
            } else {
                $users_query = mysqli_prepare($con, "SELECT username, subscription, points FROM login ORDER BY username");
                mysqli_stmt_execute($users_query);
                $users_result = mysqli_stmt_get_result($users_query);
                
                $users_output = ['System Users:', 'Username    Role      Points'];
                
                while ($user = mysqli_fetch_assoc($users_result)) {
                    $users_output[] = sprintf("%-10s  %-8s  %s", 
                        $user['username'], 
                        $user['subscription'] ?? 'user',
                        number_format($user['points'] ?? 0));
                }
                
                $response = [
                    'success' => true,
                    'output' => implode("\n", $users_output),
                    'type' => 'output'
                ];
            }
            break;

        case 'help':
            $help_text = [
                'Available Commands:',
                '',
                'File System:',
                '  ls [path]          - List directory contents',
                '  pwd                - Print working directory',
                '  cat <file>         - Display file contents',
                '',
                'System:',
                '  whoami             - Display current user',
                '  date               - Display current date and time',
                '  uptime             - Show system uptime',
                '  ps                 - Show running processes',
                '  top                - Show system resource usage',
                '',
                'File Exchange:',
                '  files              - List available files',
                '  balance            - Show user balance',
                '  transactions       - Show transaction history',
                '  purchase <id>      - Purchase file by ID',
                '',
                'Network:',
                '  ping <host>        - Test network connectivity',
                '',
                'Admin (admin only):',
                '  users              - List system users'
            ];
            
            $response = [
                'success' => true,
                'output' => implode("\n", $help_text),
                'type' => 'info'
            ];
            break;

        default:
            $response = [
                'success' => false,
                'error' => "Command not found: {$command}. Type 'help' for available commands.",
                'type' => 'error'
            ];
            break;
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Error: ' . $e->getMessage(),
        'type' => 'error'
    ];
}

// Helper function for file size formatting
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 0) {
        return $bytes . ' bytes';
    } else {
        return '0 bytes';
    }
}

// Send response
echo json_encode($response);
?>

