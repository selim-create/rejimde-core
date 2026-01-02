<?php
/**
 * RejiScore Calculation Service
 * 
 * Calculates comprehensive expert scores based on:
 * - Trust Score (user reviews, weighted by verification)
 * - Contribution Score (content, plans, approvals)
 * - Freshness Score (recent activity trends)
 * - Verification Bonus
 */

namespace Rejimde\Services;

class RejiScoreService {
    
    private const WEIGHT_TRUST = 0.30;
    private const WEIGHT_CONTRIBUTION = 0.25;
    private const WEIGHT_FRESHNESS = 0.25;
    private const WEIGHT_VERIFICATION = 0.20;
    
    /**
     * Calculate complete RejiScore for an expert
     *
     * @param int $expertId Expert user ID
     * @return array Score breakdown
     */
    public function calculate(int $expertId): array {
        $trustScore = $this->calculateTrustScore($expertId);
        $contributionScore = $this->calculateContributionScore($expertId);
        $freshnessScore = $this->calculateFreshnessScore($expertId);
        $verificationBonus = $this->calculateVerificationBonus($expertId);
        
        // Calculate final score (0-100)
        $finalScore = round(
            ($trustScore * self::WEIGHT_TRUST) +
            ($contributionScore * self::WEIGHT_CONTRIBUTION) +
            ($freshnessScore * self::WEIGHT_FRESHNESS) +
            ($verificationBonus * self::WEIGHT_VERIFICATION)
        );
        
        // Get trend data
        $trendData = $this->calculateTrend($expertId);
        
        // Get supporting stats
        $stats = $this->getExpertStats($expertId);
        
        return [
            'reji_score' => min(100, max(0, $finalScore)),
            'trust_score' => $trustScore,
            'contribution_score' => $contributionScore,
            'freshness_score' => $freshnessScore,
            'verification_bonus' => $verificationBonus,
            'is_verified' => $verificationBonus >= 100,
            'trend_percentage' => $trendData['percentage'],
            'trend_direction' => $trendData['direction'],
            'user_rating' => $stats['user_rating'],
            'review_count' => $stats['review_count'],
            'content_count' => $stats['content_count'],
            'level' => $this->getScoreLevel($finalScore),
            'level_label' => $this->getScoreLevelLabel($finalScore)
        ];
    }
    
    /**
     * Trust Score - Based on weighted user reviews
     * Verified clients have 3x weight
     */
    private function calculateTrustScore(int $expertId): int {
        global $wpdb;
        
        $postId = get_user_meta($expertId, 'professional_profile_id', true);
        if (!$postId) return 50; // Default for new experts
        
        $table = $wpdb->prefix . 'comments';
        $metaTable = $wpdb->prefix . 'commentmeta';
        
        // Get all approved reviews with their ratings and verification status
        $reviews = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.comment_ID,
                c.user_id,
                COALESCE(
                    (SELECT meta_value FROM $metaTable WHERE comment_id = c.comment_ID AND meta_key = 'rating'),
                    0
                ) as rating,
                COALESCE(
                    (SELECT meta_value FROM $metaTable WHERE comment_id = c.comment_ID AND meta_key = 'verified_client'),
                    0
                ) as is_verified_client
            FROM $table c
            WHERE c.comment_post_ID = %d
            AND c.comment_approved = '1'
            AND c.comment_type IN ('comment', '')
        ", $postId));
        
        if (empty($reviews)) return 50; // Default score for no reviews
        
        $totalWeight = 0;
        $weightedSum = 0;
        
        foreach ($reviews as $review) {
            $rating = (float) $review->rating;
            if ($rating < 1 || $rating > 5) continue;
            
            // Weight: verified client = 3, regular user = 1
            $weight = $review->is_verified_client ? 3 : 1;
            
            $totalWeight += $weight;
            $weightedSum += ($rating * $weight);
        }
        
        if ($totalWeight === 0) return 50;
        
        // Convert 1-5 rating to 0-100 score
        $averageRating = $weightedSum / $totalWeight;
        $baseScore = ($averageRating - 1) * 25; // 1=0, 5=100
        
        // Bonus for having many reviews (up to 10 points)
        $reviewCountBonus = min(10, count($reviews));
        
        return (int) min(100, $baseScore + $reviewCountBonus);
    }
    
    /**
     * Contribution Score - Based on content and activity
     */
    private function calculateContributionScore(int $expertId): int {
        global $wpdb;
        
        $score = 0;
        
        // Count diet plans created
        $dietCount = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_author = %d 
            AND post_type = 'rejimde_diet' 
            AND post_status = 'publish'
        ", $expertId));
        $score += min(30, $dietCount * 5); // Max 30 points
        
        // Count exercise plans created
        $exerciseCount = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_author = %d 
            AND post_type = 'rejimde_exercise' 
            AND post_status = 'publish'
        ", $expertId));
        $score += min(30, $exerciseCount * 5); // Max 30 points
        
        // Count articles/posts
        $articleCount = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_author = %d 
            AND post_type = 'post' 
            AND post_status = 'publish'
        ", $expertId));
        $score += min(20, $articleCount * 4); // Max 20 points
        
        // Count client completions
        $metricsTable = $wpdb->prefix . 'rejimde_expert_metrics';
        $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $metricsTable));
        
        if ($tableExists) {
            $clientCompletions = $wpdb->get_var($wpdb->prepare("
                SELECT SUM(client_completions) FROM {$wpdb->prefix}rejimde_expert_metrics 
                WHERE expert_id = %d
            ", $expertId));
            $score += min(20, ($clientCompletions ?? 0) * 2); // Max 20 points
        }
        
        return min(100, $score);
    }
    
    /**
     * Freshness Score - Based on recent activity trends
     */
    private function calculateFreshnessScore(int $expertId): int {
        global $wpdb;
        
        $metricsTable = $wpdb->prefix . 'rejimde_expert_metrics';
        
        // Check if table exists
        $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $metricsTable));
        if (!$tableExists) return 50;
        
        // Get last 30 days metrics
        $last30Days = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COALESCE(SUM(profile_views), 0) as total_views,
                COALESCE(SUM(unique_viewers), 0) as unique_views,
                COALESCE(SUM(rating_count), 0) as new_ratings,
                COUNT(*) as active_days
            FROM $metricsTable
            WHERE expert_id = %d
            AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ", $expertId));
        
        // Get previous 30 days for comparison
        $prev30Days = $wpdb->get_row($wpdb->prepare("
            SELECT COALESCE(SUM(profile_views), 0) as total_views
            FROM $metricsTable
            WHERE expert_id = %d
            AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            AND metric_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ", $expertId));
        
        $score = 50; // Base score
        
        // Activity bonus (up to 30 points)
        if ($last30Days) {
            $activityScore = min(30, 
                ($last30Days->total_views ?? 0) / 10 + 
                ($last30Days->unique_views ?? 0) / 5 +
                ($last30Days->new_ratings ?? 0) * 5 +
                ($last30Days->active_days ?? 0)
            );
            $score += $activityScore;
        }
        
        // Trend bonus (up to 20 points)
        if ($prev30Days && $prev30Days->total_views > 0 && $last30Days) {
            $growth = (($last30Days->total_views - $prev30Days->total_views) / $prev30Days->total_views) * 100;
            if ($growth > 0) {
                $score += min(20, $growth / 5);
            }
        }
        
        return min(100, max(0, (int) $score));
    }
    
    /**
     * Verification Bonus - For verified experts
     */
    private function calculateVerificationBonus(int $expertId): int {
        $isVerified = get_user_meta($expertId, 'is_verified_expert', true);
        
        if ($isVerified === '1' || $isVerified === true || $isVerified === 1) {
            return 100; // Full bonus
        }
        
        // Check if profile is claimed
        $postId = get_user_meta($expertId, 'professional_profile_id', true);
        if ($postId) {
            $isClaimed = get_post_meta($postId, 'is_claimed', true);
            if ($isClaimed === '1' || $isClaimed === true || $isClaimed === 1) {
                return 50; // Half bonus for claimed but not verified
            }
        }
        
        return 0;
    }
    
    /**
     * Calculate trend data (last 7 days vs previous 7 days)
     */
    private function calculateTrend(int $expertId): array {
        global $wpdb;
        
        $metricsTable = $wpdb->prefix . 'rejimde_expert_metrics';
        
        // Check if table exists
        $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $metricsTable));
        if (!$tableExists) {
            return ['percentage' => 0, 'direction' => 'stable'];
        }
        
        // Current period (last 7 days)
        $current = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(profile_views + COALESCE(rating_count, 0) * 10), 0)
            FROM $metricsTable
            WHERE expert_id = %d
            AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ", $expertId));
        
        // Previous period (7-14 days ago)
        $previous = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(profile_views + COALESCE(rating_count, 0) * 10), 0)
            FROM $metricsTable
            WHERE expert_id = %d
            AND metric_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            AND metric_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ", $expertId));
        
        $current = (int) ($current ?? 0);
        $previous = (int) ($previous ?? 0);
        
        if ($previous === 0) {
            return [
                'percentage' => $current > 0 ? 100 : 0,
                'direction' => $current > 0 ? 'up' : 'stable'
            ];
        }
        
        $percentage = round((($current - $previous) / $previous) * 100);
        
        return [
            'percentage' => $percentage,
            'direction' => $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'stable')
        ];
    }
    
    /**
     * Get expert statistics
     */
    private function getExpertStats(int $expertId): array {
        global $wpdb;
        
        $postId = get_user_meta($expertId, 'professional_profile_id', true);
        
        // User rating (average)
        $userRating = $postId ? (float) get_post_meta($postId, 'puan', true) : 0;
        
        // Review count
        $reviewCount = $postId ? (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->comments} 
            WHERE comment_post_ID = %d AND comment_approved = '1'
        ", $postId)) : 0;
        
        // Content count (diets + exercises + articles)
        $contentCount = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_author = %d 
            AND post_type IN ('rejimde_diet', 'rejimde_exercise', 'post')
            AND post_status = 'publish'
        ", $expertId));
        
        return [
            'user_rating' => $userRating ?: 0.0,
            'review_count' => $reviewCount,
            'content_count' => $contentCount
        ];
    }
    
    /**
     * Get score level (1-5)
     */
    private function getScoreLevel(int $score): int {
        if ($score >= 90) return 5;
        if ($score >= 80) return 4;
        if ($score >= 70) return 3;
        if ($score >= 50) return 2;
        return 1;
    }
    
    /**
     * Get score level label
     */
    private function getScoreLevelLabel(int $score): string {
        if ($score >= 90) return 'Efsane';
        if ($score >= 80) return 'Yüksek Güven';
        if ($score >= 70) return 'İyi';
        if ($score >= 50) return 'Gelişiyor';
        return 'Yeni';
    }
}
