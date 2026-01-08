<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Query;
use WP_Error;

class ProfessionalController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'professionals';
    private $rejiScoreService;
    
    /**
     * Online status timeout in seconds (15 minutes)
     */
    private const ONLINE_TIMEOUT_SECONDS = 900; // 15 * 60
    
    public function __construct() {
        // Initialize RejiScore service once for reuse
        $this->rejiScoreService = new \Rejimde\Services\RejiScoreService();
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => function() { return true; },
        ]);
        
        register_rest_route($this->namespace, '/' .  $this->base .  '/(?P<slug>[a-zA-Z0-9-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => function() { return true; },
        ]);
    }

    /**
     * Uzmanları Listele
     */
    public function get_items($request) {
        // Pagination parametreleri
        $per_page = $request->get_param('per_page') ?? 24;
        $page = $request->get_param('page') ?? 1;
        
        // Güvenlik: Maximum 100 kayıt
        $per_page = min((int) $per_page, 100);
        $page = max((int) $page, 1);
        
        $args = [
            'post_type'      => 'rejimde_pro',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
        ];

        $type = $request->get_param('type');
        if (!empty($type)) {
            $args['meta_query'][] = [
                'key'     => 'uzmanlik_tipi',
                'value'   => sanitize_text_field($type),
                'compare' => '='
            ];
        }

        $query = new WP_Query($args);
        $experts = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $user_id = get_post_meta($post_id, 'related_user_id', true);
                
                $username = '';
                if ($user_id) {
                    $user_info = get_userdata($user_id);
                    if ($user_info) {
                        $username = $user_info->user_login;
                    }
                }

                $image = 'https://placehold.co/150';
                if (has_post_thumbnail()) {
                    $image = get_the_post_thumbnail_url($post_id, 'medium');
                } elseif ($user_id) {
                    $user_avatar = get_user_meta($user_id, 'avatar_url', true);
                    if ($user_avatar) $image = $user_avatar;
                }

                $is_claimed_meta = get_post_meta($post_id, 'is_claimed', true);
                $is_claimed = ($is_claimed_meta === '1' || $is_claimed_meta === true);

                // Profession (kategori) - post meta veya user meta
                $profession = get_post_meta($post_id, 'uzmanlik_tipi', true);
                if (empty($profession) && $user_id) {
                    $profession = get_user_meta($user_id, 'profession', true);
                }
                $profession = $profession ?: 'dietitian';

                // Title (ünvan) - SADECE user meta'dan, bu kullanıcının yazdığı ünvan
                $title = '';
                if ($user_id) {
                    $title = get_user_meta($user_id, 'title', true);
                }

                // Location - user meta'dan
                $location = '';
                if ($user_id) {
                    $location = get_user_meta($user_id, 'location', true);
                }
                if (empty($location)) {
                    $location = get_post_meta($post_id, 'konum', true);
                }

                // RejiScore hesapla - SADECE claim edilmiş uzmanlar için
                $rejiScoreData = [];
                if ($user_id && $is_claimed) {
                    $rejiScoreData = $this->rejiScoreService->calculate((int) $user_id);
                }

                // Deneyim yılı hesapla
                $experience_years = 0;
                if ($user_id) {
                    $career_start = get_user_meta($user_id, 'career_start_date', true);
                    if ($career_start) {
                        $start_year = (int) substr($career_start, 0, 4);
                        $current_year = (int) date('Y');
                        $experience_years = max(0, $current_year - $start_year);
                    }
                }

                // Takipçi sayısı hesapla (tek sorguyla)
                $followers = $user_id ? get_user_meta($user_id, 'rejimde_followers', true) : [];
                $followers_count = is_array($followers) ? count($followers) : 0;

                $experts[] = [
                    'id'            => $post_id,
                    'name'          => get_the_title(),
                    'slug'          => get_post_field('post_name', $post_id),
                    'username'      => $username,
                    'profession'    => $profession,           // Kategori:  dietitian, pt, doctor
                    'title'         => $title,                // Ünvan:  Dyt., Op. Dr., vs. 
                    'image'         => $image,
                    'rating'        => get_post_meta($post_id, 'puan', true) ?: '0.0',
                    'score_impact'  => get_post_meta($post_id, 'skor_etkisi', true) ?: '--',
                    'is_verified'   => get_post_meta($post_id, 'onayli', true) === '1',
                    'is_featured'   => get_post_meta($post_id, 'is_featured', true) === '1',
                    'is_online'     => $this->isUserOnline($user_id),
                    'location'      => $location,
                    'brand'         => get_post_meta($post_id, 'kurum', true) ?: get_user_meta($user_id, 'brand_name', true),
                    'is_claimed'    => $is_claimed,
                    
                    // RejiScore verileri
                    'reji_score'       => $is_claimed ? ($rejiScoreData['reji_score'] ?? 50) : null,
                    'trend_percentage' => $is_claimed ? ($rejiScoreData['trend_percentage'] ?? 0) : null,
                    'trend_direction'  => $is_claimed ? ($rejiScoreData['trend_direction'] ?? 'stable') : null,
                    'trust_score'      => $is_claimed ? ($rejiScoreData['trust_score'] ?? 50) : null,
                    'contribution_score' => $is_claimed ? ($rejiScoreData['contribution_score'] ?? 50) : null,
                    'freshness_score'  => $is_claimed ? ($rejiScoreData['freshness_score'] ?? 50) : null,
                    
                    // Sosyal veriler
                    'followers_count'  => $followers_count,
                    'client_count'     => $user_id ? $this->getActiveClientCount($user_id) : 0,
                    'content_count'    => $is_claimed ? ($rejiScoreData['content_count'] ?? 0) : 0,
                    
                    // Deneyim
                    'experience_years' => $experience_years,
                ];
            }
            wp_reset_postdata();
        }

        // Sıralama: 0. is_claimed, 1. is_featured, 2. is_verified, 3. reji_score
        usort($experts, function($a, $b) {
            // 0. Önce claim edilmiş uzmanlar (claim edilmemişler en sonda)
            if ($a['is_claimed'] && !$b['is_claimed']) return -1;
            if (!$a['is_claimed'] && $b['is_claimed']) return 1;
            
            // 1. Editörün Seçimi (is_featured)
            if ($a['is_featured'] && !$b['is_featured']) return -1;
            if (!$a['is_featured'] && $b['is_featured']) return 1;
            
            // 2. Onaylı Uzmanlar (is_verified)
            if ($a['is_verified'] && !$b['is_verified']) return -1;
            if (!$a['is_verified'] && $b['is_verified']) return 1;
            
            // 3. RejiScore'a göre (yüksekten düşüğe)
            // null değerleri 0 olarak ele al
            $scoreA = $a['reji_score'] ?? 0;
            $scoreB = $b['reji_score'] ?? 0;
            return $scoreB - $scoreA;
        });

        // Response'u pagination bilgisiyle döndür
        return new WP_REST_Response([
            'data' => $experts,
            'pagination' => [
                'total' => (int) $query->found_posts,
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => (int) $query->max_num_pages
            ]
        ], 200);
    }
    
    /**
     * Tekil Uzman Getir
     */
    public function get_item($request) {
        $slug = $request->get_param('slug');
        
        $args = [
            'name'        => $slug,
            'post_type'   => 'rejimde_pro',
            'numberposts' => 1,
        ];
        
        $posts = get_posts($args);
        
        if (empty($posts)) {
            return new WP_Error('not_found', 'Uzman bulunamadı', ['status' => 404]);
        }
        
        $post = $posts[0];
        $post_id = $post->ID;
        $user_id = get_post_meta($post_id, 'related_user_id', true);
        
        $username = '';
        if ($user_id) {
            $user_info = get_userdata($user_id);
            if ($user_info) {
                $username = $user_info->user_login;
            }
        }

        $image = 'https://placehold.co/300';
        if (has_post_thumbnail($post_id)) {
             $image = get_the_post_thumbnail_url($post_id, 'large');
        } elseif ($user_id) {
             $user_avatar = get_user_meta($user_id, 'avatar_url', true);
             if ($user_avatar) $image = $user_avatar;
        }

        $is_claimed_meta = get_post_meta($post_id, 'is_claimed', true);
        $is_claimed = ($is_claimed_meta === '1' || $is_claimed_meta === true);

        // ===============================================
        // PROFESSION (KATEGORİ) vs TITLE (ÜNVAN) AYRIMI
        // ===============================================
        
        // Profession = Meslek kategorisi (dietitian, pt, doctor, yoga, etc.)
        $profession = get_post_meta($post_id, 'uzmanlik_tipi', true);
        if (empty($profession) && $user_id) {
            $profession = get_user_meta($user_id, 'profession', true);
        }
        $profession = $profession ?: 'dietitian';
        
        // Title = Ünvan (Dyt., Uzm. Dyt., Op. Dr., Prof.  Dr., vs.)
        // Bu SADECE kullanıcının ayarlar sayfasında yazdığı ünvandır
        $title = '';
        if ($user_id) {
            $title = get_user_meta($user_id, 'title', true);
        }

        // ===============================================
        // USER META'DAN YENİ ALANLAR
        // ===============================================
        
        // Kimlik & Profil
        $motto = get_user_meta($user_id, 'motto', true) ?: '';
        
        // Lokasyon
        $country = get_user_meta($user_id, 'country', true) ?: 'TR';
        $city = get_user_meta($user_id, 'city', true) ?: '';
        $district = get_user_meta($user_id, 'district', true) ?: '';
        $address = get_user_meta($user_id, 'address', true) ?: '';
        $phone = get_user_meta($user_id, 'phone', true) ?: '';
        
        $location = get_user_meta($user_id, 'location', true);
        if (empty($location)) {
            $location = get_post_meta($post_id, 'konum', true);
        }
        
        // Hizmet Dilleri
        $service_languages = $this->parse_json_field(get_user_meta($user_id, 'service_languages', true), ['tr']);
        
        // Mesleki Deneyim
        $career_start_date = get_user_meta($user_id, 'career_start_date', true) ?: '';
        $education = $this->parse_json_field(get_user_meta($user_id, 'education', true), []);
        $certificates = $this->parse_json_field(get_user_meta($user_id, 'certificates', true), []);
        
        // Uzmanlık & Etiketler
        $expertise_tags = $this->parse_json_field(get_user_meta($user_id, 'expertise_tags', true), []);
        $goal_tags = $this->parse_json_field(get_user_meta($user_id, 'goal_tags', true), []);
        $level_suitability = $this->parse_json_field(get_user_meta($user_id, 'level_suitability', true), []);
        $age_groups = $this->parse_json_field(get_user_meta($user_id, 'age_groups', true), []);
        
        // Danışan Bilgileri
        $client_type = get_user_meta($user_id, 'client_type', true) ?: '';
        $client_types = get_user_meta($user_id, 'client_types', true) ?: '';
        
        // Çalışmadığı Durumlar
        $excluded_cases = $this->parse_json_field(get_user_meta($user_id, 'excluded_cases', true), []);
        $referral_note = get_user_meta($user_id, 'referral_note', true) ?: '';
        
        // Çalışma & İletişim
        $working_hours = $this->parse_json_field(get_user_meta($user_id, 'working_hours', true), ['weekday' => '', 'weekend' => '']);
        $response_time = get_user_meta($user_id, 'response_time', true) ?: '24h';
        $communication_preference = $this->parse_json_field(get_user_meta($user_id, 'communication_preference', true), []);
        
        // Görünürlük & Mahremiyet
        $privacy_settings = $this->parse_json_field(get_user_meta($user_id, 'privacy_settings', true), [
            'show_phone' => false,
            'show_address' => false,
            'show_location' => true
        ]);

        // Bio
        $bio = get_user_meta($user_id, 'bio', true);
        if (empty($bio)) {
            $bio = $post->post_content;
        }

        // Brand
        $brand = get_post_meta($post_id, 'kurum', true);
        if (empty($brand)) {
            $brand = get_user_meta($user_id, 'brand_name', true);
        }

        // Branches
        $branches = get_post_meta($post_id, 'branslar', true);
        if (empty($branches)) {
            $branches = get_user_meta($user_id, 'branches', true);
        }

        // Services
        $services = get_post_meta($post_id, 'hizmetler', true);
        if (empty($services)) {
            $services = get_user_meta($user_id, 'services', true);
        }

        // ===============================================
        // GÖRÜNÜRLÜk AYARLARINA GÖRE VERİ FİLTRELEME
        // ===============================================
        
        // Telefon - show_phone false ise gizle
        $phone_visible = $phone;
        if (empty($privacy_settings['show_phone']) || $privacy_settings['show_phone'] === false) {
            $phone_visible = ''; // Gizli
        }
        
        // Adres - show_address false ise gizle
        $address_visible = $address;
        if (empty($privacy_settings['show_address']) || $privacy_settings['show_address'] === false) {
            $address_visible = ''; // Gizli
        }
        
        // Lokasyon - show_location false ise gizle
        $location_visible = $location;
        $city_visible = $city;
        $district_visible = $district;
        if (isset($privacy_settings['show_location']) && $privacy_settings['show_location'] === false) {
            $location_visible = '';
            $city_visible = '';
            $district_visible = '';
        }

        $data = [
            'id'              => $post_id,
            'user_id'         => $user_id ?  (int) $user_id : null,
            'related_user_id' => $user_id ? (int) $user_id : null,
            'name'            => $post->post_title,
            'slug'            => $post->post_name,
            'username'        => $username,
            'bio'             => $bio,
            
            // KATEGORİ ve ÜNVAN AYRIMI
            'profession'    => $profession,    // Kategori:  dietitian, pt, doctor, yoga, etc.
            'title'         => $title,         // Ünvan: Dyt., Op. Dr., vs.  (kullanıcı yazar)
            
            'image'         => $image,
            'rating'        => get_post_meta($post_id, 'puan', true) ?: '0.0',
            'score_impact'  => get_post_meta($post_id, 'skor_etkisi', true) ?: '--',
            'is_verified'   => get_post_meta($post_id, 'onayli', true) === '1',
            'is_featured'   => get_post_meta($post_id, 'is_featured', true) === '1',
            'is_claimed'    => $is_claimed,
            'is_online'     => $this->isUserOnline($user_id),
            
            // Görünürlük ayarlarına göre filtrelenmiş veriler
            'location'      => $location_visible,
            'city'          => $city_visible,
            'district'      => $district_visible,
            'address'       => $address_visible,
            'phone'         => $phone_visible,
            
            // Her zaman görünen veriler
            'country'       => $country,
            'brand'         => $brand,
            'branches'      => $branches,
            'services'      => $services,
            'client_types'  => $client_types,
            'consultation_types' => get_user_meta($user_id, 'consultation_types', true),
            
            // Kimlik & Profil
            'motto'         => $motto,
            
            // Hizmet & Dil
            'service_languages' => $service_languages,
            
            // Mesleki Deneyim
            'career_start_date' => $career_start_date,
            'education'     => $education,
            'certificates'  => $certificates,
            
            // Uzmanlık & Etiketler
            'expertise_tags'    => $expertise_tags,
            'goal_tags'         => $goal_tags,
            'level_suitability' => $level_suitability,
            'age_groups'        => $age_groups,
            
            // Danışan Bilgileri
            'client_type'   => $client_type,
            
            // Çalışmadığı Durumlar
            'excluded_cases'    => $excluded_cases,
            'referral_note'     => $referral_note,
            
            // Çalışma & İletişim
            'working_hours'     => $working_hours,
            'response_time'     => $response_time,
            'communication_preference' => $communication_preference,
            
            // Görünürlük ayarları (frontend'in bilmesi için)
            'privacy_settings'  => $privacy_settings,
        ];

        // RejiScore hesapla ve ekle - SADECE claim edilmiş uzmanlar için
        $rejiScoreData = [];
        if ($user_id && $is_claimed) {
            $rejiScoreData = $this->rejiScoreService->calculate((int) $user_id);
        }

        // RejiScore verilerini response'a ekle
        $data['reji_score'] = $is_claimed ? ($rejiScoreData['reji_score'] ?? 50) : null;
        $data['trust_score'] = $is_claimed ? ($rejiScoreData['trust_score'] ?? 50) : null;
        $data['contribution_score'] = $is_claimed ? ($rejiScoreData['contribution_score'] ?? 50) : null;
        $data['freshness_score'] = $is_claimed ? ($rejiScoreData['freshness_score'] ?? 50) : null;
        $data['trend_percentage'] = $is_claimed ? ($rejiScoreData['trend_percentage'] ?? 0) : null;
        $data['trend_direction'] = $is_claimed ? ($rejiScoreData['trend_direction'] ?? 'stable') : null;
        $data['score_level'] = $is_claimed ? ($rejiScoreData['level'] ?? 1) : null;
        $data['score_level_label'] = $is_claimed ? ($rejiScoreData['level_label'] ?? 'Yeni') : null;
        $data['review_count'] = $is_claimed ? ($rejiScoreData['review_count'] ?? 0) : 0;
        $data['content_count'] = $is_claimed ? ($rejiScoreData['content_count'] ?? 0) : 0;
        // Yeni alanlar
        $data['goal_success_rate'] = $is_claimed ? ($rejiScoreData['goal_success_rate'] ?? 85) : null;

        // ===============================================
        // YENİ ALANLAR: Sosyal ve Danışan Verileri
        // ===============================================
        
        // Takipçi & Sosyal Veriler (API'den çekilecek, localStorage'dan DEĞİL)
        if ($user_id) {
            $followers = get_user_meta($user_id, 'rejimde_followers', true);
            $data['followers_count'] = is_array($followers) ? count($followers) : 0;
            
            $following = get_user_meta($user_id, 'rejimde_following', true);
            $data['following_count'] = is_array($following) ? count($following) : 0;
            
            // Check if current user follows this expert
            $current_user_id = get_current_user_id();
            if ($current_user_id) {
                $data['is_following'] = is_array($followers) && in_array($current_user_id, $followers);
            } else {
                $data['is_following'] = false;
            }
            
            // Dinamik Danışan Sayısı (Gerçek danışan sayısı)
            $data['client_count'] = $this->getActiveClientCount($user_id);
        } else {
            $data['followers_count'] = 0;
            $data['following_count'] = 0;
            $data['is_following'] = false;
            $data['client_count'] = 0;
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * JSON alanını güvenli parse et
     */
    private function parse_json_field($value, $fallback = []) {
        if (empty($value)) {
            return $fallback;
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        return $fallback;
    }
    
    /**
     * Uzmanın aktif danışan sayısını hesapla
     */
    private function getActiveClientCount($expert_user_id) {
        global $wpdb;
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_relationships 
            WHERE expert_id = %d 
            AND status = 'active' 
            AND client_id > 0",
            $expert_user_id
        ));
        
        return (int) $count;
    }
    
    /**
     * Kullanıcının online olup olmadığını kontrol et
     * Son 15 dakika içinde aktivite varsa online kabul edilir
     * 
     * @param int $user_id WordPress user ID
     * @return bool True if user is online, false otherwise
     */
    private function isUserOnline($user_id): bool {
        if (!$user_id) return false;
        
        $last_activity = get_user_meta($user_id, 'last_activity', true);
        if (!$last_activity) return false;
        
        $timeout_threshold = time() - self::ONLINE_TIMEOUT_SECONDS;
        return (int)$last_activity > $timeout_threshold;
    }
}