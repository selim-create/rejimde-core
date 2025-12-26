<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;
use Rejimde\Services\OpenAIService;

class AiController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'ai';
    private $ai_service;

    public function __construct() {
        if (class_exists('Rejimde\Services\OpenAIService')) {
            $this->ai_service = new OpenAIService();
        }
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base . '/generate-diet', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_diet'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/generate-exercise', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_exercise'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function generate_diet($request) {
        if (!$this->ai_service) return new WP_Error('service_unavailable', 'AI servisi yok.', ['status' => 500]);

        $params = $request->get_json_params();
        if (empty($params['goal'])) return new WP_Error('missing_params', 'Hedef gerekli.', ['status' => 400]);

        // 1. AI Servisini Çalıştır
        $ai_result = $this->ai_service->generate_diet_plan($params);
        if (is_wp_error($ai_result)) return $ai_result;

        $user_id = get_current_user_id();
        
        // 2. Post Oluştur
        $post_data = [
            'post_title'   => sanitize_text_field($ai_result['title'] ?? 'AI Diyet Planı'),
            'post_content' => wp_kses_post($ai_result['description'] ?? ''),
            'post_status'  => 'publish',
            'post_type'    => 'rejimde_plan',
            'post_author'  => $user_id ? $user_id : 1,
        ];

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) return new WP_Error('db_error', 'Kayıt başarısız.', ['status' => 500]);

        // 3. Görseli Yükle ve Ata (Sideload)
        if (!empty($ai_result['featured_image_url'])) {
            $image_id = $this->upload_image_from_url($ai_result['featured_image_url'], $post_id);
            if ($image_id && !is_wp_error($image_id)) {
                set_post_thumbnail($post_id, $image_id);
            }
        }

        // 4. Meta Verileri (Frontend Yapısına Uygun)
        if (!empty($ai_result['plan_data'])) {
            update_post_meta($post_id, 'plan_data', json_encode($ai_result['plan_data'], JSON_UNESCAPED_UNICODE));
        }
        
        if (!empty($ai_result['shopping_list'])) {
            update_post_meta($post_id, 'shopping_list', json_encode($ai_result['shopping_list'], JSON_UNESCAPED_UNICODE));
        }
        
        if (!empty($ai_result['tags'])) {
            $tags = $ai_result['tags'];
            update_post_meta($post_id, 'tags', json_encode($tags, JSON_UNESCAPED_UNICODE));
            wp_set_post_tags($post_id, $tags);
        }

        // Meta Objeleri
        if (!empty($ai_result['meta'])) {
            foreach ($ai_result['meta'] as $key => $value) {
                update_post_meta($post_id, $key, sanitize_text_field($value));
            }
        }

        // Diğer Zorunlu Alanlar
        update_post_meta($post_id, 'is_ai_generated', true);
        update_post_meta($post_id, 'diet_goal', sanitize_text_field($params['goal']));
        
        if ($user_id) update_post_meta($post_id, 'started_users', json_encode([$user_id]));

        $post = get_post($post_id);

        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Plan oluşturuldu.',
            'redirect_url' => "/diets/{$post->post_name}",
            'data' => ['slug' => $post->post_name]
        ], 200);
    }


    public function generate_exercise($request) {
        if (!$this->ai_service) return new WP_Error('service_unavailable', 'AI servisi başlatılamadı.', ['status' => 500]);

        $params = $request->get_json_params();
        
        // AI Çağrısı
        $ai_result = $this->ai_service->generate_exercise_plan($params);

        if (is_wp_error($ai_result)) return $ai_result;

        // Kayıt
        $user_id = get_current_user_id();
        $post_data = [
            'post_title'   => sanitize_text_field($ai_result['title'] ?? 'AI Egzersiz Planı'),
            'post_content' => wp_kses_post($ai_result['description'] ?? ''),
            'post_status'  => 'publish',
            'post_type'    => 'rejimde_exercise',
            'post_author'  => $user_id ? $user_id : 1,
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) return new WP_Error('db_error', 'Plan kaydedilemedi.', ['status' => 500]);

        // Görsel Yükleme (YENİ)
        if (!empty($ai_result['featured_image_url'])) {
            $image_id = $this->upload_image_from_url($ai_result['featured_image_url'], $post_id);
            if ($image_id && !is_wp_error($image_id)) {
                set_post_thumbnail($post_id, $image_id);
            }
        }

        // Meta Kaydı
        if (!empty($ai_result['plan_data'])) {
            update_post_meta($post_id, 'plan_data', json_encode($ai_result['plan_data'], JSON_UNESCAPED_UNICODE));
        }
        if (!empty($ai_result['equipment_list'])) {
            update_post_meta($post_id, 'equipment_list', json_encode($ai_result['equipment_list'], JSON_UNESCAPED_UNICODE));
        }
        if (!empty($ai_result['tags'])) {
            update_post_meta($post_id, 'tags', json_encode($ai_result['tags'], JSON_UNESCAPED_UNICODE));
            wp_set_post_tags($post_id, $ai_result['tags']);
        }
        if (!empty($ai_result['meta'])) {
            foreach ($ai_result['meta'] as $key => $value) {
                update_post_meta($post_id, $key, sanitize_text_field($value));
            }
        }

        update_post_meta($post_id, 'is_ai_generated', true);

        $post = get_post($post_id);

        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Egzersiz planınız başarıyla oluşturuldu.',
            'data' => [
                'id' => $post_id,
                'slug' => $post->post_name,
                'title' => get_the_title($post_id)
            ]
        ], 200);
    }

    /**
     * URL'den Görsel Yükleme (Ortak Helper)
     */
    private function upload_image_from_url($url, $post_id) {
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        $tmp = download_url($url);
        if (is_wp_error($tmp)) return $tmp;

        $file_array = [
            'name' => basename(parse_url($url, PHP_URL_PATH)) . '.jpg',
            'tmp_name' => $tmp
        ];

        $id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return $id;
        }

        return $id;
    }
}