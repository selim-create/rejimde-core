<?php
namespace Rejimde\Api;

use WP_REST_Controller;
use WP_REST_Response;

abstract class BaseController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';

    /**
     * Register the routes for the objects of the controller.
     * * HATA DÜZELTİLDİ: 'abstract' ifadesi kaldırıldı.
     * WP_REST_Controller içinde bu metod zaten var olduğu için abstract yapılamaz.
     * Alt sınıflar (Child classes) bu metodu override ederek (üzerine yazarak) kullanacaktır.
     */
    public function register_routes() {
        // Child classes will implement this logic.
    }

    // Başarılı Yanıt Standardı
    protected function success($data = null, $message = 'Success', $code = 200) {
        return new WP_REST_Response([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    // Hatalı Yanıt Standardı
    protected function error($message = 'Error', $code = 400, $data = null) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $message,
            'error_data' => $data
        ], $code);
    }

    // Yetki Kontrolü (Middleware gibi)
    protected function check_auth($request) {
        // JWT kontrolü veya WP Nonce kontrolü burada yapılabilir
        // Şimdilik basit user check
        return is_user_logged_in();
    }
}