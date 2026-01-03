<?php
namespace Rejimde\Services;

/**
 * Badge Management Service
 * 
 * Handles progressive badge system
 */
class BadgeService {
    
    private $ruleEngine;
    
    public function __construct() {
        $this->ruleEngine = new BadgeRuleEngine();
    }
    
    /**
     * Get all badge definitions
     * 
     * @return array Array of badge definitions
     */
    public function getAllBadges(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_badge_definitions';
        
        $results = $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 ORDER BY category, tier, id",
            ARRAY_A
        );
        
        // Decode JSON conditions
        foreach ($results as &$badge) {
            $badge['conditions'] = json_decode($badge['conditions'], true) ?? [];
        }
        
        return $results;
    }
    
    /**
     * Get badge definition by slug
     * 
     * @param string $slug Badge slug
     * @return array|null Badge definition or null
     */
    public function getBadgeBySlug(string $slug): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_badge_definitions';
        
        $badge = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE slug = %s", $slug),
            ARRAY_A
        );
        
        if ($badge) {
            $badge['conditions'] = json_decode($badge['conditions'], true) ?? [];
        }
        
        return $badge;
    }
    
    /**
     * Get user's badge progress
     * 
     * @param int $userId User ID
     * @return array Array of badges with progress
     */
    public function getUserBadgeProgress(int $userId): array {
        global $wpdb;
        $badgeDefsTable = $wpdb->prefix . 'rejimde_badge_definitions';
        $userBadgesTable = $wpdb->prefix . 'rejimde_user_badges';
        
        $sql = "SELECT 
                    bd.*,
                    ub.current_progress,
                    ub.is_earned,
                    ub.earned_at
                FROM $badgeDefsTable bd
                LEFT JOIN $userBadgesTable ub ON bd.id = ub.badge_definition_id AND ub.user_id = %d
                WHERE bd.is_active = 1
                ORDER BY bd.category, bd.tier, bd.id";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $userId),
            ARRAY_A
        );
        
        // Process each badge
        foreach ($results as &$badge) {
            $badge['conditions'] = json_decode($badge['conditions'], true) ?? [];
            $badge['current_progress'] = (int)($badge['current_progress'] ?? 0);
            $badge['is_earned'] = (bool)($badge['is_earned'] ?? false);
            $badge['percent'] = $badge['max_progress'] > 0 
                ? round(($badge['current_progress'] / $badge['max_progress']) * 100, 1) 
                : 0;
        }
        
        return $results;
    }
    
    /**
     * Update badge progress based on event
     * 
     * @param int $userId User ID
     * @param string $eventType Event type
     * @param array $context Event context
     * @return array|null Newly earned badge or null
     */
    public function processEvent(int $userId, string $eventType, array $context = []): ?array {
        // Get all active badges
        $badges = $this->getAllBadges();
        $newlyEarned = null;
        
        foreach ($badges as $badge) {
            // Check if event matches badge rules
            if (!$this->ruleEngine->eventMatchesRules($eventType, $badge['conditions'])) {
                continue;
            }
            
            // Get or create user badge record
            $userBadge = $this->getOrCreateUserBadge($userId, $badge['id']);
            
            if ($userBadge['is_earned']) {
                continue; // Already earned
            }
            
            // Calculate new progress
            $progress = $this->ruleEngine->calculateProgress($userId, $badge['conditions']);
            
            // Update progress
            $this->updateBadgeProgress($userId, $badge['id'], $progress);
            
            // Check if badge earned
            if ($progress >= $badge['max_progress']) {
                $earned = $this->checkAndAwardBadge($userId, $badge['id']);
                if ($earned && !$newlyEarned) {
                    $newlyEarned = array_merge($badge, ['earned_at' => current_time('mysql')]);
                }
            }
        }
        
        return $newlyEarned;
    }
    
    /**
     * Get or create user badge record
     * 
     * @param int $userId User ID
     * @param int $badgeId Badge definition ID
     * @return array User badge record
     */
    private function getOrCreateUserBadge(int $userId, int $badgeId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_badges';
        
        $userBadge = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND badge_definition_id = %d",
            $userId, $badgeId
        ), ARRAY_A);
        
        if ($userBadge) {
            return $userBadge;
        }
        
        // Create new record
        $wpdb->insert($table, [
            'user_id' => $userId,
            'badge_definition_id' => $badgeId,
            'current_progress' => 0,
            'is_earned' => 0
        ]);
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $wpdb->insert_id
        ), ARRAY_A);
    }
    
    /**
     * Update badge progress
     * 
     * @param int $userId User ID
     * @param int $badgeId Badge definition ID
     * @param int $progress New progress value
     * @return bool Success
     */
    private function updateBadgeProgress(int $userId, int $badgeId, int $progress): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_badges';
        
        // Get or create user badge
        $userBadge = $this->getOrCreateUserBadge($userId, $badgeId);
        
        // Only update if progress increased
        if ($progress <= (int)$userBadge['current_progress']) {
            return false;
        }
        
        return $wpdb->update(
            $table,
            ['current_progress' => $progress],
            [
                'user_id' => $userId,
                'badge_definition_id' => $badgeId
            ],
            ['%d'],
            ['%d', '%d']
        ) !== false;
    }
    
    /**
     * Check and award badge if conditions met
     * 
     * @param int $userId User ID
     * @param int $badgeId Badge definition ID
     * @return bool True if badge was awarded
     */
    public function checkAndAwardBadge(int $userId, int $badgeId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_badges';
        
        // Get user badge
        $userBadge = $this->getOrCreateUserBadge($userId, $badgeId);
        
        if ($userBadge['is_earned']) {
            return false; // Already earned
        }
        
        // Get badge definition
        $badgeDefsTable = $wpdb->prefix . 'rejimde_badge_definitions';
        $badge = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $badgeDefsTable WHERE id = %d",
            $badgeId
        ), ARRAY_A);
        
        if (!$badge) {
            return false;
        }
        
        // Check if progress meets requirement
        if ((int)$userBadge['current_progress'] >= (int)$badge['max_progress']) {
            // Award badge
            $wpdb->update(
                $table,
                [
                    'is_earned' => 1,
                    'earned_at' => current_time('mysql')
                ],
                [
                    'user_id' => $userId,
                    'badge_definition_id' => $badgeId
                ],
                ['%d', '%s'],
                ['%d', '%d']
            );
            
            // Dispatch badge earned event
            \Rejimde\Core\EventDispatcher::getInstance()->dispatch('badge_earned', [
                'user_id' => $userId,
                'badge_id' => $badgeId,
                'context' => [
                    'badge_slug' => $badge['slug'],
                    'badge_title' => $badge['title'],
                    'badge_tier' => $badge['tier'],
                    'badge_category' => $badge['category']
                ]
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get recently earned badges
     * 
     * @param int $userId User ID
     * @param int $limit Number of badges to return
     * @return array Array of recently earned badges
     */
    public function getRecentlyEarnedBadges(int $userId, int $limit = 5): array {
        global $wpdb;
        $badgeDefsTable = $wpdb->prefix . 'rejimde_badge_definitions';
        $userBadgesTable = $wpdb->prefix . 'rejimde_user_badges';
        
        $sql = "SELECT 
                    bd.*,
                    ub.earned_at
                FROM $userBadgesTable ub
                INNER JOIN $badgeDefsTable bd ON ub.badge_definition_id = bd.id
                WHERE ub.user_id = %d
                AND ub.is_earned = 1
                ORDER BY ub.earned_at DESC
                LIMIT %d";
        
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $userId, $limit),
            ARRAY_A
        );
        
        // Decode JSON conditions
        foreach ($results as &$badge) {
            $badge['conditions'] = json_decode($badge['conditions'], true) ?? [];
        }
        
        return $results;
    }
    
    /**
     * Get badges organized by category
     * 
     * @param int $userId User ID
     * @return array Badges grouped by category
     */
    public function getBadgesByCategory(int $userId): array {
        $badges = $this->getUserBadgeProgress($userId);
        
        $byCategory = [
            'behavior' => [],
            'discipline' => [],
            'social' => [],
            'milestone' => []
        ];
        
        foreach ($badges as $badge) {
            $category = $badge['category'] ?? 'behavior';
            if (isset($byCategory[$category])) {
                $byCategory[$category][] = $badge;
            }
        }
        
        return $byCategory;
    }
    
    /**
     * Get badge statistics for user
     * 
     * @param int $userId User ID
     * @return array Statistics
     */
    public function getBadgeStats(int $userId): array {
        $badges = $this->getUserBadgeProgress($userId);
        
        $totalAvailable = count($badges);
        $totalEarned = 0;
        
        foreach ($badges as $badge) {
            if ($badge['is_earned']) {
                $totalEarned++;
            }
        }
        
        return [
            'total_earned' => $totalEarned,
            'total_available' => $totalAvailable,
            'percent_complete' => $totalAvailable > 0 
                ? round(($totalEarned / $totalAvailable) * 100, 1) 
                : 0
        ];
    }
}
