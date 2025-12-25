<?php
namespace Rejimde\Core;

use Rejimde\Services\EventService;
use Rejimde\Services\ScoreService;
use Rejimde\Services\StreakService;
use Rejimde\Services\MilestoneService;

/**
 * Event Dispatcher
 * 
 * Central event management system for Rejimde gamification
 */
class EventDispatcher {
    
    private static $instance = null;
    private $listeners = [];
    private $eventService;
    private $scoreService;
    private $streakService;
    private $milestoneService;
    private $config;
    
    private function __construct() {
        $this->eventService = new EventService();
        $this->scoreService = new ScoreService();
        $this->streakService = new StreakService();
        $this->milestoneService = new MilestoneService();
        $this->config = require __DIR__ . '/../Config/ScoringRules.php';
    }
    
    /**
     * Get singleton instance
     * 
     * @return EventDispatcher
     */
    public static function getInstance(): EventDispatcher {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Dispatch an event
     * 
     * @param string $eventType Event type
     * @param array $payload Event payload
     * @return array Response ['success' => bool, 'points' => int, 'message' => string, ...]
     */
    public function dispatch(string $eventType, array $payload): array {
        $userId = $payload['user_id'] ?? get_current_user_id();
        
        if (!$userId) {
            return [
                'success' => false,
                'message' => 'User not authenticated',
                'points_earned' => 0
            ];
        }
        
        // Check if pro user (pro users don't earn points)
        if ($this->scoreService->isProUser($userId)) {
            // Still log the event, but no points
            $this->eventService->log(
                $userId,
                $eventType,
                0,
                $payload['entity_type'] ?? null,
                $payload['entity_id'] ?? null,
                $payload['context'] ?? null
            );
            
            return [
                'success' => true,
                'message' => 'Event logged (Pro users do not earn points)',
                'points_earned' => 0,
                'total_score' => 0,
                'daily_score' => 0
            ];
        }
        
        // Build context
        $context = array_merge($payload['context'] ?? [], [
            'entity_type' => $payload['entity_type'] ?? null,
            'entity_id' => $payload['entity_id'] ?? null
        ]);
        
        // Check if user can earn points
        $canEarn = $this->scoreService->canEarnPoints(
            $userId,
            $eventType,
            $payload['entity_id'] ?? null,
            $context
        );
        
        if (!$canEarn['allowed']) {
            return [
                'success' => false,
                'message' => $canEarn['reason'],
                'points_earned' => 0
            ];
        }
        
        // Calculate points
        $points = $this->scoreService->calculate($eventType, $context);
        
        // Handle special cases for events with multiple recipients
        if ($eventType === 'follow_accepted') {
            return $this->handleFollowAccepted($payload);
        }
        
        // Award points
        $circleId = get_user_meta($userId, 'circle_id', true);
        $scoreResult = $this->scoreService->awardPoints($userId, $points, $circleId ?: null);
        
        // Log event
        $this->eventService->log(
            $userId,
            $eventType,
            $points,
            $payload['entity_type'] ?? null,
            $payload['entity_id'] ?? null,
            $context
        );
        
        // Check streak (for login_success)
        $streakData = null;
        $rule = $this->config[$eventType] ?? [];
        
        if (($rule['requires_streak'] ?? false) && $eventType === 'login_success') {
            $streakResult = $this->streakService->recordActivity($userId, 'daily_login');
            
            if ($streakResult['bonus_points'] > 0) {
                // Award streak bonus
                $bonusResult = $this->scoreService->awardPoints($userId, $streakResult['bonus_points'], $circleId ?: null);
                $scoreResult['total_score'] = $bonusResult['total_score'];
                $scoreResult['daily_score'] = $bonusResult['daily_score'];
                $points += $streakResult['bonus_points'];
                
                // Log streak milestone
                $this->eventService->log(
                    $userId,
                    'streak_milestone',
                    $streakResult['bonus_points'],
                    'streak',
                    $streakResult['current_streak'],
                    ['streak_type' => 'daily_login']
                );
            }
            
            $streakData = [
                'current' => $streakResult['current_streak'],
                'is_milestone' => $streakResult['is_new_milestone'],
                'bonus' => $streakResult['bonus_points']
            ];
        }
        
        // Check milestone (for comment likes)
        $milestoneData = null;
        if ($eventType === 'comment_liked' && isset($payload['comment_id'])) {
            // Get comment author
            $comment = get_comment($payload['comment_id']);
            if ($comment) {
                $commentAuthorId = $comment->user_id;
                
                // Get current like count
                $likeCount = (int) get_comment_meta($payload['comment_id'], 'like_count', true);
                
                // Check milestone
                $milestone = $this->milestoneService->checkAndAward(
                    $commentAuthorId,
                    'comment_likes',
                    $payload['comment_id'],
                    $likeCount
                );
                
                if ($milestone) {
                    // Award milestone points to comment author
                    $milestoneResult = $this->scoreService->awardPoints(
                        $commentAuthorId,
                        $milestone['points'],
                        get_user_meta($commentAuthorId, 'circle_id', true) ?: null
                    );
                    
                    // Log milestone event
                    $this->eventService->log(
                        $commentAuthorId,
                        'comment_like_milestone',
                        $milestone['points'],
                        'comment',
                        $payload['comment_id'],
                        ['like_count' => $likeCount]
                    );
                    
                    $milestoneData = [
                        'type' => 'comment_likes',
                        'value' => $milestone['milestone_value'],
                        'points' => $milestone['points'],
                        'awarded_to' => $commentAuthorId
                    ];
                }
            }
        }
        
        // Call registered listeners
        if (isset($this->listeners[$eventType])) {
            foreach ($this->listeners[$eventType] as $callback) {
                call_user_func($callback, $userId, $payload, $scoreResult);
            }
        }
        
        // Get rule label
        $label = $rule['label'] ?? $eventType;
        
        return [
            'success' => true,
            'event_type' => $eventType,
            'points_earned' => $points,
            'total_score' => $scoreResult['total_score'],
            'daily_score' => $scoreResult['daily_score'],
            'streak' => $streakData,
            'milestone' => $milestoneData,
            'message' => $label . ' tamamlandı!' . ($points > 0 ? " +{$points} puan kazandın." : '')
        ];
    }
    
    /**
     * Handle follow_accepted event (both users get points)
     * 
     * @param array $payload
     * @return array
     */
    private function handleFollowAccepted(array $payload): array {
        $followerId = $payload['follower_id'] ?? null;
        $followedId = $payload['followed_id'] ?? null;
        
        if (!$followerId || !$followedId) {
            return [
                'success' => false,
                'message' => 'Missing follower_id or followed_id',
                'points_earned' => 0
            ];
        }
        
        $totalPoints = 0;
        $results = [];
        
        // Award points to follower
        if (!$this->scoreService->isProUser($followerId)) {
            $canEarnFollower = $this->scoreService->canEarnPoints($followerId, 'follow_accepted', $followedId, [
                'entity_type' => 'follow',
                'entity_id' => $followedId,
                'role' => 'follower'
            ]);
            
            if ($canEarnFollower['allowed']) {
                $followerPoints = 1;
                $followerCircle = get_user_meta($followerId, 'circle_id', true);
                $followerResult = $this->scoreService->awardPoints($followerId, $followerPoints, $followerCircle ?: null);
                
                $this->eventService->log($followerId, 'follow_accepted', $followerPoints, 'follow', $followedId, [
                    'role' => 'follower',
                    'target_user_id' => $followedId
                ]);
                
                $totalPoints += $followerPoints;
                $results['follower'] = $followerResult;
            }
        }
        
        // Award points to followed
        if (!$this->scoreService->isProUser($followedId)) {
            $canEarnFollowed = $this->scoreService->canEarnPoints($followedId, 'follow_accepted', $followerId, [
                'entity_type' => 'follow',
                'entity_id' => $followerId,
                'role' => 'followed'
            ]);
            
            if ($canEarnFollowed['allowed']) {
                $followedPoints = 1;
                $followedCircle = get_user_meta($followedId, 'circle_id', true);
                $followedResult = $this->scoreService->awardPoints($followedId, $followedPoints, $followedCircle ?: null);
                
                $this->eventService->log($followedId, 'follow_accepted', $followedPoints, 'follow', $followerId, [
                    'role' => 'followed',
                    'target_user_id' => $followerId
                ]);
                
                $totalPoints += $followedPoints;
                $results['followed'] = $followedResult;
            }
        }
        
        return [
            'success' => true,
            'event_type' => 'follow_accepted',
            'points_earned' => $totalPoints,
            'message' => 'Takip kabul edildi! Her iki taraf da puan kazandı.',
            'results' => $results
        ];
    }
    
    /**
     * Add event listener
     * 
     * @param string $eventType Event type
     * @param callable $callback Callback function
     * @return void
     */
    public function addListener(string $eventType, callable $callback): void {
        if (!isset($this->listeners[$eventType])) {
            $this->listeners[$eventType] = [];
        }
        $this->listeners[$eventType][] = $callback;
    }
}
