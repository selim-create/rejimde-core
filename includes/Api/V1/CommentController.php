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

        // YENİ: Yorum Şikayet Et
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>[\d]+)/report', [
            'methods' => 'POST',
            'callback' => [$this, 'report_comment'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // YENİ: Yorum Öne Çıkar/Kaldır
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>[\d]+)/feature', [
            'methods' => 'POST',
            'callback' => [$this, 'feature_comment'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // YENİ: Uzman Topluluk Etkisi
        register_rest_route($this->namespace, '/experts/(?P<id>[\d]+)/impact', [
            'methods' => 'GET',
            'callback' => [$this, 'get_expert_impact'],
            'permission_callback' => '__return_true',
        ]);

        // YENİ: Başarı Hikayeleri
        register_rest_route($this->namespace, '/experts/(?P<id>[\d]+)/success-stories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_success_stories'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function check_auth() {
        $is_logged_in = is_user_logged_in();
        if ($is_logged_in) {
            update_user_meta(get_current_user_id(), 'last_activity', current_time('mysql'));
        }
        return $is_logged_in;
    }

    // ... (get_comments, create_comment, like_comment, get_my_reviews, approve_comment aynı kalıyor) ...
    // ... (Kod tekrarı olmaması için buraya sadece yeni fonksiyonları ekliyorum, diğerleri önceki versiyonla aynı) ...

    public function get_comments($request) {
        $post_id = $request->get_param('post_id') ?: $request->get_param('post');
        $context = $request->get_param('context'); 

        // NEW filter parameters
        $goal_tag = $request->get_param('goal_tag');
        $program_type = $request->get_param('program_type');
        $verified_only = $request->get_param('verified_only') === 'true';
        $featured_only = $request->get_param('featured_only') === 'true';
        $with_stories = $request->get_param('with_stories') === 'true';
        $rating_min = (int) $request->get_param('rating_min');

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

        // Build meta query
        $meta_query = [];
        
        if ($context === 'expert') {
            $meta_query[] = [
                'key' => 'rejimde_context',
                'value' => 'expert'
            ];
        }
        
        if (!empty($goal_tag)) {
            $meta_query[] = [
                'key' => 'goal_tag',
                'value' => $goal_tag
            ];
        }
        
        if (!empty($program_type)) {
            $meta_query[] = [
                'key' => 'program_type',
                'value' => $program_type
            ];
        }
        
        if ($verified_only) {
            $meta_query[] = [
                'key' => 'verified_client',
                'value' => '1'
            ];
        }
        
        if ($featured_only) {
            $meta_query[] = [
                'key' => 'is_featured',
                'value' => '1'
            ];
        }
        
        if ($with_stories) {
            $meta_query[] = [
                'key' => 'success_story',
                'value' => '',
                'compare' => '!='
            ];
        }
        
        if ($rating_min > 0) {
            $meta_query[] = [
                'key' => 'rejimde_rating',
                'value' => $rating_min,
                'compare' => '>=',
                'type' => 'NUMERIC'
            ];
        }
        
        if (!empty($meta_query)) {
            $meta_query['relation'] = 'AND';
            $args['meta_query'] = $meta_query;
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

        return new WP_REST_Response([
            'comments' => $data, 
            'stats' => $stats,
            'filters_applied' => [
                'goal_tag' => $goal_tag,
                'program_type' => $program_type,
                'verified_only' => $verified_only,
                'featured_only' => $featured_only,
                'with_stories' => $with_stories,
                'rating_min' => $rating_min
            ]
        ], 200);
    }

    public function create_comment($request) {
        try {
            $user_id = get_current_user_id();
            $params = $request->get_json_params();

            $post_id = isset($params['post']) ? (int) $params['post'] : 0;
            $content = isset($params['content']) ? sanitize_textarea_field($params['content']) : '';
            $context = isset($params['context']) ? sanitize_text_field($params['context']) : 'general';
            $rating  = isset($params['rating']) ? (int) $params['rating'] : 0;
            $parent  = isset($params['parent']) ? (int) $params['parent'] : 0;

            // NEW parameters
            $is_anonymous = isset($params['is_anonymous']) ? (bool) $params['is_anonymous'] : false;
            $goal_tag = isset($params['goal_tag']) ? sanitize_text_field($params['goal_tag']) : '';
            $program_type = isset($params['program_type']) ? sanitize_text_field($params['program_type']) : '';
            $process_weeks = isset($params['process_weeks']) ? (int) $params['process_weeks'] : 0;
            $success_story = isset($params['success_story']) ? sanitize_textarea_field($params['success_story']) : '';
            $would_recommend = isset($params['would_recommend']) ? (bool) $params['would_recommend'] : true;

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

            // NEW meta fields
            if ($is_anonymous) {
                update_comment_meta($comment_id, 'is_anonymous', true);
            }
            if (!empty($goal_tag)) {
                update_comment_meta($comment_id, 'goal_tag', $goal_tag);
            }
            if (!empty($program_type)) {
                update_comment_meta($comment_id, 'program_type', $program_type);
            }
            if ($process_weeks > 0) {
                update_comment_meta($comment_id, 'process_weeks', $process_weeks);
            }
            if (!empty($success_story)) {
                update_comment_meta($comment_id, 'success_story', $success_story);
            }
            update_comment_meta($comment_id, 'would_recommend', $would_recommend);

            // Verified client check (appointment/payment history)
            $verified_client = $this->check_verified_client($user_id, $post_id);
            if ($verified_client) {
                update_comment_meta($comment_id, 'verified_client', true);
            }
            
            // Event dispatch - hata comment oluşturmayı engellemesini önle
            $points_earned = 0;
            try {
                $eventType = ($context === 'expert' && $parent === 0 && $rating > 0) ? 'rating_submitted' : 'comment_created';
                $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
                
                // Prepare event payload
                $eventPayload = [
                    'user_id' => $user_id,
                    'entity_type' => 'comment',
                    'entity_id' => $comment_id,
                    'context' => [
                        'post_id' => $post_id,
                        'comment_context' => $context,
                        'rating' => $rating
                    ]
                ];
                
                // Add expert_id for rating_submitted events
                if ($eventType === 'rating_submitted') {
                    $post = get_post($post_id);
                    if ($post) {
                        $eventPayload['expert_id'] = $post->post_author;
                    }
                }
                
                // Add parent_comment_id for comment_created events
                if ($eventType === 'comment_created' && $parent > 0) {
                    $eventPayload['parent_comment_id'] = $parent;
                }
                
                // Add comment_id to payload for comment_created events
                if ($eventType === 'comment_created') {
                    $eventPayload['comment_id'] = $comment_id;
                }
                
                $eventResult = $dispatcher->dispatch($eventType, $eventPayload);
                $points_earned = $eventResult['points_earned'] ?? 0;
            } catch (\Exception $e) {
                error_log('EventDispatcher error in create_comment: ' . $e->getMessage());
                // Event hatası olsa bile yorum oluşturulmuş, devam et
            }

            // Response hazırlama - hata durumunda minimal response döndür
            $response_data = null;
            try {
                $new_comment = get_comment($comment_id);
                $meta_helper = new \Rejimde\Core\CommentMeta();
                $response_data = $this->prepare_comment_response($new_comment, $meta_helper);
            } catch (\Exception $e) {
                error_log('CommentMeta error in create_comment: ' . $e->getMessage());
                // Minimal response döndür
                $response_data = [
                    'id' => $comment_id,
                    'content' => $content,
                    'status' => $comment_approved === 1 ? 'approved' : 'pending'
                ];
            }
            
            $message = $comment_approved === 1 ? 'Yorumunuz yayınlandı!' : 'Değerlendirmeniz alındı, uzman onayından sonra yayınlanacaktır.';

            return new WP_REST_Response([
                'success' => true,
                'data' => $response_data,
                'earned_points' => $points_earned,
                'message' => $message,
                'status' => $comment_approved === 1 ? 'approved' : 'pending'
            ], 201);
        } catch (\Exception $e) {
            error_log('CommentController create_comment error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return new WP_Error('server_error', 'Yorum oluşturulurken bir hata oluştu.', ['status' => 500]);
        }
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

    /**
     * YENİ: YORUM ŞİKAYET ET
     */
    public function report_comment($request) {
        $comment_id = (int) $request->get_param('id');
        $user_id = get_current_user_id();
        
        if ($comment_id <= 0) {
            return new WP_Error('invalid_id', 'Geçersiz yorum ID', ['status' => 400]);
        }
        
        $comment = get_comment($comment_id);
        if (!$comment) {
            return new WP_Error('not_found', 'Yorum bulunamadı', ['status' => 404]);
        }

        // Kendi yorumunu şikayet edemez
        if ((int) $comment->user_id === $user_id) {
            return new WP_Error('self_report', 'Kendi yorumunuzu şikayet edemezsiniz.', ['status' => 400]);
        }

        // Daha önce şikayet etmiş mi?
        $reports = get_comment_meta($comment_id, 'rejimde_reports', true) ?: [];
        if (!is_array($reports)) $reports = [];

        if (in_array($user_id, $reports)) {
            return new WP_Error('already_reported', 'Bu yorumu zaten şikayet ettiniz.', ['status' => 409]);
        }

        $reports[] = $user_id;
        update_comment_meta($comment_id, 'rejimde_reports', $reports);
        update_comment_meta($comment_id, 'report_count', count($reports));

        // 3+ şikayet varsa otomatik gizle
        if (count($reports) >= 3) {
            wp_set_comment_status($comment_id, 'hold');
        }

        return new WP_REST_Response([
            'success' => true, 
            'message' => 'Şikayetiniz alındı. Teşekkürler!',
            'report_count' => count($reports)
        ], 200);
    }

    /**
     * YENİ: YORUM ÖNE ÇIKAR/KALDIR
     * Yorumu öne çıkan olarak işaretle/kaldır (toggle)
     * Sadece uzman kendi profilindeki yorumları öne çıkarabilir
     * Maksimum 3 öne çıkan yorum olabilir
     */
    public function feature_comment($request) {
        $comment_id = (int) $request->get_param('id');
        $user_id = get_current_user_id();
        
        $comment = get_comment($comment_id);
        if (!$comment) {
            return new WP_Error('not_found', 'Yorum bulunamadı', ['status' => 404]);
        }
        
        $post = get_post($comment->comment_post_ID);
        $is_owner = ($post->post_author == $user_id) || 
                    (get_post_meta($post->ID, 'related_user_id', true) == $user_id) ||
                    (get_post_meta($post->ID, 'user_id', true) == $user_id);
        
        if (!$is_owner && !current_user_can('moderate_comments')) {
            return new WP_Error('forbidden', 'Bu işlemi yapmaya yetkiniz yok.', ['status' => 403]);
        }
        
        $is_currently_featured = (bool) get_comment_meta($comment_id, 'is_featured', true);
        
        if ($is_currently_featured) {
            // Remove from featured
            delete_comment_meta($comment_id, 'is_featured');
            return new WP_REST_Response([
                'success' => true, 
                'is_featured' => false,
                'message' => 'Yorum öne çıkarılanlardan kaldırıldı.'
            ], 200);
        } else {
            // Check current featured count (max 3)
            $featured_count = get_comments([
                'post_id' => $comment->comment_post_ID,
                'meta_key' => 'is_featured',
                'meta_value' => '1',
                'count' => true,
                'parent' => 0
            ]);
            
            if ($featured_count >= 3) {
                return new WP_Error('limit_reached', 'Maksimum 3 yorum öne çıkarılabilir.', ['status' => 400]);
            }
            
            update_comment_meta($comment_id, 'is_featured', true);
            return new WP_REST_Response([
                'success' => true, 
                'is_featured' => true,
                'message' => 'Yorum öne çıkarıldı.'
            ], 200);
        }
    }

    /**
     * YENİ: UZMAN TOPLULUK ETKİSİ
     * Uzmanın topluluk etkisi istatistiklerini getir
     */
    public function get_expert_impact($request) {
        $expert_post_id = (int) $request->get_param('id');
        
        $post = get_post($expert_post_id);
        if (!$post) {
            return new WP_Error('not_found', 'Uzman bulunamadı', ['status' => 404]);
        }
        
        global $wpdb;
        
        // Expert's user_id
        $expert_user_id = get_post_meta($expert_post_id, 'related_user_id', true) 
                          ?: get_post_meta($expert_post_id, 'user_id', true)
                          ?: $post->post_author;
        
        // Total clients (unique client_id from appointments)
        $appointments_table = $wpdb->prefix . 'rejimde_appointments';
        $total_clients = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT client_id) FROM $appointments_table WHERE expert_id = %d AND status IN ('completed', 'confirmed')",
            $expert_user_id
        )) ?: 0;
        
        // Completed programs
        $programs_completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $appointments_table WHERE expert_id = %d AND status = 'completed'",
            $expert_user_id
        )) ?: 0;
        
        // Statistics from reviews
        $reviews = $wpdb->get_results($wpdb->prepare(
            "SELECT cm.meta_key, cm.meta_value 
             FROM {$wpdb->commentmeta} cm
             INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
             WHERE c.comment_post_ID = %d 
             AND c.comment_approved = '1'
             AND c.comment_parent = 0
             AND cm.meta_key IN ('process_weeks', 'would_recommend', 'verified_client')",
            $expert_post_id
        ));
        
        $total_weeks = 0;
        $weeks_count = 0;
        $recommend_count = 0;
        $total_reviews = 0;
        $verified_count = 0;
        
        foreach ($reviews as $row) {
            if ($row->meta_key === 'process_weeks' && $row->meta_value > 0) {
                $total_weeks += (int) $row->meta_value;
                $weeks_count++;
            }
            if ($row->meta_key === 'would_recommend' && $row->meta_value) {
                $recommend_count++;
                $total_reviews++;
            }
            if ($row->meta_key === 'verified_client' && $row->meta_value) {
                $verified_count++;
            }
        }
        
        $average_weeks = $weeks_count > 0 ? round($total_weeks / $weeks_count) : 0;
        $recommend_rate = $total_reviews > 0 ? round(($recommend_count / $total_reviews) * 100) : 0;
        
        // Goal distribution
        $goal_distribution = $wpdb->get_results($wpdb->prepare(
            "SELECT cm.meta_value as goal_tag, COUNT(*) as count
             FROM {$wpdb->commentmeta} cm
             INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
             WHERE c.comment_post_ID = %d 
             AND c.comment_approved = '1'
             AND c.comment_parent = 0
             AND cm.meta_key = 'goal_tag'
             AND cm.meta_value != ''
             GROUP BY cm.meta_value
             ORDER BY count DESC",
            $expert_post_id
        ), ARRAY_A);
        
        // Last 6 months activity
        $six_months_ago = date('Y-m-d H:i:s', strtotime('-6 months'));
        $recent_clients = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT client_id) FROM $appointments_table 
             WHERE expert_id = %d AND created_at >= %s AND status IN ('completed', 'confirmed')",
            $expert_user_id, $six_months_ago
        )) ?: 0;
        
        // Contextual messages
        $context_message = $recent_clients > 0 
            ? "Son 6 ayda {$recent_clients} danışana destek oldu"
            : "Rejimde'de aktif olarak danışan kabul ediyor";
        
        $highlight = '';
        if ($recommend_rate >= 80) {
            $highlight = "Her 5 danışandan " . round($recommend_rate / 20) . "'i tavsiye ediyor";
        } elseif ($total_clients > 50) {
            $highlight = "{$total_clients}+ danışana ulaştı";
        }
        
        return new WP_REST_Response([
            'total_clients_supported' => (int) $total_clients,
            'programs_completed' => (int) $programs_completed,
            'average_journey_weeks' => (int) $average_weeks,
            'recommend_rate' => (int) $recommend_rate,
            'verified_client_count' => (int) $verified_count,
            'goal_distribution' => $goal_distribution ?: [],
            'context' => [
                'message' => $context_message,
                'highlight' => $highlight
            ]
        ], 200);
    }

    /**
     * YENİ: BAŞARI HİKAYELERİ
     * Uzmanın başarı hikayelerini getir
     */
    public function get_success_stories($request) {
        $expert_post_id = (int) $request->get_param('id');
        $limit = (int) $request->get_param('limit') ?: 10;
        
        $post = get_post($expert_post_id);
        if (!$post) {
            return new WP_Error('not_found', 'Uzman bulunamadı', ['status' => 404]);
        }
        
        // Get comments with success stories
        $args = [
            'post_id' => $expert_post_id,
            'status' => 'approve',
            'parent' => 0,
            'number' => $limit,
            'orderby' => 'comment_date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => 'success_story',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];
        
        $comments = get_comments($args);
        $stories = [];
        
        foreach ($comments as $comment) {
            $is_anonymous = (bool) get_comment_meta($comment->comment_ID, 'is_anonymous', true);
            $user = get_userdata($comment->user_id);
            
            // Name determination
            $author_name = '';
            $author_initials = '';
            if ($user) {
                $author_name = $user->display_name;
                $author_initials = implode('.', array_map(function($word) {
                    return mb_strtoupper(mb_substr($word, 0, 1));
                }, explode(' ', $author_name))) . '.';
            }
            
            $stories[] = [
                'id' => (int) $comment->comment_ID,
                'author_initials' => $author_initials,
                'author_name' => $is_anonymous ? null : $author_name,
                'is_anonymous' => $is_anonymous,
                'goal_tag' => get_comment_meta($comment->comment_ID, 'goal_tag', true) ?: null,
                'program_type' => get_comment_meta($comment->comment_ID, 'program_type', true) ?: null,
                'process_weeks' => (int) get_comment_meta($comment->comment_ID, 'process_weeks', true) ?: null,
                'story' => get_comment_meta($comment->comment_ID, 'success_story', true),
                'rating' => (int) get_comment_meta($comment->comment_ID, 'rejimde_rating', true),
                'verified_client' => (bool) get_comment_meta($comment->comment_ID, 'verified_client', true),
                'created_at' => $comment->comment_date,
                'time_ago' => human_time_diff(strtotime($comment->comment_date), current_time('timestamp')) . ' önce'
            ];
        }
        
        return new WP_REST_Response([
            'stories' => $stories,
            'total' => count($stories)
        ], 200);
    }

    /**
     * Kullanıcının bu uzmanla gerçek bir danışan ilişkisi olup olmadığını kontrol et
     * 
     * Kontrol edilen durumlar:
     * 1. Randevu geçmişi (completed, confirmed, pending)
     * 2. Uzmanın relationships tablosunda kayıtlı mı
     * 3. Uzmanın user meta'sında danışan listesinde mi
     * 
     * @param int $user_id Danışan kullanıcı ID
     * @param int $expert_post_id Uzman post ID
     * @return bool
     */
    private function check_verified_client($user_id, $expert_post_id) {
        global $wpdb;
        
        // Uzmanın user_id'sini al
        $expert_user_id = get_post_meta($expert_post_id, 'related_user_id', true) 
                          ?: get_post_meta($expert_post_id, 'user_id', true)
                          ?: get_post_field('post_author', $expert_post_id);
        
        if (!$expert_user_id) return false;
        
        // 1. Randevu geçmişi kontrolü (completed, confirmed, pending)
        $appointments_table = $wpdb->prefix . 'rejimde_appointments';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $appointments_table));
        
        if ($table_exists) {
            $has_appointment = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $appointments_table 
                 WHERE client_id = %d AND expert_id = %d 
                 AND status IN ('completed', 'confirmed', 'pending')",
                $user_id, $expert_user_id
            ));
            
            if ($has_appointment > 0) return true;
        }
        
        // 2. Uzmanın relationships tablosunda kayıtlı mı?
        $relationships_table = $wpdb->prefix . 'rejimde_relationships';
        $relationships_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $relationships_table));
        
        if ($relationships_table_exists) {
            $is_in_relationships = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $relationships_table 
                 WHERE client_id = %d AND expert_id = %d 
                 AND status IN ('active', 'pending', 'paused')",
                $user_id, $expert_user_id
            ));
            
            if ($is_in_relationships > 0) return true;
        }
        
        // 3. Uzmanın user meta'sında danışan listesinde mi?
        $expert_clients_meta = get_user_meta($expert_user_id, 'rejimde_clients', true);
        if (is_array($expert_clients_meta) && in_array((int)$user_id, array_map('intval', $expert_clients_meta), true)) {
            return true;
        }
        
        // 4. Alternatif meta key kontrolü
        $client_list = get_user_meta($expert_user_id, 'client_list', true);
        if (is_array($client_list) && in_array((int)$user_id, array_map('intval', $client_list), true)) {
            return true;
        }
        
        // 5. Post meta üzerinden client kontrolü (bazı sistemlerde böyle saklanıyor)
        $post_clients = get_post_meta($expert_post_id, 'clients', true);
        if (is_array($post_clients) && in_array((int)$user_id, array_map('intval', $post_clients), true)) {
            return true;
        }
        
        return false;
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
            // is_verified meta'sını kontrol et (VerificationPage.php ile uyumlu)
            $is_verified_meta = get_user_meta($user_id, 'is_verified', true);
            $author_details['is_verified'] = ($is_verified_meta === '1' || $is_verified_meta === 1 || $is_verified_meta === true);
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
            'date' => $comment->comment_date,  // Raw date for sorting
            'timeAgo' => human_time_diff(strtotime($comment->comment_date), current_time('timestamp')) . ' önce',
            'rating' => (int) get_comment_meta($comment->comment_ID, 'rejimde_rating', true),
            'context' => get_comment_meta($comment->comment_ID, 'rejimde_context', true),
            'status'  => $comment->comment_approved == '1' ? 'approved' : 'pending',
            'author' => $author_details,
            'likes_count' => count($likes),
            'is_liked' => in_array($current_user_id, $likes),
            'replies' => $reply_data,
            // NEW fields
            'is_anonymous' => (bool) get_comment_meta($comment->comment_ID, 'is_anonymous', true),
            'goal_tag' => get_comment_meta($comment->comment_ID, 'goal_tag', true) ?: null,
            'program_type' => get_comment_meta($comment->comment_ID, 'program_type', true) ?: null,
            'process_weeks' => (int) get_comment_meta($comment->comment_ID, 'process_weeks', true) ?: null,
            'success_story' => get_comment_meta($comment->comment_ID, 'success_story', true) ?: null,
            'would_recommend' => (bool) get_comment_meta($comment->comment_ID, 'would_recommend', true),
            'verified_client' => (bool) get_comment_meta($comment->comment_ID, 'verified_client', true),
            'is_featured' => (bool) get_comment_meta($comment->comment_ID, 'is_featured', true)
        ];
    }

    private function calculate_review_stats($post_id) {
        global $wpdb;
        $ratings = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM $wpdb->commentmeta WHERE meta_key = 'rejimde_rating' AND comment_id IN (SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = '1' AND comment_parent = 0)", 
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
        
        // NEW: Additional statistics
        
        // Verified client count
        $verified_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->commentmeta cm
             INNER JOIN $wpdb->comments c ON cm.comment_id = c.comment_ID
             WHERE c.comment_post_ID = %d 
             AND c.comment_approved = '1' 
             AND c.comment_parent = 0
             AND cm.meta_key = 'verified_client' 
             AND cm.meta_value = '1'",
            $post_id
        ));
        
        // Average process weeks
        $weeks_data = $wpdb->get_col($wpdb->prepare(
            "SELECT cm.meta_value FROM $wpdb->commentmeta cm
             INNER JOIN $wpdb->comments c ON cm.comment_id = c.comment_ID
             WHERE c.comment_post_ID = %d 
             AND c.comment_approved = '1' 
             AND c.comment_parent = 0
             AND cm.meta_key = 'process_weeks' 
             AND cm.meta_value > 0",
            $post_id
        ));
        $avg_weeks = !empty($weeks_data) ? round(array_sum($weeks_data) / count($weeks_data)) : 0;
        
        // Recommendation rate
        $recommend_data = $wpdb->get_results($wpdb->prepare(
            "SELECT cm.meta_value FROM $wpdb->commentmeta cm
             INNER JOIN $wpdb->comments c ON cm.comment_id = c.comment_ID
             WHERE c.comment_post_ID = %d 
             AND c.comment_approved = '1' 
             AND c.comment_parent = 0
             AND cm.meta_key = 'would_recommend'",
            $post_id
        ), ARRAY_A);
        
        $recommend_count = 0;
        foreach ($recommend_data as $row) {
            if ($row['meta_value']) $recommend_count++;
        }
        $recommend_rate = !empty($recommend_data) ? round(($recommend_count / count($recommend_data)) * 100) : 0;
        
        // Goal distribution
        $goal_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT cm.meta_value as goal, COUNT(*) as count FROM $wpdb->commentmeta cm
             INNER JOIN $wpdb->comments c ON cm.comment_id = c.comment_ID
             WHERE c.comment_post_ID = %d 
             AND c.comment_approved = '1' 
             AND c.comment_parent = 0
             AND cm.meta_key = 'goal_tag'
             AND cm.meta_value != ''
             GROUP BY cm.meta_value",
            $post_id
        ), ARRAY_A);
        
        return [
            'average' => $avg, 
            'total' => $count, 
            'distribution' => $dist_percent,
            // NEW fields
            'verified_client_count' => (int) $verified_count,
            'average_process_weeks' => (int) $avg_weeks,
            'recommend_rate' => (int) $recommend_rate,
            'goal_distribution' => $goal_stats ?: []
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