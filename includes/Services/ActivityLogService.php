<?php
namespace Rejimde\Services;

/**
 * Activity Log Service
 * 
 * Handles user activity retrieval and formatting
 */
class ActivityLogService {
    
    /**
     * Get user activity
     * 
     * @param int $userId User ID
     * @param array $options Filters (event_type, limit, offset, date_from, date_to)
     * @return array
     */
    public function getUserActivity(int $userId, array $options = []): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_events';
        
        $query = "SELECT * FROM $table WHERE user_id = %d";
        $params = [$userId];
        
        // Filter by event type
        if (isset($options['event_type'])) {
            $query .= " AND event_type = %s";
            $params[] = $options['event_type'];
        }
        
        // Filter by date range
        if (isset($options['date_from'])) {
            $query .= " AND created_at >= %s";
            $params[] = $options['date_from'];
        }
        
        if (isset($options['date_to'])) {
            $query .= " AND created_at <= %s";
            $params[] = $options['date_to'];
        }
        
        // Order by created_at DESC
        $query .= " ORDER BY created_at DESC";
        
        // Pagination
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $events = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        // Decode context JSON and format
        foreach ($events as &$event) {
            if (!empty($event['context'])) {
                $event['context'] = json_decode($event['context'], true);
            }
            
            // Add formatted data
            $event['formatted'] = $this->formatEvent($event);
        }
        
        return $events;
    }
    
    /**
     * Get activity with point movements (ledger view)
     * 
     * @param int $userId User ID
     * @param array $options Filters
     * @return array
     */
    public function getActivityWithPoints(int $userId, array $options = []): array {
        $events = $this->getUserActivity($userId, $options);
        
        // Filter only events with points
        $events = array_filter($events, function($event) {
            return $event['points'] > 0;
        });
        
        // Calculate running total
        $runningTotal = (int) get_user_meta($userId, 'rejimde_total_score', true);
        
        foreach ($events as &$event) {
            $event['balance_after'] = $runningTotal;
            $runningTotal -= $event['points'];
            $event['balance_before'] = $runningTotal;
        }
        
        return array_values($events);
    }
    
    /**
     * Get activity summary for a period
     * 
     * @param int $userId User ID
     * @param string $period Period (today, week, month)
     * @return array
     */
    public function getActivitySummary(int $userId, string $period = 'week'): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_events';
        
        // Determine date range
        $dateFrom = $this->getDateFromPeriod($period);
        
        // Get events
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) as count, SUM(points) as total_points 
            FROM $table 
            WHERE user_id = %d AND created_at >= %s 
            GROUP BY event_type 
            ORDER BY total_points DESC",
            $userId, $dateFrom
        ), ARRAY_A);
        
        // Calculate totals
        $totalEvents = array_sum(array_column($events, 'count'));
        $totalPoints = array_sum(array_column($events, 'total_points'));
        
        return [
            'period' => $period,
            'total_events' => $totalEvents,
            'total_points' => $totalPoints,
            'breakdown' => $events
        ];
    }
    
    /**
     * Get date from period string
     * 
     * @param string $period Period
     * @return string
     */
    private function getDateFromPeriod(string $period): string {
        switch ($period) {
            case 'today':
                return date('Y-m-d 00:00:00');
            case 'week':
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d 00:00:00', strtotime('-30 days'));
            default:
                return date('Y-m-d 00:00:00', strtotime('-7 days'));
        }
    }
    
    /**
     * Format event for display
     * 
     * @param array $event Event data
     * @return array
     */
    private function formatEvent(array $event): array {
        // Load scoring rules for labels
        $config = require __DIR__ . '/../Config/ScoringRules.php';
        $rule = $config[$event['event_type']] ?? null;
        
        $label = $rule['label'] ?? $event['event_type'];
        $icon = $this->getEventIcon($event['event_type']);
        
        return [
            'label' => $label,
            'icon' => $icon,
            'description' => $this->getEventDescription($event)
        ];
    }
    
    /**
     * Get event icon
     * 
     * @param string $eventType Event type
     * @return string
     */
    private function getEventIcon(string $eventType): string {
        $icons = [
            'login_success' => 'fa-sign-in-alt',
            'blog_points_claimed' => 'fa-book-open',
            'diet_completed' => 'fa-utensils',
            'exercise_completed' => 'fa-dumbbell',
            'comment_created' => 'fa-comment',
            'follow_accepted' => 'fa-user-plus',
            'highfive_sent' => 'fa-hand-paper',
            'circle_joined' => 'fa-users',
            'rating_submitted' => 'fa-star',
            'streak_milestone' => 'fa-fire'
        ];
        
        return $icons[$eventType] ?? 'fa-check-circle';
    }
    
    /**
     * Get event description
     * 
     * @param array $event Event data
     * @return string
     */
    private function getEventDescription(array $event): string {
        $context = $event['context'] ?? [];
        
        // Build description based on event type
        switch ($event['event_type']) {
            case 'blog_points_claimed':
                return 'Blog okuma';
            case 'diet_completed':
            case 'exercise_completed':
                return $context['content_name'] ?? 'İçerik tamamlama';
            case 'comment_created':
                return 'Yorum yapıldı';
            case 'follow_accepted':
                return 'Takip kabul edildi';
            case 'highfive_sent':
                return 'Beşlik çakıldı';
            case 'streak_milestone':
                return ($context['streak_count'] ?? '') . ' günlük seri';
            default:
                return '';
        }
    }
}
