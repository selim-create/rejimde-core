<?php
namespace Rejimde\Core;

use Rejimde\Services\EventService;
use Rejimde\Services\ScoreService;
use Rejimde\Services\StreakService;
use Rejimde\Services\MilestoneService;
use Rejimde\Services\NotificationService;
use Rejimde\Services\TaskProgressService;
use Rejimde\Services\BadgeService;
use Rejimde\Services\CircleTaskService;

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
    private $notificationService;
    private $taskProgressService;
    private $badgeService;
    private $circleTaskService;
    private $config;
    
    private function __construct() {
        $this->eventService = new EventService();
        $this->scoreService = new ScoreService();
        $this->streakService = new StreakService();
        $this->milestoneService = new MilestoneService();
        $this->notificationService = new NotificationService();
        $this->taskProgressService = new TaskProgressService();
        $this->badgeService = new BadgeService();
        $this->circleTaskService = new CircleTaskService();
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
            // Determine if this is a "soft" failure (user-level, not an error) or a hard error
            $softFailureReasons = [
                \Rejimde\Services\ScoreService::REASON_ALREADY_EARNED,
                \Rejimde\Services\ScoreService::REASON_DAILY_LIMIT,
                \Rejimde\Services\ScoreService::REASON_ALREADY_SENT_TODAY,
                \Rejimde\Services\ScoreService::REASON_DAILY_CAP
            ];
            
            $isSoftFailure = in_array($canEarn['reason'], $softFailureReasons);
            
            if ($isSoftFailure) {
                // Still log the event for tracking
                $this->eventService->log(
                    $userId,
                    $eventType,
                    0,
                    $payload['entity_type'] ?? null,
                    $payload['entity_id'] ?? null,
                    $context
                );
                
                // Return success with appropriate flag
                $responseData = [
                    'success' => true,
                    'event_type' => $eventType,
                    'points_earned' => 0,
                    'total_score' => (int) get_user_meta($userId, 'rejimde_total_score', true),
                    'daily_score' => $this->scoreService->getDailyScore($userId),
                ];
                
                // Set specific flags based on reason
                if ($canEarn['reason'] === \Rejimde\Services\ScoreService::REASON_ALREADY_EARNED) {
                    $responseData['already_earned'] = true;
                    $responseData['message'] = 'Bu içerik için zaten puan kazandınız.';
                } elseif ($canEarn['reason'] === \Rejimde\Services\ScoreService::REASON_DAILY_LIMIT) {
                    $responseData['daily_limit_reached'] = true;
                    $responseData['message'] = 'Günlük limit doldu.';
                } elseif ($canEarn['reason'] === \Rejimde\Services\ScoreService::REASON_ALREADY_SENT_TODAY) {
                    $responseData['already_sent_today'] = true;
                    $responseData['message'] = 'Bu kullanıcıya bugün zaten gönderdin.';
                } elseif ($canEarn['reason'] === \Rejimde\Services\ScoreService::REASON_DAILY_CAP) {
                    $responseData['daily_cap_reached'] = true;
                    $responseData['message'] = 'Günlük puan limitine ulaştın.';
                }
                
                return $responseData;
            }
            
            // Hard failures (actual errors)
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
        
        // Process task progress (after points awarded)
        // Note: This adds ~3-5 database queries per event. For high-traffic scenarios,
        // consider implementing async processing via Action Scheduler or similar.
        $taskData = null;
        if ($points > 0 || in_array($eventType, ['login_success', 'exercise_completed', 'diet_completed'])) {
            $updatedTasks = $this->taskProgressService->processEvent($userId, $eventType, $context);
            if (!empty($updatedTasks)) {
                $taskData = [
                    'tasks_updated' => count($updatedTasks),
                    'tasks' => $updatedTasks
                ];
            }
        }
        
        // Process circle task contributions
        // Note: Only triggered for specific event types to minimize performance impact
        $circleTaskData = null;
        $circleId = get_user_meta($userId, 'circle_id', true);
        if ($circleId && in_array($eventType, ['exercise_completed', 'steps_logged'])) {
            // Get active circle tasks for this event type
            global $wpdb;
            $taskDefsTable = $wpdb->prefix . 'rejimde_task_definitions';
            $circleTasks = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM $taskDefsTable 
                 WHERE task_type = 'circle' 
                 AND is_active = 1
                 AND JSON_CONTAINS(scoring_event_types, %s)",
                json_encode($eventType)
            ), ARRAY_A);
            
            foreach ($circleTasks as $taskDef) {
                $this->circleTaskService->addContribution($circleId, $userId, $taskDef['id'], 1);
            }
        }
        
        // Process badge progress (after points and tasks)
        // Note: Badge evaluation includes complex rule engine queries.
        // Results are cached in user_badges table to minimize repeated evaluations.
        $badgeData = null;
        $newBadge = $this->badgeService->processEvent($userId, $eventType, $context);
        if ($newBadge) {
            $badgeData = [
                'badge_earned' => true,
                'badge' => $newBadge
            ];
        }
        
        // Get rule label
        $label = $rule['label'] ?? $eventType;
        
        // Build result
        $result = [
            'success' => true,
            'event_type' => $eventType,
            'points_earned' => $points,
            'total_score' => $scoreResult['total_score'],
            'daily_score' => $scoreResult['daily_score'],
            'streak' => $streakData,
            'milestone' => $milestoneData,
            'tasks' => $taskData,
            'badge' => $badgeData,
            'message' => $label . ' tamamlandı!' . ($points > 0 ? " +{$points} puan kazandın." : '')
        ];
        
        // Trigger notifications
        $this->triggerNotifications($eventType, $payload, $result);
        
        return $result;
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
        
        $result = [
            'success' => true,
            'event_type' => 'follow_accepted',
            'points_earned' => $totalPoints,
            'message' => 'Takip kabul edildi! Her iki taraf da puan kazandı.',
            'results' => $results
        ];
        
        // Trigger notifications for both users
        $this->triggerNotifications('follow_accepted', $payload, $result);
        
        return $result;
    }
    
    /**
     * Trigger notifications based on event type
     * 
     * @param string $eventType Event type
     * @param array $payload Event payload
     * @param array $result Dispatch result
     * @return void
     */
    private function triggerNotifications(string $eventType, array $payload, array $result): void {
        $userId = $payload['user_id'] ?? get_current_user_id();
        
        // Map events to notification types
        switch ($eventType) {
            case 'login_success':
                // Streak notifications
                if (isset($result['streak'])) {
                    $streak = $result['streak'];
                    
                    if ($streak['is_milestone'] && $streak['bonus'] > 0) {
                        // Streak milestone notification
                        $this->notificationService->create($userId, 'streak_milestone', [
                            'streak_count' => $streak['current'],
                            'bonus_points' => $streak['bonus'],
                            'entity_type' => 'streak',
                            'entity_id' => $streak['current']
                        ]);
                    } elseif ($streak['current'] > 1) {
                        // Streak continued notification
                        $this->notificationService->create($userId, 'streak_continued', [
                            'streak_count' => $streak['current'],
                            'entity_type' => 'streak',
                            'entity_id' => $streak['current']
                        ]);
                    }
                }
                break;
                
            case 'follow_accepted':
                // Notifications for both follower and followed
                $followerId = $payload['follower_id'] ?? null;
                $followedId = $payload['followed_id'] ?? null;
                
                if ($followerId && $followedId) {
                    // Notify follower that their follow was accepted
                    $this->notificationService->create($followerId, 'follow_accepted', [
                        'actor_id' => $followedId,
                        'entity_type' => 'follow',
                        'entity_id' => $followedId
                    ]);
                    
                    // Notify followed user about new follower
                    $this->notificationService->create($followedId, 'new_follower', [
                        'actor_id' => $followerId,
                        'entity_type' => 'follow',
                        'entity_id' => $followerId
                    ]);
                }
                break;
                
            case 'highfive_sent':
                // Notify the recipient
                if (isset($payload['target_user_id'])) {
                    $this->notificationService->create($payload['target_user_id'], 'highfive_received', [
                        'actor_id' => $userId,
                        'entity_type' => 'highfive',
                        'entity_id' => $userId
                    ]);
                }
                break;
                
            case 'comment_created':
                // Check if it's a reply
                if (isset($payload['parent_comment_id']) && $payload['parent_comment_id']) {
                    $parentComment = get_comment($payload['parent_comment_id']);
                    if ($parentComment && (int) $parentComment->user_id != $userId) {
                        // Get content information for the notification URL
                        // Use entity_id if explicitly provided, otherwise use the comment's post ID
                        $postId = isset($payload['entity_id']) ? $payload['entity_id'] : $parentComment->comment_post_ID;
                        $post = get_post($postId);
                        $contentInfo = $this->getContentTypeAndSlug($post);
                        
                        $this->notificationService->create((int) $parentComment->user_id, 'comment_reply', [
                            'actor_id' => $userId,
                            'entity_type' => $payload['entity_type'] ?? 'comment',
                            'entity_id' => $payload['entity_id'] ?? null,
                            'comment_id' => $payload['comment_id'] ?? null,
                            'content_type' => $contentInfo['content_type'],
                            'content_slug' => $contentInfo['content_slug']
                        ]);
                    }
                } else {
                    // Not a reply - check if it's a comment on user's own content
                    $postId = $payload['entity_id'] ?? null;
                    if ($postId !== null) {
                        $post = get_post($postId);
                        if ($post && (int) $post->post_author != $userId) {
                            $contentInfo = $this->getContentTypeAndSlug($post);
                            $contentName = $post->post_title;
                            
                            // Notify content owner about new comment
                            $this->notificationService->create((int) $post->post_author, 'comment_on_content', [
                                'actor_id' => $userId,
                                'entity_type' => $payload['entity_type'] ?? 'comment',
                                'entity_id' => $postId,
                                'comment_id' => $payload['comment_id'] ?? null,
                                'content_type' => $contentInfo['content_type'],
                                'content_slug' => $contentInfo['content_slug'],
                                'content_name' => $contentName
                            ]);
                        }
                    }
                }
                break;
                
            case 'comment_like_milestone':
                // Notify comment author about milestone
                if (isset($result['milestone'])) {
                    $milestone = $result['milestone'];
                    if (isset($milestone['awarded_to'])) {
                        $likeCount = $payload['context']['like_count'] ?? $milestone['value'];
                        $this->notificationService->create($milestone['awarded_to'], 'comment_like_milestone', [
                            'like_count' => $likeCount,
                            'points' => $milestone['points'],
                            'entity_type' => 'comment',
                            'entity_id' => $payload['entity_id'] ?? null
                        ]);
                    }
                }
                break;
                
            case 'comment_liked':
                // Notify comment author about like (not milestone)
                if (isset($payload['comment_id'])) {
                    $comment = get_comment($payload['comment_id']);
                    if ($comment && (int) $comment->user_id != $userId) {
                        // Get content information for the notification URL
                        $postId = $comment->comment_post_ID;
                        $post = get_post($postId);
                        $contentInfo = $this->getContentTypeAndSlug($post);
                        
                        $this->notificationService->create((int) $comment->user_id, 'comment_liked', [
                            'actor_id' => $userId,
                            'entity_type' => 'comment',
                            'entity_id' => $payload['comment_id'],
                            'comment_id' => $payload['comment_id'],
                            'content_type' => $contentInfo['content_type'],
                            'content_slug' => $contentInfo['content_slug']
                        ]);
                    }
                }
                break;
                
            case 'blog_points_claimed':
            case 'diet_completed':
            case 'exercise_completed':
                // Content completion notification
                if ($result['points_earned'] > 0) {
                    $contentName = $payload['context']['content_name'] ?? 'İçerik';
                    
                    // Determine content type label and type from event
                    $contentInfo = $this->getContentTypeFromEvent($eventType);
                    $contentSlug = '';
                    
                    // Get content slug if entity_id is available
                    if (isset($payload['entity_id'])) {
                        $post = get_post($payload['entity_id']);
                        if ($post) {
                            $contentSlug = $post->post_name;
                        }
                    }
                    
                    $this->notificationService->create($userId, 'content_completed', [
                        'content_name' => $contentName,
                        'content_type_label' => $contentInfo['content_type_label'],
                        'content_type' => $contentInfo['content_type'],
                        'content_slug' => $contentSlug,
                        'points' => $result['points_earned'],
                        'entity_type' => $payload['entity_type'] ?? null,
                        'entity_id' => $payload['entity_id'] ?? null
                    ]);
                }
                break;
                
            case 'circle_joined':
                // Notify user about joining
                if (isset($payload['circle_id'])) {
                    $circle = get_post($payload['circle_id']);
                    $circleName = $circle ? $circle->post_title : 'Circle';
                    
                    $this->notificationService->create($userId, 'circle_joined', [
                        'circle_name' => $circleName,
                        'entity_type' => 'circle',
                        'entity_id' => $payload['circle_id']
                    ]);
                    
                    // Notify circle members (optional - could be too spammy)
                    // This could be implemented if needed
                }
                break;
                
            case 'rating_submitted':
                // Notify expert about rating
                if (isset($payload['expert_id'])) {
                    $rating = $payload['context']['rating'] ?? 5;
                    $this->notificationService->create($payload['expert_id'], 'rating_received', [
                        'actor_id' => $userId,
                        'rating' => $rating,
                        'entity_type' => 'rating',
                        'entity_id' => $userId
                    ]);
                }
                break;
        }
    }
    
    /**
     * Get content type and slug from a post
     * 
     * @param \WP_Post|null $post WordPress post object
     * @return array ['content_type' => string, 'content_slug' => string]
     */
    private function getContentTypeAndSlug($post): array {
        $contentType = 'post';
        $contentSlug = '';
        
        if ($post) {
            $contentSlug = $post->post_name;
            
            // Map post types to content types
            if ($post->post_type === 'rejimde_blog') {
                $contentType = 'blog';
            } elseif ($post->post_type === 'rejimde_diet') {
                $contentType = 'diet';
            } elseif ($post->post_type === 'rejimde_exercise') {
                $contentType = 'exercise';
            }
        }
        
        return [
            'content_type' => $contentType,
            'content_slug' => $contentSlug
        ];
    }
    
    /**
     * Get content type label from event type
     * 
     * @param string $eventType Event type
     * @return array ['content_type_label' => string, 'content_type' => string]
     */
    private function getContentTypeFromEvent(string $eventType): array {
        $contentTypeLabel = 'İçerik';
        $contentType = '';
        
        if ($eventType === 'blog_points_claimed') {
            $contentTypeLabel = 'Blog';
            $contentType = 'blog';
        } elseif ($eventType === 'diet_completed') {
            $contentTypeLabel = 'Diyet';
            $contentType = 'diet';
        } elseif ($eventType === 'exercise_completed') {
            $contentTypeLabel = 'Egzersiz';
            $contentType = 'exercise';
        }
        
        return [
            'content_type_label' => $contentTypeLabel,
            'content_type' => $contentType
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
