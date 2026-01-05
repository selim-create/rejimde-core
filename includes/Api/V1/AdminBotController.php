<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

/**
 * Admin Bot Controller
 * Bot simülasyon sisteminin yönetimi için API endpoint'leri
 */
class AdminBotController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'admin/bots';

    public function register_routes() {
        // GET /admin/bots/stats - Bot istatistikleri
        register_rest_route($this->namespace, '/' . $this->base . '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bot_stats'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        // POST /admin/bots/toggle-all - Tüm botları aktif/pasif yap
        register_rest_route($this->namespace, '/' . $this->base . '/toggle-all', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_all_bots'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        // POST /admin/bots/toggle-batch/{batch_id} - Belirli batch'i aktif/pasif yap
        register_rest_route($this->namespace, '/' . $this->base . '/toggle-batch/(?P<batch_id>[a-zA-Z0-9_]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_batch'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        // GET /admin/bots/exclude-ids - Analytics için exclude edilecek ID'ler
        register_rest_route($this->namespace, '/' . $this->base . '/exclude-ids', [
            'methods' => 'GET',
            'callback' => [$this, 'get_exclude_ids'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        // GET /admin/bots/list - Bot listesi
        register_rest_route($this->namespace, '/' . $this->base . '/list', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bot_list'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        // DELETE /admin/bots/batch/{batch_id} - Batch'i sil
        register_rest_route($this->namespace, '/' . $this->base . '/batch/(?P<batch_id>[a-zA-Z0-9_]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_batch'],
            'permission_callback' => [$this, 'check_admin'],
        ]);
    }

    public function check_admin() {
        return current_user_can('manage_options');
    }

    /**
     * Bot istatistiklerini getir
     */
    public function get_bot_stats() {
        global $wpdb;

        // Toplam bot sayısı
        $total_bots = $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
            WHERE meta_key = 'is_simulation' AND meta_value = '1'
        ");

        // Aktif bot sayısı
        $active_bots = $wpdb->get_var("
            SELECT COUNT(DISTINCT um1.user_id) FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            WHERE um1.meta_key = 'is_simulation' AND um1.meta_value = '1'
            AND um2.meta_key = 'simulation_active' AND um2.meta_value = '1'
        ");

        // Persona dağılımı
        $persona_distribution = $wpdb->get_results("
            SELECT um2.meta_value as persona, COUNT(DISTINCT um1.user_id) as count
            FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            WHERE um1.meta_key = 'is_simulation' AND um1.meta_value = '1'
            AND um2.meta_key = 'simulation_persona'
            GROUP BY um2.meta_value
            ORDER BY count DESC
        ");

        // Batch listesi
        $batches = $wpdb->get_results("
            SELECT um2.meta_value as batch_id, COUNT(DISTINCT um1.user_id) as count,
                   MIN(u.user_registered) as created_at
            FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            INNER JOIN {$wpdb->users} u ON um1.user_id = u.ID
            WHERE um1.meta_key = 'is_simulation' AND um1.meta_value = '1'
            AND um2.meta_key = 'simulation_batch'
            GROUP BY um2.meta_value
            ORDER BY created_at DESC
        ");

        return new WP_REST_Response([
            'status' => 'success',
            'data' => [
                'total_bots' => (int) $total_bots,
                'active_bots' => (int) $active_bots,
                'inactive_bots' => (int) $total_bots - (int) $active_bots,
                'persona_distribution' => $persona_distribution,
                'batches' => $batches,
            ]
        ]);
    }

    /**
     * Tüm botları aktif/pasif yap
     */
    public function toggle_all_bots($request) {
        $params = $request->get_json_params();
        $active = isset($params['active']) ? (bool) $params['active'] : false;

        global $wpdb;

        // Tüm simulation kullanıcılarını bul
        $bot_ids = $wpdb->get_col("
            SELECT DISTINCT user_id FROM {$wpdb->usermeta}
            WHERE meta_key = 'is_simulation' AND meta_value = '1'
        ");

        if (empty($bot_ids)) {
            return new WP_REST_Response([
                'status' => 'success',
                'message' => 'Hiç bot bulunamadı.',
                'affected_count' => 0
            ]);
        }

        foreach ($bot_ids as $user_id) {
            update_user_meta($user_id, 'simulation_active', $active ? '1' : '0');
        }

        return new WP_REST_Response([
            'status' => 'success',
            'message' => $active ? 'Tüm botlar aktif edildi.' : 'Tüm botlar pasife alındı.',
            'affected_count' => count($bot_ids)
        ]);
    }

    /**
     * Belirli batch'i aktif/pasif yap
     */
    public function toggle_batch($request) {
        $batch_id = sanitize_text_field($request->get_param('batch_id'));
        $params = $request->get_json_params();
        $active = isset($params['active']) ? (bool) $params['active'] : false;

        global $wpdb;

        $bot_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT um1.user_id FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            WHERE um1.meta_key = 'is_simulation' AND um1.meta_value = '1'
            AND um2.meta_key = 'simulation_batch' AND um2.meta_value = %s
        ", $batch_id));

        if (empty($bot_ids)) {
            return new WP_Error('not_found', 'Bu batch_id ile bot bulunamadı.', ['status' => 404]);
        }

        foreach ($bot_ids as $user_id) {
            update_user_meta($user_id, 'simulation_active', $active ? '1' : '0');
        }

        return new WP_REST_Response([
            'status' => 'success',
            'message' => "Batch '$batch_id' " . ($active ? 'aktif edildi.' : 'pasife alındı.'),
            'affected_count' => count($bot_ids)
        ]);
    }

    /**
     * Analytics için exclude edilecek bot ID'lerini getir
     */
    public function get_exclude_ids() {
        global $wpdb;

        $ids = $wpdb->get_col("
            SELECT DISTINCT user_id FROM {$wpdb->usermeta}
            WHERE meta_key = 'is_simulation' AND meta_value = '1'
        ");

        return new WP_REST_Response([
            'status' => 'success',
            'data' => array_map('intval', $ids),
            'count' => count($ids)
        ]);
    }

    /**
     * Bot listesini getir
     */
    public function get_bot_list($request) {
        global $wpdb;

        $limit = (int) $request->get_param('limit') ?: 50;
        $offset = (int) $request->get_param('offset') ?: 0;
        $batch_id = sanitize_text_field($request->get_param('batch_id') ?: '');
        $persona = sanitize_text_field($request->get_param('persona') ?: '');
        $active_only = $request->get_param('active_only') === 'true';

        // Base query
        $where_conditions = ["um1.meta_key = 'is_simulation' AND um1.meta_value = '1'"];
        $join_conditions = [];

        if (!empty($batch_id)) {
            $join_conditions[] = $wpdb->prepare(
                "INNER JOIN {$wpdb->usermeta} um_batch ON u.ID = um_batch.user_id AND um_batch.meta_key = 'simulation_batch' AND um_batch.meta_value = %s",
                $batch_id
            );
        }

        if (!empty($persona)) {
            $join_conditions[] = $wpdb->prepare(
                "INNER JOIN {$wpdb->usermeta} um_persona ON u.ID = um_persona.user_id AND um_persona.meta_key = 'simulation_persona' AND um_persona.meta_value = %s",
                $persona
            );
        }

        if ($active_only) {
            $join_conditions[] = "INNER JOIN {$wpdb->usermeta} um_active ON u.ID = um_active.user_id AND um_active.meta_key = 'simulation_active' AND um_active.meta_value = '1'";
        }

        $join_sql = implode(' ', $join_conditions);
        $where_sql = implode(' AND ', $where_conditions);

        $bots = $wpdb->get_results($wpdb->prepare("
            SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id
            {$join_sql}
            WHERE {$where_sql}
            ORDER BY u.user_registered DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset));

        // Her bot için meta bilgilerini ekle
        $bot_list = [];
        foreach ($bots as $bot) {
            $bot_list[] = [
                'id' => (int) $bot->ID,
                'username' => $bot->user_login,
                'email' => $bot->user_email,
                'display_name' => $bot->display_name,
                'registered' => $bot->user_registered,
                'persona' => get_user_meta($bot->ID, 'simulation_persona', true),
                'batch_id' => get_user_meta($bot->ID, 'simulation_batch', true),
                'is_active' => get_user_meta($bot->ID, 'simulation_active', true) === '1',
                'total_score' => (int) get_user_meta($bot->ID, 'rejimde_total_score', true),
            ];
        }

        return new WP_REST_Response([
            'status' => 'success',
            'data' => $bot_list,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'returned' => count($bot_list)
            ]
        ]);
    }

    /**
     * Batch'i sil (tüm botları kalıcı olarak sil)
     */
    public function delete_batch($request) {
        $batch_id = sanitize_text_field($request->get_param('batch_id'));
        $params = $request->get_json_params();
        $confirm = isset($params['confirm']) && $params['confirm'] === true;

        if (!$confirm) {
            return new WP_Error('confirmation_required', 'Bu işlem geri alınamaz. confirm: true gönderin.', ['status' => 400]);
        }

        global $wpdb;

        $bot_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT um1.user_id FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            WHERE um1.meta_key = 'is_simulation' AND um1.meta_value = '1'
            AND um2.meta_key = 'simulation_batch' AND um2.meta_value = %s
        ", $batch_id));

        if (empty($bot_ids)) {
            return new WP_Error('not_found', 'Bu batch_id ile bot bulunamadı.', ['status' => 404]);
        }

        require_once(ABSPATH . 'wp-admin/includes/user.php');

        $deleted_count = 0;
        foreach ($bot_ids as $user_id) {
            if (wp_delete_user($user_id)) {
                $deleted_count++;
            }
        }

        return new WP_REST_Response([
            'status' => 'success',
            'message' => "Batch '$batch_id' silindi.",
            'deleted_count' => $deleted_count
        ]);
    }
}
