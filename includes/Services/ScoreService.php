<?php
namespace Rejimde\Services;

/**
 * Score Calculation and Management Service
 * 
 * Handles score calculations, limits, and user score updates
 */
class ScoreService {
    
    private $rules;
    private $featureFlags;
    
    public function __construct() {
        $config = require __DIR__ . '/../Config/ScoringRules.php';
        $this->rules = $config;
        $this->featureFlags = $config['feature_flags'] ?? [];
    }
    
    /**
     * Calculate points for an event
     * 
     * @param string $eventType Event type
     * @param array $context Context data
     * @return int Points to award
     */
    public function calculate(string $eventType, array $context = []): int {
        if (!isset($this->rules[$eventType])) {
            return 0;
        }
        
        $rule = $this->rules[$eventType];
        $points = $rule['points'] ?? 0;
        
        // Handle dynamic points
        if ($points === 'dynamic') {
            // Get points from entity meta
            if (isset($context['entity_id']) && isset($context['entity_type'])) {
                $entityId = $context['entity_id'];
                // Try multiple meta keys for compatibility
                $points = (int) get_post_meta($entityId, 'reward_points', true);
                
                // Fallback to score_reward (used by plans)
                if ($points === 0) {
                    $points = (int) get_post_meta($entityId, 'score_reward', true);
                }
                
                // Final fallback
                if ($points === 0) {
                    $points = 10; // Default fallback
                }
            }
        }
        // Handle array-based points (e.g., blog sticky vs normal)
        elseif (is_array($points)) {
            // For blog: check if sticky
            if ($eventType === 'blog_points_claimed') {
                $isSticky = $context['is_sticky'] ?? false;
                $points = $isSticky ? ($points['sticky'] ?? 50) : ($points['normal'] ?? 10);
            }
            // For follow: handled separately by dispatcher
            elseif ($eventType === 'follow_accepted') {
                $role = $context['role'] ?? 'follower';
                $points = $points[$role] ?? 1;
            }
        }
        
        return (int) $points;
    }
    
    /**
     * Check if user can earn points for an event
     * 
     * @param int $userId User ID
     * @param string $eventType Event type
     * @param int|null $entityId Entity ID
     * @param array $context Additional context
     * @return array ['allowed' => bool, 'reason' => string]
     */
    public function canEarnPoints(int $userId, string $eventType, ?int $entityId = null, array $context = []): array {
        // Check if event type exists
        if (!isset($this->rules[$eventType])) {
            return ['allowed' => false, 'reason' => 'Event type not found'];
        }
        
        $rule = $this->rules[$eventType];
        
        // Check feature flag
        if (isset($rule['feature_flag'])) {
            $flagName = $rule['feature_flag'];
            if (!($this->featureFlags[$flagName] ?? false)) {
                return ['allowed' => false, 'reason' => 'Feature is disabled'];
            }
        }
        
        // Check if points are 0 (logging only)
        if (($rule['points'] ?? 0) === 0 && !is_array($rule['points'])) {
            return ['allowed' => true, 'reason' => 'Logging only, no points'];
        }
        
        $eventService = new EventService();
        
        // Check daily limit
        if (isset($rule['daily_limit'])) {
            $todayCount = $eventService->countTodayEvents($userId, $eventType);
            if ($todayCount >= $rule['daily_limit']) {
                return ['allowed' => false, 'reason' => 'Daily limit reached'];
            }
        }
        
        // Check per-entity limit
        if (isset($rule['per_entity_limit']) && $entityId !== null) {
            $entityType = $context['entity_type'] ?? null;
            if ($eventService->hasEvent($userId, $eventType, $entityType, $entityId)) {
                return ['allowed' => false, 'reason' => 'Already earned points for this entity'];
            }
        }
        
        // Check daily pair limit (for highfive, etc.)
        if (isset($rule['daily_pair_limit']) && isset($context['target_user_id'])) {
            if ($eventService->hasDailyPairEvent($userId, $context['target_user_id'], $eventType)) {
                return ['allowed' => false, 'reason' => 'Already sent to this user today'];
            }
        }
        
        // Check daily score cap
        if ($this->featureFlags['enable_daily_score_cap'] ?? false) {
            $capValue = $this->featureFlags['daily_score_cap_value'] ?? 500;
            $dailyScore = $this->getDailyScore($userId);
            if ($dailyScore >= $capValue) {
                return ['allowed' => false, 'reason' => 'Daily score cap reached'];
            }
        }
        
        return ['allowed' => true, 'reason' => 'OK'];
    }
    
    /**
     * Award points to user
     * 
     * @param int $userId User ID
     * @param int $points Points to award
     * @param int|null $circleId Circle ID (optional)
     * @return array ['total_score' => int, 'daily_score' => int]
     */
    public function awardPoints(int $userId, int $points, ?int $circleId = null): array {
        // Update total score
        $currentTotal = (int) get_user_meta($userId, 'rejimde_total_score', true);
        $newTotal = $currentTotal + $points;
        update_user_meta($userId, 'rejimde_total_score', $newTotal);
        
        // Update daily score in rejimde_daily_logs
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_daily_logs';
        $today = date('Y-m-d');
        
        $dailyRow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND log_date = %s",
            $userId, $today
        ));
        
        if ($dailyRow) {
            $newDailyScore = $dailyRow->score_daily + $points;
            $wpdb->update(
                $table,
                ['score_daily' => $newDailyScore],
                ['id' => $dailyRow->id]
            );
        } else {
            $wpdb->insert($table, [
                'user_id' => $userId,
                'log_date' => $today,
                'score_daily' => $points
            ]);
            $newDailyScore = $points;
        }
        
        // Update circle score if applicable
        if ($circleId) {
            $circleScore = (int) get_post_meta($circleId, 'total_score', true);
            update_post_meta($circleId, 'total_score', $circleScore + $points);
        }
        
        return [
            'total_score' => $newTotal,
            'daily_score' => $newDailyScore
        ];
    }
    
    /**
     * Check if user is a pro user
     * 
     * @param int $userId User ID
     * @return bool
     */
    public function isProUser(int $userId): bool {
        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }
        
        return in_array('rejimde_pro', (array) $user->roles);
    }
    
    /**
     * Get user's daily score
     * 
     * @param int $userId User ID
     * @return int
     */
    public function getDailyScore(int $userId): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_daily_logs';
        $today = date('Y-m-d');
        
        $dailyRow = $wpdb->get_row($wpdb->prepare(
            "SELECT score_daily FROM $table WHERE user_id = %d AND log_date = %s",
            $userId, $today
        ));
        
        return $dailyRow ? (int) $dailyRow->score_daily : 0;
    }
}
