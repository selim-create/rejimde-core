<?php
/**
 * Plugin Name:       Rejimde Core
 * Plugin URI:        https://rejimde.com
 * Description:       Rejimde.com platformunun çekirdek API ve veritabanı yönetim eklentisi.
 * Version:           1.0.3.2
 * Author:            Hip Medya
 * Text Domain:       rejimde-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Sabitler
define( 'REJIMDE_VERSION', '1.0.3.2' ); // Versiyon güncellendi
define( 'REJIMDE_PATH', plugin_dir_path( __FILE__ ) );
define( 'REJIMDE_URL', plugin_dir_url( __FILE__ ) );

// Frontend URL for invite links
if (!defined('REJIMDE_FRONTEND_URL')) {
    define('REJIMDE_FRONTEND_URL', 'https://rejimde.com');
}

// Allowed CORS origins
if (!defined('REJIMDE_ALLOWED_ORIGINS')) {
    define('REJIMDE_ALLOWED_ORIGINS', [
        'http://localhost:3000',
        'https://rejimde.com',
        'http://127.0.0.1:3000',
        'http://192.168.48.90:3000',
        'https://192.168.48.89:3000',
        'https://www.rejimde.com',
    ]);
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
 * GÜVENLİ CORS AYARLARI - TÜM YANITLARDA ÇALIŞACAK ŞEKİLDE
 * Priority 1 ile en erken çalışarak rate limit/error yanıtlarında da header'ları ekler
 */
add_action('init', function() {
    $allowed_origins = REJIMDE_ALLOWED_ORIGINS;

    // get_http_origin() yerine direkt $_SERVER kullan - daha güvenilir
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE, PATCH");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Content-Disposition, Accept, Origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400"); // 24 saat preflight cache
    }

    // OPTIONS preflight request'leri için erken çıkış
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(200);
        exit();
    }
}, 1); // Priority 1 - En erken çalışsın

/**
 * REST API yanıtlarında da CORS header'larını garantile
 * Bu filter rate limit (429) veya diğer error yanıtlarında da çalışır
 */
add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
    $allowed_origins = REJIMDE_ALLOWED_ORIGINS;
    
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    if (in_array($origin, $allowed_origins)) {
        // Header zaten gönderilmemişse ekle
        if (!headers_sent()) {
            header("Access-Control-Allow-Origin: " . $origin);
            header("Access-Control-Allow-Credentials: true");
        }
    }
    
    return $served;
}, 10, 4);