<?php
namespace Rejimde\Services;

/**
 * Service Request Management Service
 * 
 * Handles business logic for service/package requests from users to experts
 */
class ServiceRequestService {
    
    // Default avatar URL constant
    private const DEFAULT_AVATAR_URL = 'https://placehold.co/150';
    
    // Response messages
    private const MSG_REQUEST_SENT = 'Talebiniz uzmana iletildi.';
    private const MSG_CLIENT_ADDED = 'Danışan eklendi';
    private const MSG_PACKAGE_ASSIGNED = ' ve paket atandı.';
    private const MSG_REQUEST_REJECTED = 'Talep reddedildi';
    
    /**
     * Create a new service request
     * 
     * @param int $userId User requesting the service
     * @param array $data Request data
     * @return array
     */
    public function createRequest(int $userId, array $data): array {
        global $wpdb;
        $table_requests = $wpdb->prefix . 'rejimde_service_requests';
        
        // Validate required fields
        if (empty($data['expert_id'])) {
            return ['error' => 'Expert ID gerekli'];
        }
        
        $expertId = (int) $data['expert_id'];
        
        // Check if expert exists and has rejimde_pro role
        $expert = get_userdata($expertId);
        if (!$expert || !in_array('rejimde_pro', (array) $expert->roles)) {
            return ['error' => 'Geçersiz uzman'];
        }
        
        // Check if user already has an active relationship with this expert
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_relationships 
            WHERE expert_id = %d AND client_id = %d AND status = 'active'",
            $expertId,
            $userId
        ));
        
        if ($existing) {
            return ['error' => 'Bu uzmanla zaten aktif bir ilişkiniz mevcut'];
        }
        
        // Check for pending request
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_requests 
            WHERE expert_id = %d AND user_id = %d AND status = 'pending'",
            $expertId,
            $userId
        ));
        
        if ($pending) {
            return ['error' => 'Bu uzmana zaten bekleyen bir talebiniz var'];
        }
        
        $serviceId = !empty($data['service_id']) ? (int) $data['service_id'] : null;
        $message = !empty($data['message']) ? sanitize_textarea_field($data['message']) : null;
        $contactPreference = !empty($data['contact_preference']) ? sanitize_text_field($data['contact_preference']) : 'message';
        
        // Insert request
        $result = $wpdb->insert($table_requests, [
            'expert_id' => $expertId,
            'user_id' => $userId,
            'service_id' => $serviceId,
            'message' => $message,
            'contact_preference' => $contactPreference,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ]);
        
        if (!$result) {
            return ['error' => 'Talep oluşturulamadı: ' . $wpdb->last_error];
        }
        
        $requestId = $wpdb->insert_id;
        
        // TODO: Send notification to expert
        
        return [
            'request_id' => $requestId,
            'status' => 'pending',
            'message' => self::MSG_REQUEST_SENT
        ];
    }
    
    /**
     * Get service requests for an expert
     * 
     * @param int $expertId Expert user ID
     * @param array $filters Optional filters (status, limit, offset)
     * @return array
     */
    public function getExpertRequests(int $expertId, array $filters = []): array {
        global $wpdb;
        $table_requests = $wpdb->prefix . 'rejimde_service_requests';
        
        $query = "SELECT * FROM $table_requests WHERE expert_id = %d";
        $params = [$expertId];
        
        // Filter by status
        if (!empty($filters['status'])) {
            $query .= " AND status = %s";
            $params[] = $filters['status'];
        }
        
        $query .= " ORDER BY created_at DESC";
        
        // Pagination
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $requests = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        // Get meta counts
        $meta_query = "SELECT status, COUNT(*) as count FROM $table_requests WHERE expert_id = %d GROUP BY status";
        $meta_results = $wpdb->get_results($wpdb->prepare($meta_query, $expertId), ARRAY_A);
        
        $meta = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'total' => 0
        ];
        
        foreach ($meta_results as $row) {
            $meta[$row['status']] = (int) $row['count'];
            $meta['total'] += (int) $row['count'];
        }
        
        // Format requests
        $data = [];
        foreach ($requests as $request) {
            $userId = (int) $request['user_id'];
            $user = get_userdata($userId);
            
            if (!$user) {
                continue;
            }
            
            $serviceData = null;
            if (!empty($request['service_id'])) {
                $serviceData = $this->getServiceData((int) $request['service_id']);
            }
            
            $data[] = [
                'id' => (int) $request['id'],
                'user' => [
                    'id' => $userId,
                    'name' => $user->display_name,
                    'avatar' => get_user_meta($userId, 'avatar_url', true) ?: self::DEFAULT_AVATAR_URL,
                    'email' => $user->user_email
                ],
                'service' => $serviceData,
                'message' => $request['message'],
                'contact_preference' => $request['contact_preference'],
                'status' => $request['status'],
                'expert_response' => $request['expert_response'],
                'created_at' => $request['created_at'],
                'updated_at' => $request['updated_at']
            ];
        }
        
        return [
            'data' => $data,
            'meta' => $meta
        ];
    }
    
    /**
     * Respond to a service request (approve or reject)
     * 
     * @param int $requestId Request ID
     * @param int $expertId Expert user ID
     * @param string $action 'approve' or 'reject'
     * @param array $data Additional data (response_message, assign_package)
     * @return array
     */
    public function respondToRequest(int $requestId, int $expertId, string $action, array $data = []): array {
        global $wpdb;
        $table_requests = $wpdb->prefix . 'rejimde_service_requests';
        
        // Get request
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_requests WHERE id = %d AND expert_id = %d",
            $requestId,
            $expertId
        ), ARRAY_A);
        
        if (!$request) {
            return ['error' => 'Talep bulunamadı'];
        }
        
        if ($request['status'] !== 'pending') {
            return ['error' => 'Bu talep zaten yanıtlanmış'];
        }
        
        $userId = (int) $request['user_id'];
        $responseMessage = !empty($data['response_message']) ? sanitize_textarea_field($data['response_message']) : null;
        
        if ($action === 'reject') {
            // Reject request
            $wpdb->update(
                $table_requests,
                [
                    'status' => 'rejected',
                    'expert_response' => $responseMessage,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $requestId]
            );
            
            // TODO: Send notification to user
            
            return [
                'status' => 'rejected',
                'message' => self::MSG_REQUEST_REJECTED
            ];
        }
        
        if ($action === 'approve') {
            // Check if relationship already exists
            $table_relationships = $wpdb->prefix . 'rejimde_relationships';
            $existingRelationship = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_relationships WHERE expert_id = %d AND client_id = %d",
                $expertId,
                $userId
            ));
            
            $relationshipId = null;
            
            if ($existingRelationship) {
                // Reactivate if archived/paused
                $relationshipId = $existingRelationship;
                $wpdb->update(
                    $table_relationships,
                    [
                        'status' => 'active',
                        'started_at' => current_time('mysql'),
                        'ended_at' => null,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $relationshipId]
                );
            } else {
                // Create new relationship
                $wpdb->insert($table_relationships, [
                    'expert_id' => $expertId,
                    'client_id' => $userId,
                    'status' => 'active',
                    'source' => 'marketplace',
                    'started_at' => current_time('mysql'),
                    'created_at' => current_time('mysql')
                ]);
                
                $relationshipId = $wpdb->insert_id;
            }
            
            // Update request
            $wpdb->update(
                $table_requests,
                [
                    'status' => 'approved',
                    'expert_response' => $responseMessage,
                    'created_relationship_id' => $relationshipId,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $requestId]
            );
            
            // Assign package if requested
            if (!empty($data['assign_package']) && !empty($request['service_id'])) {
                $this->assignPackageToClient($relationshipId, (int) $request['service_id']);
            }
            
            // TODO: Send notification to user
            
            $message = self::MSG_CLIENT_ADDED;
            if (!empty($data['assign_package'])) {
                $message .= self::MSG_PACKAGE_ASSIGNED;
            } else {
                $message .= '.';
            }
            
            return [
                'status' => 'approved',
                'client_id' => $relationshipId,
                'message' => $message
            ];
        }
        
        return ['error' => 'Geçersiz işlem'];
    }
    
    /**
     * Get service data
     * 
     * @param int $serviceId Service ID
     * @return array|null
     */
    private function getServiceData(int $serviceId): ?array {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_services WHERE id = %d",
            $serviceId
        ), ARRAY_A);
        
        if (!$service) {
            return null;
        }
        
        return [
            'id' => (int) $service['id'],
            'name' => $service['name'],
            'price' => (float) $service['price'],
            'type' => $service['type'],
            'duration_minutes' => (int) $service['duration_minutes'],
            'session_count' => $service['session_count'] ? (int) $service['session_count'] : null
        ];
    }
    
    /**
     * Assign package to client based on service
     * 
     * @param int $relationshipId Relationship ID
     * @param int $serviceId Service ID
     * @return bool
     */
    private function assignPackageToClient(int $relationshipId, int $serviceId): bool {
        global $wpdb;
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        // Get service details
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_services WHERE id = %d",
            $serviceId
        ), ARRAY_A);
        
        if (!$service) {
            return false;
        }
        
        // Create package
        $result = $wpdb->insert($table_packages, [
            'relationship_id' => $relationshipId,
            'package_name' => $service['name'],
            'package_type' => $service['type'] === 'session' || $service['type'] === 'package' ? 'session' : 'duration',
            'total_sessions' => $service['session_count'],
            'used_sessions' => 0,
            'start_date' => current_time('mysql', false),
            'end_date' => null,
            'price' => $service['price'],
            'status' => 'active',
            'created_at' => current_time('mysql')
        ]);
        
        return $result !== false;
    }
}
