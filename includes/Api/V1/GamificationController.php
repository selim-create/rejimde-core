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
            'join_clan'         => ['points' => 100,'limit' => 1,  'label' => 'Klana Katılma'],
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
    }

    /**
     * LİDERLİK TABLOSU VE LİGLER (YENİ)
     * type=users (varsayılan) veya type=clans parametresi alır.
     */
    public function get_leaderboard($request) {
        $type = $request->get_param('type') ?: 'users';
        $limit = (int) ($request->get_param('limit') ?: 20);
        
        $data = [];

        if ($type === 'clans') {
            // --- KLAN SIRALAMASI ---
            $args = [
                'post_type' => 'rejimde_clan',
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
                    'league' => $this->calculate_league($score), // Lig bilgisi
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
                'role__not_in' => ['administrator'], // Adminleri gizle
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
                    'league' => $this->calculate_league($score) // Lig bilgisi
                ];
            }
        }

        return $this->success($data);
    }

    /**
     * PUANA GÖRE LİG HESAPLAMA (YARDIMCI)
     */
    private function calculate_league($score) {
        if ($score >= 10000) return ['id' => 'diamond', 'name' => 'Elmas Lig', 'color' => 'from-blue-400 to-cyan-300'];
        if ($score >= 5000) return ['id' => 'platinum', 'name' => 'Platin Lig', 'color' => 'from-slate-300 to-slate-100'];
        if ($score >= 2000) return ['id' => 'gold', 'name' => 'Altın Lig', 'color' => 'from-yellow-400 to-yellow-200'];
        if ($score >= 500) return ['id' => 'silver', 'name' => 'Gümüş Lig', 'color' => 'from-gray-400 to-gray-200'];
        return ['id' => 'bronze', 'name' => 'Bronz Lig', 'color' => 'from-orange-400 to-orange-200'];
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
        
        // Seviye yerine Lig bilgisini dönelim
        $league = $this->calculate_league($total_score);
        
        $earned_badges = get_user_meta($user_id, 'rejimde_earned_badges', true);
        if (!is_array($earned_badges)) $earned_badges = [];

        return $this->success([
            'daily_score' => $daily_score,
            'total_score' => $total_score,
            'league' => $league, // Seviye yerine Lig
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
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $action = sanitize_text_field($params['action'] ?? '');
        $ref_id = isset($params['ref_id']) ? sanitize_text_field($params['ref_id']) : null;

        $rules_json = get_option('rejimde_gamification_rules');
        $db_rules = !empty($rules_json) ? json_decode($rules_json, true) : [];
        $rules = array_merge($this->get_default_rules(), $db_rules);

        if (!isset($rules[$action])) {
            return $this->error('Geçersiz işlem: ' . $action . ' kuralı bulunamadı.', 400);
        }

        $rule = $rules[$action];
        $today = date('Y-m-d');
        global $wpdb;
        $table_logs = $wpdb->prefix . 'rejimde_daily_logs';
        
        $daily_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_logs WHERE user_id = %d AND log_date = %s", $user_id, $today));
        $actions_data = $daily_row ? json_decode($daily_row->data_json, true) : [];
        if (!is_array($actions_data)) $actions_data = [];

        $current_count = $actions_data[$action]['count'] ?? 0;
        
        if ($ref_id && isset($actions_data[$action]['refs']) && in_array($ref_id, $actions_data[$action]['refs'])) {
             return $this->error('Bu işlemden zaten puan aldınız.', 409);
        }

        if ($current_count >= $rule['limit']) {
            return $this->error('Günlük işlem limitine ulaştınız.', 403);
        }

        if (!isset($actions_data[$action])) $actions_data[$action] = ['count' => 0, 'refs' => []];
        $actions_data[$action]['count']++;
        if ($ref_id) $actions_data[$action]['refs'][] = $ref_id;

        if ($daily_row) {
            $wpdb->update($table_logs, ['score_daily' => $daily_row->score_daily + $rule['points'], 'data_json' => json_encode($actions_data)], ['id' => $daily_row->id]);
            $daily_score = $daily_row->score_daily + $rule['points'];
        } else {
            $wpdb->insert($table_logs, ['user_id' => $user_id, 'log_date' => $today, 'score_daily' => $rule['points'], 'data_json' => json_encode($actions_data)]);
            $daily_score = $rule['points'];
        }

        $current_total = (int) get_user_meta($user_id, 'rejimde_total_score', true);
        $new_total = $current_total + $rule['points'];
        update_user_meta($user_id, 'rejimde_total_score', $new_total);
        
        // Eğer klanı varsa, klan puanını da artır
        $clan_id = get_user_meta($user_id, 'clan_id', true);
        if ($clan_id) {
            $clan_score = (int) get_post_meta($clan_id, 'total_score', true);
            update_post_meta($clan_id, 'total_score', $clan_score + $rule['points']);
        }

        return $this->success([
            'earned' => $rule['points'],
            'total_score' => $new_total,
            'daily_score' => $daily_score,
            'message' => $rule['label'] . ' tamamlandı!'
        ]);
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

    public function check_auth($request) { return is_user_logged_in(); }
    protected function success($data = null) { return new WP_REST_Response(['status' => 'success', 'data' => $data], 200); }
    protected function error($message = 'Error', $code = 400) { return new WP_REST_Response(['status' => 'error', 'message' => $message], $code); }
}