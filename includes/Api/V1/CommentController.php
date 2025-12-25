<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;
use WP_Comment_Query;

class CommentController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'comments';

    public function register_routes() {
        // ... (Eski rotalar aynı) ...
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_comments'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'create_comment'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>[\d]+)/like', [
            'methods' => 'POST',
            'callback' => [$this, 'like_comment'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/expert/reviews', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_reviews'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Yorum Onayla
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>[\d]+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_comment'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // YENİ: Yorum Reddet (Trash)
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>[\d]+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_comment'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // YENİ: Yorum Spam
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>[\d]+)/spam', [
            'methods' => 'POST',
            'callback' => [$this, 'spam_comment'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    public function check_auth() {
        return is_user_logged_in();
    }

    // ... (get_comments, create_comment, like_comment, get_my_reviews, approve_comment aynı kalıyor) ...
    // ... (Kod tekrarı olmaması için buraya sadece yeni fonksiyonları ekliyorum, diğerleri önceki versiyonla aynı) ...

    public function get_comments($request) {
        $post_id = $request->get_param('post');
        $context = $request->get_param('context'); 

        if (!$post_id) return new WP_Error('missing_post_id', 'Post ID gerekli', ['status' => 400]);

        $user_id = get_current_user_id();
        $status = 'approve'; 

        if ($user_id) {
            $post = get_post($post_id);
            $is_owner = ($post->post_author == $user_id) || 
                        (get_post_meta($post_id, 'related_user_id', true) == $user_id) ||
                        (get_post_meta($post_id, 'user_id', true) == $user_id);
            
            if ($is_owner || current_user_can('moderate_comments')) {
                $status = 'all'; 
            }
        }

        $args = [
            'post_id' => $post_id,
            'status'  => $status, 
            'orderby' => 'comment_date',
            'order'   => 'DESC',
            'parent'  => 0 
        ];

        if ($context === 'expert') {
            $args['meta_key'] = 'rejimde_context';
            $args['meta_value'] = 'expert';
        }

        $comments_query = new WP_Comment_Query($args);
        $comments = $comments_query->comments;

        if ($status === 'all') {
            $comments = array_filter($comments, function($c) {
                return $c->comment_approved == '1' || $c->comment_approved == '0';
            });
        }

        $data = [];
        $meta_helper = new \Rejimde\Core\CommentMeta();

        foreach ($comments as $comment) {
            $data[] = $this->prepare_comment_response($comment, $meta_helper);
        }

        $stats = null;
        if (in_array($context, ['expert', 'diet', 'exercise'])) {
            $stats = $this->calculate_review_stats($post_id);
        }

        return new WP_REST_Response(['comments' => $data, 'stats' => $stats], 200);
    }

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

        if ($context === 'expert') {
            $post = get_post($post_id);
            $is_owner = ($post->post_author == $user_id) || 
                        (get_post_meta($post_id, 'related_user_id', true) == $user_id) ||
                        (get_post_meta($post_id, 'user_id', true) == $user_id);
            
            if ($is_owner && $parent === 0) { 
                return new WP_Error('self_review', 'Kendi profilinize değerlendirme yapamazsınız.', ['status' => 403]);
            }
        }

        if (in_array($context, ['expert', 'diet', 'exercise']) && $parent === 0) {
            $existing = get_comments([
                'post_id' => $post_id,
                'user_id' => $user_id,
                'meta_key' => 'rejimde_context',
                'meta_value' => $context,
                'count' => true,
                'parent' => 0
            ]);

            if ($existing > 0) {
                return new WP_Error('already_reviewed', 'Bu içeriği zaten değerlendirdiniz.', ['status' => 409]);
            }
        }

        $comment_approved = 1;
        if ($context === 'expert') {
            if (!current_user_can('moderate_comments')) {
                $comment_approved = 0; 
            }
        }

        $comment_data = [
            'comment_post_ID' => $post_id,
            'comment_content' => $content,
            'user_id'         => $user_id,
            'comment_parent'  => $parent,
            'comment_approved' => $comment_approved,
        ];

        $comment_id = wp_insert_comment($comment_data);

        if (!$comment_id) {
            return new WP_Error('save_error', 'Yorum kaydedilemedi.', ['status' => 500]);
        }

        update_comment_meta($comment_id, 'rejimde_context', $context);
        if ($rating > 0) {
            update_comment_meta($comment_id, 'rejimde_rating', $rating);
        }
        
        // Dispatch event for comment creation or rating
        $eventType = ($context === 'expert' && $parent === 0 && $rating > 0) ? 'rating_submitted' : 'comment_created';
        $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
        $eventResult = $dispatcher->dispatch($eventType, [
            'user_id' => $user_id,
            'entity_type' => 'comment',
            'entity_id' => $comment_id,
            'context' => [
                'post_id' => $post_id,
                'comment_context' => $context,
                'rating' => $rating
            ]
        ]);
        
        $points_earned = $eventResult['points_earned'] ?? 0;

        $new_comment = get_comment($comment_id);
        $meta_helper = new \Rejimde\Core\CommentMeta();
        
        $response_data = $this->prepare_comment_response($new_comment, $meta_helper);
        $message = $comment_approved === 1 ? 'Yorumunuz yayınlandı!' : 'Değerlendirmeniz alındı, uzman onayından sonra yayınlanacaktır.';

        return new WP_REST_Response([
            'success' => true,
            'data' => $response_data,
            'earned_points' => $points_earned,
            'message' => $message,
            'status' => $comment_approved === 1 ? 'approved' : 'pending'
        ], 201);
    }

    public function like_comment($request) {
        $comment_id = $request->get_param('id');
        $user_id = get_current_user_id();
        if (!$user_id) return new WP_Error('auth_required', 'Giriş yapmalısınız', ['status' => 401]);
        
        $likes = get_comment_meta($comment_id, 'rejimde_likes', true) ?: [];
        if (!is_array($likes)) $likes = [];

        $is_liked = false;
        if (in_array($user_id, $likes)) {
            $likes = array_diff($likes, [$user_id]);
        } else {
            $likes[] = $user_id;
            $is_liked = true;
            
            // Only dispatch event when liking (not unliking)
            // Dispatch event for comment liked
            $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
            $dispatcher->dispatch('comment_liked', [
                'user_id' => $user_id,
                'comment_id' => $comment_id,
                'entity_type' => 'comment',
                'entity_id' => $comment_id
            ]);
        }
        
        update_comment_meta($comment_id, 'rejimde_likes', array_values($likes));
        update_comment_meta($comment_id, 'like_count', count($likes));
        
        return new WP_REST_Response(['success' => true, 'likes_count' => count($likes), 'is_liked' => $is_liked], 200);
    }

    public function get_my_reviews($request) {
        $user_id = get_current_user_id();
        
        $post_types = ['professional', 'rejimde_professional', 'expert', 'rejimde_pro'];
        
        $expert_posts = get_posts([
            'post_type' => $post_types,
            'author' => $user_id,
            'numberposts' => 1,
            'post_status' => 'any'
        ]);

        if (empty($expert_posts)) {
            $expert_posts = get_posts([
                'post_type' => $post_types,
                'numberposts' => 1,
                'post_status' => 'any',
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key' => 'related_user_id',
                        'value' => $user_id,
                        'compare' => '='
                    ],
                    [
                        'key' => 'user_id',
                        'value' => $user_id,
                        'compare' => '='
                    ]
                ]
            ]);
        }

        if (empty($expert_posts)) {
            return new WP_Error('no_profile', 'Uzman profili bulunamadı.', ['status' => 404]);
        }

        $post_id = $expert_posts[0]->ID;

        $comments_query = new WP_Comment_Query([
            'post_id' => $post_id,
            'status' => 'all',
            'orderby' => 'comment_date',
            'order' => 'DESC',
            'parent' => 0
        ]);
        
        $comments = $comments_query->comments;
        
        $filtered_comments = array_filter($comments, function($c) {
            return $c->comment_approved == '1' || $c->comment_approved == '0';
        });

        $data = [];
        $meta_helper = new \Rejimde\Core\CommentMeta();

        foreach ($filtered_comments as $comment) {
            $data[] = $this->prepare_comment_response($comment, $meta_helper);
        }

        $stats = $this->calculate_review_stats($post_id);

        return new WP_REST_Response(['comments' => $data, 'stats' => $stats], 200);
    }

    public function approve_comment($request) {
        $comment_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        $comment = get_comment($comment_id);
        if (!$comment) return new WP_Error('not_found', 'Yorum bulunamadı', ['status' => 404]);

        $post = get_post($comment->comment_post_ID);
        
        $is_author = ($post->post_author == $user_id);
        $is_linked_user = (get_post_meta($post->ID, 'related_user_id', true) == $user_id) || (get_post_meta($post->ID, 'user_id', true) == $user_id);

        if (!$is_author && !$is_linked_user && !current_user_can('moderate_comments')) {
             return new WP_Error('forbidden', 'Bu işlemi yapmaya yetkiniz yok.', ['status' => 403]);
        }

        wp_set_comment_status($comment_id, 'approve');
        $this->update_post_average_rating($comment->comment_post_ID);

        return new WP_REST_Response(['success' => true, 'message' => 'Yorum onaylandı.'], 200);
    }

    /**
     * YENİ: YORUM REDDET (Çöpe At)
     */
    public function reject_comment($request) {
        $comment_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        $comment = get_comment($comment_id);
        if (!$comment) return new WP_Error('not_found', 'Yorum bulunamadı', ['status' => 404]);

        $post = get_post($comment->comment_post_ID);
        $is_owner = ($post->post_author == $user_id) || 
                    (get_post_meta($post->ID, 'related_user_id', true) == $user_id) ||
                    (get_post_meta($post->ID, 'user_id', true) == $user_id);

        if (!$is_owner && !current_user_can('moderate_comments')) {
             return new WP_Error('forbidden', 'Yetkiniz yok.', ['status' => 403]);
        }

        wp_set_comment_status($comment_id, 'trash');
        $this->update_post_average_rating($comment->comment_post_ID);

        return new WP_REST_Response(['success' => true, 'message' => 'Yorum reddedildi (çöpe taşındı).'], 200);
    }

    /**
     * YENİ: SPAM BİLDİR
     */
    public function spam_comment($request) {
        $comment_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        $comment = get_comment($comment_id);
        if (!$comment) return new WP_Error('not_found', 'Yorum bulunamadı', ['status' => 404]);

        $post = get_post($comment->comment_post_ID);
        $is_owner = ($post->post_author == $user_id) || 
                    (get_post_meta($post->ID, 'related_user_id', true) == $user_id) ||
                    (get_post_meta($post->ID, 'user_id', true) == $user_id);

        if (!$is_owner && !current_user_can('moderate_comments')) {
             return new WP_Error('forbidden', 'Yetkiniz yok.', ['status' => 403]);
        }

        wp_set_comment_status($comment_id, 'spam');
        $this->update_post_average_rating($comment->comment_post_ID);

        return new WP_REST_Response(['success' => true, 'message' => 'Yorum spam olarak işaretlendi.'], 200);
    }

    // --- YARDIMCILAR ---
    private function get_translated_profession($slug) {
        $map = ['dietitian' => 'Diyetisyen', 'trainer' => 'Antrenör', 'psychologist' => 'Psikolog', 'physiotherapist' => 'Fizyoterapist', 'doctor' => 'Doktor', 'specialist' => 'Uzman', 'coach' => 'Yaşam Koçu'];
        $slug_lower = strtolower($slug);
        return isset($map[$slug_lower]) ? $map[$slug_lower] : $slug;
    }

    private function prepare_comment_response($comment, $meta_helper) {
        $author_details = $meta_helper->get_author_details($comment);
        $user_id = $comment->user_id;
        
        if ($user_id) {
            $last_active = get_user_meta($user_id, 'last_activity', true);
            $author_details['is_online'] = ($last_active && (time() - strtotime($last_active) < 300));
            $raw_profession = get_user_meta($user_id, 'profession', true) ?: 'Rejimde Üyesi';
            $author_details['profession'] = $this->get_translated_profession($raw_profession);
            $user_meta = get_userdata($user_id);
            $roles = $user_meta ? $user_meta->roles : [];
            $is_expert = in_array('rejimde_expert', $roles) || in_array('rejimde_pro', $roles);
            $author_details['is_expert'] = $is_expert;
            $is_verified_meta = get_user_meta($user_id, 'is_verified_expert', true);
            $author_details['is_verified'] = ($is_verified_meta !== '') ? (bool) $is_verified_meta : $is_expert;
            $author_details['rank'] = (int) get_user_meta($user_id, 'rejimde_rank', true) ?: 1;
            $author_details['score'] = (int) get_user_meta($user_id, 'rejimde_total_score', true);
            $author_details['slug'] = $user_meta ? $user_meta->user_nicename : '';
        } else {
            $author_details['is_online'] = false;
            $author_details['profession'] = 'Misafir';
            $author_details['is_expert'] = false;
            $author_details['slug'] = '';
        }

        $likes = get_comment_meta($comment->comment_ID, 'rejimde_likes', true) ?: [];
        if(!is_array($likes)) $likes = [];
        $current_user_id = get_current_user_id();

        $replies = get_comments([
            'parent' => $comment->comment_ID,
            'status' => 'all', 
            'orderby' => 'comment_date',
            'order' => 'ASC'
        ]);
        
        $reply_data = [];
        foreach ($replies as $reply) {
            if ($reply->comment_approved == '1' || $reply->comment_approved == '0') {
                $reply_data[] = $this->prepare_comment_response($reply, $meta_helper);
            }
        }

        return [
            'id' => (int) $comment->comment_ID,
            'content' => $comment->comment_content,
            'date' => human_time_diff(strtotime($comment->comment_date), current_time('timestamp')) . ' önce',
            'rating' => (int) get_comment_meta($comment->comment_ID, 'rejimde_rating', true),
            'context' => get_comment_meta($comment->comment_ID, 'rejimde_context', true),
            'status'  => $comment->comment_approved == '1' ? 'approved' : 'pending',
            'author' => $author_details,
            'likes_count' => count($likes),
            'is_liked' => in_array($current_user_id, $likes),
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
        $dist_percent = [];
        for ($i=5; $i>=1; $i--) {
            $val = isset($distribution[$i]) ? $distribution[$i] : 0;
            $dist_percent[$i] = ['count' => $val, 'percent' => round(($val / $count) * 100)];
        }
        return ['average' => $avg, 'total' => $count, 'distribution' => $dist_percent];
    }

    private function update_post_average_rating($post_id) {
        $stats = $this->calculate_review_stats($post_id);
        if ($stats) {
            update_post_meta($post_id, 'puan', $stats['average']);
            update_post_meta($post_id, 'review_count', $stats['total']);
        }
    }
}