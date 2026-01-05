# Bot Simulation System - API Test Guide

Bu dokümanda bot simülasyon sistemi API endpoint'lerinin nasıl test edileceği anlatılmaktadır.

## Ön Koşullar

1. WordPress yönetici hesabı (manage_options yetkisi)
2. REST API erişimi
3. Bir API test aracı (Postman, cURL, vb.)

## Test Ortamı Hazırlığı

### 1. Admin Token Alma

```bash
POST /wp-json/rejimde/v1/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "admin_password"
}
```

Yanıt:
```json
{
  "status": "success",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {...}
}
```

Token'ı kaydedin, diğer isteklerde kullanacaksınız.

## Test Senaryoları

### Senaryo 1: İlk Durum Kontrolü

**1.1. Bot İstatistiklerini Kontrol Edin**

```bash
GET /wp-json/rejimde/v1/admin/bots/stats
Authorization: Bearer {token}
```

Beklenen Yanıt (henüz bot yoksa):
```json
{
  "status": "success",
  "data": {
    "total_bots": 0,
    "active_bots": 0,
    "inactive_bots": 0,
    "persona_distribution": [],
    "batches": []
  }
}
```

### Senaryo 2: Test Bot Kullanıcıları Oluşturma

**2.1. Super Active Bot Oluştur**

```bash
POST /wp-json/rejimde/v1/auth/register
Content-Type: application/json

{
  "username": "bot_superactive_001",
  "email": "bot_sa_001@example.com",
  "password": "BotPassword123!",
  "role": "rejimde_user",
  "meta": {
    "name": "Bot Süper Aktif 001",
    "is_simulation": "1",
    "simulation_persona": "super_active",
    "simulation_batch": "batch_test_001",
    "simulation_active": "1",
    "height": "175",
    "current_weight": "80",
    "gender": "male"
  }
}
```

**2.2. Dormant Bot Oluştur**

```bash
POST /wp-json/rejimde/v1/auth/register
Content-Type: application/json

{
  "username": "bot_dormant_001",
  "email": "bot_d_001@example.com",
  "password": "BotPassword123!",
  "role": "rejimde_user",
  "meta": {
    "name": "Bot Dormant 001",
    "is_simulation": "1",
    "simulation_persona": "dormant",
    "simulation_batch": "batch_test_001",
    "simulation_active": "1",
    "height": "165",
    "current_weight": "70",
    "gender": "female"
  }
}
```

**2.3. Farklı Batch'te Bot Oluştur**

```bash
POST /wp-json/rejimde/v1/auth/register
Content-Type: application/json

{
  "username": "bot_active_002",
  "email": "bot_a_002@example.com",
  "password": "BotPassword123!",
  "role": "rejimde_user",
  "meta": {
    "name": "Bot Aktif 002",
    "is_simulation": "1",
    "simulation_persona": "active",
    "simulation_batch": "batch_test_002",
    "simulation_active": "1"
  }
}
```

### Senaryo 3: Bot İstatistiklerini Doğrulama

**3.1. Güncel İstatistikleri Kontrol Edin**

```bash
GET /wp-json/rejimde/v1/admin/bots/stats
Authorization: Bearer {token}
```

Beklenen Yanıt:
```json
{
  "status": "success",
  "data": {
    "total_bots": 3,
    "active_bots": 3,
    "inactive_bots": 0,
    "persona_distribution": [
      {"persona": "super_active", "count": "1"},
      {"persona": "dormant", "count": "1"},
      {"persona": "active", "count": "1"}
    ],
    "batches": [
      {
        "batch_id": "batch_test_002",
        "count": "1",
        "created_at": "2026-01-05 21:45:00"
      },
      {
        "batch_id": "batch_test_001",
        "count": "2",
        "created_at": "2026-01-05 21:40:00"
      }
    ]
  }
}
```

### Senaryo 4: Bot Listesini Getirme

**4.1. Tüm Botları Listele**

```bash
GET /wp-json/rejimde/v1/admin/bots/list
Authorization: Bearer {token}
```

**4.2. Sadece Super Active Persona'yı Filtrele**

```bash
GET /wp-json/rejimde/v1/admin/bots/list?persona=super_active
Authorization: Bearer {token}
```

**4.3. Belirli Batch'i Filtrele**

```bash
GET /wp-json/rejimde/v1/admin/bots/list?batch_id=batch_test_001
Authorization: Bearer {token}
```

**4.4. Sadece Aktif Botları Getir**

```bash
GET /wp-json/rejimde/v1/admin/bots/list?active_only=true
Authorization: Bearer {token}
```

**4.5. Pagination ile Getir**

```bash
GET /wp-json/rejimde/v1/admin/bots/list?limit=10&offset=0
Authorization: Bearer {token}
```

### Senaryo 5: Bot Yönetimi

**5.1. Belirli Batch'i Pasife Al**

```bash
POST /wp-json/rejimde/v1/admin/bots/toggle-batch/batch_test_001
Authorization: Bearer {token}
Content-Type: application/json

{
  "active": false
}
```

Beklenen Yanıt:
```json
{
  "status": "success",
  "message": "Batch 'batch_test_001' pasife alındı.",
  "affected_count": 2
}
```

**5.2. İstatistikleri Tekrar Kontrol Et**

```bash
GET /wp-json/rejimde/v1/admin/bots/stats
Authorization: Bearer {token}
```

Beklenen:
- `active_bots`: 1 (sadece batch_test_002)
- `inactive_bots`: 2 (batch_test_001)

**5.3. Batch'i Tekrar Aktif Et**

```bash
POST /wp-json/rejimde/v1/admin/bots/toggle-batch/batch_test_001
Authorization: Bearer {token}
Content-Type: application/json

{
  "active": true
}
```

**5.4. Tüm Botları Pasife Al**

```bash
POST /wp-json/rejimde/v1/admin/bots/toggle-all
Authorization: Bearer {token}
Content-Type: application/json

{
  "active": false
}
```

**5.5. Tüm Botları Tekrar Aktif Et**

```bash
POST /wp-json/rejimde/v1/admin/bots/toggle-all
Authorization: Bearer {token}
Content-Type: application/json

{
  "active": true
}
```

### Senaryo 6: Analytics Entegrasyonu

**6.1. Exclude ID'leri Al**

```bash
GET /wp-json/rejimde/v1/admin/bots/exclude-ids
Authorization: Bearer {token}
```

Beklenen Yanıt:
```json
{
  "status": "success",
  "data": [123, 124, 125],
  "count": 3
}
```

Bu ID'ler analytics sorgularında kullanılabilir:
```sql
SELECT * FROM users 
WHERE ID NOT IN (123, 124, 125)
```

### Senaryo 7: AI Settings

**7.1. OpenAI Ayarlarını Kontrol Et**

```bash
GET /wp-json/rejimde/v1/admin/settings/ai
Authorization: Bearer {token}
```

Beklenen Yanıt (eğer key ayarlanmışsa):
```json
{
  "status": "success",
  "data": {
    "openai_api_key": "sk-...",
    "openai_model": "gpt-4o-mini"
  }
}
```

**7.2. Bot Config'i Al**

```bash
GET /wp-json/rejimde/v1/admin/settings/bot-config
Authorization: Bearer {token}
```

Beklenen Yanıt:
```json
{
  "status": "success",
  "data": {
    "api_base_url": "https://yoursite.com/wp-json/rejimde/v1",
    "persona_types": {
      "super_active": {
        "label": "Süper Aktif",
        "ai_enabled": true
      },
      "active": {
        "label": "Aktif",
        "ai_enabled": false
      },
      ...
    },
    "features": {
      "water_tracking": true,
      "steps_tracking": true,
      "meal_photos": true
    }
  }
}
```

### Senaryo 8: Batch Silme

⚠️ **DİKKAT**: Bu işlem geri alınamaz!

**8.1. Test Batch'ini Sil**

```bash
DELETE /wp-json/rejimde/v1/admin/bots/batch/batch_test_002
Authorization: Bearer {token}
Content-Type: application/json

{
  "confirm": true
}
```

Beklenen Yanıt:
```json
{
  "status": "success",
  "message": "Batch 'batch_test_002' silindi.",
  "deleted_count": 1
}
```

**8.2. Onaysız Silme Denemesi (Hata Beklenir)**

```bash
DELETE /wp-json/rejimde/v1/admin/bots/batch/batch_test_001
Authorization: Bearer {token}
Content-Type: application/json

{
  "confirm": false
}
```

Beklenen Hata:
```json
{
  "code": "confirmation_required",
  "message": "Bu işlem geri alınamaz. confirm: true gönderin.",
  "data": {
    "status": 400
  }
}
```

## Hata Durumları

### Yetkisiz Erişim
```bash
GET /wp-json/rejimde/v1/admin/bots/stats
# Token olmadan veya yetkisiz token ile
```

Beklenen Hata:
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": {
    "status": 401
  }
}
```

### Olmayan Batch
```bash
POST /wp-json/rejimde/v1/admin/bots/toggle-batch/nonexistent_batch
Authorization: Bearer {token}
Content-Type: application/json

{
  "active": true
}
```

Beklenen Hata:
```json
{
  "code": "not_found",
  "message": "Bu batch_id ile bot bulunamadı.",
  "data": {
    "status": 404
  }
}
```

### OpenAI Key Ayarlanmamış
```bash
GET /wp-json/rejimde/v1/admin/settings/ai
Authorization: Bearer {token}
```

Beklenen Hata (key yoksa):
```json
{
  "code": "not_configured",
  "message": "OpenAI API key ayarlanmamış.",
  "data": {
    "status": 400
  }
}
```

## Performans Testleri

### Çok Sayıda Bot ile Test

1. 100 bot oluşturun (script ile)
2. `/admin/bots/list` endpoint'ini çağırın
3. Response time'ı ölçün (optimize edilmiş sorgu sayesinde hızlı olmalı)
4. `/admin/bots/stats` endpoint'ini çağırın
5. Toplam query sayısını kontrol edin

## Test Checklist

- [ ] Bot istatistikleri boş durumda çalışıyor
- [ ] Bot kullanıcı kaydı yapılabiliyor
- [ ] Bot meta field'ları doğru kaydediliyor
- [ ] İstatistikler doğru hesaplanıyor
- [ ] Bot listesi filtreleme çalışıyor
- [ ] Pagination çalışıyor
- [ ] Batch toggle çalışıyor
- [ ] Tüm botları toggle çalışıyor
- [ ] Exclude IDs doğru döndürülüyor
- [ ] AI settings çalışıyor
- [ ] Bot config doğru döndürülüyor
- [ ] Batch silme çalışıyor
- [ ] Onaysız silme engelleniyor
- [ ] Yetkisiz erişim engelleniyor
- [ ] Olmayan batch için hata dönüyor
- [ ] N+1 query optimizasyonu çalışıyor

## Notlar

- Tüm admin endpoint'ler HTTPS üzerinden çağrılmalıdır
- Bearer token her istekte gönderilmelidir
- Bot kullanıcıları normal kullanıcı endpoint'leri ile de görüntülenebilir
- `is_simulation` meta field'ı ile botlar filtrelenebilir
- Batch ID'leri timestamp formatında (`batch_1736100000`) kullanılması önerilir

## Destek

Sorun yaşarsanız:
1. WordPress debug.log'u kontrol edin
2. Browser console'da network tab'ı kontrol edin
3. PHP error log'unu kontrol edin
4. Database'de usermeta tablosunu kontrol edin
