<?php
namespace Rejimde\Api\V1;

use Rejimde\Api\BaseController;
use Rejimde\Services\LedgerService;
use Rejimde\Services\EventService;
use Rejimde\Services\ScoreService;
use Rejimde\Utils\TimezoneHelper;
use WP_REST_Request;

/**
 * ScoreController
 * 
 * Handles score and ledger endpoints
 */
class ScoreController extends BaseController {
    
    protected $namespace = 'rejimde/v1';
    protected $base = 'users';
    
    public function register_routes() {
        // GET /rejimde/v1/users/{id}/score - Get user score
        register_rest_route($this->namespace, '/users/(?P<id>\d+)/score', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_score'],
            'permission_callback' => '__return_true'
        ]);
        
        // GET /rejimde/v1/users/{id}/points - Get user points breakdown
        register_rest_route($this->namespace, '/users/(?P<id>\d+)/points', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_points'],
            'permission_callback' => '__return_true'
        ]);
        
        // GET /rejimde/v1/users/{id}/ledger - Get user ledger history
        register_rest_route($this->namespace, '/users/(?P<id>\d+)/ledger', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_ledger'],
            'permission_callback' => '__return_true'
        ]);
        
        // GET /rejimde/v1/users/{id}/events - Get user events
        register_rest_route($this->namespace, '/users/(?P<id>\d+)/events', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_events'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * GET /rejimde/v1/users/{id}/score
     * Get user score and snapshots
     */
    public function get_user_score(WP_REST_Request $request) {
        $user_id = (int) $request['id'];
        
        // Get current balance
        $balance = LedgerService::getBalance($user_id);
        
        // Get current week score
        $week_bounds = TimezoneHelper::getWeekBoundsTR();
        $weekly_score = ScoreService::calculateWeeklyScore($user_id, $week_bounds['start'], $week_bounds['end']);
        
        // Get current month score
        $month_bounds = TimezoneHelper::getMonthBoundsTR();
        $monthly_score = ScoreService::calculateMonthlyScore($user_id, $month_bounds['start'], $month_bounds['end']);
        
        // Get recent weekly snapshots
        global $wpdb;
        $scores_table = $wpdb->prefix . 'rejimde_user_scores';
        $weekly_snapshots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $scores_table 
             WHERE user_id = %d AND period_type = 'weekly'
             ORDER BY period_start DESC
             LIMIT 10",
            $user_id
        ), ARRAY_A);
        
        $monthly_snapshots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $scores_table 
             WHERE user_id = %d AND period_type = 'monthly'
             ORDER BY period_start DESC
             LIMIT 10",
            $user_id
        ), ARRAY_A);
        
        return $this->success([
            'user_id' => $user_id,
            'total_balance' => $balance,
            'current_week' => [
                'start' => $week_bounds['start'],
                'end' => $week_bounds['end'],
                'score' => $weekly_score
            ],
            'current_month' => [
                'start' => $month_bounds['start'],
                'end' => $month_bounds['end'],
                'score' => $monthly_score
            ],
            'weekly_snapshots' => $weekly_snapshots,
            'monthly_snapshots' => $monthly_snapshots
        ]);
    }
    
    /**
     * GET /rejimde/v1/users/{id}/points
     * Get user points breakdown
     */
    public function get_user_points(WP_REST_Request $request) {
        $user_id = (int) $request['id'];
        
        $balance = LedgerService::getBalance($user_id);
        
        // Get breakdown by reason
        global $wpdb;
        $ledger_table = $wpdb->prefix . 'rejimde_points_ledger';
        $breakdown = $wpdb->get_results($wpdb->prepare(
            "SELECT reason, 
                    SUM(CASE WHEN points_delta > 0 THEN points_delta ELSE 0 END) as total_earned,
                    COUNT(*) as count
             FROM $ledger_table
             WHERE user_id = %d
             GROUP BY reason
             ORDER BY total_earned DESC",
            $user_id
        ), ARRAY_A);
        
        return $this->success([
            'user_id' => $user_id,
            'total_balance' => $balance,
            'breakdown' => $breakdown
        ]);
    }
    
    /**
     * GET /rejimde/v1/users/{id}/ledger
     * Get user ledger history
     */
    public function get_user_ledger(WP_REST_Request $request) {
        $user_id = (int) $request['id'];
        $limit = (int) ($request->get_param('limit') ?: 50);
        $offset = (int) ($request->get_param('offset') ?: 0);
        
        $history = LedgerService::getHistory($user_id, $limit, $offset);
        
        return $this->success([
            'user_id' => $user_id,
            'ledger' => $history,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * GET /rejimde/v1/users/{id}/events
     * Get user events
     */
    public function get_user_events(WP_REST_Request $request) {
        $user_id = (int) $request['id'];
        $limit = (int) ($request->get_param('limit') ?: 50);
        $offset = (int) ($request->get_param('offset') ?: 0);
        
        $events = EventService::getEvents($user_id, $limit, $offset);
        
        return $this->success([
            'user_id' => $user_id,
            'events' => $events,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
}
