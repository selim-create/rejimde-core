<?php
namespace Rejimde\Services;

/**
 * Announcement Service
 * 
 * Handles business logic for system announcements
 */
class AnnouncementService {
    
    /**
     * Get active announcements for user
     * 
     * @param int $userId User ID
     * @return array
     */
    public function getActiveAnnouncements(int $userId): array {
        global $wpdb;
        $table_announcements = $wpdb->prefix . 'rejimde_announcements';
        $table_dismissals = $wpdb->prefix . 'rejimde_announcement_dismissals';
        
        $user = get_userdata($userId);
        $userRoles = $user ? $user->roles : [];
        
        $now = current_time('mysql');
        
        $query = "SELECT a.* FROM $table_announcements a
                  WHERE a.start_date <= %s 
                  AND a.end_date >= %s
                  AND NOT EXISTS (
                      SELECT 1 FROM $table_dismissals d 
                      WHERE d.announcement_id = a.id 
                      AND d.user_id = %d
                      AND a.is_dismissible = 1
                  )
                  ORDER BY a.priority DESC, a.created_at DESC";
        
        $announcements = $wpdb->get_results($wpdb->prepare($query, $now, $now, $userId), ARRAY_A);
        
        // Filter by target roles
        $filtered = array_filter($announcements, function($announcement) use ($userRoles) {
            $targetRoles = $announcement['target_roles'] ? json_decode($announcement['target_roles'], true) : [];
            
            // If no target roles specified, show to everyone
            if (empty($targetRoles)) {
                return true;
            }
            
            // Check if user has any of the target roles
            return !empty(array_intersect($userRoles, $targetRoles));
        });
        
        return array_map([$this, 'formatAnnouncement'], array_values($filtered));
    }
    
    /**
     * Get announcement by ID
     * 
     * @param int $announcementId Announcement ID
     * @return array|null
     */
    public function getAnnouncement(int $announcementId): ?array {
        global $wpdb;
        $table_announcements = $wpdb->prefix . 'rejimde_announcements';
        
        $announcement = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_announcements WHERE id = %d",
            $announcementId
        ), ARRAY_A);
        
        if (!$announcement) {
            return null;
        }
        
        return $this->formatAnnouncement($announcement);
    }
    
    /**
     * Get all announcements (admin)
     * 
     * @return array
     */
    public function getAllAnnouncements(): array {
        global $wpdb;
        $table_announcements = $wpdb->prefix . 'rejimde_announcements';
        
        $announcements = $wpdb->get_results(
            "SELECT * FROM $table_announcements ORDER BY created_at DESC",
            ARRAY_A
        );
        
        return array_map([$this, 'formatAnnouncement'], $announcements);
    }
    
    /**
     * Create announcement
     * 
     * @param array $data Announcement data
     * @return int|array Announcement ID or error
     */
    public function createAnnouncement(array $data) {
        global $wpdb;
        $table_announcements = $wpdb->prefix . 'rejimde_announcements';
        
        if (empty($data['title']) || empty($data['content']) || empty($data['start_date']) || empty($data['end_date'])) {
            return ['error' => 'Title, content, start_date, and end_date are required'];
        }
        
        $insertData = [
            'title' => sanitize_text_field($data['title']),
            'content' => wp_kses_post($data['content']),
            'type' => $data['type'] ?? 'info',
            'target_roles' => isset($data['target_roles']) ? json_encode($data['target_roles']) : null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_dismissible' => isset($data['is_dismissible']) ? (bool) $data['is_dismissible'] : true,
            'priority' => isset($data['priority']) ? (int) $data['priority'] : 0,
        ];
        
        $result = $wpdb->insert($table_announcements, $insertData);
        
        if ($result === false) {
            return ['error' => 'Failed to create announcement'];
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update announcement
     * 
     * @param int $announcementId Announcement ID
     * @param array $data Update data
     * @return bool|array
     */
    public function updateAnnouncement(int $announcementId, array $data) {
        global $wpdb;
        $table_announcements = $wpdb->prefix . 'rejimde_announcements';
        
        $updateData = [];
        
        if (isset($data['title'])) {
            $updateData['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['content'])) {
            $updateData['content'] = wp_kses_post($data['content']);
        }
        if (isset($data['type'])) {
            $updateData['type'] = $data['type'];
        }
        if (isset($data['target_roles'])) {
            $updateData['target_roles'] = json_encode($data['target_roles']);
        }
        if (isset($data['start_date'])) {
            $updateData['start_date'] = $data['start_date'];
        }
        if (isset($data['end_date'])) {
            $updateData['end_date'] = $data['end_date'];
        }
        if (isset($data['is_dismissible'])) {
            $updateData['is_dismissible'] = (bool) $data['is_dismissible'];
        }
        if (isset($data['priority'])) {
            $updateData['priority'] = (int) $data['priority'];
        }
        
        if (empty($updateData)) {
            return ['error' => 'No valid fields to update'];
        }
        
        $result = $wpdb->update($table_announcements, $updateData, ['id' => $announcementId]);
        
        return $result !== false;
    }
    
    /**
     * Delete announcement
     * 
     * @param int $announcementId Announcement ID
     * @return bool
     */
    public function deleteAnnouncement(int $announcementId): bool {
        global $wpdb;
        $table_announcements = $wpdb->prefix . 'rejimde_announcements';
        $table_dismissals = $wpdb->prefix . 'rejimde_announcement_dismissals';
        
        // Delete dismissals first
        $wpdb->delete($table_dismissals, ['announcement_id' => $announcementId]);
        
        // Delete announcement
        $result = $wpdb->delete($table_announcements, ['id' => $announcementId]);
        
        return $result !== false;
    }
    
    /**
     * Dismiss announcement for user
     * 
     * @param int $announcementId Announcement ID
     * @param int $userId User ID
     * @return bool|array
     */
    public function dismissAnnouncement(int $announcementId, int $userId) {
        global $wpdb;
        $table_announcements = $wpdb->prefix . 'rejimde_announcements';
        $table_dismissals = $wpdb->prefix . 'rejimde_announcement_dismissals';
        
        // Check if announcement is dismissible
        $announcement = $wpdb->get_row($wpdb->prepare(
            "SELECT is_dismissible FROM $table_announcements WHERE id = %d",
            $announcementId
        ), ARRAY_A);
        
        if (!$announcement) {
            return ['error' => 'Announcement not found'];
        }
        
        if (!$announcement['is_dismissible']) {
            return ['error' => 'This announcement cannot be dismissed'];
        }
        
        // Check if already dismissed
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_dismissals WHERE announcement_id = %d AND user_id = %d",
            $announcementId,
            $userId
        ));
        
        if ($existing) {
            return true; // Already dismissed
        }
        
        $result = $wpdb->insert($table_dismissals, [
            'announcement_id' => $announcementId,
            'user_id' => $userId,
        ]);
        
        return $result !== false;
    }
    
    /**
     * Format announcement
     * 
     * @param array $announcement Raw announcement data
     * @return array
     */
    private function formatAnnouncement(array $announcement): array {
        return [
            'id' => (int) $announcement['id'],
            'title' => $announcement['title'],
            'content' => $announcement['content'],
            'type' => $announcement['type'],
            'target_roles' => $announcement['target_roles'] ? json_decode($announcement['target_roles'], true) : [],
            'start_date' => $announcement['start_date'],
            'end_date' => $announcement['end_date'],
            'is_dismissible' => (bool) $announcement['is_dismissible'],
            'priority' => (int) $announcement['priority'],
            'created_at' => $announcement['created_at'],
            'updated_at' => $announcement['updated_at'],
        ];
    }

    /**
     * Get announcements created by a specific expert
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getProAnnouncements(int $expertId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_announcements';
        
        $announcements = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE expert_id = %d ORDER BY created_at DESC",
            $expertId
        ), ARRAY_A);
        
        return array_map(function($row) {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'content' => $row['content'],
                'type' => $row['type'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'is_dismissible' => (bool) $row['is_dismissible'],
                'priority' => (int) $row['priority'],
                'created_at' => $row['created_at'],
            ];
        }, $announcements ?: []);
    }

    /**
     * Create announcement for pro user's clients
     * 
     * @param int $expertId Expert user ID
     * @param array $data Announcement data
     * @return int|array Announcement ID or error
     */
    public function createProAnnouncement(int $expertId, array $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_announcements';
        
        if (empty($data['title']) || empty($data['content'])) {
            return ['error' => 'Title and content are required'];
        }
        
        $result = $wpdb->insert($table, [
            'expert_id' => $expertId,
            'title' => sanitize_text_field($data['title']),
            'content' => wp_kses_post($data['content']),
            'type' => sanitize_text_field($data['type'] ?? 'info'),
            'target_roles' => json_encode(['rejimde_user']), // Pro's clients
            'start_date' => $data['start_date'] ?? current_time('mysql'),
            'end_date' => $data['end_date'] ?? date('Y-m-d H:i:s', strtotime('+30 days')),
            'is_dismissible' => $data['is_dismissible'] ?? 1,
            'priority' => $data['priority'] ?? 0,
            'created_at' => current_time('mysql'),
        ]);
        
        if ($result === false) {
            return ['error' => 'Failed to create announcement'];
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Delete pro user's own announcement
     * 
     * @param int $announcementId Announcement ID
     * @param int $expertId Expert user ID
     * @return bool
     */
    public function deleteProAnnouncement(int $announcementId, int $expertId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_announcements';
        
        // Verify ownership
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE id = %d AND expert_id = %d",
            $announcementId,
            $expertId
        ));
        
        if (!$existing) {
            return false;
        }
        
        $result = $wpdb->delete($table, ['id' => $announcementId], ['%d']);
        
        return $result !== false;
    }
}
