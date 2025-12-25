# Event-Driven Puan Sistemi Kullanım Kılavuzu

## Genel Bakış

Rejimde Core eklentisi artık merkezi bir event-driven puan sistemi kullanmaktadır. Bu sistem, tüm kullanıcı aksiyonlarını loglar ve puanları otomatik olarak hesaplar.

## Temel Kavramlar

### Event Types (Olay Tipleri)

Sistemde tanımlı event tipleri:

- `login_success` - Kullanıcı girişi (streak tracking ile)
- `blog_points_claimed` - Blog okuma (sticky/normal ayrımı)
- `diet_started` / `diet_completed` - Diyet başlatma/tamamlama
- `exercise_started` / `exercise_completed` - Egzersiz başlatma/tamamlama
- `calculator_saved` - Hesaplayıcı kullanımı
- `rating_submitted` - Uzman değerlendirme
- `comment_created` - Yorum yapma
- `comment_liked` - Yorum beğenme (milestone kontrolü ile)
- `follow_accepted` - Takip (her iki taraf için)
- `highfive_sent` - Beşlik çakma
- `circle_created` / `circle_joined` - Circle oluşturma/katılım

### Özellikler

1. **İdempotency**: Aynı event için tekrar puan verilmez
2. **Daily Limits**: Günlük event limit kontrolü
3. **Per-Entity Limits**: Aynı varlık için tekrar puan yok
4. **Pro User Exception**: `rejimde_pro` rolündeki kullanıcılar puan kazanmaz
5. **Feature Flags**: Bazı özellikler açılıp kapatılabilir
6. **Streak Bonuses**: Ardışık gün bonusları (7,14,30,60,90)
7. **Milestones**: Özel başarı ödülleri (örn: comment likes)

## Kullanım

### Event Dispatch Etme

```php
// EventDispatcher instance al
$dispatcher = \Rejimde\Core\EventDispatcher::getInstance();

// Event dispatch et
$result = $dispatcher->dispatch('event_type', [
    'user_id' => $user_id,           // Opsiyonel (yoksa current user)
    'entity_type' => 'blog',         // Opsiyonel
    'entity_id' => 123,              // Opsiyonel
    'context' => [                   // Opsiyonel ek bilgiler
        'is_sticky' => true,
        'target_user_id' => 456
    ]
]);

// Sonuç kontrolü
if ($result['success']) {
    $points = $result['points_earned'];
    $total = $result['total_score'];
    $message = $result['message'];
}
```

### Yeni Event Type Ekleme

1. `includes/Config/ScoringRules.php` dosyasına yeni event ekleyin:

```php
'my_custom_event' => [
    'points' => 15,              // Sabit puan
    'daily_limit' => 5,          // Günlük limit (opsiyonel)
    'per_entity_limit' => 1,     // Varlık başına limit (opsiyonel)
    'feature_flag' => 'my_flag', // Feature flag (opsiyonel)
    'label' => 'Özel Aksiyon'
],
```

2. Controller'ınızda event dispatch edin:

```php
$dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
$dispatcher->dispatch('my_custom_event', [
    'user_id' => get_current_user_id(),
    'entity_type' => 'my_entity',
    'entity_id' => $entity_id
]);
```

### Dinamik Puanlar

Entity meta'dan puan almak için:

```php
'my_event' => [
    'points' => 'dynamic',  // entity meta'dan 'reward_points' alanını okur
    'label' => 'Dinamik Puan'
],
```

### Array-Based Puanlar

Farklı durumlar için farklı puanlar:

```php
'blog_points_claimed' => [
    'points' => ['sticky' => 50, 'normal' => 10],
    'label' => 'Blog Okuma'
],
```

Context'te `is_sticky` bilgisi gönderilmelidir.

## API Endpoints

### Yeni Endpoints

```
GET /rejimde/v1/gamification/streak
GET /rejimde/v1/gamification/milestones?limit=50
GET /rejimde/v1/gamification/events?limit=50&offset=0
```

### Mevcut Endpoint Kullanımı

```
POST /rejimde/v1/gamification/earn
Body: {
    "action": "event_type",
    "ref_id": 123,
    "context": { ... }
}
```

## Veritabanı Tabloları

### rejimde_events
Tüm event'lerin detaylı logu. 90 gün sonra otomatik temizlenir.

### rejimde_streaks
Kullanıcı streak bilgileri. Grace period (haftalık 2 gün telafi) destekler.

### rejimde_milestones
Milestone ödülleri. UNIQUE constraint ile idempotent.

### rejimde_score_snapshots
Günlük/haftalık/aylık özetler ve sıralamalar.

## Cron Jobs

Otomatik olarak şu işleri yapar:

- **Günlük (00:00)**: Daily snapshots oluşturur
- **Haftalık (Pazartesi)**: Weekly snapshots + ranking
- **Aylık (1. gün)**: Monthly snapshots
- **Haftalık (Pazartesi)**: Streak grace period reset
- **Haftalık (Pazar)**: Eski event'leri temizler (90+ gün)

## Örnek Kullanım Senaryoları

### Blog Okuma
```php
$dispatcher->dispatch('blog_points_claimed', [
    'entity_type' => 'blog',
    'entity_id' => $post_id,
    'context' => ['is_sticky' => is_sticky($post_id)]
]);
```

### Takip İşlemi
```php
$dispatcher->dispatch('follow_accepted', [
    'follower_id' => $current_user_id,
    'followed_id' => $target_user_id
]);
```

### Yorum Beğeni (Milestone ile)
```php
// Like meta update
update_comment_meta($comment_id, 'like_count', $new_count);

// Event dispatch (milestone otomatik kontrol edilir)
$dispatcher->dispatch('comment_liked', [
    'user_id' => $liker_id,
    'comment_id' => $comment_id,
    'entity_type' => 'comment',
    'entity_id' => $comment_id
]);
```

## Feature Flags

`includes/Config/ScoringRules.php` içinde:

```php
'feature_flags' => [
    'enable_water_tracking' => false,
    'enable_steps_tracking' => false,
    'enable_meal_photos' => true,
    'enable_circle_creation_points' => false,
    'enable_daily_score_cap' => false,
    'daily_score_cap_value' => 500
]
```

## Önemli Notlar

1. **Pro Kullanıcılar**: `rejimde_pro` rolündeki kullanıcılar event'leri loglar ama puan kazanmaz
2. **Backward Compatibility**: Mevcut `rejimde_daily_logs` ve `rejimde_total_score` korunur
3. **Circle Integration**: Kullanıcı circle'a dahilse, circle score'u otomatik güncellenir
4. **Test Ortamı**: Plugin aktivasyonunda tablolar otomatik oluşturulur

## Sorun Giderme

### Puan Verilmedi?

Kontrol edin:
1. Kullanıcı pro mu? → `user_can('rejimde_pro')`
2. Daily limit aşıldı mı? → Event history kontrol et
3. Entity limit var mı? → Aynı entity için daha önce puan alındı mı?
4. Feature flag kapalı mı? → Config dosyasını kontrol et

### Event Loglanmadı?

1. Veritabanı tabloları oluşturuldu mu?
2. EventDispatcher doğru çağrıldı mı?
3. PHP error log kontrol et

## Geliştirme İpuçları

1. Her yeni event için önce `ScoringRules.php`'ye ekleyin
2. Event dispatch'ten sonra `$result['success']` kontrol edin
3. Test ortamında event'leri manuel tetikleyerek test edin
4. Production'da cron job'ların çalıştığından emin olun
