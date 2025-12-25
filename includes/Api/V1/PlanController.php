<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;
use WP_User;

class PlanController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'plans';

    public function register_routes() {
        // YENİ: Listeleme (Tüm Planlar)
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => '__return_true', // Herkes görebilir
        ]);

        // ... (Diğer route'lar aynı kalıyor: create, update, get_item, approve, start, complete)
        register_rest_route($this->namespace, '/' . $this->base . '/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/update/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/approve/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_plan'],
            'permission_callback' => [$this, 'check_expert_permission'], 
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/start/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'start_plan'],
            'permission_callback' => [$this, 'check_auth_permission'], 
        ]);
        
        register_rest_route($this->namespace, '/' . $this->base . '/complete/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_plan'],
            'permission_callback' => [$this, 'check_auth_permission'],
        ]);
    }

    public function create_item($request) { return $this->handle_save($request, 'create'); }
    public function update_item($request) { return $this->handle_save($request, 'update'); }

    // ... (handle_save aynı kalıyor) ...
    private function handle_save($request, $action) {
        $params = $request->get_json_params();
        
        if (empty($params['title'])) {
            return new WP_Error('missing_title', 'Başlık zorunludur.', ['status' => 400]);
        }

        $post_data = [
            'post_title'   => sanitize_text_field($params['title']),
            'post_content' => wp_kses_post($params['content'] ?? ''),
            'post_status'  => 'publish',
            'post_type'    => 'rejimde_plan',
            'post_author'  => get_current_user_id(),
        ];

        if ($action === 'update') {
            $post_id = $request['id'];
            $post_data['ID'] = $post_id;
            
            $existing = get_post($post_id);
            if (!$existing || $existing->post_type !== 'rejimde_plan') {
                return new WP_Error('not_found', 'Plan bulunamadı.', ['status' => 404]);
            }
            
            if (!current_user_can('manage_options') && $existing->post_author != get_current_user_id()) {
                return new WP_Error('forbidden', 'Bu planı düzenleme yetkiniz yok.', ['status' => 403]);
            }

            $result = wp_update_post($post_data);
        } else {
            $result = wp_insert_post($post_data);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $post_id = $result;

        if (isset($params['plan_data'])) {
            $json_plan = is_string($params['plan_data']) ? $params['plan_data'] : json_encode($params['plan_data'], JSON_UNESCAPED_UNICODE);
            update_post_meta($post_id, 'plan_data', $json_plan);
        }

        if (!empty($params['featured_media_id'])) {
            set_post_thumbnail($post_id, intval($params['featured_media_id']));
        }

        if (!empty($params['meta']) && is_array($params['meta'])) {
            $meta = $params['meta'];
            $simple_fields = ['difficulty', 'duration', 'calories', 'score_reward', 'diet_category', 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'];

            foreach ($simple_fields as $key) {
                if (isset($meta[$key])) {
                    update_post_meta($post_id, $key, sanitize_text_field($meta[$key]));
                }
            }

            if (isset($meta['shopping_list'])) {
                $shop_list = is_string($meta['shopping_list']) ? $meta['shopping_list'] : json_encode($meta['shopping_list'], JSON_UNESCAPED_UNICODE);
                update_post_meta($post_id, 'shopping_list', $shop_list);
            }

            if (isset($meta['tags'])) {
                $tags = is_string($meta['tags']) ? $meta['tags'] : json_encode($meta['tags'], JSON_UNESCAPED_UNICODE);
                update_post_meta($post_id, 'tags', $tags);
            }
        }

        $post = get_post($post_id);
        return $this->success([
            'id' => $post->ID,
            'slug' => $post->post_name,
            'message' => $action === 'create' ? 'Plan oluşturuldu.' : 'Plan güncellendi.'
        ]);
    }

    // --- YENİ: Listeleme Metodu ---
    public function get_items($request) {
        $args = [
            'post_type' => 'rejimde_plan',
            'post_status' => 'publish',
            'posts_per_page' => -1, // Tümünü getir
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        // Filtreleme parametreleri eklenebilir
        // if ($request['category']) ...

        $posts = get_posts($args);
        $data = [];

        foreach ($posts as $post) {
            $author_id = $post->post_author;
            $author_name = get_the_author_meta('display_name', $author_id);
            $author_user = get_userdata($author_id);
            $author_avatar = get_avatar_url($author_id); // Varsayılan WP avatarı, frontendde dicebear fallback var

            $completed_users_raw = get_post_meta($post->ID, 'completed_users', true);
            // Handle both array and JSON string formats
            if (is_array($completed_users_raw)) {
                $completed_users = $completed_users_raw;
            } elseif (is_string($completed_users_raw) && !empty($completed_users_raw)) {
                $completed_users = json_decode($completed_users_raw, true) ?: [];
            } else {
                $completed_users = [];
            }
            
            // Son 3 tamamlayan (avatar için)
            $last_completed_avatars = [];
            $count = 0;
            if(is_array($completed_users)) {
                foreach (array_reverse($completed_users) as $uid) {
                    if ($count >= 3) break;
                    $last_completed_avatars[] = [
                        'avatar' => get_avatar_url($uid),
                        'name' => get_the_author_meta('display_name', $uid)
                    ];
                    $count++;
                }
            }

            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => wp_trim_words($post->post_content, 20),
                'content' => $post->post_content, // Full içerik gerekirse
                'slug' => $post->post_name,
                'image' => get_the_post_thumbnail_url($post->ID, 'medium_large') ?: '',
                'date' => $post->post_date,
                'meta' => [
                    'difficulty' => get_post_meta($post->ID, 'difficulty', true),
                    'duration' => get_post_meta($post->ID, 'duration', true),
                    'calories' => get_post_meta($post->ID, 'calories', true),
                    'score_reward' => get_post_meta($post->ID, 'score_reward', true),
                    'diet_category' => get_post_meta($post->ID, 'diet_category', true),
                    'is_verified' => (bool) get_post_meta($post->ID, 'is_verified', true),
                ],
                'author' => [
                    'name' => $author_name,
                    'avatar' => $author_avatar,
                    'slug' => $author_user ? $author_user->user_nicename : ''
                ],
                'completed_users' => $last_completed_avatars, // Sadece avatar listesi
                'completed_count' => count($completed_users)
            ];
        }

        return $this->success($data);
    }

    // ... (approve, start, complete, get_item metodları aynı kalıyor) ...
    public function approve_plan($request) {
        $post_id = (int) $request['id'];
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        // Yetki kontrolü
        $roles = (array) $user->roles;
        if (!in_array('rejimde_pro', $roles) && !in_array('administrator', $roles)) {
            return new WP_Error('forbidden', 'Bu işlem için yetkiniz yok.', ['status' => 403]);
        }

        // Mevcut onaylayanları al
        $approved_by = get_post_meta($post_id, 'approved_by', true);
        if (!is_array($approved_by)) {
            // Eski format (tek kişi) - yeni formata çevir
            $old_approver = $approved_by;
            $approved_by = $old_approver ? [$old_approver] : [];
        }

        // Bu uzman zaten onaylamış mı?
        if (in_array($user_id, $approved_by)) {
            return new WP_Error('already_approved', 'Bu içeriği zaten onayladınız.', ['status' => 400]);
        }

        // Onaylayanlara ekle
        $approved_by[] = $user_id;
        update_post_meta($post_id, 'approved_by', $approved_by);
        update_post_meta($post_id, 'is_verified', true);

        // Onaylayan bilgilerini hazırla
        $approvers_info = [];
        foreach ($approved_by as $approver_id) {
            $approver = get_userdata($approver_id);
            if ($approver) {
                $approvers_info[] = [
                    'id' => $approver_id,
                    'name' => $approver->display_name,
                    'avatar' => get_user_meta($approver_id, 'avatar_url', true) ?: get_avatar_url($approver_id),
                    'slug' => $approver->user_nicename,
                    'title' => get_user_meta($approver_id, 'title', true) ?: 'Uzman'
                ];
            }
        }

        // Geriye uyumluluk için approved_by_users meta'sını da güncelle
        update_post_meta($post_id, 'approved_by_users', $approved_by);

        return $this->success([
            'message' => 'İçerik başarıyla onaylandı!',
            'approved_by' => $approvers_info,
            'approval_count' => count($approved_by)
        ]);
    }

    public function start_plan($request) {
        $post_id = $request['id'];
        $user_id = get_current_user_id();
        $started_users_raw = get_post_meta($post_id, 'started_users', true);
        // Handle both array and JSON string formats
        if (is_array($started_users_raw)) {
            $started_users = $started_users_raw;
        } elseif (is_string($started_users_raw) && !empty($started_users_raw)) {
            $started_users = json_decode($started_users_raw, true) ?: [];
        } else {
            $started_users = [];
        }
        if (!in_array($user_id, $started_users)) {
            $started_users[] = $user_id;
            update_post_meta($post_id, 'started_users', json_encode($started_users));
        }
        return $this->success(['message' => 'Diyete başarıyla başladınız.']);
    }
    
    public function complete_plan($request) {
        $post_id = $request['id'];
        $user_id = get_current_user_id();
        $completed_users_raw = get_post_meta($post_id, 'completed_users', true);
        // Handle both array and JSON string formats
        if (is_array($completed_users_raw)) {
            $completed_users = $completed_users_raw;
        } elseif (is_string($completed_users_raw) && !empty($completed_users_raw)) {
            $completed_users = json_decode($completed_users_raw, true) ?: [];
        } else {
            $completed_users = [];
        }
        if (!in_array($user_id, $completed_users)) {
            $completed_users[] = $user_id;
            update_post_meta($post_id, 'completed_users', json_encode($completed_users));
        }
        return $this->success(['message' => 'Diyet tamamlandı olarak işaretlendi.']);
    }

    public function get_item($request) {
        $slug = $request['slug'];
        $posts = get_posts(['name' => $slug, 'post_type' => 'rejimde_plan', 'post_status' => 'publish', 'numberposts' => 1]);

        if (empty($posts)) { return new WP_Error('not_found', 'Plan bulunamadı.', ['status' => 404]); }

        $post = $posts[0];
        $post_id = $post->ID;

        // Meta Çözümleme
        $plan_data = json_decode(get_post_meta($post_id, 'plan_data', true)) ?: [];
        $shopping_list = json_decode(get_post_meta($post_id, 'shopping_list', true)) ?: [];
        $tags = json_decode(get_post_meta($post_id, 'tags', true)) ?: [];
        
        // Yazar Bilgisi
        $author_id = $post->post_author;
        $author_user = get_userdata($author_id);
        $author_data = [
            'id' => $author_id,
            'name' => $author_user->display_name,
            'avatar' => get_avatar_url($author_id),
            'slug' => $author_user->user_nicename,
            'is_expert' => in_array('rejimde_pro', (array) $author_user->roles) || in_array('administrator', (array) $author_user->roles)
        ];

        // Onaylayan Uzman Bilgisi
        $approved_by_id = get_post_meta($post_id, 'approved_by', true);
        $approved_by = null;
        if ($approved_by_id) {
            $approver = get_userdata($approved_by_id);
            if ($approver) {
                $approved_by = [
                    'name' => $approver->display_name,
                    'avatar' => get_avatar_url($approved_by_id),
                    'slug' => $approver->user_nicename
                ];
            }
        }

        // Onaylayan Uzmanlar (Çoklu)
        $approved_by_users = get_post_meta($post_id, 'approved_by_users', true);
        $approvers = [];
        if (is_array($approved_by_users)) {
            foreach ($approved_by_users as $approver_id) {
                $approver = get_userdata($approver_id);
                if ($approver) {
                    $approvers[] = [
                        'id' => $approver_id,
                        'name' => $approver->display_name,
                        'avatar' => get_user_meta($approver_id, 'avatar_url', true) ?: get_avatar_url($approver_id),
                        'slug' => $approver->user_nicename,
                        'profession' => get_user_meta($approver_id, 'profession', true)
                    ];
                }
            }
        }
        
        // Tamamlayan Kullanıcılar (Son 5 - Avatar için)
        $completed_users_raw = get_post_meta($post_id, 'completed_users', true);
        if (is_array($completed_users_raw)) {
            $completed_users_ids = $completed_users_raw;
        } elseif (is_string($completed_users_raw) && !empty($completed_users_raw)) {
            $completed_users_ids = json_decode($completed_users_raw, true) ?: [];
        } else {
            $completed_users_ids = [];
        }
        $completed_users = [];
        $count = 0;
        foreach (array_reverse($completed_users_ids) as $uid) {
            if ($count >= 5) break;
            $u = get_userdata($uid);
            if ($u) {
                $completed_users[] = [
                    'id' => $uid,
                    'name' => $u->display_name,
                    'avatar' => get_avatar_url($uid),
                    'slug' => $u->user_nicename // Link için slug ekledik
                ];
                $count++;
            }
        }

        $data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'slug' => $post->post_name,
            'image' => get_the_post_thumbnail_url($post->ID, 'large') ?: '',
            'plan_data' => $plan_data,
            'shopping_list' => $shopping_list,
            'tags' => $tags,
            'meta' => [
                'difficulty' => get_post_meta($post_id, 'difficulty', true),
                'duration' => get_post_meta($post_id, 'duration', true),
                'calories' => get_post_meta($post_id, 'calories', true),
                'score_reward' => get_post_meta($post_id, 'score_reward', true),
                'diet_category' => get_post_meta($post_id, 'diet_category', true),
                'is_verified' => (bool) get_post_meta($post_id, 'is_verified', true),
            ],
            'author' => $author_data,
            'approved_by' => $approved_by,
            'approvers' => $approvers,
            'completed_users' => $completed_users,
            'completed_count' => count($completed_users_ids),
            'date' => get_the_date('d F Y', $post_id)
        ];

        return $this->success($data);
    }

    public function check_permission() { return current_user_can('edit_posts'); }
    public function check_expert_permission() { 
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        $allowed_roles = ['administrator', 'editor', 'rejimde_pro'];
        return !empty(array_intersect($allowed_roles, (array) $user->roles));
    }
    public function check_auth_permission() { return is_user_logged_in(); }

    protected function success($data) {
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data
        ], 200);
    }
}