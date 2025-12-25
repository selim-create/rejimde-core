<?php
namespace Rejimde\Services;

use Rejimde\Utils\TimezoneHelper;

/**
 * ScoreService
 * 
 * Score calculation and snapshot management
 */
class ScoreService {
    
    /**
     * Calculate weekly score for a user
     * 
     * @param int $user_id User ID
     * @param string $week_start Week start date (Y-m-d)
     * @param string $week_end Week end date (Y-m-d)
     * @return int Weekly score
     */
    public static function calculateWeeklyScore($user_id, $week_start, $week_end) {
        return LedgerService::getTotalByPeriod($user_id, $week_start, $week_end);
    }
    
    /**
     * Calculate monthly score for a user
     * 
     * @param int $user_id User ID
     * @param string $month_start Month start date (Y-m-d)
     * @param string $month_end Month end date (Y-m-d)
     * @return int Monthly score
     */
    public static function calculateMonthlyScore($user_id, $month_start, $month_end) {
        return LedgerService::getTotalByPeriod($user_id, $month_start, $month_end);
    }
    
    /**
     * Save user score snapshot
     * 
     * @param int $user_id User ID
     * @param string $period_type 'weekly' or 'monthly'
     * @param string $start Start date (Y-m-d)
     * @param string $end End date (Y-m-d)
     * @param int|null $score Score (calculated if null)
     * @return bool Success
     */
    public static function saveUserScoreSnapshot($user_id, $period_type, $start, $end, $score = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_scores';
        
        if ($score === null) {
            $score = LedgerService::getTotalByPeriod($user_id, $start, $end);
        }
        
        // Check if snapshot exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND period_type = %s AND period_start = %s",
            $user_id, $period_type, $start
        ));
        
        if ($existing) {
            // Update
            return $wpdb->update(
                $table,
                [
                    'score' => $score,
                    'period_end' => $end
                ],
                ['id' => $existing],
                ['%d', '%s'],
                ['%d']
            ) !== false;
        } else {
            // Insert
            return $wpdb->insert(
                $table,
                [
                    'user_id' => $user_id,
                    'period_type' => $period_type,
                    'period_start' => $start,
                    'period_end' => $end,
                    'score' => $score,
                    'created_at' => TimezoneHelper::formatForDB()
                ],
                ['%d', '%s', '%s', '%s', '%d', '%s']
            ) !== false;
        }
    }
    
    /**
     * Calculate and save rankings for a period
     * 
     * @param string $period_type 'weekly' or 'monthly'
     * @param string $start Start date (Y-m-d)
     * @return bool Success
     */
    public static function calculateRankings($period_type, $start) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_scores';
        
        // Get all scores for this period, ordered by score desc
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, score FROM $table 
             WHERE period_type = %s AND period_start = %s
             ORDER BY score DESC, id ASC",
            $period_type, $start
        ), ARRAY_A);
        
        // Assign ranks
        $rank = 1;
        foreach ($results as $row) {
            $wpdb->update(
                $table,
                ['rank_position' => $rank],
                ['id' => $row['id']],
                ['%d'],
                ['%d']
            );
            $rank++;
        }
        
        return true;
    }
    
    /**
     * Save circle score snapshot
     * 
     * @param int $circle_id Circle ID
     * @param string $period_type 'weekly' or 'monthly'
     * @param string $start Start date (Y-m-d)
     * @param string $end End date (Y-m-d)
     * @return bool Success
     */
    public static function saveCircleScoreSnapshot($circle_id, $period_type, $start, $end) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_circle_scores';
        
        // Get circle members
        $members = get_post_meta($circle_id, 'members', true);
        if (!is_array($members)) {
            $members = [];
        }
        
        $total_points = 0;
        $total_score = 0;
        
        // Calculate total points and score for all members
        foreach ($members as $member_id) {
            $member_points = LedgerService::getTotalByPeriod($member_id, $start, $end);
            $total_points += $member_points;
            $total_score += $member_points; // For now, score = points
        }
        
        // Check if snapshot exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE circle_id = %d AND period_type = %s AND period_start = %s",
            $circle_id, $period_type, $start
        ));
        
        if ($existing) {
            // Update
            return $wpdb->update(
                $table,
                [
                    'total_points' => $total_points,
                    'total_score' => $total_score,
                    'member_count' => count($members),
                    'period_end' => $end
                ],
                ['id' => $existing],
                ['%d', '%d', '%d', '%s'],
                ['%d']
            ) !== false;
        } else {
            // Insert
            return $wpdb->insert(
                $table,
                [
                    'circle_id' => $circle_id,
                    'period_type' => $period_type,
                    'period_start' => $start,
                    'period_end' => $end,
                    'total_points' => $total_points,
                    'total_score' => $total_score,
                    'member_count' => count($members),
                    'created_at' => TimezoneHelper::formatForDB()
                ],
                ['%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
            ) !== false;
        }
    }
    
    /**
     * Get user score for a specific period
     * 
     * @param int $user_id User ID
     * @param string $period_type 'weekly' or 'monthly'
     * @param string $start Start date (Y-m-d)
     * @return array|null Score data
     */
    public static function getUserScore($user_id, $period_type, $start) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_scores';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND period_type = %s AND period_start = %s",
            $user_id, $period_type, $start
        ), ARRAY_A);
    }
    
    /**
     * Get top users for a period
     * 
     * @param string $period_type 'weekly' or 'monthly'
     * @param string $start Start date (Y-m-d)
     * @param int $limit Number of results
     * @return array Top users
     */
    public static function getTopUsers($period_type, $start, $limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_scores';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE period_type = %s AND period_start = %s
             ORDER BY score DESC, id ASC
             LIMIT %d",
            $period_type, $start, $limit
        ), ARRAY_A);
    }
}
