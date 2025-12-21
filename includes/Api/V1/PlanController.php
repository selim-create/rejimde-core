<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

class PlanController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'plans';

    public function register_routes() {
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
    }

    public function create_item($request) { return $this->handle_save($request, 'create'); }
    public function update_item($request) { return $this->handle_save($request, 'update'); }

    private function handle_save($request, $action) {
        $params = $request->get_json_params();
        $title = sanitize_text_field($params['title'] ?? '');
        $content = wp_kses_post($params['content'] ?? '');
        $status = sanitize_text_field($params['status'] ?? 'draft');
        
        // KRİTİK DÜZELTME: Gelen plan datasını array olarak garantiye al
        $plan_data = isset($params['plan_data']) && is_array($params['plan_data']) 
            ? array_values($params['plan_data']) 
            : [];
            
        $meta = $params['meta'] ?? [];
        $featured_media_id = intval($params['featured_media_id'] ?? 0);
        $categories = $params['categories'] ?? [];

        if (empty($title)) return $this->error('Başlık zorunludur.', 400);

        $post_data = [
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => $status,
            'post_type'     => 'rejimde_plan',
            'post_author'   => get_current_user_id()
        ];

        if ($action === 'update') {
            $post_id = $request->get_param('id');
            $post_data['ID'] = $post_id;
            // Yetki kontrolü...
            $post = get_post($post_id);
            if (!$post) return $this->error('Plan bulunamadı.', 404);
            if ($post->post_author != get_current_user_id() && !current_user_can('edit_others_posts')) {
                return $this->error('Yetkiniz yok.', 403);
            }
            wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if (is_wp_error($post_id)) return $this->error($post_id->get_error_message(), 500);

        // JSON olarak kaydet (Unicode desteği ile)
        update_post_meta($post_id, 'plan_data', wp_json_encode($plan_data, JSON_UNESCAPED_UNICODE));
        
        if (!empty($meta)) {
            foreach ($meta as $key => $value) {
                update_post_meta($post_id, sanitize_key($key), sanitize_text_field($value));
            }
        }

        if ($featured_media_id > 0) set_post_thumbnail($post_id, $featured_media_id);
        if (!empty($categories)) wp_set_post_categories($post_id, $categories);

        $post = get_post($post_id);
        return $this->success([
            'id' => $post->ID,
            'slug' => $post->post_name,
            'link' => get_permalink($post->ID),
            'message' => 'Plan kaydedildi.'
        ]);
    }

    public function get_item($request) {
        $slug = $request->get_param('slug');
        $args = ['name' => $slug, 'post_type' => 'rejimde_plan', 'numberposts' => 1];
        $posts = get_posts($args);

        if (empty($posts)) return $this->error('Plan bulunamadı', 404);
        
        $post = $posts[0];
        
        // Veriyi çek ve decode et
        $raw_plan_data = get_post_meta($post->ID, 'plan_data', true);
        $plan_data = json_decode($raw_plan_data, true);

        // KRİTİK DÜZELTME: Verinin kesinlikle bir liste (array) olduğundan emin ol
        if (!is_array($plan_data)) {
            $plan_data = [];
        } else {
            // Eğer obje geldiyse (key-value), onu listeye çevir
            if (array_keys($plan_data) !== range(0, count($plan_data) - 1)) {
                $plan_data = array_values($plan_data);
            }
        }
        
        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);
        // Avatar mantığı: Özel > DiceBear
        $author_avatar = get_user_meta($author_id, 'avatar_url', true) ?: 'https://api.dicebear.com/9.x/personas/svg?seed=' . get_the_author_meta('user_nicename', $author_id);
        
        // Uzman mı?
        $user = new \WP_User($author_id);
        $is_expert = in_array('rejimde_pro', (array) $user->roles);
        $author_slug = get_the_author_meta('user_nicename', $author_id);

        $data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'image' => get_the_post_thumbnail_url($post->ID, 'large') ?: 'https://placehold.co/600x400',
            'plan_data' => $plan_data,
            'meta' => [
                'difficulty' => get_post_meta($post->ID, 'difficulty', true),
                'duration' => get_post_meta($post->ID, 'duration', true),
                'calories' => get_post_meta($post->ID, 'calories', true),
            ],
            'author' => [
                'name' => $author_name,
                'avatar' => $author_avatar,
                'slug' => $author_slug,
                'is_expert' => $is_expert
            ],
            'categories' => [] // Gerekirse eklenebilir
        ];

        return $this->success($data);
    }

    public function check_permission() { return current_user_can('edit_posts'); }
    protected function success($data = null) { return new WP_REST_Response(['status' => 'success', 'data' => $data], 200); }
    protected function error($message = 'Error', $code = 400) { return new WP_REST_Response(['status' => 'error', 'message' => $message], $code); }
}