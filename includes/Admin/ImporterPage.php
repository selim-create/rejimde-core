<?php
namespace Rejimde\Admin;

class ImporterPage {

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'rejimde_settings',
            'Toplu Uzman Ekle',
            'İçe Aktar (Import)',
            'manage_options',
            'rejimde-import',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        $message = '';

        if (isset($_POST['rejimde_import_json']) && check_admin_referer('rejimde_import_action')) {
            $json = stripslashes($_POST['rejimde_import_json']);
            $data = json_decode($json, true);

            if (is_array($data)) {
                $count = 0;
                foreach ($data as $item) {
                    // Post Oluştur
                    $post_id = wp_insert_post([
                        'post_title'    => $item['name'],
                        'post_status'   => 'publish',
                        'post_type'     => 'rejimde_pro'
                    ]);

                    if ($post_id) {
                        update_post_meta($post_id, 'uzmanlik_tipi', $item['type'] ?? 'dietitian');
                        update_post_meta($post_id, 'unvan', $item['title'] ?? 'Uzman');
                        update_post_meta($post_id, 'konum', $item['city'] ?? '');
                        update_post_meta($post_id, 'ilce', $item['district'] ?? '');
                        update_post_meta($post_id, 'email', $item['email'] ?? '');
                        update_post_meta($post_id, 'is_claimed', '0'); // Sahipsiz
                        update_post_meta($post_id, 'onayli', '0');
                        $count++;
                    }
                }
                $message = "<div class='notice notice-success'><p>Başarıyla $count uzman eklendi!</p></div>";
            } else {
                $message = "<div class='notice notice-error'><p>JSON formatı hatalı!</p></div>";
            }
        }

        ?>
        <div class="wrap">
            <h1>Toplu Uzman İçe Aktarma (Rehber Verisi)</h1>
            <?php echo $message; ?>
            
            <form method="post">
                <?php wp_nonce_field('rejimde_import_action'); ?>
                <p>Aşağıdaki formatta JSON verisi yapıştırın:</p>
                <pre style="background:#eee; padding:10px;">
[
    {
        "name": "Dyt. Ayşe Yılmaz",
        "type": "dietitian",
        "title": "Beslenme Uzmanı",
        "city": "İstanbul",
        "district": "Kadıköy",
        "email": "ayse@ornek.com"
    },
    {
        "name": "Koç Mehmet",
        "type": "pt",
        "title": "Fitness Antrenörü",
        "city": "Ankara",
        "district": "Çankaya"
    }
]
                </pre>
                <textarea name="rejimde_import_json" rows="15" style="width:100%; font-family:monospace;" placeholder="JSON verisini buraya yapıştırın..."></textarea>
                <br><br>
                <button type="submit" class="button button-primary">İçe Aktar ve Oluştur</button>
            </form>
        </div>
        <?php
    }
}