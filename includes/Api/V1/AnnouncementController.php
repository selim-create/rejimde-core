<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\AnnouncementService;

/**
 * Announcement Controller
 * 
 * Handles announcement management endpoints
 */
class AnnouncementController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'announcements';
    private $announcementService;

    public function __construct() {
        $this->announcementService = new AnnouncementService();
    }

    public function register_routes() {
        // Public/User endpoints
        
        // GET /announcements - Get active announcements
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_active_announcements'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // GET /announcements/{id} - Get announcement detail
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_announcement'],
            'permission_callback' => '__return_true',
        ]);

        // POST /announcements/{id}/dismiss - Dismiss announcement
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/dismiss', [
            'methods' => 'POST',
            'callback' => [$this, 'dismiss_announcement'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Admin endpoints
        
        // GET /admin/announcements - Get all announcements
        register_rest_route($this->namespace, '/admin/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_all_announcements'],
            'permission_callback' => [$this, 'check_admin_auth'],
        ]);

        // POST /admin/announcements - Create announcement
        register_rest_route($this->namespace, '/admin/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'create_announcement'],
            'permission_callback' => [$this, 'check_admin_auth'],
        ]);

        // PATCH /admin/announcements/{id} - Update announcement
        register_rest_route($this->namespace, '/admin/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_announcement'],
            'permission_callback' => [$this, 'check_admin_auth'],
        ]);

        // DELETE /admin/announcements/{id} - Delete announcement
        register_rest_route($this->namespace, '/admin/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_announcement'],
            'permission_callback' => [$this, 'check_admin_auth'],
        ]);

        // Pro user endpoints

        // GET /pro/announcements - Pro's announcements list
        register_rest_route($this->namespace, '/pro/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_pro_announcements'],
            'permission_callback' => [$this, 'check_pro_auth'],
        ]);

        // POST /pro/announcements - Create pro announcement
        register_rest_route($this->namespace, '/pro/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'create_pro_announcement'],
            'permission_callback' => [$this, 'check_pro_auth'],
        ]);

        // DELETE /pro/announcements/{id} - Delete pro announcement
        register_rest_route($this->namespace, '/pro/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_pro_announcement'],
            'permission_callback' => [$this, 'check_pro_auth'],
        ]);
    }

    /**
     * GET /announcements
     */
    public function get_active_announcements(WP_REST_Request $request): WP_REST_Response {
        $userId = get_current_user_id();
        
        $announcements = $this->announcementService->getActiveAnnouncements($userId);
        
        return $this->success($announcements);
    }

    /**
     * GET /announcements/{id}
     */
    public function get_announcement(WP_REST_Request $request): WP_REST_Response {
        $announcementId = (int) $request['id'];
        
        $announcement = $this->announcementService->getAnnouncement($announcementId);
        
        if (!$announcement) {
            return $this->error('Announcement not found', 404);
        }
        
        return $this->success($announcement);
    }

    /**
     * POST /announcements/{id}/dismiss
     */
    public function dismiss_announcement(WP_REST_Request $request): WP_REST_Response {
        $userId = get_current_user_id();
        $announcementId = (int) $request['id'];
        
        $result = $this->announcementService->dismissAnnouncement($announcementId, $userId);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['message' => 'Announcement dismissed']);
    }

    /**
     * GET /admin/announcements
     */
    public function get_all_announcements(WP_REST_Request $request): WP_REST_Response {
        $announcements = $this->announcementService->getAllAnnouncements();
        
        return $this->success($announcements);
    }

    /**
     * POST /admin/announcements
     */
    public function create_announcement(WP_REST_Request $request): WP_REST_Response {
        $data = [
            'title' => $request->get_param('title'),
            'content' => $request->get_param('content'),
            'type' => $request->get_param('type'),
            'target_roles' => $request->get_param('target_roles'),
            'start_date' => $request->get_param('start_date'),
            'end_date' => $request->get_param('end_date'),
            'is_dismissible' => $request->get_param('is_dismissible'),
            'priority' => $request->get_param('priority'),
        ];
        
        $result = $this->announcementService->createAnnouncement($data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['id' => $result], 'Announcement created successfully', 201);
    }

    /**
     * PATCH /admin/announcements/{id}
     */
    public function update_announcement(WP_REST_Request $request): WP_REST_Response {
        $announcementId = (int) $request['id'];
        
        $data = [];
        $allowedFields = ['title', 'content', 'type', 'target_roles', 'start_date', 'end_date', 'is_dismissible', 'priority'];
        
        foreach ($allowedFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }
        
        $result = $this->announcementService->updateAnnouncement($announcementId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['message' => 'Announcement updated successfully']);
    }

    /**
     * DELETE /admin/announcements/{id}
     */
    public function delete_announcement(WP_REST_Request $request): WP_REST_Response {
        $announcementId = (int) $request['id'];
        
        $result = $this->announcementService->deleteAnnouncement($announcementId);
        
        if (!$result) {
            return $this->error('Announcement not found', 404);
        }
        
        return $this->success(['message' => 'Announcement deleted successfully']);
    }

    /**
     * GET /pro/announcements
     */
    public function get_pro_announcements(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $announcements = $this->announcementService->getProAnnouncements($expertId);
        return $this->success($announcements);
    }

    /**
     * POST /pro/announcements
     */
    public function create_pro_announcement(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        error_log("Rejimde API: create_pro_announcement called by expert $expertId");
        
        $data = [
            'title' => $request->get_param('title'),
            'content' => $request->get_param('content'),
            'type' => $request->get_param('type') ?? 'info',
            'start_date' => $request->get_param('start_date') ?? current_time('mysql'),
            'end_date' => $request->get_param('end_date'),
            'is_dismissible' => $request->get_param('is_dismissible') ?? true,
            'priority' => $request->get_param('priority') ?? 0,
        ];
        
        $result = $this->announcementService->createProAnnouncement($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            error_log("Rejimde API: create_pro_announcement error - " . $result['error']);
            return $this->error($result['error'], 400);
        }
        
        // Get the full announcement to return
        $announcement = $this->announcementService->getAnnouncement($result);
        
        if (!$announcement) {
            error_log("Rejimde API: Failed to retrieve created announcement ID $result");
            return $this->error('Announcement created but failed to retrieve', 500);
        }
        
        error_log("Rejimde API: Successfully created announcement ID $result");
        return $this->success($announcement, 'Announcement created successfully', 201);
    }

    /**
     * DELETE /pro/announcements/{id}
     */
    public function delete_pro_announcement(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $announcementId = (int) $request['id'];
        
        $result = $this->announcementService->deleteProAnnouncement($announcementId, $expertId);
        
        if (!$result) {
            return $this->error('Announcement not found or access denied', 404);
        }
        
        return $this->success(['message' => 'Announcement deleted successfully']);
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

    public function check_admin_auth(): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('administrator', (array) $user->roles);
    }

    public function check_pro_auth(): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('rejimde_pro', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles);
    }
}
