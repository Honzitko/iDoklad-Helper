<?php
/**
 * User management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_UserManager {
    
    public function __construct() {
        // Constructor can be used for additional initialization if needed
    }
    
    /**
     * Get all authorized users
     */
    public function get_all_users() {
        return IDokladProcessor_Database::get_all_authorized_users();
    }
    
    /**
     * Check if user is authorized
     */
    public function is_user_authorized($email) {
        $user = IDokladProcessor_Database::get_authorized_user($email);
        return $user !== null;
    }
    
    /**
     * Get user by email
     */
    public function get_user_by_email($email) {
        return IDokladProcessor_Database::get_authorized_user($email);
    }
}
