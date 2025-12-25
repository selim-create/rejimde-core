<?php
namespace Rejimde\Services;

/**
 * Profile View Service
 * 
 * Handles profile view tracking with privacy controls
 */
class ProfileViewService {
    
    /**
     * Record a profile view
     * 
     * @param int $profileUserId Profile being viewed
     * @param int|null $viewerUserId Viewer (null for anonymous)
     * @param string $source Source (direct, search, list, etc.)
     * @return bool
     */
    public function recordView(int $profileUserId, ?int $viewerUserId, string $source = 'direct'): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_profile_views';
        
        // Don't record self-views
        if ($viewerUserId && $viewerUserId === $profileUserId) {
            return false;
        }
        
        // For anonymous users, use IP hash
        $viewerIpHash = null;
        if (!$viewerUserId) {
            // Get IP address with proxy support
            $ip = '';
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            
            if ($ip) {
                $viewerIpHash = hash('sha256', $ip . date('Y-m-d')); // Daily IP hash for privacy
            }
        }
        
        // Record the view
        $wpdb->insert($table, [
            'profile_user_id' => $profileUserId,
            'viewer_user_id' => $viewerUserId,
            'viewer_ip_hash' => $viewerIpHash,
            'source' => $source,
            'created_at' => current_time('mysql')
        ]);
        
        return true;
    }
    
    /**
     * Get recent viewers of a profile
     * 
     * @param int $profileUserId Profile user ID
     * @param int $limit Number of viewers to return
     * @return array
     */
    public function getViewers(int $profileUserId, int $limit = 10): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_profile_views';
        
        // Only return logged-in viewers (privacy)
        $viewers = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT viewer_user_id, MAX(created_at) as last_viewed 
            FROM $table 
            WHERE profile_user_id = %d AND viewer_user_id IS NOT NULL 
            GROUP BY viewer_user_id 
            ORDER BY last_viewed DESC 
            LIMIT %d",
            $profileUserId, $limit
        ), ARRAY_A);
        
        // Enrich with user data
        foreach ($viewers as &$viewer) {
            $userId = $viewer['viewer_user_id'];
            $user = get_userdata($userId);
            
            if ($user) {
                $viewer['display_name'] = $user->display_name;
                $viewer['avatar_url'] = get_avatar_url($userId);
            }
        }
        
        return $viewers;
    }
    
    /**
     * Get today's view count for a profile
     * 
     * @param int $profileUserId Profile user ID
     * @return int
     */
    public function getTodayViewCount(int $profileUserId): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_profile_views';
        $today = date('Y-m-d');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE profile_user_id = %d AND DATE(created_at) = %s",
            $profileUserId, $today
        ));
        
        return (int) $count;
    }
    
    /**
     * Get unique viewer count for a period
     * 
     * @param int $profileUserId Profile user ID
     * @param int $days Number of days to look back
     * @return int
     */
    public function getUniqueViewerCount(int $profileUserId, int $days = 7): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_profile_views';
        $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
        
        // Count unique viewers (both logged in and IP hash)
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT COALESCE(viewer_user_id, viewer_ip_hash)) 
            FROM $table 
            WHERE profile_user_id = %d AND DATE(created_at) >= %s",
            $profileUserId, $dateFrom
        ));
        
        return (int) $count;
    }
    
    /**
     * Get view statistics for a profile
     * 
     * @param int $profileUserId Profile user ID
     * @param int $days Number of days to look back
     * @return array
     */
    public function getViewStats(int $profileUserId, int $days = 30): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_profile_views';
        $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get daily view counts
        $dailyViews = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as views, 
            COUNT(DISTINCT COALESCE(viewer_user_id, viewer_ip_hash)) as unique_viewers
            FROM $table 
            WHERE profile_user_id = %d AND DATE(created_at) >= %s 
            GROUP BY DATE(created_at) 
            ORDER BY date DESC",
            $profileUserId, $dateFrom
        ), ARRAY_A);
        
        // Calculate totals
        $totalViews = array_sum(array_column($dailyViews, 'views'));
        $uniqueViewers = $this->getUniqueViewerCount($profileUserId, $days);
        
        return [
            'period_days' => $days,
            'total_views' => $totalViews,
            'unique_viewers' => $uniqueViewers,
            'daily_views' => $dailyViews
        ];
    }
}
