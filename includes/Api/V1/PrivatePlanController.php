<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\PrivatePlanService;

/**
 * Private Plan Controller
 * 
 * Handles custom plan management endpoints
 */
class PrivatePlanController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/plans';
    private $planService;

    public function __construct() {
        $this->planService = new PrivatePlanService();
    }

    public function register_routes() {
        // Expert endpoints
        
        // GET /pro/plans - List plans
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_plans'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/plans/{id} - Get plan detail
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_plan'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/plans - Create plan
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'create_plan'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // PATCH /pro/plans/{id} - Update plan
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_plan'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // DELETE /pro/plans/{id} - Delete plan
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_plan'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/plans/{id}/assign - Assign to client
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/assign', [
            'methods' => 'POST',
            'callback' => [$this, 'assign_plan'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/plans/{id}/duplicate - Duplicate plan
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/duplicate', [
            'methods' => 'POST',
            'callback' => [$this, 'duplicate_plan'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // PATCH /pro/plans/{id}/status - Update status
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/status', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_status'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Client endpoints
        
        // GET /me/plans - Get assigned plans
        register_rest_route($this->namespace, '/me/plans', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_plans'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // GET /me/plans/{id} - Get plan detail
        register_rest_route($this->namespace, '/me/plans/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_plan'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /me/plans/{id}/progress - Record progress
        register_rest_route($this->namespace, '/me/plans/(?P<id>\d+)/progress', [
            'methods' => 'POST',
            'callback' => [$this, 'record_progress'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /**
     * GET /pro/plans
     */
    public function get_plans(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $filters = [
            'type' => $request->get_param('type'),
            'status' => $request->get_param('status'),
            'client_id' => $request->get_param('client_id'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0,
        ];
        
        // Remove null values
        $filters = array_filter($filters, function($value) {
            return $value !== null;
        });
        
        $plans = $this->planService->getPlans($expertId, $filters);
        
        return $this->success($plans);
    }

    /**
     * GET /pro/plans/{id}
     */
    public function get_plan(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $planId = (int) $request['id'];
        
        $plan = $this->planService->getPlan($planId, $expertId);
        
        if (!$plan) {
            return $this->error('Plan not found', 404);
        }
        
        return $this->success($plan);
    }

    /**
     * POST /pro/plans
     */
    public function create_plan(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $data = [
            'title' => $request->get_param('title'),
            'type' => $request->get_param('type'),
            'status' => $request->get_param('status'),
            'plan_data' => $request->get_param('plan_data'),
            'notes' => $request->get_param('notes'),
        ];
        
        $result = $this->planService->createPlan($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['id' => $result], 'Plan created successfully', 201);
    }

    /**
     * PATCH /pro/plans/{id}
     */
    public function update_plan(WP_REST_Request $request): WP_REST_Response {
        $planId = (int) $request['id'];
        
        $data = [];
        $allowedFields = ['title', 'type', 'plan_data', 'notes'];
        
        foreach ($allowedFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }
        
        $result = $this->planService->updatePlan($planId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['message' => 'Plan updated successfully']);
    }

    /**
     * DELETE /pro/plans/{id}
     */
    public function delete_plan(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $planId = (int) $request['id'];
        
        $result = $this->planService->deletePlan($planId, $expertId);
        
        if (!$result) {
            return $this->error('Plan not found or access denied', 404);
        }
        
        return $this->success(['message' => 'Plan deleted successfully']);
    }

    /**
     * POST /pro/plans/{id}/assign
     */
    public function assign_plan(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $planId = (int) $request['id'];
        $clientId = $request->get_param('client_id');
        
        if (empty($clientId)) {
            return $this->error('client_id is required', 400);
        }
        
        // Auto-detect relationship_id from expert-client pair
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $relationshipId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_relationships 
             WHERE expert_id = %d AND client_id = %d AND status = 'active'",
            $expertId,
            (int) $clientId
        ));
        
        if (!$relationshipId) {
            return $this->error('No active relationship found with this client', 400);
        }
        
        $result = $this->planService->assignPlan($planId, (int) $clientId, (int) $relationshipId);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['message' => 'Plan assigned successfully']);
    }

    /**
     * POST /pro/plans/{id}/duplicate
     */
    public function duplicate_plan(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $planId = (int) $request['id'];
        
        $result = $this->planService->duplicatePlan($planId, $expertId);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 404);
        }
        
        return $this->success(['id' => $result], 'Plan duplicated successfully', 201);
    }

    /**
     * PATCH /pro/plans/{id}/status
     */
    public function update_status(WP_REST_Request $request): WP_REST_Response {
        $planId = (int) $request['id'];
        $status = $request->get_param('status');
        
        if (empty($status)) {
            return $this->error('Status is required', 400);
        }
        
        $result = $this->planService->updateStatus($planId, $status);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['message' => 'Status updated successfully']);
    }

    /**
     * GET /me/plans
     */
    public function get_my_plans(WP_REST_Request $request): WP_REST_Response {
        $clientId = get_current_user_id();
        
        $plans = $this->planService->getClientPlans($clientId);
        
        return $this->success($plans);
    }

    /**
     * GET /me/plans/{id}
     */
    public function get_my_plan(WP_REST_Request $request): WP_REST_Response {
        $userId = get_current_user_id();
        $planId = (int) $request['id'];
        
        // Verify plan belongs to user
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_plans WHERE id = %d AND client_id = %d",
            $planId,
            $userId
        ), ARRAY_A);
        
        if (!$plan) {
            return $this->error('Plan not found', 404);
        }
        
        // Format plan data
        $formatted = [
            'id' => (int) $plan['id'],
            'title' => $plan['title'],
            'type' => $plan['type'],
            'status' => $plan['status'],
            'plan_data' => $plan['plan_data'] ? json_decode($plan['plan_data'], true) : null,
            'assigned_at' => $plan['assigned_at'],
            'completed_at' => $plan['completed_at'],
        ];
        
        return $this->success($formatted);
    }

    /**
     * POST /me/plans/{id}/progress
     */
    public function record_progress(WP_REST_Request $request): WP_REST_Response {
        $userId = get_current_user_id();
        $planId = (int) $request['id'];
        $completedItems = $request->get_param('completed_items');
        $progressPercent = $request->get_param('progress_percent');
        
        if (!is_array($completedItems) || $progressPercent === null) {
            return $this->error('completed_items and progress_percent are required', 400);
        }
        
        $result = $this->planService->recordProgress($planId, $userId, $completedItems, (float) $progressPercent);
        
        if (!$result) {
            return $this->error('Failed to record progress', 500);
        }
        
        return $this->success(['message' => 'Progress recorded successfully']);
    }

    // Helper methods

    protected function success($data = null, $message = 'Success', $code = 200): WP_REST_Response {
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data
        ], $code);
    }

    protected function error($message = 'Error', $code = 400): WP_REST_Response {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $message
        ], $code);
    }

    public function check_auth(): bool {
        return is_user_logged_in();
    }

    public function check_expert_auth(): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('rejimde_pro', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles);
    }
}
