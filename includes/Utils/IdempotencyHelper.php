<?php
namespace Rejimde\Utils;

/**
 * IdempotencyHelper
 * 
 * Generates unique idempotency keys for different event types
 */
class IdempotencyHelper {
    
    /**
     * Generate idempotency key based on event type and parameters
     * 
     * @param string $event_type Event type
     * @param int $user_id User ID
     * @param array $params Additional parameters
     * @return string Unique idempotency key
     */
    public static function generate($event_type, $user_id, $params = []) {
        $key = '';
        
        switch ($event_type) {
            case 'login_success':
                // user:{u}|day:{YYYY-MM-DD}|login_success
                $date = TimezoneHelper::getTodayTR();
                $key = "user:{$user_id}|day:{$date}|login_success";
                break;
                
            case 'blog_points_claimed':
                // user:{u}|blog:{id}|blog_points_claimed
                $blog_id = isset($params['entity_id']) ? $params['entity_id'] : 0;
                $key = "user:{$user_id}|blog:{$blog_id}|blog_points_claimed";
                break;
                
            case 'diet_started':
                // user:{u}|diet:{id}|diet_started
                $diet_id = isset($params['entity_id']) ? $params['entity_id'] : 0;
                $key = "user:{$user_id}|diet:{$diet_id}|diet_started";
                break;
                
            case 'diet_completed':
                // user:{u}|diet:{id}|diet_completed
                $diet_id = isset($params['entity_id']) ? $params['entity_id'] : 0;
                $key = "user:{$user_id}|diet:{$diet_id}|diet_completed";
                break;
                
            case 'exercise_started':
                // user:{u}|exercise:{id}|exercise_started
                $exercise_id = isset($params['entity_id']) ? $params['entity_id'] : 0;
                $key = "user:{$user_id}|exercise:{$exercise_id}|exercise_started";
                break;
                
            case 'exercise_completed':
                // user:{u}|exercise:{id}|exercise_completed
                $exercise_id = isset($params['entity_id']) ? $params['entity_id'] : 0;
                $key = "user:{$user_id}|exercise:{$exercise_id}|exercise_completed";
                break;
                
            case 'calculator_saved':
                // user:{u}|calc:{type}|calculator_saved
                $calc_type = isset($params['calculator_type']) ? $params['calculator_type'] : 'unknown';
                $key = "user:{$user_id}|calc:{$calc_type}|calculator_saved";
                break;
                
            case 'rating_submitted':
                // user:{u}|target:{t}:{id}|rating_submitted
                $target_type = isset($params['target_type']) ? $params['target_type'] : 'unknown';
                $target_id = isset($params['target_id']) ? $params['target_id'] : 0;
                $key = "user:{$user_id}|target:{$target_type}:{$target_id}|rating_submitted";
                break;
                
            case 'comment_created':
                // comment:{id}|comment_created
                $comment_id = isset($params['comment_id']) ? $params['comment_id'] : 0;
                $key = "comment:{$comment_id}|comment_created";
                break;
                
            case 'comment_liked':
                // liker:{u}|comment:{id}|comment_liked
                $comment_id = isset($params['comment_id']) ? $params['comment_id'] : 0;
                $key = "liker:{$user_id}|comment:{$comment_id}|comment_liked";
                break;
                
            case 'comment_like_milestone_rewarded':
                // comment:{id}|milestone:{n}|milestone_rewarded
                $comment_id = isset($params['comment_id']) ? $params['comment_id'] : 0;
                $milestone = isset($params['milestone']) ? $params['milestone'] : 0;
                $key = "comment:{$comment_id}|milestone:{$milestone}|milestone_rewarded";
                break;
                
            case 'follow_accepted':
                // pair:{min_id}-{max_id}|follow_accepted
                $follower_id = isset($params['follower_id']) ? $params['follower_id'] : $user_id;
                $following_id = isset($params['following_id']) ? $params['following_id'] : 0;
                $min_id = min($follower_id, $following_id);
                $max_id = max($follower_id, $following_id);
                $key = "pair:{$min_id}-{$max_id}|follow_accepted";
                break;
                
            case 'highfive_sent':
                // sender:{a}|recv:{b}|day:{YYYY-MM-DD}|highfive_sent
                $receiver_id = isset($params['receiver_id']) ? $params['receiver_id'] : 0;
                $date = TimezoneHelper::getTodayTR();
                $key = "sender:{$user_id}|recv:{$receiver_id}|day:{$date}|highfive_sent";
                break;
                
            case 'water_added':
                // user:{u}|day:{YYYY-MM-DD}|water_added|bucket:{bucket_id}
                $date = TimezoneHelper::getTodayTR();
                $bucket_id = isset($params['bucket_id']) ? $params['bucket_id'] : uniqid();
                $key = "user:{$user_id}|day:{$date}|water_added|bucket:{$bucket_id}";
                break;
                
            case 'steps_logged':
                // user:{u}|day:{YYYY-MM-DD}|steps_bucket:{bucket_index}
                $date = TimezoneHelper::getTodayTR();
                $bucket_index = isset($params['bucket_index']) ? $params['bucket_index'] : 0;
                $key = "user:{$user_id}|day:{$date}|steps_bucket:{$bucket_index}";
                break;
                
            case 'meal_photo_uploaded':
                // user:{u}|day:{YYYY-MM-DD}|meal_photo:{meal_id}
                $date = TimezoneHelper::getTodayTR();
                $meal_id = isset($params['meal_id']) ? $params['meal_id'] : uniqid();
                $key = "user:{$user_id}|day:{$date}|meal_photo:{$meal_id}";
                break;
                
            case 'circle_joined':
                // user:{u}|circle:{id}|circle_joined
                $circle_id = isset($params['entity_id']) ? $params['entity_id'] : 0;
                $key = "user:{$user_id}|circle:{$circle_id}|circle_joined";
                break;
                
            case 'circle_created':
                // user:{u}|circle:{id}|circle_created
                $circle_id = isset($params['entity_id']) ? $params['entity_id'] : 0;
                $key = "user:{$user_id}|circle:{$circle_id}|circle_created";
                break;
                
            // Weekly and monthly events (system-generated)
            case 'weekly_score_calculated':
                // user:{u}|week:{start}|weekly_score_calculated
                $week_start = isset($params['week_start']) ? $params['week_start'] : TimezoneHelper::getTodayTR();
                $key = "user:{$user_id}|week:{$week_start}|weekly_score_calculated";
                break;
                
            case 'level_week_completed':
                // user:{u}|level:{id}|week:{start}|level_week_completed
                $level_id = isset($params['level_id']) ? $params['level_id'] : 0;
                $week_start = isset($params['week_start']) ? $params['week_start'] : TimezoneHelper::getTodayTR();
                $key = "user:{$user_id}|level:{$level_id}|week:{$week_start}|level_week_completed";
                break;
                
            case 'level_position_rewarded':
                // user:{u}|level:{id}|week:{start}|position:{pos}|rewarded
                $level_id = isset($params['level_id']) ? $params['level_id'] : 0;
                $week_start = isset($params['week_start']) ? $params['week_start'] : TimezoneHelper::getTodayTR();
                $position = isset($params['position']) ? $params['position'] : 0;
                $key = "user:{$user_id}|level:{$level_id}|week:{$week_start}|position:{$position}|rewarded";
                break;
                
            case 'level_promoted':
            case 'level_demoted':
            case 'level_retained':
                // user:{u}|week:{start}|{transition}
                $week_start = isset($params['week_start']) ? $params['week_start'] : TimezoneHelper::getTodayTR();
                $key = "user:{$user_id}|week:{$week_start}|{$event_type}";
                break;
                
            default:
                // Generic fallback: event:{type}|user:{u}|timestamp:{ts}
                $timestamp = time();
                $key = "event:{$event_type}|user:{$user_id}|timestamp:{$timestamp}";
                break;
        }
        
        return $key;
    }
}
