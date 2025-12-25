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

    /**
     * Check if user can earn points
     * rejimde_pro users cannot earn points, all other roles can
     * 
     * @param int|null $user_id User ID (null = current user)
     * @return bool True if user can earn points, false otherwise
     */
    protected function can_earn_points($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // rejimde_pro users cannot earn points
        if (in_array('rejimde_pro', (array) $user->roles)) {
            return false;
        }
        
        return true;
    }
}