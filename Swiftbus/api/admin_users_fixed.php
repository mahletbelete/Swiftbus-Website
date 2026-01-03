<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Start session for testing
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'U001';
    $_SESSION['user_role'] = 'admin';
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_users';

try {
    // Try database first
    $pdo = new PDO("mysql:host=localhost;dbname=swiftbus_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'get_users':
            getUsers($pdo);
            break;
        case 'update_user':
            updateUser($pdo);
            break;
        case 'delete_user':
            deleteUser($pdo);
            break;
        case 'get_user_details':
            getUserDetails($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    // Database failed, use localStorage fallback
    echo json_encode([
        'success' => true,
        'data' => getLocalStorageFallbackUsers(),
        'source' => 'localStorage',
        'message' => 'Using localStorage fallback data'
    ]);
}

function getUsers($pdo) {
    try {
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
        $offset = max(0, intval($_GET['offset'] ?? 0));
        
        // Build query - exclude admin users from count
        $sql = "SELECT 
                    u.id,
                    u.user_id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.full_name,
                    u.phone,
                    u.role,
                    u.is_active,
                    u.is_verified,
                    u.joined_date,
                    u.last_login,
                    u.created_at,
                    COALESCE(b.booking_count, 0) as total_bookings,
                    COALESCE(b.total_spent, 0) as total_spent
                FROM users u
                LEFT JOIN (
                    SELECT 
                        user_id,
                        COUNT(*) as booking_count,
                        SUM(total_amount) as total_spent
                    FROM bookings 
                    WHERE booking_status = 'confirmed'
                    GROUP BY user_id
                ) b ON u.id = b.user_id
                WHERE u.role != 'admin'";
        
        $params = [];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.user_id LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        // Add role filter
        if (!empty($role)) {
            $sql .= " AND u.role = ?";
            $params[] = $role;
        }
        
        // Add status filter
        if (!empty($status)) {
            if ($status === 'active') {
                $sql .= " AND u.is_active = 1";
            } elseif ($status === 'inactive') {
                $sql .= " AND u.is_active = 0";
            } elseif ($status === 'verified') {
                $sql .= " AND u.is_verified = 1";
            } elseif ($status === 'unverified') {
                $sql .= " AND u.is_verified = 0";
            }
        }
        
        // Add ordering and pagination
        $sql .= " ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process the data
        foreach ($users as &$user) {
            // Format dates
            $user['joined_date'] = $user['joined_date'] ? date('Y-m-d', strtotime($user['joined_date'])) : date('Y-m-d');
            $user['last_login'] = $user['last_login'] ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : 'Never';
            $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
            
            // Format amounts
            $user['total_spent'] = (float)$user['total_spent'];
            $user['total_bookings'] = (int)$user['total_bookings'];
            
            // Add status labels
            $user['status_label'] = $user['is_active'] ? 'Active' : 'Inactive';
            $user['verification_label'] = $user['is_verified'] ? 'Verified' : 'Unverified';
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM users u WHERE u.role != 'admin'";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.user_id LIKE ?)";
            $searchParam = "%$search%";
            $countParams = array_merge($countParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        if (!empty($role)) {
            $countSql .= " AND u.role = ?";
            $countParams[] = $role;
        }
        
        if (!empty($status)) {
            if ($status === 'active') {
                $countSql .= " AND u.is_active = 1";
            } elseif ($status === 'inactive') {
                $countSql .= " AND u.is_active = 0";
            } elseif ($status === 'verified') {
                $countSql .= " AND u.is_verified = 1";
            } elseif ($status === 'unverified') {
                $countSql .= " AND u.is_verified = 0";
            }
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $users,
            'source' => 'database',
            'pagination' => [
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch users: ' . $e->getMessage());
    }
}

function getLocalStorageFallbackUsers() {
    return [
        [
            'id' => 1,
            'user_id' => 'U001',
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'phone' => '+251911123456',
            'role' => 'user',
            'is_active' => 1,
            'is_verified' => 1,
            'joined_date' => '2024-01-15',
            'last_login' => '2024-01-20 10:30:00',
            'created_at' => '2024-01-15 09:00:00',
            'total_bookings' => 5,
            'total_spent' => 4250.00,
            'status_label' => 'Active',
            'verification_label' => 'Verified'
        ],
        [
            'id' => 2,
            'user_id' => 'U002',
            'email' => 'jane.smith@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'full_name' => 'Jane Smith',
            'phone' => '+251922234567',
            'role' => 'user',
            'is_active' => 1,
            'is_verified' => 0,
            'joined_date' => '2024-01-18',
            'last_login' => '2024-01-19 14:15:00',
            'created_at' => '2024-01-18 11:30:00',
            'total_bookings' => 2,
            'total_spent' => 1800.00,
            'status_label' => 'Active',
            'verification_label' => 'Unverified'
        ],
        [
            'id' => 3,
            'user_id' => 'U003',
            'email' => 'mike.johnson@example.com',
            'first_name' => 'Mike',
            'last_name' => 'Johnson',
            'full_name' => 'Mike Johnson',
            'phone' => '+251933345678',
            'role' => 'user',
            'is_active' => 0,
            'is_verified' => 1,
            'joined_date' => '2024-01-10',
            'last_login' => 'Never',
            'created_at' => '2024-01-10 16:45:00',
            'total_bookings' => 0,
            'total_spent' => 0.00,
            'status_label' => 'Inactive',
            'verification_label' => 'Verified'
        ]
    ];
}

// Placeholder functions for other actions
function updateUser($pdo) {
    echo json_encode(['success' => false, 'message' => 'Update user not implemented yet']);
}

function deleteUser($pdo) {
    echo json_encode(['success' => false, 'message' => 'Delete user not implemented yet']);
}

function getUserDetails($pdo) {
    echo json_encode(['success' => false, 'message' => 'Get user details not implemented yet']);
}
?>