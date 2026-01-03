<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

class ProfileController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'profile';

    public function register_routes() {
        // Kullanıcı Profilini Getir (Username ile)
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<username>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_profile_by_username'],
            'permission_callback' => '__return_true',
        ]);

        // Takip Et / Bırak
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/follow', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_follow'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Beşlik Çak (High Five)
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/high-five', [
            'methods' => 'POST',
            'callback' => [$this, 'send_high_five'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
        
        // Takip Edilen Kullanıcılar ve Aktiviteleri
        register_rest_route($this->namespace, '/' . $this->base . '/following', [
            'methods' => 'GET',
            'callback' => [$this, 'get_following'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    public function check_auth() {
        return is_user_logged_in();
    }

    /**
     * Kullanıcı Profilini Username ile Getir
     * Hem rejimde_pro (uzman) hem de rejimde_user rollerini destekler
     */
    public function get_profile_by_username($request) {
        $username = sanitize_user($request->get_param('username'));
        
        if (empty($username)) {
            return new WP_Error('missing_username', 'Kullanıcı adı gerekli', ['status' => 400]);
        }

        // Username ile kullanıcıyı bul
        $user = get_user_by('login', $username);
        
        // Nicename ile de dene
        if (!$user) {
            global $wpdb;
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM $wpdb->users WHERE user_nicename = %s",
                $username
            ));
            if ($user_id) {
                $user = get_user_by('id', $user_id);
            }
        }
        
        if (!$user) {
            return new WP_Error('user_not_found', 'Kullanıcı Bulunamadı', ['status' => 404]);
        }

        $user_id = $user->ID;
        
        // Update last activity for the current user viewing the profile
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            update_user_meta($current_user_id, 'last_activity', time());
        }
        
        $roles = (array) $user->roles;
        $is_expert = in_array('rejimde_pro', $roles);

        // Temel kullanıcı bilgileri
        $profile_data = [
            'id' => $user_id,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'roles' => $roles,
            'is_expert' => $is_expert,
        ];

        // Frontend uyumu için eksik alanlar
        $profile_data['slug'] = $user->user_nicename;
        $profile_data['name'] = $user->display_name;
        $profile_data['description'] = get_user_meta($user_id, 'description', true) ?: '';
        $profile_data['registered_date'] = $user->user_registered;
        
        // Lokasyon
        $profile_data['location'] = get_user_meta($user_id, 'location', true) ?: '';
        $profile_data['city'] = get_user_meta($user_id, 'city', true) ?: '';
        $profile_data['district'] = get_user_meta($user_id, 'district', true) ?: '';

        // Avatar
        $custom_avatar = get_user_meta($user_id, 'avatar_url', true);
        $profile_data['avatar_url'] = $custom_avatar ?: 'https://api.dicebear.com/9.x/personas/svg?seed=' . urlencode($user->user_login);

        // Profile URL
        if ($is_expert) {
            $professional_id = get_user_meta($user_id, 'related_pro_post_id', true);
            if ($professional_id) {
                $professional_post = get_post($professional_id);
                if ($professional_post && $professional_post->post_status === 'publish') {
                    $profile_data['profile_url'] = '/experts/' . $professional_post->post_name;
                    $profile_data['professional_id'] = $professional_id;
                } else {
                    $profile_data['profile_url'] = '/profile/' . $user->user_nicename;
                }
            } else {
                $profile_data['profile_url'] = '/profile/' . $user->user_nicename;
            }
        } else {
            $profile_data['profile_url'] = '/profile/' . $user->user_nicename;
        }

        // Gamification data
        $profile_data['rank'] = (int) get_user_meta($user_id, 'rejimde_rank', true) ?: 1;
        $profile_data['total_score'] = (int) get_user_meta($user_id, 'rejimde_total_score', true) ?: 0;
        $profile_data['current_streak'] = (int) get_user_meta($user_id, 'current_streak', true) ?: 0;

        // Social data
        $followers = get_user_meta($user_id, 'rejimde_followers', true);
        $profile_data['followers_count'] = is_array($followers) ? count($followers) : 0;
        
        $following = get_user_meta($user_id, 'rejimde_following', true);
        $profile_data['following_count'] = is_array($following) ? count($following) : 0;

        $profile_data['high_fives'] = (int) get_user_meta($user_id, 'rejimde_high_fives', true);

        // Check if current user follows this profile
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            $profile_data['is_following'] = is_array($followers) && in_array($current_user_id, $followers);
            
            // Beşlik çakılmış mı?
            $last_high_five = get_user_meta($current_user_id, 'last_high_five_' . $user_id, true);
            $has_high_fived_today = $last_high_five && (time() - $last_high_five) < 86400; // 24 saat
            $profile_data['has_high_fived'] = $has_high_fived_today;
            $profile_data['has_high_fived_today'] = $has_high_fived_today; // Alias for frontend compatibility
        } else {
            $profile_data['is_following'] = false;
            $profile_data['has_high_fived'] = false;
            $profile_data['has_high_fived_today'] = false;
        }

        // Badges
        $earned_badges = get_user_meta($user_id, 'rejimde_earned_badges', true);
        $profile_data['earned_badges'] = is_array($earned_badges) ? array_map('intval', $earned_badges) : [];

        // Circle info
        $circle_id = get_user_meta($user_id, 'circle_id', true);
        if ($circle_id) {
            $circle = get_post($circle_id);
            if ($circle && $circle->post_status === 'publish') {
                $profile_data['circle'] = [
                    'id' => $circle->ID,
                    'name' => $circle->post_title,
                    'slug' => $circle->post_name,
                    'logo' => get_post_meta($circle->ID, 'circle_logo_url', true)
                ];
            }
        }

        // Level bilgisi
        $total_score = $profile_data['total_score'];
        $profile_data['level'] = $this->calculate_level($total_score);

        // Expert-specific data
        if ($is_expert) {
            $profile_data['title'] = get_user_meta($user_id, 'title', true) ?: '';
            $profile_data['bio'] = get_user_meta($user_id, 'bio', true) ?: '';
            $profile_data['profession'] = get_user_meta($user_id, 'profession', true) ?: '';
            $profile_data['career_start_date'] = get_user_meta($user_id, 'career_start_date', true) ?: '';
            
            $is_verified_meta = get_user_meta($user_id, 'is_verified', true);
            $profile_data['is_verified'] = $is_verified_meta === '1' || $is_verified_meta === true;
            
            // Get rating from professional profile if exists
            if (!empty($profile_data['professional_id'])) {
                $profile_data['rating'] = get_post_meta($profile_data['professional_id'], 'puan', true) ?: '0.0';
                $profile_data['review_count'] = (int) get_post_meta($profile_data['professional_id'], 'review_count', true) ?: 0;
            }
        }

        // İçerik sayısı
        $profile_data['content_count'] = $this->get_user_content_count($user_id);

        return new WP_REST_Response($profile_data, 200);
    }

    /**
     * Takip Et / Bırak
     */
    public function toggle_follow($request) {
        $target_id = (int) $request->get_param('id');
        $current_user_id = get_current_user_id();

        if ($target_id === $current_user_id) {
            return new WP_Error('invalid_action', 'Kendinizi takip edemezsiniz.', ['status' => 400]);
        }

        // Target'ın Takipçileri
        $followers = get_user_meta($target_id, 'rejimde_followers', true);
        if (!is_array($followers)) $followers = [];

        // Current'ın Takip Ettikleri
        $following = get_user_meta($current_user_id, 'rejimde_following', true);
        if (!is_array($following)) $following = [];

        $is_following = false;

        if (in_array($current_user_id, $followers)) {
            // Unfollow
            $followers = array_diff($followers, [$current_user_id]);
            $following = array_diff($following, [$target_id]);
            $message = 'Takipten çıkıldı.';
        } else {
            // Follow
            $followers[] = $current_user_id;
            $following[] = $target_id;
            $is_following = true;
            $message = 'Takip edildi!';
            
            // Dispatch follow_accepted event
            $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
            $dispatcher->dispatch('follow_accepted', [
                'follower_id' => $current_user_id,
                'followed_id' => $target_id
            ]);
        }

        update_user_meta($target_id, 'rejimde_followers', array_values($followers));
        update_user_meta($current_user_id, 'rejimde_following', array_values($following));

        return new WP_REST_Response([
            'success' => true,
            'is_following' => $is_following,
            'followers_count' => count($followers),
            'message' => $message
        ], 200);
    }

    /**
     * Beşlik Gönder
     */
    public function send_high_five($request) {
        $target_id = (int) $request->get_param('id');
        $current_user_id = get_current_user_id();

        if ($target_id === $current_user_id) {
            return new WP_Error('invalid_action', 'Kendine beşlik çakamazsın :)', ['status' => 400]);
        }
        
        // Dispatch highfive_sent event
        $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
        $result = $dispatcher->dispatch('highfive_sent', [
            'user_id' => $current_user_id,
            'context' => [
                'target_user_id' => $target_id
            ]
        ]);
        
        if (!$result['success']) {
            return new WP_Error('rate_limit', $result['message'], ['status' => 429]);
        }

        // Sayacı artır
        $count = (int) get_user_meta($target_id, 'rejimde_high_fives', true);
        update_user_meta($target_id, 'rejimde_high_fives', $count + 1);

        // Son beşlik çakma zamanını kaydet (24 saat kontrolü için)
        update_user_meta($current_user_id, 'last_high_five_' . $target_id, time());

        return new WP_REST_Response([
            'success' => true,
            'count' => $count + 1,
            'message' => 'Beşlik gönderildi! ✋'
        ], 200);
    }

    /**
     * Calculate level based on score
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
     * Get following users with their last activities
     */
    public function get_following($request) {
        $user_id = get_current_user_id();
        
        // Get list of users that current user follows
        $following = get_user_meta($user_id, 'rejimde_following', true);
        
        if (!is_array($following) || empty($following)) {
            return new WP_REST_Response([
                'status' => 'success',
                'data' => [],
                'total_following' => 0,
                'message' => 'Henüz kimseyi takip etmiyorsun.'
            ], 200);
        }
        
        global $wpdb;
        $table_events = $wpdb->prefix . 'rejimde_events';
        
        // Fetch last activities for all followed users in a single query
        $user_ids_placeholder = implode(',', array_fill(0, count($following), '%d'));
        $query = "
            SELECT e1.user_id, e1.event_type, e1.created_at, e1.context
            FROM $table_events e1
            INNER JOIN (
                SELECT user_id, MAX(created_at) as max_created_at
                FROM $table_events
                WHERE user_id IN ($user_ids_placeholder)
                GROUP BY user_id
            ) e2 ON e1.user_id = e2.user_id AND e1.created_at = e2.max_created_at
        ";
        
        $last_events = $wpdb->get_results($wpdb->prepare($query, ...$following), OBJECT_K);
        
        $data = [];
        
        foreach ($following as $followed_user_id) {
            $user = get_user_by('id', $followed_user_id);
            if (!$user) {
                continue; // Skip if user doesn't exist anymore
            }
            
            // Get last event for this user from our pre-fetched results
            $last_event = isset($last_events[$followed_user_id]) ? $last_events[$followed_user_id] : null;
            
            $last_activity = null;
            if ($last_event) {
                $activity_info = $this->map_event_to_activity($last_event->event_type, $last_event->context);
                $last_activity = [
                    'type' => $last_event->event_type,
                    'label' => $activity_info['label'],
                    'icon' => $activity_info['icon'],
                    'time_ago' => $this->time_ago($last_event->created_at)
                ];
            }
            
            // Get avatar
            $custom_avatar = get_user_meta($followed_user_id, 'avatar_url', true);
            $avatar_url = $custom_avatar ?: 'https://api.dicebear.com/9.x/personas/svg?seed=' . urlencode($user->user_login);
            
            $data[] = [
                'id' => $followed_user_id,
                'name' => $user->display_name,
                'slug' => $user->user_nicename,
                'avatar_url' => $avatar_url,
                'last_activity' => $last_activity
            ];
        }
        
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data,
            'total_following' => count($following)
        ], 200);
    }
    
    /**
     * Map event type to user-friendly label and icon
     */
    private function map_event_to_activity($event_type, $context_json = null) {
        $context = $context_json ? json_decode($context_json, true) : [];
        
        $activity_map = [
            'water_added' => ['label' => 'Su hedefini tamamladı', 'icon' => '💧'],
            'steps_logged' => ['label' => 'Adım hedefini tamamladı', 'icon' => '👟'],
            'meal_photo_uploaded' => ['label' => 'Öğün fotoğrafı yükledi', 'icon' => '📸'],
            'diet_completed' => ['label' => 'Diyet tamamladı', 'icon' => '🥗'],
            'exercise_completed' => ['label' => 'Egzersiz tamamladı', 'icon' => '💪'],
            'login_success' => ['label' => 'Giriş yaptı', 'icon' => '✅'],
            'blog_points_claimed' => ['label' => 'Blog okudu', 'icon' => '📚'],
            'comment_created' => ['label' => 'Yorum yaptı', 'icon' => '💬'],
            'highfive_sent' => ['label' => 'Beşlik çaktı', 'icon' => '✋'],
            'follow_accepted' => ['label' => 'Birini takip etti', 'icon' => '👥'],
            'calculator_saved' => ['label' => 'Hesaplayıcı kullandı', 'icon' => '🧮'],
            'circle_joined' => ['label' => "Circle'a katıldı", 'icon' => '🎯'],
            'diet_started' => ['label' => 'Diyet başlattı', 'icon' => '🍽️'],
            'exercise_started' => ['label' => 'Egzersiz başlattı', 'icon' => '🏃'],
            'rating_submitted' => ['label' => 'Uzman değerlendirdi', 'icon' => '⭐'],
        ];
        
        // Check for milestone events
        if (strpos($event_type, 'milestone_') === 0) {
            return ['label' => 'Bir başarı kazandı', 'icon' => '🏆'];
        }
        
        // Return mapped activity or default
        return $activity_map[$event_type] ?? ['label' => 'Aktivite gerçekleştirdi', 'icon' => '📌'];
    }
    
    /**
     * Calculate time ago from timestamp
     */
    private function time_ago($datetime) {
        $now = new \DateTime();
        $past = new \DateTime($datetime);
        $diff = $now->diff($past);
        
        if ($diff->y > 0) {
            return $diff->y . ' yıl önce';
        } elseif ($diff->m > 0) {
            return $diff->m . ' ay önce';
        } elseif ($diff->d > 0) {
            return $diff->d . ' gün önce';
        } elseif ($diff->h > 0) {
            return $diff->h . ' saat önce';
        } elseif ($diff->i > 0) {
            return $diff->i . ' dakika önce';
        } else {
            return 'Az önce';
        }
    }

    /**
     * Yardımcı metod: Kullanıcının içerik sayısı
     */
    private function get_user_content_count($user_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->posts 
            WHERE post_author = %d 
            AND post_status = 'publish' 
            AND post_type IN ('post', 'rejimde_plan', 'rejimde_exercise')",
            $user_id
        ));
        
        return (int) $count;
    }
}