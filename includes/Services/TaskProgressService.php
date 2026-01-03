<?php
namespace Rejimde\Services;

use Rejimde\Core\EventDispatcher;

/**
 * Task Progress Tracking Service
 * 
 * Handles progress updates when events are dispatched
 */
class TaskProgressService {
    
    private $taskService;
    private $periodService;
    
    public function __construct() {
        $this->taskService = new TaskService();
        $this->periodService = new PeriodService();
    }
    
    /**
     * Process event and update relevant task progress
     * Called from EventDispatcher after successful event
     * 
     * @param int $userId User ID
     * @param string $eventType Event type
     * @param array $context Event context
     * @return array Updated tasks information
     */
    public function processEvent(int $userId, string $eventType, array $context = []): array {
        global $wpdb;
        
        $updatedTasks = [];
        
        // Get all active task definitions that match this event type
        $taskDefsTable = $wpdb->prefix . 'rejimde_task_definitions';
        $sql = "SELECT * FROM $taskDefsTable WHERE is_active = 1";
        $taskDefs = $wpdb->get_results($sql, ARRAY_A);
        
        foreach ($taskDefs as $taskDef) {
            $scoringEvents = json_decode($taskDef['scoring_event_types'], true) ?? [];
            
            // Check if this event type matches task's scoring events
            if (!in_array($eventType, $scoringEvents)) {
                continue;
            }
            
            // Skip circle tasks (handled separately)
            if ($taskDef['task_type'] === 'circle') {
                continue;
            }
            
            // Get or create user task
            $userTask = $this->taskService->getOrCreateUserTask($userId, $taskDef['id']);
            
            if (!$userTask || $userTask['status'] !== 'in_progress') {
                continue;
            }
            
            // Handle special counting logic for weekly/monthly tasks
            $incrementBy = 1;
            
            if ($taskDef['task_type'] === 'weekly' || $taskDef['task_type'] === 'monthly') {
                // For weekly/monthly exercise tasks, count unique days
                if (in_array($eventType, ['exercise_completed', 'diet_completed'])) {
                    $incrementBy = $this->shouldIncrementDailyCount($userId, $userTask['id'], $eventType) ? 1 : 0;
                }
            }
            
            if ($incrementBy > 0) {
                // Update progress
                $result = $this->updateProgress($userId, $userTask['id'], $incrementBy);
                if ($result) {
                    $updatedTasks[] = $result;
                }
            }
        }
        
        return $updatedTasks;
    }
    
    /**
     * Check if we should increment daily count for weekly/monthly tasks
     * Prevents multiple increments on the same day for the same event
     * 
     * @param int $userId User ID
     * @param int $userTaskId User task ID
     * @param string $eventType Event type
     * @return bool True if should increment
     */
    private function shouldIncrementDailyCount(int $userId, int $userTaskId, string $eventType): bool {
        global $wpdb;
        $eventsTable = $wpdb->prefix . 'rejimde_events';
        $today = date('Y-m-d');
        
        // Check if user already has this event type today
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $eventsTable 
             WHERE user_id = %d 
             AND event_type = %s 
             AND DATE(created_at) = %s",
            $userId, $eventType, $today
        ));
        
        return $count == 1; // Only increment if this is the first event of this type today
    }
    
    /**
     * Update user task progress
     * 
     * @param int $userId User ID
     * @param int $userTaskId User task ID
     * @param int $incrementBy Amount to increment
     * @return array|null Updated task data or null
     */
    public function updateProgress(int $userId, int $userTaskId, int $incrementBy = 1): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_tasks';
        
        // Get current task
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $userTaskId, $userId
        ), ARRAY_A);
        
        if (!$task || $task['status'] !== 'in_progress') {
            return null;
        }
        
        // Update current value
        $newValue = (int)$task['current_value'] + $incrementBy;
        
        $wpdb->update(
            $table,
            ['current_value' => $newValue],
            ['id' => $userTaskId],
            ['%d'],
            ['%d']
        );
        
        // Check if task is completed
        if ($newValue >= (int)$task['target_value']) {
            $this->checkAndCompleteTask($userTaskId);
        }
        
        // Return updated task
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $userTaskId
        ), ARRAY_A);
    }
    
    /**
     * Check and complete task if target reached
     * 
     * @param int $userTaskId User task ID
     * @return bool True if completed
     */
    public function checkAndCompleteTask(int $userTaskId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_tasks';
        
        // Get task
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $userTaskId
        ), ARRAY_A);
        
        if (!$task || $task['status'] !== 'in_progress') {
            return false;
        }
        
        // Check if target reached
        if ((int)$task['current_value'] >= (int)$task['target_value']) {
            // Mark as completed
            $wpdb->update(
                $table,
                [
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ],
                ['id' => $userTaskId],
                ['%s', '%s'],
                ['%d']
            );
            
            // Award rewards
            $this->awardTaskRewards($task['user_id'], $task);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Award task completion rewards
     * 
     * @param int $userId User ID
     * @param array $task Task data
     * @return void
     */
    private function awardTaskRewards(int $userId, array $task): void {
        // Get task definition
        $taskDef = $this->taskService->getTaskById($task['task_definition_id']);
        if (!$taskDef) {
            return;
        }
        
        // Award points if configured
        if ($taskDef['reward_score'] > 0) {
            $scoreService = new ScoreService();
            $circleId = get_user_meta($userId, 'circle_id', true);
            $scoreService->awardPoints($userId, $taskDef['reward_score'], $circleId ?: null);
        }
        
        // Dispatch task completed event
        $eventType = $taskDef['task_type'] . '_task_completed';
        
        // Prevent infinite loop by checking if this is already a task_completed event
        if (!in_array($eventType, ['task_completed', 'weekly_task_completed', 'monthly_task_completed'])) {
            EventDispatcher::getInstance()->dispatch($eventType, [
                'user_id' => $userId,
                'task_id' => $task['id'],
                'task_slug' => $taskDef['slug'],
                'context' => [
                    'task_type' => $taskDef['task_type'],
                    'reward_score' => $taskDef['reward_score']
                ]
            ]);
        }
        
        // Also dispatch generic task_completed event
        EventDispatcher::getInstance()->dispatch('task_completed', [
            'user_id' => $userId,
            'task_id' => $task['id'],
            'task_slug' => $taskDef['slug'],
            'context' => [
                'task_type' => $taskDef['task_type']
            ]
        ]);
    }
    
    /**
     * Expire old tasks
     * Called by cron job
     * 
     * @param string $type Task type (daily, weekly, monthly)
     * @return int Number of tasks expired
     */
    public function expireOldTasks(string $type): int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_tasks';
        
        $currentPeriod = $this->periodService->getCurrentPeriodKey($type);
        
        // Get task definition IDs for this type
        $taskDefsTable = $wpdb->prefix . 'rejimde_task_definitions';
        $taskDefIds = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $taskDefsTable WHERE task_type = %s AND is_active = 1",
            $type
        ));
        
        if (empty($taskDefIds)) {
            return 0;
        }
        
        $taskDefIdsList = implode(',', array_map('intval', $taskDefIds));
        
        // Update expired tasks using prepared statement
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table 
             SET status = 'expired'
             WHERE task_definition_id IN ($taskDefIdsList)
             AND period_key != %s
             AND status = 'in_progress'",
            $currentPeriod
        ));
        
        return $result ?: 0;
    }
}
