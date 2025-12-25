<?php
namespace Rejimde\Core;

use Rejimde\Admin\CoreSettings;
use Rejimde\Admin\VerificationPage;
use Rejimde\Admin\ImporterPage;
use Rejimde\Admin\MascotSettings; // EKLENDİ

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
       
        // Utils (Gamification v2.0)
        if (file_exists(REJIMDE_PATH . 'includes/Utils/TimezoneHelper.php')) require_once REJIMDE_PATH . 'includes/Utils/TimezoneHelper.php';
        if (file_exists(REJIMDE_PATH . 'includes/Utils/IdempotencyHelper.php')) require_once REJIMDE_PATH . 'includes/Utils/IdempotencyHelper.php';
        if (file_exists(REJIMDE_PATH . 'includes/Utils/ConfigHelper.php')) require_once REJIMDE_PATH . 'includes/Utils/ConfigHelper.php';
        if (file_exists(REJIMDE_PATH . 'includes/Utils/DatabaseHelper.php')) require_once REJIMDE_PATH . 'includes/Utils/DatabaseHelper.php';
        
        // Services
        if (file_exists(REJIMDE_PATH . 'includes/Services/OpenAIService.php')) require_once REJIMDE_PATH . 'includes/Services/OpenAIService.php';
        // Gamification v2.0 Services
        if (file_exists(REJIMDE_PATH . 'includes/Services/RuleEngine.php')) require_once REJIMDE_PATH . 'includes/Services/RuleEngine.php';
        if (file_exists(REJIMDE_PATH . 'includes/Services/LedgerService.php')) require_once REJIMDE_PATH . 'includes/Services/LedgerService.php';
        if (file_exists(REJIMDE_PATH . 'includes/Services/EventService.php')) require_once REJIMDE_PATH . 'includes/Services/EventService.php';
        if (file_exists(REJIMDE_PATH . 'includes/Services/MilestoneService.php')) require_once REJIMDE_PATH . 'includes/Services/MilestoneService.php';
        if (file_exists(REJIMDE_PATH . 'includes/Services/ScoreService.php')) require_once REJIMDE_PATH . 'includes/Services/ScoreService.php';
        if (file_exists(REJIMDE_PATH . 'includes/Services/LevelService.php')) require_once REJIMDE_PATH . 'includes/Services/LevelService.php';
        if (file_exists(REJIMDE_PATH . 'includes/Services/ScheduledJobs.php')) require_once REJIMDE_PATH . 'includes/Services/ScheduledJobs.php';

        // API Controllers
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/MascotController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/MascotController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/AuthController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/AuthController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/ProfileController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/ProfileController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/GamificationController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/GamificationController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/ProfessionalController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/ProfessionalController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/BlogController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/BlogController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/PlanController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/PlanController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/ExerciseController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/ExerciseController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/AIController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/AIController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/CircleController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/CircleController.php';
        // YENİ: Dictionary Controller
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/DictionaryController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/DictionaryController.php';
        // YENİ: Progress Controller
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/ProgressController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/ProgressController.php';
        // YENİ: Favorites Controller
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/FavoritesController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/FavoritesController.php';
        // YENİ: Comment Sınıfları
        if (file_exists(REJIMDE_PATH . 'includes/Core/CommentMeta.php')) require_once REJIMDE_PATH . 'includes/Core/CommentMeta.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/CommentController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/CommentController.php';
        // Gamification v2.0 API Controllers
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/EventController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/EventController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/ScoreController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/ScoreController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/LevelController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/LevelController.php';
        if (file_exists(REJIMDE_PATH . 'includes/Api/V1/AdminController.php')) require_once REJIMDE_PATH . 'includes/Api/V1/AdminController.php';
        // Post Types
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Plan.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Plan.php';
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/ExercisePlan.php')) require_once REJIMDE_PATH . 'includes/PostTypes/ExercisePlan.php';
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Professional.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Professional.php';
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Badge.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Badge.php';
        
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Circle.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Circle.php';
        // YENİ: Dictionary Post Type
        if (file_exists(REJIMDE_PATH . 'includes/PostTypes/Dictionary.php')) require_once REJIMDE_PATH . 'includes/PostTypes/Dictionary.php';

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
            if (class_exists('Rejimde\\Api\\V1\\AIController') || class_exists('Rejimde\\Api\\V1\\AiController')) {
                $class = class_exists('Rejimde\\Api\\V1\\AIController') ? 'Rejimde\\Api\\V1\\AIController' : 'Rejimde\\Api\\V1\\AiController';
                (new $class())->register_routes();
            }
            if (class_exists('Rejimde\\Api\\V1\\DictionaryController')) (new \Rejimde\Api\V1\DictionaryController())->register_routes();
            
            if (class_exists('Rejimde\\Api\\V1\\CircleController')) (new \Rejimde\Api\V1\CircleController())->register_routes();
            // YENİ: Comment Routes
            if (class_exists('Rejimde\\Api\\V1\\CommentController')) (new \Rejimde\Api\V1\CommentController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\ProgressController')) (new \Rejimde\Api\V1\ProgressController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\FavoritesController')) (new \Rejimde\Api\V1\FavoritesController())->register_routes();
            // Gamification v2.0 Routes
            if (class_exists('Rejimde\\Api\\V1\\EventController')) (new \Rejimde\Api\V1\EventController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\ScoreController')) (new \Rejimde\Api\V1\ScoreController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\LevelController')) (new \Rejimde\Api\V1\LevelController())->register_routes();
            if (class_exists('Rejimde\\Api\\V1\\AdminController')) (new \Rejimde\Api\V1\AdminController())->register_routes();
        });

        // CPT Kayıtları
        add_action('init', function() {
            if (class_exists('Rejimde\\PostTypes\\Plan')) (new \Rejimde\PostTypes\Plan())->register();
            if (class_exists('Rejimde\\PostTypes\\ExercisePlan')) (new \Rejimde\PostTypes\ExercisePlan())->register();
            if (class_exists('Rejimde\\PostTypes\\Professional')) (new \Rejimde\PostTypes\Professional())->register();
            if (class_exists('Rejimde\\PostTypes\\Badge')) (new \Rejimde\PostTypes\Badge())->register();
            
            // YENİ: Dictionary Post Type
            if (class_exists('Rejimde\\PostTypes\\Dictionary')) (new \Rejimde\PostTypes\Dictionary())->register();

            // YENİ: Circle Post Type
            if (class_exists('Rejimde\\PostTypes\\Circle')) (new \Rejimde\PostTypes\Circle())->register();
        });

        // Admin Menüleri
        // Düzeltme: Admin sınıflarını doğrudan is_admin() kontrolü altında başlatıyoruz.
        // CoreSettings sınıfının nasıl yazıldığına bağlı olarak (constructor vs run methodu),
        // burada güvenli bir başlatma yapıyoruz.
        if (is_admin()) {
            // CoreSettings'i başlat
            if (class_exists('Rejimde\\Admin\\CoreSettings')) {
                $settings = new \Rejimde\Admin\CoreSettings();
                // Eğer run metodu varsa çağır, yoksa constructor hallediyordur.
                if (method_exists($settings, 'run')) {
                    $settings->run();
                }
            }

            if (class_exists('Rejimde\\Admin\\VerificationPage')) (new \Rejimde\Admin\VerificationPage())->run();
            if (class_exists('Rejimde\\Admin\\ImporterPage')) (new \Rejimde\Admin\ImporterPage())->run();
            if (class_exists('Rejimde\\Admin\\MascotSettings')) (new \Rejimde\Admin\MascotSettings())->run();
        }

        // Meta Alanları
        if (class_exists('Rejimde\\Core\\UserMeta')) (new \Rejimde\Core\UserMeta())->register();
        if (class_exists('Rejimde\\Core\\PostMeta')) (new \Rejimde\Core\PostMeta())->register();
        
        // YENİ: Comment Meta
        if (class_exists('Rejimde\\Core\\CommentMeta')) (new \Rejimde\Core\CommentMeta())->register();
        
        // Initialize Gamification v2.0 Scheduled Jobs
        if (class_exists('Rejimde\\Services\\ScheduledJobs')) {
            \Rejimde\Services\ScheduledJobs::init();
        }
    }
}