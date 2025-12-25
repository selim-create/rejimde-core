<?php
namespace Rejimde\Utils;

/**
 * ConfigHelper
 * 
 * Helper for reading/writing gamification configuration
 */
class ConfigHelper {
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public static function get($key, $default = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_gamification_config';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT config_value FROM $table WHERE config_key = %s",
            $key
        ));
        
        if ($result === null) {
            return $default;
        }
        
        // Try to decode JSON
        $decoded = json_decode($result, true);
        return ($decoded !== null) ? $decoded : $result;
    }
    
    /**
     * Set configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @param string $description Optional description
     * @return bool Success status
     */
    public static function set($key, $value, $description = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_gamification_config';
        
        // Encode arrays/objects as JSON
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE config_key = %s",
            $key
        ));
        
        if ($existing) {
            // Update
            $result = $wpdb->update(
                $table,
                [
                    'config_value' => $value,
                    'description' => $description
                ],
                ['config_key' => $key],
                ['%s', '%s'],
                ['%s']
            );
        } else {
            // Insert
            $result = $wpdb->insert(
                $table,
                [
                    'config_key' => $key,
                    'config_value' => $value,
                    'description' => $description
                ],
                ['%s', '%s', '%s']
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Delete configuration value
     * 
     * @param string $key Configuration key
     * @return bool Success status
     */
    public static function delete($key) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_gamification_config';
        
        $result = $wpdb->delete(
            $table,
            ['config_key' => $key],
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get all configuration values
     * 
     * @return array Associative array of all config values
     */
    public static function getAll() {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_gamification_config';
        
        $results = $wpdb->get_results(
            "SELECT config_key, config_value FROM $table",
            ARRAY_A
        );
        
        $config = [];
        foreach ($results as $row) {
            $decoded = json_decode($row['config_value'], true);
            $config[$row['config_key']] = ($decoded !== null) ? $decoded : $row['config_value'];
        }
        
        return $config;
    }
}
