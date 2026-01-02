# Profile Views API Documentation

## Özet
Bu API, rejimde_pro üyelerinin kendi slug sayfalarını kimlerin (üye/misafir) ziyaret ettiğini takip etmelerini ve görüntülemelerini sağlar.

## Database Tablosu

### wp_rejimde_profile_views

| Kolon | Tip | Açıklama |
|-------|-----|----------|
| id | BIGINT UNSIGNED | Primary key |
| expert_user_id | BIGINT UNSIGNED | Profili görüntülenen uzmanın user ID'si |
| expert_slug | VARCHAR(255) | Uzman slug - hızlı sorgu için |
| viewer_user_id | BIGINT UNSIGNED | Görüntüleyen kullanıcı (NULL = misafir) |
| viewer_ip | VARCHAR(45) | IP adresi (anonim takip için) |
| viewer_user_agent | VARCHAR(500) | User agent bilgisi (DoS önleme için length limited) |
| is_member | TINYINT(1) | 1 = üye, 0 = misafir |
| viewed_at | DATETIME | Görüntülenme zamanı |
| session_id | VARCHAR(255) | Aynı oturumdaki tekrar ziyaretleri filtrelemek için |

**Indexes:**
- `idx_expert_user_id` on `expert_user_id`
- `idx_expert_slug` on `expert_slug`
- `idx_viewed_at` on `viewed_at`
- `idx_viewer_user_id` on `viewer_user_id`

## API Endpoints

### 1. POST /rejimde/v1/profile-views/track

Profil görüntülenmesini kaydet.

**Permission:** Public (misafirler de kullanabilir)

**Request Body:**
```json
{
  "expert_slug": "string (required)",
  "session_id": "string (required)"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "View tracked successfully",
  "data": {
    "tracked": true
  }
}
```

**Özellikler:**
- Uzman slug'dan expert_user_id otomatik bulunur
- Kendi profilini görüntüleme sayılmaz (viewer_user_id == expert_user_id ise skip)
- Aynı session'da son 30 dakika içinde kayıt varsa skip (spam önleme)
- CloudFlare header'ı kontrol edilir (HTTP_CF_CONNECTING_IP)
- IP adresi ve user agent kaydedilir
- Üye/misafir durumu otomatik belirlenir

**Örnek Kullanım:**
```javascript
// JavaScript
const sessionId = localStorage.getItem('session_id') || generateSessionId();
localStorage.setItem('session_id', sessionId);

fetch('/wp-json/rejimde/v1/profile-views/track', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    expert_slug: 'ahmet-yilmaz',
    session_id: sessionId
  })
});
```

### 2. GET /rejimde/v1/profile-views/my-stats

Kendi profil görüntülenme istatistiklerini al.

**Permission:** `rejimde_pro` veya `administrator`

**Response:**
```json
{
  "status": "success",
  "message": "Statistics retrieved successfully",
  "data": {
    "this_week": 25,
    "this_month": 120,
    "total": 450,
    "member_views": 80,
    "guest_views": 370
  }
}
```

**Örnek Kullanım:**
```javascript
// JavaScript (giriş yapmış kullanıcı gerekli)
fetch('/wp-json/rejimde/v1/profile-views/my-stats', {
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce
  }
})
.then(response => response.json())
.then(data => {
  console.log('Bu hafta:', data.data.this_week);
  console.log('Bu ay:', data.data.this_month);
  console.log('Toplam:', data.data.total);
});
```

### 3. GET /rejimde/v1/profile-views/activity

Profil görüntülenme aktivitelerini listele (sayfalama ile).

**Permission:** `rejimde_pro` veya `administrator`

**Query Parameters:**
- `page` (optional): Sayfa numarası (varsayılan: 1)
- `per_page` (optional): Sayfa başına kayıt sayısı (varsayılan: 20, max: 100)

**Response:**
```json
{
  "status": "success",
  "message": "Activity retrieved successfully",
  "data": [
    {
      "id": 1,
      "viewed_at": "2026-01-02 14:30:00",
      "is_member": true,
      "viewer": {
        "id": 123,
        "name": "Ahmet Yılmaz",
        "avatar": "https://..."
      }
    },
    {
      "id": 2,
      "viewed_at": "2026-01-02 13:15:00",
      "is_member": false,
      "viewer": null
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 450,
    "total_pages": 23
  }
}
```

**Özellikler:**
- Sadece üye görüntülemelerde viewer bilgisi gösterilir
- Misafir görüntülemelerde viewer null olur
- Avatar için önce `avatar_url` user meta kontrol edilir, yoksa dicebear fallback kullanılır
- Görüntülemeler en yeniden en eskiye sıralanır

**Örnek Kullanım:**
```javascript
// JavaScript
fetch('/wp-json/rejimde/v1/profile-views/activity?page=1&per_page=20', {
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce
  }
})
.then(response => response.json())
.then(data => {
  console.log('Aktiviteler:', data.data);
  console.log('Toplam:', data.meta.total);
  console.log('Sayfa sayısı:', data.meta.total_pages);
});
```

## Cron Job

### rejimde_weekly_view_summary

Haftalık olarak çalışan cron job, geçen hafta görüntülenme alan tüm uzmanları bulur ve her uzman için bildirim oluşturur.

**Çalışma Zamanı:** Her Pazartesi saat 09:00

**Bildirim Özellikleri:**
- `type`: `profile_view_summary`
- `category`: `expert`
- `title`: "Haftalık Profil Özeti"
- `message`: "Bu hafta profiliniz {view_count} kez görüntülendi! 🎉"
- `icon`: `fa-eye`

**Manuel Çalıştırma:**
```bash
wp cron event run rejimde_weekly_view_summary
```

## Güvenlik

1. **IP Adresi Toplama:** CloudFlare header önceliklidir (HTTP_CF_CONNECTING_IP)
2. **Spam Önleme:** Aynı session_id ile 30 dakika içinde tekrar kayıt yapılmaz
3. **Self-View Prevention:** Kullanıcı kendi profilini görüntülediğinde kayıt yapılmaz
4. **Gizlilik:** Misafir görüntülemelerde sadece IP ve user agent kaydedilir, kişisel bilgi gösterilmez

## Error Responses

```json
{
  "status": "error",
  "message": "Missing required parameters: expert_slug, session_id"
}
```

```json
{
  "status": "error",
  "message": "Expert not found"
}
```

```json
{
  "status": "error",
  "message": "Failed to track view"
}
```

## Migration Notes

Mevcut `wp_rejimde_profile_views` tablosu varsa, aktivasyon sırasında dbDelta ile otomatik olarak güncellenir. Yeni kolonlar:
- `expert_slug`
- `viewer_ip`
- `viewer_user_agent`
- `is_member`
- `session_id`

Eski kolonlar (`profile_user_id`, `viewer_ip_hash`, `source`, `created_at`) yeni şemada karşılığı yoksa veriler kaybolabilir.

## Frontend Integration Örneği

```html
<!DOCTYPE html>
<html>
<head>
    <title>Expert Profile</title>
</head>
<body>
    <script>
        // Generate or retrieve session ID
        function getSessionId() {
            let sessionId = localStorage.getItem('profile_view_session_id');
            if (!sessionId) {
                sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem('profile_view_session_id', sessionId);
            }
            return sessionId;
        }

        // Track profile view
        function trackProfileView(expertSlug) {
            fetch('/wp-json/rejimde/v1/profile-views/track', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    expert_slug: expertSlug,
                    session_id: getSessionId()
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('View tracked:', data);
            })
            .catch(error => {
                console.error('Error tracking view:', error);
            });
        }

        // Track view on page load
        document.addEventListener('DOMContentLoaded', function() {
            const expertSlug = document.body.dataset.expertSlug; // Get from data attribute
            if (expertSlug) {
                trackProfileView(expertSlug);
            }
        });
    </script>
</body>
</html>
```
