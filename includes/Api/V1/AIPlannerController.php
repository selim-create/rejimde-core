<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\AIPlannerService;

/**
 * AI Planner Controller
 * 
 * Handles AI-powered plan generation endpoints
 */
class AIPlannerController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/ai';
    private $aiPlannerService;

    public function __construct() {
        $this->aiPlannerService = new AIPlannerService();
    }

    public function register_routes() {
        // POST /pro/ai/generate-plan - Generate plan draft
        register_rest_route($this->namespace, '/' . $this->base . '/generate-plan', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_plan'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/ai/generate-draft - Generate message draft
        register_rest_route($this->namespace, '/' . $this->base . '/generate-draft', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_draft'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/ai/analyze-progress - Analyze client progress
        register_rest_route($this->namespace, '/' . $this->base . '/analyze-progress', [
            'methods' => 'POST',
            'callback' => [$this, 'analyze_progress'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/ai/usage - Get AI usage stats
        register_rest_route($this->namespace, '/' . $this->base . '/usage', [
            'methods' => 'GET',
            'callback' => [$this, 'get_usage'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
    }

    /**
     * POST /pro/ai/generate-plan
     */
    public function generate_plan(WP_REST_Request $request): WP_REST_Response {
        $clientId = $request->get_param('client_id');
        $planType = $request->get_param('plan_type');
        $parameters = $request->get_param('parameters');
        
        if (empty($clientId) || empty($planType)) {
            return $this->error('client_id and plan_type are required', 400);
        }
        
        if (!is_array($parameters)) {
            $parameters = [];
        }
        
        $result = $this->aiPlannerService->generatePlan((int) $clientId, $planType, $parameters);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success($result);
    }

    /**
     * POST /pro/ai/generate-draft
     */
    public function generate_draft(WP_REST_Request $request): WP_REST_Response {
        $context = [
            'context' => $request->get_param('context'),
            'type' => $request->get_param('type'),
        ];
        
        if (empty($context['context'])) {
            return $this->error('Context is required', 400);
        }
        
        $result = $this->aiPlannerService->generateDraft($context);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success($result);
    }

    /**
     * POST /pro/ai/analyze-progress
     */
    public function analyze_progress(WP_REST_Request $request): WP_REST_Response {
        $clientId = $request->get_param('client_id');
        $progressData = $request->get_param('progress_data');
        
        if (empty($clientId) || !is_array($progressData)) {
            return $this->error('client_id and progress_data are required', 400);
        }
        
        $result = $this->aiPlannerService->analyzeProgress((int) $clientId, $progressData);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success($result);
    }

    /**
     * GET /pro/ai/usage
     */
    public function get_usage(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $usage = $this->aiPlannerService->getUsageStats($expertId);
        
        return $this->success($usage);
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

    protected function check_expert_auth(): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('rejimde_pro', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles);
    }
}
