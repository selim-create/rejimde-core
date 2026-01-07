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
     * Get all badge definitions (Config + PostType merged)
     * 
     * @return array Array of badge definitions
     */
    public function getAllBadges(): array {
        // 1. Config'den rozetleri al
        $configBadges = require REJIMDE_PATH . 'includes/Config/BadgeRules.php';
        
        // 2. PostType'dan rozetleri al
        $postBadges = get_posts([
            'post_type' => 'rejimde_badge',
            'post_status' => 'publish',
            'numberposts' => -1
        ]);
        
        $allBadges = [];
        
        // Config badges
        foreach ($configBadges as $slug => $badge) {
            $badge['slug'] = $slug;
            $badge['source'] = 'config';
            $badge['id'] = 'config_' . $slug;
            $allBadges[$slug] = $badge;
        }
        
        // PostType badges (can override config with same slug)
        foreach ($postBadges as $post) {
            $slug = $post->post_name;
            $badge = [
                'id' => $post->ID,
                'slug' => $slug,
                'title' => $post->post_title,
                'description' => $post->post_content,
                'icon' => get_post_meta($post->ID, 'badge_icon', true) ?: '🏅',
                'category' => get_post_meta($post->ID, 'badge_category', true) ?: 'milestone',
                'tier' => get_post_meta($post->ID, 'badge_tier', true) ?: 'bronze',
                'max_progress' => (int) (get_post_meta($post->ID, 'max_progress', true) ?: 1),
                'conditions' => get_post_meta($post->ID, 'badge_conditions', true) ?: [],
                'source' => 'post_type'
            ];
            $allBadges[$slug] = $badge;
        }
        
        return $allBadges;
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
        
        // Check if tables exist
        $defs_table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $badgeDefsTable
        ));
        
        if (!$defs_table_exists) {
            return [];
        }
        
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
     * Get badge ID by slug
     * 
     * @param string $slug Badge slug
     * @return int|null Badge ID or null
     */
    private function getBadgeIdBySlug(string $slug): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_badge_definitions';
        
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE slug = %s",
            $slug
        ));
        
        return $id ? (int) $id : null;
    }
    
    /**
     * Get or create user badge record
     * 
     * @param int $userId User ID
     * @param int|string $badgeId Badge definition ID or slug
     * @return array User badge record
     */
    private function getOrCreateUserBadge(int $userId, int|string $badgeId): array {
        // Convert string badge slug to ID if needed
        if (is_string($badgeId)) {
            // Handle config badges (format: 'config_slug')
            $slug = $badgeId;
            if (strpos($badgeId, 'config_') === 0) {
                $slug = substr($badgeId, 7); // Remove 'config_' prefix
            }
            
            $badgeId = $this->getBadgeIdBySlug($slug);
            if (!$badgeId) {
                // Badge not found in database, return empty array
                return [
                    'user_id' => $userId,
                    'badge_definition_id' => 0,
                    'current_progress' => 0,
                    'is_earned' => 0
                ];
            }
        }
        
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
     * @param int|string $badgeId Badge definition ID or slug
     * @param int $progress New progress value
     * @return bool Success
     */
    private function updateBadgeProgress(int $userId, int|string $badgeId, int $progress): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_badges';
        
        // Get or create user badge (this handles string->int conversion)
        $userBadge = $this->getOrCreateUserBadge($userId, $badgeId);
        
        // If badge not found, return false
        if (!isset($userBadge['badge_definition_id']) || $userBadge['badge_definition_id'] == 0) {
            return false;
        }
        
        // Use the actual badge_definition_id from the user badge record
        $actualBadgeId = (int) $userBadge['badge_definition_id'];
        
        // Only update if progress increased
        if ($progress <= (int)$userBadge['current_progress']) {
            return false;
        }
        
        return $wpdb->update(
            $table,
            ['current_progress' => $progress],
            [
                'user_id' => $userId,
                'badge_definition_id' => $actualBadgeId
            ],
            ['%d'],
            ['%d', '%d']
        ) !== false;
    }
    
    /**
     * Check and award badge if conditions met
     * 
     * @param int $userId User ID
     * @param int|string $badgeId Badge definition ID or slug
     * @return bool True if badge was awarded
     */
    public function checkAndAwardBadge(int $userId, int|string $badgeId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_badges';
        
        // Get user badge (handles string->int conversion)
        $userBadge = $this->getOrCreateUserBadge($userId, $badgeId);
        
        // If badge not found, return false
        if (!isset($userBadge['badge_definition_id']) || $userBadge['badge_definition_id'] == 0) {
            return false;
        }
        
        // Use the actual badge_definition_id from the user badge record
        $actualBadgeId = (int) $userBadge['badge_definition_id'];
        
        if ($userBadge['is_earned']) {
            return false; // Already earned
        }
        
        // Get badge definition
        $badgeDefsTable = $wpdb->prefix . 'rejimde_badge_definitions';
        $badge = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $badgeDefsTable WHERE id = %d",
            $actualBadgeId
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
                    'badge_definition_id' => $actualBadgeId
                ],
                ['%d', '%s'],
                ['%d', '%d']
            );
            
            // Dispatch badge earned event
            \Rejimde\Core\EventDispatcher::getInstance()->dispatch('badge_earned', [
                'user_id' => $userId,
                'badge_id' => $actualBadgeId,
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
