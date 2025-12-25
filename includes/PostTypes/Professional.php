<?php
namespace Rejimde\PostTypes;

class Professional {

    public function register() {
        $labels = [
            'name'                  => 'Uzmanlar',
            'singular_name'         => 'Uzman',
            'menu_name'             => 'Uzmanlar',
            'add_new'               => 'Yeni Uzman Ekle',
            'add_new_item'          => 'Yeni Uzman Ekle',
            'edit_item'             => 'Uzmanı Düzenle',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'uzman'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 21,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => ['title', 'editor', 'thumbnail'],
            'show_in_rest'       => true 
        ];

        register_post_type('rejimde_pro', $args);

        // Metabox (Veri Giriş Kutuları) Ekleme
        add_action('add_meta_boxes', [$this, 'add_custom_meta_boxes']);
        add_action('save_post', [$this, 'save_custom_meta_boxes']);
    }

    public function add_custom_meta_boxes() {
        add_meta_box(
            'rejimde_pro_details',
            'Uzman Detayları (Rehber Verisi)',
            [$this, 'render_meta_box'],
            'rejimde_pro',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        // Mevcut değerleri al
        $meta = get_post_meta($post->ID);
        $profession = $meta['uzmanlik_tipi'][0] ?? '';
        $title = $meta['unvan'][0] ?? '';
        $city = $meta['konum'][0] ?? '';
        $district = $meta['ilce'][0] ?? '';
        $email = $meta['email'][0] ?? '';
        $is_claimed = $meta['is_claimed'][0] ?? '0';

        wp_nonce_field('rejimde_pro_save', 'rejimde_pro_nonce');
        ?>
        <style>
            .rejimde-row { margin-bottom: 15px; display: flex; align-items: center; }
            .rejimde-row label { width: 150px; font-weight: bold; }
            .rejimde-row input, .rejimde-row select { width: 100%; max-width: 400px; }
        </style>

        <div class="rejimde-row">
            <label>Meslek Grubu:</label>
            <select name="uzmanlik_tipi">
                <option value="">Seçiniz</option>
                
                <optgroup label="Beslenme">
                    <option value="dietitian" <?php selected($profession, 'dietitian'); ?>>Diyetisyen</option>
                </optgroup>
                
                <optgroup label="Hareket">
                    <option value="pt" <?php selected($profession, 'pt'); ?>>PT / Fitness Koçu</option>
                    <option value="yoga" <?php selected($profession, 'yoga'); ?>>Yoga / Pilates</option>
                    <option value="functional" <?php selected($profession, 'functional'); ?>>Fonksiyonel Antrenman</option>
                    <option value="swim" <?php selected($profession, 'swim'); ?>>Yüzme Eğitmeni</option>
                    <option value="run" <?php selected($profession, 'run'); ?>>Koşu Eğitmeni</option>
                </optgroup>
                
                <optgroup label="Zihin & Alışkanlık">
                    <option value="life_coach" <?php selected($profession, 'life_coach'); ?>>Yaşam Koçu</option>
                    <option value="breath" <?php selected($profession, 'breath'); ?>>Nefes & Meditasyon</option>
                </optgroup>
                
                <optgroup label="Sağlık Destek">
                    <option value="physio" <?php selected($profession, 'physio'); ?>>Fizyoterapist</option>
                </optgroup>
                
                <optgroup label="Kardiyo & Güç">
                    <option value="box" <?php selected($profession, 'box'); ?>>Boks / Kickboks</option>
                    <option value="defense" <?php selected($profession, 'defense'); ?>>Savunma & Kondisyon</option>
                </optgroup>
            </select>
        </div>

        <div class="rejimde-row">
            <label>Unvan:</label>
            <input type="text" name="unvan" value="<?php echo esc_attr($title); ?>" placeholder="Örn: Uzman Diyetisyen">
        </div>

        <div class="rejimde-row">
            <label>Şehir:</label>
            <input type="text" name="konum" value="<?php echo esc_attr($city); ?>" placeholder="Örn: İstanbul">
        </div>
        
        <div class="rejimde-row">
            <label>İlçe:</label>
            <input type="text" name="ilce" value="<?php echo esc_attr($district); ?>" placeholder="Örn: Kadıköy">
        </div>

        <div class="rejimde-row">
            <label>E-posta (Gizli):</label>
            <input type="email" name="email" value="<?php echo esc_attr($email); ?>" placeholder="Eşleştirme için gerekli">
            <p class="description" style="margin-left:10px;">*Bu e-posta ile kayıt olurlarsa profil otomatik eşleşir.</p>
        </div>

        <div class="rejimde-row">
            <label>Sahiplenildi mi?</label>
            <select name="is_claimed">
                <option value="0" <?php selected($is_claimed, '0'); ?>>Hayır (Onaysız Profil)</option>
                <option value="1" <?php selected($is_claimed, '1'); ?>>Evet (Gerçek Kullanıcı)</option>
            </select>
        </div>
        <?php
    }

    public function save_custom_meta_boxes($post_id) {
        if (!isset($_POST['rejimde_pro_nonce']) || !wp_verify_nonce($_POST['rejimde_pro_nonce'], 'rejimde_pro_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = ['uzmanlik_tipi', 'unvan', 'konum', 'ilce', 'email', 'is_claimed'];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
}