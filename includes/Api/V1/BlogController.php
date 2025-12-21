<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

class BlogController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'blog';

    public function register_routes() {
        // Oluşturma
        register_rest_route($this->namespace, '/' . $this->base . '/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Güncelleme (ID ile)
        register_rest_route($this->namespace, '/' . $this->base . '/update/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    /**
     * YAZI OLUŞTUR
     */
    public function create_item($request) {
        return $this->handle_save($request, 'create');
    }

    /**
     * YAZI GÜNCELLE
     */
    public function update_item($request) {
        return $this->handle_save($request, 'update');
    }

    /**
     * ORTAK KAYIT MANTIĞI
     */
    private function handle_save($request, $action) {
        $params = $request->get_json_params();
        
        $title = sanitize_text_field($params['title'] ?? '');
        // wp_kses_post ile güvenli HTML'e izin veriyoruz (Gutenberg blokları için şart)
        $content = wp_kses_post($params['content'] ?? ''); 
        $status = sanitize_text_field($params['status'] ?? 'draft');
        $excerpt = sanitize_textarea_field($params['excerpt'] ?? '');
        $is_sticky = isset($params['sticky']) ? (bool) $params['sticky'] : false; // YENİ
        $categories = $params['categories'] ?? [];
        $tags = $params['tags'] ?? []; // ['diyet', 'spor'] gibi string array
        $featured_media_id = intval($params['featured_media_id'] ?? 0);
        $meta = $params['meta'] ?? [];

        if (empty($title)) {
            return $this->error('Başlık zorunludur.', 400);
        }

        // Post Verisi Hazırla
        $post_data = [
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => $status,
            'post_excerpt'  => $excerpt,
            'post_type'     => 'post',
            'post_author'   => get_current_user_id()
        ];

        // İŞLEM: GÜNCELLEME veya EKLEME
        if ($action === 'update') {
            $post_id = $request->get_param('id');
            $post_data['ID'] = $post_id;
            
            // Yetki Kontrolü: Yazı başkasının mı?
            $post = get_post($post_id);
            if (!$post) return $this->error('Yazı bulunamadı.', 404);
            
            // Sadece kendi yazısını veya editörse herkesinkini düzenleyebilir
            if ($post->post_author != get_current_user_id() && !current_user_can('edit_others_posts')) {
                return $this->error('Bu yazıyı düzenleme yetkiniz yok.', 403);
            }

            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if (is_wp_error($result)) {
            return $this->error($result->get_error_message(), 500);
        }
        
        $post_id = $result;

        // 1. Kategoriler
        if (!empty($categories)) {
            wp_set_post_categories($post_id, $categories);
        }

        // 2. Etiketler (String dizisini otomatik ID'ye çevirip atar)
        if (!empty($tags)) {
            wp_set_post_tags($post_id, $tags);
        }

        // 3. Öne Çıkan Görsel
        if ($featured_media_id > 0) {
            set_post_thumbnail($post_id, $featured_media_id);
        }
        // Sticky (Yapışkan) Ayarı
        if ($is_sticky) {
            stick_post($post_id);
        } else {
            unstick_post($post_id);
        }
        // 4. Meta / SEO Verileri
        if (!empty($meta)) {
            foreach ($meta as $key => $value) {
                update_post_meta($post_id, sanitize_key($key), sanitize_text_field($value));
            }
        }

        $post = get_post($post_id);
        return $this->success([
            'id' => $post->ID,
            'slug' => $post->post_name,
            'link' => get_permalink($post->ID),
            'message' => $action === 'update' ? 'Yazı güncellendi.' : 'Yazı oluşturuldu.'
        ]);
    }

    public function check_permission() {
        return current_user_can('edit_posts');
    }

    protected function success($data = null) {
        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }

    protected function error($message = 'Error', $code = 400) {
        return new WP_REST_Response(['status' => 'error', 'message' => $message], $code);
    }
}