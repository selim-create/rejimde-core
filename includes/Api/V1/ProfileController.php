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
        
        if (!$user) {
            return new WP_Error('user_not_found', 'Kullanıcı Bulunamadı', ['status' => 404]);
        }

        $user_id = $user->ID;
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
        $profile_data['description'] = $user->description ?: get_user_meta($user_id, 'description', true) ?: '';
        $profile_data['registered_date'] = $user->user_registered;
        $profile_data['location'] = get_user_meta($user_id, 'location', true) ?: '';
        $profile_data['gender'] = get_user_meta($user_id, 'gender', true) ?: '';

        // Avatar
        $custom_avatar = get_user_meta($user_id, 'avatar_url', true);
        $profile_data['avatar_url'] = $custom_avatar ?: 'https://api.dicebear.com/9.x/personas/svg?seed=' . urlencode($user->user_login);

        // Profile URL
        if ($is_expert) {
            $professional_id = get_user_meta($user_id, 'professional_profile_id', true);
            if ($professional_id) {
                $professional_post = get_post($professional_id);
                if ($professional_post && $professional_post->post_status === 'publish') {
                    $profile_data['profile_url'] = '/experts/' . $professional_post->post_name;
                    $profile_data['professional_id'] = $professional_id;
                } else {
                    $profile_data['profile_url'] = '/profile/' . $username;
                }
            } else {
                $profile_data['profile_url'] = '/profile/' . $username;
            }
        } else {
            $profile_data['profile_url'] = '/profile/' . $username;
        }

        // Gamification data
        $profile_data['level'] = (int) get_user_meta($user_id, 'rejimde_level', true) ?: 1;
        $profile_data['total_score'] = (int) get_user_meta($user_id, 'rejimde_total_score', true) ?: 0;
        $profile_data['current_streak'] = (int) get_user_meta($user_id, 'current_streak', true) ?: 0;

        // League bilgisi (Frontend bunu bekliyor)
        $total_score = $profile_data['total_score'];
        $profile_data['league'] = $this->calculate_league($total_score);

        // Gamification alanlarını frontend'in beklediği isimlerle de dön
        $profile_data['rejimde_level'] = $profile_data['level'];
        $profile_data['rejimde_total_score'] = $profile_data['total_score'];

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
        } else {
            $profile_data['is_following'] = false;
        }

        // Badges
        $earned_badges = get_user_meta($user_id, 'rejimde_earned_badges', true);
        $profile_data['earned_badges'] = is_array($earned_badges) ? array_map('intval', $earned_badges) : [];
        $profile_data['rejimde_earned_badges'] = $profile_data['earned_badges'];

        // Clan info
        $clan_id = get_user_meta($user_id, 'clan_id', true);
        if ($clan_id) {
            $clan = get_post($clan_id);
            if ($clan && $clan->post_status === 'publish') {
                $profile_data['clan'] = [
                    'id' => $clan->ID,
                    'name' => $clan->post_title,
                    'slug' => $clan->post_name,
                    'logo' => get_post_meta($clan->ID, 'clan_logo_url', true)
                ];
            }
        }

        // Expert-specific data
        if ($is_expert) {
            $profile_data['title'] = get_user_meta($user_id, 'title', true) ?: '';
            $profile_data['bio'] = get_user_meta($user_id, 'bio', true) ?: '';
            $profile_data['profession'] = get_user_meta($user_id, 'profession', true) ?: '';
            
            $is_verified_meta = get_user_meta($user_id, 'is_verified', true);
            $profile_data['is_verified'] = $is_verified_meta === '1' || $is_verified_meta === true;
            
            // Get rating from professional profile if exists
            if (!empty($profile_data['professional_id'])) {
                $profile_data['rating'] = get_post_meta($profile_data['professional_id'], 'puan', true) ?: '0.0';
                $profile_data['review_count'] = get_post_meta($profile_data['professional_id'], 'review_count', true) ?: 0;
            }
        }

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
            
            // Bildirim oluşturulabilir...
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
        
        // Spam kontrolü (Basit: Son 1 saatte gönderdi mi?)
        $last_high_five = get_user_meta($current_user_id, 'last_high_five_' . $target_id, true);
        if ($last_high_five && (time() - $last_high_five) < 3600) {
             return new WP_Error('rate_limit', 'Biraz beklemen lazım.', ['status' => 429]);
        }

        // Sayacı artır
        $count = (int) get_user_meta($target_id, 'rejimde_high_fives', true);
        update_user_meta($target_id, 'rejimde_high_fives', $count + 1);
        
        // Zaman damgası kaydet
        update_user_meta($current_user_id, 'last_high_five_' . $target_id, time());

        return new WP_REST_Response([
            'success' => true,
            'count' => $count + 1,
            'message' => 'Beşlik gönderildi! ✋'
        ], 200);
    }

    /**
     * Calculate league based on score
     */
    private function calculate_league($score) {
        if ($score >= 10000) return ['id' => 'diamond', 'name' => 'Elmas Lig', 'slug' => 'diamond', 'icon' => 'fa-gem', 'color' => 'text-purple-600'];
        if ($score >= 5000) return ['id' => 'ruby', 'name' => 'Yakut Lig', 'slug' => 'ruby', 'icon' => 'fa-gem', 'color' => 'text-red-600'];
        if ($score >= 2000) return ['id' => 'sapphire', 'name' => 'Safir Lig', 'slug' => 'sapphire', 'icon' => 'fa-gem', 'color' => 'text-blue-600'];
        if ($score >= 1000) return ['id' => 'gold', 'name' => 'Altın Lig', 'slug' => 'gold', 'icon' => 'fa-crown', 'color' => 'text-yellow-600'];
        if ($score >= 500) return ['id' => 'silver', 'name' => 'Gümüş Lig', 'slug' => 'silver', 'icon' => 'fa-medal', 'color' => 'text-slate-500'];
        return ['id' => 'bronze', 'name' => 'Bronz Lig', 'slug' => 'bronze', 'icon' => 'fa-medal', 'color' => 'text-amber-700'];
    }
}