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
}
