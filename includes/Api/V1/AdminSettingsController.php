<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

/**
 * Admin Settings Controller
 * Admin ayarlarını API üzerinden döndürür (bot sistemi için)
 */
class AdminSettingsController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'admin/settings';

    public function register_routes() {
        // GET /admin/settings/ai - AI ayarlarını getir
        register_rest_route($this->namespace, '/' . $this->base . '/ai', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ai_settings'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        // GET /admin/settings/bot-config - Bot konfigürasyonu
        register_rest_route($this->namespace, '/' . $this->base . '/bot-config', [
            'methods' => 'GET',
            'callback' => [$this, 'get_bot_config'],
            'permission_callback' => [$this, 'check_admin'],
        ]);
    }

    public function check_admin() {
        return current_user_can('manage_options');
    }

    /**
     * AI ayarlarını getir (OpenAI key vb.)
     */
    public function get_ai_settings() {
        $api_key = get_option('rejimde_openai_api_key', '');
        $model = get_option('rejimde_openai_model', 'gpt-4o-mini');

        if (empty($api_key)) {
            return new WP_Error('not_configured', 'OpenAI API key ayarlanmamış.', ['status' => 400]);
        }

        return new WP_REST_Response([
            'status' => 'success',
            'data' => [
                'openai_api_key' => $api_key,
                'openai_model' => $model,
            ]
        ]);
    }

    /**
     * Bot sistem konfigürasyonu
     */
    public function get_bot_config() {
        return new WP_REST_Response([
            'status' => 'success',
            'data' => [
                'api_base_url' => rest_url('rejimde/v1'),
                'persona_types' => [
                    'super_active' => ['label' => 'Süper Aktif', 'ai_enabled' => true],
                    'active' => ['label' => 'Aktif', 'ai_enabled' => false],
                    'normal' => ['label' => 'Normal', 'ai_enabled' => false],
                    'low_activity' => ['label' => 'Düşük Aktivite', 'ai_enabled' => false],
                    'dormant' => ['label' => 'Uykuda', 'ai_enabled' => false],
                    'diet_focused' => ['label' => 'Diyet Odaklı', 'ai_enabled' => false],
                    'exercise_focused' => ['label' => 'Egzersiz Odaklı', 'ai_enabled' => false],
                ],
                'features' => [
                    'water_tracking' => get_option('rejimde_feature_water_tracking', true),
                    'steps_tracking' => get_option('rejimde_feature_steps_tracking', true),
                    'meal_photos' => get_option('rejimde_feature_meal_photos', true),
                ]
            ]
        ]);
    }
}
