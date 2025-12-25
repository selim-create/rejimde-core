<?php
namespace Rejimde\Admin;

class MascotSettings {

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
            [$this, 'render_settings_page'],
            'dashicons-leaf', 
            25
        );
    }

    public function register_settings() {
        // Mevcut Maskot Ayarı
        register_setting('rejimde_settings_group', 'rejimde_mascot_config', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_json']
        ]);

        // YENİ: Google Client ID Ayarı
        register_setting('rejimde_settings_group', 'rejimde_google_client_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }

    public function sanitize_json($input) {
        $json = json_decode($input);
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error('rejimde_mascot_config', 'invalid_json', 'Hatalı JSON formatı! Ayarlar kaydedilmedi.');
            return get_option('rejimde_mascot_config');
        }
        return $input;
    }

    public function render_settings_page() {
        $config = get_option('rejimde_mascot_config');
        $google_client_id = get_option('rejimde_google_client_id', ''); // YENİ
        
        if (empty($config)) {
            $config = json_encode([
                'meta' => ['version' => '1.0', 'character_name' => 'FitBuddy'],
                'states' => []
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            if (is_array($config) || is_object($config)) {
                $config = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        }
        ?>
        <div class="wrap">
            <h1>Rejimde Core Ayarları</h1>
            <form method="post" action="options.php">
                <?php settings_fields('rejimde_settings_group'); ?>
                <?php do_settings_sections('rejimde_settings_group'); ?>
                
                <table class="form-table">
                    <!-- Google Client ID Alanı -->
                    <tr valign="top">
                        <th scope="row">Google Client ID</th>
                        <td>
                            <input type="text" name="rejimde_google_client_id" value="<?php echo esc_attr($google_client_id); ?>" class="regular-text" style="width: 100%; max-width: 600px;" />
                            <p class="description">Google Cloud Console'dan aldığınız Client ID'yi buraya girin. <br>Örn: <code>629392742338-....apps.googleusercontent.com</code></p>
                        </td>
                    </tr>

                    <!-- Maskot Config Alanı -->
                    <tr valign="top">
                        <th scope="row">Maskot & Metin Konfigürasyonu (JSON)</th>
                        <td>
                            <textarea name="rejimde_mascot_config" rows="20" cols="50" class="large-text code" style="font-family: monospace; background: #f0f0f1;"><?php echo esc_textarea($config); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Ayarları Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}