<?php
namespace Rejimde\Api\V1;

// HATA ÇÖZÜMÜ: Harici 'BaseController' bağımlılığını kaldırdık.
// Doğrudan WordPress'in kendi sınıfını kullanıyoruz.
// Bu sayede "Class not found" hatası riskini sıfıra indiriyoruz.
class MascotController extends \WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'mascot';

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base . '/config', [
            'methods' => 'GET', // WP_REST_Server::READABLE yerine string kullanımı daha garantidir
            'callback' => [$this, 'get_config'],
            'permission_callback' => function() { return true; }, // Anonim fonksiyon ile izin kontrolü
        ]);
    }

    public function get_config($request) {
        $defaults = [
            'meta' => [
                'version' => '1.0',
                'character_name' => 'FitBuddy',
            ],
            'states' => [
                'onboarding_welcome' => [
                    'assets' => ['mascot_wave_hello', 'mascot_holding_sign'],
                    'texts' => [
                        "Rejimde'ye hoş geldin! Baklavalar peşini bıraksın istiyorsan doğru yerdesin.",
                        "Selam! Ben senin yeni suç ortağınım... pardon, sağlık koçunum!",
                        "Hazır mısın? Bugün hayatının en fit gününün ilk günü!"
                    ]
                ],
                'water_reminder' => [
                    'assets' => ['mascot_thirsty_sweating', 'mascot_holding_water_glass'],
                    'texts' => [
                        "Hocam o suyu içmezsen skorun düşecek, benden söylemesi! 💧",
                        "Su içsen yarıyor aslında ama biz yine de içelim.",
                        "Böbrekler ağlıyor şu an, duyuyor musun? 😢"
                    ]
                ],
                'cheat_meal_detected' => [
                    'assets' => ['mascot_whistle_police', 'mascot_shocked_eyes_wide'],
                    'texts' => [
                        "Şimdi elindeki o poğaçayı yavaşça yere bırak! 🥐🚫",
                        "Bunu yersen yarınki antrenmanda acısını çıkarırım, anlaşalım.",
                        "Hocam emin miyiz? Rejimde Skoru bunu beğenmedi..."
                    ]
                ],
                'workout_motivation' => [
                    'assets' => ['mascot_lifting_dumbbell', 'mascot_running_sweatband'],
                    'texts' => [
                        "Biraz egzersiz Rejimde skorunu da canlandırır aslında! 😉",
                        "Ter, yağların ağlama şeklidir. Ağlat onları! 💪",
                        "Sadece 20 dakika... Bir dizi bölümünden kısa."
                    ]
                ]
            ]
        ];

        // Veritabanından veriyi al
        $config = get_option('rejimde_mascot_config', $defaults);

        // JSON/String kontrolü
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
                $config = $decoded;
            } else {
                // Hatalı JSON varsa varsayılanı dön
                $config = $defaults; 
            }
        }

        // MANUEL YANIT (BaseController olmadan)
        // Standart WordPress REST yanıtı döndürüyoruz.
        return new \WP_REST_Response([
            'status' => 'success',
            'message' => 'Config retrieved successfully',
            'data' => $config
        ], 200);
    }
}