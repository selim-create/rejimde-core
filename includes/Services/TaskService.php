<?php
namespace Rejimde\Services;

/**
 * Task Management Service
 * 
 * Handles task definitions and user task assignments
 */
class TaskService {
    
    private $periodService;
    
    public function __construct() {
        $this->periodService = new PeriodService();
    }
    
    /**
     * Get all active task definitions
     * 
     * @param string|null $type Filter by task type (daily, weekly, monthly, circle)
     * @return array Array of task definitions
     */
    public function getActiveTaskDefinitions(string $type = null): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_task_definitions';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));
        
        if (!$table_exists) {
            return [];
        }
        
        $sql = "SELECT * FROM $table WHERE is_active = 1";
        
        if ($type) {
            $sql .= $wpdb->prepare(" AND task_type = %s", $type);
        }
        
        $sql .= " ORDER BY task_type, id";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Decode JSON fields
        foreach ($results as &$task) {
            $task['scoring_event_types'] = json_decode($task['scoring_event_types'], true) ?? [];
        }
        
        return $results;
    }
    
    /**
     * Get task definition by slug
     * 
     * @param string $slug Task slug
     * @return array|null Task definition or null
     */
    public function getTaskBySlug(string $slug): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_task_definitions';
        
        $task = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE slug = %s", $slug),
            ARRAY_A
        );
        
        if ($task) {
            $task['scoring_event_types'] = json_decode($task['scoring_event_types'], true) ?? [];
        }
        
        return $task;
    }
    
    /**
     * Get task definition by ID
     * 
     * @param int $taskId Task definition ID
     * @return array|null Task definition or null
     */
    public function getTaskById(int $taskId): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_task_definitions';
        
        $task = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $taskId),
            ARRAY_A
        );
        
        if ($task) {
            $task['scoring_event_types'] = json_decode($task['scoring_event_types'], true) ?? [];
        }
        
        return $task;
    }
    
    /**
     * Create or get user task for current period
     * 
     * @param int $userId User ID
     * @param int $taskDefinitionId Task definition ID
     * @return array User task
     */
    public function getOrCreateUserTask(int $userId, int $taskDefinitionId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_tasks';
        
        // Get task definition
        $taskDef = $this->getTaskById($taskDefinitionId);
        if (!$taskDef) {
            return [];
        }
        
        // Get current period key
        $periodKey = $this->periodService->getCurrentPeriodKey($taskDef['task_type']);
        
        // Check if user task exists
        $userTask = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE user_id = %d 
             AND task_definition_id = %d 
             AND period_key = %s",
            $userId, $taskDefinitionId, $periodKey
        ), ARRAY_A);
        
        if ($userTask) {
            return $userTask;
        }
        
        // Create new user task
        $wpdb->insert($table, [
            'user_id' => $userId,
            'task_definition_id' => $taskDefinitionId,
            'period_key' => $periodKey,
            'current_value' => 0,
            'target_value' => $taskDef['target_value'],
            'status' => 'in_progress'
        ]);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $wpdb->insert_id
        ), ARRAY_A);
    }
    
    /**
     * Get user's tasks by type (daily, weekly, monthly)
     * 
     * @param int $userId User ID
     * @param string $type Task type
     * @return array Array of user tasks with task definition data
     */
    public function getUserTasksByType(int $userId, string $type): array {
        global $wpdb;
        $userTasksTable = $wpdb->prefix . 'rejimde_user_tasks';
        $taskDefsTable = $wpdb->prefix . 'rejimde_task_definitions';
        
        $periodKey = $this->periodService->getCurrentPeriodKey($type);
        
        $sql = "SELECT 
                    ut.*,
                    td.slug,
                    td.title,
                    td.description,
                    td.task_type,
                    td.scoring_event_types,
                    td.reward_score,
                    td.badge_progress_contribution
                FROM $userTasksTable ut
                INNER JOIN $taskDefsTable td ON ut.task_definition_id = td.id
                WHERE ut.user_id = %d
                AND td.task_type = %s
                AND ut.period_key = %s
                ORDER BY ut.status, ut.id";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $userId, $type, $periodKey),
            ARRAY_A
        );
        
        // Decode JSON fields
        foreach ($results as &$task) {
            $task['scoring_event_types'] = json_decode($task['scoring_event_types'], true) ?? [];
        }
        
        return $results;
    }
    
    /**
     * Get all user tasks (all types for current periods)
     * 
     * @param int $userId User ID
     * @return array Organized by type (daily, weekly, monthly)
     */
    public function getAllUserTasks(int $userId): array {
        return [
            'daily' => $this->getUserTasksByType($userId, 'daily'),
            'weekly' => $this->getUserTasksByType($userId, 'weekly'),
            'monthly' => $this->getUserTasksByType($userId, 'monthly')
        ];
    }
    
    /**
     * Initialize user tasks for current period
     * Creates task records for all active task definitions if they don't exist
     * 
     * @param int $userId User ID
     * @param string $type Task type (daily, weekly, monthly)
     * @return int Number of tasks created
     */
    public function initializeUserTasks(int $userId, string $type): int {
        $taskDefs = $this->getActiveTaskDefinitions($type);
        $created = 0;
        
        foreach ($taskDefs as $taskDef) {
            $userTask = $this->getOrCreateUserTask($userId, $taskDef['id']);
            if ($userTask) {
                $created++;
            }
        }
        
        return $created;
    }
    
    /**
     * Get all task definitions (Config + Database merged)
     * 
     * @param string|null $type Filter by task type
     * @return array Array of task definitions
     */
    public function getAllTaskDefinitions(string $type = null): array {
        // 1. Config'den görevleri al
        $configTasks = require REJIMDE_PATH . 'includes/Config/TaskDefinitions.php';
        
        // 2. Database'den dinamik görevleri al
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_task_definitions';
        $dbTasks = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1", ARRAY_A);
        
        // 3. Merge et (database slug'ları config'i override edebilir)
        $allTasks = [];
        
        foreach ($configTasks as $slug => $task) {
            $task['slug'] = $slug;
            $task['source'] = 'config';
            if ($type === null || $task['task_type'] === $type) {
                $allTasks[$slug] = $task;
            }
        }
        
        foreach ($dbTasks as $task) {
            $task['source'] = 'database';
            $task['scoring_event_types'] = json_decode($task['scoring_event_types'], true) ?? [];
            if ($type === null || $task['task_type'] === $type) {
                $allTasks[$task['slug']] = $task;
            }
        }
        
        return $allTasks;
    }
    
    /**
     * Create dynamic task (database)
     * 
     * @param array $data Task data
     * @return int|false Task ID or false on failure
     */
    public function createTask(array $data): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_task_definitions';
        
        // Validate required fields
        if (empty($data['slug']) || empty($data['title']) || empty($data['task_type'])) {
            return false;
        }
        
        // Check if slug already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s",
            $data['slug']
        ));
        
        if ($exists) {
            return false; // Slug must be unique
        }
        
        // Prepare data for insertion
        $insert_data = [
            'slug' => $data['slug'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'task_type' => $data['task_type'],
            'target_value' => $data['target_value'] ?? 1,
            'scoring_event_types' => json_encode($data['scoring_event_types'] ?? []),
            'reward_score' => $data['reward_score'] ?? 0,
            'badge_progress_contribution' => $data['badge_progress_contribution'] ?? 0,
            'reward_badge_id' => $data['reward_badge_id'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ];
        
        $result = $wpdb->insert($table, $insert_data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update dynamic task
     * 
     * @param int $id Task ID
     * @param array $data Task data
     * @return bool Success
     */
    public function updateTask(int $id, array $data): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_task_definitions';
        
        // Prepare data for update
        $update_data = [];
        
        $allowed_fields = [
            'slug', 'title', 'description', 'task_type', 'target_value',
            'scoring_event_types', 'reward_score', 'badge_progress_contribution',
            'reward_badge_id', 'is_active'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if ($field === 'scoring_event_types') {
                    $update_data[$field] = json_encode($data[$field]);
                } else {
                    $update_data[$field] = $data[$field];
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $id],
            null,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete dynamic task
     * 
     * @param int $id Task ID
     * @return bool Success
     */
    public function deleteTask(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_task_definitions';
        
        $result = $wpdb->delete($table, ['id' => $id], ['%d']);
        
        return $result !== false;
    }
    
    /**
     * Toggle task active status
     * 
     * @param int $id Task ID
     * @return int|false New status (1 or 0) or false on failure
     */
    public function toggleTaskStatus(int $id): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_task_definitions';
        
        // Get current status
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM $table WHERE id = %d",
            $id
        ));
        
        if ($current === null) {
            return false;
        }
        
        $new_status = $current ? 0 : 1;
        
        $result = $wpdb->update(
            $table,
            ['is_active' => $new_status],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
        
        return $result !== false ? $new_status : false;
    }
}
