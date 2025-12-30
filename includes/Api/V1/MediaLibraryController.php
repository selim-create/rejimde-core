<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use Rejimde\Services\MediaLibraryService;

/**
 * Media Library Controller
 * 
 * Handles media library management endpoints
 */
class MediaLibraryController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'pro/media';
    private $mediaService;

    public function __construct() {
        $this->mediaService = new MediaLibraryService();
    }

    public function register_routes() {
        // GET /pro/media - List media items
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_media'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/media/{id} - Get media detail
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_media_item'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/media - Add media
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'add_media'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // PATCH /pro/media/{id} - Update media
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'update_media'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // DELETE /pro/media/{id} - Delete media
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_media'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // POST /pro/media/folders - Create folder
        register_rest_route($this->namespace, '/' . $this->base . '/folders', [
            'methods' => 'POST',
            'callback' => [$this, 'create_folder'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // GET /pro/media/folders - Get folders
        register_rest_route($this->namespace, '/' . $this->base . '/folders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_folders'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);

        // DELETE /pro/media/folders/{id} - Delete folder
        register_rest_route($this->namespace, '/' . $this->base . '/folders/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_folder'],
            'permission_callback' => [$this, 'check_expert_auth'],
        ]);
    }

    /**
     * GET /pro/media
     */
    public function get_media(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $filters = [
            'type' => $request->get_param('type'),
            'search' => $request->get_param('search'),
            'folder' => $request->get_param('folder'),
            'limit' => $request->get_param('limit') ?? 50,
            'offset' => $request->get_param('offset') ?? 0,
        ];
        
        // Remove null values
        $filters = array_filter($filters, function($value) {
            return $value !== null;
        });
        
        $items = $this->mediaService->getMediaItems($expertId, $filters);
        
        return $this->success($items);
    }

    /**
     * GET /pro/media/{id}
     */
    public function get_media_item(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $mediaId = (int) $request['id'];
        
        $item = $this->mediaService->getMediaItem($mediaId, $expertId);
        
        if (!$item) {
            return $this->error('Media not found', 404);
        }
        
        return $this->success($item);
    }

    /**
     * POST /pro/media
     */
    public function add_media(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $data = [
            'title' => $request->get_param('title'),
            'description' => $request->get_param('description'),
            'type' => $request->get_param('type'),
            'url' => $request->get_param('url'),
            'thumbnail_url' => $request->get_param('thumbnail_url'),
            'folder_id' => $request->get_param('folder_id'),
            'tags' => $request->get_param('tags'),
        ];
        
        $result = $this->mediaService->addMedia($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        // Get the full media item to return to frontend
        $mediaItem = $this->mediaService->getMediaItem($result, $expertId);
        
        if (!$mediaItem) {
            return $this->error('Failed to retrieve created media item', 500);
        }
        
        return $this->success($mediaItem, 'Media added successfully', 201);
    }

    /**
     * PATCH /pro/media/{id}
     */
    public function update_media(WP_REST_Request $request): WP_REST_Response {
        $mediaId = (int) $request['id'];
        
        $data = [];
        $allowedFields = ['title', 'description', 'url', 'thumbnail_url', 'folder_id', 'tags'];
        
        foreach ($allowedFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }
        
        $result = $this->mediaService->updateMedia($mediaId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['message' => 'Media updated successfully']);
    }

    /**
     * DELETE /pro/media/{id}
     */
    public function delete_media(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $mediaId = (int) $request['id'];
        
        $result = $this->mediaService->deleteMedia($mediaId, $expertId);
        
        if (!$result) {
            return $this->error('Media not found or access denied', 404);
        }
        
        return $this->success(['message' => 'Media deleted successfully']);
    }

    /**
     * GET /pro/media/folders
     */
    public function get_folders(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $folders = $this->mediaService->getFolders($expertId);
        
        return $this->success($folders);
    }

    /**
     * POST /pro/media/folders
     */
    public function create_folder(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        
        $data = [
            'name' => $request->get_param('name'),
            'parent_id' => $request->get_param('parent_id'),
            'sort_order' => $request->get_param('sort_order'),
        ];
        
        $result = $this->mediaService->createFolder($expertId, $data);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        return $this->success(['id' => $result], 'Folder created successfully', 201);
    }

    /**
     * DELETE /pro/media/folders/{id}
     */
    public function delete_folder(WP_REST_Request $request): WP_REST_Response {
        $expertId = get_current_user_id();
        $folderId = (int) $request['id'];
        
        $result = $this->mediaService->deleteFolder($folderId, $expertId);
        
        if (is_array($result) && isset($result['error'])) {
            return $this->error($result['error'], 400);
        }
        
        if (!$result) {
            return $this->error('Folder not found or access denied', 404);
        }
        
        return $this->success(['message' => 'Folder deleted successfully']);
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
