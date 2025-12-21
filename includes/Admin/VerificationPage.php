<?php
namespace Rejimde\Admin;

class VerificationPage {

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'rejimde_settings',
            'Uzman Onayları',
            'Onay & Vitrin',
            'manage_options',
            'rejimde-verifications',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        // --- İŞLEM YÖNETİMİ (POST) ---
        if (isset($_POST['action']) && isset($_POST['user_id']) && check_admin_referer('rejimde_verify_action')) {
            $user_id = intval($_POST['user_id']);
            $action = sanitize_text_field($_POST['action']);
            
            // Kullanıcıya bağlı Profil Kartı ID'sini bul
            $post_id = get_user_meta($user_id, 'related_pro_post_id', true);

            if ($action === 'approve') {
                // ONAYLA (Mavi Tik)
                update_user_meta($user_id, 'is_verified', '1');
                update_user_meta($user_id, 'certificate_status', 'approved');
                if ($post_id) update_post_meta($post_id, 'onayli', '1');
                echo '<div class="notice notice-success is-dismissible"><p>Uzman onaylandı ve rozet verildi.</p></div>';
                
            } elseif ($action === 'reject') {
                // REDDET / ONAY KALDIR
                update_user_meta($user_id, 'is_verified', '0');
                update_user_meta($user_id, 'certificate_status', 'rejected');
                if ($post_id) update_post_meta($post_id, 'onayli', '0');
                echo '<div class="notice notice-warning is-dismissible"><p>Uzman onayı kaldırıldı.</p></div>';
                
            } elseif ($action === 'feature') {
                // EDİTÖR SEÇİMİ YAP (Yıldız)
                if ($post_id) update_post_meta($post_id, 'editor_secimi', '1');
                echo '<div class="notice notice-success is-dismissible"><p>Uzman "Editörün Seçimi" olarak işaretlendi! ★</p></div>';
                
            } elseif ($action === 'unfeature') {
                // EDİTÖR SEÇİMİNİ KALDIR
                if ($post_id) update_post_meta($post_id, 'editor_secimi', '0');
                echo '<div class="notice notice-warning is-dismissible"><p>"Editörün Seçimi" kaldırıldı.</p></div>';
            }
        }

        // --- VERİ ÇEKME ---
        $args = [
            'role' => 'rejimde_pro',
            'orderby' => 'registered',
            'order' => 'DESC',
        ];
        $user_query = new \WP_User_Query($args);
        $experts = $user_query->get_results();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Uzman Doğrulama & Vitrin Yönetimi</h1>
            <p class="description">Uzmanların belgelerini onaylayın (Mavi Tik) veya onları vitrine çıkarın (Sarı Yıldız).</p>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped table-view-list users">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th style="width: 250px;">Uzman Bilgisi</th>
                        <th>Durumlar</th>
                        <th>Yüklenen Belge</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($experts)) : ?>
                        <?php foreach ($experts as $expert) : 
                            $post_id = get_user_meta($expert->ID, 'related_pro_post_id', true);
                            
                            // Veriler
                            $cert_url = get_user_meta($expert->ID, 'certificate_url', true);
                            $status = get_user_meta($expert->ID, 'certificate_status', true);
                            $is_verified = get_user_meta($expert->ID, 'is_verified', true);
                            $profession = get_user_meta($expert->ID, 'profession', true);
                            
                            // Editör Seçimi Durumu (Post Meta'dan)
                            $is_featured = $post_id ? get_post_meta($post_id, 'editor_secimi', true) : '0';
                            
                            // Satır Rengi (Onay bekleyenler sarı)
                            $row_style = ($status === 'pending') ? 'background-color: #fffbf0;' : '';
                        ?>
                            <tr style="<?php echo $row_style; ?>">
                                <td>#<?php echo $expert->ID; ?></td>
                                <td>
                                    <strong><?php echo esc_html($expert->display_name); ?></strong><br>
                                    <a href="mailto:<?php echo esc_attr($expert->user_email); ?>"><?php echo esc_html($expert->user_email); ?></a><br>
                                    <small style="color:#666;"><?php echo esc_html($profession ? $profession : 'Belirtilmemiş'); ?></small>
                                </td>
                                <td>
                                    <div style="margin-bottom:5px;">
                                        <?php if ($is_verified == '1') : ?>
                                            <span style="color:green; border:1px solid green; padding:2px 6px; border-radius:4px; font-size:10px;">✔ ONAYLI</span>
                                        <?php else : ?>
                                            <span style="color:gray; border:1px solid #ccc; padding:2px 6px; border-radius:4px; font-size:10px;">STANDART</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($is_featured == '1') : ?>
                                            <span style="color:#e5a500; border:1px solid #e5a500; padding:2px 6px; border-radius:4px; font-size:10px; background:#fffdf0;">★ EDİTÖR SEÇİMİ</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($cert_url) : ?>
                                        <a href="<?php echo esc_url($cert_url); ?>" target="_blank" class="button button-small">
                                            <span class="dashicons dashicons-media-document"></span> Görüntüle
                                        </a>
                                        <?php if($status === 'pending') echo '<br><span style="color:red; font-size:10px;">Onay Bekliyor</span>'; ?>
                                    <?php else : ?>
                                        <span class="description">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" style="display:inline-block; margin-right:5px;">
                                        <?php wp_nonce_field('rejimde_verify_action'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $expert->ID; ?>">
                                        
                                        <!-- Onay Butonu -->
                                        <?php if ($is_verified != '1') : ?>
                                            <button type="submit" name="action" value="approve" class="button button-primary button-small" title="Mavi Tik Ver">✔ Onayla</button>
                                        <?php else : ?>
                                            <button type="submit" name="action" value="reject" class="button button-small" title="Onayı Geri Al">✖ Reddet</button>
                                        <?php endif; ?>
                                    </form>

                                    <form method="post" style="display:inline-block;">
                                        <?php wp_nonce_field('rejimde_verify_action'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $expert->ID; ?>">

                                        <!-- Editör Seçimi Butonu -->
                                        <?php if ($is_featured != '1') : ?>
                                            <button type="submit" name="action" value="feature" class="button button-small" title="Yıldız Ver" style="color:#e5a500; border-color:#e5a500;">★ Öne Çıkar</button>
                                        <?php else : ?>
                                            <button type="submit" name="action" value="unfeature" class="button button-small" title="Yıldızı Kaldır">☆ Kaldır</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5">Kayıtlı uzman bulunamadı.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}