<?php
namespace Rejimde\Cron;

/**
 * Score Aggregator
 * 
 * Creates periodic snapshots and handles data cleanup
 */
class ScoreAggregator {
    
    /**
     * Register cron jobs
     * 
     * @return void
     */
    public function register(): void {
        // Daily snapshot (runs at midnight)
        if (!wp_next_scheduled('rejimde_create_daily_snapshots')) {
            wp_schedule_event(strtotime('tomorrow'), 'daily', 'rejimde_create_daily_snapshots');
        }
        add_action('rejimde_create_daily_snapshots', [$this, 'createDailySnapshots']);
        
        // Weekly snapshot (runs on Monday)
        if (!wp_next_scheduled('rejimde_create_weekly_snapshots')) {
            wp_schedule_event(strtotime('next Monday'), 'weekly', 'rejimde_create_weekly_snapshots');
        }
        add_action('rejimde_create_weekly_snapshots', [$this, 'createWeeklySnapshots']);
        
        // Monthly snapshot (runs on 1st of month)
        if (!wp_next_scheduled('rejimde_create_monthly_snapshots')) {
            wp_schedule_event(strtotime('first day of next month'), 'monthly', 'rejimde_create_monthly_snapshots');
        }
        add_action('rejimde_create_monthly_snapshots', [$this, 'createMonthlySnapshots']);
        
        // Weekly grace reset (runs on Monday)
        if (!wp_next_scheduled('rejimde_reset_weekly_grace')) {
            wp_schedule_event(strtotime('next Monday'), 'weekly', 'rejimde_reset_weekly_grace');
        }
        add_action('rejimde_reset_weekly_grace', [$this, 'resetWeeklyGrace']);
        
        // Cleanup old events (runs weekly)
        if (!wp_next_scheduled('rejimde_cleanup_old_events')) {
            wp_schedule_event(strtotime('next Sunday'), 'weekly', 'rejimde_cleanup_old_events');
        }
        add_action('rejimde_cleanup_old_events', [$this, 'cleanupOldEvents']);
    }
    
    /**
     * Create daily snapshots for all users
     * 
     * @return void
     */
    public function createDailySnapshots(): void {
        global $wpdb;
        $eventsTable = $wpdb->prefix . 'rejimde_events';
        $snapshotsTable = $wpdb->prefix . 'rejimde_score_snapshots';
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $periodKey = date('Y-m-d', strtotime('-1 day'));
        
        // Get all users who had events yesterday
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT user_id FROM $eventsTable WHERE DATE(created_at) = %s",
            $yesterday
        ));
        
        foreach ($users as $user) {
            $userId = $user->user_id;
            
            // Calculate daily score
            $dailyScore = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(points) FROM $eventsTable WHERE user_id = %d AND DATE(created_at) = %s",
                $userId, $yesterday
            ));
            
            // Get event counts
            $eventCounts = $wpdb->get_results($wpdb->prepare(
                "SELECT event_type, COUNT(*) as count FROM $eventsTable 
                WHERE user_id = %d AND DATE(created_at) = %s 
                GROUP BY event_type",
                $userId, $yesterday
            ), ARRAY_A);
            
            $eventCountsJson = [];
            foreach ($eventCounts as $event) {
                $eventCountsJson[$event['event_type']] = (int) $event['count'];
            }
            
            // Insert or update snapshot
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $snapshotsTable WHERE user_id = %d AND period_type = 'daily' AND period_key = %s",
                $userId, $periodKey
            ));
            
            if ($existing) {
                $wpdb->update($snapshotsTable, [
                    'score' => (int) $dailyScore,
                    'event_counts' => json_encode($eventCountsJson)
                ], ['id' => $existing->id]);
            } else {
                $wpdb->insert($snapshotsTable, [
                    'user_id' => $userId,
                    'period_type' => 'daily',
                    'period_key' => $periodKey,
                    'score' => (int) $dailyScore,
                    'event_counts' => json_encode($eventCountsJson)
                ]);
            }
        }
    }
    
    /**
     * Create weekly snapshots and calculate ranks
     * 
     * @return void
     */
    public function createWeeklySnapshots(): void {
        global $wpdb;
        $eventsTable = $wpdb->prefix . 'rejimde_events';
        $snapshotsTable = $wpdb->prefix . 'rejimde_score_snapshots';
        
        // Get week number and year
        $periodKey = date('Y-W', strtotime('-1 week'));
        $weekStart = date('Y-m-d', strtotime('last Monday -1 week'));
        $weekEnd = date('Y-m-d', strtotime('last Sunday'));
        
        // Get all users who had events last week
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT user_id FROM $eventsTable WHERE DATE(created_at) BETWEEN %s AND %s",
            $weekStart, $weekEnd
        ));
        
        $userScores = [];
        
        foreach ($users as $user) {
            $userId = $user->user_id;
            
            // Calculate weekly score
            $weeklyScore = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(points) FROM $eventsTable WHERE user_id = %d AND DATE(created_at) BETWEEN %s AND %s",
                $userId, $weekStart, $weekEnd
            ));
            
            $userScores[$userId] = (int) $weeklyScore;
        }
        
        // Sort by score to calculate ranks
        arsort($userScores);
        $rank = 1;
        
        foreach ($userScores as $userId => $score) {
            // Get event counts
            $eventCounts = $wpdb->get_results($wpdb->prepare(
                "SELECT event_type, COUNT(*) as count FROM $eventsTable 
                WHERE user_id = %d AND DATE(created_at) BETWEEN %s AND %s 
                GROUP BY event_type",
                $userId, $weekStart, $weekEnd
            ), ARRAY_A);
            
            $eventCountsJson = [];
            foreach ($eventCounts as $event) {
                $eventCountsJson[$event['event_type']] = (int) $event['count'];
            }
            
            // Insert snapshot
            $wpdb->replace($snapshotsTable, [
                'user_id' => $userId,
                'period_type' => 'weekly',
                'period_key' => $periodKey,
                'score' => $score,
                'rank_position' => $rank,
                'event_counts' => json_encode($eventCountsJson)
            ]);
            
            $rank++;
        }
    }
    
    /**
     * Create monthly snapshots
     * 
     * @return void
     */
    public function createMonthlySnapshots(): void {
        global $wpdb;
        $eventsTable = $wpdb->prefix . 'rejimde_events';
        $snapshotsTable = $wpdb->prefix . 'rejimde_score_snapshots';
        
        // Get previous month
        $periodKey = date('Y-m', strtotime('first day of last month'));
        $monthStart = date('Y-m-01', strtotime('first day of last month'));
        $monthEnd = date('Y-m-t', strtotime('first day of last month'));
        
        // Get all users who had events last month
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT user_id FROM $eventsTable WHERE DATE(created_at) BETWEEN %s AND %s",
            $monthStart, $monthEnd
        ));
        
        foreach ($users as $user) {
            $userId = $user->user_id;
            
            // Calculate monthly score
            $monthlyScore = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(points) FROM $eventsTable WHERE user_id = %d AND DATE(created_at) BETWEEN %s AND %s",
                $userId, $monthStart, $monthEnd
            ));
            
            // Get event counts
            $eventCounts = $wpdb->get_results($wpdb->prepare(
                "SELECT event_type, COUNT(*) as count FROM $eventsTable 
                WHERE user_id = %d AND DATE(created_at) BETWEEN %s AND %s 
                GROUP BY event_type",
                $userId, $monthStart, $monthEnd
            ), ARRAY_A);
            
            $eventCountsJson = [];
            foreach ($eventCounts as $event) {
                $eventCountsJson[$event['event_type']] = (int) $event['count'];
            }
            
            // Insert snapshot
            $wpdb->replace($snapshotsTable, [
                'user_id' => $userId,
                'period_type' => 'monthly',
                'period_key' => $periodKey,
                'score' => (int) $monthlyScore,
                'event_counts' => json_encode($eventCountsJson)
            ]);
        }
    }
    
    /**
     * Reset weekly grace for streaks
     * 
     * @return void
     */
    public function resetWeeklyGrace(): void {
        require_once REJIMDE_PATH . 'includes/Services/StreakService.php';
        $streakService = new \Rejimde\Services\StreakService();
        $streakService->resetWeeklyGrace();
    }
    
    /**
     * Cleanup old events (keep only 90 days)
     * 
     * @param int $daysToKeep Days to keep
     * @return int Number of deleted events
     */
    public function cleanupOldEvents(int $daysToKeep = 90): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_events';
        $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE DATE(created_at) < %s",
            $cutoffDate
        ));
        
        return (int) $deleted;
    }
}
