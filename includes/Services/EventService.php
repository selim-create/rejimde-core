<?php
namespace Rejimde\Services;

use Rejimde\Utils\TimezoneHelper;
use Rejimde\Utils\IdempotencyHelper;

/**
 * EventService
 * 
 * Main event ingestion and processing service
 */
class EventService {
    
    /**
     * Ingest an event and process it
     * 
     * @param int $user_id User ID
     * @param string $event_type Event type
     * @param string|null $entity_type Entity type (e.g., 'blog', 'diet')
     * @param int|null $entity_id Entity ID
     * @param array $metadata Additional metadata
     * @param string $source Source of event (e.g., 'web', 'mobile')
     * @return array Result with event_id, points, messages, etc.
     */
    public static function ingestEvent($user_id, $event_type, $entity_type = null, $entity_id = null, $metadata = [], $source = 'web') {
        try {
            // Check if tables exist
            if (class_exists(\Rejimde\Utils\DatabaseHelper::class)) {
                if (!\Rejimde\Utils\DatabaseHelper::isGamificationReady()) {
                    // Fallback: log to error_log and return graceful response
                    error_log("Rejimde Gamification: Tables not ready, attempting to create...");
                    
                    try {
                        \Rejimde\Utils\DatabaseHelper::ensureTablesExist();
                    } catch (\Throwable $e) {
                        error_log('EventService::ingestEvent - ensureTablesExist error: ' . $e->getMessage());
                    }
                    
                    // Recheck
                    if (!\Rejimde\Utils\DatabaseHelper::isGamificationReady()) {
                        return [
                            'status' => 'error',
                            'event_id' => 0,
                            'awarded_points_total' => 0,
                            'awarded_ledger_items' => [],
                            'messages' => ['Sistem henüz hazır değil. Lütfen daha sonra tekrar deneyin.'],
                            'daily_remaining' => null,
                            'current_balance' => 0,
                            'code' => 503
                        ];
                    }
                }
            }
            
            global $wpdb;
            $events_table = $wpdb->prefix . 'rejimde_events';
            
            // Generate idempotency key
            $params = array_merge($metadata, [
                'entity_id' => $entity_id,
                'entity_type' => $entity_type
            ]);
            $idempotency_key = IdempotencyHelper::generate($event_type, $user_id, $params);
            
            // Check for duplicate
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id, status, points_awarded FROM $events_table WHERE idempotency_key = %s",
                $idempotency_key
            ), ARRAY_A);
            
            if ($existing) {
                // Return existing event info
                return [
                    'status' => 'duplicate',
                    'event_id' => $existing['id'],
                    'points_awarded' => (int) $existing['points_awarded'],
                    'message' => 'Bu aksiyon daha önce kaydedilmiş.',
                    'code' => 409
                ];
            }
            
            // Check daily limits
            $daily_limit = RuleEngine::getDailyLimit($event_type);
            $limit_exceeded = false;
            $daily_remaining = null;
            
            if ($daily_limit !== null) {
                $today = TimezoneHelper::getTodayTR();
                $count_today = self::getDailyCount($user_id, $event_type, $today);
                
                if ($count_today >= $daily_limit) {
                    $limit_exceeded = true;
                    $daily_remaining = 0;
                } else {
                    $daily_remaining = $daily_limit - $count_today - 1;
                }
            }
            
            // Calculate points
            $points = 0;
            $status = 'valid';
            $rejection_reason = null;
            
            if ($limit_exceeded) {
                $status = 'rejected';
                $rejection_reason = 'daily_limit_exceeded';
            } else {
                $points = RuleEngine::calculatePoints($event_type, $metadata);
            }
            
            // Insert event
            $occurred_at = TimezoneHelper::formatForDB();
            $wpdb->insert(
                $events_table,
                [
                    'user_id' => $user_id,
                    'event_type' => $event_type,
                    'entity_type' => $entity_type,
                    'entity_id' => $entity_id,
                    'occurred_at' => $occurred_at,
                    'metadata' => !empty($metadata) ? json_encode($metadata) : null,
                    'idempotency_key' => $idempotency_key,
                    'source' => $source,
                    'status' => $status,
                    'rejection_reason' => $rejection_reason,
                    'points_awarded' => $points,
                    'created_at' => $occurred_at
                ],
                ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
            );
            
            $event_id = $wpdb->insert_id;
            
            // Award points if valid
            $ledger_items = [];
            if ($status === 'valid' && $points > 0) {
                $ledger_entry = LedgerService::addPoints(
                    $user_id,
                    $points,
                    $event_type,
                    $event_id,
                    $metadata
                );
                
                if ($ledger_entry) {
                    $ledger_items[] = $ledger_entry;
                    
                    // Update circle score if user is in a circle
                    self::updateCircleScore($user_id, $points);
                }
            }
            
            // Generate message
            $message = '';
            if ($status === 'valid' && $points > 0) {
                $message = RuleEngine::getMessage($event_type, $points, $metadata);
            } elseif ($limit_exceeded) {
                $message = 'Günlük limit aşıldı.';
            }
            
            // Get current balance
            $current_balance = LedgerService::getBalance($user_id);
            
            return [
                'status' => 'success',
                'event_id' => $event_id,
                'event_type' => $event_type,
                'event_status' => $status,
                'awarded_points_total' => $points,
                'awarded_ledger_items' => $ledger_items,
                'messages' => $message ? [$message] : [],
                'daily_remaining' => $daily_remaining !== null ? [$event_type => $daily_remaining] : null,
                'current_balance' => $current_balance,
                'code' => 200
            ];
        } catch (\Throwable $e) {
            error_log('EventService::ingestEvent error: ' . $e->getMessage());
            error_log('EventService::ingestEvent trace: ' . $e->getTraceAsString());
            error_log('EventService::ingestEvent file: ' . $e->getFile() . ':' . $e->getLine());
            return [
                'status' => 'error',
                'event_id' => 0,
                'awarded_points_total' => 0,
                'awarded_ledger_items' => [],
                'messages' => ['Bir hata oluştu. Lütfen tekrar deneyin.'],
                'daily_remaining' => null,
                'current_balance' => 0,
                'code' => 500
            ];
        }
    }
    
    /**
     * Get daily count for a specific event type
     * 
     * @param int $user_id User ID
     * @param string $event_type Event type
     * @param string $date Date (Y-m-d)
     * @return int Count
     */
    private static function getDailyCount($user_id, $event_type, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_events';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE user_id = %d 
             AND event_type = %s 
             AND DATE(occurred_at) = %s
             AND status = 'valid'",
            $user_id, $event_type, $date
        ));
        
        return (int) $count;
    }
    
    /**
     * Update circle score when user earns points
     * 
     * @param int $user_id User ID
     * @param int $points Points earned
     */
    private static function updateCircleScore($user_id, $points) {
        // Find user's current circle
        $args = [
            'post_type' => 'rejimde_circle',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'members',
                    'value' => sprintf(':"%d";', $user_id),
                    'compare' => 'LIKE'
                ]
            ]
        ];
        
        $circles = get_posts($args);
        
        foreach ($circles as $circle) {
            $current_score = (int) get_post_meta($circle->ID, 'total_score', true);
            $new_score = $current_score + $points;
            update_post_meta($circle->ID, 'total_score', $new_score);
        }
    }
    
    /**
     * Get events for a user
     * 
     * @param int $user_id User ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Events
     */
    public static function getEvents($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_events';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d 
             ORDER BY occurred_at DESC, id DESC 
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ), ARRAY_A);
        
        // Decode metadata
        foreach ($results as &$event) {
            if (!empty($event['metadata'])) {
                $event['metadata'] = json_decode($event['metadata'], true);
            }
        }
        
        return $results;
    }
}
