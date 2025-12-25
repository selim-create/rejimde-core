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
        
        $earned_badges = get_user_meta($user_id, 'rejimde_earned_badges', true);
        if (!is_array($earned_badges)) $earned_badges = [];

        return $this->success([
            'daily_score' => $daily_score,
            'total_score' => $total_score,
            'rank' => $rank,           // Kullanıcı deneyim seviyesi
            'level' => $level,         // Puan bazlı level
            'earned_badges' => $earned_badges
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
        $eventType = sanitize_text_field($params['action'] ?? '');
        
        if (empty($eventType)) {
            return $this->error('Event type is required', 400);
        }
        
        // Build payload for EventDispatcher
        $payload = [
            'user_id' => get_current_user_id(),
            'entity_type' => sanitize_text_field($params['entity_type'] ?? null),
            'entity_id' => isset($params['ref_id']) ? (int) $params['ref_id'] : null,
            'context' => []
        ];
        
        // Add any additional context
        if (isset($params['context']) && is_array($params['context'])) {
            $payload['context'] = $params['context'];
        }
        
        // Dispatch event
        $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
        $result = $dispatcher->dispatch($eventType, $payload);
        
        if ($result['success']) {
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
        
        // Get all circle members
        $members = get_users([
            'meta_key' => 'circle_id',
            'meta_value' => $circle_id,
            'fields' => ['ID', 'display_name']
        ]);
        
        $members_data = [];
        $total_circle_score = 0;
        
        foreach ($members as $member) {
            $member_score = (int) get_user_meta($member->ID, 'rejimde_total_score', true);
            $total_circle_score += $member_score;
            
            $members_data[] = [
                'id' => $member->ID,
                'name' => $member->display_name,
                'avatar' => get_user_meta($member->ID, 'avatar_url', true),
                'score' => $member_score,
                'role' => get_user_meta($member->ID, 'circle_role', true),
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

    public function check_auth($request) { return is_user_logged_in(); }
    protected function success($data = null) { return new WP_REST_Response(['status' => 'success', 'data' => $data], 200); }
    protected function error($message = 'Error', $code = 400) { return new WP_REST_Response(['status' => 'error', 'message' => $message], $code); }
}