<?php
namespace Rejimde\Services;

/**
 * Circle Task Management Service
 */
class CircleTaskService {
    
    private $taskService;
    private $periodService;
    
    public function __construct() {
        $this->taskService = new TaskService();
        $this->periodService = new PeriodService();
    }
    
    /**
     * Get circle's active tasks
     * 
     * @param int $circleId Circle ID
     * @return array Array of circle tasks
     */
    public function getCircleTasks(int $circleId): array {
        global $wpdb;
        $circleTasksTable = $wpdb->prefix . 'rejimde_circle_tasks';
        $taskDefsTable = $wpdb->prefix . 'rejimde_task_definitions';
        
        // Get current period for each task type
        $periods = [
            'weekly' => $this->periodService->getCurrentPeriodKey('weekly'),
            'monthly' => $this->periodService->getCurrentPeriodKey('monthly')
        ];
        
        $sql = "SELECT 
                    ct.*,
                    td.slug,
                    td.title,
                    td.description,
                    td.task_type,
                    td.scoring_event_types,
                    td.reward_score
                FROM $circleTasksTable ct
                INNER JOIN $taskDefsTable td ON ct.task_definition_id = td.id
                WHERE ct.circle_id = %d
                AND td.task_type = 'circle'
                AND (ct.period_key = %s OR ct.period_key = %s)
                ORDER BY ct.status, ct.id";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $circleId, $periods['weekly'], $periods['monthly']),
            ARRAY_A
        );
        
        // Decode JSON and add contributions info
        foreach ($results as &$task) {
            $task['scoring_event_types'] = json_decode($task['scoring_event_types'], true) ?? [];
            $task['top_contributors'] = $this->getTopContributors($task['id'], 3);
        }
        
        return $results;
    }
    
    /**
     * Get or create circle task for current period
     * 
     * @param int $circleId Circle ID
     * @param int $taskDefinitionId Task definition ID
     * @return array|null Circle task or null
     */
    public function getOrCreateCircleTask(int $circleId, int $taskDefinitionId): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_circle_tasks';
        
        // Get task definition
        $taskDef = $this->taskService->getTaskById($taskDefinitionId);
        if (!$taskDef || $taskDef['task_type'] !== 'circle') {
            return null;
        }
        
        // Use weekly or monthly period for circle tasks
        $periodType = 'weekly'; // Default to weekly for circle tasks
        $periodKey = $this->periodService->getCurrentPeriodKey($periodType);
        
        // Check if circle task exists
        $circleTask = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE circle_id = %d 
             AND task_definition_id = %d 
             AND period_key = %s",
            $circleId, $taskDefinitionId, $periodKey
        ), ARRAY_A);
        
        if ($circleTask) {
            return $circleTask;
        }
        
        // Create new circle task
        $wpdb->insert($table, [
            'circle_id' => $circleId,
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
     * Add user contribution to circle task
     * 
     * @param int $circleId Circle ID
     * @param int $userId User ID
     * @param int $taskDefinitionId Task definition ID
     * @param int $value Contribution value
     * @return array Result with task status
     */
    public function addContribution(int $circleId, int $userId, int $taskDefinitionId, int $value): array {
        global $wpdb;
        
        // Get or create circle task
        $circleTask = $this->getOrCreateCircleTask($circleId, $taskDefinitionId);
        if (!$circleTask) {
            return ['success' => false, 'message' => 'Circle task not found'];
        }
        
        if ($circleTask['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'Task already completed or expired'];
        }
        
        $contributionsTable = $wpdb->prefix . 'rejimde_circle_task_contributions';
        $circleTasksTable = $wpdb->prefix . 'rejimde_circle_tasks';
        $today = date('Y-m-d');
        
        // Check if contribution exists for today
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $contributionsTable 
             WHERE circle_task_id = %d 
             AND user_id = %d 
             AND contribution_date = %s",
            $circleTask['id'], $userId, $today
        ), ARRAY_A);
        
        if ($existing) {
            // Update existing contribution
            $newValue = (int)$existing['contribution_value'] + $value;
            $wpdb->update(
                $contributionsTable,
                ['contribution_value' => $newValue],
                ['id' => $existing['id']],
                ['%d'],
                ['%d']
            );
        } else {
            // Insert new contribution
            $wpdb->insert($contributionsTable, [
                'circle_task_id' => $circleTask['id'],
                'user_id' => $userId,
                'contribution_value' => $value,
                'contribution_date' => $today
            ]);
        }
        
        // Update circle task current value
        $newTotal = (int)$circleTask['current_value'] + $value;
        $wpdb->update(
            $circleTasksTable,
            ['current_value' => $newTotal],
            ['id' => $circleTask['id']],
            ['%d'],
            ['%d']
        );
        
        // Check if task completed
        $completed = false;
        if ($newTotal >= (int)$circleTask['target_value']) {
            $completed = $this->checkAndCompleteCircleTask($circleTask['id']);
        }
        
        return [
            'success' => true,
            'current_value' => $newTotal,
            'target_value' => $circleTask['target_value'],
            'completed' => $completed
        ];
    }
    
    /**
     * Get user's contributions to circle tasks
     * 
     * @param int $circleId Circle ID
     * @param int $userId User ID
     * @return array Array of contributions
     */
    public function getUserContributions(int $circleId, int $userId): array {
        global $wpdb;
        $contributionsTable = $wpdb->prefix . 'rejimde_circle_task_contributions';
        $circleTasksTable = $wpdb->prefix . 'rejimde_circle_tasks';
        $taskDefsTable = $wpdb->prefix . 'rejimde_task_definitions';
        
        $sql = "SELECT 
                    c.*,
                    ct.current_value as task_current_value,
                    ct.target_value as task_target_value,
                    td.title as task_title
                FROM $contributionsTable c
                INNER JOIN $circleTasksTable ct ON c.circle_task_id = ct.id
                INNER JOIN $taskDefsTable td ON ct.task_definition_id = td.id
                WHERE ct.circle_id = %d
                AND c.user_id = %d
                ORDER BY c.contribution_date DESC
                LIMIT 20";
        
        return $wpdb->get_results(
            $wpdb->prepare($sql, $circleId, $userId),
            ARRAY_A
        );
    }
    
    /**
     * Get top contributors for a circle task
     * 
     * @param int $circleTaskId Circle task ID
     * @param int $limit Number of top contributors
     * @return array Array of contributors
     */
    private function getTopContributors(int $circleTaskId, int $limit = 3): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_circle_task_contributions';
        
        $sql = "SELECT 
                    user_id,
                    SUM(contribution_value) as total_contribution
                FROM $table
                WHERE circle_task_id = %d
                GROUP BY user_id
                ORDER BY total_contribution DESC
                LIMIT %d";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $circleTaskId, $limit),
            ARRAY_A
        );
        
        // Add user display names
        foreach ($results as &$contributor) {
            $user = get_userdata($contributor['user_id']);
            $contributor['display_name'] = $user ? $user->display_name : 'Unknown';
        }
        
        return $results;
    }
    
    /**
     * Check and complete circle task if target reached
     * 
     * @param int $circleTaskId Circle task ID
     * @return bool True if completed
     */
    public function checkAndCompleteCircleTask(int $circleTaskId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_circle_tasks';
        
        // Get task
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $circleTaskId
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
                ['id' => $circleTaskId],
                ['%s', '%s'],
                ['%d']
            );
            
            // Award rewards to all circle members
            $this->awardCircleTaskRewards($task);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Award circle task completion rewards to all members
     * 
     * @param array $task Circle task data
     * @return void
     */
    private function awardCircleTaskRewards(array $task): void {
        // Get task definition
        $taskDef = $this->taskService->getTaskById($task['task_definition_id']);
        if (!$taskDef || $taskDef['reward_score'] <= 0) {
            return;
        }
        
        // Get all circle members
        global $wpdb;
        $userMetaTable = $wpdb->usermeta;
        
        $memberIds = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM $userMetaTable 
             WHERE meta_key = 'circle_id' 
             AND meta_value = %d",
            $task['circle_id']
        ));
        
        if (empty($memberIds)) {
            return;
        }
        
        // Award points to each member
        $scoreService = new ScoreService();
        foreach ($memberIds as $userId) {
            // Check if user is pro (pro users don't get points)
            if (!$scoreService->isProUser($userId)) {
                $scoreService->awardPoints($userId, $taskDef['reward_score'], $task['circle_id']);
            }
        }
        
        // Dispatch circle task completed event
        \Rejimde\Core\EventDispatcher::getInstance()->dispatch('circle_task_completed', [
            'circle_id' => $task['circle_id'],
            'task_id' => $task['id'],
            'context' => [
                'reward_score' => $taskDef['reward_score'],
                'member_count' => count($memberIds)
            ]
        ]);
    }
}
