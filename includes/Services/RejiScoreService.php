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
    
    // Freshness score calculation weights
    private const FRESHNESS_WEIGHT_VIEWS = 10;      // Divide profile views by this
    private const FRESHNESS_WEIGHT_UNIQUE = 5;      // Divide unique viewers by this
    private const FRESHNESS_WEIGHT_RATINGS = 5;     // Multiply new ratings by this
    private const FRESHNESS_WEIGHT_ACTIVE_DAYS = 1; // Multiply active days by this
    private const FRESHNESS_GROWTH_DIVISOR = 5;     // Convert growth % to bonus points
    
    private $metricsTableExists = null; // Cache table existence check
    
    /**
     * Get expert's professional post ID from user meta
     * Checks both 'professional_profile_id' and 'related_pro_post_id' for compatibility
     * Falls back to reverse lookup from post meta if needed
     *
     * @param int $expertId Expert user ID
     * @return int|null Post ID or null if not found
     */
    private function getExpertPostId(int $expertId): ?int {
        // Try primary key
        $postId = get_user_meta($expertId, 'professional_profile_id', true);
        if (!empty($postId)) {
            return (int) $postId;
        }
        
        // Fallback to alternative key
        $postId = get_user_meta($expertId, 'related_pro_post_id', true);
        if (!empty($postId)) {
            return (int) $postId;
        }
        
        // Fallback: reverse lookup from post meta
        global $wpdb;
        $postId = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = 'related_user_id' 
            AND meta_value = %d
            LIMIT 1
        ", $expertId));
        
        return $postId ? (int) $postId : null;
    }
    
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
        
        // Get success stats
        $successStats = $this->getSuccessStats($expertId);
        
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
            'level_label' => $this->getScoreLevelLabel($finalScore),
            // Yeni alanlar
            'score_impact' => $successStats['score_impact'],
            'goal_success_rate' => $successStats['goal_success_rate'],
            'completed_clients' => $successStats['completed_clients']
        ];
    }
    
    /**
     * Check if expert metrics table exists (cached)
     */
    private function metricsTableExists(): bool {
        if ($this->metricsTableExists !== null) {
            return $this->metricsTableExists;
        }
        
        global $wpdb;
        $metricsTable = $wpdb->prefix . 'rejimde_expert_metrics';
        $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($metricsTable)));
        $this->metricsTableExists = ($tableExists !== null);
        
        return $this->metricsTableExists;
    }
    
    /**
     * Trust Score - Based on weighted user reviews
     * Verified clients have 3x weight
     */
    private function calculateTrustScore(int $expertId): int {
        global $wpdb;
        
        $postId = $this->getExpertPostId($expertId);
        if (!$postId) return 50; // Default for new experts
        
        $table = $wpdb->prefix . 'comments';
        $metaTable = $wpdb->prefix . 'commentmeta';
        
        // Get all approved reviews with their ratings and verification status
        // Using LEFT JOINs for better performance than correlated subqueries
        $reviews = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.comment_ID,
                c.user_id,
                COALESCE(cm_rating.meta_value, 0) as rating,
                COALESCE(cm_verified.meta_value, 0) as is_verified_client
            FROM $table c
            LEFT JOIN $metaTable cm_rating 
                ON c.comment_ID = cm_rating.comment_id AND cm_rating.meta_key = 'rejimde_rating'
            LEFT JOIN $metaTable cm_verified 
                ON c.comment_ID = cm_verified.comment_id AND cm_verified.meta_key = 'verified_client'
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
            // Explicit string comparison to handle '0', '1', null, etc.
            $weight = ($review->is_verified_client == '1') ? 3 : 1;
            
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
        
        // Count diet plans created (DOĞRU POST TYPE: rejimde_plan)
        $dietCount = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_author = %d 
            AND post_type = 'rejimde_plan' 
            AND post_status = 'publish'
        ", $expertId));
        $score += min(30, $dietCount * 5); // Max 30 points
        
        // Count exercise plans created
        $exerciseCount = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_author = %d 
            AND post_type = 'rejimde_exercise' 
            AND post_status = 'publish'
        ", $expertId));
        $score += min(30, $exerciseCount * 5); // Max 30 points
        
        // Count articles/posts
        $articleCount = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_author = %d 
            AND post_type = 'post' 
            AND post_status = 'publish'
        ", $expertId));
        $score += min(20, $articleCount * 4); // Max 20 points
        
        // Count plans approved by this expert (ONAYLANAN PLANLAR)
        $approvedCount = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'approved_by' 
            AND pm.meta_value = %d
            AND p.post_status = 'publish'
            AND p.post_type IN ('rejimde_plan', 'rejimde_exercise')
        ", $expertId));
        $score += min(20, $approvedCount * 2); // Max 20 points
        
        return min(100, $score);
    }
    
    /**
     * Freshness Score - Based on recent activity trends
     */
    private function calculateFreshnessScore(int $expertId): int {
        global $wpdb;
        
        if (!$this->metricsTableExists()) {
            return 50; // Default score if metrics table doesn't exist
        }
        
        $metricsTable = $wpdb->prefix . 'rejimde_expert_metrics';
        
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
        // Weighted by: views/10 + unique_viewers/5 + ratings*5 + active_days*1
        if ($last30Days) {
            $activityScore = min(30, 
                ($last30Days->total_views ?? 0) / self::FRESHNESS_WEIGHT_VIEWS + 
                ($last30Days->unique_views ?? 0) / self::FRESHNESS_WEIGHT_UNIQUE +
                ($last30Days->new_ratings ?? 0) * self::FRESHNESS_WEIGHT_RATINGS +
                ($last30Days->active_days ?? 0) * self::FRESHNESS_WEIGHT_ACTIVE_DAYS
            );
            $score += $activityScore;
        }
        
        // Trend bonus (up to 20 points)
        // Growth percentage divided by 5 converts to bonus points
        if ($prev30Days && $prev30Days->total_views > 0 && $last30Days) {
            $growth = (($last30Days->total_views - $prev30Days->total_views) / $prev30Days->total_views) * 100;
            if ($growth > 0) {
                $score += min(20, $growth / self::FRESHNESS_GROWTH_DIVISOR);
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
        $postId = $this->getExpertPostId($expertId);
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
        
        if (!$this->metricsTableExists()) {
            return ['percentage' => 0, 'direction' => 'stable'];
        }
        
        $metricsTable = $wpdb->prefix . 'rejimde_expert_metrics';
        
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
        
        $postId = $this->getExpertPostId($expertId);
        
        // User rating (average)
        $userRating = $postId ? (float) get_post_meta($postId, 'puan', true) : 0;
        
        // Review count - expert context yorumlarını say
        $reviewCount = 0;
        if ($postId) {
            $reviewCount = (int) $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT c.comment_ID) 
                FROM {$wpdb->comments} c
                LEFT JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'rejimde_context'
                WHERE c.comment_post_ID = %d 
                AND c.comment_approved = '1'
                AND c.comment_parent = 0
                AND (cm.meta_value = 'expert' OR cm.meta_value IS NULL)
            ", $postId));
        }
        
        // Content count - DOĞRU POST TYPE'LAR
        // 1. Oluşturulan planlar
        $createdCount = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_author = %d 
            AND post_type IN ('rejimde_plan', 'rejimde_exercise', 'post')
            AND post_status = 'publish'
        ", $expertId));
        
        // 2. Onaylanan planlar
        $approvedCount = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'approved_by' 
            AND pm.meta_value = %d
            AND p.post_status = 'publish'
        ", $expertId));
        
        $contentCount = $createdCount + $approvedCount;
        
        return [
            'user_rating' => $userRating ?: 0.0,
            'review_count' => $reviewCount,
            'content_count' => $contentCount
        ];
    }
    
    /**
     * Get expert success statistics for frontend display
     */
    public function getSuccessStats(int $expertId): array {
        global $wpdb;
        
        $postId = $this->getExpertPostId($expertId);
        
        // Score impact from post meta
        $scoreImpact = $postId ? get_post_meta($postId, 'skor_etkisi', true) : '--';
        
        // Calculate goal success rate from completed clients
        // Check rejimde_appointments table for completed appointments
        $appointmentsTable = $wpdb->prefix . 'rejimde_appointments';
        $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($appointmentsTable)));
        
        $goalSuccessRate = 0;
        $completedClients = 0;
        
        if ($tableExists) {
            // Count completed appointments/programs
            $completedClients = (int) $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT client_id) 
                FROM $appointmentsTable 
                WHERE expert_id = %d 
                AND status = 'completed'
            ", $expertId));
            
            // Get total clients
            $totalClients = (int) $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT client_id) 
                FROM $appointmentsTable 
                WHERE expert_id = %d
            ", $expertId));
            
            if ($totalClients > 0) {
                $goalSuccessRate = round(($completedClients / $totalClients) * 100);
            }
        }
        
        // If no data, use default
        if ($goalSuccessRate === 0) {
            $goalSuccessRate = 85; // Default placeholder
        }
        
        return [
            'score_impact' => $scoreImpact ?: '--',
            'goal_success_rate' => $goalSuccessRate,
            'completed_clients' => $completedClients
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
