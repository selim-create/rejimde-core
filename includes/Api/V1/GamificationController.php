<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Query; // WP_Query sınıfını kullanabilmek için ekledik

class GamificationController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'gamification';

    // Varsayılan Kurallar (Yedek)
    private function get_default_rules() {
        return [
            'daily_login'       => ['points' => 10, 'limit' => 1, 'label' => 'Günlük Giriş'],
            'log_water'         => ['points' => 5,  'limit' => 10, 'label' => 'Su İçme'],
            'log_meal'          => ['points' => 15, 'limit' => 5,  'label' => 'Öğün Girme'],
            'read_blog'         => ['points' => 10, 'limit' => 5,  'label' => 'Makale Okuma'],
            'complete_workout'  => ['points' => 50, 'limit' => 1,  'label' => 'Antrenman'],
            'update_weight'     => ['points' => 50, 'limit' => 1,  'label' => 'Profil Güncelleme'],
            'join_circle'       => ['points' => 100,'limit' => 1,  'label' => 'Circle\'a Katılma'],
        ];
    }

    public function register_routes() {
        // Puan Kazanma
        register_rest_route($this->namespace, '/' . $this->base . '/earn', [
            'methods' => 'POST',
            'callback' => [$this, 'earn_points'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Kullanıcı Gamification Durumu
        register_rest_route($this->namespace, '/' . $this->base . '/me', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_stats'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Rozetleri Listele
        register_rest_route($this->namespace, '/' . $this->base . '/badges', [
            'methods' => 'GET',
            'callback' => [$this, 'get_badges'],
            'permission_callback' => '__return_true',
        ]);
        
        // Geçmiş
        register_rest_route($this->namespace, '/' . $this->base . '/history', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_history'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // LİDERLİK TABLOSU (YENİ) - Hem bireysel hem klan sıralaması
        register_rest_route($this->namespace, '/' . $this->base . '/leaderboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_leaderboard'],
            'permission_callback' => '__return_true', // Herkes görebilir
        ]);
        
        // YENİ: Streak Bilgisi
        register_rest_route($this->namespace, '/' . $this->base . '/streak', [
            'methods' => 'GET',
            'callback' => [$this, 'get_streak'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
        
        // YENİ: Milestones
        register_rest_route($this->namespace, '/' . $this->base . '/milestones', [
            'methods' => 'GET',
            'callback' => [$this, 'get_milestones'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
        
        // YENİ: Log Milestone (manual tracking)
        register_rest_route($this->namespace, '/' . $this->base . '/milestones/log', [
            'methods' => 'POST',
            'callback' => [$this, 'log_milestone'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
        
        // YENİ: Events
        register_rest_route($this->namespace, '/' . $this->base . '/events', [
            'methods' => 'GET',
            'callback' => [$this, 'get_events'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
        
        // YENİ: Circle Account - Get user's circle membership and score contribution
        register_rest_route($this->namespace, '/' . $this->base . '/circle-account', [
            'methods' => 'GET',
            'callback' => [$this, 'get_circle_account'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
        
        // Level-based leaderboard
        register_rest_route($this->namespace, '/' . $this->base . '/level-leaderboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_level_leaderboard'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * LİDERLİK TABLOSU VE SEVİYELER (YENİ)
     * type=users (varsayılan) veya type=circles parametresi alır.
     */
    public function get_leaderboard($request) {
        $type = $request->get_param('type') ?: 'users';
        $limit = (int) ($request->get_param('limit') ?: 20);
        
        $data = [];

        if ($type === 'circles') {
            // --- CIRCLE SIRALAMASI ---
            $args = [
                'post_type' => 'rejimde_circle',
                'posts_per_page' => $limit,
                'post_status' => 'publish',
                'meta_key' => 'total_score',
                'orderby' => 'meta_value_num',
                'order' => 'DESC'
            ];
            $query = new WP_Query($args);
            
            foreach ($query->posts as $post) {
                $score = (int) get_post_meta($post->ID, 'total_score', true);
                $data[] = [
                    'id' => $post->ID,
                    'name' => $post->post_title,
                    'slug' => $post->post_name,
                    'score' => $score,
                    'level' => $this->calculate_level($score), // Seviye bilgisi
                    'logo' => get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: null
                ];
            }
        } else {
            // --- KULLANICI SIRALAMASI ---
            // Sadece rejimde_total_score'a sahip kullanıcıları getir
            $user_query = new \WP_User_Query([
                'meta_key' => 'rejimde_total_score',
                'orderby' => 'meta_value_num',
                'order' => 'DESC',
                'number' => $limit,
                'role__not_in' => ['administrator', 'rejimde_pro'], // Pro kullanıcılar seviyelere katılmaz
                'fields' => 'all_with_meta' // Performans için
            ]);
            
            $users = $user_query->get_results();
            
            foreach ($users as $user) {
                $score = (int) get_user_meta($user->ID, 'rejimde_total_score', true);
                $data[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name ?: $user->user_login,
                    'avatar' => get_user_meta($user->ID, 'avatar_url', true),
                    'score' => $score,
                    'rank' => (int) get_user_meta($user->ID, 'rejimde_rank', true) ?: 1, // Kullanıcı rank'ı
                    'level' => $this->calculate_level($score) // Puan bazlı level
                ];
            }
        }

        return $this->success($data);
    }

    /**
     * PUANA GÖRE SEVİYE HESAPLAMA (YARDIMCI)
     */
    private function calculate_level($score) {
        if ($score >= 6000) return [
            'id' => 'level-8', 
            'name' => 'Transform', 
            'level' => 8, 
            'slug' => 'transform', 
            'icon' => 'fa-star', 
            'color' => 'text-purple-600', 
            'description' => 'Kalıcı değişim. Yeni bir denge, yeni bir sen.',
            'min' => 6000,
            'max' => 10000
        ];
        if ($score >= 4000) return [
            'id' => 'level-7', 
            'name' => 'Mastery', 
            'level' => 7, 
            'slug' => 'mastery', 
            'icon' => 'fa-crown', 
            'color' => 'text-yellow-500', 
            'description' => 'Bilinçli seçimler yaparsın. Ne yaptığını ve neden yaptığını bilerek ilerlersin.',
            'min' => 4000,
            'max' => 6000
        ];
        if ($score >= 2000) return [
            'id' => 'level-6', 
            'name' => 'Sustain', 
            'level' => 6, 
            'slug' => 'sustain', 
            'icon' => 'fa-infinity', 
            'color' => 'text-teal-500', 
            'description' => 'Bu bir rejim olmaktan çıkar, yaşam tarzına dönüşür. Devam etmek zor gelmez.',
            'min' => 2000,
            'max' => 4000
        ];
        if ($score >= 1000) return [
            'id' => 'level-5', 
            'name' => 'Strengthen', 
            'level' => 5, 
            'slug' => 'strengthen', 
            'icon' => 'fa-dumbbell', 
            'color' => 'text-red-500', 
            'description' => 'Fiziksel ve zihinsel olarak güçlenme başlar. Gelişim artık net şekilde hissedilir.',
            'min' => 1000,
            'max' => 2000
        ];
        if ($score >= 500) return [
            'id' => 'level-4', 
            'name' => 'Balance', 
            'level' => 4, 
            'slug' => 'balance', 
            'icon' => 'fa-scale-balanced', 
            'color' => 'text-blue-500', 
            'description' => 'Beslenme, hareket ve zihin dengelenir. Kendini daha kontrollü ve rahat hissedersin.',
            'min' => 500,
            'max' => 1000
        ];
        if ($score >= 300) return [
            'id' => 'level-3', 
            'name' => 'Commit', 
            'level' => 3, 
            'slug' => 'commit', 
            'icon' => 'fa-check-circle', 
            'color' => 'text-green-500', 
            'description' => 'İstikrar burada doğar. Düzenli devam etmek artık bir tercih değil, alışkanlık.',
            'min' => 300,
            'max' => 500
        ];
        if ($score >= 200) return [
            'id' => 'level-2', 
            'name' => 'Adapt', 
            'level' => 2, 
            'slug' => 'adapt', 
            'icon' => 'fa-sync', 
            'color' => 'text-orange-500', 
            'description' => 'Vücut ve zihin yeni rutine alışmaya başlar. Küçük değişimler büyük farklar yaratır.',
            'min' => 200,
            'max' => 300
        ];
        return [
            'id' => 'level-1', 
            'name' => 'Begin', 
            'level' => 1, 
            'slug' => 'begin', 
            'icon' => 'fa-seedling', 
            'color' => 'text-gray-500', 
            'description' => 'Her yolculuk bir adımla başlar. Burada beklenti yok, sadece başlamak var.',
            'min' => 0,
            'max' => 200
        ];
    }

    /**
     * KULLANICI İSTATİSTİKLERİ
     */
    public function get_my_stats($request) {
        $user_id = get_current_user_id();
        $today = date('Y-m-d');
        global $wpdb;
        $table_logs = $wpdb->prefix . 'rejimde_daily_logs';

        $daily_row = $wpdb->get_row($wpdb->prepare(
            "SELECT score_daily FROM $table_logs WHERE user_id = %d AND log_date = %s",
            $user_id, $today
        ));
        $daily_score = $daily_row ? (int)$daily_row->score_daily : 0;

        $total_score = (int) get_user_meta($user_id, 'rejimde_total_score', true);
        
        // Level bilgisini hesapla (puan bazlı)
        $level = $this->calculate_level($total_score);
        
        // Rank bilgisini al (kullanıcı deneyim seviyesi)
        $rank = (int) get_user_meta($user_id, 'rejimde_rank', true) ?: 1;
        
        // Calculate user's rank within their level
        $level_rank = $this->calculate_user_rank_in_level($user_id, $level, $total_score);
        
        $earned_badges = get_user_meta($user_id, 'rejimde_earned_badges', true);
        if (!is_array($earned_badges)) $earned_badges = [];
        
        // Check if user is pro
        $user = wp_get_current_user();
        $is_pro = in_array('rejimde_pro', (array) $user->roles);
        
        // Get circle info
        $circle_id = get_user_meta($user_id, 'circle_id', true);
        $circle_info = null;
        if ($circle_id) {
            $circle = get_post($circle_id);
            if ($circle && $circle->post_type === 'rejimde_circle') {
                $circle_info = [
                    'id' => $circle_id,
                    'name' => $circle->post_title,
                    'role' => get_user_meta($user_id, 'circle_role', true)
                ];
            } else {
                // Circle deleted, cleanup
                delete_user_meta($user_id, 'circle_id');
                delete_user_meta($user_id, 'circle_role');
            }
        }

        return $this->success([
            'daily_score' => $daily_score,
            'total_score' => $total_score,
            'rank' => $level_rank,     // User's rank within their level
            'level' => $level,         // Puan bazlı level
            'earned_badges' => $earned_badges,
            'is_pro' => $is_pro,       // Pro user status
            'circle' => $circle_info   // Circle membership info
        ]);
    }

    /**
     * ROZETLERİ LİSTELE
     */
    public function get_badges($request) {
        $args = [
            'post_type' => 'rejimde_badge',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];
        $posts = get_posts($args);
        $badges = [];

        foreach ($posts as $post) {
            $raw_content = $post->post_content;
            $clean_content = preg_replace('/<!--(.|\s)*?-->/', '', $raw_content);
            $clean_content = wp_strip_all_tags($clean_content);
            $clean_content = trim($clean_content);

            $badges[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'description' => $clean_content,
                'image' => get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: 'https://placehold.co/100x100/orange/white?text=Badge',
                'points_required' => (int) get_post_meta($post->ID, 'points_required', true),
                'action_required' => get_post_meta($post->ID, 'action_required', true)
            ];
        }
        return $this->success($badges);
    }

    public function earn_points($request) {
        $params = $request->get_json_params();
        $eventType = sanitize_text_field($params['action'] ?? $params['event_type'] ?? '');
        
        if (empty($eventType)) {
            return $this->error('Event type is required', 400);
        }
        
        // Build payload for EventDispatcher
        $payload = [
            'user_id' => get_current_user_id(),
            'entity_type' => sanitize_text_field($params['entity_type'] ?? null),
            'entity_id' => isset($params['ref_id']) ? (int) $params['ref_id'] : (isset($params['entity_id']) ? (int) $params['entity_id'] : null),
            'context' => []
        ];
        
        // Add any additional context
        if (isset($params['context']) && is_array($params['context'])) {
            $payload['context'] = $params['context'];
        }
        
        // Support for follow events
        if (isset($params['follower_id'])) {
            $payload['follower_id'] = (int) $params['follower_id'];
        }
        if (isset($params['followed_id'])) {
            $payload['followed_id'] = (int) $params['followed_id'];
        }
        
        // Support for comment events
        if (isset($params['comment_id'])) {
            $payload['comment_id'] = (int) $params['comment_id'];
        }
        
        // Dispatch event
        $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
        $result = $dispatcher->dispatch($eventType, $payload);
        
        // Handle success or already_earned cases (both should return 200 OK)
        if ($result['success'] || !empty($result['already_earned'])) {
            return $this->success($result);
        } else {
            return $this->error($result['message'], 400);
        }
    }

    private function check_badges($user_id, $total_score) {
        $earned = get_user_meta($user_id, 'rejimde_earned_badges', true);
        if (!is_array($earned)) $earned = [];

        $args = ['post_type' => 'rejimde_badge', 'posts_per_page' => -1];
        $badges = get_posts($args);

        foreach ($badges as $badge) {
            if (in_array($badge->ID, $earned)) continue;

            $required = (int) get_post_meta($badge->ID, 'points_required', true);
            if ($required > 0 && $total_score >= $required) {
                $earned[] = $badge->ID;
                update_user_meta($user_id, 'rejimde_earned_badges', $earned);
            }
        }
    }
    
    public function get_user_history($request) {
        $user_id = get_current_user_id();
        global $wpdb;
        $table_logs = $wpdb->prefix . 'rejimde_daily_logs';

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT log_date, score_daily, data_json FROM $table_logs WHERE user_id = %d ORDER BY log_date DESC LIMIT 7",
            $user_id
        ));

        $history = [];
        foreach ($logs as $log) {
            $history[] = [
                'date' => $log->log_date,
                'score' => (int) $log->score_daily,
                'details' => json_decode($log->data_json)
            ];
        }

        return $this->success($history);
    }
    
    /**
     * Get user's streak information
     */
    public function get_streak($request) {
        $user_id = get_current_user_id();
        $streakService = new \Rejimde\Services\StreakService();
        
        $streak = $streakService->getStreak($user_id, 'daily_login');
        
        return $this->success($streak);
    }
    
    /**
     * Get user's milestones
     */
    public function get_milestones($request) {
        $user_id = get_current_user_id();
        $limit = (int) ($request->get_param('limit') ?: 50);
        
        $milestoneService = new \Rejimde\Services\MilestoneService();
        $milestones = $milestoneService->getUserMilestones($user_id, $limit);
        
        return $this->success($milestones);
    }
    
    /**
     * Log a milestone achievement
     * 
     * This endpoint allows manual milestone tracking for custom scenarios
     * that may not be automatically tracked by the event system.
     */
    public function log_milestone($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        // Validate required parameters
        $milestoneType = sanitize_text_field($params['milestone_type'] ?? '');
        $entityId = isset($params['entity_id']) ? (int) $params['entity_id'] : null;
        $currentValue = isset($params['current_value']) ? (int) $params['current_value'] : null;
        
        if (empty($milestoneType)) {
            return $this->error('Milestone type is required', 400);
        }
        
        if ($entityId === null) {
            return $this->error('Entity ID is required', 400);
        }
        
        if ($currentValue === null) {
            return $this->error('Current value is required', 400);
        }
        
        // Check and award milestone
        $milestoneService = new \Rejimde\Services\MilestoneService();
        $milestone = $milestoneService->checkAndAward(
            $user_id,
            $milestoneType,
            $entityId,
            $currentValue
        );
        
        if ($milestone) {
            // Award points
            $scoreService = new \Rejimde\Services\ScoreService();
            $circleId = get_user_meta($user_id, 'circle_id', true);
            
            // Verify circle still exists before passing to awardPoints
            if ($circleId) {
                $circle = get_post($circleId);
                if (!$circle || $circle->post_type !== 'rejimde_circle') {
                    // Circle deleted, clean up user meta
                    delete_user_meta($user_id, 'circle_id');
                    delete_user_meta($user_id, 'circle_role');
                    $circleId = null;
                }
            }
            
            $scoreResult = $scoreService->awardPoints($user_id, $milestone['points'], $circleId ?: null);
            
            // Log milestone event
            $eventService = new \Rejimde\Services\EventService();
            $eventService->log(
                $user_id,
                'milestone_' . $milestoneType,
                $milestone['points'],
                'milestone',
                $entityId,
                ['milestone_value' => $milestone['milestone_value']]
            );
            
            return $this->success([
                'milestone_awarded' => true,
                'milestone_type' => $milestoneType,
                'milestone_value' => $milestone['milestone_value'],
                'points_earned' => $milestone['points'],
                'total_score' => $scoreResult['total_score'],
                'daily_score' => $scoreResult['daily_score']
            ]);
        } else {
            return $this->success([
                'milestone_awarded' => false,
                'message' => 'No milestone reached or already awarded'
            ]);
        }
    }
    
    /**
     * Get user's events
     */
    public function get_events($request) {
        $user_id = get_current_user_id();
        $limit = (int) ($request->get_param('limit') ?: 50);
        $offset = (int) ($request->get_param('offset') ?: 0);
        
        $eventService = new \Rejimde\Services\EventService();
        $events = $eventService->getUserEvents($user_id, $limit, $offset);
        
        return $this->success($events);
    }
    
    /**
     * Get user's circle account information
     * 
     * Returns the user's circle membership details including:
     * - Circle information
     * - User's contribution to circle score
     * - User's role in circle
     * - Circle members and their scores
     */
    public function get_circle_account($request) {
        $user_id = get_current_user_id();
        $circle_id = get_user_meta($user_id, 'circle_id', true);
        
        // User not in a circle
        if (!$circle_id) {
            return $this->success([
                'in_circle' => false,
                'circle' => null,
                'user_contribution' => 0,
                'user_role' => null
            ]);
        }
        
        // Get circle information
        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            // Circle doesn't exist anymore, clean up user meta
            delete_user_meta($user_id, 'circle_id');
            delete_user_meta($user_id, 'circle_role');
            
            return $this->success([
                'in_circle' => false,
                'circle' => null,
                'user_contribution' => 0,
                'user_role' => null
            ]);
        }
        
        // Get user's role in circle
        $user_role = get_user_meta($user_id, 'circle_role', true);
        
        // Get user's total score (their contribution)
        $user_score = (int) get_user_meta($user_id, 'rejimde_total_score', true);
        
        // Get all circle members with their meta data in one query
        $members = get_users([
            'meta_key' => 'circle_id',
            'meta_value' => $circle_id,
            'fields' => 'all_with_meta'
        ]);
        
        $members_data = [];
        $total_circle_score = 0;
        
        foreach ($members as $member) {
            $member_score = isset($member->rejimde_total_score) ? (int) $member->rejimde_total_score : 0;
            $total_circle_score += $member_score;
            
            $members_data[] = [
                'id' => $member->ID,
                'name' => $member->display_name,
                'avatar' => isset($member->avatar_url) ? $member->avatar_url : null,
                'score' => $member_score,
                'role' => isset($member->circle_role) ? $member->circle_role : 'member',
                'is_current_user' => $member->ID === $user_id
            ];
        }
        
        // Sort members by score (highest first)
        usort($members_data, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Calculate user's contribution percentage
        $contribution_percentage = $total_circle_score > 0 
            ? round(($user_score / $total_circle_score) * 100, 2) 
            : 0;
        
        return $this->success([
            'in_circle' => true,
            'circle' => [
                'id' => $circle_id,
                'name' => $circle->post_title,
                'slug' => $circle->post_name,
                'logo' => get_post_meta($circle_id, 'circle_logo_url', true),
                'total_score' => $total_circle_score,
                'member_count' => count($members_data),
                'motto' => get_post_meta($circle_id, 'motto', true),
                'privacy' => get_post_meta($circle_id, 'privacy', true) ?: 'public'
            ],
            'user_role' => $user_role,
            'user_contribution' => $user_score,
            'user_contribution_percentage' => $contribution_percentage,
            'members' => $members_data
        ]);
    }

    /**
     * Level-based leaderboard endpoint
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_level_leaderboard($request) {
        $level_slug = $request->get_param('level_slug');
        $type = $request->get_param('type') ?: 'users';
        $limit = (int) ($request->get_param('limit') ?: 50);
        
        // Validate level_slug
        if (empty($level_slug)) {
            return $this->error('level_slug parameter is required', 400);
        }
        
        $level_info = $this->get_level_bounds($level_slug);
        if (!$level_info) {
            return $this->error('Invalid level_slug', 400);
        }
        
        // Get period end date
        $period_end = $this->get_period_end_date();
        
        // Promotion and relegation counts
        $promotion_count = 5;
        $relegation_count = 5;
        
        $users_data = [];
        $circles_data = [];
        $current_user_data = null;
        
        if ($type === 'circles') {
            // Circle leaderboard for this level
            $args = [
                'post_type' => 'rejimde_circle',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => 'total_score',
                        'value' => $level_info['min'],
                        'type' => 'NUMERIC',
                        'compare' => '>='
                    ],
                    [
                        'key' => 'total_score',
                        'value' => $level_info['max'],
                        'type' => 'NUMERIC',
                        'compare' => '<'
                    ]
                ],
                'meta_key' => 'total_score',
                'orderby' => 'meta_value_num',
                'order' => 'DESC'
            ];
            
            $query = new WP_Query($args);
            $rank = 1;
            $total_circles = $query->post_count;
            
            foreach ($query->posts as $post) {
                $score = (int) get_post_meta($post->ID, 'total_score', true);
                $zone = $this->get_zone($rank, $total_circles, $promotion_count, $relegation_count);
                
                $circles_data[] = [
                    'rank' => $rank,
                    'id' => $post->ID,
                    'name' => $post->post_title,
                    'slug' => $post->post_name,
                    'logo' => get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: null,
                    'score' => $score,
                    'zone' => $zone
                ];
                
                $rank++;
                
                if ($rank > $limit) {
                    break;
                }
            }
        } else {
            // User leaderboard for this level
            $user_query = new \WP_User_Query([
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => 'rejimde_total_score',
                        'value' => $level_info['min'],
                        'type' => 'NUMERIC',
                        'compare' => '>='
                    ],
                    [
                        'key' => 'rejimde_total_score',
                        'value' => $level_info['max'],
                        'type' => 'NUMERIC',
                        'compare' => '<'
                    ]
                ],
                'orderby' => 'meta_value_num',
                'order' => 'DESC',
                'number' => -1,
                'role__not_in' => ['administrator', 'rejimde_pro'],
                'fields' => 'all_with_meta'
            ]);
            
            $users = $user_query->get_results();
            $total_users = count($users);
            $current_user_id = get_current_user_id();
            $current_user_index = -1;
            
            $rank = 1;
            foreach ($users as $index => $user) {
                $score = (int) get_user_meta($user->ID, 'rejimde_total_score', true);
                $zone = $this->get_zone($rank, $total_users, $promotion_count, $relegation_count);
                $is_current_user = ($user->ID === $current_user_id);
                
                if ($is_current_user) {
                    $current_user_index = $index;
                }
                
                $user_entry = [
                    'rank' => $rank,
                    'id' => $user->ID,
                    'name' => $user->display_name ?: $user->user_login,
                    'slug' => $user->user_login,
                    'avatar' => get_user_meta($user->ID, 'avatar_url', true),
                    'score' => $score,
                    'zone' => $zone,
                    'is_current_user' => $is_current_user
                ];
                
                if ($rank <= $limit) {
                    $users_data[] = $user_entry;
                }
                
                $rank++;
            }
            
            // Calculate current user data if logged in
            if ($current_user_id && $current_user_index >= 0) {
                $current_user = $users[$current_user_index];
                $current_score = (int) get_user_meta($current_user_id, 'rejimde_total_score', true);
                $current_rank = $current_user_index + 1;
                $current_zone = $this->get_zone($current_rank, $total_users, $promotion_count, $relegation_count);
                
                $current_user_data = [
                    'id' => $current_user_id,
                    'rank' => $current_rank,
                    'score' => $current_score,
                    'zone' => $current_zone,
                    'points_to_promotion' => $this->calculate_points_to_promotion($current_user_index, $users, $promotion_count),
                    'points_to_relegation' => $this->calculate_points_to_relegation($current_user_index, $users, $total_users, $relegation_count),
                    'in_this_level' => true
                ];
            } elseif ($current_user_id) {
                // User is not in this level
                $current_score = (int) get_user_meta($current_user_id, 'rejimde_total_score', true);
                $user_level = $this->calculate_level($current_score);
                
                $current_user_data = [
                    'id' => $current_user_id,
                    'rank' => null,
                    'score' => $current_score,
                    'zone' => null,
                    'points_to_promotion' => null,
                    'points_to_relegation' => null,
                    'in_this_level' => false,
                    'current_level' => $user_level['slug'],
                    'message' => 'You are currently in ' . $user_level['name'] . ' level'
                ];
            }
        }
        
        return $this->success([
            'level' => [
                'min' => $level_info['min'],
                'max' => $level_info['max'],
                'level' => $level_info['level'],
                'name' => $level_info['name'],
                'next' => $level_info['next'],
                'prev' => $level_info['prev']
            ],
            'period_ends_at' => $period_end->format('Y-m-d H:i:s'),
            'period_ends_timestamp' => $period_end->getTimestamp(),
            'promotion_count' => $promotion_count,
            'relegation_count' => $relegation_count,
            'users' => $users_data,
            'circles' => $circles_data,
            'current_user' => $current_user_data
        ]);
    }
    
    /**
     * Get level bounds by slug
     * 
     * @param string $slug
     * @return array|null
     */
    private function get_level_bounds($slug) {
        $levels = [
            'begin' => ['min' => 0, 'max' => 200, 'level' => 1, 'name' => 'Begin', 'next' => 'adapt', 'prev' => null],
            'adapt' => ['min' => 200, 'max' => 300, 'level' => 2, 'name' => 'Adapt', 'next' => 'commit', 'prev' => 'begin'],
            'commit' => ['min' => 300, 'max' => 500, 'level' => 3, 'name' => 'Commit', 'next' => 'balance', 'prev' => 'adapt'],
            'balance' => ['min' => 500, 'max' => 1000, 'level' => 4, 'name' => 'Balance', 'next' => 'strengthen', 'prev' => 'commit'],
            'strengthen' => ['min' => 1000, 'max' => 2000, 'level' => 5, 'name' => 'Strengthen', 'next' => 'sustain', 'prev' => 'balance'],
            'sustain' => ['min' => 2000, 'max' => 4000, 'level' => 6, 'name' => 'Sustain', 'next' => 'mastery', 'prev' => 'strengthen'],
            'mastery' => ['min' => 4000, 'max' => 6000, 'level' => 7, 'name' => 'Mastery', 'next' => 'transform', 'prev' => 'sustain'],
            'transform' => ['min' => 6000, 'max' => 10000, 'level' => 8, 'name' => 'Transform', 'next' => null, 'prev' => 'mastery'],
        ];
        return $levels[$slug] ?? null;
    }
    
    /**
     * Get period end date (Sunday 23:59:59 Istanbul time)
     * 
     * @return \DateTime
     */
    private function get_period_end_date() {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Istanbul'));
        $dayOfWeek = (int) $now->format('N'); // 1 (Monday) to 7 (Sunday)
        
        $end = clone $now;
        
        // If today is Sunday (7), keep it; otherwise calculate days until next Sunday
        if ($dayOfWeek < 7) {
            $daysUntilSunday = 7 - $dayOfWeek;
            $end->modify("+{$daysUntilSunday} days");
        }
        
        $end->setTime(23, 59, 59);
        return $end;
    }
    
    /**
     * Calculate zone for user/circle
     * 
     * @param int $rank
     * @param int $total
     * @param int $promotion_count
     * @param int $relegation_count
     * @return string
     */
    private function get_zone($rank, $total, $promotion_count, $relegation_count) {
        if ($rank <= $promotion_count) return 'promotion';
        if ($total - $rank < $relegation_count) return 'relegation';
        return 'safe';
    }
    
    /**
     * Calculate points needed to reach promotion zone
     * 
     * @param int $current_index
     * @param array $users
     * @param int $promotion_count
     * @return int|null
     */
    private function calculate_points_to_promotion($current_index, $users, $promotion_count) {
        if ($current_index < $promotion_count) {
            return 0; // Already in promotion zone
        }
        
        // Need to beat the last promotion zone user
        $target_index = $promotion_count - 1;
        if (isset($users[$target_index])) {
            $current_score = (int) get_user_meta($users[$current_index]->ID, 'rejimde_total_score', true);
            $target_score = (int) get_user_meta($users[$target_index]->ID, 'rejimde_total_score', true);
            return max(0, $target_score - $current_score + 1);
        }
        
        return null;
    }
    
    /**
     * Calculate points difference from relegation zone
     * 
     * @param int $current_index
     * @param array $users
     * @param int $total
     * @param int $relegation_count
     * @return int|null
     */
    private function calculate_points_to_relegation($current_index, $users, $total, $relegation_count) {
        $relegation_start = $total - $relegation_count;
        
        if ($current_index >= $relegation_start) {
            return 0; // Already in relegation zone
        }
        
        // Points above the first relegation zone user
        $first_relegation_index = $relegation_start;
        if (isset($users[$first_relegation_index])) {
            $current_score = (int) get_user_meta($users[$current_index]->ID, 'rejimde_total_score', true);
            $relegation_score = (int) get_user_meta($users[$first_relegation_index]->ID, 'rejimde_total_score', true);
            return max(0, $current_score - $relegation_score);
        }
        
        return null;
    }
    
    /**
     * Calculate user's rank within their level
     * 
     * @param int $user_id
     * @param array $level
     * @param int $user_score
     * @return int
     */
    private function calculate_user_rank_in_level($user_id, $level, $user_score) {
        // Query users in the same level, sorted by score
        $user_query = new \WP_User_Query([
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'rejimde_total_score',
                    'value' => $level['min'],
                    'type' => 'NUMERIC',
                    'compare' => '>='
                ],
                [
                    'key' => 'rejimde_total_score',
                    'value' => $level['max'],
                    'type' => 'NUMERIC',
                    'compare' => '<'
                ]
            ],
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'number' => -1,
            'role__not_in' => ['administrator', 'rejimde_pro'],
            'fields' => 'ID'
        ]);
        
        $users = $user_query->get_results();
        
        // Find user's position in the sorted list
        $rank = 1;
        foreach ($users as $uid) {
            if ($uid == $user_id) {
                return $rank;
            }
            $rank++;
        }
        
        // If user not found in level (shouldn't happen), return 1
        return 1;
    }

    public function check_auth($request) { return is_user_logged_in(); }
    protected function success($data = null) { return new WP_REST_Response(['status' => 'success', 'data' => $data], 200); }
    protected function error($message = 'Error', $code = 400) { return new WP_REST_Response(['status' => 'error', 'message' => $message], $code); }
}