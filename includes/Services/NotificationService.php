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
        $category = $template['category'] ?? 'system';
        $preferences = $this->getUserPreferences($userId, $category);

        if (empty($preferences['channel_in_app'])) {
            return false; // User disabled this category
        }

        // Check DND (Do Not Disturb)
        if ($this->isInDndPeriod($preferences)) {
            return false;
        }

        // Parse template
        $title = $this->parseTemplate($template['title'] ?? '', $data);
        $body  = $this->parseTemplate($template['body'] ?? '', $data);
        $actionUrl = isset($template['action_url']) ? $this->parseTemplate($template['action_url'], $data) : null;

        // Calculate expiration
        $expiresAt = null;
        if (isset($template['expires_days'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int) $template['expires_days'] . ' days'));
        }

        // Idempotency keys
        $entityType = $data['entity_type'] ?? null;
        $entityId   = isset($data['entity_id']) ? (int) $data['entity_id'] : null;

        // Prevent duplicates today
        if ($this->exists($userId, $type, $entityType, $entityId)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';

        // created_at (WP timezone) + created_date (Y-m-d) -> unique key uyumu
        $createdAt   = current_time('mysql');
        $createdDate = current_time('Y-m-d'); // WP timezone

        // Meta (LONGTEXT) - güvenli encode
        $meta = null;
        if (isset($data['meta'])) {
            $meta = wp_json_encode($data['meta'], JSON_UNESCAPED_UNICODE);
        }

        // Bazı kurulumlarda created_date kolonu henüz yoksa insert patlamasın:
        // Kolon var mı kontrol et.
        $hasCreatedDate = $this->tableHasColumn($table, 'created_date');

        $insertData = [
            'user_id'     => $userId,
            'type'        => $type,
            'category'    => $category,
            'title'       => $title,
            'body'        => $body,
            'icon'        => $template['icon'] ?? 'fa-bell',
            'action_url'  => $actionUrl,
            'actor_id'    => $data['actor_id'] ?? null,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'meta'        => $meta,
            'expires_at'  => $expiresAt,
            'created_at'  => $createdAt,
        ];

        $formats = [
            '%d', // user_id
            '%s', // type
            '%s', // category
            '%s', // title
            '%s', // body
            '%s', // icon
            '%s', // action_url
            '%d', // actor_id
            '%s', // entity_type
            '%d', // entity_id
            '%s', // meta
            '%s', // expires_at
            '%s', // created_at
        ];

        if ($hasCreatedDate) {
            // created_date DB kolonuna yaz
            $insertData['created_date'] = $createdDate;
            $formats[] = '%s';
        }

        // NULL formatlarını düzgün yönetmek için: actor_id/entity_id NULL ise format %d sorun çıkarabilir.
        // Bu yüzden insert'i WPDB'nin esnekliğine bırakalım; ama formatları yine de koruyalım.
        // (WPDB NULL’u kabul eder)
        $result = $wpdb->insert($table, $insertData, $formats);

        if ($result) {
            return (int) $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Check if notification already exists today
     */
    private function exists(int $userId, string $type, ?string $entityType, ?int $entityId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';

        $today = current_time('Y-m-d'); // WP timezone

        // created_date kolonu varsa onu kullan (index/unique ile uyumlu)
        if ($this->tableHasColumn($table, 'created_date')) {
            $query  = "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND type = %s AND created_date = %s";
            $params = [$userId, $type, $today];

            if ($entityType !== null) {
                $query .= " AND entity_type = %s";
                $params[] = $entityType;
            } else {
                // entity_type null ise aynı “null” setlerini de kapsasın
                $query .= " AND entity_type IS NULL";
            }

            if ($entityId !== null) {
                $query .= " AND entity_id = %d";
                $params[] = $entityId;
            } else {
                $query .= " AND entity_id IS NULL";
            }

            $count = (int) $wpdb->get_var($wpdb->prepare($query, ...$params));
            return $count > 0;
        }

        // Fallback: created_date yoksa eski yöntem (DATE(created_at)) - ama index yok, daha yavaş
        $query  = "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND type = %s AND DATE(created_at) = %s";
        $params = [$userId, $type, $today];

        if ($entityType !== null) {
            $query .= " AND entity_type = %s";
            $params[] = $entityType;
        } else {
            $query .= " AND entity_type IS NULL";
        }

        if ($entityId !== null) {
            $query .= " AND entity_id = %d";
            $params[] = $entityId;
        } else {
            $query .= " AND entity_id IS NULL";
        }

        $count = (int) $wpdb->get_var($wpdb->prepare($query, ...$params));
        return $count > 0;
    }

    /**
     * Get notifications for a user
     */
    public function getNotifications(int $userId, array $options = []): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';

        $query = "SELECT * FROM {$table} WHERE user_id = %d";
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
        $limit  = isset($options['limit']) ? (int) $options['limit'] : 50;
        $offset = isset($options['offset']) ? (int) $options['offset'] : 0;

        $query .= " LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $notifications = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);

        foreach ($notifications as &$notification) {
            if (!empty($notification['meta'])) {
                $decoded = json_decode($notification['meta'], true);
                $notification['meta'] = is_array($decoded) ? $decoded : null;
            }
            
            // Convert created_at to ISO 8601 format
            if (!empty($notification['created_at'])) {
                $timestamp = strtotime($notification['created_at']);
                if ($timestamp !== false) {
                    $notification['created_at'] = date('c', $timestamp); // ISO 8601
                }
            }
        }

        return $notifications;
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(int $userId): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0 AND (expires_at IS NULL OR expires_at > NOW())",
            $userId
        ));

        return (int) $count;
    }

    /**
     * Mark notifications as read
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
            return $result === false ? 0 : (int) $result;
        }

        $ids = is_array($ids) ? $ids : [$ids];
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $query = "UPDATE {$table} SET is_read = 1 WHERE user_id = %d AND id IN ({$placeholders})";
        $params = array_merge([$userId], $ids);

        $wpdb->query($wpdb->prepare($query, ...$params));
        return (int) $wpdb->rows_affected;
    }

    /**
     * Get user preferences for a category
     */
    public function getUserPreferences(int $userId, string $category): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notification_preferences';

        $prefs = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND category = %s",
            $userId, $category
        ), ARRAY_A);

        if (!$prefs) {
            return [
                'channel_in_app' => 1,
                'channel_push'   => 1,
                'channel_email'  => 0,
                'dnd_start'      => null,
                'dnd_end'        => null
            ];
        }

        return $prefs;
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(int $userId, array $preferences): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notification_preferences';

        $success = true;

        foreach ($preferences as $category => $settings) {
            $data = [
                'user_id'         => $userId,
                'category'        => (string) $category,
                'channel_in_app'  => isset($settings['channel_in_app']) ? (int) $settings['channel_in_app'] : 1,
                'channel_push'    => isset($settings['channel_push']) ? (int) $settings['channel_push'] : 1,
                'channel_email'   => isset($settings['channel_email']) ? (int) $settings['channel_email'] : 0,
                'dnd_start'       => $settings['dnd_start'] ?? null,
                'dnd_end'         => $settings['dnd_end'] ?? null,
            ];

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d AND category = %s",
                $userId, $category
            ));

            if ($exists) {
                $result = $wpdb->update($table, $data, ['user_id' => $userId, 'category' => $category]);
                if ($result === false) $success = false;
            } else {
                $result = $wpdb->insert($table, $data);
                if (!$result) $success = false;
            }
        }

        return $success;
    }

    /**
     * Check if current time is in DND period
     */
    private function isInDndPeriod(array $preferences): bool {
        if (empty($preferences['dnd_start']) || empty($preferences['dnd_end'])) {
            return false;
        }

        $now   = current_time('H:i:s');
        $start = $preferences['dnd_start'];
        $end   = $preferences['dnd_end'];

        // Overnight DND (e.g. 23:00 - 07:00)
        if ($start > $end) {
            return $now >= $start || $now <= $end;
        }

        return $now >= $start && $now <= $end;
    }

    /**
     * Parse notification template placeholders
     */
    private function parseTemplate(string $template, array $data): string {
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace('{' . $key . '}', (string) $value, $template);
            }
        }

        if (isset($data['actor_id']) && strpos($template, '{actor_name}') !== false) {
            $actor = get_userdata((int) $data['actor_id']);
            $actorName = $actor ? $actor->display_name : 'Bir kullanıcı';
            $template = str_replace('{actor_name}', $actorName, $template);
        }

        return $template;
    }

    /**
     * Clean up old expired notifications
     */
    public function cleanupOld(int $daysToKeep = 30): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_notifications';

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s OR (expires_at IS NOT NULL AND expires_at < NOW())",
            $cutoffDate
        ));

        return (int) $result;
    }

    /**
     * Check if a table has a specific column
     */
    private function tableHasColumn(string $table, string $column): bool {
        static $cache = [];

        $key = $table . '::' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        global $wpdb;

        // SHOW COLUMNS works well on MariaDB/MySQL
        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$table} LIKE %s",
            $column
        ));

        $cache[$key] = !empty($exists);
        return $cache[$key];
    }
}
