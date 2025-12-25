<?php
namespace Rejimde\Cron;

use Rejimde\Services\NotificationService;

/**
 * Notification Jobs
 * 
 * Scheduled tasks for notifications
 */
class NotificationJobs {
    
    private $notificationService;
    
    public function __construct() {
        $this->notificationService = new NotificationService();
    }
    
    /**
     * Register cron jobs
     */
    public function register() {
        // Register cleanup job (daily)
        if (!wp_next_scheduled('rejimde_cleanup_notifications')) {
            wp_schedule_event(time(), 'daily', 'rejimde_cleanup_notifications');
        }
        add_action('rejimde_cleanup_notifications', [$this, 'cleanupOldNotifications']);
        
        // Register weekly ranking job (weekly on Monday 9am)
        if (!wp_next_scheduled('rejimde_weekly_ranking_notifications')) {
            $nextMonday = strtotime('next Monday 09:00:00');
            wp_schedule_event($nextMonday, 'weekly', 'rejimde_weekly_ranking_notifications');
        }
        add_action('rejimde_weekly_ranking_notifications', [$this, 'processWeeklyRankingNotifications']);
    }
    
    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications(int $daysToKeep = 30) {
        $deleted = $this->notificationService->cleanupOld($daysToKeep);
        error_log("Cleaned up {$deleted} old notifications");
    }
    
    /**
     * Process weekly ranking notifications
     * 
     * Sends notifications to users about their weekly ranking
     */
    public function processWeeklyRankingNotifications() {
        global $wpdb;
        $snapshotsTable = $wpdb->prefix . 'rejimde_score_snapshots';
        
        // Get last week's key
        $lastWeekKey = date('Y-W', strtotime('-1 week'));
        
        // Get all users with weekly snapshots
        $snapshots = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, score, rank_position FROM $snapshotsTable 
            WHERE period_type = 'weekly' AND period_key = %s AND rank_position IS NOT NULL 
            ORDER BY rank_position ASC",
            $lastWeekKey
        ), ARRAY_A);
        
        $notificationsSent = 0;
        
        foreach ($snapshots as $snapshot) {
            $userId = $snapshot['user_id'];
            $rank = $snapshot['rank_position'];
            $points = $snapshot['score'];
            
            // Create notification
            $result = $this->notificationService->create($userId, 'weekly_ranking', [
                'rank' => $rank,
                'points' => $points,
                'entity_type' => 'weekly_ranking',
                'entity_id' => null
            ]);
            
            if ($result) {
                $notificationsSent++;
            }
        }
        
        error_log("Sent {$notificationsSent} weekly ranking notifications");
    }
    
    /**
     * Send daily digest (optional, future feature)
     * 
     * Sends a summary of unread notifications to users who opted in
     */
    public function sendDailyDigest() {
        // This is a placeholder for future implementation
        // Would check user preferences for email digest
        // and send a summary of unread notifications
        error_log("Daily digest job triggered (not implemented yet)");
    }
}
