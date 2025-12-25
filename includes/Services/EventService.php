<?php
namespace Rejimde\Services;

/**
 * Event Logging Service
 * 
 * Handles event logging to rejimde_events table
 */
class EventService {
    
    /**
     * Log an event to the database
     * 
     * @param int $userId User ID
     * @param string $eventType Event type (e.g., 'blog_points_claimed')
     * @param int $points Points earned
     * @param string|null $entityType Entity type (e.g., 'blog', 'diet')
     * @param int|null $entityId Entity ID
     * @param array|null $context Additional context data
     * @return int Inserted event ID
     */
    public function log(int $userId, string $eventType, int $points, ?string $entityType = null, ?int $entityId = null, ?array $context = null): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_events';
        
        $data = [
            'user_id' => $userId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'points' => $points,
            'context' => $context ? json_encode($context) : null,
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($table, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Check if an event already exists (for idempotency)
     * 
     * @param int $userId User ID
     * @param string $eventType Event type
     * @param string|null $entityType Entity type
     * @param int|null $entityId Entity ID
     * @param string|null $dateLimit Date limit (e.g., 'today', '2024-01-01')
     * @return bool
     */
    public function hasEvent(int $userId, string $eventType, ?string $entityType = null, ?int $entityId = null, ?string $dateLimit = null): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_events';
        
        // Build query with proper parameter handling
        $query = "SELECT COUNT(*) FROM $table WHERE user_id = %d AND event_type = %s";
        $params = [$userId, $eventType];
        
        // Add entity filters if provided
        // For per-entity limit checks, both entity_type and entity_id should be provided
        // For entity_type-only filtering, just entity_type is sufficient
        if ($entityType !== null && $entityId !== null) {
            $query .= " AND entity_type = %s AND entity_id = %d";
            $params[] = $entityType;
            $params[] = $entityId;
        } elseif ($entityType !== null) {
            $query .= " AND entity_type = %s";
            $params[] = $entityType;
        }
        
        // Add date filter if provided
        if ($dateLimit) {
            if ($dateLimit === 'today') {
                $dateLimit = date('Y-m-d');
            }
            $query .= " AND DATE(created_at) = %s";
            $params[] = $dateLimit;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($query, ...$params));
        
        return $count > 0;
    }
    
    /**
     * Count today's events for a specific event type
     * 
     * @param int $userId User ID
     * @param string $eventType Event type
     * @return int
     */
    public function countTodayEvents(int $userId, string $eventType): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_events';
        $today = date('Y-m-d');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND event_type = %s AND DATE(created_at) = %s",
            $userId, $eventType, $today
        ));
        
        return (int) $count;
    }
    
    /**
     * Get user event history
     * 
     * @param int $userId User ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array
     */
    public function getUserEvents(int $userId, int $limit = 50, int $offset = 0): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_events';
        
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $userId, $limit, $offset
        ), ARRAY_A);
        
        // Decode context JSON
        foreach ($events as &$event) {
            if (!empty($event['context'])) {
                $event['context'] = json_decode($event['context'], true);
            }
        }
        
        return $events;
    }
    
    /**
     * Check if a daily pair event exists (for highfive, etc.)
     * 
     * @param int $userId User ID
     * @param int $targetUserId Target user ID
     * @param string $eventType Event type
     * @return bool
     */
    public function hasDailyPairEvent(int $userId, int $targetUserId, string $eventType): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_events';
        $today = date('Y-m-d');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
            WHERE user_id = %d 
            AND event_type = %s 
            AND DATE(created_at) = %s
            AND JSON_EXTRACT(context, '$.target_user_id') = %d",
            $userId, $eventType, $today, $targetUserId
        ));
        
        return $count > 0;
    }
}
