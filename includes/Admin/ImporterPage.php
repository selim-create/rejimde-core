<?php
namespace Rejimde\Admin;

class ImporterPage {

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'rejimde-core',
            'İçe Aktar (Import)',
            'İçe Aktar',
            'manage_options',
            'rejimde-import',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'professionals';
        $message = '';

        if (isset($_POST['rejimde_import_json']) && check_admin_referer('rejimde_import_action')) {
            $json = stripslashes($_POST['rejimde_import_json']);
            $data = json_decode($json, true);
            $type = $_POST['import_type'];

            if (is_array($data)) {
                $count = 0;
                
                // UZMAN İÇE AKTARMA
                if ($type === 'professionals') {
                    foreach ($data as $item) {
                        $clean_slug = sanitize_title($item['name']);
                        
                        // Mükerrer kontrolü (Slug ile)
                        $existing = get_page_by_path($clean_slug, OBJECT, 'rejimde_pro');
                        if ($existing) continue;

                        $post_id = wp_insert_post([
                            'post_title'    => $item['name'],
                            'post_name'     => $clean_slug,
                            'post_status'   => 'publish',
                            'post_type'     => 'rejimde_pro'
                        ]);

                        if ($post_id && !is_wp_error($post_id)) {
                            update_post_meta($post_id, 'uzmanlik_tipi', $item['type'] ?? 'dietitian');
                            update_post_meta($post_id, 'unvan', $item['title'] ?? 'Uzman');
                            update_post_meta($post_id, 'konum', $item['city'] ?? '');
                            update_post_meta($post_id, 'ilce', $item['district'] ?? '');
                            if (!empty($item['email'])) update_post_meta($post_id, 'email', sanitize_email($item['email']));
                            // Uzman görseli varsa onu da kaydedelim (Opsiyonel)
                            if (!empty($item['image_url'])) update_post_meta($post_id, 'image_url', esc_url_raw($item['image_url']));
                            
                            update_post_meta($post_id, 'onayli', '0');
                            update_post_meta($post_id, 'is_claimed', '0');
                            $count++;
                        }
                    }
                } 
                // SÖZLÜK İÇE AKTARMA (GÜNCELLENDİ)
                elseif ($type === 'dictionary') {
                    foreach ($data as $item) {
                        $clean_slug = sanitize_title($item['title']);
                        
                        // Mükerrer kontrolü
                        $existing = get_page_by_path($clean_slug, OBJECT, 'rejimde_dictionary');
                        if ($existing) continue;

                        $post_id = wp_insert_post([
                            'post_title'   => $item['title'],
                            'post_name'    => $clean_slug,
                            'post_content' => $item['content'] ?? '',
                            'post_excerpt' => $item['excerpt'] ?? '',
                            'post_status'  => 'publish',
                            'post_type'    => 'rejimde_dictionary'
                        ]);

                        if ($post_id && !is_wp_error($post_id)) {
                            // Meta Veriler
                            if (!empty($item['video_url'])) update_post_meta($post_id, 'video_url', sanitize_text_field($item['video_url']));
                            
                            // YENİ: Görsel URL Desteği
                            if (!empty($item['image_url'])) {
                                update_post_meta($post_id, 'image_url', esc_url_raw($item['image_url']));
                                // Not: Bu URL dış kaynaklıdır (Hotlink). Medya kütüphanesine indirilmez.
                            }

                            if (!empty($item['main_benefit'])) update_post_meta($post_id, 'main_benefit', sanitize_text_field($item['main_benefit']));
                            if (!empty($item['difficulty'])) update_post_meta($post_id, 'difficulty', intval($item['difficulty']));
                            if (!empty($item['alt_names'])) update_post_meta($post_id, 'alt_names', sanitize_text_field($item['alt_names']));
                            
                            // Taksonomiler (Kategoriler)
                            if (!empty($item['category'])) wp_set_object_terms($post_id, $item['category'], 'dictionary_category');
                            if (!empty($item['muscles']) && is_array($item['muscles'])) wp_set_object_terms($post_id, $item['muscles'], 'muscle_group');
                            if (!empty($item['equipment']) && is_array($item['equipment'])) wp_set_object_terms($post_id, $item['equipment'], 'equipment');
                            
                            $count++;
                        }
                    }
                }

                $message = "<div class='notice notice-success'><p>Başarıyla $count kayıt eklendi ($type)!</p></div>";
            } else {
                $message = "<div class='notice notice-error'><p>JSON formatı hatalı!</p></div>";
            }
        }
        
        // Örnek Veriler
        $example_professionals = '[
    {
        "name": "Dyt. Ayşe Yılmaz",
        "type": "dietitian",
        "title": "Uzman Diyetisyen",
        "city": "İstanbul",
        "district": "Kadıköy",
        "email": "ayse@rejimde.com"
    },
    {
        "name": "Burak Demir",
        "type": "pt",
        "title": "Fitness Antrenörü",
        "city": "Ankara",
        "district": "Çankaya"
    }
]';

        // GÜNCELLENMİŞ SÖZLÜK ÖRNEĞİ (image_url eklendi)
        $example_dictionary = '[
    {
        "title": "Squat (Çömelme)",
        "content": "Ayakları omuz genişliğinde açarak yapılan temel bacak egzersizi.",
        "excerpt": "Alt vücut için temel güç hareketi.",
        "category": "Fitness",
        "muscles": ["Bacaklar", "Kalça"],
        "equipment": ["Vücut Ağırlığı", "Dumbbell"],
        "video_url": "aclHkVaku9U", 
        "image_url": "https://images.unsplash.com/photo-1574680096141-63318b4e94e7?w=800&q=80",
        "main_benefit": "Bacak ve kalça kaslarını güçlendirir.",
        "difficulty": 2,
        "alt_names": "Çömelme"
    },
    {
        "title": "Meyve Tabağı",
        "content": "Ara öğünler için sağlıklı bir seçenek.",
        "excerpt": "Vitamin deposu.",
        "category": "Beslenme",
        "muscles": [],
        "equipment": [],
        "video_url": "", 
        "image_url": "https://images.unsplash.com/photo-1615486511484-92e5462d997f?w=800&q=80",
        "main_benefit": "Enerji verir.",
        "difficulty": 1
    }
]';

        $current_example = $active_tab === 'dictionary' ? $example_dictionary : $example_professionals;
        ?>
        <div class="wrap">
            <h1>Rejimde İçe Aktarma Merkezi</h1>
            <?php echo $message; ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=rejimde-import&tab=professionals" class="nav-tab <?php echo $active_tab == 'professionals' ? 'nav-tab-active' : ''; ?>">Uzmanlar</a>
                <a href="?page=rejimde-import&tab=dictionary" class="nav-tab <?php echo $active_tab == 'dictionary' ? 'nav-tab-active' : ''; ?>">Sözlük (Wiki)</a>
            </h2>

            <div class="card" style="margin-top:20px; padding:10px; max-width:100%;">
                <h3>Örnek JSON Formatı:</h3>
                <p class="description">Not: <code>video_url</code> (Youtube ID) veya <code>image_url</code> (Görsel Linki) kullanabilirsiniz.</p>
                <pre style="background:#f0f0f1; padding:10px; overflow:auto; max-height:300px;"><?php echo esc_html($current_example); ?></pre>
                <button type="button" class="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.innerText); alert('Kopyalandı!');">Örneği Kopyala</button>
            </div>

            <form method="post" style="margin-top:20px;">
                <?php wp_nonce_field('rejimde_import_action'); ?>
                <input type="hidden" name="import_type" value="<?php echo $active_tab; ?>">
                
                <p><strong><?php echo $active_tab === 'professionals' ? 'Uzman' : 'Sözlük Terimi'; ?> JSON Verisi:</strong></p>
                <textarea name="rejimde_import_json" rows="20" style="width:100%; font-family:monospace; background:#f0f0f1; padding:10px; border-radius:5px;" placeholder="Yukarıdaki formatta JSON verisini buraya yapıştırın..."></textarea>
                <?php submit_button('İçe Aktar'); ?>
            </form>
        </div>
        <?php
    }
}