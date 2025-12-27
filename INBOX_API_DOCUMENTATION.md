# Inbox & Messaging API Documentation

## Overview

The Inbox & Messaging API provides asynchronous communication between experts (rejimde_pro) and clients. This system allows for thread-based conversations, message templates, and AI-powered draft generation.

## Database Tables

### wp_rejimde_threads
Stores message threads/conversations.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| relationship_id | BIGINT UNSIGNED | FK to wp_rejimde_relationships |
| subject | VARCHAR(255) | Thread subject |
| status | ENUM | open, closed, archived |
| last_message_at | DATETIME | Timestamp of last message |
| last_message_by | BIGINT UNSIGNED | User ID of last sender |
| unread_expert | INT | Unread count for expert |
| unread_client | INT | Unread count for client |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

### wp_rejimde_messages
Stores individual messages within threads.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| thread_id | BIGINT UNSIGNED | FK to wp_rejimde_threads |
| sender_id | BIGINT UNSIGNED | User ID of sender |
| sender_type | ENUM | expert, client |
| content | TEXT | Message content |
| content_type | ENUM | text, image, file, voice, plan_link |
| attachments | LONGTEXT | JSON array of attachments |
| is_read | TINYINT(1) | Read status |
| read_at | DATETIME | Read timestamp |
| is_ai_generated | TINYINT(1) | AI generated flag |
| created_at | DATETIME | Creation timestamp |

### wp_rejimde_message_templates
Stores pre-defined message templates for experts.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED | Primary key |
| expert_id | BIGINT UNSIGNED | FK to wp_users |
| title | VARCHAR(255) | Template title |
| content | TEXT | Template content |
| category | VARCHAR(50) | Template category |
| usage_count | INT | Number of times used |
| created_at | DATETIME | Creation timestamp |

## API Endpoints

### Expert Endpoints

#### GET /wp-json/rejimde/v1/pro/inbox
List all threads for the expert.

**Authentication:** Required (rejimde_pro role)

**Query Parameters:**
- `status` (optional): Filter by status (open, closed, archived)
- `search` (optional): Search by client name or subject
- `limit` (optional): Number of results per page (default: 50)
- `offset` (optional): Pagination offset (default: 0)

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "relationship_id": 123,
      "client": {
        "id": 456,
        "name": "Ahmet Yılmaz",
        "avatar": "https://..."
      },
      "subject": "Diyet programı hakkında",
      "status": "open",
      "last_message": {
        "content": "Merhaba, bu hafta...",
        "sender_type": "client",
        "created_at": "2025-12-27 10:30:00",
        "is_read": false
      },
      "unread_count": 2,
      "created_at": "2025-12-20"
    }
  ],
  "meta": {
    "total": 15,
    "unread_total": 5
  }
}
```

#### GET /wp-json/rejimde/v1/pro/inbox/{threadId}
Get a specific thread with all messages.

**Authentication:** Required (rejimde_pro role)

**Response:**
```json
{
  "status": "success",
  "data": {
    "thread": {
      "id": 1,
      "relationship_id": 123,
      "client": {
        "id": 456,
        "name": "Ahmet Yılmaz",
        "avatar": "https://...",
        "email": "ahmet@email.com"
      },
      "subject": "Diyet programı hakkında",
      "status": "open"
    },
    "messages": [
      {
        "id": 1,
        "sender_id": 456,
        "sender_type": "client",
        "sender_name": "Ahmet Yılmaz",
        "sender_avatar": "https://...",
        "content": "Merhaba, bu hafta programımı değiştirebilir miyiz?",
        "content_type": "text",
        "attachments": null,
        "is_read": true,
        "is_ai_generated": false,
        "created_at": "2025-12-27 10:30:00"
      }
    ]
  }
}
```

#### POST /wp-json/rejimde/v1/pro/inbox/{threadId}/reply
Reply to a thread.

**Authentication:** Required (rejimde_pro role)

**Request Body:**
```json
{
  "content": "Tabii, yarın yeni programı gönderiyorum.",
  "content_type": "text",
  "attachments": null
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "message_id": 42,
    "message": "Mesaj gönderildi"
  }
}
```

#### POST /wp-json/rejimde/v1/pro/inbox/new
Create a new thread.

**Authentication:** Required (rejimde_pro role)

**Request Body:**
```json
{
  "client_id": 456,
  "subject": "Yeni program hakkında",
  "content": "İlk mesaj içeriği"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "thread_id": 1,
    "message": "Thread oluşturuldu"
  }
}
```

#### POST /wp-json/rejimde/v1/pro/inbox/{threadId}/mark-read
Mark a thread as read.

**Authentication:** Required (rejimde_pro role)

**Response:**
```json
{
  "status": "success",
  "data": {
    "message": "Okundu olarak işaretlendi"
  }
}
```

#### POST /wp-json/rejimde/v1/pro/inbox/{threadId}/close
Close a thread.

**Authentication:** Required (rejimde_pro role)

**Response:**
```json
{
  "status": "success",
  "data": {
    "message": "Thread kapatıldı"
  }
}
```

#### POST /wp-json/rejimde/v1/pro/inbox/{threadId}/archive
Archive a thread.

**Authentication:** Required (rejimde_pro role)

**Response:**
```json
{
  "status": "success",
  "data": {
    "message": "Thread arşivlendi"
  }
}
```

#### GET /wp-json/rejimde/v1/pro/inbox/templates
List message templates.

**Authentication:** Required (rejimde_pro role)

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "title": "İlk Karşılama",
      "content": "Merhaba! Size yardımcı olmaktan mutluluk duyarım.",
      "category": "general",
      "usage_count": 15,
      "created_at": "2025-12-20 10:00:00"
    }
  ]
}
```

#### POST /wp-json/rejimde/v1/pro/inbox/templates
Create a new message template.

**Authentication:** Required (rejimde_pro role)

**Request Body:**
```json
{
  "title": "Haftalık Takip",
  "content": "Bu hafta ilerlemenizi kontrol ediyorum...",
  "category": "progress"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "template_id": 5,
    "message": "Şablon oluşturuldu"
  }
}
```

#### DELETE /wp-json/rejimde/v1/pro/inbox/templates/{id}
Delete a message template.

**Authentication:** Required (rejimde_pro role)

**Response:**
```json
{
  "status": "success",
  "data": {
    "message": "Şablon silindi"
  }
}
```

#### POST /wp-json/rejimde/v1/pro/inbox/{threadId}/ai-draft
Generate an AI-powered draft response.

**Authentication:** Required (rejimde_pro role)

**Request Body:**
```json
{
  "context": "last_5_messages"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "draft": "Mesajınız için teşekkür ederim. Programınızı bu hafta..."
  }
}
```

### Client Endpoints

#### GET /wp-json/rejimde/v1/me/inbox
List threads for the authenticated client.

**Authentication:** Required

**Query Parameters:** Same as expert endpoint

**Response:** Same structure as expert endpoint

#### GET /wp-json/rejimde/v1/me/inbox/{threadId}
Get a specific thread for the client.

**Authentication:** Required

**Response:** Same structure as expert endpoint

#### POST /wp-json/rejimde/v1/me/inbox/{threadId}/reply
Client reply to a thread.

**Authentication:** Required

**Request Body:** Same as expert reply

**Response:** Same as expert reply

## Usage Examples

### Creating a Thread and Sending a Message

```bash
# Create a new thread
curl -X POST https://yourdomain.com/wp-json/rejimde/v1/pro/inbox/new \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 456,
    "subject": "Yeni program hakkında",
    "content": "Merhaba, yeni programınızı inceledim."
  }'

# Reply to the thread
curl -X POST https://yourdomain.com/wp-json/rejimde/v1/pro/inbox/1/reply \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Ek bilgiler...",
    "content_type": "text"
  }'
```

### Using Templates

```bash
# Create a template
curl -X POST https://yourdomain.com/wp-json/rejimde/v1/pro/inbox/templates \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Haftalık Takip",
    "content": "Bu hafta ilerlemenizi kontrol ediyorum...",
    "category": "progress"
  }'

# List templates
curl https://yourdomain.com/wp-json/rejimde/v1/pro/inbox/templates \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### AI Draft Generation

```bash
# Generate AI draft
curl -X POST https://yourdomain.com/wp-json/rejimde/v1/pro/inbox/1/ai-draft \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "context": "last_5_messages"
  }'
```

## Error Responses

All endpoints return consistent error responses:

```json
{
  "status": "error",
  "message": "Error description"
}
```

Common HTTP status codes:
- `400` - Bad Request (missing required fields)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found (resource doesn't exist)
- `500` - Internal Server Error

## Permission Requirements

- **Expert endpoints** (`/pro/inbox/*`): Require `rejimde_pro` role
- **Client endpoints** (`/me/inbox/*`): Require authentication
- **Ownership verification**: Both expert and client endpoints verify that the user has access to the requested thread through relationship ownership.

## Integration Notes

### Notification System
When a new message is sent, the system can integrate with the existing notification system to alert the recipient. The notification integration is prepared but requires adding a `new_message` notification type to `NotificationTypes.php`.

### AI Integration
The AI draft generation feature integrates with the existing `OpenAIService`. If OpenAI is not configured, it falls back to simple template-based responses.

### Relationship Dependency
All threads are tied to expert-client relationships (`wp_rejimde_relationships`). A relationship must exist before creating a thread between an expert and client.
