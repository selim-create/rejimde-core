<?php
namespace Rejimde\Api\V1;

use Rejimde\Api\BaseController;
use Rejimde\Services\BadgeService;
use WP_REST_Request;

/**
 * Badge API Controller
 */
class BadgeController extends BaseController {
    
    protected $base = 'badges';
    private $badgeService;
    
    public function __construct() {
        $this->badgeService = new BadgeService();
    }
    
    public function register_routes() {
        // Get all badge definitions
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_badges'],
            'permission_callback' => '__return_true'
        ]);
        
        // Get user's badge progress (requires auth)
        register_rest_route($this->namespace, '/' . $this->base . '/me', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_badges'],
            'permission_callback' => [$this, 'check_auth']
        ]);
        
        // Get specific badge details
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<slug>[a-z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_badge'],
            'permission_callback' => '__return_true',
            'args' => [
                'slug' => [
                    'required' => true,
                    'type' => 'string'
                ]
            ]
        ]);
    }
    
    /**
     * Get all badge definitions
     */
    public function get_badges(WP_REST_Request $request) {
        $badges = $this->badgeService->getAllBadges();
        
        // Format for public view (no conditions exposed)
        $formatted = [];
        foreach ($badges as $badge) {
            $formatted[] = [
                'slug' => $badge['slug'],
                'title' => $badge['title'],
                'description' => $badge['description'],
                'icon' => $badge['icon'],
                'category' => $badge['category'],
                'tier' => $badge['tier'],
                'max_progress' => (int)$badge['max_progress']
            ];
        }
        
        return $this->success($formatted);
    }
    
    /**
     * Get user's badge progress
     */
    public function get_my_badges(WP_REST_Request $request) {
        try {
            $userId = get_current_user_id();
            
            if (!$userId) {
                return $this->error('User not authenticated', 401);
            }
            
            $badges = $this->badgeService->getUserBadgeProgress($userId);
            
            // Format badges
            $formatted = [];
            foreach ($badges as $badge) {
                $formatted[] = [
                    'slug' => $badge['slug'],
                    'title' => $badge['title'],
                    'description' => $badge['description'],
                    'icon' => $badge['icon'],
                    'category' => $badge['category'],
                    'tier' => $badge['tier'],
                    'progress' => (int)$badge['current_progress'],
                    'max_progress' => (int)$badge['max_progress'],
                    'percent' => $badge['percent'],
                    'is_earned' => (bool)$badge['is_earned'],
                    'earned_at' => $badge['earned_at']
                ];
            }
            
            // Get badges by category
            $byCategory = [
                'behavior' => [],
                'discipline' => [],
                'social' => [],
                'milestone' => []
            ];
            
            foreach ($formatted as $badge) {
                $category = $badge['category'];
                if (isset($byCategory[$category])) {
                    $byCategory[$category][] = $badge;
                }
            }
            
            // Get recently earned
            $recentlyEarned = $this->badgeService->getRecentlyEarnedBadges($userId, 5);
            $recentFormatted = [];
            foreach ($recentlyEarned as $badge) {
                $recentFormatted[] = [
                    'slug' => $badge['slug'],
                    'title' => $badge['title'],
                    'icon' => $badge['icon'],
                    'tier' => $badge['tier'],
                    'earned_at' => $badge['earned_at']
                ];
            }
            
            // Get stats
            $stats = $this->badgeService->getBadgeStats($userId);
            
            return $this->success([
                'badges' => $formatted,
                'by_category' => $byCategory,
                'recently_earned' => $recentFormatted,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            error_log('BadgeController::get_my_badges error: ' . $e->getMessage());
            return $this->success([
                'badges' => [],
                'by_category' => [
                    'behavior' => [],
                    'discipline' => [],
                    'social' => [],
                    'milestone' => []
                ],
                'recently_earned' => [],
                'stats' => [
                    'total_earned' => 0,
                    'total_available' => 0,
                    'percent_complete' => 0
                ]
            ]);
        }
    }
    
    /**
     * Get specific badge details
     */
    public function get_badge(WP_REST_Request $request) {
        $slug = $request->get_param('slug');
        $badge = $this->badgeService->getBadgeBySlug($slug);
        
        if (!$badge) {
            return $this->error('Badge not found', 404);
        }
        
        $formatted = [
            'slug' => $badge['slug'],
            'title' => $badge['title'],
            'description' => $badge['description'],
            'icon' => $badge['icon'],
            'category' => $badge['category'],
            'tier' => $badge['tier'],
            'max_progress' => (int)$badge['max_progress']
        ];
        
        // Add user progress if authenticated
        $userId = get_current_user_id();
        if ($userId) {
            $userBadges = $this->badgeService->getUserBadgeProgress($userId);
            foreach ($userBadges as $userBadge) {
                if ($userBadge['slug'] === $slug) {
                    $formatted['progress'] = (int)$userBadge['current_progress'];
                    $formatted['percent'] = $userBadge['percent'];
                    $formatted['is_earned'] = (bool)$userBadge['is_earned'];
                    $formatted['earned_at'] = $userBadge['earned_at'];
                    break;
                }
            }
        }
        
        return $this->success($formatted);
    }
}
