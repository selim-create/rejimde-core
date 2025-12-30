<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\FAQService;

/**
 * FAQ Controller
 * 
 * Handles FAQ management endpoints
 */
class FAQController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/faq';
    private $faqService;

    public function __construct() {
        $this->faqService = new FAQService();
    }

    public function register_routes() {
        // Expert endpoints
        
        // GET /pro/faq - List FAQs
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_faqs'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/faq/{id} - Get FAQ detail
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_faq'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/faq - Create FAQ
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'create_faq'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // PATCH /pro/faq/{id} - Update FAQ
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_faq'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // DELETE /pro/faq/{id} - Delete FAQ
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_faq'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/faq/reorder - Reorder FAQs
        register_rest_route($this->namespace, '/' . $this->base . '/reorder', [
            'methods' => 'POST',
            'callback' => [$this, 'reorder_faqs'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/faq/import-templates - Import templates
        register_rest_route($this->namespace, '/' . $this->base . '/import-templates', [
            'methods' => 'POST',
            'callback' => [$this, 'import_templates'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // Public endpoint
        
        // GET /experts/{expertId}/faq - Get public FAQs
        register_rest_route($this->namespace, '/experts/(?P<expertId>\d+)/faq', [
            'methods' => 'GET',
            'callback' => [$this, 'get_public_faqs'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * GET /pro/faq
     */
    public function get_faqs(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $faqs = $this->faqService->getFAQs($expertId);
        
        return $this->success($faqs);
    }

    /**
     * GET /pro/faq/{id}
     */
    public function get_faq(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $faqId = (int) $request['id'];
        
        $faq = $this->faqService->getFAQ($faqId, $expertId);
        
        if (!$faq) {
            return $this->error('FAQ not found', 404);
        }
        
        return $this->success($faq);
    }

    /**
     * POST /pro/faq
     */
    public function create_faq(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $data = [
            'question' => $request->get_param('question'),
            'answer' => $request->get_param('answer'),
            'category' => $request->get_param('category'),
            'is_public' => $request->get_param('is_public'),
            'sort_order' => $request->get_param('sort_order'),
        ];
        
        $result = $this->faqService->createFAQ($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['id' => $result], 'FAQ created successfully', 201);
    }

    /**
     * PATCH /pro/faq/{id}
     */
    public function update_faq(WP_REST_Request $request): WP_REST_Response {
        $faqId = (int) $request['id'];
        
        $data = [];
        $allowedFields = ['question', 'answer', 'category', 'is_public', 'sort_order'];
        
        foreach ($allowedFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }
        
        $result = $this->faqService->updateFAQ($faqId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['message' => 'FAQ updated successfully']);
    }

    /**
     * DELETE /pro/faq/{id}
     */
    public function delete_faq(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $faqId = (int) $request['id'];
        
        $result = $this->faqService->deleteFAQ($faqId, $expertId);
        
        if (!$result) {
            return $this->error('FAQ not found or access denied', 404);
        }
        
        return $this->success(['message' => 'FAQ deleted successfully']);
    }

    /**
     * POST /pro/faq/reorder
     */
    public function reorder_faqs(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $order = $request->get_param('order');
        
        if (!is_array($order)) {
            return $this->error('Order must be an array of FAQ IDs', 400);
        }
        
        $result = $this->faqService->reorderFAQs($expertId, $order);
        
        if (!$result) {
            return $this->error('Failed to reorder FAQs', 500);
        }
        
        return $this->success(['message' => 'FAQs reordered successfully']);
    }

    /**
     * POST /pro/faq/import-templates
     */
    public function import_templates(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $templatePack = $request->get_param('template_pack');
        
        if (empty($templatePack)) {
            return $this->error('Template pack is required', 400);
        }
        
        $count = $this->faqService->importTemplates($expertId, $templatePack);
        
        return $this->success([
            'imported' => $count,
            'message' => "$count FAQs imported successfully"
        ]);
    }

    /**
     * GET /experts/{expertId}/faq
     */
    public function get_public_faqs(WP_REST_Request $request): WP_REST_Response {
        $expertId = (int) $request['expertId'];
        
        $faqs = $this->faqService->getPublicFAQs($expertId);
        
        return $this->success($faqs);
    }

    // Helper methods

    protected function success($data = null, $message = 'Success', $code = 200): WP_REST_Response {
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data
        ], $code);
    }

    protected function error($message = 'Error', $code = 400): WP_REST_Response {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $message
        ], $code);
    }

    public function check_expert_auth(): bool {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('rejimde_pro', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles);
    }
}
