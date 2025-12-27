<?php
namespace Rejimde\Services;

/**
 * Client Management Service
 * 
 * Handles business logic for expert-client relationships (CRM)
 */
class ClientService {
    
    /**
     * Get expert's clients with filters
     * 
     * @param int $expertId Expert user ID
     * @param array $options Filters (status, search, limit, offset)
     * @return array
     */
    public function getClients(int $expertId, array $options = []): array {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        $table_events = $wpdb->prefix . 'rejimde_events';
        
        // Build query - filter out pending invites (client_id = 0)
        $query = "SELECT r.* FROM $table_relationships r WHERE r.expert_id = %d AND r.client_id > 0";
        $params = [$expertId];
        
        // Filter by status
        if (!empty($options['status'])) {
            $query .= " AND r.status = %s";
            $params[] = $options['status'];
        }
        
        // Search by client name
        if (!empty($options['search'])) {
            $search = '%' . $wpdb->esc_like($options['search']) . '%';
            $query .= " AND EXISTS (
                SELECT 1 FROM {$wpdb->users} u 
                WHERE u.ID = r.client_id 
                AND u.display_name LIKE %s
            )";
            $params[] = $search;
        }
        
        $query .= " ORDER BY r.created_at DESC";
        
        // Pagination
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $relationships = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        // Get meta counts - filter out pending invites (client_id = 0)
        $meta = [
            'total' => 0,
            'active' => 0,
            'pending' => 0,
            'archived' => 0
        ];
        
        $meta_query = "SELECT status, COUNT(*) as count FROM $table_relationships WHERE expert_id = %d AND client_id > 0 GROUP BY status";
        $meta_results = $wpdb->get_results($wpdb->prepare($meta_query, $expertId), ARRAY_A);
        
        foreach ($meta_results as $row) {
            $meta[$row['status']] = (int) $row['count'];
            $meta['total'] += (int) $row['count'];
        }
        
        // Format each relationship
        $data = [];
        foreach ($relationships as $rel) {
            $clientId = (int) $rel['client_id'];
            $relationshipId = (int) $rel['id'];
            
            // Get client data
            $client = get_userdata($clientId);
            if (!$client) continue;
            
            // Get active package
            $package = $this->getActivePackage($relationshipId);
            
            // Get last activity
            $lastActivity = $this->getLastActivityDate($clientId);
            
            // Calculate risk status
            $risk = $this->calculateRiskStatus($clientId);
            
            // Get score
            $score = (int) get_user_meta($clientId, 'rejimde_total_score', true);
            
            $data[] = [
                'id' => $relationshipId,
                'relationship_id' => $relationshipId,
                'client' => [
                    'id' => $clientId,
                    'name' => $client->display_name,
                    'avatar' => get_user_meta($clientId, 'avatar_url', true) ?: 'https://placehold.co/150',
                    'email' => $client->user_email
                ],
                'status' => $rel['status'],
                'source' => $rel['source'],
                'started_at' => $rel['started_at'],
                'package' => $package,
                'last_activity' => $lastActivity,
                'risk_status' => $risk['status'],
                'risk_reason' => $risk['reason'],
                'score' => $score,
                'created_at' => $rel['created_at']
            ];
        }
        
        return [
            'data' => $data,
            'meta' => $meta
        ];
    }
    
    /**
     * Get single client details
     * 
     * @param int $expertId Expert user ID
     * @param int $relationshipId Relationship ID
     * @return array|null
     */
    public function getClient(int $expertId, int $relationshipId): ?array {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        $relationship = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_relationships WHERE id = %d AND expert_id = %d",
            $relationshipId,
            $expertId
        ), ARRAY_A);
        
        if (!$relationship) {
            return null;
        }
        
        $clientId = (int) $relationship['client_id'];
        $client = get_userdata($clientId);
        
        if (!$client) {
            return null;
        }
        
        // Get active package
        $package = $this->getActivePackage($relationshipId);
        
        // Get stats
        $score = (int) get_user_meta($clientId, 'rejimde_total_score', true);
        $streak = (int) get_user_meta($clientId, 'rejimde_streak_current', true);
        $lastActivity = $this->getLastActivityDate($clientId);
        
        // Get notes
        $notes = $this->getNotes($relationshipId);
        
        // Get assigned plans
        $plans = $this->getAssignedPlans($relationshipId);
        
        // Get recent activity
        $activity = $this->getClientActivity($clientId, 10);
        
        return [
            'id' => $relationshipId,
            'relationship_id' => $relationshipId,
            'client' => [
                'id' => $clientId,
                'name' => $client->display_name,
                'avatar' => get_user_meta($clientId, 'avatar_url', true) ?: 'https://placehold.co/150',
                'email' => $client->user_email,
                'phone' => get_user_meta($clientId, 'phone', true) ?: '',
                'birth_date' => get_user_meta($clientId, 'birth_date', true) ?: '',
                'gender' => get_user_meta($clientId, 'gender', true) ?: ''
            ],
            'status' => $relationship['status'],
            'agreement' => $package ? [
                'start_date' => $package['start_date'],
                'end_date' => $package['end_date'],
                'package_name' => $package['name'],
                'total_sessions' => $package['total'],
                'used_sessions' => $package['used'],
                'remaining_sessions' => $package['remaining'],
                'price' => (float) $package['price']
            ] : null,
            'stats' => [
                'score' => $score,
                'streak' => $streak,
                'completed_plans' => 0, // TODO: Calculate from progress table
                'last_activity' => $lastActivity
            ],
            'notes' => $notes,
            'recent_activity' => $activity,
            'assigned_plans' => $plans
        ];
    }
    
    /**
     * Add new client manually
     * 
     * @param int $expertId Expert user ID
     * @param array $data Client data
     * @return array|int Relationship ID or error array
     */
    public function addClient(int $expertId, array $data) {
        global $wpdb;
        
        // Get or create client user
        $clientEmail = $data['client_email'];
        $client = get_user_by('email', $clientEmail);
        
        if (!$client) {
            // Create new user
            $clientId = wp_create_user(
                $clientEmail,
                wp_generate_password(),
                $clientEmail
            );
            
            if (is_wp_error($clientId)) {
                return ['error' => 'Kullanıcı oluşturulamadı: ' . $clientId->get_error_message()];
            }
            
            // Set display name
            wp_update_user([
                'ID' => $clientId,
                'display_name' => $data['client_name'] ?? $clientEmail,
                'role' => 'rejimde_user'
            ]);
        } else {
            $clientId = $client->ID;
        }
        
        // Check for existing relationship
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        $existingRelationship = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM $table_relationships WHERE expert_id = %d AND client_id = %d",
            $expertId,
            $clientId
        ));
        
        if ($existingRelationship) {
            // Relationship already exists
            if ($existingRelationship->status === 'active') {
                return ['error' => 'Bu danışan zaten listenizde aktif olarak mevcut.'];
            }
            
            if ($existingRelationship->status === 'archived' || $existingRelationship->status === 'paused') {
                // Reactivate archived or paused relationship
                $wpdb->update(
                    $table_relationships,
                    [
                        'status' => 'active',
                        'started_at' => current_time('mysql'),
                        'ended_at' => null,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $existingRelationship->id]
                );
                
                // Update package if provided
                if (!empty($data['package_name'])) {
                    $this->createPackage($existingRelationship->id, $data);
                }
                
                return ['relationship_id' => $existingRelationship->id, 'reactivated' => true];
            }
            
            if ($existingRelationship->status === 'blocked') {
                return ['error' => 'Bu danışan engellenmiş durumda. Önce engeli kaldırın.'];
            }
            
            if ($existingRelationship->status === 'pending') {
                return ['error' => 'Bu danışan için zaten bekleyen bir davet var.'];
            }
        }
        
        // Create new relationship
        $result = $wpdb->insert($table_relationships, [
            'expert_id' => $expertId,
            'client_id' => $clientId,
            'status' => 'active',
            'source' => 'manual',
            'started_at' => current_time('mysql'),
            'notes' => $data['notes'] ?? null,
            'created_at' => current_time('mysql')
        ]);
        
        if (!$result) {
            return ['error' => 'Veritabanı hatası: İlişki oluşturulamadı.'];
        }
        
        $relationshipId = $wpdb->insert_id;
        
        // Create package if provided
        if (!empty($data['package_name'])) {
            $this->createPackage($relationshipId, $data);
        }
        
        // TODO: Log activity when expert adds a new client
        
        return ['relationship_id' => $relationshipId];
    }
    
    /**
     * Create invite link
     * 
     * @param int $expertId Expert user ID
     * @param array $data Package data
     * @return array|false
     */
    public function createInvite(int $expertId, array $data) {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        
        // Store invite (with client_id = 0 for pending)
        $result = $wpdb->insert($table_relationships, [
            'expert_id' => $expertId,
            'client_id' => 0, // Pending until accepted
            'status' => 'pending',
            'source' => 'invite',
            'invite_token' => $token,
            'notes' => json_encode($data), // Store package data temporarily
            'created_at' => current_time('mysql')
        ]);
        
        if (!$result) {
            return false;
        }
        
        $expiresAt = date('Y-m-d', strtotime('+14 days'));
        
        // Use frontend URL instead of backend URL
        $frontendUrl = defined('REJIMDE_FRONTEND_URL') 
            ? REJIMDE_FRONTEND_URL 
            : 'https://rejimde.com';
        
        return [
            'invite_token' => $token,
            'invite_url' => $frontendUrl . '/invite/' . $token,
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Update relationship status
     * 
     * @param int $relationshipId Relationship ID
     * @param string $status New status
     * @param string|null $reason Reason for change
     * @return bool
     */
    public function updateStatus(int $relationshipId, string $status, ?string $reason = null): bool {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        $updateData = [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ];
        
        if ($status === 'active' && !$wpdb->get_var($wpdb->prepare(
            "SELECT started_at FROM $table_relationships WHERE id = %d",
            $relationshipId
        ))) {
            $updateData['started_at'] = current_time('mysql');
        }
        
        if ($status === 'archived') {
            $updateData['ended_at'] = current_time('mysql');
        }
        
        if ($reason) {
            $current_notes = $wpdb->get_var($wpdb->prepare(
                "SELECT notes FROM $table_relationships WHERE id = %d",
                $relationshipId
            ));
            $updateData['notes'] = $current_notes . "\n[" . current_time('mysql') . "] Status: $status - $reason";
        }
        
        $result = $wpdb->update(
            $table_relationships,
            $updateData,
            ['id' => $relationshipId]
        );
        
        return $result !== false;
    }
    
    /**
     * Update or create package
     * 
     * @param int $relationshipId Relationship ID
     * @param array $data Package data
     * @return int|false Package ID or false
     */
    public function updatePackage(int $relationshipId, array $data) {
        global $wpdb;
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        
        $action = $data['action'] ?? 'renew';
        
        if ($action === 'cancel') {
            // Cancel current active package
            return $wpdb->update(
                $table_packages,
                ['status' => 'cancelled'],
                ['relationship_id' => $relationshipId, 'status' => 'active']
            ) !== false;
        }
        
        // For renew or extend, create new package
        return $this->createPackage($relationshipId, $data);
    }
    
    /**
     * Add note to client
     * 
     * @param int $relationshipId Relationship ID
     * @param array $data Note data
     * @return int|false Note ID or false
     */
    public function addNote(int $relationshipId, array $data) {
        global $wpdb;
        $table_notes = $wpdb->prefix . 'rejimde_client_notes';
        
        // Get expert_id from relationship
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $expertId = $wpdb->get_var($wpdb->prepare(
            "SELECT expert_id FROM $table_relationships WHERE id = %d",
            $relationshipId
        ));
        
        if (!$expertId) {
            return false;
        }
        
        $result = $wpdb->insert($table_notes, [
            'relationship_id' => $relationshipId,
            'expert_id' => $expertId,
            'note_type' => $data['type'] ?? 'general',
            'content' => $data['content'],
            'is_pinned' => $data['is_pinned'] ?? 0,
            'created_at' => current_time('mysql')
        ]);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Delete note
     * 
     * @param int $noteId Note ID
     * @param int $expertId Expert ID (for permission check)
     * @return bool
     */
    public function deleteNote(int $noteId, int $expertId): bool {
        global $wpdb;
        $table_notes = $wpdb->prefix . 'rejimde_client_notes';
        
        $result = $wpdb->delete(
            $table_notes,
            ['id' => $noteId, 'expert_id' => $expertId]
        );
        
        return $result !== false;
    }
    
    /**
     * Calculate risk status based on last activity
     * 
     * @param int $clientId Client user ID
     * @return array
     */
    public function calculateRiskStatus(int $clientId): array {
        $lastActivity = $this->getLastActivityDate($clientId);
        
        if (!$lastActivity) {
            return ['status' => 'danger', 'reason' => 'Hiç aktivite yok'];
        }
        
        $lastDate = new \DateTime($lastActivity);
        $now = new \DateTime();
        $daysSince = (int) $now->diff($lastDate)->days;
        
        if ($daysSince <= 2) {
            return ['status' => 'normal', 'reason' => null];
        }
        
        if ($daysSince <= 5) {
            return ['status' => 'warning', 'reason' => "$daysSince gündür aktivite yok"];
        }
        
        return ['status' => 'danger', 'reason' => "$daysSince gündür log girmiyor"];
    }
    
    /**
     * Get client activity history
     * 
     * @param int $clientId Client user ID
     * @param int $limit Number of activities to return
     * @return array
     */
    public function getClientActivity(int $clientId, int $limit = 50): array {
        global $wpdb;
        $table_events = $wpdb->prefix . 'rejimde_events';
        
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_events WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $clientId,
            $limit
        ), ARRAY_A);
        
        foreach ($events as &$event) {
            if (!empty($event['context'])) {
                $event['context'] = json_decode($event['context'], true);
            }
        }
        
        return $events;
    }
    
    /**
     * Get assigned plans for client
     * 
     * @param int $relationshipId Relationship ID
     * @return array
     */
    public function getAssignedPlans(int $relationshipId): array {
        // TODO: Query from user_progress table or plan assignments
        // For now, return empty array
        return [];
    }
    
    /**
     * Accept invite link
     * 
     * @param string $token Invite token
     * @param int $clientId Client user ID
     * @return array
     */
    public function acceptInvite(string $token, int $clientId): array {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        // Find pending invite
        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_relationships WHERE invite_token = %s AND status = 'pending' AND client_id = 0",
            $token
        ), ARRAY_A);
        
        if (!$invite) {
            return ['error' => 'Geçersiz veya süresi dolmuş davet linki'];
        }
        
        $expertId = (int) $invite['expert_id'];
        
        // Check if relationship already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_relationships WHERE expert_id = %d AND client_id = %d AND id != %d",
            $expertId,
            $clientId,
            $invite['id']
        ));
        
        if ($existing) {
            // Delete the pending invite since relationship exists
            $wpdb->delete($table_relationships, ['id' => $invite['id']]);
            return ['error' => 'Bu uzmanla zaten bir ilişkiniz mevcut'];
        }
        
        // Update invite to active relationship
        $packageData = json_decode($invite['notes'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $packageData = [];
        }
        
        $updateResult = $wpdb->update(
            $table_relationships,
            [
                'client_id' => $clientId,
                'status' => 'active',
                'invite_token' => null,
                'started_at' => current_time('mysql'),
                'notes' => null,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $invite['id']]
        );
        
        if ($updateResult === false) {
            return ['error' => 'Davet kabul edilemedi. Lütfen tekrar deneyin.'];
        }
        
        // Create package if data exists
        if (!empty($packageData['package_name'])) {
            $this->createPackage($invite['id'], $packageData);
        }
        
        // Get expert info
        $expert = get_userdata($expertId);
        
        if (!$expert) {
            return ['error' => 'Uzman bilgisi bulunamadı'];
        }
        
        return [
            'relationship_id' => $invite['id'],
            'expert' => [
                'id' => $expertId,
                'name' => $expert->display_name,
                'avatar' => get_user_meta($expertId, 'avatar_url', true) ?: 'https://placehold.co/150'
            ]
        ];
    }
    
    /**
     * Get client's experts (for client-side endpoint)
     * 
     * @param int $clientId Client user ID
     * @return array
     */
    public function getClientExperts(int $clientId): array {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        $relationships = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_relationships WHERE client_id = %d AND status != 'blocked'",
            $clientId
        ), ARRAY_A);
        
        $data = [];
        foreach ($relationships as $rel) {
            $expertId = (int) $rel['expert_id'];
            $expert = get_userdata($expertId);
            
            if (!$expert) continue;
            
            $data[] = [
                'id' => (int) $rel['id'],
                'expert' => [
                    'id' => $expertId,
                    'name' => $expert->display_name,
                    'avatar' => get_user_meta($expertId, 'avatar_url', true) ?: 'https://placehold.co/150',
                    'profession' => get_user_meta($expertId, 'profession', true) ?: 'dietitian',
                    'title' => get_user_meta($expertId, 'title', true) ?: ''
                ],
                'status' => $rel['status'],
                'started_at' => $rel['started_at']
            ];
        }
        
        return $data;
    }
    
    /**
     * Get active package for relationship
     * 
     * @param int $relationshipId Relationship ID
     * @return array|null
     */
    private function getActivePackage(int $relationshipId): ?array {
        global $wpdb;
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        
        $package = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_packages WHERE relationship_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
            $relationshipId
        ), ARRAY_A);
        
        if (!$package) {
            return null;
        }
        
        $total = $package['total_sessions'] ? (int) $package['total_sessions'] : null;
        $used = (int) $package['used_sessions'];
        $remaining = $total ? max(0, $total - $used) : null;
        $progressPercent = $total ? round(($used / $total) * 100) : 0;
        
        return [
            'name' => $package['package_name'],
            'type' => $package['package_type'],
            'total' => $total,
            'used' => $used,
            'remaining' => $remaining,
            'progress_percent' => $progressPercent,
            'start_date' => $package['start_date'],
            'end_date' => $package['end_date'],
            'price' => $package['price']
        ];
    }
    
    /**
     * Create package
     * 
     * @param int $relationshipId Relationship ID
     * @param array $data Package data
     * @return int|false Package ID or false
     */
    private function createPackage(int $relationshipId, array $data) {
        global $wpdb;
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        
        $result = $wpdb->insert($table_packages, [
            'relationship_id' => $relationshipId,
            'package_name' => $data['package_name'],
            'package_type' => $data['package_type'] ?? 'session',
            'total_sessions' => $data['total_sessions'] ?? null,
            'used_sessions' => 0,
            'start_date' => $data['start_date'] ?? current_time('mysql', false),
            'end_date' => $data['end_date'] ?? null,
            'price' => $data['price'] ?? 0,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
            'created_at' => current_time('mysql')
        ]);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get last activity date for client
     * 
     * @param int $clientId Client user ID
     * @return string|null
     */
    private function getLastActivityDate(int $clientId): ?string {
        global $wpdb;
        $table_events = $wpdb->prefix . 'rejimde_events';
        
        $lastActivity = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM $table_events WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $clientId
        ));
        
        return $lastActivity;
    }
    
    /**
     * Get notes for relationship
     * 
     * @param int $relationshipId Relationship ID
     * @return array
     */
    private function getNotes(int $relationshipId): array {
        global $wpdb;
        $table_notes = $wpdb->prefix . 'rejimde_client_notes';
        
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_notes WHERE relationship_id = %d ORDER BY is_pinned DESC, created_at DESC",
            $relationshipId
        ), ARRAY_A);
        
        return array_map(function($note) {
            return [
                'id' => (int) $note['id'],
                'type' => $note['note_type'],
                'content' => $note['content'],
                'is_pinned' => (bool) $note['is_pinned'],
                'created_at' => $note['created_at']
            ];
        }, $notes);
    }
}
