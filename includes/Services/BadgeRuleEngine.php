<?php
namespace Rejimde\Services;

/**
 * Badge Rule Engine
 * 
 * Evaluates complex badge conditions
 */
class BadgeRuleEngine {
    
    /**
     * Evaluate badge conditions for a user
     * 
     * @param int $userId User ID
     * @param array $conditions JSON decoded conditions
     * @return array ['passed' => bool, 'progress' => int, 'max' => int]
     */
    public function evaluate(int $userId, array $conditions): array {
        $type = $conditions['type'] ?? '';
        
        switch ($type) {
            case 'COUNT':
                return $this->evaluateCount($userId, $conditions);
                
            case 'COUNT_UNIQUE_DAYS':
                return $this->evaluateCountUniqueDays($userId, $conditions);
                
            case 'STREAK':
                return $this->evaluateStreak($userId, $conditions);
                
            case 'CONSECUTIVE_WEEKS':
                return $this->evaluateConsecutiveWeeks($userId, $conditions);
                
            case 'COUNT_IN_PERIOD':
                return $this->evaluateCountInPeriod($userId, $conditions);
                
            case 'COMEBACK':
                return $this->evaluateComeback($userId, $conditions);
                
            case 'CIRCLE_CONTRIBUTION':
                return $this->evaluateCircleContribution($userId, $conditions);
                
            case 'COUNT_UNIQUE_USERS':
                return $this->evaluateCountUniqueUsers($userId, $conditions);
                
            case 'CIRCLE_HERO':
                return $this->evaluateCircleHero($userId, $conditions);
                
            default:
                return ['passed' => false, 'progress' => 0, 'max' => 1];
        }
    }
    
    /**
     * Check if event matches badge rules
     * 
     * @param string $eventType Event type
     * @param array $conditions Badge conditions
     * @return bool True if matches
     */
    public function eventMatchesRules(string $eventType, array $conditions): bool {
        if (isset($conditions['event'])) {
            return $eventType === $conditions['event'];
        }
        
        if (isset($conditions['events']) && is_array($conditions['events'])) {
            return in_array($eventType, $conditions['events']);
        }
        
        return false;
    }
    
    /**
     * Calculate progress for progressive badges
     * 
     * @param int $userId User ID
     * @param array $conditions Badge conditions
     * @return int Current progress value
     */
    public function calculateProgress(int $userId, array $conditions): int {
        $result = $this->evaluate($userId, $conditions);
        return $result['progress'] ?? 0;
    }
    
    /**
     * Evaluate COUNT condition
     */
    private function evaluateCount(int $userId, array $conditions): array {
        global $wpdb;
        $eventsTable = $wpdb->prefix . 'rejimde_events';
        
        $event = $conditions['event'] ?? '';
        $target = $conditions['target'] ?? 1;
        $contextFilter = $conditions['context_filter'] ?? null;
        
        $sql = "SELECT COUNT(*) FROM $eventsTable WHERE user_id = %d AND event_type = %s";
        $params = [$userId, $event];
        
        // Apply context filter if provided
        if ($contextFilter && is_array($contextFilter)) {
            foreach ($contextFilter as $key => $value) {
                $sql .= " AND JSON_EXTRACT(context, '$.$key') = %s";
                $params[] = $value;
            }
        }
        
        $count = (int)$wpdb->get_var($wpdb->prepare($sql, ...$params));
        
        return [
            'passed' => $count >= $target,
            'progress' => min($count, $target),
            'max' => $target
        ];
    }
    
    /**
     * Evaluate COUNT_UNIQUE_DAYS condition
     */
    private function evaluateCountUniqueDays(int $userId, array $conditions): array {
        global $wpdb;
        $eventsTable = $wpdb->prefix . 'rejimde_events';
        
        $event = $conditions['event'] ?? '';
        
        $count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT DATE(created_at)) 
             FROM $eventsTable 
             WHERE user_id = %d AND event_type = %s",
            $userId, $event
        ));
        
        return [
            'passed' => true, // Progressive badge
            'progress' => $count,
            'max' => $count
        ];
    }
    
    /**
     * Evaluate STREAK condition
     */
    private function evaluateStreak(int $userId, array $conditions): array {
        global $wpdb;
        $streaksTable = $wpdb->prefix . 'rejimde_streaks';
        
        $streakType = $conditions['streak_type'] ?? 'daily_login';
        $target = $conditions['target'] ?? 7;
        
        $streak = $wpdb->get_row($wpdb->prepare(
            "SELECT current_count FROM $streaksTable 
             WHERE user_id = %d AND streak_type = %s",
            $userId, $streakType
        ), ARRAY_A);
        
        $currentStreak = $streak ? (int)$streak['current_count'] : 0;
        
        return [
            'passed' => $currentStreak >= $target,
            'progress' => min($currentStreak, $target),
            'max' => $target
        ];
    }
    
    /**
     * Evaluate CONSECUTIVE_WEEKS condition
     */
    private function evaluateConsecutiveWeeks(int $userId, array $conditions): array {
        global $wpdb;
        $eventsTable = $wpdb->prefix . 'rejimde_events';
        
        $event = $conditions['event'] ?? '';
        $target = $conditions['target'] ?? 4;
        
        // Get all weeks where event occurred (using YEARWEEK for proper ordering)
        $weeks = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT YEARWEEK(created_at, 3) as week 
             FROM $eventsTable 
             WHERE user_id = %d AND event_type = %s 
             ORDER BY week DESC",
            $userId, $event
        ));
        
        // Count consecutive weeks from most recent
        $consecutive = 0;
        $prevWeek = null;
        
        foreach ($weeks as $week) {
            if ($prevWeek === null) {
                $consecutive = 1;
            } else {
                // Check if weeks are consecutive (diff should be 1)
                $diff = $prevWeek - $week;
                
                if ($diff === 1) {
                    $consecutive++;
                } else {
                    break;
                }
            }
            $prevWeek = $week;
        }
        
        return [
            'passed' => $consecutive >= $target,
            'progress' => min($consecutive, $target),
            'max' => $target
        ];
    }
    
    /**
     * Evaluate COUNT_IN_PERIOD condition
     */
    private function evaluateCountInPeriod(int $userId, array $conditions): array {
        global $wpdb;
        $eventsTable = $wpdb->prefix . 'rejimde_events';
        
        $event = $conditions['event'] ?? '';
        $period = $conditions['period'] ?? 'monthly';
        $target = $conditions['target'] ?? 50;
        
        $periodService = new PeriodService();
        $periodKey = $periodService->getCurrentPeriodKey($period);
        
        // Build date filter based on period type
        if ($period === 'monthly') {
            $count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $eventsTable 
                 WHERE user_id = %d AND event_type = %s 
                 AND DATE_FORMAT(created_at, '%%Y-%%m') = %s",
                $userId, $event, $periodKey
            ));
        } elseif ($period === 'weekly') {
            // Extract year and week from periodKey (format: "2026-W01")
            $weekParts = explode('-W', $periodKey);
            if (count($weekParts) === 2) {
                $count = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $eventsTable 
                     WHERE user_id = %d AND event_type = %s 
                     AND YEAR(created_at) = %d AND WEEK(created_at, 3) = %d",
                    $userId, $event, (int)$weekParts[0], (int)$weekParts[1]
                ));
            } else {
                $count = 0;
            }
        } else {
            // Daily
            $count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $eventsTable 
                 WHERE user_id = %d AND event_type = %s 
                 AND DATE(created_at) = %s",
                $userId, $event, $periodKey
            ));
        }
        
        return [
            'passed' => $count >= $target,
            'progress' => min($count, $target),
            'max' => $target
        ];
    }
    
    /**
     * Evaluate COMEBACK condition
     */
    private function evaluateComeback(int $userId, array $conditions): array {
        global $wpdb;
        $eventsTable = $wpdb->prefix . 'rejimde_events';
        
        $minGapDays = $conditions['min_gap_days'] ?? 7;
        $activeDaysAfter = $conditions['active_days_after'] ?? 3;
        
        // Find last gap of 7+ days
        $recentLogins = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE(created_at) as login_date 
             FROM $eventsTable 
             WHERE user_id = %d AND event_type = 'login_success' 
             ORDER BY login_date DESC 
             LIMIT 30",
            $userId
        ));
        
        if (count($recentLogins) < $activeDaysAfter) {
            return ['passed' => false, 'progress' => 0, 'max' => $activeDaysAfter];
        }
        
        // Check for gap
        $hadGap = false;
        $consecutiveAfterGap = 0;
        
        for ($i = 0; $i < count($recentLogins) - 1; $i++) {
            $current = new \DateTime($recentLogins[$i]);
            $next = new \DateTime($recentLogins[$i + 1]);
            $diff = $current->diff($next)->days;
            
            if (!$hadGap && $diff >= $minGapDays) {
                $hadGap = true;
                $consecutiveAfterGap = $i + 1;
                break;
            }
        }
        
        return [
            'passed' => $hadGap && $consecutiveAfterGap >= $activeDaysAfter,
            'progress' => $hadGap ? min($consecutiveAfterGap, $activeDaysAfter) : 0,
            'max' => $activeDaysAfter
        ];
    }
    
    /**
     * Evaluate CIRCLE_CONTRIBUTION condition
     */
    private function evaluateCircleContribution(int $userId, array $conditions): array {
        global $wpdb;
        $contributionsTable = $wpdb->prefix . 'rejimde_circle_task_contributions';
        $circleTasksTable = $wpdb->prefix . 'rejimde_circle_tasks';
        
        $minPercent = $conditions['min_contribution_percent'] ?? 10;
        $uniqueTasks = $conditions['unique_tasks'] ?? 3;
        
        // Find tasks where user contributed at least X%
        $sql = "SELECT COUNT(DISTINCT c.circle_task_id) as count
                FROM $contributionsTable c
                INNER JOIN $circleTasksTable ct ON c.circle_task_id = ct.id
                WHERE c.user_id = %d
                AND (SELECT SUM(contribution_value) 
                     FROM $contributionsTable 
                     WHERE circle_task_id = c.circle_task_id AND user_id = c.user_id) 
                    >= (ct.target_value * %d / 100)";
        
        $count = (int)$wpdb->get_var($wpdb->prepare($sql, $userId, $minPercent));
        
        return [
            'passed' => $count >= $uniqueTasks,
            'progress' => min($count, $uniqueTasks),
            'max' => $uniqueTasks
        ];
    }
    
    /**
     * Evaluate COUNT_UNIQUE_USERS condition
     */
    private function evaluateCountUniqueUsers(int $userId, array $conditions): array {
        global $wpdb;
        $eventsTable = $wpdb->prefix . 'rejimde_events';
        
        $events = $conditions['events'] ?? [];
        
        if (empty($events)) {
            return ['passed' => false, 'progress' => 0, 'max' => 1];
        }
        
        // Build placeholders for events
        $placeholders = implode(',', array_fill(0, count($events), '%s'));
        $params = array_merge([$userId], $events);
        
        $count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT JSON_EXTRACT(context, '$.target_user_id')) 
             FROM $eventsTable 
             WHERE user_id = %d 
             AND event_type IN ($placeholders)
             AND JSON_EXTRACT(context, '$.target_user_id') IS NOT NULL",
            ...$params
        ));
        
        return [
            'passed' => true, // Progressive
            'progress' => $count,
            'max' => $count
        ];
    }
    
    /**
     * Evaluate CIRCLE_HERO condition
     */
    private function evaluateCircleHero(int $userId, array $conditions): array {
        global $wpdb;
        $contributionsTable = $wpdb->prefix . 'rejimde_circle_task_contributions';
        $circleTasksTable = $wpdb->prefix . 'rejimde_circle_tasks';
        
        $minPercent = $conditions['min_contribution_percent'] ?? 20;
        $windowHours = $conditions['completion_window_hours'] ?? 24;
        
        // Find tasks completed within window where user contributed X%
        $sql = "SELECT COUNT(*) FROM $circleTasksTable ct
                WHERE ct.status = 'completed'
                AND ct.completed_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
                AND EXISTS (
                    SELECT 1 FROM $contributionsTable c
                    WHERE c.circle_task_id = ct.id
                    AND c.user_id = %d
                    AND c.contribution_date >= DATE_SUB(ct.completed_at, INTERVAL %d HOUR)
                    HAVING SUM(c.contribution_value) >= (ct.target_value * %d / 100)
                )";
        
        $count = (int)$wpdb->get_var($wpdb->prepare(
            $sql, 
            $windowHours * 2, // Look back double the window
            $userId, 
            $windowHours,
            $minPercent
        ));
        
        return [
            'passed' => $count >= 1,
            'progress' => min($count, 1),
            'max' => 1
        ];
    }
}
