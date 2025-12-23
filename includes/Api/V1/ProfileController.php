<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

class ProfileController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'profile';

    public function register_routes() {
        // Takip Et / Bırak
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/follow', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_follow'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Beşlik Çak (High Five)
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/high-five', [
            'methods' => 'POST',
            'callback' => [$this, 'send_high_five'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    public function check_auth() {
        return is_user_logged_in();
    }

    /**
     * Takip Et / Bırak
     */
    public function toggle_follow($request) {
        $target_id = (int) $request->get_param('id');
        $current_user_id = get_current_user_id();

        if ($target_id === $current_user_id) {
            return new WP_Error('invalid_action', 'Kendinizi takip edemezsiniz.', ['status' => 400]);
        }

        // Target'ın Takipçileri
        $followers = get_user_meta($target_id, 'rejimde_followers', true);
        if (!is_array($followers)) $followers = [];

        // Current'ın Takip Ettikleri
        $following = get_user_meta($current_user_id, 'rejimde_following', true);
        if (!is_array($following)) $following = [];

        $is_following = false;

        if (in_array($current_user_id, $followers)) {
            // Unfollow
            $followers = array_diff($followers, [$current_user_id]);
            $following = array_diff($following, [$target_id]);
            $message = 'Takipten çıkıldı.';
        } else {
            // Follow
            $followers[] = $current_user_id;
            $following[] = $target_id;
            $is_following = true;
            $message = 'Takip edildi!';
            
            // Bildirim oluşturulabilir...
        }

        update_user_meta($target_id, 'rejimde_followers', array_values($followers));
        update_user_meta($current_user_id, 'rejimde_following', array_values($following));

        return new WP_REST_Response([
            'success' => true,
            'is_following' => $is_following,
            'followers_count' => count($followers),
            'message' => $message
        ], 200);
    }

    /**
     * Beşlik Gönder
     */
    public function send_high_five($request) {
        $target_id = (int) $request->get_param('id');
        $current_user_id = get_current_user_id();

        if ($target_id === $current_user_id) {
            return new WP_Error('invalid_action', 'Kendine beşlik çakamazsın :)', ['status' => 400]);
        }
        
        // Spam kontrolü (Basit: Son 1 saatte gönderdi mi?)
        $last_high_five = get_user_meta($current_user_id, 'last_high_five_' . $target_id, true);
        if ($last_high_five && (time() - $last_high_five) < 3600) {
             return new WP_Error('rate_limit', 'Biraz beklemen lazım.', ['status' => 429]);
        }

        // Sayacı artır
        $count = (int) get_user_meta($target_id, 'rejimde_high_fives', true);
        update_user_meta($target_id, 'rejimde_high_fives', $count + 1);
        
        // Zaman damgası kaydet
        update_user_meta($current_user_id, 'last_high_five_' . $target_id, time());

        return new WP_REST_Response([
            'success' => true,
            'count' => $count + 1,
            'message' => 'Beşlik gönderildi! ✋'
        ], 200);
    }
}