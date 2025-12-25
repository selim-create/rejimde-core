<?php
namespace Rejimde\Services;

use Rejimde\Utils\TimezoneHelper;

/**
 * LevelService
 * 
 * Level/League management service
 */
class LevelService {
    
    /**
     * Get user's current level
     * 
     * @param int $user_id User ID
     * @return array|null Current level info
     */
    public static function getUserCurrentLevel($user_id) {
        global $wpdb;
        $user_levels_table = $wpdb->prefix . 'rejimde_user_levels';
        $levels_table = $wpdb->prefix . 'rejimde_levels';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT ul.*, l.name, l.slug, l.rank_order, l.min_score, l.max_score, 
                    l.icon, l.color, l.description
             FROM $user_levels_table ul
             INNER JOIN $levels_table l ON ul.level_id = l.id
             WHERE ul.user_id = %d AND ul.is_current = 1
             ORDER BY ul.joined_at DESC
             LIMIT 1",
            $user_id
        ), ARRAY_A);
        
        return $result;
    }
    
    /**
     * Assign user to a level
     * 
     * @param int $user_id User ID
     * @param int $level_id Level ID
     * @param string $transition_type 'initial', 'promote', 'demote', 'retain'
     * @param string|null $week_id Week ID (YYYY-MM-DD format)
     * @return bool Success
     */
    public static function assignUserToLevel($user_id, $level_id, $transition_type = 'initial', $week_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_levels';
        
        // Close current level
        $wpdb->update(
            $table,
            [
                'is_current' => 0,
                'left_at' => TimezoneHelper::formatForDB()
            ],
            [
                'user_id' => $user_id,
                'is_current' => 1
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );
        
        // Assign new level
        return $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'level_id' => $level_id,
                'joined_at' => TimezoneHelper::formatForDB(),
                'is_current' => 1,
                'transition_type' => $transition_type,
                'week_id' => $week_id
            ],
            ['%d', '%d', '%s', '%d', '%s', '%s']
        ) !== false;
    }
    
    /**
     * Calculate level positions for a week
     * 
     * @param int $level_id Level ID
     * @param string $week_start Week start date (Y-m-d)
     * @param string $week_end Week end date (Y-m-d)
     * @return array Users with their positions
     */
    public static function calculateLevelPositions($level_id, $week_start, $week_end) {
        global $wpdb;
        $user_levels_table = $wpdb->prefix . 'rejimde_user_levels';
        
        // Get all users in this level
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM $user_levels_table 
             WHERE level_id = %d AND is_current = 1",
            $level_id
        ), ARRAY_A);
        
        // Calculate scores for each user
        $scores = [];
        foreach ($users as $user) {
            $user_id = $user['user_id'];
            $score = ScoreService::calculateWeeklyScore($user_id, $week_start, $week_end);
            $scores[] = [
                'user_id' => $user_id,
                'score' => $score
            ];
        }
        
        // Sort by score descending
        usort($scores, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Assign positions
        $position = 1;
        foreach ($scores as &$entry) {
            $entry['position'] = $position++;
        }
        
        return $scores;
    }
    
    /**
     * Apply position rewards (1st: +50, 2nd: +25, 3rd: +15)
     * 
     * @param int $level_id Level ID
     * @param string $week_start Week start date (Y-m-d)
     * @return bool Success
     */
    public static function applyPositionRewards($level_id, $week_start) {
        $week_bounds = TimezoneHelper::getWeekBoundsTR();
        $week_end = $week_bounds['end'];
        
        $positions = self::calculateLevelPositions($level_id, $week_start, $week_end);
        
        $rewards = [
            1 => 50,
            2 => 25,
            3 => 15
        ];
        
        foreach ($positions as $entry) {
            $position = $entry['position'];
            if (isset($rewards[$position])) {
                $points = $rewards[$position];
                $user_id = $entry['user_id'];
                
                // Award points via event
                EventService::ingestEvent(
                    $user_id,
                    'level_position_rewarded',
                    'level',
                    $level_id,
                    [
                        'position' => $position,
                        'points' => $points,
                        'week_start' => $week_start
                    ],
                    'system'
                );
            }
        }
        
        return true;
    }
    
    /**
     * Apply promote/demote logic (top 5 promote, bottom 5 demote)
     * 
     * @param int $level_id Level ID
     * @param string $week_start Week start date (Y-m-d)
     * @return bool Success
     */
    public static function applyPromoteDemote($level_id, $week_start) {
        global $wpdb;
        $levels_table = $wpdb->prefix . 'rejimde_levels';
        
        $week_bounds = TimezoneHelper::getWeekBoundsTR();
        $week_end = $week_bounds['end'];
        
        $positions = self::calculateLevelPositions($level_id, $week_start, $week_end);
        
        // Get current level info
        $current_level = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $levels_table WHERE id = %d",
            $level_id
        ), ARRAY_A);
        
        if (!$current_level) {
            return false;
        }
        
        // Get higher level (lower rank_order)
        $higher_level = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $levels_table WHERE rank_order < %d ORDER BY rank_order DESC LIMIT 1",
            $current_level['rank_order']
        ), ARRAY_A);
        
        // Get lower level (higher rank_order)
        $lower_level = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $levels_table WHERE rank_order > %d ORDER BY rank_order ASC LIMIT 1",
            $current_level['rank_order']
        ), ARRAY_A);
        
        $total_users = count($positions);
        
        foreach ($positions as $entry) {
            $user_id = $entry['user_id'];
            $position = $entry['position'];
            
            $transition = 'retain';
            $new_level_id = $level_id;
            
            // Top 5 promote (if higher level exists)
            if ($position <= 5 && $higher_level) {
                $transition = 'promote';
                $new_level_id = $higher_level['id'];
            }
            // Bottom 5 demote (if lower level exists)
            elseif ($position > ($total_users - 5) && $lower_level) {
                $transition = 'demote';
                $new_level_id = $lower_level['id'];
            }
            
            // Create event
            EventService::ingestEvent(
                $user_id,
                'level_' . $transition,
                'level',
                $level_id,
                [
                    'week_start' => $week_start,
                    'old_level_id' => $level_id,
                    'new_level_id' => $new_level_id
                ],
                'system'
            );
            
            // Update user level if changed
            if ($new_level_id !== $level_id) {
                self::assignUserToLevel($user_id, $new_level_id, $transition, $week_start);
            }
        }
        
        return true;
    }
    
    /**
     * Save level snapshot for a user
     * 
     * @param int $user_id User ID
     * @param int $level_id Level ID
     * @param string $week_start Week start date (Y-m-d)
     * @param string $week_end Week end date (Y-m-d)
     * @param int $weekly_score Weekly score
     * @param int $position Position in level
     * @param int $position_reward Reward for position
     * @param string|null $transition Transition (promote/demote/retain)
     * @return bool Success
     */
    public static function saveLevelSnapshot($user_id, $level_id, $week_start, $week_end, $weekly_score, $position, $position_reward = 0, $transition = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_level_snapshots';
        
        // Check if exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND level_id = %d AND week_start = %s",
            $user_id, $level_id, $week_start
        ));
        
        if ($existing) {
            return $wpdb->update(
                $table,
                [
                    'weekly_score' => $weekly_score,
                    'position' => $position,
                    'position_reward' => $position_reward,
                    'transition' => $transition,
                    'week_end' => $week_end
                ],
                ['id' => $existing],
                ['%d', '%d', '%d', '%s', '%s'],
                ['%d']
            ) !== false;
        } else {
            return $wpdb->insert(
                $table,
                [
                    'user_id' => $user_id,
                    'level_id' => $level_id,
                    'week_start' => $week_start,
                    'week_end' => $week_end,
                    'weekly_score' => $weekly_score,
                    'position' => $position,
                    'position_reward' => $position_reward,
                    'transition' => $transition,
                    'created_at' => TimezoneHelper::formatForDB()
                ],
                ['%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
            ) !== false;
        }
    }
    
    /**
     * Get all levels
     * 
     * @return array All levels ordered by rank
     */
    public static function getAllLevels() {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_levels';
        
        return $wpdb->get_results(
            "SELECT * FROM $table ORDER BY rank_order ASC",
            ARRAY_A
        );
    }
    
    /**
     * Determine appropriate level for a score
     * 
     * @param int $total_score Total score
     * @return array|null Level info
     */
    public static function getLevelForScore($total_score) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_levels';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE min_score <= %d 
             AND (max_score IS NULL OR max_score >= %d)
             ORDER BY rank_order ASC
             LIMIT 1",
            $total_score, $total_score
        ), ARRAY_A);
    }
}
