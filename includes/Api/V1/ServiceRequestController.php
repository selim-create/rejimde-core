<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;
use Rejimde\Services\ServiceRequestService;

class ServiceRequestController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'service-requests';
    private $service;
    
    public function __construct() {
        $this->service = new ServiceRequestService();
    }

    public function register_routes() {
        // Create service request
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'create_request'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
        
        // Get expert's service requests
        register_rest_route($this->namespace, '/me/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_expert_requests'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
        
        // Respond to service request
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/respond', [
            'methods' => 'POST',
            'callback' => [$this, 'respond_to_request'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
    }

    public function check_auth() {
        return is_user_logged_in();
    }
    
    public function check_expert_auth() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('rejimde_pro', (array) $user->roles);
    }

    /**
     * Create a new service request
     * 
     * POST /rejimde/v1/service-requests
     */
    public function create_request($request) {
        $userId = get_current_user_id();
        
        $data = [
            'expert_id' => $request->get_param('expert_id'),
            'service_id' => $request->get_param('service_id'),
            'message' => $request->get_param('message'),
            'contact_preference' => $request->get_param('contact_preference')
        ];
        
        $result = $this->service->createRequest($userId, $data);
        
        if (isset($result['error'])) {
            return new WP_Error('request_failed', $result['error'], ['status' => 400]);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $result
        ], 200);
    }
    
    /**
     * Get service requests for current expert
     * 
     * GET /rejimde/v1/me/service-requests
     */
    public function get_expert_requests($request) {
        $expertId = get_current_user_id();
        
        $filters = [
            'status' => $request->get_param('status'),
            'limit' => $request->get_param('per_page') ?? 50,
            'offset' => ($request->get_param('page') ?? 1) - 1
        ];
        
        // Calculate offset from page number
        $filters['offset'] = $filters['offset'] * $filters['limit'];
        
        $result = $this->service->getExpertRequests($expertId, $filters);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $result['data'],
            'meta' => $result['meta']
        ], 200);
    }
    
    /**
     * Respond to a service request (approve or reject)
     * 
     * POST /rejimde/v1/service-requests/{id}/respond
     */
    public function respond_to_request($request) {
        $expertId = get_current_user_id();
        $requestId = (int) $request->get_param('id');
        $action = $request->get_param('action');
        
        if (!in_array($action, ['approve', 'reject'])) {
            return new WP_Error('invalid_action', 'Geçersiz işlem', ['status' => 400]);
        }
        
        $data = [
            'response_message' => $request->get_param('response_message'),
            'assign_package' => $request->get_param('assign_package') ?? false
        ];
        
        $result = $this->service->respondToRequest($requestId, $expertId, $action, $data);
        
        if (isset($result['error'])) {
            return new WP_Error('respond_failed', $result['error'], ['status' => 400]);
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $result
        ], 200);
    }
}
