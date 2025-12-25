<?php
namespace Rejimde\Services;

use Rejimde\Utils\TimezoneHelper;

/**
 * ScheduledJobs
 * 
 * WP-Cron jobs for weekly and monthly close operations
 */
class ScheduledJobs {
    
    /**
     * Initialize scheduled jobs
     */
    public static function init() {
        // Schedule weekly close job (Mondays at 00:05 Turkey time)
        add_action('rejimde_weekly_close', [__CLASS__, 'runWeeklyClose']);
        
        if (!wp_next_scheduled('rejimde_weekly_close')) {
            // Calculate next Monday 00:05 in Turkey time
            $next_run = self::getNextMonday();
            wp_schedule_event($next_run, 'weekly', 'rejimde_weekly_close');
        }
        
        // Schedule monthly close job (1st of month at 00:10 Turkey time)
        add_action('rejimde_monthly_close', [__CLASS__, 'runMonthlyClose']);
        
        if (!wp_next_scheduled('rejimde_monthly_close')) {
            // Calculate next 1st of month 00:10 in Turkey time
            $next_run = self::getNextMonthFirst();
            wp_schedule_event($next_run, 'monthly', 'rejimde_monthly_close');
        }
    }
    
    /**
     * Get next Monday 00:05 Turkey time in UTC timestamp
     * 
     * @return int UTC timestamp
     */
    private static function getNextMonday() {
        $tr_now = TimezoneHelper::getNowTR();
        
        // Get next Monday
        $next_monday = clone $tr_now;
        $next_monday->modify('next Monday');
        $next_monday->setTime(0, 5, 0);
        
        return $next_monday->getTimestamp();
    }
    
    /**
     * Get next 1st of month 00:10 Turkey time in UTC timestamp
     * 
     * @return int UTC timestamp
     */
    private static function getNextMonthFirst() {
        $tr_now = TimezoneHelper::getNowTR();
        
        // Get first day of next month
        $first_of_next = clone $tr_now;
        $first_of_next->modify('first day of next month');
        $first_of_next->setTime(0, 10, 0);
        
        return $first_of_next->getTimestamp();
    }
    
    /**
     * Run weekly close operations
     * - Calculate weekly scores
     * - Process level positions and rewards
     * - Apply promote/demote logic
     * - Save snapshots
     */
    public static function runWeeklyClose() {
        global $wpdb;
        
        // Get last week's boundaries
        $tr_now = TimezoneHelper::getNowTR();
        $tr_now->modify('-7 days'); // Go back to last week
        $week_bounds = TimezoneHelper::getWeekBoundsTR($tr_now);
        $week_start = $week_bounds['start'];
        $week_end = $week_bounds['end'];
        
        error_log("Rejimde: Running weekly close for week {$week_start} to {$week_end}");
        
        // 1. Calculate weekly scores for all users
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $user_id) {
            $weekly_score = ScoreService::calculateWeeklyScore($user_id, $week_start, $week_end);
            
            if ($weekly_score > 0) {
                // Save snapshot
                ScoreService::saveUserScoreSnapshot($user_id, 'weekly', $week_start, $week_end, $weekly_score);
                
                // Create event
                EventService::ingestEvent(
                    $user_id,
                    'weekly_score_calculated',
                    null,
                    null,
                    [
                        'week_start' => $week_start,
                        'week_end' => $week_end,
                        'score' => $weekly_score
                    ],
                    'system'
                );
            }
        }
        
        // 2. Calculate rankings
        ScoreService::calculateRankings('weekly', $week_start);
        
        // 3. Process each level
        $levels = LevelService::getAllLevels();
        foreach ($levels as $level) {
            $level_id = $level['id'];
            
            // Calculate positions
            $positions = LevelService::calculateLevelPositions($level_id, $week_start, $week_end);
            
            foreach ($positions as $entry) {
                $user_id = $entry['user_id'];
                $position = $entry['position'];
                $score = $entry['score'];
                
                // Determine position reward
                $position_reward = 0;
                if ($position === 1) $position_reward = 50;
                elseif ($position === 2) $position_reward = 25;
                elseif ($position === 3) $position_reward = 15;
                
                // Save level snapshot
                LevelService::saveLevelSnapshot(
                    $user_id,
                    $level_id,
                    $week_start,
                    $week_end,
                    $score,
                    $position,
                    $position_reward
                );
                
                // Create level_week_completed event
                EventService::ingestEvent(
                    $user_id,
                    'level_week_completed',
                    'level',
                    $level_id,
                    [
                        'level_id' => $level_id,
                        'week_start' => $week_start,
                        'position' => $position,
                        'score' => $score
                    ],
                    'system'
                );
            }
            
            // 4. Apply position rewards (1st, 2nd, 3rd)
            LevelService::applyPositionRewards($level_id, $week_start);
            
            // 5. Apply promote/demote logic
            LevelService::applyPromoteDemote($level_id, $week_start);
        }
        
        // 6. Calculate circle scores
        $circles = get_posts([
            'post_type' => 'rejimde_circle',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        foreach ($circles as $circle_id) {
            ScoreService::saveCircleScoreSnapshot($circle_id, 'weekly', $week_start, $week_end);
        }
        
        error_log("Rejimde: Weekly close completed for week {$week_start} to {$week_end}");
    }
    
    /**
     * Run monthly close operations
     * - Calculate monthly scores
     * - Save snapshots
     */
    public static function runMonthlyClose() {
        // Get last month's boundaries
        $tr_now = TimezoneHelper::getNowTR();
        $tr_now->modify('-1 month');
        $month_bounds = TimezoneHelper::getMonthBoundsTR($tr_now);
        $month_start = $month_bounds['start'];
        $month_end = $month_bounds['end'];
        
        error_log("Rejimde: Running monthly close for month {$month_start} to {$month_end}");
        
        // 1. Calculate monthly scores for all users
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $user_id) {
            $monthly_score = ScoreService::calculateMonthlyScore($user_id, $month_start, $month_end);
            
            if ($monthly_score > 0) {
                ScoreService::saveUserScoreSnapshot($user_id, 'monthly', $month_start, $month_end, $monthly_score);
            }
        }
        
        // 2. Calculate rankings
        ScoreService::calculateRankings('monthly', $month_start);
        
        // 3. Calculate circle scores
        $circles = get_posts([
            'post_type' => 'rejimde_circle',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        foreach ($circles as $circle_id) {
            ScoreService::saveCircleScoreSnapshot($circle_id, 'monthly', $month_start, $month_end);
        }
        
        error_log("Rejimde: Monthly close completed for month {$month_start} to {$month_end}");
    }
}
