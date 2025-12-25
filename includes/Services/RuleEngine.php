<?php
namespace Rejimde\Services;

use Rejimde\Utils\ConfigHelper;

/**
 * RuleEngine
 * 
 * Points calculation rules for different event types
 */
class RuleEngine {
    
    /**
     * Calculate points for an event
     * 
     * @param string $event_type Event type
     * @param array $metadata Event metadata
     * @return int Points to award
     */
    public static function calculatePoints($event_type, $metadata = []) {
        $points = 0;
        
        switch ($event_type) {
            case 'login_success':
                // +2 points, daily limit checked elsewhere
                $points = 2;
                break;
                
            case 'blog_points_claimed':
                // Sticky: +50, Normal: +10
                // Metadata'dan is_sticky al, çeşitli formatları destekle
                $is_sticky = false;
                if (isset($metadata['is_sticky'])) {
                    // Boolean, string "true"/"false", "1"/"0" hepsini destekle
                    $is_sticky = filter_var($metadata['is_sticky'], FILTER_VALIDATE_BOOLEAN);
                }
                $points = $is_sticky ? 50 : 10;
                break;
                
            case 'diet_started':
                // +5 points
                $points = 5;
                break;
                
            case 'diet_completed':
                // Dynamic points: Try diet_points first, then points, default to 10
                $points = isset($metadata['diet_points']) ? (int) $metadata['diet_points'] : 
                          (isset($metadata['points']) ? (int) $metadata['points'] : 10);
                break;
                
            case 'exercise_started':
                // +3 points
                $points = 3;
                break;
                
            case 'exercise_completed':
                // Dynamic points: Try exercise_points first, then points, default to 10
                $points = isset($metadata['exercise_points']) ? (int) $metadata['exercise_points'] : 
                          (isset($metadata['points']) ? (int) $metadata['points'] : 10);
                break;
                
            case 'calculator_saved':
                // +10 points
                $points = 10;
                break;
                
            case 'rating_submitted':
                // +20 points
                $points = 20;
                break;
                
            case 'comment_created':
                // +2 points
                $points = 2;
                break;
                
            case 'comment_liked':
                // 0 points for liker (only logged)
                $points = 0;
                break;
                
            case 'follow_accepted':
                // +1 point (will be awarded to both users)
                $points = 1;
                break;
                
            case 'highfive_sent':
                // +1 point for sender
                $points = 1;
                break;
                
            case 'water_added':
                // +1 point per 200ml bucket
                $points = 1;
                break;
                
            case 'steps_logged':
                // +1 point per 1000 steps bucket
                $points = 1;
                break;
                
            case 'meal_photo_uploaded':
                // +15 points
                $points = 15;
                break;
                
            case 'circle_joined':
                // +100 points (from old system)
                $points = 100;
                break;
                
            case 'circle_created':
                // Configurable, default 0
                $enabled = ConfigHelper::get('circle_create_points_enabled', false);
                if ($enabled) {
                    $points = ConfigHelper::get('circle_create_points', 0);
                }
                break;
                
            case 'comment_like_milestone_rewarded':
                // Dynamic points from milestone
                $points = isset($metadata['points']) ? (int) $metadata['points'] : 0;
                break;
                
            case 'level_position_rewarded':
                // Dynamic points from position
                $points = isset($metadata['points']) ? (int) $metadata['points'] : 0;
                break;
                
            default:
                $points = 0;
                break;
        }
        
        return $points;
    }
    
    /**
     * Get daily limit for an event type
     * 
     * @param string $event_type Event type
     * @return int|null Daily limit (null = no limit)
     */
    public static function getDailyLimit($event_type) {
        switch ($event_type) {
            case 'login_success':
                return 1;
                
            case 'blog_points_claimed':
                return 5;
                
            case 'meal_photo_uploaded':
                return 5;
                
            case 'water_added':
                // Max 15 points = 15 buckets = 3000ml
                return 15;
                
            case 'steps_logged':
                // Configurable, default 20
                return ConfigHelper::get('steps_daily_max_points', 20);
                
            default:
                return null; // No limit
        }
    }
    
    /**
     * Get user-friendly message for points earned
     * 
     * @param string $event_type Event type
     * @param int $points Points awarded
     * @param array $metadata Event metadata
     * @return string User-friendly message
     */
    public static function getMessage($event_type, $points, $metadata = []) {
        if ($points === 0) {
            return '';
        }
        
        switch ($event_type) {
            case 'login_success':
                return "+{$points} puan kazandın! Hoş geldin!";
                
            case 'blog_points_claimed':
                // Metadata'dan is_sticky al, çeşitli formatları destekle
                $is_sticky = false;
                if (isset($metadata['is_sticky'])) {
                    $is_sticky = filter_var($metadata['is_sticky'], FILTER_VALIDATE_BOOLEAN);
                }
                $type = $is_sticky ? 'Sticky blog' : 'Blog okuma';
                return "+{$points} puan kazandın! ({$type})";
                
            case 'diet_started':
                return "+{$points} puan kazandın! Diyet planını başlattın.";
                
            case 'diet_completed':
                return "+{$points} puan kazandın! Diyet planını tamamladın!";
                
            case 'exercise_started':
                return "+{$points} puan kazandın! Egzersiz başlattın.";
                
            case 'exercise_completed':
                return "+{$points} puan kazandın! Egzersizi tamamladın!";
                
            case 'calculator_saved':
                return "+{$points} puan kazandın! Hesaplama kaydedildi.";
                
            case 'rating_submitted':
                return "+{$points} puan kazandın! Değerlendirme yaptın.";
                
            case 'comment_created':
                return "+{$points} puan kazandın! Yorum yaptın.";
                
            case 'follow_accepted':
                return "+{$points} puan kazandın! Yeni bağlantı.";
                
            case 'highfive_sent':
                return "+{$points} puan kazandın! High five!";
                
            case 'water_added':
                return "+{$points} puan kazandın! Su içtin.";
                
            case 'steps_logged':
                return "+{$points} puan kazandın! Adım kaydedildi.";
                
            case 'meal_photo_uploaded':
                return "+{$points} puan kazandın! Öğün fotoğrafı eklendi.";
                
            case 'circle_joined':
                return "+{$points} puan kazandın! Circle'a katıldın!";
                
            case 'comment_like_milestone_rewarded':
                $milestone = isset($metadata['milestone']) ? $metadata['milestone'] : 0;
                return "+{$points} puan kazandın! Yorumun {$milestone} beğeni aldı!";
                
            case 'level_position_rewarded':
                $position = isset($metadata['position']) ? $metadata['position'] : 0;
                return "+{$points} puan kazandın! {$position}. sırada bitirdin!";
                
            default:
                return "+{$points} puan kazandın!";
        }
    }
}
