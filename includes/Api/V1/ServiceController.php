<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\ServiceManager;

/**
 * Service Controller
 * 
 * Handles service/package management endpoints
 */
class ServiceController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/services';
    private $serviceManager;

    public function __construct() {
        $this->serviceManager = new ServiceManager();
    }

    public function register_routes() {
        // GET /pro/services - List services
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_services'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/services/{id} - Get service detail
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_service'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/services - Create service
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'create_service'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // PATCH /pro/services/{id} - Update service
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_service'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // DELETE /pro/services/{id} - Delete service (soft delete)
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_service'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // PATCH /pro/services/{id}/toggle - Toggle active status
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/toggle', [
            'methods' => 'PATCH',
            'callback' => [$this, 'toggle_active'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/services/reorder - Reorder services
        register_rest_route($this->namespace, '/' . $this->base . '/reorder', [
            'methods' => 'POST',
            'callback' => [$this, 'reorder_services'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
    }

    /**
     * GET /pro/services
     */
    public function get_services(WP_REST_Request $request): WP_REST_Response {
        try {
            $expertId = get_current_user_id();
            
            if (!$expertId) {
                return $this->error('Not authenticated', 401);
            }
            
            $services = $this->serviceManager->getServices($expertId);
            
            return $this->success($services);
        } catch (\Exception $e) {
            error_log('Rejimde Services Error: ' . $e->getMessage());
            return $this->error('Failed to fetch services', 500);
        }
    }

    /**
     * GET /pro/services/{id}
     */
    public function get_service(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $serviceId = (int) $request['id'];
        
        $service = $this->serviceManager->getService($serviceId, $expertId);
        
        if (!$service) {
            return $this->error('Service not found', 404);
        }
        
        return $this->success($service);
    }

    /**
     * POST /pro/services
     */
    public function create_service(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $data = [
            'name' => $request->get_param('name'),
            'description' => $request->get_param('description'),
            'type' => $request->get_param('type'),
            'price' => $request->get_param('price'),
            'currency' => $request->get_param('currency'),
            'duration_minutes' => $request->get_param('duration_minutes'),
            'session_count' => $request->get_param('session_count'),
            'validity_days' => $request->get_param('validity_days'),
            'is_active' => $request->get_param('is_active'),
            'is_featured' => $request->get_param('is_featured'),
            'is_public' => $request->get_param('is_public'),
            'color' => $request->get_param('color'),
            'sort_order' => $request->get_param('sort_order'),
            'booking_enabled' => $request->get_param('booking_enabled'),
        ];
        
        $result = $this->serviceManager->createService($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['id' => $result], 'Service created successfully', 201);
    }

    /**
     * PATCH /pro/services/{id}
     */
    public function update_service(WP_REST_Request $request): WP_REST_Response {
        $serviceId = (int) $request['id'];
        
        $data = [];
        $allowedFields = [
            'name', 'description', 'type', 'price', 'currency', 'duration_minutes',
            'session_count', 'validity_days', 'is_active', 'is_featured', 'is_public',
            'color', 'sort_order', 'booking_enabled'
        ];
        
        foreach ($allowedFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }
        
        $result = $this->serviceManager->updateService($serviceId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['message' => 'Service updated successfully']);
    }

    /**
     * DELETE /pro/services/{id}
     */
    public function delete_service(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $serviceId = (int) $request['id'];
        
        $result = $this->serviceManager->deleteService($serviceId, $expertId);
        
        if (!$result) {
            return $this->error('Service not found or access denied', 404);
        }
        
        return $this->success(['message' => 'Service deactivated successfully']);
    }

    /**
     * PATCH /pro/services/{id}/toggle
     */
    public function toggle_active(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $serviceId = (int) $request['id'];
        
        $result = $this->serviceManager->toggleActive($serviceId, $expertId);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 404);
        }
        
        return $this->success($result, 'Service status toggled successfully');
    }

    /**
     * POST /pro/services/reorder
     */
    public function reorder_services(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $order = $request->get_param('order');
        
        if (!is_array($order)) {
            return $this->error('Order must be an array of service IDs', 400);
        }
        
        $result = $this->serviceManager->reorderServices($expertId, $order);
        
        if (!$result) {
            return $this->error('Failed to reorder services', 500);
        }
        
        return $this->success(['message' => 'Services reordered successfully']);
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
