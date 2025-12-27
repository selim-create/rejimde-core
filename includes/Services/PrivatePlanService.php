<?php
namespace Rejimde\Services;

/**
 * Private Plan Service
 * 
 * Handles business logic for custom plans (diet, workout, etc.)
 */
class PrivatePlanService {
    
    /**
     * Get plans with filters
     * 
     * @param int $expertId Expert user ID
     * @param array $filters Filters (type, status, client_id, limit, offset)
     * @return array
     */
    public function getPlans(int $expertId, array $filters = []): array {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        
        $query = "SELECT * FROM $table_plans WHERE expert_id = %d";
        $params = [$expertId];
        
        if (!empty($filters['type'])) {
            $query .= " AND type = %s";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $query .= " AND status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['client_id'])) {
            $query .= " AND client_id = %d";
            $params[] = (int) $filters['client_id'];
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $plans = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        return $this->formatPlans($plans);
    }
    
    /**
     * Get single plan
     * 
     * @param int $planId Plan ID
     * @param int $expertId Expert user ID
     * @return array|null
     */
    public function getPlan(int $planId, int $expertId): ?array {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_plans WHERE id = %d AND expert_id = %d",
            $planId,
            $expertId
        ), ARRAY_A);
        
        if (!$plan) {
            return null;
        }
        
        return $this->formatPlan($plan);
    }
    
    /**
     * Get client's assigned plans
     * 
     * @param int $clientId Client user ID
     * @return array
     */
    public function getClientPlans(int $clientId): array {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        $table_progress = $wpdb->prefix . 'rejimde_plan_progress';
        
        $plans = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, pr.progress_percent, pr.completed_items 
             FROM $table_plans p
             LEFT JOIN $table_progress pr ON p.id = pr.plan_id AND pr.user_id = %d
             WHERE p.client_id = %d AND p.status IN ('assigned', 'in_progress', 'completed')
             ORDER BY p.assigned_at DESC",
            $clientId,
            $clientId
        ), ARRAY_A);
        
        return $this->formatPlans($plans);
    }
    
    /**
     * Create plan
     * 
     * @param int $expertId Expert user ID
     * @param array $data Plan data
     * @return int|array Plan ID or error array
     */
    public function createPlan(int $expertId, array $data) {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        
        if (empty($data['title']) || empty($data['type'])) {
            return ['error' => 'Title and type are required'];
        }
        
        $insertData = [
            'expert_id' => $expertId,
            'title' => sanitize_text_field($data['title']),
            'type' => $data['type'],
            'status' => $data['status'] ?? 'draft',
            'plan_data' => isset($data['plan_data']) ? json_encode($data['plan_data']) : null,
            'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : null,
        ];
        
        $result = $wpdb->insert($table_plans, $insertData);
        
        if ($result === false) {
            return ['error' => 'Failed to create plan'];
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update plan
     * 
     * @param int $planId Plan ID
     * @param array $data Update data
     * @return bool|array
     */
    public function updatePlan(int $planId, array $data) {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        
        $updateData = [];
        
        if (isset($data['title'])) {
            $updateData['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['type'])) {
            $updateData['type'] = $data['type'];
        }
        if (isset($data['plan_data'])) {
            $updateData['plan_data'] = json_encode($data['plan_data']);
        }
        if (isset($data['notes'])) {
            $updateData['notes'] = sanitize_textarea_field($data['notes']);
        }
        
        if (empty($updateData)) {
            return ['error' => 'No valid fields to update'];
        }
        
        $result = $wpdb->update($table_plans, $updateData, ['id' => $planId]);
        
        return $result !== false;
    }
    
    /**
     * Delete plan
     * 
     * @param int $planId Plan ID
     * @param int $expertId Expert user ID
     * @return bool
     */
    public function deletePlan(int $planId, int $expertId): bool {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        
        $result = $wpdb->delete($table_plans, [
            'id' => $planId,
            'expert_id' => $expertId
        ]);
        
        return $result !== false;
    }
    
    /**
     * Assign plan to client
     * 
     * @param int $planId Plan ID
     * @param int $clientId Client user ID
     * @param int $relationshipId Relationship ID
     * @return bool|array
     */
    public function assignPlan(int $planId, int $clientId, int $relationshipId) {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        
        $result = $wpdb->update(
            $table_plans,
            [
                'client_id' => $clientId,
                'relationship_id' => $relationshipId,
                'status' => 'assigned',
                'assigned_at' => current_time('mysql')
            ],
            ['id' => $planId]
        );
        
        if ($result === false) {
            return ['error' => 'Failed to assign plan'];
        }
        
        // Send notification to client
        if (class_exists('Rejimde\\Services\\NotificationService')) {
            $notificationService = new NotificationService();
            $notificationService->create($clientId, 'plan_assigned', [
                'entity_type' => 'plan',
                'entity_id' => $planId
            ]);
        }
        
        return true;
    }
    
    /**
     * Duplicate plan
     * 
     * @param int $planId Plan ID
     * @param int $expertId Expert user ID
     * @return int|array New plan ID or error
     */
    public function duplicatePlan(int $planId, int $expertId) {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_plans WHERE id = %d AND expert_id = %d",
            $planId,
            $expertId
        ), ARRAY_A);
        
        if (!$original) {
            return ['error' => 'Plan not found'];
        }
        
        $newData = [
            'expert_id' => $expertId,
            'title' => $original['title'] . ' (Kopya)',
            'type' => $original['type'],
            'status' => 'draft',
            'plan_data' => $original['plan_data'],
            'notes' => $original['notes'],
        ];
        
        $result = $wpdb->insert($table_plans, $newData);
        
        if ($result === false) {
            return ['error' => 'Failed to duplicate plan'];
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update plan status
     * 
     * @param int $planId Plan ID
     * @param string $status New status
     * @return bool|array
     */
    public function updateStatus(int $planId, string $status) {
        global $wpdb;
        $table_plans = $wpdb->prefix . 'rejimde_private_plans';
        
        $validStatuses = ['draft', 'ready', 'assigned', 'in_progress', 'completed'];
        if (!in_array($status, $validStatuses)) {
            return ['error' => 'Invalid status'];
        }
        
        $updateData = ['status' => $status];
        
        if ($status === 'completed') {
            $updateData['completed_at'] = current_time('mysql');
        }
        
        $result = $wpdb->update($table_plans, $updateData, ['id' => $planId]);
        
        return $result !== false;
    }
    
    /**
     * Record progress
     * 
     * @param int $planId Plan ID
     * @param int $userId User ID
     * @param array $completedItems Completed item IDs
     * @param float $progressPercent Progress percentage
     * @return bool
     */
    public function recordProgress(int $planId, int $userId, array $completedItems, float $progressPercent): bool {
        global $wpdb;
        $table_progress = $wpdb->prefix . 'rejimde_plan_progress';
        
        $data = [
            'plan_id' => $planId,
            'user_id' => $userId,
            'completed_items' => json_encode($completedItems),
            'progress_percent' => $progressPercent,
        ];
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_progress WHERE plan_id = %d AND user_id = %d",
            $planId,
            $userId
        ));
        
        if ($existing) {
            $result = $wpdb->update($table_progress, $data, [
                'plan_id' => $planId,
                'user_id' => $userId
            ]);
        } else {
            $result = $wpdb->insert($table_progress, $data);
        }
        
        // Update plan status if completed
        if ($progressPercent >= 100) {
            $this->updateStatus($planId, 'completed');
        } elseif ($progressPercent > 0) {
            $table_plans = $wpdb->prefix . 'rejimde_private_plans';
            $currentStatus = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM $table_plans WHERE id = %d",
                $planId
            ));
            
            if ($currentStatus === 'assigned') {
                $this->updateStatus($planId, 'in_progress');
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Format plans array
     */
    private function formatPlans(array $plans): array {
        return array_map([$this, 'formatPlan'], $plans);
    }
    
    /**
     * Format single plan
     */
    private function formatPlan(array $plan): array {
        $formatted = [
            'id' => (int) $plan['id'],
            'expert_id' => (int) $plan['expert_id'],
            'client_id' => $plan['client_id'] ? (int) $plan['client_id'] : null,
            'relationship_id' => $plan['relationship_id'] ? (int) $plan['relationship_id'] : null,
            'title' => $plan['title'],
            'type' => $plan['type'],
            'status' => $plan['status'],
            'plan_data' => $plan['plan_data'] ? json_decode($plan['plan_data'], true) : null,
            'notes' => $plan['notes'],
            'assigned_at' => $plan['assigned_at'],
            'completed_at' => $plan['completed_at'],
            'created_at' => $plan['created_at'],
            'updated_at' => $plan['updated_at'],
        ];
        
        // Add progress if available
        if (isset($plan['progress_percent'])) {
            $formatted['progress'] = [
                'percent' => (float) $plan['progress_percent'],
                'completed_items' => $plan['completed_items'] ? json_decode($plan['completed_items'], true) : [],
            ];
        }
        
        // Add client info if assigned
        if ($plan['client_id']) {
            $client = get_userdata((int) $plan['client_id']);
            if ($client) {
                $formatted['client'] = [
                    'id' => (int) $plan['client_id'],
                    'name' => $client->display_name,
                    'avatar' => get_user_meta((int) $plan['client_id'], 'avatar_url', true) ?: 'https://placehold.co/150',
                ];
            }
        }
        
        return $formatted;
    }
}
