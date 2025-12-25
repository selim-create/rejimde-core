<?php
namespace Rejimde\Services;

use Rejimde\Utils\TimezoneHelper;

/**
 * LedgerService
 * 
 * Manages points ledger operations
 */
class LedgerService {
    
    /**
     * Add points to user's ledger
     * 
     * @param int $user_id User ID
     * @param int $points Points delta (can be negative)
     * @param string $reason Reason for points change
     * @param int|null $event_id Related event ID
     * @param array $metadata Additional metadata
     * @return array|false Ledger entry or false on failure
     */
    public static function addPoints($user_id, $points, $reason, $event_id = null, $metadata = []) {
        try {
            // Check if tables exist
            if (class_exists(\Rejimde\Utils\DatabaseHelper::class)) {
                if (!\Rejimde\Utils\DatabaseHelper::isGamificationReady()) {
                    error_log("Rejimde LedgerService: Tables not ready");
                    return false;
                }
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'rejimde_points_ledger';
        
        // Get current balance
        $current_balance = self::getBalance($user_id);
        $new_balance = $current_balance + $points;
        
        // Insert ledger entry
        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'points_delta' => $points,
                'reason' => $reason,
                'related_event_id' => $event_id,
                'balance_after' => $new_balance,
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
                'created_at' => TimezoneHelper::formatForDB()
            ],
            ['%d', '%d', '%s', '%d', '%d', '%s', '%s']
        );
        
        if ($result === false) {
            return false;
        }
        
        $ledger_id = $wpdb->insert_id;
        
        // Update user meta with new total score
        update_user_meta($user_id, 'rejimde_total_score', $new_balance);
        
        return [
            'id' => $ledger_id,
            'user_id' => $user_id,
            'points_delta' => $points,
            'reason' => $reason,
            'balance_after' => $new_balance,
            'created_at' => TimezoneHelper::formatForDB()
        ];
        } catch (\Exception $e) {
            error_log('LedgerService::addPoints error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current points balance for a user
     * 
     * @param int $user_id User ID
     * @return int Current balance
     */
    public static function getBalance($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_points_ledger';
        
        // Get most recent balance
        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT balance_after FROM $table 
             WHERE user_id = %d 
             ORDER BY id DESC 
             LIMIT 1",
            $user_id
        ));
        
        if ($balance !== null) {
            return (int) $balance;
        }
        
        // Fallback to user meta (for backward compatibility)
        $meta_balance = get_user_meta($user_id, 'rejimde_total_score', true);
        return $meta_balance ? (int) $meta_balance : 0;
    }
    
    /**
     * Get ledger history for a user
     * 
     * @param int $user_id User ID
     * @param int $limit Number of entries to return
     * @param int $offset Offset for pagination
     * @return array Ledger entries
     */
    public static function getHistory($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_points_ledger';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d 
             ORDER BY created_at DESC, id DESC 
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ), ARRAY_A);
        
        // Decode metadata
        foreach ($results as &$entry) {
            if (!empty($entry['metadata'])) {
                $entry['metadata'] = json_decode($entry['metadata'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get ledger entries for a specific time period
     * 
     * @param int $user_id User ID
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array Ledger entries
     */
    public static function getEntriesByPeriod($user_id, $start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_points_ledger';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d 
             AND DATE(created_at) BETWEEN %s AND %s
             ORDER BY created_at ASC, id ASC",
            $user_id, $start_date, $end_date
        ), ARRAY_A);
        
        // Decode metadata
        foreach ($results as &$entry) {
            if (!empty($entry['metadata'])) {
                $entry['metadata'] = json_decode($entry['metadata'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get total points earned in a period
     * 
     * @param int $user_id User ID
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return int Total points
     */
    public static function getTotalByPeriod($user_id, $start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_points_ledger';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points_delta), 0) FROM $table 
             WHERE user_id = %d 
             AND DATE(created_at) BETWEEN %s AND %s
             AND points_delta > 0",
            $user_id, $start_date, $end_date
        ));
        
        return (int) $total;
    }
}
