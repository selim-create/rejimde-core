<?php
namespace Rejimde\Services;

/**
 * Service Manager
 * 
 * Handles business logic for expert services/packages management
 */
class ServiceManager {
    
    /**
     * Get expert's services
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getServices(int $expertId): array {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        // Tablo var mı kontrol et
        $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_services));
        if (!$tableExists) {
            // Tablo yoksa boş array dön (hata yerine)
            error_log('Rejimde: Services table does not exist');
            return [];
        }
        
        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_services 
             WHERE expert_id = %d 
             ORDER BY sort_order ASC, created_at DESC",
            $expertId
        ), ARRAY_A);
        
        // Database hatası kontrolü
        if ($wpdb->last_error) {
            error_log('Rejimde Services: DB Error - ' . $wpdb->last_error);
            return [];
        }
        
        if (!$services || !is_array($services)) {
            return [];
        }
        
        return array_map(function($service) {
            return [
                'id' => (int) $service['id'],
                'name' => $service['name'],
                'description' => $service['description'],
                'type' => $service['type'],
                'price' => (float) $service['price'],
                'currency' => $service['currency'],
                'duration_minutes' => (int) $service['duration_minutes'],
                'session_count' => $service['session_count'] ? (int) $service['session_count'] : null,
                'validity_days' => $service['validity_days'] ? (int) $service['validity_days'] : null,
                'is_active' => (bool) $service['is_active'],
                'is_featured' => (bool) $service['is_featured'],
                'is_public' => (bool) $service['is_public'],
                'color' => $service['color'],
                'sort_order' => (int) $service['sort_order'],
                'booking_enabled' => (bool) $service['booking_enabled'],
                'created_at' => $service['created_at'],
                'updated_at' => $service['updated_at'],
            ];
        }, $services);
    }
    
    /**
     * Get service by ID
     * 
     * @param int $serviceId Service ID
     * @param int $expertId Expert user ID
     * @return array|null
     */
    public function getService(int $serviceId, int $expertId): ?array {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_services WHERE id = %d AND expert_id = %d",
            $serviceId,
            $expertId
        ), ARRAY_A);
        
        if (!$service) {
            return null;
        }
        
        return [
            'id' => (int) $service['id'],
            'name' => $service['name'],
            'description' => $service['description'],
            'type' => $service['type'],
            'price' => (float) $service['price'],
            'currency' => $service['currency'],
            'duration_minutes' => (int) $service['duration_minutes'],
            'session_count' => $service['session_count'] ? (int) $service['session_count'] : null,
            'validity_days' => $service['validity_days'] ? (int) $service['validity_days'] : null,
            'is_active' => (bool) $service['is_active'],
            'is_featured' => (bool) $service['is_featured'],
            'is_public' => (bool) $service['is_public'],
            'color' => $service['color'],
            'sort_order' => (int) $service['sort_order'],
            'booking_enabled' => (bool) $service['booking_enabled'],
            'created_at' => $service['created_at'],
            'updated_at' => $service['updated_at'],
        ];
    }
    
    /**
     * Create service
     * 
     * @param int $expertId Expert user ID
     * @param array $data Service data
     * @return int|array Service ID or error array
     */
    public function createService(int $expertId, array $data) {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        // Validation
        if (empty($data['name']) || empty($data['type']) || !isset($data['price'])) {
            return ['error' => 'Name, type, and price are required'];
        }
        
        $insertData = [
            'expert_id' => $expertId,
            'name' => sanitize_text_field($data['name']),
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
            'type' => $data['type'],
            'price' => (float) $data['price'],
            'currency' => $data['currency'] ?? 'TRY',
            'duration_minutes' => isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : 60,
            'session_count' => isset($data['session_count']) ? (int) $data['session_count'] : null,
            'validity_days' => isset($data['validity_days']) ? (int) $data['validity_days'] : null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'is_featured' => isset($data['is_featured']) ? (bool) $data['is_featured'] : false,
            'is_public' => isset($data['is_public']) ? (bool) $data['is_public'] : true,
            'color' => $data['color'] ?? '#3B82F6',
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'booking_enabled' => isset($data['booking_enabled']) ? (bool) $data['booking_enabled'] : true,
        ];
        
        $result = $wpdb->insert($table_services, $insertData);
        
        if ($result === false) {
            return ['error' => 'Failed to create service'];
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update service
     * 
     * @param int $serviceId Service ID
     * @param array $data Update data
     * @return bool|array True on success or error array
     */
    public function updateService(int $serviceId, array $data) {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        $updateData = [];
        
        $allowedFields = [
            'name', 'description', 'type', 'price', 'currency', 'duration_minutes',
            'session_count', 'validity_days', 'is_active', 'is_featured', 'is_public',
            'color', 'sort_order', 'booking_enabled'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'name') {
                    $updateData[$field] = sanitize_text_field($data[$field]);
                } elseif ($field === 'description') {
                    $updateData[$field] = sanitize_textarea_field($data[$field]);
                } elseif (in_array($field, ['is_active', 'is_featured', 'is_public', 'booking_enabled'])) {
                    $updateData[$field] = (bool) $data[$field];
                } elseif (in_array($field, ['price', 'duration_minutes', 'session_count', 'validity_days', 'sort_order'])) {
                    $updateData[$field] = is_numeric($data[$field]) ? $data[$field] : null;
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (empty($updateData)) {
            return ['error' => 'No valid fields to update'];
        }
        
        $result = $wpdb->update($table_services, $updateData, ['id' => $serviceId]);
        
        return $result !== false;
    }
    
    /**
     * Delete service (soft delete - set is_active to false)
     * 
     * @param int $serviceId Service ID
     * @param int $expertId Expert user ID
     * @return bool
     */
    public function deleteService(int $serviceId, int $expertId): bool {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        $result = $wpdb->update(
            $table_services,
            ['is_active' => 0],
            [
                'id' => $serviceId,
                'expert_id' => $expertId
            ]
        );
        
        return $result !== false;
    }
    
    /**
     * Toggle service active status
     * 
     * @param int $serviceId Service ID
     * @param int $expertId Expert user ID
     * @return bool|array
     */
    public function toggleActive(int $serviceId, int $expertId) {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT is_active FROM $table_services WHERE id = %d AND expert_id = %d",
            $serviceId,
            $expertId
        ), ARRAY_A);
        
        if (!$service) {
            return ['error' => 'Service not found'];
        }
        
        $newStatus = !$service['is_active'];
        
        $result = $wpdb->update(
            $table_services,
            ['is_active' => $newStatus],
            ['id' => $serviceId]
        );
        
        return $result !== false ? ['is_active' => $newStatus] : ['error' => 'Failed to toggle status'];
    }
    
    /**
     * Reorder services
     * 
     * @param int $expertId Expert user ID
     * @param array $order Array of service IDs in new order
     * @return bool
     */
    public function reorderServices(int $expertId, array $order): bool {
        global $wpdb;
        $table_services = $wpdb->prefix . 'rejimde_services';
        
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($order as $index => $serviceId) {
                $result = $wpdb->update(
                    $table_services,
                    ['sort_order' => $index],
                    [
                        'id' => (int) $serviceId,
                        'expert_id' => $expertId
                    ]
                );
                
                if ($result === false) {
                    throw new \Exception('Failed to update order');
                }
            }
            
            $wpdb->query('COMMIT');
            return true;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }
}
