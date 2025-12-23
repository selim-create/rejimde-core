<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

class CommentController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'comments';

    public function register_routes() {
        // Yorumları Getir
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_comments'],
            'permission_callback' => '__return_true',
        ]);

        // Yorum Yap / Değerlendir
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'create_comment'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    public function check_auth() {
        return is_user_logged_in();
    }

    /**
     * YORUMLARI GETİR (İstatistiklerle Birlikte)
     */
    public function get_comments($request) {
        $post_id = $request->get_param('post');
        $context = $request->get_param('context'); // 'expert', 'blog', 'diet' ...

        if (!$post_id) return new WP_Error('missing_post_id', 'Post ID gerekli', ['status' => 400]);

        $args = [
            'post_id' => $post_id,
            'status'  => 'approve',
            'orderby' => 'comment_date',
            'order'   => 'DESC',
            'parent'  => 0 // Sadece ana yorumları çek (Cevaplar nested gelecek)
        ];

        // Uzman değerlendirmesi ise sadece o context'i getir
        if ($context === 'expert') {
            $args['meta_key'] = 'rejimde_context';
            $args['meta_value'] = 'expert';
        }

        $comments = get_comments($args);
        $data = [];

        // Meta sınıfını başlat (Helper için)
        $meta_helper = new \Rejimde\Core\CommentMeta();

        foreach ($comments as $comment) {
            $data[] = $this->prepare_comment_response($comment, $meta_helper);
        }

        // İstatistikler (Sadece Uzman Değerlendirmesi için)
        $stats = null;
        if ($context === 'expert') {
            $stats = $this->calculate_review_stats($post_id);
        }

        return new WP_REST_Response(['comments' => $data, 'stats' => $stats], 200);
    }

    /**
     * YORUM OLUŞTUR
     */
    public function create_comment($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        $post_id = isset($params['post']) ? (int) $params['post'] : 0;
        $content = isset($params['content']) ? sanitize_textarea_field($params['content']) : '';
        $context = isset($params['context']) ? sanitize_text_field($params['context']) : 'general';
        $rating  = isset($params['rating']) ? (int) $params['rating'] : 0;
        $parent  = isset($params['parent']) ? (int) $params['parent'] : 0;

        if (!$post_id || empty($content)) {
            return new WP_Error('missing_data', 'İçerik ve Post ID zorunludur.', ['status' => 400]);
        }

        // Kural: Uzman Değerlendirmesi ise (ve cevap değilse), kullanıcı daha önce yorum yapmış mı?
        if ($context === 'expert' && $parent === 0) {
            if ($rating < 1 || $rating > 5) {
                return new WP_Error('missing_rating', 'Lütfen 1 ile 5 arasında bir puan verin.', ['status' => 400]);
            }

            $existing = get_comments([
                'post_id' => $post_id,
                'user_id' => $user_id,
                'meta_key' => 'rejimde_context',
                'meta_value' => 'expert',
                'count' => true
            ]);

            if ($existing > 0) {
                return new WP_Error('already_reviewed', 'Bu uzmanı zaten değerlendirdiniz.', ['status' => 409]);
            }
        }

        $comment_data = [
            'comment_post_ID' => $post_id,
            'comment_content' => $content,
            'user_id'         => $user_id,
            'comment_parent'  => $parent,
            'comment_approved' => 1, // Şimdilik otomatik onay
        ];

        $comment_id = wp_insert_comment($comment_data);

        if (!$comment_id) {
            return new WP_Error('save_error', 'Yorum kaydedilemedi.', ['status' => 500]);
        }

        // Meta Kayıt
        update_comment_meta($comment_id, 'rejimde_context', $context);
        if ($rating > 0) {
            update_comment_meta($comment_id, 'rejimde_rating', $rating);
            // Uzman Puanını Güncelle (Cache mantığı)
            if ($context === 'expert') $this->update_post_average_rating($post_id);
        }

        // GAMIFICATION: Puan Ver
        // Günde max 5 yorumdan puan kazanılır
        $today = date('Ymd');
        $daily_count = (int) get_user_meta($user_id, "daily_comments_$today", true);
        
        $points_earned = 0;
        if ($daily_count < 5) {
            $points_earned = ($context === 'expert' && $parent === 0) ? 20 : 5; // Değerlendirme 20, Yorum 5
            $current_score = (int) get_user_meta($user_id, 'rejimde_total_score', true);
            update_user_meta($user_id, 'rejimde_total_score', $current_score + $points_earned);
            update_user_meta($user_id, "daily_comments_$today", $daily_count + 1);
        }

        $new_comment = get_comment($comment_id);
        $meta_helper = new \Rejimde\Core\CommentMeta();
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $this->prepare_comment_response($new_comment, $meta_helper),
            'earned_points' => $points_earned,
            'message' => 'Yorumunuz yayınlandı!'
        ], 201);
    }

    // --- YARDIMCILAR ---

    private function prepare_comment_response($comment, $meta_helper) {
        // Ensure we pass the comment object properly to get_author_details
        $author_details = $meta_helper->get_author_details($comment);
        
        // Alt yorumları (replies) çek
        $replies = get_comments([
            'parent' => $comment->comment_ID,
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'ASC'
        ]);
        
        $reply_data = [];
        foreach ($replies as $reply) {
            $reply_data[] = $this->prepare_comment_response($reply, $meta_helper);
        }

        return [
            'id' => (int) $comment->comment_ID,
            'content' => $comment->comment_content,
            'date' => human_time_diff(strtotime($comment->comment_date), current_time('timestamp')) . ' önce',
            'rating' => (int) get_comment_meta($comment->comment_ID, 'rejimde_rating', true),
            'author' => $author_details,
            'replies' => $reply_data
        ];
    }

    private function calculate_review_stats($post_id) {
        global $wpdb;
        $ratings = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM $wpdb->commentmeta WHERE meta_key = 'rejimde_rating' AND comment_id IN (SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = '1')", 
            $post_id
        ));

        if (empty($ratings)) return null;

        $count = count($ratings);
        $sum = array_sum($ratings);
        $avg = round($sum / $count, 1);
        
        $distribution = array_count_values($ratings);
        
        // Yüzdelik hesaplama
        $dist_percent = [];
        for ($i=5; $i>=1; $i--) {
            $val = isset($distribution[$i]) ? $distribution[$i] : 0;
            $dist_percent[$i] = [
                'count' => $val,
                'percent' => round(($val / $count) * 100)
            ];
        }
        
        return [
            'average' => $avg,
            'total' => $count,
            'distribution' => $dist_percent
        ];
    }

    private function update_post_average_rating($post_id) {
        $stats = $this->calculate_review_stats($post_id);
        if ($stats) {
            update_post_meta($post_id, 'puan', $stats['average']);
            update_post_meta($post_id, 'review_count', $stats['total']);
        }
    }
}