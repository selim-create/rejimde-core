<?php
namespace Rejimde\Api\V1;

use Rejimde\Api\BaseController;
use Rejimde\Services\TaskService;
use Rejimde\Services\TaskProgressService;
use Rejimde\Services\PeriodService;
use Rejimde\Services\CircleTaskService;
use WP_REST_Request;

/**
 * Task API Controller
 */
class TaskController extends BaseController {
    
    protected $base = 'tasks';
    private $taskService;
    private $taskProgressService;
    private $periodService;
    private $circleTaskService;
    
    public function __construct() {
        $this->taskService = new TaskService();
        $this->taskProgressService = new TaskProgressService();
        $this->periodService = new PeriodService();
        $this->circleTaskService = new CircleTaskService();
    }
    
    public function register_routes() {
        // Get all active task definitions
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_tasks'],
            'permission_callback' => '__return_true'
        ]);
        
        // Get daily tasks
        register_rest_route($this->namespace, '/' . $this->base . '/daily', [
            'methods' => 'GET',
            'callback' => [$this, 'get_daily_tasks'],
            'permission_callback' => '__return_true'
        ]);
        
        // Get weekly tasks
        register_rest_route($this->namespace, '/' . $this->base . '/weekly', [
            'methods' => 'GET',
            'callback' => [$this, 'get_weekly_tasks'],
            'permission_callback' => '__return_true'
        ]);
        
        // Get monthly tasks
        register_rest_route($this->namespace, '/' . $this->base . '/monthly', [
            'methods' => 'GET',
            'callback' => [$this, 'get_monthly_tasks'],
            'permission_callback' => '__return_true'
        ]);
        
        // Get user's all tasks (requires auth)
        register_rest_route($this->namespace, '/' . $this->base . '/me', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_tasks'],
            'permission_callback' => [$this, 'check_auth']
        ]);
    }
    
    /**
     * Get all active task definitions
     */
    public function get_tasks(WP_REST_Request $request) {
        $type = $request->get_param('type');
        $tasks = $this->taskService->getActiveTaskDefinitions($type);
        
        return $this->success($tasks);
    }
    
    /**
     * Get daily task definitions
     */
    public function get_daily_tasks(WP_REST_Request $request) {
        $tasks = $this->taskService->getActiveTaskDefinitions('daily');
        return $this->success($tasks);
    }
    
    /**
     * Get weekly task definitions
     */
    public function get_weekly_tasks(WP_REST_Request $request) {
        $tasks = $this->taskService->getActiveTaskDefinitions('weekly');
        return $this->success($tasks);
    }
    
    /**
     * Get monthly task definitions
     */
    public function get_monthly_tasks(WP_REST_Request $request) {
        $tasks = $this->taskService->getActiveTaskDefinitions('monthly');
        return $this->success($tasks);
    }
    
    /**
     * Get user's task status (all types with progress)
     */
    public function get_my_tasks(WP_REST_Request $request) {
        try {
            $userId = get_current_user_id();
            
            if (!$userId) {
                return $this->error('User not authenticated', 401);
            }
            
            // Initialize tasks for current periods if needed
            $this->taskService->initializeUserTasks($userId, 'daily');
            $this->taskService->initializeUserTasks($userId, 'weekly');
            $this->taskService->initializeUserTasks($userId, 'monthly');
            
            // Get all user tasks
            $allTasks = $this->taskService->getAllUserTasks($userId);
            
            // Format tasks for response
            $daily = $this->formatUserTasks($allTasks['daily'], 'daily');
            $weekly = $this->formatUserTasks($allTasks['weekly'], 'weekly');
            $monthly = $this->formatUserTasks($allTasks['monthly'], 'monthly');
            
            // Get circle tasks if user is in a circle
            $circle = [];
            $circleId = get_user_meta($userId, 'circle_id', true);
            if ($circleId) {
                $circleTasks = $this->circleTaskService->getCircleTasks($circleId);
                $circle = $this->formatCircleTasks($circleTasks);
            }
            
            // Calculate summary
            $summary = $this->calculateSummary($userId);
            
            return $this->success([
                'daily' => $daily,
                'weekly' => $weekly,
                'monthly' => $monthly,
                'circle' => $circle,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            error_log('TaskController::get_my_tasks error: ' . $e->getMessage());
            return $this->success([
                'daily' => [],
                'weekly' => [],
                'monthly' => [],
                'circle' => [],
                'summary' => [
                    'completed_today' => 0,
                    'completed_this_week' => 0,
                    'completed_this_month' => 0
                ]
            ]);
        }
    }
    
    /**
     * Format user tasks for API response
     */
    private function formatUserTasks(array $tasks, string $type): array {
        $formatted = [];
        
        foreach ($tasks as $task) {
            $percent = $task['target_value'] > 0 
                ? round(($task['current_value'] / $task['target_value']) * 100, 1) 
                : 0;
            
            $expiresAt = $this->periodService->getPeriodEndTimestamp($type);
            
            $formatted[] = [
                'id' => $task['slug'],
                'title' => $task['title'],
                'description' => $task['description'],
                'progress' => (int)$task['current_value'],
                'target' => (int)$task['target_value'],
                'percent' => $percent,
                'reward_score' => (int)$task['reward_score'],
                'badge_contribution' => (int)$task['badge_progress_contribution'],
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
                'status' => $task['status'],
                'completed_at' => $task['completed_at']
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Format circle tasks for API response
     */
    private function formatCircleTasks(array $tasks): array {
        $formatted = [];
        
        foreach ($tasks as $task) {
            $percent = $task['target_value'] > 0 
                ? round(($task['current_value'] / $task['target_value']) * 100, 1) 
                : 0;
            
            $formatted[] = [
                'id' => $task['slug'],
                'title' => $task['title'],
                'description' => $task['description'],
                'progress' => (int)$task['current_value'],
                'target' => (int)$task['target_value'],
                'percent' => $percent,
                'reward_score' => (int)$task['reward_score'],
                'status' => $task['status'],
                'completed_at' => $task['completed_at'],
                'top_contributors' => $task['top_contributors'] ?? []
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Calculate task completion summary
     */
    private function calculateSummary(int $userId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_tasks';
        
        $today = date('Y-m-d');
        $thisWeek = $this->periodService->getCurrentPeriodKey('weekly');
        $thisMonth = $this->periodService->getCurrentPeriodKey('monthly');
        
        $completedToday = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE user_id = %d 
             AND status = 'completed' 
             AND DATE(completed_at) = %s",
            $userId, $today
        ));
        
        $completedThisWeek = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE user_id = %d 
             AND status = 'completed' 
             AND period_key = %s",
            $userId, $thisWeek
        ));
        
        $completedThisMonth = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE user_id = %d 
             AND status = 'completed' 
             AND period_key = %s",
            $userId, $thisMonth
        ));
        
        return [
            'completed_today' => $completedToday,
            'completed_this_week' => $completedThisWeek,
            'completed_this_month' => $completedThisMonth
        ];
    }
}
