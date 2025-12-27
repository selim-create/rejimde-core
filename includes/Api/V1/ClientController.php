<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\ClientService;
use Rejimde\Services\NotificationService;

/**
 * Client Controller
 * 
 * Handles client management endpoints (CRM)
 */
class ClientController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/clients';
    private $clientService;
    private $notificationService;

    public function __construct() {
        $this->clientService = new ClientService();
        $this->notificationService = new NotificationService();
    }

    public function register_routes() {
        // GET /pro/clients - List clients
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_clients'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/clients/{id} - Get client detail
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_client'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/clients - Add client manually
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'add_client'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/clients/invite - Create invite link
        register_rest_route($this->namespace, '/' . $this->base . '/invite', [
            'methods' => 'POST',
            'callback' => [$this, 'create_invite'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/clients/accept-invite - Accept invite (client side)
        register_rest_route($this->namespace, '/' . $this->base . '/accept-invite', [
            'methods' => 'POST',
            'callback' => [$this, 'accept_invite'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // PATCH /pro/clients/{id}/status - Update status
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/status', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_status'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // PATCH /pro/clients/{id}/package - Update package
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/package', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_package'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/clients/{id}/activity - Get client activity
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/activity', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activity'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/clients/{id}/plans - Get assigned plans
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/plans', [
            'methods' => 'GET',
            'callback' => [$this, 'get_plans'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/clients/{id}/notes - Add note
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/notes', [
            'methods' => 'POST',
            'callback' => [$this, 'add_note'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // DELETE /pro/clients/{id}/notes/{noteId} - Delete note
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/notes/(?P<noteId>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_note'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/clients/{id}/award-badge - Award badge
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/award-badge', [
            'methods' => 'POST',
            'callback' => [$this, 'award_badge'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
    }

    /**
     * GET /pro/clients
     */
    public function get_clients(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $filters = [
            'status' => $request->get_param('status'),
            'search' => $request->get_param('search'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0,
        ];
        
        // Remove null values
        $filters = array_filter($filters, function($value) {
            return $value !== null;
        });
        
        $result = $this->clientService->getClients($expertId, $filters);
        
        return $this->success($result['data'], 'Success', 200, $result['meta']);
    }

    /**
     * GET /pro/clients/{id}
     */
    public function get_client(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $relationshipId = (int) $request['id'];
        
        $client = $this->clientService->getClient($expertId, $relationshipId);
        
        if (!$client) {
            return $this->error('Client not found', 404);
        }
        
        return $this->success($client);
    }

    /**
     * POST /pro/clients
     */
    public function add_client(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $data = [
            'client_id' => $request->get_param('client_id'),
            'email' => $request->get_param('email'),
            'name' => $request->get_param('name'),
            'package_data' => $request->get_param('package'),
            'source' => 'manual',
        ];
        
        if (empty($data['client_id']) && empty($data['email'])) {
            return $this->error('Either client_id or email is required', 400);
        }
        
        $result = $this->clientService->addClient($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['relationship_id' => $result], 'Client added successfully', 201);
    }

    /**
     * POST /pro/clients/invite
     */
    public function create_invite(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $data = [
            'package_name' => $request->get_param('package_name'),
            'package_type' => $request->get_param('package_type'),
            'total_sessions' => $request->get_param('total_sessions'),
            'validity_days' => $request->get_param('validity_days'),
            'price' => $request->get_param('price'),
        ];
        
        $result = $this->clientService->createInvite($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success($result, 'Invite created successfully', 201);
    }

    /**
     * POST /pro/clients/accept-invite
     */
    public function accept_invite(WP_REST_Request $request): WP_REST_Response {
        $clientId = get_current_user_id();
        $token = $request->get_param('token');
        
        if (empty($token)) {
            return $this->error('Token is required', 400);
        }
        
        $result = $this->clientService->acceptInvite($token, $clientId);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success($result, 'Invite accepted successfully');
    }

    /**
     * PATCH /pro/clients/{id}/status
     */
    public function update_status(WP_REST_Request $request): WP_REST_Response {
        $relationshipId = (int) $request['id'];
        $status = $request->get_param('status');
        $reason = $request->get_param('reason');
        
        if (empty($status)) {
            return $this->error('Status is required', 400);
        }
        
        $result = $this->clientService->updateStatus($relationshipId, $status, $reason);
        
        if (!$result) {
            return $this->error('Failed to update status', 500);
        }
        
        return $this->success(['message' => 'Status updated successfully']);
    }

    /**
     * PATCH /pro/clients/{id}/package
     */
    public function update_package(WP_REST_Request $request): WP_REST_Response {
        $relationshipId = (int) $request['id'];
        
        $data = [
            'action' => $request->get_param('action'), // renew, extend, cancel
            'data' => $request->get_param('data'),
        ];
        
        if (empty($data['action'])) {
            return $this->error('Action is required', 400);
        }
        
        $result = $this->clientService->updatePackage($relationshipId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['message' => 'Package updated successfully']);
    }

    /**
     * GET /pro/clients/{id}/activity
     */
    public function get_activity(WP_REST_Request $request): WP_REST_Response {
        $relationshipId = (int) $request['id'];
        $limit = $request->get_param('limit') ?? 50;
        
        // Get client ID from relationship
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $clientId = $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM $table_relationships WHERE id = %d",
            $relationshipId
        ));
        
        if (!$clientId) {
            return $this->error('Relationship not found', 404);
        }
        
        $activity = $this->clientService->getClientActivity((int) $clientId, (int) $limit);
        
        return $this->success($activity);
    }

    /**
     * GET /pro/clients/{id}/plans
     */
    public function get_plans(WP_REST_Request $request): WP_REST_Response {
        $relationshipId = (int) $request['id'];
        
        $plans = $this->clientService->getAssignedPlans($relationshipId);
        
        return $this->success($plans);
    }

    /**
     * POST /pro/clients/{id}/notes
     */
    public function add_note(WP_REST_Request $request): WP_REST_Response {
        $relationshipId = (int) $request['id'];
        
        $data = [
            'type' => $request->get_param('type') ?? 'general',
            'content' => $request->get_param('content'),
            'is_pinned' => $request->get_param('is_pinned') ?? false,
        ];
        
        if (empty($data['content'])) {
            return $this->error('Content is required', 400);
        }
        
        $result = $this->clientService->addNote($relationshipId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['note_id' => $result], 'Note added successfully', 201);
    }

    /**
     * DELETE /pro/clients/{id}/notes/{noteId}
     */
    public function delete_note(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $noteId = (int) $request['noteId'];
        
        $result = $this->clientService->deleteNote($noteId, $expertId);
        
        if (!$result) {
            return $this->error('Note not found or access denied', 404);
        }
        
        return $this->success(['message' => 'Note deleted successfully']);
    }

    /**
     * POST /pro/clients/{id}/award-badge
     */
    public function award_badge(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $relationshipId = (int) $request['id'];
        $badgeId = $request->get_param('badge_id');
        
        if (empty($badgeId)) {
            return $this->error('Badge ID is required', 400);
        }
        
        // Get client ID from relationship
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $clientId = $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM $table_relationships WHERE id = %d AND expert_id = %d",
            $relationshipId,
            $expertId
        ));
        
        if (!$clientId) {
            return $this->error('Client not found', 404);
        }
        
        // Award badge via user meta
        $existingBadges = get_user_meta((int) $clientId, 'rejimde_badges', true) ?: [];
        if (!is_array($existingBadges)) {
            $existingBadges = [];
        }
        
        if (!in_array($badgeId, $existingBadges)) {
            $existingBadges[] = $badgeId;
            update_user_meta((int) $clientId, 'rejimde_badges', $existingBadges);
            
            // Send notification
            $this->notificationService->create((int) $clientId, 'badge_awarded', [
                'actor_id' => $expertId,
                'entity_type' => 'badge',
                'entity_id' => $badgeId
            ]);
        }
        
        return $this->success(['message' => 'Badge awarded successfully']);
    }

    // Helper methods

    protected function success($data = null, $message = 'Success', $code = 200, $meta = null): WP_REST_Response {
        $response = [
            'status' => 'success',
            'data' => $data
        ];
        
        if ($meta) {
            $response['meta'] = $meta;
        }
        
        return new WP_REST_Response($response, $code);
    }

    protected function error($message = 'Error', $code = 400): WP_REST_Response {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $message
        ], $code);
    }

    protected function check_auth(): bool {
        return is_user_logged_in();
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
