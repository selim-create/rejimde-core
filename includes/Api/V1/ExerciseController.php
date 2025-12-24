<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

class ExerciseController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'exercises';

    public function register_routes() {
        // Listeleme
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => '__return_true',
        ]);

        // Oluşturma
        register_rest_route($this->namespace, '/' . $this->base . '/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Güncelleme
        register_rest_route($this->namespace, '/' . $this->base . '/update/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Detay (Slug ile)
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => '__return_true',
        ]);

        // Onaylama
        register_rest_route($this->namespace, '/' . $this->base . '/approve/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_plan'],
            'permission_callback' => [$this, 'check_expert_permission'], 
        ]);
    }

    public function create_item($request) { return $this->handle_save($request, 'create'); }
    public function update_item($request) { return $this->handle_save($request, 'update'); }

    // --- Kayıt İşlemi ---
    private function handle_save($request, $action) {
        $params = $request->get_json_params();
        
        if (empty($params['title'])) {
            return new WP_Error('missing_title', 'Başlık zorunludur.', ['status' => 400]);
        }

        $post_data = [
            'post_title'   => sanitize_text_field($params['title']),
            'post_content' => wp_kses_post($params['content'] ?? ''),
            'post_status'  => 'publish',
            'post_type'    => 'rejimde_exercise', // DİKKAT: Post type farklı
            'post_author'  => get_current_user_id(),
        ];

        if ($action === 'update') {
            $post_id = $request['id'];
            $post_data['ID'] = $post_id;
            
            // Yetki Kontrolü
            $existing = get_post($post_id);
            if (!$existing || $existing->post_type !== 'rejimde_exercise') {
                return new WP_Error('not_found', 'Egzersiz planı bulunamadı.', ['status' => 404]);
            }
            if (!current_user_can('manage_options') && $existing->post_author != get_current_user_id()) {
                return new WP_Error('forbidden', 'Yetkiniz yok.', ['status' => 403]);
            }
            $result = wp_update_post($post_data);
        } else {
            $result = wp_insert_post($post_data);
        }

        if (is_wp_error($result)) return $result;
        $post_id = $result;

        // Meta Kaydı
        if (isset($params['plan_data'])) {
            $json_plan = is_string($params['plan_data']) ? $params['plan_data'] : json_encode($params['plan_data'], JSON_UNESCAPED_UNICODE);
            update_post_meta($post_id, 'plan_data', $json_plan);
        }

        if (!empty($params['featured_media_id'])) {
            set_post_thumbnail($post_id, intval($params['featured_media_id']));
        }

        if (!empty($params['meta']) && is_array($params['meta'])) {
            $meta = $params['meta'];
            $simple_fields = ['difficulty', 'duration', 'calories', 'score_reward', 'exercise_category', 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'];

            foreach ($simple_fields as $key) {
                if (isset($meta[$key])) {
                    update_post_meta($post_id, $key, sanitize_text_field($meta[$key]));
                }
            }

            // Ekipman Listesi (Shopping List yerine)
            if (isset($meta['equipment_list'])) {
                $eq_list = is_string($meta['equipment_list']) ? $meta['equipment_list'] : json_encode($meta['equipment_list'], JSON_UNESCAPED_UNICODE);
                update_post_meta($post_id, 'equipment_list', $eq_list);
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
            'message' => $action === 'create' ? 'Egzersiz planı oluşturuldu.' : 'Egzersiz planı güncellendi.'
        ]);
    }

    // --- Listeleme ---
    public function get_items($request) {
        $args = [
            'post_type' => 'rejimde_exercise',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $posts = get_posts($args);
        $data = [];

        foreach ($posts as $post) {
            $author_id = $post->post_author;
            $author_name = get_the_author_meta('display_name', $author_id);
            $author_avatar = get_avatar_url($author_id);

            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => wp_trim_words($post->post_content, 20),
                'slug' => $post->post_name,
                'image' => get_the_post_thumbnail_url($post->ID, 'medium_large') ?: '',
                'meta' => [
                    'difficulty' => get_post_meta($post->ID, 'difficulty', true),
                    'duration' => get_post_meta($post->ID, 'duration', true),
                    'calories' => get_post_meta($post->ID, 'calories', true),
                    'exercise_category' => get_post_meta($post->ID, 'exercise_category', true),
                    'is_verified' => (bool) get_post_meta($post->ID, 'is_verified', true),
                ],
                'author' => [
                    'name' => $author_name,
                    'avatar' => $author_avatar,
                ]
            ];
        }
        return $this->success($data);
    }

    // --- Detay ---
    public function get_item($request) {
        $slug = $request['slug'];
        $posts = get_posts(['name' => $slug, 'post_type' => 'rejimde_exercise', 'post_status' => 'publish', 'numberposts' => 1]);

        if (empty($posts)) return new WP_Error('not_found', 'Plan bulunamadı.', ['status' => 404]);

        $post = $posts[0];
        $post_id = $post->ID;

        $plan_data = json_decode(get_post_meta($post_id, 'plan_data', true)) ?: [];
        $equipment_list = json_decode(get_post_meta($post_id, 'equipment_list', true)) ?: [];
        $tags = json_decode(get_post_meta($post_id, 'tags', true)) ?: [];
        
        $author_id = $post->post_author;
        $author_user = get_userdata($author_id);
        
        $data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'slug' => $post->post_name,
            'image' => get_the_post_thumbnail_url($post->ID, 'large') ?: '',
            'plan_data' => $plan_data,
            'equipment_list' => $equipment_list,
            'tags' => $tags,
            'meta' => [
                'difficulty' => get_post_meta($post_id, 'difficulty', true),
                'duration' => get_post_meta($post_id, 'duration', true),
                'calories' => get_post_meta($post_id, 'calories', true),
                'score_reward' => get_post_meta($post_id, 'score_reward', true),
                'exercise_category' => get_post_meta($post_id, 'exercise_category', true),
                'is_verified' => (bool) get_post_meta($post_id, 'is_verified', true),
            ],
            'author' => [
                'name' => $author_user->display_name,
                'avatar' => get_avatar_url($author_id),
                'slug' => $author_user->user_nicename,
                'is_expert' => in_array('rejimde_pro', (array) $author_user->roles) || in_array('administrator', (array) $author_user->roles)
            ],
            'date' => get_the_date('d F Y', $post_id)
        ];

        return $this->success($data);
    }

    public function approve_plan($request) {
        update_post_meta($request['id'], 'is_verified', true);
        update_post_meta($request['id'], 'approved_by', get_current_user_id());
        return $this->success(['message' => 'Onaylandı.']);
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
    
    protected function success($data) {
        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }
}