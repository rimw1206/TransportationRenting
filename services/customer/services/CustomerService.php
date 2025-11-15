<?php
/**
 * ============================================
 * services/customer/services/UserService.php
 * FIXED VERSION - Consistent JWT method usage
 * ============================================
 */

require_once __DIR__ . '/../classes/Customer.php';
require_once __DIR__ . '/../../../shared/classes/JWTHandler.php';

class UserService {
    private $userModel;
    private $jwtHandler;

    public function __construct() {
        $this->userModel = new User();
        $this->jwtHandler = new JWTHandler();
    }

    /**
     * Verify token and get user - FIXED to use decode() instead of verifyToken()
     */
    public function verifyToken($token) {
        try {
            // Use decode() method which exists in JWTHandler
            $decoded = $this->jwtHandler->decode($token);
            
            if ($decoded === false || !is_array($decoded)) {
                return [
                    'success' => false,
                    'message' => 'Invalid token'
                ];
            }
            
            if (!isset($decoded['user_id'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid token payload - missing user_id'
                ];
            }
            
            $user = $this->userModel->getById($decoded['user_id']);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            // Remove password
            unset($user['password']);
            
            return [
                'success' => true,
                'data' => [
                    'user' => $user,
                    'token_data' => $decoded
                ]
            ];
            
        } catch (Exception $e) {
            error_log('UserService::verifyToken error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user profile
     */
    public function getProfile($userId) {
        try {
            $user = $this->userModel->getById($userId);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            // Remove password
            unset($user['password']);
            
            return [
                'success' => true,
                'data' => $user
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data) {
        try {
            $result = $this->userModel->update($userId, $data);
            
            // Remove password
            unset($result['password']);
            
            return [
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get user
            $user = $this->userModel->getById($userId);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            // Update password
            $result = $this->userModel->updatePassword($userId, $newPassword);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Password changed successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to change password'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete user account
     */
    public function deleteAccount($userId) {
        try {
            $result = $this->userModel->delete($userId);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Account deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete account'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}