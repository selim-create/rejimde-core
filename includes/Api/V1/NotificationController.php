<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use Rejimde\Services\NotificationService;

/**
 * Notification Controller
 * 
 * Handles notification-related endpoints
 */
class NotificationController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'notifications';
    private $notificationService;

    public function __construct() {
        $this->notificationService = new NotificationService();
    }

    public function register_routes() {
        // Get notifications
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_notifications'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Get unread count
        register_rest_route($this->namespace, '/' . $this->base . '/unread-count', [
            'methods' => 'GET',
            'callback' => [$this, 'get_unread_count'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Mark as read
        register_rest_route($this->namespace, '/' . $this->base . '/mark-read', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_as_read'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Get preferences
        register_rest_route($this->namespace, '/' . $this->base . '/preferences', [
            'methods' => 'GET',
            'callback' => [$this, 'get_preferences'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Update preferences
        register_rest_route($this->namespace, '/' . $this->base . '/preferences', [
            'methods' => 'POST',
            'callback' => [$this, 'update_preferences'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /**
     * Get notifications
     */
    public function get_notifications($request) {
        $userId = get_current_user_id();
        
        $options = [
            'category' => $request->get_param('category'),
            'is_read' => $request->get_param('is_read'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0
        ];
        
        // Remove null values
        $options = array_filter($options, function($value) {
            return $value !== null;
        });
        
        $notifications = $this->notificationService->getNotifications($userId, $options);
        
        return $this->success($notifications);
    }

    /**
     * Get unread count
     */
    public function get_unread_count($request) {
        $userId = get_current_user_id();
        $count = $this->notificationService->getUnreadCount($userId);
        
        return $this->success(['unread_count' => $count]);
    }

    /**
     * Mark notifications as read
     */
    public function mark_as_read($request) {
        $userId = get_current_user_id();
        $params = $request->get_json_params();
        
        $ids = $params['ids'] ?? 'all';
        
        $updated = $this->notificationService->markAsRead($userId, $ids);
        
        return $this->success([
            'updated_count' => $updated,
            'message' => 'Bildirimler okundu olarak işaretlendi.'
        ]);
    }

    /**
     * Get notification preferences
     */
    public function get_preferences($request) {
        $userId = get_current_user_id();
        
        // Get preferences for all categories
        $categories = ['social', 'system', 'level', 'circle', 'points', 'expert'];
        $preferences = [];
        
        foreach ($categories as $category) {
            $preferences[$category] = $this->notificationService->getUserPreferences($userId, $category);
        }
        
        return $this->success($preferences);
    }

    /**
     * Update notification preferences
     */
    public function update_preferences($request) {
        $userId = get_current_user_id();
        $params = $request->get_json_params();
        
        $preferences = $params['preferences'] ?? [];
        
        if (empty($preferences)) {
            return $this->error('Tercihler boş olamaz.', 400);
        }
        
        $result = $this->notificationService->updatePreferences($userId, $preferences);
        
        if ($result) {
            return $this->success([
                'message' => 'Bildirim tercihleri güncellendi.'
            ]);
        }
        
        return $this->error('Tercihler güncellenirken bir hata oluştu.', 500);
    }

    public function check_auth($request) {
        return is_user_logged_in();
    }

    protected function success($data = null) {
        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }

    protected function error($message = 'Error', $code = 400) {
        return new WP_REST_Response(['status' => 'error', 'message' => $message], $code);
    }
}
