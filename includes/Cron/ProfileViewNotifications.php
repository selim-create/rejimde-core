<?php
namespace Rejimde\Cron;

/**
 * Profile View Notifications
 * 
 * Weekly cron job to send profile view summary notifications to experts
 */
class ProfileViewNotifications {
    
    /**
     * Register cron job
     */
    public function register() {
        // Register weekly view summary job (weekly on Monday 9am)
        if (!wp_next_scheduled('rejimde_weekly_view_summary')) {
            $nextMonday = strtotime('next Monday 09:00:00');
            wp_schedule_event($nextMonday, 'weekly', 'rejimde_weekly_view_summary');
        }
        add_action('rejimde_weekly_view_summary', [$this, 'sendWeeklyViewSummary']);
    }
    
    /**
     * Send weekly profile view summary to experts
     * 
     * Sends notifications to all experts who received views in the past week
     */
    public function sendWeeklyViewSummary() {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_profile_views';
        $notificationsTable = $wpdb->prefix . 'rejimde_notifications';
        
        // Get date range for last week (Monday to Sunday)
        // Note: '-7 days monday' gives us the Monday of last week (not 'last monday' which could be today)
        // Then we add 6 days to get the Sunday of the same week
        $week_start_timestamp = strtotime('-7 days monday');
        $week_start = date('Y-m-d 00:00:00', $week_start_timestamp);
        $week_end = date('Y-m-d 23:59:59', strtotime('+6 days', $week_start_timestamp));
        
        // Get all experts who received views last week
        $experts = $wpdb->get_results($wpdb->prepare(
            "SELECT expert_user_id, COUNT(*) as view_count 
            FROM {$table} 
            WHERE viewed_at >= %s AND viewed_at <= %s 
            GROUP BY expert_user_id 
            HAVING view_count > 0",
            $week_start,
            $week_end
        ), ARRAY_A);
        
        $notificationsSent = 0;
        
        foreach ($experts as $expert) {
            $expert_id = $expert['expert_user_id'];
            $view_count = $expert['view_count'];
            
            // Check if user is still a pro member
            $user = get_userdata($expert_id);
            if (!$user) {
                continue;
            }
            
            $roles = (array) $user->roles;
            if (!in_array('rejimde_pro', $roles) && !in_array('administrator', $roles)) {
                continue;
            }
            
            // Create notification
            $title = 'Haftalık Profil Özeti';
            $message = sprintf('Bu hafta profiliniz %d kez görüntülendi! 🎉', $view_count);
            
            // Use direct insert to avoid template dependency
            $result = $wpdb->insert(
                $notificationsTable,
                [
                    'user_id' => $expert_id,
                    'type' => 'profile_view_summary',
                    'category' => 'expert',
                    'title' => $title,
                    'body' => $message,
                    'icon' => 'fa-eye',
                    'action_url' => null,
                    'actor_id' => null,
                    'entity_type' => 'profile_views',
                    'entity_id' => null,
                    'meta' => wp_json_encode(['view_count' => $view_count], JSON_UNESCAPED_UNICODE),
                    'is_read' => 0,
                    'is_pushed' => 0,
                    'is_emailed' => 0,
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'created_date' => current_time('Y-m-d'),
                    'created_at' => current_time('mysql')
                ],
                [
                    '%d', // user_id
                    '%s', // type
                    '%s', // category
                    '%s', // title
                    '%s', // body
                    '%s', // icon
                    '%s', // action_url
                    '%d', // actor_id
                    '%s', // entity_type
                    '%d', // entity_id
                    '%s', // meta
                    '%d', // is_read
                    '%d', // is_pushed
                    '%d', // is_emailed
                    '%s', // expires_at
                    '%s', // created_date
                    '%s'  // created_at
                ]
            );
            
            if ($result !== false) {
                $notificationsSent++;
            }
        }
        
        error_log("Sent {$notificationsSent} weekly profile view summary notifications");
        
        return $notificationsSent;
    }
}
