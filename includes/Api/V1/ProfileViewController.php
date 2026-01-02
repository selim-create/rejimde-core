<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Traits\ProAuthTrait;

/**
 * Profile View Controller
 * 
 * Handles profile view tracking for rejimde_pro members
 */
class ProfileViewController extends WP_REST_Controller {
    
    use ProAuthTrait;

    protected $namespace = 'rejimde/v1';
    protected $base = 'profile-views';

    public function register_routes() {
        // POST /rejimde/v1/profile-views/track - Track a profile view (public)
        register_rest_route($this->namespace, '/' . $this->base . '/track', [
            'methods' => 'POST',
            'callback' => [$this, 'track_view'],
            'permission_callback' => '__return_true', // Public endpoint
        ]);

        // GET /rejimde/v1/profile-views/my-stats - Get view statistics (Pro only)
        register_rest_route($this->namespace, '/' . $this->base . '/my-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_stats'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /rejimde/v1/profile-views/activity - Get activity log (Pro only)
        register_rest_route($this->namespace, '/' . $this->base . '/activity', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activity'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
    }

    /**
     * Track a profile view
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function track_view(WP_REST_Request $request) {
        global $wpdb;
        
        $expert_slug = $request->get_param('expert_slug');
        $session_id = $request->get_param('session_id');
        
        if (empty($expert_slug) || empty($session_id)) {
            return $this->error('Missing required parameters: expert_slug, session_id', 400);
        }
        
        // Find expert user ID from slug
        $expert_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE user_login = %s",
            $expert_slug
        ));
        
        if (!$expert_user_id) {
            return $this->error('Expert not found', 404);
        }
        
        // Get current user ID (null for guests)
        $viewer_user_id = is_user_logged_in() ? get_current_user_id() : null;
        
        // Don't track self-views
        if ($viewer_user_id && $viewer_user_id === $expert_user_id) {
            return $this->success(null, 'Self-view not tracked', 200);
        }
        
        // Check for duplicate view within last 30 minutes
        $table = $wpdb->prefix . 'rejimde_profile_views';
        $thirty_minutes_ago = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        
        $recent_view = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} 
            WHERE expert_user_id = %d 
            AND session_id = %s 
            AND viewed_at > %s 
            LIMIT 1",
            $expert_user_id,
            $session_id,
            $thirty_minutes_ago
        ));
        
        if ($recent_view) {
            return $this->success(null, 'View already tracked in this session', 200);
        }
        
        // Get IP address (check CloudFlare header first)
        $viewer_ip = null;
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $viewer_ip = sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $viewer_ip = sanitize_text_field(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $viewer_ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        
        // Validate IP format
        if ($viewer_ip && !filter_var($viewer_ip, FILTER_VALIDATE_IP)) {
            $viewer_ip = null;
        }
        
        // Get user agent and sanitize
        $viewer_user_agent = !empty($_SERVER['HTTP_USER_AGENT']) ? wp_strip_all_tags($_SERVER['HTTP_USER_AGENT']) : null;
        
        // Limit user agent length to prevent abuse
        if ($viewer_user_agent && strlen($viewer_user_agent) > 500) {
            $viewer_user_agent = substr($viewer_user_agent, 0, 500);
        }
        
        // Determine if member
        $is_member = $viewer_user_id ? 1 : 0;
        
        // Insert view record
        $result = $wpdb->insert(
            $table,
            [
                'expert_user_id' => $expert_user_id,
                'expert_slug' => sanitize_text_field($expert_slug),
                'viewer_user_id' => $viewer_user_id,
                'viewer_ip' => $viewer_ip,
                'viewer_user_agent' => $viewer_user_agent,
                'is_member' => $is_member,
                'session_id' => sanitize_text_field($session_id),
                'viewed_at' => current_time('mysql')
            ],
            [
                '%d', // expert_user_id
                '%s', // expert_slug
                '%d', // viewer_user_id
                '%s', // viewer_ip
                '%s', // viewer_user_agent
                '%d', // is_member
                '%s', // session_id
                '%s'  // viewed_at
            ]
        );
        
        if ($result === false) {
            return $this->error('Failed to track view', 500);
        }
        
        return $this->success(['tracked' => true], 'View tracked successfully', 200);
    }

    /**
     * Get view statistics for current user
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_my_stats(WP_REST_Request $request) {
        global $wpdb;
        
        $expert_user_id = get_current_user_id();
        $table = $wpdb->prefix . 'rejimde_profile_views';
        
        // Get this week's count
        $week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $this_week = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE expert_user_id = %d AND viewed_at >= %s",
            $expert_user_id,
            $week_start
        ));
        
        // Get this month's count
        $month_start = date('Y-m-01 00:00:00');
        $this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE expert_user_id = %d AND viewed_at >= %s",
            $expert_user_id,
            $month_start
        ));
        
        // Get total count
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE expert_user_id = %d",
            $expert_user_id
        ));
        
        // Get member views count
        $member_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE expert_user_id = %d AND is_member = 1",
            $expert_user_id
        ));
        
        // Get guest views count
        $guest_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE expert_user_id = %d AND is_member = 0",
            $expert_user_id
        ));
        
        return $this->success([
            'this_week' => (int) $this_week,
            'this_month' => (int) $this_month,
            'total' => (int) $total,
            'member_views' => (int) $member_views,
            'guest_views' => (int) $guest_views
        ], 'Statistics retrieved successfully', 200);
    }

    /**
     * Get activity log with pagination
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_activity(WP_REST_Request $request) {
        global $wpdb;
        
        $expert_user_id = get_current_user_id();
        $page = max(1, (int) $request->get_param('page') ?: 1);
        $per_page = min(100, max(1, (int) $request->get_param('per_page') ?: 20));
        $offset = ($page - 1) * $per_page;
        
        $table = $wpdb->prefix . 'rejimde_profile_views';
        
        // Get total count
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE expert_user_id = %d",
            $expert_user_id
        ));
        
        // Get views
        $views = $wpdb->get_results($wpdb->prepare(
            "SELECT id, viewer_user_id, is_member, viewed_at 
            FROM {$table} 
            WHERE expert_user_id = %d 
            ORDER BY viewed_at DESC 
            LIMIT %d OFFSET %d",
            $expert_user_id,
            $per_page,
            $offset
        ), ARRAY_A);
        
        // Enrich with viewer data
        $data = [];
        foreach ($views as $view) {
            $item = [
                'id' => (int) $view['id'],
                'viewed_at' => $view['viewed_at'],
                'is_member' => (bool) $view['is_member'],
                'viewer' => null
            ];
            
            // Add viewer info for members only
            if ($view['is_member'] && $view['viewer_user_id']) {
                $user = get_userdata($view['viewer_user_id']);
                if ($user) {
                    // Get avatar - check custom avatar_url meta first
                    $avatar_url = get_user_meta($view['viewer_user_id'], 'avatar_url', true);
                    
                    // If no custom avatar, use dicebear fallback
                    if (empty($avatar_url)) {
                        $avatar_url = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($user->user_login);
                    }
                    
                    $item['viewer'] = [
                        'id' => (int) $view['viewer_user_id'],
                        'name' => $user->display_name,
                        'avatar' => $avatar_url
                    ];
                }
            }
            
            $data[] = $item;
        }
        
        $total_pages = ceil($total / $per_page);
        
        return $this->success($data, 'Activity retrieved successfully', 200, [
            'page' => $page,
            'per_page' => $per_page,
            'total' => (int) $total,
            'total_pages' => $total_pages
        ]);
    }
}
