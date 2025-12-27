<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\UserDashboardService;

/**
 * User Dashboard Controller
 * 
 * Handles user-side endpoints (Mirror Logic - what experts send, users receive)
 */
class UserDashboardController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'me';
    private $userDashboardService;

    public function __construct() {
        $this->userDashboardService = new UserDashboardService();
    }

    public function register_routes() {
        // ===== MY EXPERTS =====
        
        // GET /me/experts - List user's connected experts
        register_rest_route($this->namespace, '/' . $this->base . '/experts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_experts'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // GET /me/experts/{id} - Get expert detail
        register_rest_route($this->namespace, '/' . $this->base . '/experts/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_expert'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // ===== MY PACKAGES =====
        
        // GET /me/packages - List user's active packages
        register_rest_route($this->namespace, '/' . $this->base . '/packages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_packages'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // GET /me/packages/{id} - Get package detail
        register_rest_route($this->namespace, '/' . $this->base . '/packages/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_package'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // ===== MY TRANSACTIONS =====
        
        // GET /me/transactions - List user's payment history
        register_rest_route($this->namespace, '/' . $this->base . '/transactions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_transactions'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // ===== MY PRIVATE PLANS =====
        
        // GET /me/private-plans - List assigned private plans
        register_rest_route($this->namespace, '/' . $this->base . '/private-plans', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_private_plans'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // GET /me/private-plans/{id} - Get plan detail
        register_rest_route($this->namespace, '/' . $this->base . '/private-plans/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_private_plan'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /me/private-plans/{id}/progress - Update plan progress
        register_rest_route($this->namespace, '/' . $this->base . '/private-plans/(?P<id>\d+)/progress', [
            'methods' => 'POST',
            'callback' => [$this, 'update_plan_progress'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // ===== MY INBOX (Additional) =====
        
        // POST /me/inbox/new - Create new thread (user initiates)
        register_rest_route($this->namespace, '/' . $this->base . '/inbox/new', [
            'methods' => 'POST',
            'callback' => [$this, 'create_inbox_thread'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // ===== DASHBOARD SUMMARY =====
        
        // GET /me/dashboard-summary - Get dashboard summary for widgets
        register_rest_route($this->namespace, '/' . $this->base . '/dashboard-summary', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard_summary'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // ===== INVITES =====
        
        // POST /me/accept-invite - Accept expert invite
        register_rest_route($this->namespace, '/' . $this->base . '/accept-invite', [
            'methods' => 'POST',
            'callback' => [$this, 'accept_invite'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /**
     * GET /me/experts - List user's connected experts
     */
    public function get_my_experts(WP_REST_Request $request) {
        $userId = get_current_user_id();
        
        $options = [
            'status' => $request->get_param('status'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0,
        ];
        
        // Remove null values
        $options = array_filter($options, function($value) {
            return $value !== null;
        });
        
        $experts = $this->userDashboardService->getMyExperts($userId, $options);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $experts,
        ], 200);
    }

    /**
     * GET /me/experts/{id} - Get expert detail
     */
    public function get_my_expert(WP_REST_Request $request) {
        $userId = get_current_user_id();
        $expertId = (int) $request['id'];
        
        $experts = $this->userDashboardService->getMyExperts($userId, []);
        
        // Find the specific expert
        $expert = null;
        foreach ($experts as $exp) {
            if ($exp['expert']['id'] === $expertId) {
                $expert = $exp;
                break;
            }
        }
        
        if (!$expert) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Uzman bulunamadı',
            ], 404);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $expert,
        ], 200);
    }

    /**
     * GET /me/packages - List user's active packages
     */
    public function get_my_packages(WP_REST_Request $request) {
        $userId = get_current_user_id();
        
        $packages = $this->userDashboardService->getMyPackages($userId);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $packages,
        ], 200);
    }

    /**
     * GET /me/packages/{id} - Get package detail
     */
    public function get_my_package(WP_REST_Request $request) {
        $userId = get_current_user_id();
        $packageId = (int) $request['id'];
        
        $packages = $this->userDashboardService->getMyPackages($userId);
        
        // Find the specific package
        $package = null;
        foreach ($packages as $pkg) {
            if ($pkg['id'] === $packageId) {
                $package = $pkg;
                break;
            }
        }
        
        if (!$package) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Paket bulunamadı',
            ], 404);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $package,
        ], 200);
    }

    /**
     * GET /me/transactions - List user's payment history
     */
    public function get_my_transactions(WP_REST_Request $request) {
        $userId = get_current_user_id();
        
        $options = [
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0,
        ];
        
        $transactions = $this->userDashboardService->getMyTransactions($userId, $options);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $transactions,
        ], 200);
    }

    /**
     * GET /me/private-plans - List assigned private plans
     */
    public function get_my_private_plans(WP_REST_Request $request) {
        $userId = get_current_user_id();
        
        $options = [
            'type' => $request->get_param('type'),
            'status' => $request->get_param('status'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0,
        ];
        
        // Remove null values
        $options = array_filter($options, function($value) {
            return $value !== null;
        });
        
        $plans = $this->userDashboardService->getMyPrivatePlans($userId, $options);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $plans,
        ], 200);
    }

    /**
     * GET /me/private-plans/{id} - Get plan detail
     */
    public function get_my_private_plan(WP_REST_Request $request) {
        $userId = get_current_user_id();
        $planId = (int) $request['id'];
        
        $plan = $this->userDashboardService->getMyPrivatePlan($userId, $planId);
        
        if (!$plan) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Plan bulunamadı',
            ], 404);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $plan,
        ], 200);
    }

    /**
     * POST /me/private-plans/{id}/progress - Update plan progress
     */
    public function update_plan_progress(WP_REST_Request $request) {
        $userId = get_current_user_id();
        $planId = (int) $request['id'];
        
        $data = [
            'completed_items' => $request->get_param('completed_items') ?? [],
            'progress_percent' => $request->get_param('progress_percent'),
        ];
        
        $success = $this->userDashboardService->updatePlanProgress($userId, $planId, $data);
        
        if (!$success) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'İlerleme güncellenemedi',
            ], 400);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'İlerleme güncellendi',
        ], 200);
    }

    /**
     * POST /me/inbox/new - Create new thread (user initiates)
     */
    public function create_inbox_thread(WP_REST_Request $request) {
        $userId = get_current_user_id();
        $expertId = (int) $request->get_param('expert_id');
        $subject = $request->get_param('subject');
        $content = $request->get_param('content');
        
        if (!$expertId || !$subject || !$content) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'expert_id, subject ve content gerekli',
            ], 400);
        }
        
        $threadId = $this->userDashboardService->createInboxThread($userId, $expertId, $subject, $content);
        
        if (!$threadId) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Thread oluşturulamadı. Lütfen uzmanla aktif bir ilişkiniz olduğundan emin olun.',
            ], 400);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => [
                'thread_id' => $threadId,
            ],
        ], 201);
    }

    /**
     * GET /me/dashboard-summary - Get dashboard summary for widgets
     */
    public function get_dashboard_summary(WP_REST_Request $request) {
        $userId = get_current_user_id();
        
        $summary = $this->userDashboardService->getDashboardSummary($userId);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $summary,
        ], 200);
    }

    /**
     * POST /me/accept-invite - Accept expert invite
     */
    public function accept_invite(WP_REST_Request $request) {
        $userId = get_current_user_id();
        $token = $request->get_param('token');
        
        if (!$token) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => 'Token gerekli',
            ], 400);
        }
        
        $result = $this->userDashboardService->acceptInvite($userId, $token);
        
        if (isset($result['error'])) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => $result['error'],
            ], 400);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $result,
        ], 200);
    }

    /**
     * Check authentication
     */
    public function check_auth($request) {
        return is_user_logged_in();
    }
}
