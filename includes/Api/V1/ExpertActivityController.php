<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use Rejimde\Services\NotificationService;
use Rejimde\Services\ActivityLogService;
use Rejimde\Services\ExpertMetricsService;
use Rejimde\Services\ProfileViewService;

/**
 * Expert Activity Controller
 * 
 * Handles expert-specific endpoints (for rejimde_pro users)
 */
class ExpertActivityController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'expert';
    private $notificationService;
    private $activityService;
    private $metricsService;
    private $profileViewService;

    public function __construct() {
        $this->notificationService = new NotificationService();
        $this->activityService = new ActivityLogService();
        $this->metricsService = new ExpertMetricsService();
        $this->profileViewService = new ProfileViewService();
    }

    public function register_routes() {
        // Get expert notifications
        register_rest_route($this->namespace, '/' . $this->base . '/notifications', [
            'methods' => 'GET',
            'callback' => [$this, 'get_notifications'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Get expert activity
        register_rest_route($this->namespace, '/' . $this->base . '/activity', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activity'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Get expert metrics
        register_rest_route($this->namespace, '/' . $this->base . '/metrics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_metrics'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Get profile viewers
        register_rest_route($this->namespace, '/' . $this->base . '/profile-viewers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_profile_viewers'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Get view stats
        register_rest_route($this->namespace, '/' . $this->base . '/view-stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_view_stats'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
    }

    /**
     * Get expert notifications (expert category only)
     */
    public function get_notifications($request) {
        $userId = get_current_user_id();
        
        $options = [
            'category' => 'expert',
            'is_read' => $request->get_param('is_read'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0
        ];
        
        // Remove null values
        $options = array_filter($options, function($value) {
            return $value !== null;
        });
        
        $notifications = $this->notificationService->getNotifications($userId, $options);
        
        return $this->success($notifications);
    }

    /**
     * Get expert activity
     */
    public function get_activity($request) {
        $userId = get_current_user_id();
        
        $options = [
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0
        ];
        
        $activity = $this->activityService->getUserActivity($userId, $options);
        
        return $this->success($activity);
    }

    /**
     * Get expert metrics
     */
    public function get_metrics($request) {
        $userId = get_current_user_id();
        $days = $request->get_param('days') ?? 30;
        
        $metrics = $this->metricsService->getMetricsSummary($userId, $days);
        
        return $this->success($metrics);
    }

    /**
     * Get profile viewers
     */
    public function get_profile_viewers($request) {
        $userId = get_current_user_id();
        $limit = $request->get_param('limit') ?? 10;
        
        $viewers = $this->profileViewService->getViewers($userId, $limit);
        
        return $this->success($viewers);
    }

    /**
     * Get view statistics
     */
    public function get_view_stats($request) {
        $userId = get_current_user_id();
        $days = $request->get_param('days') ?? 30;
        
        $stats = $this->profileViewService->getViewStats($userId, $days);
        
        return $this->success($stats);
    }

    /**
     * Check if user is an expert (rejimde_pro)
     */
    public function check_expert_auth(): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('rejimde_pro', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles);
    }

    protected function success($data = null) {
        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }

    protected function error($message = 'Error', $code = 400) {
        return new WP_REST_Response(['status' => 'error', 'message' => $message], $code);
    }
}
