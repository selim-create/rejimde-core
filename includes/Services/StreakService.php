<?php
namespace Rejimde\Services;

/**
 * Streak Management Service
 * 
 * Handles user streaks (daily login, etc.) with grace period support
 */
class StreakService {
    
    private $streakBonuses;
    
    public function __construct() {
        $config = require __DIR__ . '/../Config/ScoringRules.php';
        $this->streakBonuses = $config['streak_bonuses'] ?? [];
    }
    
    /**
     * Record an activity and update streak
     * 
     * @param int $userId User ID
     * @param string $streakType Streak type (default: 'daily_login')
     * @return array ['current_streak' => int, 'is_new_milestone' => bool, 'bonus_points' => int]
     */
    public function recordActivity(int $userId, string $streakType = 'daily_login'): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_streaks';
        $today = date('Y-m-d');
        
        // Get or create streak record
        $streak = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND streak_type = %s",
            $userId, $streakType
        ));
        
        if (!$streak) {
            // Create new streak
            $wpdb->insert($table, [
                'user_id' => $userId,
                'streak_type' => $streakType,
                'current_count' => 1,
                'longest_count' => 1,
                'last_activity_date' => $today,
                'grace_used_this_week' => 0
            ]);
            
            return [
                'current_streak' => 1,
                'is_new_milestone' => false,
                'bonus_points' => 0
            ];
        }
        
        // Check if already recorded today
        if ($streak->last_activity_date === $today) {
            return [
                'current_streak' => (int) $streak->current_count,
                'is_new_milestone' => false,
                'bonus_points' => 0
            ];
        }
        
        $lastDate = new \DateTime($streak->last_activity_date);
        $currentDate = new \DateTime($today);
        $daysDiff = $lastDate->diff($currentDate)->days;
        
        $newCount = $streak->current_count;
        $graceUsed = $streak->grace_used_this_week;
        
        if ($daysDiff === 1) {
            // Consecutive day
            $newCount++;
        } elseif ($daysDiff === 2 && $graceUsed < 2) {
            // Missed one day, use grace
            $newCount++;
            $graceUsed++;
        } else {
            // Streak broken
            $newCount = 1;
        }
        
        // Update longest count
        $longestCount = max($streak->longest_count, $newCount);
        
        // Check for milestone
        $bonusPoints = $this->checkStreakMilestone($newCount);
        $isNewMilestone = $bonusPoints > 0;
        
        // Update streak
        $wpdb->update($table, [
            'current_count' => $newCount,
            'longest_count' => $longestCount,
            'last_activity_date' => $today,
            'grace_used_this_week' => $graceUsed
        ], [
            'id' => $streak->id
        ]);
        
        return [
            'current_streak' => $newCount,
            'is_new_milestone' => $isNewMilestone,
            'bonus_points' => $bonusPoints
        ];
    }
    
    /**
     * Get user's current streak
     * 
     * @param int $userId User ID
     * @param string $streakType Streak type
     * @return array
     */
    public function getStreak(int $userId, string $streakType = 'daily_login'): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_streaks';
        
        $streak = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND streak_type = %s",
            $userId, $streakType
        ), ARRAY_A);
        
        if (!$streak) {
            return [
                'current_count' => 0,
                'longest_count' => 0,
                'last_activity_date' => null,
                'grace_remaining' => 2
            ];
        }
        
        return [
            'current_count' => (int) $streak['current_count'],
            'longest_count' => (int) $streak['longest_count'],
            'last_activity_date' => $streak['last_activity_date'],
            'grace_remaining' => 2 - (int) $streak['grace_used_this_week']
        ];
    }
    
    /**
     * Check if current streak count is a milestone
     * 
     * @param int $currentStreak Current streak count
     * @return int Bonus points (0 if not a milestone)
     */
    public function checkStreakMilestone(int $currentStreak): ?int {
        return $this->streakBonuses[$currentStreak] ?? null;
    }
    
    /**
     * Reset weekly grace for all users
     * Should be called by cron weekly
     * 
     * @return void
     */
    public function resetWeeklyGrace(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_streaks';
        
        $wpdb->query("UPDATE $table SET grace_used_this_week = 0");
    }
}
