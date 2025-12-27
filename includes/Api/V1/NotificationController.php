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

    /** @var NotificationService */
    private $notificationService;

    private const ALLOWED_CATEGORIES = ['social', 'system', 'level', 'circle', 'points', 'expert'];

    public function __construct() {
        $this->notificationService = new NotificationService();
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_notifications'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/unread-count', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_unread_count'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/mark-read', [
            'methods'             => 'POST',
            'callback'            => [$this, 'mark_as_read'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/preferences', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_preferences'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/preferences', [
            'methods'             => 'POST',
            'callback'            => [$this, 'update_preferences'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /**
     * GET /notifications
     */
    public function get_notifications($request) {
        $userId = get_current_user_id();

        $category = $request->get_param('category');
        if (is_string($category)) {
            $category = trim($category);
            if ($category === '' || !in_array($category, self::ALLOWED_CATEGORIES, true)) {
                $category = null; // invalid -> ignore filter
            }
        } else {
            $category = null;
        }

        $isReadParam = $request->get_param('is_read');
        $isRead = null;
        if ($isReadParam !== null) {
            // Accept: 1/0, "1"/"0", true/false, "true"/"false"
            if ($isReadParam === true || $isReadParam === 'true' || $isReadParam === 1 || $isReadParam === '1') {
                $isRead = true;
            } elseif ($isReadParam === false || $isReadParam === 'false' || $isReadParam === 0 || $isReadParam === '0') {
                $isRead = false;
            }
        }

        $limit  = (int) ($request->get_param('limit') ?? 50);
        $offset = (int) ($request->get_param('offset') ?? 0);

        // clamp
        if ($limit < 1) $limit = 1;
        if ($limit > 100) $limit = 100;
        if ($offset < 0) $offset = 0;

        $options = [
            'limit'  => $limit,
            'offset' => $offset,
        ];

        if ($category !== null) {
            $options['category'] = $category;
        }

        if ($isRead !== null) {
            $options['is_read'] = $isRead;
        }

        $notifications = $this->notificationService->getNotifications($userId, $options);

        return $this->success($notifications);
    }

    /**
     * GET /notifications/unread-count
     */
    public function get_unread_count($request) {
        $userId = get_current_user_id();
        $count  = $this->notificationService->getUnreadCount($userId);

        return $this->success(['unread_count' => $count]);
    }

    /**
     * POST /notifications/mark-read
     * body: { "ids": "all" } or { "ids": [1,2,3] } or { "ids": 5 }
     */
    public function mark_as_read($request) {
        $userId = get_current_user_id();
        $params = (array) $request->get_json_params();

        $ids = $params['ids'] ?? 'all';

        // normalize ids
        if ($ids !== 'all') {
            if (is_numeric($ids)) {
                $ids = (int) $ids;
            } elseif (is_array($ids)) {
                $ids = array_values(array_filter(array_map('intval', $ids), function($v) {
                    return $v > 0;
                }));
                if (empty($ids)) {
                    return $this->error('Geçersiz ids değeri.', 400);
                }
            } else {
                return $this->error('Geçersiz ids değeri.', 400);
            }
        }

        $updated = $this->notificationService->markAsRead($userId, $ids);

        return $this->success([
            'updated_count' => $updated,
            'message'       => 'Bildirimler okundu olarak işaretlendi.'
        ]);
    }

    /**
     * GET /notifications/preferences
     */
    public function get_preferences($request) {
        $userId = get_current_user_id();

        $preferences = [];
        foreach (self::ALLOWED_CATEGORIES as $category) {
            $preferences[$category] = $this->notificationService->getUserPreferences($userId, $category);
        }

        return $this->success($preferences);
    }

    /**
     * POST /notifications/preferences
     * body: { "preferences": { "social": {...}, "system": {...} } }
     */
    public function update_preferences($request) {
        $userId = get_current_user_id();
        $params = (array) $request->get_json_params();

        $preferences = $params['preferences'] ?? [];

        if (!is_array($preferences) || empty($preferences)) {
            return $this->error('Tercihler boş olamaz.', 400);
        }

        // Whitelist categories only
        $filtered = [];
        foreach ($preferences as $category => $settings) {
            if (!in_array($category, self::ALLOWED_CATEGORIES, true)) {
                continue;
            }
            if (!is_array($settings)) {
                continue;
            }
            $filtered[$category] = $settings;
        }

        if (empty($filtered)) {
            return $this->error('Geçerli kategori tercihi bulunamadı.', 400);
        }

        $result = $this->notificationService->updatePreferences($userId, $filtered);

        if ($result) {
            return $this->success(['message' => 'Bildirim tercihleri güncellendi.']);
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
        return new WP_REST_Response(['status' => 'error', 'message' => $message], (int) $code);
    }
}
