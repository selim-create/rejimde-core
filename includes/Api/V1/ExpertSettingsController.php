<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\ExpertSettingsService;

/**
 * Expert Settings Controller
 * 
 * Handles expert settings endpoints
 */
class ExpertSettingsController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/settings';
    private $service;

    public function __construct() {
        $this->service = new ExpertSettingsService();
    }

    public function register_routes() {
        // GET /pro/settings - Get all settings
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/settings - Update settings
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/settings/addresses - Get addresses
        register_rest_route($this->namespace, '/' . $this->base . '/addresses', [
            'methods' => 'GET',
            'callback' => [$this, 'get_addresses'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/settings/addresses - Add address
        register_rest_route($this->namespace, '/' . $this->base . '/addresses', [
            'methods' => 'POST',
            'callback' => [$this, 'add_address'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // PATCH /pro/settings/addresses/{id} - Update address
        register_rest_route($this->namespace, '/' . $this->base . '/addresses/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_address'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // DELETE /pro/settings/addresses/{id} - Delete address
        register_rest_route($this->namespace, '/' . $this->base . '/addresses/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_address'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/addresses - Get addresses (alternative endpoint for frontend)
        register_rest_route($this->namespace, '/pro/addresses', [
            'methods' => 'GET',
            'callback' => [$this, 'get_addresses'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // PATCH /pro/addresses/{id} - Update address (alternative endpoint for frontend)
        register_rest_route($this->namespace, '/pro/addresses/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_address'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // DELETE /pro/addresses/{id} - Delete address (alternative endpoint for frontend)
        register_rest_route($this->namespace, '/pro/addresses/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_address'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
    }

    /**
     * GET /pro/settings
     */
    public function get_settings(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $settings = $this->service->getSettings($expertId);
        
        return $this->success($settings);
    }

    /**
     * POST /pro/settings
     */
    public function update_settings(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $data = $request->get_json_params();
        
        $result = $this->service->updateSettings($expertId, $data);
        
        if ($result) {
            return $this->success([
                'message' => 'Settings updated successfully'
            ]);
        }
        
        return $this->error('Failed to update settings', 500);
    }

    /**
     * GET /pro/settings/addresses
     */
    public function get_addresses(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $addresses = $this->service->getAddresses($expertId);
        
        return $this->success($addresses);
    }

    /**
     * POST /pro/settings/addresses
     */
    public function add_address(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $data = $request->get_json_params();
        
        // Validate required fields
        if (!isset($data['title']) || $data['title'] === '' || !isset($data['address']) || $data['address'] === '') {
            return $this->error('Title and address are required', 400);
        }
        
        $addressId = $this->service->addAddress($expertId, $data);
        
        if ($addressId) {
            return $this->success([
                'id' => $addressId,
                'message' => 'Address added successfully'
            ], 'Address added', 201);
        }
        
        return $this->error('Failed to add address', 500);
    }

    /**
     * PATCH /pro/settings/addresses/{id}
     */
    public function update_address(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $addressId = (int) $request->get_param('id');
        $data = $request->get_json_params();
        
        $result = $this->service->updateAddress($expertId, $addressId, $data);
        
        if ($result) {
            return $this->success([
                'message' => 'Address updated successfully'
            ]);
        }
        
        return $this->error('Address not found or failed to update', 404);
    }

    /**
     * DELETE /pro/settings/addresses/{id}
     */
    public function delete_address(WP_REST_Request $request) {
        $expertId = get_current_user_id();
        $addressId = (int) $request->get_param('id');
        
        $result = $this->service->deleteAddress($expertId, $addressId);
        
        if ($result) {
            return $this->success([
                'message' => 'Address deleted successfully'
            ]);
        }
        
        return $this->error('Address not found or failed to delete', 404);
    }

    // Helper methods

    protected function success($data = null, $message = 'Success', $code = 200) {
        return new WP_REST_Response([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function error($message = 'Error', $code = 400) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $message
        ], $code);
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
