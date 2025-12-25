<?php
namespace Rejimde\Services;

/**
 * Milestone Achievement Service
 * 
 * Handles milestone tracking and rewards (idempotent)
 */
class MilestoneService {
    
    private $commentLikeMilestones;
    
    public function __construct() {
        $config = require __DIR__ . '/../Config/ScoringRules.php';
        $this->commentLikeMilestones = $config['comment_like_milestones'] ?? [];
    }
    
    /**
     * Check and award milestone if applicable
     * 
     * @param int $userId User ID
     * @param string $milestoneType Milestone type
     * @param int $entityId Entity ID
     * @param int $currentValue Current value (e.g., like count)
     * @return array|null ['milestone_value' => int, 'points' => int] or null
     */
    public function checkAndAward(int $userId, string $milestoneType, int $entityId, int $currentValue): ?array {
        // Check if we've reached a milestone threshold
        $milestoneValue = null;
        $points = null;
        
        if ($milestoneType === 'comment_likes') {
            $points = $this->getCommentLikeMilestonePoints($currentValue);
            if ($points !== null) {
                $milestoneValue = $currentValue;
            }
        }
        
        if ($milestoneValue === null || $points === null) {
            return null;
        }
        
        // Check if this milestone was already awarded (idempotent)
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_milestones';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND milestone_type = %s AND entity_id = %d AND milestone_value = %d",
            $userId, $milestoneType, $entityId, $milestoneValue
        ));
        
        if ($existing) {
            // Already awarded
            return null;
        }
        
        // Award milestone
        $wpdb->insert($table, [
            'user_id' => $userId,
            'milestone_type' => $milestoneType,
            'entity_id' => $entityId,
            'milestone_value' => $milestoneValue,
            'points_awarded' => $points,
            'awarded_at' => current_time('mysql')
        ]);
        
        return [
            'milestone_value' => $milestoneValue,
            'points' => $points
        ];
    }
    
    /**
     * Get milestone points for comment likes
     * 
     * @param int $likeCount Like count
     * @return int|null Points to award or null if not a milestone
     */
    public function getCommentLikeMilestonePoints(int $likeCount): ?int {
        // Check if this is a defined milestone
        if (isset($this->commentLikeMilestones[$likeCount])) {
            return $this->commentLikeMilestones[$likeCount];
        }
        
        // Check if 150+ (every 50 = +5 points)
        if ($likeCount >= 150 && $likeCount % 50 === 0) {
            return 5;
        }
        
        return null;
    }
    
    /**
     * Get user's earned milestones
     * 
     * @param int $userId User ID
     * @param int $limit Limit
     * @return array
     */
    public function getUserMilestones(int $userId, int $limit = 50): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_milestones';
        
        $milestones = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY awarded_at DESC LIMIT %d",
            $userId, $limit
        ), ARRAY_A);
        
        return $milestones;
    }
    
    /**
     * Get total milestone points for a user
     * 
     * @param int $userId User ID
     * @return int
     */
    public function getTotalMilestonePoints(int $userId): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_milestones';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_awarded) FROM $table WHERE user_id = %d",
            $userId
        ));
        
        return (int) $total;
    }
}
