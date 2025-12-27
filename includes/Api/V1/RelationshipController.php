<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use Rejimde\Services\ClientService;

/**
 * Relationship Controller (CRM)
 * 
 * Handles expert-client relationship endpoints
 */
class RelationshipController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/clients';
    private $clientService;

    public function __construct() {
        $this->clientService = new ClientService();
    }

    public function register_routes() {
        // GET /pro/clients - List all clients
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_clients'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/clients/{id} - Get single client
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_client'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/clients - Add new client
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

        // POST /pro/clients/{id}/status - Update status
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/status', [
            'methods' => 'POST',
            'callback' => [$this, 'update_status'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/clients/{id}/package - Update package
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/package', [
            'methods' => 'POST',
            'callback' => [$this, 'update_package'],
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

        // GET /pro/clients/{id}/activity - Get activity
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/activity', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activity'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/clients/{id}/plans - Get plans
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/plans', [
            'methods' => 'GET',
            'callback' => [$this, 'get_plans'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /me/experts - Client view: list their experts
        register_rest_route($this->namespace, '/me/experts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_experts'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /pro/clients/accept-invite - Accept invite (client-side)
        register_rest_route($this->namespace, '/' . $this->base . '/accept-invite', [
            'methods' => 'POST',
            'callback' => [$this, 'accept_invite'],
            'permission_callback' => [$this, 'check_auth'], // Normal auth, not expert
        ]);
    }

    /**
     * Get all clients for expert
     */
    public function get_clients($request) {
        $expertId = get_current_user_id();
        
        $options = [
            'status' => $request->get_param('status'),
            'search' => $request->get_param('search'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0
        ];
        
        // Remove null values
        $options = array_filter($options, function($value) {
            return $value !== null;
        });
        
        $result = $this->clientService->getClients($expertId, $options);
        
        // Return data and meta at top level, not nested in data
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $result['data'],
            'meta' => $result['meta']
        ], 200);
    }

    /**
     * Get single client details
     */
    public function get_client($request) {
        $expertId = get_current_user_id();
        $relationshipId = (int) $request['id'];
        
        $client = $this->clientService->getClient($expertId, $relationshipId);
        
        if (!$client) {
            return $this->error('İlişki bulunamadı', 404);
        }
        
        return $this->success($client);
    }

    /**
     * Add new client manually
     */
    public function add_client($request) {
        $expertId = get_current_user_id();
        
        $data = [
            'client_email' => $request->get_param('client_email'),
            'client_name' => $request->get_param('client_name'),
            'package_name' => $request->get_param('package_name'),
            'package_type' => $request->get_param('package_type'),
            'total_sessions' => $request->get_param('total_sessions'),
            'start_date' => $request->get_param('start_date'),
            'end_date' => $request->get_param('end_date'),
            'price' => $request->get_param('price'),
            'notes' => $request->get_param('notes')
        ];
        
        // Validate required fields
        if (empty($data['client_email'])) {
            return $this->error('client_email gerekli', 400);
        }
        
        // Validate email format
        if (!is_email($data['client_email'])) {
            return $this->error('Geçerli bir e-posta adresi girin', 400);
        }
        
        $result = $this->clientService->addClient($expertId, $data);
        
        // Handle error responses
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 409); // 409 Conflict
        }
        
        if (!$result || (is_array($result) && !isset($result['relationship_id']))) {
            return $this->error('Danışan eklenemedi', 500);
        }
        
        $relationshipId = is_array($result) ? $result['relationship_id'] : $result;
        $reactivated = is_array($result) && isset($result['reactivated']) && $result['reactivated'];
        
        return $this->success([
            'relationship_id' => $relationshipId,
            'message' => $reactivated ? 'Danışan yeniden aktifleştirildi' : 'Danışan başarıyla eklendi'
        ], 201);
    }

    /**
     * Create invite link
     */
    public function create_invite($request) {
        $expertId = get_current_user_id();
        
        $data = [
            'package_name' => $request->get_param('package_name'),
            'package_type' => $request->get_param('package_type'),
            'total_sessions' => $request->get_param('total_sessions'),
            'duration_months' => $request->get_param('duration_months'),
            'price' => $request->get_param('price')
        ];
        
        $invite = $this->clientService->createInvite($expertId, $data);
        
        // Error handling
        if (isset($invite['error'])) {
            return $this->error($invite['error'], 500);
        }
        
        return $this->success($invite);
    }

    /**
     * Update relationship status
     */
    public function update_status($request) {
        $expertId = get_current_user_id();
        $relationshipId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyOwnership($expertId, $relationshipId)) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $status = $request->get_param('status');
        $reason = $request->get_param('reason');
        
        if (empty($status)) {
            return $this->error('status gerekli', 400);
        }
        
        $validStatuses = ['pending', 'active', 'paused', 'archived', 'blocked'];
        if (!in_array($status, $validStatuses)) {
            return $this->error('Geçersiz status', 400);
        }
        
        $result = $this->clientService->updateStatus($relationshipId, $status, $reason);
        
        if (!$result) {
            return $this->error('Durum güncellenemedi', 500);
        }
        
        return $this->success(['message' => 'Durum güncellendi']);
    }

    /**
     * Update or renew package
     */
    public function update_package($request) {
        $expertId = get_current_user_id();
        $relationshipId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyOwnership($expertId, $relationshipId)) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $data = [
            'action' => $request->get_param('action'),
            'package_name' => $request->get_param('package_name'),
            'total_sessions' => $request->get_param('total_sessions'),
            'start_date' => $request->get_param('start_date'),
            'price' => $request->get_param('price')
        ];
        
        $result = $this->clientService->updatePackage($relationshipId, $data);
        
        if ($result === false) {
            return $this->error('Paket güncellenemedi', 500);
        }
        
        return $this->success(['message' => 'Paket güncellendi']);
    }

    /**
     * Add note to client
     */
    public function add_note($request) {
        $expertId = get_current_user_id();
        $relationshipId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyOwnership($expertId, $relationshipId)) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $data = [
            'type' => $request->get_param('type') ?? 'general',
            'content' => $request->get_param('content'),
            'is_pinned' => $request->get_param('is_pinned') ?? false
        ];
        
        if (empty($data['content'])) {
            return $this->error('content gerekli', 400);
        }
        
        $noteId = $this->clientService->addNote($relationshipId, $data);
        
        if (!$noteId) {
            return $this->error('Not eklenemedi', 500);
        }
        
        return $this->success([
            'note_id' => $noteId,
            'message' => 'Not eklendi'
        ], 201);
    }

    /**
     * Delete note
     */
    public function delete_note($request) {
        $expertId = get_current_user_id();
        $relationshipId = (int) $request['id'];
        $noteId = (int) $request['noteId'];
        
        // Verify ownership
        if (!$this->verifyOwnership($expertId, $relationshipId)) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $result = $this->clientService->deleteNote($noteId, $expertId);
        
        if (!$result) {
            return $this->error('Not silinemedi', 500);
        }
        
        return $this->success(['message' => 'Not silindi']);
    }

    /**
     * Get client activity
     */
    public function get_activity($request) {
        $expertId = get_current_user_id();
        $relationshipId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyOwnership($expertId, $relationshipId)) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        // Get client ID from relationship
        $clientId = $this->getClientId($relationshipId);
        if (!$clientId) {
            return $this->error('İlişki bulunamadı', 404);
        }
        
        $limit = $request->get_param('limit') ?? 50;
        $activity = $this->clientService->getClientActivity($clientId, $limit);
        
        return $this->success($activity);
    }

    /**
     * Get assigned plans
     */
    public function get_plans($request) {
        $expertId = get_current_user_id();
        $relationshipId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyOwnership($expertId, $relationshipId)) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $plans = $this->clientService->getAssignedPlans($relationshipId);
        
        return $this->success($plans);
    }

    /**
     * Get client's experts (client-side endpoint)
     */
    public function get_my_experts($request) {
        $clientId = get_current_user_id();
        
        $experts = $this->clientService->getClientExperts($clientId);
        
        return $this->success($experts);
    }

    /**
     * Accept invite link (client-side)
     */
    public function accept_invite($request) {
        $clientId = get_current_user_id();
        $token = $request->get_param('token');
        
        if (empty($token)) {
            return $this->error('Token gerekli', 400);
        }
        
        $result = $this->clientService->acceptInvite($token, $clientId);
        
        if (isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success([
            'message' => 'Davet kabul edildi',
            'expert' => $result['expert']
        ]);
    }

    /**
     * Check if user is expert (rejimde_pro role)
     */
    public function check_expert_auth(): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('rejimde_pro', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles);
    }

    /**
     * Check if user is authenticated
     */
    public function check_auth(): bool {
        return is_user_logged_in();
    }

    /**
     * Verify expert owns the relationship
     * 
     * @param int $expertId Expert user ID
     * @param int $relationshipId Relationship ID
     * @return bool
     */
    private function verifyOwnership(int $expertId, int $relationshipId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_relationships';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE id = %d AND expert_id = %d",
            $relationshipId,
            $expertId
        ));
        
        return $count > 0;
    }

    /**
     * Get client ID from relationship
     * 
     * @param int $relationshipId Relationship ID
     * @return int|null
     */
    private function getClientId(int $relationshipId): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_relationships';
        
        $clientId = $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM $table WHERE id = %d",
            $relationshipId
        ));
        
        return $clientId ? (int) $clientId : null;
    }

    /**
     * Success response
     */
    protected function success($data = null, $code = 200) {
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data
        ], $code);
    }

    /**
     * Error response
     */
    protected function error($message = 'Error', $code = 400) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $message
        ], $code);
    }
}
