<?php
namespace Rejimde\Admin;

class CoreSettings {

    // Varsayılan Puan Kuralları
    private $default_rules = [
        'daily_login'       => ['points' => 10, 'limit' => 1, 'label' => 'Günlük Giriş'],
        'log_water'         => ['points' => 5,  'limit' => 10, 'label' => 'Su İçme'],
        'log_meal'          => ['points' => 15, 'limit' => 5,  'label' => 'Öğün Girme'],
        'read_blog'         => ['points' => 10, 'limit' => 5,  'label' => 'Makale Okuma'],
        'complete_workout'  => ['points' => 50, 'limit' => 1,  'label' => 'Antrenman Tamamlama'],
        'update_weight'     => ['points' => 20, 'limit' => 1,  'label' => 'Kilo Güncelleme'],
        'join_clan'         => ['points' => 100,'limit' => 1,  'label' => 'Klana Katılma'],
        'invite_expert'     => ['points' => 50, 'limit' => 5,  'label' => 'Uzman Daveti'],
    ];

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Rejimde Ayarlar',
            'Rejimde',
            'manage_options',
            'rejimde_settings',
            [$this, 'render_page'],
            'dashicons-leaf', 
            25
        );
    }

    public function register_settings() {
        // 1. Google Client ID
        register_setting('rejimde_settings_group', 'rejimde_google_client_id');

        // 2. Gamification Kuralları (JSON olarak saklayacağız)
        register_setting('rejimde_settings_group', 'rejimde_gamification_rules', [
            'type' => 'string',
            'sanitize_callback' => function($input) {
                // JSON doğrulaması yapılabilir
                return $input;
            }
        ]);

        // 3. Maskot Ayarları
        register_setting('rejimde_settings_group', 'rejimde_mascot_config');
    }

    public function render_page() {
        // Mevcut ayarları çek
        $google_id = get_option('rejimde_google_client_id', '');
        
        $rules_json = get_option('rejimde_gamification_rules');
        if (empty($rules_json)) {
            $rules_json = json_encode($this->default_rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $mascot_json = get_option('rejimde_mascot_config');
        if (empty($mascot_json)) {
             // Varsayılan boş yapı
             $mascot_json = json_encode(['meta' => ['version'=>'1.0'], 'states' => []], JSON_PRETTY_PRINT);
        }

        ?>
        <div class="wrap">
            <h1>Rejimde Çekirdek Ayarları</h1>
            <form method="post" action="options.php">
                <?php settings_fields('rejimde_settings_group'); ?>
                <?php do_settings_sections('rejimde_settings_group'); ?>
                
                <h2 class="title">🔑 Kimlik Doğrulama</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Google Client ID</th>
                        <td>
                            <input type="text" name="rejimde_google_client_id" value="<?php echo esc_attr($google_id); ?>" class="regular-text" style="width: 100%;" />
                            <p class="description">Google Cloud Console'dan alınan ID.</p>
                        </td>
                    </tr>
                </table>

                <hr>

                <h2 class="title">🎮 Oyunlaştırma & Puanlar</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Puan Kuralları (JSON)</th>
                        <td>
                            <textarea name="rejimde_gamification_rules" rows="15" cols="50" class="large-text code" style="font-family: monospace; background: #f0f0f1;"><?php echo esc_textarea($rules_json); ?></textarea>
                            <p class="description">Format: <code>"action_key": {"points": 10, "limit": 1, "label": "Açıklama"}</code></p>
                        </td>
                    </tr>
                </table>

                <hr>

                <h2 class="title">🤖 Maskot & Metinler</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Maskot Konfigürasyonu (JSON)</th>
                        <td>
                            <textarea name="rejimde_mascot_config" rows="15" cols="50" class="large-text code" style="font-family: monospace; background: #f0f0f1;"><?php echo esc_textarea($mascot_json); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Ayarları Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}