<?php
namespace Rejimde\Api\V1;

use Rejimde\Api\BaseController;
use Rejimde\Services\LevelService;
use Rejimde\Services\ScoreService;
use Rejimde\Utils\TimezoneHelper;
use WP_REST_Request;

/**
 * LevelController
 * 
 * Handles level and leaderboard endpoints
 */
class LevelController extends BaseController {
    
    protected $namespace = 'rejimde/v1';
    
    public function register_routes() {
        // GET /rejimde/v1/levels - Get all levels
        register_rest_route($this->namespace, '/levels', [
            'methods' => 'GET',
            'callback' => [$this, 'get_levels'],
            'permission_callback' => '__return_true'
        ]);
        
        // GET /rejimde/v1/users/{id}/level - Get user level info
        register_rest_route($this->namespace, '/users/(?P<id>\d+)/level', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_level'],
            'permission_callback' => '__return_true'
        ]);
        
        // GET /rejimde/v1/leaderboard/weekly - Weekly leaderboard
        register_rest_route($this->namespace, '/leaderboard/weekly', [
            'methods' => 'GET',
            'callback' => [$this, 'get_weekly_leaderboard'],
            'permission_callback' => '__return_true'
        ]);
        
        // GET /rejimde/v1/leaderboard/monthly - Monthly leaderboard
        register_rest_route($this->namespace, '/leaderboard/monthly', [
            'methods' => 'GET',
            'callback' => [$this, 'get_monthly_leaderboard'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * GET /rejimde/v1/levels
     * Get all levels
     */
    public function get_levels(WP_REST_Request $request) {
        $levels = LevelService::getAllLevels();
        
        return $this->success([
            'levels' => $levels
        ]);
    }
    
    /**
     * GET /rejimde/v1/users/{id}/level
     * Get user level and history
     */
    public function get_user_level(WP_REST_Request $request) {
        $user_id = (int) $request['id'];
        
        // Get current level
        $current_level = LevelService::getUserCurrentLevel($user_id);
        
        // Get level history
        global $wpdb;
        $user_levels_table = $wpdb->prefix . 'rejimde_user_levels';
        $levels_table = $wpdb->prefix . 'rejimde_levels';
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT ul.*, l.name, l.slug, l.icon, l.color
             FROM $user_levels_table ul
             INNER JOIN $levels_table l ON ul.level_id = l.id
             WHERE ul.user_id = %d
             ORDER BY ul.joined_at DESC
             LIMIT 20",
            $user_id
        ), ARRAY_A);
        
        // Get level snapshots
        $snapshots_table = $wpdb->prefix . 'rejimde_level_snapshots';
        $snapshots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $snapshots_table
             WHERE user_id = %d
             ORDER BY week_start DESC
             LIMIT 10",
            $user_id
        ), ARRAY_A);
        
        return $this->success([
            'user_id' => $user_id,
            'current_level' => $current_level,
            'level_history' => $history,
            'level_snapshots' => $snapshots
        ]);
    }
    
    /**
     * GET /rejimde/v1/leaderboard/weekly
     * Get weekly leaderboard
     */
    public function get_weekly_leaderboard(WP_REST_Request $request) {
        $limit = (int) ($request->get_param('limit') ?: 20);
        $week_start = $request->get_param('week_start');
        
        // Use current week if not specified
        if (!$week_start) {
            $week_bounds = TimezoneHelper::getWeekBoundsTR();
            $week_start = $week_bounds['start'];
        }
        
        // Get top users for the week
        $top_users = ScoreService::getTopUsers('weekly', $week_start, $limit);
        
        // Enhance with user data
        foreach ($top_users as &$entry) {
            $user = get_userdata($entry['user_id']);
            if ($user) {
                $entry['user_display_name'] = $user->display_name;
                $entry['user_avatar'] = get_avatar_url($user->ID);
            }
        }
        
        return $this->success([
            'period_type' => 'weekly',
            'period_start' => $week_start,
            'leaderboard' => $top_users,
            'limit' => $limit
        ]);
    }
    
    /**
     * GET /rejimde/v1/leaderboard/monthly
     * Get monthly leaderboard
     */
    public function get_monthly_leaderboard(WP_REST_Request $request) {
        $limit = (int) ($request->get_param('limit') ?: 20);
        $month_start = $request->get_param('month_start');
        
        // Use current month if not specified
        if (!$month_start) {
            $month_bounds = TimezoneHelper::getMonthBoundsTR();
            $month_start = $month_bounds['start'];
        }
        
        // Get top users for the month
        $top_users = ScoreService::getTopUsers('monthly', $month_start, $limit);
        
        // Enhance with user data
        foreach ($top_users as &$entry) {
            $user = get_userdata($entry['user_id']);
            if ($user) {
                $entry['user_display_name'] = $user->display_name;
                $entry['user_avatar'] = get_avatar_url($user->ID);
            }
        }
        
        return $this->success([
            'period_type' => 'monthly',
            'period_start' => $month_start,
            'leaderboard' => $top_users,
            'limit' => $limit
        ]);
    }
}
