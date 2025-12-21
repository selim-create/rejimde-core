<?php
namespace Rejimde\Core;

class PostMeta {

    public function register() {
        add_action('rest_api_init', [$this, 'register_post_meta']);
        add_filter('rest_prepare_post', [$this, 'enrich_post_author_info'], 10, 3);
    }

    public function register_post_meta() {
        // RankMath / SEO Alanları ve Diğerleri
        $meta_keys = [
            'rank_math_title',
            'rank_math_description',
            'rank_math_focus_keyword',
            '_rejimde_featured_image_url', // URL olarak saklamak istersek
        ];

        foreach ($meta_keys as $key) {
            register_post_meta('post', $key, [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
                'auth_callback' => function() { return current_user_can('edit_posts'); }
            ]);
        }
    }

    /**
     * Enrich post author information in REST API response
     * Adds custom avatar and expert status to post author data
     * 
     * @param WP_REST_Response $response The response object
     * @param WP_Post $post The post object
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response Modified response
     */
    public function enrich_post_author_info($response, $post, $request) {
        $data = $response->get_data();
        
        // Get author ID from the post
        $author_id = $post->post_author;
        
        if ($author_id) {
            // Get custom avatar or use DiceBear fallback
            $custom_avatar = get_user_meta($author_id, 'avatar_url', true);
            
            if ($custom_avatar && !empty($custom_avatar)) {
                $author_avatar = $custom_avatar;
            } else {
                $author_nicename = get_the_author_meta('user_nicename', $author_id);
                $author_avatar = 'https://api.dicebear.com/9.x/personas/svg?seed=' . $author_nicename;
            }
            
            // Check if user is an expert
            $user = new \WP_User($author_id);
            $is_expert = in_array('rejimde_pro', (array) $user->roles);
            
            // Add enriched author data
            $data['author_info'] = [
                'id' => $author_id,
                'name' => get_the_author_meta('display_name', $author_id),
                'slug' => get_the_author_meta('user_nicename', $author_id),
                'avatar' => $author_avatar,
                'is_expert' => $is_expert
            ];
        }
        
        $response->set_data($data);
        return $response;
    }
}