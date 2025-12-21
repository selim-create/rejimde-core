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

        // API Controllers
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/MascotController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/MascotController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/AuthController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/AuthController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/ProfileController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/ProfileController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/GamificationController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/GamificationController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/ProfessionalController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/ProfessionalController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/BlogController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/BlogController.php';
        
        // EKSİK OLABİLECEK SATIR: PlanController
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/PlanController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/PlanController.php';

        // Admin
        if (is_admin()) {
            if (file_exists(REJIMDE_PATH . 'includes/Admin/CoreSettings.php')) require_once REJIMDE_PATH . 'includes/Admin/CoreSettings.php';
            if (file_exists(REJIMDE_PATH . 'includes/Admin/VerificationPage.php')) require_once REJIMDE_PATH . 'includes/Admin/VerificationPage.php';
            if (file_exists(REJIMDE_PATH . 'includes/Admin/ImporterPage.php')) require_once REJIMDE_PATH . 'includes/Admin/ImporterPage.php';
        }

        // CPT
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Plan.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Plan.php';
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Professional.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Professional.php';
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Badge.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Badge.php';
    }

    private function define_hooks() {
        // Meta Kayıtları
        if (class_exists('Rejimde\Core\UserMeta')) (new \Rejimde\Core\UserMeta())->register();
        if (class_exists('Rejimde\Core\PostMeta')) (new \Rejimde\Core\PostMeta())->register();

        // API Rotaları
        add_action('rest_api_init', function() {
            if (class_exists('Rejimde\Api\V1\MascotController')) (new \Rejimde\Api\V1\MascotController())->register_routes();
            if (class_exists('Rejimde\Api\V1\AuthController')) (new \Rejimde\Api\V1\AuthController())->register_routes();
            if (class_exists('Rejimde\Api\V1\ProfileController')) (new \Rejimde\Api\V1\ProfileController())->register_routes();
            if (class_exists('Rejimde\Api\V1\GamificationController')) (new \Rejimde\Api\V1\GamificationController())->register_routes();
            if (class_exists('Rejimde\Api\V1\ProfessionalController')) (new \Rejimde\Api\V1\ProfessionalController())->register_routes();
            if (class_exists('Rejimde\Api\V1\BlogController')) (new \Rejimde\Api\V1\BlogController())->register_routes();
            
            // EKSİK OLABİLECEK SATIR: PlanController rotaları
            if (class_exists('Rejimde\Api\V1\PlanController')) (new \Rejimde\Api\V1\PlanController())->register_routes();
        });

        // CPT Kayıtları
        add_action('init', function() {
            if (class_exists('Rejimde\PostTypes\Plan')) (new \Rejimde\PostTypes\Plan())->register();
            if (class_exists('Rejimde\PostTypes\Professional')) (new \Rejimde\PostTypes\Professional())->register();
            if (class_exists('Rejimde\PostTypes\Badge')) (new \Rejimde\PostTypes\Badge())->register();
        });

        // Admin Menüleri
        if (is_admin()) {
            if (class_exists('Rejimde\Admin\CoreSettings')) (new \Rejimde\Admin\CoreSettings())->run();
            if (class_exists('Rejimde\Admin\VerificationPage')) (new \Rejimde\Admin\VerificationPage())->run();
            if (class_exists('Rejimde\Admin\ImporterPage')) (new \Rejimde\Admin\ImporterPage())->run();
        }
    }
}