<?php
namespace Rejimde\Services;

/**
 * Notification Service
 * 
 * Handles notification creation, retrieval, and management
 */
class NotificationService {
    
    private $templates;
    
    public function __construct() {
        $this->templates = require __DIR__ . '/../Config/NotificationTypes.php';
    }
    
    /**
     * Create a notification
     * 
     * @param int $userId Recipient user ID
     * @param string $type Notification type
     * @param array $data Template data (actor_id, entity_id, points, etc.)
     * @return int|false Notification ID or false on failure
     */
    public function create(int $userId, string $type, array $data = []) {
        // Check if notification type exists
        if (!isset($this->templates[$type])) {
            return false;
        }
        
        $template = $this->templates[$type];
        
        // Check user preferences
        $category = $template['category'];
        $preferences = $this->getUserPreferences($userId, $category);
        
        if (!$preferences['channel_in_app']) {
            return false; // User disabled this category
        }
        
        // Check DND (Do Not Disturb)
        if ($this->isInDndPeriod($preferences)) {
            return false;
        }
        
        // Parse template
        $title = $this->parseTemplate($template['title'], $data);
        $body = $this->parseTemplate($template['body'], $data);
        $actionUrl = isset($template['action_url']) ? $this->parseTemplate($template['action_url'], $data) : null;
        
        // Calculate expiration
        $expiresAt = null;
        if (isset($template['expires_days'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $template['expires_days'] . ' days'));
        }
        
        // Check idempotency (prevent duplicate notifications today)
        $entityType = $data['entity_type'] ?? null;
        $entityId = $data['entity_id'] ?? null;
        
        if ($this->exists($userId, $type, $entityType, $entityId)) {
            return false; // Already created today
        }
        
        // Create notification
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';
        
        $result = $wpdb->insert($table, [
            'user_id' => $userId,
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'icon' => $template['icon'] ?? 'fa-bell',
            'action_url' => $actionUrl,
            'actor_id' => $data['actor_id'] ?? null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'meta' => isset($data['meta']) ? json_encode($data['meta']) : null,
            'expires_at' => $expiresAt,
            'created_at' => current_time('mysql')
        ]);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Check if notification already exists today
     * 
     * @param int $userId User ID
     * @param string $type Notification type
     * @param string|null $entityType Entity type
     * @param int|null $entityId Entity ID
     * @return bool
     */
    private function exists(int $userId, string $type, ?string $entityType, ?int $entityId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';
        $today = date('Y-m-d');
        
        $query = "SELECT COUNT(*) FROM $table WHERE user_id = %d AND type = %s AND DATE(created_at) = %s";
        $params = [$userId, $type, $today];
        
        if ($entityType !== null) {
            $query .= " AND entity_type = %s";
            $params[] = $entityType;
        }
        
        if ($entityId !== null) {
            $query .= " AND entity_id = %d";
            $params[] = $entityId;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($query, ...$params));
        
        return $count > 0;
    }
    
    /**
     * Get notifications for a user
     * 
     * @param int $userId User ID
     * @param array $options Filters (category, is_read, limit, offset)
     * @return array
     */
    public function getNotifications(int $userId, array $options = []): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';
        
        $query = "SELECT * FROM $table WHERE user_id = %d";
        $params = [$userId];
        
        // Filter by category
        if (isset($options['category'])) {
            $query .= " AND category = %s";
            $params[] = $options['category'];
        }
        
        // Filter by read status
        if (isset($options['is_read'])) {
            $query .= " AND is_read = %d";
            $params[] = $options['is_read'] ? 1 : 0;
        }
        
        // Filter out expired notifications
        $query .= " AND (expires_at IS NULL OR expires_at > NOW())";
        
        // Order by created_at DESC
        $query .= " ORDER BY created_at DESC";
        
        // Pagination
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $notifications = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        // Decode meta JSON
        foreach ($notifications as &$notification) {
            if (!empty($notification['meta'])) {
                $notification['meta'] = json_decode($notification['meta'], true);
            }
        }
        
        return $notifications;
    }
    
    /**
     * Get unread notification count
     * 
     * @param int $userId User ID
     * @return int
     */
    public function getUnreadCount(int $userId): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND is_read = 0 AND (expires_at IS NULL OR expires_at > NOW())",
            $userId
        ));
        
        return (int) $count;
    }
    
    /**
     * Mark notifications as read
     * 
     * @param int $userId User ID
     * @param mixed $ids Single ID, array of IDs, or 'all'
     * @return int Number of notifications updated
     */
    public function markAsRead(int $userId, $ids): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';
        
        if ($ids === 'all') {
            $result = $wpdb->update(
                $table,
                ['is_read' => 1],
                ['user_id' => $userId, 'is_read' => 0],
                ['%d'],
                ['%d', '%d']
            );
        } else {
            $ids = is_array($ids) ? $ids : [$ids];
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            
            $query = "UPDATE $table SET is_read = 1 WHERE user_id = %d AND id IN ($placeholders)";
            $params = array_merge([$userId], $ids);
            
            $wpdb->query($wpdb->prepare($query, ...$params));
            $result = $wpdb->rows_affected;
        }
        
        return $result;
    }
    
    /**
     * Get user preferences for a category
     * 
     * @param int $userId User ID
     * @param string $category Category
     * @return array
     */
    public function getUserPreferences(int $userId, string $category): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notification_preferences';
        
        $prefs = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND category = %s",
            $userId, $category
        ), ARRAY_A);
        
        // Return defaults if not set
        if (!$prefs) {
            return [
                'channel_in_app' => 1,
                'channel_push' => 1,
                'channel_email' => 0,
                'dnd_start' => null,
                'dnd_end' => null
            ];
        }
        
        return $prefs;
    }
    
    /**
     * Update user preferences
     * 
     * @param int $userId User ID
     * @param array $preferences Array of category => settings
     * @return bool
     */
    public function updatePreferences(int $userId, array $preferences): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notification_preferences';
        
        foreach ($preferences as $category => $settings) {
            $data = [
                'user_id' => $userId,
                'category' => $category,
                'channel_in_app' => $settings['channel_in_app'] ?? 1,
                'channel_push' => $settings['channel_push'] ?? 1,
                'channel_email' => $settings['channel_email'] ?? 0,
                'dnd_start' => $settings['dnd_start'] ?? null,
                'dnd_end' => $settings['dnd_end'] ?? null
            ];
            
            // Check if preference exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE user_id = %d AND category = %s",
                $userId, $category
            ));
            
            if ($exists) {
                $wpdb->update(
                    $table,
                    $data,
                    ['user_id' => $userId, 'category' => $category]
                );
            } else {
                $wpdb->insert($table, $data);
            }
        }
        
        return true;
    }
    
    /**
     * Check if current time is in DND period
     * 
     * @param array $preferences User preferences
     * @return bool
     */
    private function isInDndPeriod(array $preferences): bool {
        if (empty($preferences['dnd_start']) || empty($preferences['dnd_end'])) {
            return false;
        }
        
        $now = current_time('H:i:s');
        $start = $preferences['dnd_start'];
        $end = $preferences['dnd_end'];
        
        // Handle overnight DND period
        if ($start > $end) {
            return $now >= $start || $now <= $end;
        }
        
        return $now >= $start && $now <= $end;
    }
    
    /**
     * Parse notification template
     * 
     * @param string $template Template string
     * @param array $data Template data
     * @return string
     */
    private function parseTemplate(string $template, array $data): string {
        // Replace placeholders like {actor_name}, {points}, etc.
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace('{' . $key . '}', $value, $template);
            }
        }
        
        // Handle actor_name lookup
        if (isset($data['actor_id']) && strpos($template, '{actor_name}') !== false) {
            $actor = get_userdata($data['actor_id']);
            $actorName = $actor ? $actor->display_name : 'Bir kullanıcı';
            $template = str_replace('{actor_name}', $actorName, $template);
        }
        
        return $template;
    }
    
    /**
     * Clean up old expired notifications
     * 
     * @param int $daysToKeep Days to keep notifications
     * @return int Number of deleted notifications
     */
    public function cleanupOld(int $daysToKeep = 30): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s OR (expires_at IS NOT NULL AND expires_at < NOW())",
            $cutoffDate
        ));
        
        return $result;
    }
}
