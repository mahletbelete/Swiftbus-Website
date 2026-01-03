<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is authenticated and is admin
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_users';

try {
    switch ($action) {
        case 'get_users':
            getUsers();
            break;
        case 'update_user':
            updateUser();
            break;
        case 'delete_user':
            deleteUser();
            break;
        case 'get_user_details':
            getUserDetails();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getUsers() {
    try {
        $pdo = getDB();
        
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        // Build query
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
                WHERE 1=1";
        
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
        $sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process the data
        foreach ($users as &$user) {
            // Format dates
            $user['joined_date'] = date('Y-m-d', strtotime($user['joined_date']));
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
        $countSql = "SELECT COUNT(*) as total FROM users u WHERE 1=1";
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

function updateUser() {
    try {
        $pdo = getDB();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['user_id'])) {
            throw new Exception('User ID is required');
        }
        
        $userId = $input['user_id'];
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : null;
        $isVerified = isset($input['is_verified']) ? (int)$input['is_verified'] : null;
        $role = $input['role'] ?? null;
        
        // Build update query
        $updateFields = [];
        $params = [];
        
        if ($isActive !== null) {
            $updateFields[] = "is_active = ?";
            $params[] = $isActive;
        }
        
        if ($isVerified !== null) {
            $updateFields[] = "is_verified = ?";
            $params[] = $isVerified;
        }
        
        if ($role !== null) {
            $updateFields[] = "role = ?";
            $params[] = $role;
        }
        
        $updateFields[] = "updated_at = NOW()";
        
        if (empty($updateFields)) {
            throw new Exception('No fields to update');
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
        $params[] = $userId;
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception('Failed to update user');
        }
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('User not found');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to update user: ' . $e->getMessage());
    }
}

function deleteUser() {
    try {
        $pdo = getDB();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['user_id'])) {
            throw new Exception('User ID is required');
        }
        
        $userId = $input['user_id'];
        
        // Check if user has bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = (SELECT id FROM users WHERE user_id = ?)");
        $stmt->execute([$userId]);
        $bookingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($bookingCount > 0) {
            // Don't delete, just deactivate
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE user_id = ?");
            $result = $stmt->execute([$userId]);
            $message = 'User deactivated (has existing bookings)';
        } else {
            // Safe to delete
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
            $result = $stmt->execute([$userId]);
            $message = 'User deleted successfully';
        }
        
        if (!$result) {
            throw new Exception('Failed to delete user');
        }
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('User not found or cannot be deleted');
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to delete user: ' . $e->getMessage());
    }
}

function getUserDetails() {
    try {
        $pdo = getDB();
        
        $userId = $_GET['user_id'] ?? '';
        
        if (empty($userId)) {
            throw new Exception('User ID is required');
        }
        
        $sql = "SELECT 
                    u.*,
                    COALESCE(b.booking_count, 0) as total_bookings,
                    COALESCE(b.total_spent, 0) as total_spent,
                    COALESCE(b.last_booking_date, NULL) as last_booking_date
                FROM users u
                LEFT JOIN (
                    SELECT 
                        user_id,
                        COUNT(*) as booking_count,
                        SUM(total_amount) as total_spent,
                        MAX(travel_date) as last_booking_date
                    FROM bookings 
                    WHERE booking_status = 'confirmed'
                    GROUP BY user_id
                ) b ON u.id = b.user_id
                WHERE u.user_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Get recent bookings
        $stmt = $pdo->prepare("
            SELECT booking_id, from_city, to_city, travel_date, booking_status, total_amount
            FROM bookings 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user['id']]);
        $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $user['recent_bookings'] = $recentBookings;
        
        echo json_encode([
            'success' => true,
            'data' => $user
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get user details: ' . $e->getMessage());
    }
}
?>