<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use Rejimde\Services\InboxService;

/**
 * Inbox Controller
 * 
 * Handles inbox and messaging endpoints for expert-client communication
 */
class InboxController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/inbox';
    private $inboxService;

    public function __construct() {
        $this->inboxService = new InboxService();
    }

    public function register_routes() {
        // Expert endpoints
        
        // GET /pro/inbox - List threads
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_threads'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/inbox/{threadId} - Get thread messages
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_thread'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/inbox/{threadId}/reply - Reply to thread
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/reply', [
            'methods' => 'POST',
            'callback' => [$this, 'reply_to_thread'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/inbox/new - Create new thread
        register_rest_route($this->namespace, '/' . $this->base . '/new', [
            'methods' => 'POST',
            'callback' => [$this, 'create_thread'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/inbox/{threadId}/mark-read - Mark as read
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/mark-read', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_as_read'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/inbox/{threadId}/close - Close thread
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/close', [
            'methods' => 'POST',
            'callback' => [$this, 'close_thread'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/inbox/{threadId}/archive - Archive thread
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/archive', [
            'methods' => 'POST',
            'callback' => [$this, 'archive_thread'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/inbox/templates - List templates
        register_rest_route($this->namespace, '/' . $this->base . '/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_templates'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/inbox/templates - Create template
        register_rest_route($this->namespace, '/' . $this->base . '/templates', [
            'methods' => 'POST',
            'callback' => [$this, 'create_template'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // DELETE /pro/inbox/templates/{id} - Delete template
        register_rest_route($this->namespace, '/' . $this->base . '/templates/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_template'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/inbox/{threadId}/ai-draft - Generate AI draft
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/ai-draft', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_ai_draft'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Client endpoints
        
        // GET /me/inbox - Client's threads
        register_rest_route($this->namespace, '/me/inbox', [
            'methods' => 'GET',
            'callback' => [$this, 'get_client_threads'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // GET /me/inbox/{threadId} - Client's thread messages
        register_rest_route($this->namespace, '/me/inbox/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_client_thread'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /me/inbox/{threadId}/reply - Client reply
        register_rest_route($this->namespace, '/me/inbox/(?P<id>\d+)/reply', [
            'methods' => 'POST',
            'callback' => [$this, 'client_reply'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /**
     * Get threads for expert
     */
    public function get_threads($request) {
        $expertId = get_current_user_id();
        
        $options = [
            'status' => $request->get_param('status'),
            'search' => $request->get_param('search'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0,
        ];
        
        // Remove null values
        $options = array_filter($options, function($value) {
            return $value !== null;
        });
        
        $result = $this->inboxService->getThreads($expertId, 'expert', $options);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ], 200);
    }

    /**
     * Get single thread with messages (expert)
     */
    public function get_thread($request) {
        $expertId = get_current_user_id();
        $threadId = (int) $request['id'];
        
        $result = $this->inboxService->getThread($expertId, $threadId, 'expert');
        
        if (!$result) {
            return $this->error('Thread bulunamadı', 404);
        }
        
        return $this->success($result);
    }

    /**
     * Reply to thread (expert)
     */
    public function reply_to_thread($request) {
        $expertId = get_current_user_id();
        $threadId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyThreadOwnership($expertId, $threadId, 'expert')) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $data = [
            'content' => $request->get_param('content'),
            'content_type' => $request->get_param('content_type') ?? 'text',
            'attachments' => $request->get_param('attachments'),
        ];
        
        if (empty($data['content'])) {
            return $this->error('İçerik gerekli', 400);
        }
        
        $messageId = $this->inboxService->sendMessage($threadId, $expertId, 'expert', $data);
        
        if (!$messageId) {
            return $this->error('Mesaj gönderilemedi', 500);
        }
        
        return $this->success([
            'message_id' => $messageId,
            'message' => 'Mesaj gönderildi',
        ], 201);
    }

    /**
     * Create new thread
     */
    public function create_thread($request) {
        $expertId = get_current_user_id();
        
        $clientId = $request->get_param('client_id');
        $subject = $request->get_param('subject');
        $content = $request->get_param('content');
        
        if (empty($clientId) || empty($content)) {
            return $this->error('client_id ve içerik gerekli', 400);
        }
        
        $threadId = $this->inboxService->createThread($expertId, (int) $clientId, $subject, $content);
        
        if (!$threadId) {
            return $this->error('Thread oluşturulamadı', 500);
        }
        
        return $this->success([
            'thread_id' => $threadId,
            'message' => 'Thread oluşturuldu',
        ], 201);
    }

    /**
     * Mark thread as read
     */
    public function mark_as_read($request) {
        $expertId = get_current_user_id();
        $threadId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyThreadOwnership($expertId, $threadId, 'expert')) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $result = $this->inboxService->markAsRead($threadId, 'expert');
        
        if (!$result) {
            return $this->error('İşlem başarısız', 500);
        }
        
        return $this->success(['message' => 'Okundu olarak işaretlendi']);
    }

    /**
     * Close thread
     */
    public function close_thread($request) {
        $expertId = get_current_user_id();
        $threadId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyThreadOwnership($expertId, $threadId, 'expert')) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $result = $this->inboxService->closeThread($threadId);
        
        if (!$result) {
            return $this->error('İşlem başarısız', 500);
        }
        
        return $this->success(['message' => 'Thread kapatıldı']);
    }

    /**
     * Archive thread
     */
    public function archive_thread($request) {
        $expertId = get_current_user_id();
        $threadId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyThreadOwnership($expertId, $threadId, 'expert')) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $result = $this->inboxService->archiveThread($threadId);
        
        if (!$result) {
            return $this->error('İşlem başarısız', 500);
        }
        
        return $this->success(['message' => 'Thread arşivlendi']);
    }

    /**
     * Get templates
     */
    public function get_templates($request) {
        $expertId = get_current_user_id();
        
        $templates = $this->inboxService->getTemplates($expertId);
        
        return $this->success($templates);
    }

    /**
     * Create template
     */
    public function create_template($request) {
        $expertId = get_current_user_id();
        
        $data = [
            'title' => $request->get_param('title'),
            'content' => $request->get_param('content'),
            'category' => $request->get_param('category') ?? 'general',
        ];
        
        if (empty($data['title']) || empty($data['content'])) {
            return $this->error('Başlık ve içerik gerekli', 400);
        }
        
        $templateId = $this->inboxService->createTemplate($expertId, $data);
        
        if (!$templateId) {
            return $this->error('Şablon oluşturulamadı', 500);
        }
        
        return $this->success([
            'template_id' => $templateId,
            'message' => 'Şablon oluşturuldu',
        ], 201);
    }

    /**
     * Delete template
     */
    public function delete_template($request) {
        $expertId = get_current_user_id();
        $templateId = (int) $request['id'];
        
        $result = $this->inboxService->deleteTemplate($templateId, $expertId);
        
        if (!$result) {
            return $this->error('Şablon silinemedi', 500);
        }
        
        return $this->success(['message' => 'Şablon silindi']);
    }

    /**
     * Generate AI draft
     */
    public function generate_ai_draft($request) {
        $expertId = get_current_user_id();
        $threadId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyThreadOwnership($expertId, $threadId, 'expert')) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $context = $request->get_param('context') ?? 'last_5_messages';
        
        $draft = $this->inboxService->generateAIDraft($threadId, $context);
        
        return $this->success([
            'draft' => $draft,
        ]);
    }

    /**
     * Get client's threads
     */
    public function get_client_threads($request) {
        $clientId = get_current_user_id();
        
        $options = [
            'status' => $request->get_param('status'),
            'search' => $request->get_param('search'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0,
        ];
        
        // Remove null values
        $options = array_filter($options, function($value) {
            return $value !== null;
        });
        
        $result = $this->inboxService->getThreads($clientId, 'client', $options);
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ], 200);
    }

    /**
     * Get client's thread messages
     */
    public function get_client_thread($request) {
        $clientId = get_current_user_id();
        $threadId = (int) $request['id'];
        
        $result = $this->inboxService->getThread($clientId, $threadId, 'client');
        
        if (!$result) {
            return $this->error('Thread bulunamadı', 404);
        }
        
        return $this->success($result);
    }

    /**
     * Client reply to thread
     */
    public function client_reply($request) {
        $clientId = get_current_user_id();
        $threadId = (int) $request['id'];
        
        // Verify ownership
        if (!$this->verifyThreadOwnership($clientId, $threadId, 'client')) {
            return $this->error('Yetkiniz yok', 403);
        }
        
        $data = [
            'content' => $request->get_param('content'),
            'content_type' => $request->get_param('content_type') ?? 'text',
            'attachments' => $request->get_param('attachments'),
        ];
        
        if (empty($data['content'])) {
            return $this->error('İçerik gerekli', 400);
        }
        
        $messageId = $this->inboxService->sendMessage($threadId, $clientId, 'client', $data);
        
        if (!$messageId) {
            return $this->error('Mesaj gönderilemedi', 500);
        }
        
        return $this->success([
            'message_id' => $messageId,
            'message' => 'Mesaj gönderildi',
        ], 201);
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
     * Verify thread ownership
     * 
     * @param int $userId User ID
     * @param int $threadId Thread ID
     * @param string $userType 'expert' or 'client'
     * @return bool
     */
    private function verifyThreadOwnership(int $userId, int $threadId, string $userType): bool {
        global $wpdb;
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        if ($userType === 'expert') {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM $table_threads t 
                 INNER JOIN $table_relationships r ON t.relationship_id = r.id 
                 WHERE t.id = %d AND r.expert_id = %d",
                $threadId,
                $userId
            ));
        } else {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM $table_threads t 
                 INNER JOIN $table_relationships r ON t.relationship_id = r.id 
                 WHERE t.id = %d AND r.client_id = %d",
                $threadId,
                $userId
            ));
        }
        
        return $count > 0;
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
