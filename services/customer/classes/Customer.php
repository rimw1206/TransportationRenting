<?php
// ============================================
// services/customer/classes/User.php
// ============================================
require_once __DIR__ . '/../../../shared/classes/DatabaseManager.php';

class User {
    private $serviceName = "customer";

    /**
     * Get user by ID
     */
    public function getById($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                SELECT 
                    user_id, username, password, name, email, phone, 
                    birthdate, created_at, status 
                FROM Users 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("User::getById error: " . $e->getMessage());
            throw new Exception("Failed to fetch user");
        }
    }

    /**
     * Get user by username
     */
    public function getByUsername($username) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                SELECT 
                    user_id, username, password, name, email, phone, 
                    birthdate, created_at, status 
                FROM Users 
                WHERE username = ?
            ");
            $stmt->execute([$username]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("User::getByUsername error: " . $e->getMessage());
            throw new Exception("Failed to fetch user by username");
        }
    }

    /**
     * Get user by email
     */
    public function getByEmail($email) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                SELECT 
                    user_id, username, password, name, email, phone, 
                    birthdate, created_at, status 
                FROM Users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("User::getByEmail error: " . $e->getMessage());
            throw new Exception("Failed to fetch user by email");
        }
    }

    /**
     * Get all users with filters
     */
    public function getAll($filters = []) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $sql = "
                SELECT 
                    user_id, username, name, email, phone, 
                    birthdate, status, created_at 
                FROM Users 
                WHERE 1=1
            ";
            $params = [];
            
            // Filter by status
            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            // Search query
            if (!empty($filters['search'])) {
                $sql .= " AND (name LIKE ? OR email LIKE ? OR username LIKE ? OR phone LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Date range filter
            if (!empty($filters['from_date'])) {
                $sql .= " AND created_at >= ?";
                $params[] = $filters['from_date'];
            }
            
            if (!empty($filters['to_date'])) {
                $sql .= " AND created_at <= ?";
                $params[] = $filters['to_date'];
            }
            
            // Order by
            $orderBy = $filters['order_by'] ?? 'created_at';
            $orderDir = $filters['order_dir'] ?? 'DESC';
            $sql .= " ORDER BY {$orderBy} {$orderDir}";
            
            // Pagination
            if (isset($filters['limit'])) {
                $sql .= " LIMIT ?";
                $params[] = (int)$filters['limit'];
                
                if (isset($filters['offset'])) {
                    $sql .= " OFFSET ?";
                    $params[] = (int)$filters['offset'];
                }
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("User::getAll error: " . $e->getMessage());
            throw new Exception("Failed to fetch users");
        }
    }

    /**
     * Count users with filters
     */
    public function count($filters = []) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $sql = "SELECT COUNT(*) as total FROM Users WHERE 1=1";
            $params = [];
            
            // Filter by status
            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }
            
            // Search query
            if (!empty($filters['search'])) {
                $sql .= " AND (name LIKE ? OR email LIKE ? OR username LIKE ? OR phone LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['total'];
            
        } catch (PDOException $e) {
            error_log("User::count error: " . $e->getMessage());
            throw new Exception("Failed to count users");
        }
    }

    /**
     * Create new user
     */
    public function create($data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Validate required fields
            $required = ['username', 'password', 'name', 'email'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Field '{$field}' is required");
                }
            }
            
            // Check if username exists
            if ($this->getByUsername($data['username'])) {
                throw new Exception("Username already exists");
            }
            
            // Check if email exists
            if ($this->getByEmail($data['email'])) {
                throw new Exception("Email already exists");
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            // Validate password length
            if (strlen($data['password']) < 6) {
                throw new Exception("Password must be at least 6 characters");
            }
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                INSERT INTO Users (
                    username, password, name, email, phone, 
                    birthdate, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $data['username'],
                $hashedPassword,
                $data['name'],
                $data['email'],
                $data['phone'] ?? null,
                $data['birthdate'] ?? null,
                $data['status'] ?? 'Pending'
            ]);
            
            $userId = $db->lastInsertId();
            
            return [
                'user_id' => $userId,
                'username' => $data['username'],
                'email' => $data['email'],
                'name' => $data['name']
            ];
            
        } catch (PDOException $e) {
            error_log("User::create error: " . $e->getMessage());
            
            // Handle duplicate key error
            if ($e->getCode() == 23000) {
                throw new Exception("Username or email already exists");
            }
            
            throw new Exception("Failed to create user");
        }
    }

    /**
     * Update user information
     */
    public function update($userId, $data) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Check if user exists
            $user = $this->getById($userId);
            if (!$user) {
                throw new Exception("User not found");
            }
            
            $fields = [];
            $params = [];
            
            $allowedFields = ['name', 'email', 'phone', 'birthdate'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    // Validate email if updating
                    if ($field === 'email' && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Invalid email format");
                    }
                    
                    // Check if email is already taken by another user
                    if ($field === 'email' && $data[$field] !== $user['email']) {
                        $existingUser = $this->getByEmail($data[$field]);
                        if ($existingUser && $existingUser['user_id'] != $userId) {
                            throw new Exception("Email already exists");
                        }
                    }
                    
                    $fields[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                throw new Exception("No fields to update");
            }
            
            $params[] = $userId;
            
            $sql = "UPDATE Users SET " . implode(', ', $fields) . " WHERE user_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return $this->getById($userId);
            
        } catch (PDOException $e) {
            error_log("User::update error: " . $e->getMessage());
            throw new Exception("Failed to update user");
        }
    }

    /**
     * Update user password
     */
    public function updatePassword($userId, $newPassword) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Validate password length
            if (strlen($newPassword) < 6) {
                throw new Exception("Password must be at least 6 characters");
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("User::updatePassword error: " . $e->getMessage());
            throw new Exception("Failed to update password");
        }
    }

    /**
     * Update user status
     */
    public function updateStatus($userId, $status) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $validStatuses = ['Active', 'Inactive', 'Pending'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid status. Must be: Active, Inactive, or Pending");
            }
            
            $stmt = $db->prepare("UPDATE Users SET status = ? WHERE user_id = ?");
            $stmt->execute([$status, $userId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("User not found");
            }
            
            return $this->getById($userId);
            
        } catch (PDOException $e) {
            error_log("User::updateStatus error: " . $e->getMessage());
            throw new Exception("Failed to update status");
        }
    }

    /**
     * Delete user
     */
    public function delete($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Check if user exists
            $user = $this->getById($userId);
            if (!$user) {
                throw new Exception("User not found");
            }
            
            $stmt = $db->prepare("DELETE FROM Users WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("User::delete error: " . $e->getMessage());
            throw new Exception("Failed to delete user");
        }
    }

    /**
     * Verify user credentials for login
     */
    public function verifyCredentials($username, $password) {
        try {
            $user = $this->getByUsername($username);
            
            if (!$user) {
                // Try email instead
                $user = $this->getByEmail($username);
            }
            
            if (!$user) {
                return null;
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                return null;
            }
            
            // Remove password from result
            unset($user['password']);
            
            return $user;
            
        } catch (Exception $e) {
            error_log("User::verifyCredentials error: " . $e->getMessage());
            throw new Exception("Failed to verify credentials");
        }
    }

    /**
     * Get user statistics
     */
    public function getStatistics() {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->query("
                SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive_users,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_users,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_signups,
                    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_signups,
                    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as month_signups
                FROM Users
            ");
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("User::getStatistics error: " . $e->getMessage());
            throw new Exception("Failed to fetch statistics");
        }
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username) {
        return $this->getByUsername($username) !== false;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email) {
        return $this->getByEmail($email) !== false;
    }

    /**
     * Get user with KYC information
     */
    public function getWithKYC($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            $stmt = $db->prepare("
                SELECT 
                    u.user_id, u.username, u.name, u.email, u.phone, 
                    u.birthdate, u.status, u.created_at,
                    k.kyc_id, k.identity_number, k.verification_status,
                    k.verified_at
                FROM Users u
                LEFT JOIN KYC k ON u.user_id = k.user_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("User::getWithKYC error: " . $e->getMessage());
            throw new Exception("Failed to fetch user with KYC");
        }
    }

    /**
     * Get user with payment methods
     */
    public function getWithPaymentMethods($userId) {
        try {
            $db = DatabaseManager::getConnection($this->serviceName);
            
            // Get user
            $user = $this->getById($userId);
            if (!$user) {
                return null;
            }
            
            // Get payment methods
            $stmt = $db->prepare("
                SELECT method_id, type, provider, account_number, 
                       expiry_date, is_default
                FROM PaymentMethod 
                WHERE user_id = ?
                ORDER BY is_default DESC, method_id DESC
            ");
            $stmt->execute([$userId]);
            
            unset($user['password']);
            $user['payment_methods'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $user;
            
        } catch (PDOException $e) {
            error_log("User::getWithPaymentMethods error: " . $e->getMessage());
            throw new Exception("Failed to fetch user with payment methods");
        }
    }
}
