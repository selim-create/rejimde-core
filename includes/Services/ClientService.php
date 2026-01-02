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
        
        try {
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
            
            // Handle database errors
            if ($wpdb->last_error) {
                error_log('Rejimde CRM: getClients DB error occurred');
                return [
                    'data' => [],
                    'meta' => ['total' => 0, 'active' => 0, 'pending' => 0, 'archived' => 0]
                ];
            }
            
            // Ensure relationships is an array
            if (!is_array($relationships)) {
                $relationships = [];
            }
            
            // Get meta counts - filter out pending invites (client_id = 0)
            $meta = [
                'total' => 0,
                'active' => 0,
                'pending' => 0,
                'archived' => 0
            ];
            
            $meta_query = "SELECT status, COUNT(*) as count FROM $table_relationships WHERE expert_id = %d AND client_id > 0 GROUP BY status";
            $meta_results = $wpdb->get_results($wpdb->prepare($meta_query, $expertId), ARRAY_A);
            
            if (is_array($meta_results)) {
                foreach ($meta_results as $row) {
                    if (isset($row['status']) && isset($row['count'])) {
                        $meta[$row['status']] = (int) $row['count'];
                        $meta['total'] += (int) $row['count'];
                    }
                }
            }
            
            // Format each relationship
            $data = [];
            foreach ($relationships as $rel) {
                $clientId = (int) $rel['client_id'];
                $relationshipId = (int) $rel['id'];
                
                // Skip if client_id is 0 (pending invite)
                if ($clientId === 0) {
                    continue;
                }
                
                // Get client data with null check
                $client = get_userdata($clientId);
                if (!$client) {
                    error_log('Rejimde CRM: Client user data not found');
                    continue;
                }
                
                // Get active package
                $package = $this->getActivePackage($relationshipId);
                
                // Get last activity
                $lastActivity = $this->getLastActivityDate($clientId);
                
                // Calculate risk status
                $risk = $this->calculateRiskStatus($clientId);
                
                // Get score with null check
                $score = (int) get_user_meta($clientId, 'rejimde_total_score', true);
                
                $data[] = [
                    'id' => $relationshipId,
                    'relationship_id' => $relationshipId,
                    'client' => [
                        'id' => $clientId,
                        'name' => $client->display_name ?? 'Unknown',
                        'avatar' => get_user_meta($clientId, 'avatar_url', true) ?: 'https://placehold.co/150',
                        'email' => $client->user_email ?? ''
                    ],
                    'status' => $rel['status'] ?? 'active',
                    'source' => $rel['source'] ?? 'manual',
                    'started_at' => $rel['started_at'] ?? null,
                    'package' => $package,
                    'last_activity' => $lastActivity,
                    'risk_status' => $risk['status'] ?? 'normal',
                    'risk_reason' => $risk['reason'] ?? null,
                    'score' => $score,
                    'created_at' => $rel['created_at'] ?? null
                ];
            }
            
            return [
                'data' => $data,
                'meta' => $meta
            ];
            
        } catch (\Exception $e) {
            error_log('Rejimde CRM: getClients exception - ' . $e->getMessage());
            return [
                'data' => [],
                'meta' => ['total' => 0, 'active' => 0, 'pending' => 0, 'archived' => 0]
            ];
        }
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
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        
        // Relationship'i al
        $relationship = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_relationships WHERE id = %d AND expert_id = %d",
            $relationshipId,
            $expertId
        ), ARRAY_A);
        
        if (!$relationship) {
            return null;
        }
        
        // Client bilgilerini al
        $clientId = (int) $relationship['client_id'];
        $clientUser = get_userdata($clientId);
        
        if (!$clientUser) {
            return null;
        }
        
        // Aktif paketi al
        $package = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_packages 
             WHERE relationship_id = %d AND status = 'active' 
             ORDER BY created_at DESC LIMIT 1",
            $relationshipId
        ), ARRAY_A);
        
        // Paket bilgilerini formatla
        $packageData = null;
        if ($package) {
            $total = (int) ($package['total_sessions'] ?? 0);
            $used = (int) ($package['used_sessions'] ?? 0);
            $remaining = max(0, $total - $used);
            $progressPercent = $total > 0 ? round(($used / $total) * 100) : 0;
            
            $packageData = [
                'name' => $package['package_name'] ?? 'Paket',
                'type' => $package['package_type'] ?? 'session',
                'total' => $total,
                'used' => $used,
                'remaining' => $remaining,
                'progress_percent' => $progressPercent,
                'start_date' => $package['start_date'] ?? null,
                'end_date' => $package['end_date'] ?? null,
                'price' => (float) ($package['price'] ?? 0),
            ];
        }
        
        // Agreement bilgilerini formatla
        $agreementData = [
            'start_date' => $relationship['started_at'] ?? $relationship['created_at'],
            'end_date' => $packageData['end_date'] ?? null,
            'package_name' => $packageData['name'] ?? 'Paket Yok',
            'total_sessions' => $packageData['total'] ?? null,
            'used_sessions' => $packageData['used'] ?? 0,
            'remaining_sessions' => $packageData['remaining'] ?? null,
            'price' => (float) ($packageData['price'] ?? 0),
        ];
        
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
        
        // Calculate risk status
        $riskStatus = $this->calculateRiskStatus($clientId);
        
        return [
            'id' => (int) $relationship['id'],
            'relationship_id' => (int) $relationship['id'],
            'client' => [
                'id' => $clientId,
                'name' => $clientUser->display_name,
                'avatar' => get_user_meta($clientId, 'avatar_url', true) ?: $this->getDefaultAvatar($clientId),
                'email' => $clientUser->user_email,
                'phone' => get_user_meta($clientId, 'phone', true) ?: null,
                'birth_date' => get_user_meta($clientId, 'birth_date', true) ?: null,
                'gender' => get_user_meta($clientId, 'gender', true) ?: null,
            ],
            'status' => $relationship['status'],
            'source' => $relationship['source'],
            'started_at' => $relationship['started_at'],
            'package' => $packageData,
            'agreement' => $agreementData,
            'stats' => [
                'score' => $score,
                'streak' => $streak,
                'completed_plans' => 0, // TODO: Calculate from plans
                'last_activity' => $lastActivity
            ],
            'notes' => $notes,
            'recent_activity' => $activity,
            'assigned_plans' => $plans,
            'risk_status' => $riskStatus['status'] ?? 'normal',
            'risk_reason' => $riskStatus['reason'] ?? null,
            'created_at' => $relationship['created_at'],
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
        
        // Check for existing pending invite (get most recent one if multiple exist)
        $existingInvite = $wpdb->get_row($wpdb->prepare(
            "SELECT id, invite_token FROM $table_relationships 
             WHERE expert_id = %d AND client_id = 0 AND status = 'pending' AND source = 'invite'
             ORDER BY created_at DESC LIMIT 1",
            $expertId
        ));
        
        try {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            // Fallback token generation with high entropy
            $token = hash('sha256', wp_generate_password(64, true, true) . time() . wp_rand());
        }
        
        // Validate and set defaults
        if (empty($data['package_name'])) {
            $data['package_name'] = 'Genel Paket';
        }
        if (empty($data['package_type'])) {
            $data['package_type'] = 'session';
        }
        if (!isset($data['price'])) {
            $data['price'] = 0;
        }
        
        if ($existingInvite) {
            // Update existing invite with new token and data (preserve original created_at)
            $result = $wpdb->update(
                $table_relationships,
                [
                    'invite_token' => $token,
                    'notes' => wp_json_encode($data),
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $existingInvite->id]
            );
            
            if ($result === false) {
                error_log('Rejimde CRM: Invite update failed - ' . $wpdb->last_error);
                return ['error' => 'Davet güncellenemedi: ' . $wpdb->last_error];
            }
        } else {
            // Create new invite (with client_id = 0 for pending)
            $insertData = [
                'expert_id' => $expertId,
                'client_id' => 0, // Pending until accepted
                'status' => 'pending',
                'source' => 'invite',
                'invite_token' => $token,
                'notes' => wp_json_encode($data), // Use wp_json_encode for safety
                'created_at' => current_time('mysql')
            ];
            
            $result = $wpdb->insert($table_relationships, $insertData);
            
            if (!$result) {
                error_log('Rejimde CRM: Invite creation failed - ' . $wpdb->last_error);
                return ['error' => 'Davet oluşturulamadı: ' . $wpdb->last_error];
            }
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
        
        error_log("Rejimde CRM: updatePackage called with action: $action for relationship: $relationshipId");
        
        if ($action === 'cancel') {
            // Cancel current active package
            $result = $wpdb->update(
                $table_packages,
                ['status' => 'cancelled', 'updated_at' => current_time('mysql')],
                ['relationship_id' => $relationshipId, 'status' => 'active'],
                ['%s', '%s'],
                ['%d', '%s']
            );
            
            if ($result === false) {
                error_log("Rejimde CRM: Failed to cancel package for relationship: $relationshipId");
            }
            
            return $result !== false;
        }
        
        if ($action === 'extend') {
            // Extend existing package by adding sessions
            $packageData = $data['data'] ?? [];
            
            if (empty($packageData['sessions_to_add'])) {
                error_log("Rejimde CRM: extend action requires 'sessions_to_add' in data");
                return ['error' => 'sessions_to_add is required for extend action'];
            }
            
            $sessionsToAdd = (int) $packageData['sessions_to_add'];
            
            // Get active package
            $package = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_packages WHERE relationship_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
                $relationshipId
            ), ARRAY_A);
            
            if (!$package) {
                error_log("Rejimde CRM: No active package found for relationship: $relationshipId");
                return ['error' => 'No active package found to extend'];
            }
            
            // Check if package is unlimited (null total_sessions)
            // Use is_null() for reliable null checking across database drivers
            if (is_null($package['total_sessions']) || $package['total_sessions'] === null) {
                error_log("Rejimde CRM: Cannot extend unlimited package for relationship: $relationshipId");
                return ['error' => 'Cannot extend unlimited package. Please create a new package instead.'];
            }
            
            $currentTotal = (int) $package['total_sessions'];
            $newTotal = $currentTotal + $sessionsToAdd;
            
            // Update total sessions
            $result = $wpdb->update(
                $table_packages,
                [
                    'total_sessions' => $newTotal,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $package['id']],
                ['%d', '%s'],
                ['%d']
            );
            
            if ($result === false) {
                error_log("Rejimde CRM: Failed to extend package ID: {$package['id']}");
                return ['error' => 'Failed to extend package'];
            }
            
            error_log("Rejimde CRM: Successfully extended package ID: {$package['id']} from $currentTotal to $newTotal sessions");
            
            return (int) $package['id'];
        }
        
        // For renew action, create new package
        if ($action === 'renew') {
            // First, mark existing package as completed
            $wpdb->update(
                $table_packages,
                ['status' => 'completed', 'updated_at' => current_time('mysql')],
                ['relationship_id' => $relationshipId, 'status' => 'active'],
                ['%s', '%s'],
                ['%d', '%s']
            );
            
            error_log("Rejimde CRM: Creating new package for renew action");
        }
        
        // Extract package data from 'data' field if it exists
        $packageData = $data['data'] ?? $data;
        
        return $this->createPackage($relationshipId, $packageData);
    }
    
    /**
     * Add note to client
     * 
     * @param int $relationshipId Relationship ID
     * @param array $data Note data
     * @return array Note object or error array
     */
    public function addNote(int $relationshipId, array $data) {
        global $wpdb;
        $table_notes = $wpdb->prefix . 'rejimde_client_notes';
        
        // Validate required content field
        if (empty($data['content'])) {
            return ['error' => 'Content is required'];
        }
        
        // Get expert_id from relationship
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $expertId = $wpdb->get_var($wpdb->prepare(
            "SELECT expert_id FROM $table_relationships WHERE id = %d",
            $relationshipId
        ));
        
        if (!$expertId) {
            return ['error' => 'Relationship not found'];
        }
        
        $noteType = $data['type'] ?? 'general';
        $content = $data['content'];
        $isPinned = $data['is_pinned'] ?? 0;
        $createdAt = current_time('mysql');
        
        $result = $wpdb->insert($table_notes, [
            'relationship_id' => $relationshipId,
            'expert_id' => $expertId,
            'note_type' => $noteType,
            'content' => $content,
            'is_pinned' => $isPinned,
            'created_at' => $createdAt
        ]);
        
        if (!$result) {
            return ['error' => 'Failed to insert note'];
        }
        
        $noteId = $wpdb->insert_id;
        
        // Return full note object
        return [
            'id' => (int) $noteId,
            'type' => $noteType,
            'content' => $content,
            'is_pinned' => (bool) $isPinned,
            'created_at' => $createdAt
        ];
    }
    
    /**
     * Use session(s) from client package
     * 
     * @param int $relationshipId Relationship ID
     * @param int $count Number of sessions to use
     * @param string|null $reason Reason for using session
     * @return array Updated package data or error array
     */
    public function useSession(int $relationshipId, int $count = 1, ?string $reason = null) {
        global $wpdb;
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        $table_events = $wpdb->prefix . 'rejimde_events';
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        // Get active package
        $package = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_packages WHERE relationship_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
            $relationshipId
        ), ARRAY_A);
        
        if (!$package) {
            return ['error' => 'No active package found'];
        }
        
        // Get client_id for activity log
        $clientId = $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM $table_relationships WHERE id = %d",
            $relationshipId
        ));
        
        if (!$clientId) {
            return ['error' => 'Relationship not found'];
        }
        
        $packageId = (int) $package['id'];
        $currentUsed = (int) $package['used_sessions'];
        $totalSessions = $package['total_sessions'] !== null ? (int) $package['total_sessions'] : null;
        $newUsed = $currentUsed + $count;
        
        // Check if we have enough sessions
        if ($totalSessions !== null && $newUsed > $totalSessions) {
            return ['error' => 'Not enough sessions remaining'];
        }
        
        // Update used_sessions
        $updateData = [
            'used_sessions' => $newUsed,
            'updated_at' => current_time('mysql')
        ];
        
        // Check if package should be completed
        if ($totalSessions !== null && $newUsed >= $totalSessions) {
            $updateData['status'] = 'completed';
        }
        
        $result = $wpdb->update(
            $table_packages,
            $updateData,
            ['id' => $packageId]
        );
        
        if ($result === false) {
            return ['error' => 'Failed to update package'];
        }
        
        // Log activity (sessions don't award points, just track usage)
        $wpdb->insert($table_events, [
            'user_id' => $clientId,
            'event_type' => 'session_used',
            'entity_type' => 'package',
            'entity_id' => $packageId,
            'points' => 0,
            'context' => wp_json_encode([
                'count' => $count,
                'reason' => $reason,
                'used_sessions' => $newUsed,
                'total_sessions' => $totalSessions
            ]),
            'created_at' => current_time('mysql')
        ]);
        
        // Return updated package info
        $remaining = $totalSessions !== null ? max(0, $totalSessions - $newUsed) : null;
        $progressPercent = ($totalSessions !== null && $totalSessions > 0) ? round(($newUsed / $totalSessions) * 100) : 0;
        
        return [
            'id' => $packageId,
            'name' => $package['package_name'],
            'type' => $package['package_type'],
            'total' => $totalSessions,
            'used' => $newUsed,
            'remaining' => $remaining,
            'progress_percent' => $progressPercent,
            'status' => $updateData['status'] ?? $package['status'],
            'start_date' => $package['start_date'],
            'end_date' => $package['end_date'],
            'price' => $package['price']
        ];
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
        
        $total = $package['total_sessions'] !== null ? (int) $package['total_sessions'] : null;
        $used = (int) $package['used_sessions'];
        $remaining = $total !== null ? max(0, $total - $used) : null;
        $progressPercent = ($total !== null && $total > 0) ? round(($used / $total) * 100) : 0;
        
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
    
    /**
     * Get default avatar for user
     * 
     * @param int $userId User ID
     * @return string
     */
    private function getDefaultAvatar(int $userId): string {
        return defined('REJIMDE_DEFAULT_AVATAR') 
            ? REJIMDE_DEFAULT_AVATAR 
            : 'https://placehold.co/150';
    }
    
    /**
     * Update package end date
     * 
     * @param int $relationshipId Relationship ID
     * @param string $endDate New end date (YYYY-MM-DD format)
     * @return bool
     */
    public function updatePackageEndDate(int $relationshipId, string $endDate): bool {
        global $wpdb;
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        
        // Validate date format
        $dateObj = \DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $endDate) {
            error_log('Rejimde: Invalid date format for package end_date - ' . $endDate);
            return false;
        }
        
        $result = $wpdb->update(
            $table_packages,
            ['end_date' => $endDate, 'updated_at' => current_time('mysql')],
            ['relationship_id' => $relationshipId, 'status' => 'active']
        );
        
        return $result !== false;
    }
}
