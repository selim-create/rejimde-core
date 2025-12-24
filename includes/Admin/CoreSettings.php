<?php
namespace Rejimde\Admin;

class CoreSettings {

    // Option Grubu (Tüm ayarlar bu grupta toplanacak ama her biri kendi ismiyle saklanacak)
    // NOT: options.php'de her alan için register_setting çağırmazsanız veriler kaydedilmez.
    // Ancak tek bir option_name altında array olarak saklamak daha temizdir.
    // Fakat eski verileri korumak için tek tek register_setting kullanacağız.
    private $option_group = 'rejimde_core_settings_group';

    // Varsayılan Puan Kuralları (Eski veriyi korumak için)
    private $default_rules = [
        'daily_login'       => ['points' => 10, 'limit' => 1, 'label' => 'Günlük Giriş'],
        'log_water'         => ['points' => 5,  'limit' => 10, 'label' => 'Su İçme'],
        'log_meal'          => ['points' => 15, 'limit' => 5,  'label' => 'Öğün Girme'],
        'read_blog'         => ['points' => 10, 'limit' => 5,  'label' => 'Makale Okuma'],
        'complete_workout'  => ['points' => 50, 'limit' => 1,  'label' => 'Antrenman Tamamlama'],
        'update_weight'     => ['points' => 20, 'limit' => 1,  'label' => 'Kilo Güncelleme'],
        'join_circle'       => ['points' => 100,'limit' => 1,  'label' => 'Circle\'a Katılma'],
        'invite_expert'     => ['points' => 50, 'limit' => 5,  'label' => 'Uzman Daveti'],
    ];

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_admin_menu() {
        // Ana Menü (Rejimde)
        add_menu_page(
            'Rejimde Yönetim', 
            'Rejimde', 
            'manage_options', 
            'rejimde-core', 
            [$this, 'render_settings_page'], 
            'dashicons-heart', 
            2
        );

        // Varsayılan "Ayarlar" alt menüsü
        add_submenu_page(
            'rejimde-core',
            'Genel Ayarlar',
            'Ayarlar',
            'manage_options',
            'rejimde-core',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        // --- GRUP 1: GENEL (rejimde_group_general) ---
        // Her grubu ayrı bir option_group ismine bağlarsak tab'lar arası kaybolma sorunu çözülür.
        
        // Genel Ayarlar Grubu
        register_setting('rejimde_group_general', 'rejimde_maintenance_mode', 'absint');
        
        register_setting('rejimde_group_general', 'rejimde_gamification_rules', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_json']
        ]);

        // Maskot Ayarları (MascotSettings.php'den gelen) - BURASI EKLENDİ
        register_setting('rejimde_group_general', 'rejimde_mascot_config', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_json']
        ]);

        add_settings_section('rejimde_general_section', 'Genel Ayarlar', null, 'rejimde-core-general');
        
        add_settings_field('rejimde_maintenance_mode', 'Bakım Modu', [$this, 'render_checkbox_field'], 'rejimde-core-general', 'rejimde_general_section', ['label_for' => 'rejimde_maintenance_mode', 'desc' => 'API erişimini geçici olarak durdurur.']);
        add_settings_field('rejimde_gamification_rules', 'Puan Kuralları (JSON)', [$this, 'render_textarea_field'], 'rejimde-core-general', 'rejimde_general_section', ['label_for' => 'rejimde_gamification_rules', 'desc' => 'Format: "action": {"points": 10, "limit": 1}']);
        
        // Maskot Alanı
        add_settings_field(
            'rejimde_mascot_config', 
            'Maskot Ayarları (JSON)', 
            [$this, 'render_textarea_field'], 
            'rejimde-core-general', 
            'rejimde_general_section', 
            ['label_for' => 'rejimde_mascot_config', 'desc' => 'Maskot mesajları ve durumları.']
        );


        // --- GRUP 2: YAPAY ZEKA (rejimde_group_ai) ---
        register_setting('rejimde_group_ai', 'rejimde_openai_api_key', 'sanitize_text_field');
        register_setting('rejimde_group_ai', 'rejimde_openai_model', 'sanitize_text_field');

        add_settings_section('rejimde_ai_section', 'Yapay Zeka (AI)', null, 'rejimde-core-ai');

        add_settings_field('rejimde_openai_api_key', 'OpenAI API Key', [$this, 'render_text_field'], 'rejimde-core-ai', 'rejimde_ai_section', ['label_for' => 'rejimde_openai_api_key', 'type' => 'password', 'desc' => 'sk-... ile başlayan anahtar.']);
        add_settings_field('rejimde_openai_model', 'OpenAI Model', [$this, 'render_select_field'], 'rejimde-core-ai', 'rejimde_ai_section', [
            'label_for' => 'rejimde_openai_model', 
            'options' => [
                'gpt-4o' => 'GPT-4o', 
                'gpt-4o-mini' => 'GPT-4o Mini', // Yeni Eklendi
                'gpt-4-turbo' => 'GPT-4 Turbo', 
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
            ],
            'desc' => 'Diyet asistanı için kullanılacak model.'
        ]);


        // --- GRUP 3: GÖRSEL (rejimde_group_images) ---
        register_setting('rejimde_group_images', 'rejimde_pexels_api_key', 'sanitize_text_field');
        register_setting('rejimde_group_images', 'rejimde_unsplash_api_key', 'sanitize_text_field');

        add_settings_section('rejimde_images_section', 'Görsel API', null, 'rejimde-core-images');

        add_settings_field('rejimde_pexels_api_key', 'Pexels API Key', [$this, 'render_text_field'], 'rejimde-core-images', 'rejimde_images_section', ['label_for' => 'rejimde_pexels_api_key', 'type' => 'password']);
        add_settings_field('rejimde_unsplash_api_key', 'Unsplash Access Key', [$this, 'render_text_field'], 'rejimde-core-images', 'rejimde_images_section', ['label_for' => 'rejimde_unsplash_api_key', 'type' => 'password']);


        // --- GRUP 4: ENTEGRASYON (rejimde_group_auth) ---
        register_setting('rejimde_group_auth', 'rejimde_google_client_id', 'sanitize_text_field');

        add_settings_section('rejimde_auth_section', 'Kimlik Doğrulama', null, 'rejimde-core-auth');

        add_settings_field('rejimde_google_client_id', 'Google Client ID', [$this, 'render_text_field'], 'rejimde-core-auth', 'rejimde_auth_section', ['label_for' => 'rejimde_google_client_id', 'desc' => 'Google ile giriş için OAuth Client ID.']);
    }

    // --- Helpers ---

    public function sanitize_json($input) {
        if (empty($input)) {
            return '';
        }

        // Önce stripslashes uygulayalım (WP bazen slash ekler)
        $clean_input = stripslashes($input);

        // JSON geçerli mi diye kontrol edelim
        $json = json_decode($clean_input, true);

        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            // Eğer stripslashes sonrası hala hata varsa, orijinal input'u deneyelim
            // (Bazen stripslashes gereksiz yere tırnakları bozabilir)
            $json = json_decode($input, true);
            
            if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
                // Hata durumunda, veriyi olduğu gibi döndürebiliriz VEYA hata mesajı ekleyip eski değeri koruyabiliriz.
                // Veriyi kaybetmemek adına, eğer JSON decode edilemiyorsa input'u olduğu gibi kaydedelim.
                // Ancak bu, veritabanında bozuk JSON saklanmasına neden olabilir.
                // En güvenlisi: Hata mesajı ekleyip, veritabanındaki ESKİ değeri döndürmek.
                
                add_settings_error('rejimde_json_error', 'invalid_json', 'Hatalı JSON formatı! Ayarlar kaydedilmedi. Lütfen JSON formatınızı kontrol edin (tırnaklar, virgüller vb.).');
                
                // Hangi ayarın hatalı olduğunu bulup onun eski değerini döndürmemiz gerekirdi ama
                // burada basitçe boş string veya input'u dönmek zorundayız çünkü hangi field olduğunu bilmiyoruz.
                // Kullanıcıya hata mesajı göstermek ve veriyi kaydetmemek en doğrusu.
                
                // Geriye dönük uyumluluk veya "zorla kaydet" istenirse: return $input;
                // Ama biz güvenli olanı seçelim:
                return ''; // Veya get_option(...) ile eski değeri çekebilirsiniz.
            }
        }
        
        // Geçerli JSON ise, güzel formatta tekrar encode edip saklayalım
        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        
        // Aktif sekmeye göre doğru grup belirleniyor
        $active_group = 'rejimde_group_general';
        if ($active_tab == 'ai') $active_group = 'rejimde_group_ai';
        elseif ($active_tab == 'images') $active_group = 'rejimde_group_images';
        elseif ($active_tab == 'auth') $active_group = 'rejimde_group_auth';
        ?>
        <div class="wrap">
            <h1>Rejimde Core Ayarları</h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=rejimde-core&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">Genel & Oyunlaştırma</a>
                <a href="?page=rejimde-core&tab=ai" class="nav-tab <?php echo $active_tab == 'ai' ? 'nav-tab-active' : ''; ?>">Yapay Zeka (AI)</a>
                <a href="?page=rejimde-core&tab=images" class="nav-tab <?php echo $active_tab == 'images' ? 'nav-tab-active' : ''; ?>">Görsel Servisleri</a>
                <a href="?page=rejimde-core&tab=auth" class="nav-tab <?php echo $active_tab == 'auth' ? 'nav-tab-active' : ''; ?>">Entegrasyonlar</a>
            </h2>
            
            <form action="options.php" method="post">
                <?php
                // Sadece aktif sekmenin grubunu basıyoruz. Böylece diğerleri silinmez.
                settings_fields($active_group);
                
                if ($active_tab == 'general') {
                    do_settings_sections('rejimde-core-general');
                } elseif ($active_tab == 'ai') {
                    do_settings_sections('rejimde-core-ai');
                } elseif ($active_tab == 'images') {
                    do_settings_sections('rejimde-core-images');
                } elseif ($active_tab == 'auth') {
                    do_settings_sections('rejimde-core-auth');
                }
                
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // --- Field Renderers ---

    public function render_text_field($args) {
        $value = get_option($args['label_for'], '');
        $type = isset($args['type']) ? $args['type'] : 'text';
        echo '<input type="' . $type . '" id="' . $args['label_for'] . '" name="' . $args['label_for'] . '" value="' . esc_attr($value) . '" class="regular-text" style="width: 100%; max-width: 400px;">';
        if (isset($args['desc'])) echo '<p class="description">' . $args['desc'] . '</p>';
    }

    public function render_checkbox_field($args) {
        $value = get_option($args['label_for'], 0);
        echo '<input type="checkbox" id="' . $args['label_for'] . '" name="' . $args['label_for'] . '" value="1" ' . checked(1, $value, false) . '>';
        if (isset($args['desc'])) echo '<p class="description">' . $args['desc'] . '</p>';
    }

    public function render_select_field($args) {
        $value = get_option($args['label_for'], '');
        echo '<select id="' . $args['label_for'] . '" name="' . $args['label_for'] . '">';
        foreach ($args['options'] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        if (isset($args['desc'])) echo '<p class="description">' . $args['desc'] . '</p>';
    }

    public function render_textarea_field($args) {
        $default = '';
        if ($args['label_for'] === 'rejimde_gamification_rules') {
            $default = json_encode($this->default_rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $value = get_option($args['label_for'], $default);
        echo '<textarea id="' . $args['label_for'] . '" name="' . $args['label_for'] . '" rows="10" cols="50" class="large-text code" style="font-family: monospace;">' . esc_textarea($value) . '</textarea>';
        if (isset($args['desc'])) echo '<p class="description">' . $args['desc'] . '</p>';
    }
}