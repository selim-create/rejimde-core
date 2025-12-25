<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use Rejimde\Services\ActivityLogService;

/**
 * Activity Controller
 * 
 * Handles activity log endpoints
 */
class ActivityController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'activity';
    private $activityService;

    public function __construct() {
        $this->activityService = new ActivityLogService();
    }

    public function register_routes() {
        // Get user activity
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_activity'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Get activity with points (ledger view)
        register_rest_route($this->namespace, '/' . $this->base . '/points', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activity_with_points'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Get activity summary
        register_rest_route($this->namespace, '/' . $this->base . '/summary', [
            'methods' => 'GET',
            'callback' => [$this, 'get_summary'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /**
     * Get user activity
     */
    public function get_activity($request) {
        $userId = get_current_user_id();
        
        $options = [
            'event_type' => $request->get_param('event_type'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0
        ];
        
        // Remove null values
        $options = array_filter($options, function($value) {
            return $value !== null;
        });
        
        $activity = $this->activityService->getUserActivity($userId, $options);
        
        return $this->success($activity);
    }

    /**
     * Get activity with points (ledger view)
     */
    public function get_activity_with_points($request) {
        $userId = get_current_user_id();
        
        $options = [
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0
        ];
        
        $activity = $this->activityService->getActivityWithPoints($userId, $options);
        
        return $this->success($activity);
    }

    /**
     * Get activity summary
     */
    public function get_summary($request) {
        $userId = get_current_user_id();
        $period = $request->get_param('period') ?? 'week';
        
        $summary = $this->activityService->getActivitySummary($userId, $period);
        
        return $this->success($summary);
    }

    public function check_auth($request) {
        return is_user_logged_in();
    }

    protected function success($data = null) {
        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }

    protected function error($message = 'Error', $code = 400) {
        return new WP_REST_Response(['status' => 'error', 'message' => $message], $code);
    }
}
