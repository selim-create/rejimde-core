<?php
namespace Rejimde\Core;

class CommentMeta {

    public function register() {
        add_action('rest_api_init', [$this, 'register_comment_meta']);
    }

    public function register_comment_meta() {
        // Puanlama (1-5) - Sadece uzman değerlendirmeleri için
        register_rest_field('comment', 'rejimde_rating', [
            'get_callback' => function ($comment) {
                return (int) get_comment_meta($comment['id'], 'rejimde_rating', true);
            },
            'update_callback' => function ($value, $comment_obj) {
                return update_comment_meta($comment_obj->comment_ID, 'rejimde_rating', $value);
            },
            'schema' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 5],
        ]);

        // Bağlam (expert_review, blog, diet, exercise)
        register_rest_field('comment', 'rejimde_context', [
            'get_callback' => function ($comment) {
                return get_comment_meta($comment['id'], 'rejimde_context', true);
            },
            'update_callback' => function ($value, $comment_obj) {
                return update_comment_meta($comment_obj->comment_ID, 'rejimde_context', sanitize_text_field($value));
            },
            'schema' => ['type' => 'string'],
        ]);

        // Yazarın Rolü ve Rozeti (Frontend'de göstermek için)
        register_rest_field('comment', 'author_details', [
            'get_callback' => [$this, 'get_author_details'],
            'schema' => ['type' => 'object'],
        ]);
    }

    public function get_author_details($comment) {
        $user_id = $comment['author'];
        if (!$user_id) return null; // Anonim yorumlar için null döner

        $user = get_userdata($user_id);
        if (!$user) return null;

        $roles = (array) $user->roles;
        $is_expert = in_array('rejimde_pro', $roles);
        
        // Puan Mantığı: Uzmansa 'rating' (ortalaması), üyeyse 'total_score' (XP)
        $score = $is_expert 
            ? get_post_meta(get_user_meta($user_id, 'professional_profile_id', true), 'puan', true) 
            : get_user_meta($user_id, 'rejimde_total_score', true);

        return [
            'id' => $user_id,
            'name' => $user->display_name,
            'is_expert' => $is_expert,
            'role_label' => $is_expert ? 'UZMAN' : 'ÜYE',
            'level' => (int) get_user_meta($user_id, 'rejimde_level', true) ?: 1,
            'score' => $score ?: 0,
            'title' => get_user_meta($user_id, 'title', true) ?: '', // Dyt., Pt. vb.
            'avatar' => get_user_meta($user_id, 'avatar_url', true) 
                        ?: 'https://api.dicebear.com/9.x/personas/svg?seed=' . urlencode($user->user_login)
        ];
    }
}