<?php
namespace Rejimde\Services;

/**
 * Media Library Service
 * 
 * Handles business logic for expert's media library (external links)
 */
class MediaLibraryService {
    
    /**
     * Get media items with filters
     * 
     * @param int $expertId Expert user ID
     * @param array $filters Filters (type, search, folder, limit, offset)
     * @return array
     */
    public function getMediaItems(int $expertId, array $filters = []): array {
        global $wpdb;
        $table_media = $wpdb->prefix . 'rejimde_media_library';
        
        error_log("Rejimde Media: getMediaItems called for expert $expertId");
        
        $query = "SELECT * FROM $table_media WHERE expert_id = %d";
        $params = [$expertId];
        
        if (!empty($filters['type'])) {
            $query .= " AND type = %s";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['folder'])) {
            $query .= " AND folder_id = %d";
            $params[] = (int) $filters['folder'];
        }
        
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $query .= " AND (title LIKE %s OR description LIKE %s)";
            $params[] = $search;
            $params[] = $search;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $items = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log("Rejimde Media: Database error - " . $wpdb->last_error);
        }
        
        $count = is_array($items) ? count($items) : 0;
        error_log("Rejimde Media: Found $count media items for expert $expertId");
        
        return array_map(function($item) {
            return [
                'id' => (int) $item['id'],
                'title' => $item['title'],
                'description' => $item['description'],
                'type' => $item['type'],
                'url' => $item['url'],
                'thumbnail_url' => $item['thumbnail_url'],
                'folder_id' => $item['folder_id'] ? (int) $item['folder_id'] : null,
                'tags' => $item['tags'] ? json_decode($item['tags'], true) : [],
                'usage_count' => (int) $item['usage_count'],
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ];
        }, $items);
    }
    
    /**
     * Get single media item
     * 
     * @param int $mediaId Media ID
     * @param int $expertId Expert user ID
     * @return array|null
     */
    public function getMediaItem(int $mediaId, int $expertId): ?array {
        global $wpdb;
        $table_media = $wpdb->prefix . 'rejimde_media_library';
        
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_media WHERE id = %d AND expert_id = %d",
            $mediaId,
            $expertId
        ), ARRAY_A);
        
        if (!$item) {
            return null;
        }
        
        return [
            'id' => (int) $item['id'],
            'title' => $item['title'],
            'description' => $item['description'],
            'type' => $item['type'],
            'url' => $item['url'],
            'thumbnail_url' => $item['thumbnail_url'],
            'folder_id' => $item['folder_id'] ? (int) $item['folder_id'] : null,
            'tags' => $item['tags'] ? json_decode($item['tags'], true) : [],
            'usage_count' => (int) $item['usage_count'],
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'],
        ];
    }
    
    /**
     * Add media item
     * 
     * @param int $expertId Expert user ID
     * @param array $data Media data
     * @return int|array Media ID or error
     */
    public function addMedia(int $expertId, array $data) {
        global $wpdb;
        $table_media = $wpdb->prefix . 'rejimde_media_library';
        
        if (empty($data['title']) || empty($data['type']) || empty($data['url'])) {
            error_log("Rejimde Media: addMedia failed - title, type, or URL missing");
            return ['error' => 'Title, type, and URL are required'];
        }
        
        error_log("Rejimde Media: Adding media for expert $expertId - title: {$data['title']}, type: {$data['type']}");
        
        $insertData = [
            'expert_id' => $expertId,
            'title' => sanitize_text_field($data['title']),
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
            'type' => $data['type'],
            'url' => esc_url_raw($data['url']),
            'thumbnail_url' => isset($data['thumbnail_url']) ? esc_url_raw($data['thumbnail_url']) : null,
            'folder_id' => isset($data['folder_id']) ? (int) $data['folder_id'] : null,
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
            'usage_count' => 0,
            'created_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert($table_media, $insertData);
        
        if ($result === false) {
            error_log("Rejimde Media: Database insert failed - " . $wpdb->last_error);
            return ['error' => 'Failed to add media'];
        }
        
        $mediaId = $wpdb->insert_id;
        error_log("Rejimde Media: Successfully created media ID $mediaId for expert $expertId");
        
        return $mediaId;
    }
    
    /**
     * Update media item
     * 
     * @param int $mediaId Media ID
     * @param array $data Update data
     * @return bool|array
     */
    public function updateMedia(int $mediaId, array $data) {
        global $wpdb;
        $table_media = $wpdb->prefix . 'rejimde_media_library';
        
        $updateData = [];
        
        if (isset($data['title'])) {
            $updateData['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['description'])) {
            $updateData['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['url'])) {
            $updateData['url'] = esc_url_raw($data['url']);
        }
        if (isset($data['thumbnail_url'])) {
            $updateData['thumbnail_url'] = esc_url_raw($data['thumbnail_url']);
        }
        if (isset($data['folder_id'])) {
            $updateData['folder_id'] = (int) $data['folder_id'];
        }
        if (isset($data['tags'])) {
            $updateData['tags'] = json_encode($data['tags']);
        }
        
        if (empty($updateData)) {
            return ['error' => 'No valid fields to update'];
        }
        
        $result = $wpdb->update($table_media, $updateData, ['id' => $mediaId]);
        
        return $result !== false;
    }
    
    /**
     * Delete media item
     * 
     * @param int $mediaId Media ID
     * @param int $expertId Expert user ID
     * @return bool
     */
    public function deleteMedia(int $mediaId, int $expertId): bool {
        global $wpdb;
        $table_media = $wpdb->prefix . 'rejimde_media_library';
        
        $result = $wpdb->delete($table_media, [
            'id' => $mediaId,
            'expert_id' => $expertId
        ]);
        
        return $result !== false;
    }
    
    /**
     * Get folders
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getFolders(int $expertId): array {
        global $wpdb;
        $table_folders = $wpdb->prefix . 'rejimde_media_folders';
        
        $folders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_folders WHERE expert_id = %d ORDER BY sort_order ASC, name ASC",
            $expertId
        ), ARRAY_A);
        
        return array_map(function($folder) {
            return [
                'id' => (int) $folder['id'],
                'name' => $folder['name'],
                'parent_id' => $folder['parent_id'] ? (int) $folder['parent_id'] : null,
                'sort_order' => (int) $folder['sort_order'],
                'created_at' => $folder['created_at'],
            ];
        }, $folders);
    }
    
    /**
     * Create folder
     * 
     * @param int $expertId Expert user ID
     * @param array $data Folder data
     * @return int|array Folder ID or error
     */
    public function createFolder(int $expertId, array $data) {
        global $wpdb;
        $table_folders = $wpdb->prefix . 'rejimde_media_folders';
        
        if (empty($data['name'])) {
            return ['error' => 'Folder name is required'];
        }
        
        $insertData = [
            'expert_id' => $expertId,
            'name' => sanitize_text_field($data['name']),
            'parent_id' => isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
        ];
        
        $result = $wpdb->insert($table_folders, $insertData);
        
        if ($result === false) {
            return ['error' => 'Failed to create folder'];
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Delete folder
     * 
     * @param int $folderId Folder ID
     * @param int $expertId Expert user ID
     * @return bool|array
     */
    public function deleteFolder(int $folderId, int $expertId) {
        global $wpdb;
        $table_folders = $wpdb->prefix . 'rejimde_media_folders';
        $table_media = $wpdb->prefix . 'rejimde_media_library';
        
        // Check if folder has items
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_media WHERE folder_id = %d AND expert_id = %d",
            $folderId,
            $expertId
        ));
        
        if ($count > 0) {
            return ['error' => 'Folder contains media items. Please move or delete them first.'];
        }
        
        $result = $wpdb->delete($table_folders, [
            'id' => $folderId,
            'expert_id' => $expertId
        ]);
        
        return $result !== false;
    }
    
    /**
     * Increment usage count
     * 
     * @param int $mediaId Media ID
     * @return bool
     */
    public function incrementUsage(int $mediaId): bool {
        global $wpdb;
        $table_media = $wpdb->prefix . 'rejimde_media_library';
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE $table_media SET usage_count = usage_count + 1 WHERE id = %d",
            $mediaId
        )) !== false;
    }
}
