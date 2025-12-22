<?php
namespace Rejimde\Core;

use Rejimde\Admin\CoreSettings;
use Rejimde\Admin\VerificationPage;
use Rejimde\Admin\ImporterPage;

class Loader {

    public function run() {
        $this->load_files();
        $this->define_hooks();
    }

    private function load_files() {
        // Temel Dosyalar
        if (file_exists(REJIMDE_PATH . 'includes/Api/BaseController.php')) require_once REJIMDE_PATH . 'includes/Api/BaseController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Core/UserMeta.php')) require_once REJIMDE_PATH . 'includes/Core/UserMeta.php';
        if (file_exists(REJIMDE_PATH . 'includes/Core/PostMeta.php')) require_once REJIMDE_PATH . 'includes/Core/PostMeta.php';
       
        // Services (YENİ)
        if (file_exists(REJIMDE_PATH . 'includes/Services/OpenAIService.php')) require_once REJIMDE_PATH . 'includes/Services/OpenAIService.php';

        // API Controllers
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/MascotController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/MascotController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/AuthController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/AuthController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/ProfileController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/ProfileController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/GamificationController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/GamificationController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/ProfessionalController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/ProfessionalController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/BlogController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/BlogController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/PlanController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/PlanController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/ExerciseController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/ExerciseController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/AiController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/AiController.php'; // YENİ

        // Post Types
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Plan.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Plan.php';
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/ExercisePlan.php')) require_once REJIMDE_PATH . 'includes/PostTypes/ExercisePlan.php';
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Professional.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Professional.php';
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Badge.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Badge.php';

        // Admin Pages
        if (is_admin()) {
            if (file_exists(REJIMDE_PATH . 'includes/Admin/CoreSettings.php')) require_once REJIMDE_PATH . 'includes/Admin/CoreSettings.php';
            if (file_exists(REJIMDE_PATH . 'includes/Admin/VerificationPage.php')) require_once REJIMDE_PATH . 'includes/Admin/VerificationPage.php';
            if (file_exists(REJIMDE_PATH . 'includes/Admin/ImporterPage.php')) require_once REJIMDE_PATH . 'includes/Admin/ImporterPage.php';
            if (file_exists(REJIMDE_PATH . 'includes/Admin/MascotSettings.php')) require_once REJIMDE_PATH . 'includes/Admin/MascotSettings.php';
        }
    }

    private function define_hooks() {
        // API Rotalarını Kaydet
        add_action('rest_api_init', function() {
            // AuthController her zaman yüklenmeli
            if (class_exists('Rejimde\\Api\\V1\\AuthController')) (new \Rejimde\Api\V1\AuthController())->register_routes();
            
            // Diğer Controllerlar
            if (class_exists('Rejimde\\Api\\V1\\MascotController')) (new \Rejimde\Api\V1\MascotController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\ProfileController')) (new \Rejimde\Api\V1\ProfileController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\GamificationController')) (new \Rejimde\Api\V1\GamificationController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\ProfessionalController')) (new \Rejimde\Api\V1\ProfessionalController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\BlogController')) (new \Rejimde\Api\V1\BlogController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\PlanController')) (new \Rejimde\Api\V1\PlanController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\ExerciseController')) (new \Rejimde\Api\V1\ExerciseController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\AiController')) (new \Rejimde\Api\V1\AiController())->register_routes(); // YENİ        
        });

        // CPT Kayıtları
        add_action('init', function() {
            if (class_exists('Rejimde\\PostTypes\\Plan')) (new \Rejimde\PostTypes\Plan())->register();
            if (class_exists('Rejimde\\PostTypes\\ExercisePlan')) (new \Rejimde\PostTypes\ExercisePlan())->register();
            if (class_exists('Rejimde\\PostTypes\\Professional')) (new \Rejimde\PostTypes\Professional())->register();
            if (class_exists('Rejimde\\PostTypes\\Badge')) (new \Rejimde\PostTypes\Badge())->register();
        });

        // Admin Menüleri
        if (is_admin()) {
            if (class_exists('Rejimde\\Admin\\CoreSettings')) (new \Rejimde\Admin\CoreSettings())->run(); // run() metodu CoreSettings'de tanımlı değilse hata verebilir, __construct içinde add_action varsa new yeterli
            // Düzeltme: CoreSettings constructor içinde action ekliyor, sadece newlemek yeterli olabilir ama singleton değilse her initte newlenmeli.
            // CoreSettings yapısına baktığımızda __construct içinde add_action var.
            // Bu yüzden burada newlemek yerine, admin_menu hookunda veya initte bir kere newlemek lazım.
            // Ancak Loader::run() bir kere çalışıyor.
            
            // Mevcut yapıda CoreSettings __construct içinde hook ekliyor.
            // Bu yüzden sadece sınıfı başlatmak yeterli.
            new \Rejimde\Admin\CoreSettings();

            if (class_exists('Rejimde\\Admin\\VerificationPage')) (new \Rejimde\Admin\VerificationPage())->run();
            if (class_exists('Rejimde\\Admin\\ImporterPage')) (new \Rejimde\Admin\ImporterPage())->run();
            if (class_exists('Rejimde\\Admin\\MascotSettings')) (new \Rejimde\Admin\MascotSettings())->run();
        }

        // Meta Alanları
        if (class_exists('Rejimde\\Core\\UserMeta')) (new \Rejimde\Core\UserMeta())->register();
        if (class_exists('Rejimde\\Core\\PostMeta')) (new \Rejimde\Core\PostMeta())->register();
    }
}