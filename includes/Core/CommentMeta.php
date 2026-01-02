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

        // Anonim değerlendirme
        register_rest_field('comment', 'is_anonymous', [
            'get_callback' => function ($comment) {
                return (bool) get_comment_meta($comment['id'], 'is_anonymous', true);
            },
            'update_callback' => function ($value, $comment_obj) {
                return update_comment_meta($comment_obj->comment_ID, 'is_anonymous', (bool) $value);
            },
            'schema' => ['type' => 'boolean'],
        ]);

        // Hedef etiketi (weight_loss, muscle_gain, healthy_eating, etc.)
        register_rest_field('comment', 'goal_tag', [
            'get_callback' => function ($comment) {
                return get_comment_meta($comment['id'], 'goal_tag', true) ?: null;
            },
            'update_callback' => function ($value, $comment_obj) {
                return update_comment_meta($comment_obj->comment_ID, 'goal_tag', sanitize_text_field($value));
            },
            'schema' => ['type' => 'string'],
        ]);

        // Program tipi (online, face_to_face, package, group)
        register_rest_field('comment', 'program_type', [
            'get_callback' => function ($comment) {
                return get_comment_meta($comment['id'], 'program_type', true) ?: null;
            },
            'update_callback' => function ($value, $comment_obj) {
                return update_comment_meta($comment_obj->comment_ID, 'program_type', sanitize_text_field($value));
            },
            'schema' => ['type' => 'string'],
        ]);

        // Süreç süresi (hafta)
        register_rest_field('comment', 'process_weeks', [
            'get_callback' => function ($comment) {
                $weeks = get_comment_meta($comment['id'], 'process_weeks', true);
                return $weeks ? (int) $weeks : null;
            },
            'update_callback' => function ($value, $comment_obj) {
                return update_comment_meta($comment_obj->comment_ID, 'process_weeks', (int) $value);
            },
            'schema' => ['type' => 'integer'],
        ]);

        // Öne çıkan yorum (uzman tarafından seçilmiş)
        register_rest_field('comment', 'is_featured', [
            'get_callback' => function ($comment) {
                return (bool) get_comment_meta($comment['id'], 'is_featured', true);
            },
            'update_callback' => function ($value, $comment_obj) {
                return update_comment_meta($comment_obj->comment_ID, 'is_featured', (bool) $value);
            },
            'schema' => ['type' => 'boolean'],
        ]);

        // Başarı hikayesi metni
        register_rest_field('comment', 'success_story', [
            'get_callback' => function ($comment) {
                return get_comment_meta($comment['id'], 'success_story', true) ?: null;
            },
            'update_callback' => function ($value, $comment_obj) {
                return update_comment_meta($comment_obj->comment_ID, 'success_story', sanitize_textarea_field($value));
            },
            'schema' => ['type' => 'string'],
        ]);

        // Tavsiye eder mi
        register_rest_field('comment', 'would_recommend', [
            'get_callback' => function ($comment) {
                $val = get_comment_meta($comment['id'], 'would_recommend', true);
                return $val !== '' ? (bool) $val : null;
            },
            'update_callback' => function ($value, $comment_obj) {
                return update_comment_meta($comment_obj->comment_ID, 'would_recommend', (bool) $value);
            },
            'schema' => ['type' => 'boolean'],
        ]);

        // Doğrulanmış danışan (ödeme/randevu geçmişi varsa)
        register_rest_field('comment', 'verified_client', [
            'get_callback' => function ($comment) {
                return (bool) get_comment_meta($comment['id'], 'verified_client', true);
            },
            'schema' => ['type' => 'boolean'],
        ]);
    }

    public function get_author_details($comment) {
        // Handle both array and object comment formats
        // WordPress uses 'user_id' in comment objects
        $user_id = 0;
        $comment_id = 0;
        if (is_array($comment)) {
            $user_id = isset($comment['user_id']) ? $comment['user_id'] : (isset($comment['author']) ? $comment['author'] : 0);
            $comment_id = isset($comment['id']) ? $comment['id'] : 0;
        } elseif (is_object($comment)) {
            $user_id = isset($comment->user_id) ? $comment->user_id : 0;
            $comment_id = isset($comment->comment_ID) ? $comment->comment_ID : 0;
        }
            
        if (!$user_id) return [
            'id' => 0,
            'name' => 'Anonim Kullanıcı',
            'username' => '',
            'is_expert' => false,
            'role_label' => 'ANONIM',
            'rank' => 0,
            'score' => 0,
            'title' => '',
            'avatar' => 'https://api.dicebear.com/9.x/personas/svg?seed=anonymous',
            'profile_url' => '',
            'is_anonymous' => false
        ];

        $user = get_userdata($user_id);
        if (!$user) return [
            'id' => 0,
            'name' => 'Silinmiş Kullanıcı',
            'username' => '',
            'is_expert' => false,
            'role_label' => 'SİLİNDİ',
            'rank' => 0,
            'score' => 0,
            'title' => '',
            'avatar' => 'https://api.dicebear.com/9.x/personas/svg?seed=deleted',
            'profile_url' => '',
            'is_anonymous' => false
        ];

        // Check if comment is anonymous
        $is_anonymous = false;
        if ($comment_id) {
            $is_anonymous = (bool) get_comment_meta($comment_id, 'is_anonymous', true);
        }

        if ($is_anonymous && $user_id) {
            // Convert name to initials: "Ahmet Kaya" -> "A.K."
            $display_name = $user->display_name;
            $initials = implode('.', array_map(function($word) {
                return mb_strtoupper(mb_substr($word, 0, 1));
            }, explode(' ', $display_name))) . '.';
            
            return [
                'id' => 0, // Hide ID for anonymous
                'name' => $initials,
                'username' => '',
                'is_expert' => false,
                'role_label' => 'ANONİM',
                'rank' => (int) get_user_meta($user_id, 'rejimde_rank', true) ?: 1,
                'score' => 0,
                'title' => '',
                'avatar' => 'https://api.dicebear.com/9.x/initials/svg?seed=' . urlencode($initials),
                'profile_url' => '',
                'is_anonymous' => true
            ];
        }

        $roles = (array) $user->roles;
        $is_expert = in_array('rejimde_pro', $roles);
        
        // Puan Mantığı: Uzmansa 'rating' (ortalaması), üyeyse 'total_score' (XP)
        $score = $is_expert 
            ? get_post_meta(get_user_meta($user_id, 'professional_profile_id', true), 'puan', true) 
            : get_user_meta($user_id, 'rejimde_total_score', true);

        // Profile URL: experts go to /experts/{slug}, regular users go to /profile/{username}
        $profile_url = '';
        if ($is_expert) {
            $professional_id = get_user_meta($user_id, 'professional_profile_id', true);
            if ($professional_id) {
                $professional_post = get_post($professional_id);
                if ($professional_post) {
                    $profile_url = '/experts/' . $professional_post->post_name;
                }
            }
        }
        if (empty($profile_url)) {
            $profile_url = '/profile/' . $user->user_login;
        }

        return [
            'id' => $user_id,
            'name' => $user->display_name,
            'username' => $user->user_login,
            'is_expert' => $is_expert,
            'role_label' => $is_expert ? 'UZMAN' : 'ÜYE',
            'rank' => (int) get_user_meta($user_id, 'rejimde_rank', true) ?: 1,
            'score' => $score ?: 0,
            'title' => get_user_meta($user_id, 'title', true) ?: '', // Dyt., Pt. vb.
            'avatar' => get_user_meta($user_id, 'avatar_url', true) 
                        ?: 'https://api.dicebear.com/9.x/personas/svg?seed=' . urlencode($user->user_login),
            'profile_url' => $profile_url,
            'is_anonymous' => false
        ];
    }
}