<?php
/**
 * Plugin Name:       Rejimde Core
 * Plugin URI:        https://rejimde.com
 * Description:       Rejimde.com platformunun çekirdek API ve veritabanı yönetim eklentisi.
 * Version:           1.0.3.1
 * Author:            Hip Medya
 * Text Domain:       rejimde-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Sabitler
define( 'REJIMDE_VERSION', '1.0.3.1' ); // Versiyon güncellendi
define( 'REJIMDE_PATH', plugin_dir_path( __FILE__ ) );
define( 'REJIMDE_URL', plugin_dir_url( __FILE__ ) );

// Frontend URL for invite links
if (!defined('REJIMDE_FRONTEND_URL')) {
    define('REJIMDE_FRONTEND_URL', 'https://rejimde.com');
}

// Eğer Composer kullanıyorsanız (JWT vb. kütüphaneler için) aşağıdaki satırı açabilirsiniz:
// if (file_exists(REJIMDE_PATH . 'vendor/autoload.php')) require_once REJIMDE_PATH . 'vendor/autoload.php';

// 1. Önce Loader sınıfını manuel olarak dahil et
require_once REJIMDE_PATH . 'includes/Core/Loader.php';

// 2. Aktivasyon Kancaları
if (file_exists(REJIMDE_PATH . 'includes/Core/Activator.php')) {
    require_once REJIMDE_PATH . 'includes/Core/Activator.php';
    register_activation_hook( __FILE__, ['Rejimde\Core\Activator', 'activate'] );
}

if (file_exists(REJIMDE_PATH . 'includes/Core/Deactivator.php')) {
    require_once REJIMDE_PATH . 'includes/Core/Deactivator.php';
    register_deactivation_hook( __FILE__, ['Rejimde\Core\Deactivator', 'deactivate'] );
}

// 3. Eklentiyi Başlat
function run_rejimde_core() {
    // Loader sınıfı güncellendiği için yeni modülleri (Klan vb.) otomatik yükleyecektir.
    $plugin = new Rejimde\Core\Loader();
    $plugin->run();
}
run_rejimde_core();

/**
 * ÖZEL ROLLERİ TANIMLA (CRITICAL FIX)
 * Bu fonksiyon her init'te çalışarak rollerin varlığını garanti eder.
 */
add_action('init', function() {
    // Standart Üye Rolü
    add_role(
        'rejimde_user',
        'Rejimde Üyesi',
        [
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        ]
    );

    // Uzman Rolü (Daha yetkili)
    add_role(
        'rejimde_pro',
        'Rejimde Uzmanı',
        [
            'read' => true,
            'edit_posts' => true, // Kendi profil kartını düzenleyebilsin
            'upload_files' => true, // Sertifika/Fotoğraf yükleyebilsin
            'publish_posts' => true,
        ]
    );
});

/**
 * GÜVENLİ CORS AYARLARI
 */
add_action('init', function() {
    $allowed_origins = [
        'http://localhost:3000',
        'https://rejimde.com',
        'http://127.0.0.1:3000',
        'http://192.168.48.90:3000',
        'https://192.168.48.89:3000',
        'https://www.rejimde.com',
    ];

    $origin = get_http_origin();

    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE, PATCH");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Content-Disposition");
        header("Access-Control-Allow-Credentials: true");
    }

    if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
        status_header(200);
        exit();
    }
});