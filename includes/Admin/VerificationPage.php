<?php
namespace Rejimde\Admin;

class VerificationPage {

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'rejimde-core', // DÜZELTİLDİ: CoreSettings menüsünün altına
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
                // REDDET
                update_user_meta($user_id, 'is_verified', '0');
                update_user_meta($user_id, 'certificate_status', 'rejected');
                if ($post_id) update_post_meta($post_id, 'onayli', '0');
                echo '<div class="notice notice-warning is-dismissible"><p>Uzman onayı reddedildi.</p></div>';

            } elseif ($action === 'feature') {
                // ÖNE ÇIKAR (Vitrin)
                if ($post_id) {
                    update_post_meta($post_id, 'is_featured', '1');
                    echo '<div class="notice notice-success is-dismissible"><p>Uzman vitrine eklendi.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Hata: Kullanıcının profil kartı bulunamadı.</p></div>';
                }

            } elseif ($action === 'unfeature') {
                // VİTRİNDEN KALDIR
                if ($post_id) {
                    update_post_meta($post_id, 'is_featured', '0');
                    echo '<div class="notice notice-info is-dismissible"><p>Uzman vitrinden kaldırıldı.</p></div>';
                }
            }
        }

        // --- LİSTELEME (Sadece 'rejimde_pro' rolü olanlar) ---
        $args = [
            'role'    => 'rejimde_pro',
            'orderby' => 'registered',
            'order'   => 'DESC'
        ];
        $experts = get_users($args);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Uzman Onay ve Vitrin Yönetimi</h1>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-primary">Uzman Bilgisi</th>
                        <th scope="col" class="manage-column">Sertifika</th>
                        <th scope="col" class="manage-column">Durum</th>
                        <th scope="col" class="manage-column">Vitrin</th>
                        <th scope="col" class="manage-column">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($experts)) : ?>
                        <?php foreach ($experts as $expert) : 
                            $is_verified = get_user_meta($expert->ID, 'is_verified', true);
                            $cert_url = get_user_meta($expert->ID, 'certificate_url', true);
                            $post_id = get_user_meta($expert->ID, 'related_pro_post_id', true);
                            $is_featured = $post_id ? get_post_meta($post_id, 'is_featured', true) : '0';
                        ?>
                            <tr>
                                <td class="title column-title has-row-actions column-primary" data-colname="Uzman Bilgisi">
                                    <strong><?php echo esc_html($expert->display_name); ?></strong><br>
                                    <span class="description"><?php echo esc_html($expert->user_email); ?></span>
                                </td>
                                <td data-colname="Sertifika">
                                    <?php if ($cert_url) : ?>
                                        <a href="<?php echo esc_url($cert_url); ?>" target="_blank" class="button button-secondary">📄 Belgeyi Görüntüle</a>
                                    <?php else : ?>
                                        <span style="color:#999;">Belge Yok</span>
                                    <?php endif; ?>
                                </td>
                                <td data-colname="Durum">
                                    <?php if ($is_verified == '1') : ?>
                                        <span class="dashicons dashicons-yes-alt" style="color:green; font-size:20px;"></span> Onaylı
                                    <?php else : ?>
                                        <span class="dashicons dashicons-warning" style="color:orange; font-size:20px;"></span> Bekliyor
                                    <?php endif; ?>
                                </td>
                                <td data-colname="Vitrin">
                                    <?php if ($is_featured == '1') : ?>
                                        <span class="dashicons dashicons-star-filled" style="color:#e5a500;"></span> Vitrinde
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td data-colname="İşlemler">
                                    <form method="post" style="display:inline-block; margin-right:5px;">
                                        <?php wp_nonce_field('rejimde_verify_action'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $expert->ID; ?>">
                                        
                                        <!-- Onay Butonları -->
                                        <?php if ($is_verified != '1') : ?>
                                            <button type="submit" name="action" value="approve" class="button button-primary">✔ Onayla</button>
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