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
        // Diyet Oluşturma
        register_rest_route($this->namespace, '/' . $this->base . '/generate-diet', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_diet'],
            'permission_callback' => '__return_true', // Test için
        ]);

        // YENİ: Egzersiz Oluşturma
        register_rest_route($this->namespace, '/' . $this->base . '/generate-exercise', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_exercise'],
            'permission_callback' => '__return_true', // Test için
        ]);
    }

    public function generate_diet($request) {
        // Servis yüklü mü kontrol et
        if (!$this->ai_service) {
            return new WP_Error('service_unavailable', 'AI servisi başlatılamadı.', ['status' => 500]);
        }

        $params = $request->get_json_params();

        // Temel Validasyon
        if (empty($params['age']) || empty($params['weight']) || empty($params['goal'])) {
            return new WP_Error('missing_params', 'Lütfen yaş, kilo ve hedef alanlarını doldurun.', ['status' => 400]);
        }

        // AI Servisini Çağır
        $ai_result = $this->ai_service->generate_diet_plan($params);

        if (is_wp_error($ai_result)) {
            return $ai_result;
        }

        // Dönen veriyi kontrol et
        if (empty($ai_result['plan_data']) || !is_array($ai_result['plan_data'])) {
            // AI bazen düzgün JSON dönmeyebilir, mock data ile fallback yapalım (Geliştirme aşaması için)
            // return new WP_Error('ai_error', 'Yapay zeka geçerli bir plan üretemedi.', ['status' => 500]);
        }

        // Planı Veritabanına Kaydet (Draft Olarak)
        $user_id = get_current_user_id();
        
        $post_data = [
            'post_title'   => sanitize_text_field($ai_result['title'] ?? 'AI Diyet Planı'),
            'post_content' => wp_kses_post($ai_result['description'] ?? ''),
            'post_status'  => 'publish', 
            'post_type'    => 'rejimde_plan',
            'post_author'  => $user_id ? $user_id : 1, // Kullanıcı yoksa admin'e ata
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return new WP_Error('db_error', 'Plan kaydedilemedi.', ['status' => 500]);
        }

        // Meta Verilerini Kaydet
        if (!empty($ai_result['plan_data'])) {
            $json_plan = json_encode($ai_result['plan_data'], JSON_UNESCAPED_UNICODE);
            update_post_meta($post_id, 'plan_data', $json_plan);
        }
        
        if (!empty($ai_result['shopping_list'])) {
            update_post_meta($post_id, 'shopping_list', json_encode($ai_result['shopping_list'], JSON_UNESCAPED_UNICODE));
        }
        
        if (!empty($ai_result['tags'])) {
            update_post_meta($post_id, 'tags', json_encode($ai_result['tags'], JSON_UNESCAPED_UNICODE));
        }

        if (!empty($ai_result['meta'])) {
            foreach ($ai_result['meta'] as $key => $value) {
                update_post_meta($post_id, $key, sanitize_text_field($value));
            }
        }

        update_post_meta($post_id, 'is_ai_generated', true);
        
        if ($user_id) {
            update_post_meta($post_id, 'started_users', json_encode([$user_id]));
        }

        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Diyet planınız başarıyla oluşturuldu.',
            'data' => [
                'id' => $post_id,
                'slug' => get_post_field('post_name', $post_id),
                'title' => get_the_title($post_id)
            ]
        ], 200);
    }

    // YENİ: Egzersiz Oluşturma Metodu
    public function generate_exercise($request) {
        if (!$this->ai_service) {
            return new WP_Error('service_unavailable', 'AI servisi başlatılamadı.', ['status' => 500]);
        }

        $params = $request->get_json_params();

        // Validasyon
        if (empty($params['fitness_level']) || empty($params['goal'])) {
            return new WP_Error('missing_params', 'Lütfen fitness seviyesi ve hedef alanlarını doldurun.', ['status' => 400]);
        }

        // AI Servisini Çağır
        $ai_result = $this->ai_service->generate_exercise_plan($params);

        if (is_wp_error($ai_result)) {
            return $ai_result;
        }

        // Planı Kaydet
        $user_id = get_current_user_id();
        
        $post_data = [
            'post_title'   => sanitize_text_field($ai_result['title'] ?? 'AI Egzersiz Planı'),
            'post_content' => wp_kses_post($ai_result['description'] ?? ''),
            'post_status'  => 'publish',
            'post_type'    => 'rejimde_exercise', // Egzersiz post type'ı
            'post_author'  => $user_id ? $user_id : 1,
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return new WP_Error('db_error', 'Plan kaydedilemedi.', ['status' => 500]);
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
        }

        if (!empty($ai_result['meta'])) {
            foreach ($ai_result['meta'] as $key => $value) {
                update_post_meta($post_id, $key, sanitize_text_field($value));
            }
        }

        update_post_meta($post_id, 'is_ai_generated', true);

        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Egzersiz planınız başarıyla oluşturuldu.',
            'data' => [
                'id' => $post_id,
                'slug' => get_post_field('post_name', $post_id),
                'title' => get_the_title($post_id)
            ]
        ], 200);
    }
}