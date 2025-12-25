<?php
namespace Rejimde\Utils;

/**
 * DatabaseHelper
 * 
 * Utility class for database table existence checks and validation
 */
class DatabaseHelper {
    
    private static $checked_tables = [];
    
    /**
     * Check if a table exists
     * 
     * @param string $table_name Table name (without prefix)
     * @return bool True if table exists, false otherwise
     */
    public static function tableExists($table_name) {
        try {
            global $wpdb;
            
            // Check cache
            if (isset(self::$checked_tables[$table_name])) {
                return self::$checked_tables[$table_name];
            }
            
            $full_table = $wpdb->prefix . $table_name;
            $result = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $full_table
            ));
            
            $exists = ($result === $full_table);
            self::$checked_tables[$table_name] = $exists;
            
            return $exists;
        } catch (\Throwable $e) {
            error_log('DatabaseHelper::tableExists error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if gamification tables are ready
     * 
     * @return bool True if all required tables exist, false otherwise
     */
    public static function isGamificationReady() {
        try {
            $required_tables = [
                'rejimde_events',
                'rejimde_points_ledger',
                'rejimde_daily_counters'
            ];
            
            foreach ($required_tables as $table) {
                if (!self::tableExists($table)) {
                    return false;
                }
            }
            
            return true;
        } catch (\Throwable $e) {
            error_log('DatabaseHelper::isGamificationReady error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ensure tables exist, create if needed
     * 
     * @return bool True if tables are ready after this call, false otherwise
     */
    public static function ensureTablesExist() {
        if (!self::isGamificationReady()) {
            if (class_exists(\Rejimde\Core\Activator::class)) {
                try {
                    \Rejimde\Core\Activator::activate();
                    self::$checked_tables = []; // Clear cache
                    return self::isGamificationReady();
                } catch (\Throwable $e) {
                    error_log('DatabaseHelper::ensureTablesExist error: ' . $e->getMessage());
                    return false;
                }
            }
            return false;
        }
        return true;
    }
}
