<?php
namespace Rejimde\Services;

use Rejimde\Utils\TimezoneHelper;

/**
 * MilestoneService
 * 
 * Manages comment like milestone rewards
 */
class MilestoneService {
    
    // Milestone configuration: likes => points
    private static $milestones = [
        3 => 1,
        7 => 1,
        10 => 2,
        25 => 2,
        50 => 5,
        100 => 5,
        150 => 5,
        // After 150, every 50 likes gives 5 points
    ];
    
    /**
     * Check and award milestone for a comment
     * 
     * @param int $comment_id Comment ID
     * @param int $like_count Current like count
     * @return array|null Awarded milestone info or null
     */
    public static function checkAndAwardMilestone($comment_id, $like_count) {
        global $wpdb;
        $milestones_table = $wpdb->prefix . 'rejimde_comment_milestones';
        
        // Determine which milestone(s) to check
        $milestone_to_award = null;
        $points_to_award = 0;
        
        // Check predefined milestones
        foreach (self::$milestones as $milestone => $points) {
            if ($like_count >= $milestone) {
                // Check if already rewarded
                $already_rewarded = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $milestones_table WHERE comment_id = %d AND milestone = %d",
                    $comment_id, $milestone
                ));
                
                if (!$already_rewarded) {
                    $milestone_to_award = $milestone;
                    $points_to_award = $points;
                }
            }
        }
        
        // Check dynamic milestones (every 50 after 150)
        if (!$milestone_to_award && $like_count > 150) {
            // Calculate which 50-interval milestone this is
            if ($like_count % 50 === 0) {
                $milestone = $like_count;
                
                // Check if already rewarded
                $already_rewarded = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $milestones_table WHERE comment_id = %d AND milestone = %d",
                    $comment_id, $milestone
                ));
                
                if (!$already_rewarded) {
                    $milestone_to_award = $milestone;
                    $points_to_award = 5;
                }
            }
        }
        
        // Award milestone if found
        if ($milestone_to_award) {
            // Get comment author
            $comment = get_comment($comment_id);
            if (!$comment) {
                return null;
            }
            
            $author_id = $comment->user_id;
            
            // Record milestone
            $wpdb->insert(
                $milestones_table,
                [
                    'comment_id' => $comment_id,
                    'milestone' => $milestone_to_award,
                    'rewarded_user_id' => $author_id,
                    'points_awarded' => $points_to_award,
                    'created_at' => TimezoneHelper::formatForDB()
                ],
                ['%d', '%d', '%d', '%d', '%s']
            );
            
            // Create event for the milestone
            EventService::ingestEvent(
                $author_id,
                'comment_like_milestone_rewarded',
                'comment',
                $comment_id,
                [
                    'milestone' => $milestone_to_award,
                    'points' => $points_to_award
                ],
                'system'
            );
            
            return [
                'milestone' => $milestone_to_award,
                'points' => $points_to_award,
                'author_id' => $author_id
            ];
        }
        
        return null;
    }
    
    /**
     * Get all milestones for a comment
     * 
     * @param int $comment_id Comment ID
     * @return array Milestones
     */
    public static function getMilestones($comment_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_comment_milestones';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE comment_id = %d ORDER BY milestone ASC",
            $comment_id
        ), ARRAY_A);
    }
    
    /**
     * Get next milestone for a comment
     * 
     * @param int $like_count Current like count
     * @return array|null Next milestone info
     */
    public static function getNextMilestone($like_count) {
        // Find next predefined milestone
        foreach (self::$milestones as $milestone => $points) {
            if ($like_count < $milestone) {
                return [
                    'milestone' => $milestone,
                    'points' => $points,
                    'remaining' => $milestone - $like_count
                ];
            }
        }
        
        // Calculate next 50-interval milestone
        if ($like_count >= 150) {
            $next_milestone = ceil(($like_count + 1) / 50) * 50;
            return [
                'milestone' => $next_milestone,
                'points' => 5,
                'remaining' => $next_milestone - $like_count
            ];
        }
        
        return null;
    }
}
