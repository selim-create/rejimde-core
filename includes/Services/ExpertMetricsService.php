<?php
namespace Rejimde\Services;

/**
 * Expert Metrics Service
 * 
 * Handles expert statistics and metrics tracking
 */
class ExpertMetricsService {
    
    /**
     * Record a profile view
     * 
     * @param int $expertId Expert user ID
     * @param int|null $viewerId Viewer user ID (null for anonymous)
     * @param string $source Source (direct, search, etc.)
     * @return bool
     */
    public function recordProfileView(int $expertId, ?int $viewerId, string $source = 'direct'): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_expert_metrics';
        $today = date('Y-m-d');
        
        // Get or create today's metrics
        $metrics = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE expert_id = %d AND metric_date = %s",
            $expertId, $today
        ));
        
        if (!$metrics) {
            // Create new metrics row
            $wpdb->insert($table, [
                'expert_id' => $expertId,
                'metric_date' => $today,
                'profile_views' => 1,
                'unique_viewers' => $viewerId ? 1 : 0
            ]);
        } else {
            // Update existing metrics
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET profile_views = profile_views + 1 WHERE expert_id = %d AND metric_date = %s",
                $expertId, $today
            ));
            
            // Update unique viewers if logged in user
            if ($viewerId) {
                // Check if this viewer viewed today already
                $viewsTable = $wpdb->prefix . 'rejimde_profile_views';
                $viewedToday = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $viewsTable 
                    WHERE profile_user_id = %d AND viewer_user_id = %d AND DATE(created_at) = %s",
                    $expertId, $viewerId, $today
                ));
                
                if (!$viewedToday) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $table SET unique_viewers = unique_viewers + 1 
                        WHERE expert_id = %d AND metric_date = %s",
                        $expertId, $today
                    ));
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get daily metrics for an expert
     * 
     * @param int $expertId Expert user ID
     * @param string $date Date (Y-m-d)
     * @return array|null
     */
    public function getDailyMetrics(int $expertId, string $date): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_expert_metrics';
        
        $metrics = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE expert_id = %d AND metric_date = %s",
            $expertId, $date
        ), ARRAY_A);
        
        return $metrics;
    }
    
    /**
     * Get metrics summary for a period
     * 
     * @param int $expertId Expert user ID
     * @param int $days Number of days to look back
     * @return array
     */
    public function getMetricsSummary(int $expertId, int $days = 30): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_expert_metrics';
        $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
        
        $metrics = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE expert_id = %d AND metric_date >= %s ORDER BY metric_date DESC",
            $expertId, $dateFrom
        ), ARRAY_A);
        
        // Calculate totals
        $totals = [
            'profile_views' => 0,
            'unique_viewers' => 0,
            'rating_count' => 0,
            'rating_sum' => 0,
            'content_views' => 0,
            'client_completions' => 0
        ];
        
        foreach ($metrics as $metric) {
            $totals['profile_views'] += $metric['profile_views'];
            $totals['unique_viewers'] += $metric['unique_viewers'];
            $totals['rating_count'] += $metric['rating_count'];
            $totals['rating_sum'] += $metric['rating_sum'];
            $totals['content_views'] += $metric['content_views'];
            $totals['client_completions'] += $metric['client_completions'];
        }
        
        // Calculate average rating
        $averageRating = $totals['rating_count'] > 0 ? 
            round($totals['rating_sum'] / $totals['rating_count'], 2) : 0;
        
        return [
            'period_days' => $days,
            'totals' => $totals,
            'average_rating' => $averageRating,
            'daily_metrics' => $metrics
        ];
    }
    
    /**
     * Record a client completion
     * 
     * @param int $expertId Expert user ID
     * @param int $clientId Client user ID
     * @param string $contentType Content type (diet, exercise)
     * @return bool
     */
    public function recordClientCompletion(int $expertId, int $clientId, string $contentType): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_expert_metrics';
        $today = date('Y-m-d');
        
        // Get or create today's metrics
        $metrics = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE expert_id = %d AND metric_date = %s",
            $expertId, $today
        ));
        
        if (!$metrics) {
            // Create new metrics row
            $wpdb->insert($table, [
                'expert_id' => $expertId,
                'metric_date' => $today,
                'client_completions' => 1
            ]);
        } else {
            // Update existing metrics
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET client_completions = client_completions + 1 
                WHERE expert_id = %d AND metric_date = %s",
                $expertId, $today
            ));
        }
        
        return true;
    }
    
    /**
     * Record a rating
     * 
     * @param int $expertId Expert user ID
     * @param float $rating Rating value (1-5)
     * @return bool
     */
    public function recordRating(int $expertId, float $rating): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_expert_metrics';
        $today = date('Y-m-d');
        
        // Get or create today's metrics
        $metrics = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE expert_id = %d AND metric_date = %s",
            $expertId, $today
        ));
        
        if (!$metrics) {
            // Create new metrics row
            $wpdb->insert($table, [
                'expert_id' => $expertId,
                'metric_date' => $today,
                'rating_count' => 1,
                'rating_sum' => $rating
            ]);
        } else {
            // Update existing metrics
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET rating_count = rating_count + 1, rating_sum = rating_sum + %f 
                WHERE expert_id = %d AND metric_date = %s",
                $rating, $expertId, $today
            ));
        }
        
        return true;
    }
}
