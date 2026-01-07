# 📚 API Documentation

**Sistem Inventaris & Peminjaman Barang**  
**API Version:** v1  
**Base URL:** `http://localhost:8000/api`  
**Production URL:** `https://yourdomain.com/api`

---

## 📋 Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)
- [Endpoints](#endpoints)
  - [Health Check](#health-check)
  - [Authentication](#authentication-endpoints)
  - [Dashboard](#dashboard)
  - [Categories](#categories)
  - [Items](#items)
  - [Borrowings](#borrowings)
  - [Reports](#reports)
  - [Profile](#profile)
  - [Notifications](#notifications)
  - [Activity Logs](#activity-logs)
  - [Users](#users-admin-only)

---

## Overview

### API Versioning
The API supports versioning. Current version is `v1`.

**Versioned endpoint:**
```
GET /api/v1/items
```

**Legacy endpoint (backward compatible):**
```
GET /api/items
```

### Content Type
All requests and responses use JSON format:
```
Content-Type: application/json
Accept: application/json
```

---

## Authentication

### Authentication Method
The API uses **Laravel Sanctum** token-based authentication.

### Obtaining Token
**Endpoint:** `POST /api/auth/login`

**Request:**
```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin"
  },
  "token": "1|abc123def456..."
}
```

### Using Token
Include token in all authenticated requests:
```
Authorization: Bearer YOUR_TOKEN_HERE
```

### Token Expiration
Tokens expire after **30 days** of inactivity.

---

## Error Handling

### Error Response Format
```json
{
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 204 | No Content (Delete success) |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests (Rate Limit) |
| 500 | Server Error |

### Example Error Responses

**Validation Error (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

**Unauthorized (401):**
```json
{
  "message": "Unauthenticated."
}
```

**Not Found (404):**
```json
{
  "message": "Item not found"
}
```

---

## Rate Limiting

### Limits

| Endpoint Type | Limit |
|---------------|-------|
| Public (login/register) | 10 requests per minute |
| Authenticated | 60 requests per minute |

### Rate Limit Headers
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
Retry-After: 60 (when limit exceeded)
```

---

## Endpoints

## Health Check

### Check API Health
**Endpoint:** `GET /api/health`  
**Auth:** Not required

**Response:**
```json
{
  "status": "ok",
  "database": "connected",
  "cache": "working",
  "timestamp": "2026-01-07T12:00:00Z"
}
```

---

## Authentication Endpoints

### Register
**Endpoint:** `POST /api/auth/register`  
**Auth:** Not required  
**Rate Limit:** 10 per minute

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!"
}
```

**Response (201):**
```json
{
  "user": {
    "id": 5,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "user",
    "created_at": "2026-01-07T12:00:00Z"
  },
  "token": "5|xyz789..."
}
```

**Validation Rules:**
- `name`: required, string, max:255
- `email`: required, email, unique
- `password`: required, min:8, confirmed, strong password

---

### Login
**Endpoint:** `POST /api/auth/login`  
**Auth:** Not required  
**Rate Limit:** 10 per minute

**Request:**
```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

**Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin"
  },
  "token": "1|abc123..."
}
```

**Error Response (401):**
```json
{
  "message": "Invalid credentials"
}
```

---

### Logout
**Endpoint:** `POST /api/auth/logout`  
**Auth:** Required

**Response (200):**
```json
{
  "message": "Logged out successfully"
}
```

---

### Get Current User
**Endpoint:** `GET /api/auth/me`  
**Auth:** Required

**Response (200):**
```json
{
  "id": 1,
  "name": "Admin User",
  "email": "admin@example.com",
  "role": "admin",
  "created_at": "2026-01-01T00:00:00Z"
}
```

---

## Dashboard

### Get Dashboard Statistics
**Endpoint:** `GET /api/dashboard`  
**Auth:** Required

**Response (200):**
```json
{
  "totalItems": 150,
  "totalBorrowings": 45,
  "activeBorrowings": 12,
  "overdueBorrowings": 3,
  "totalUsers": 25,
  "recentBorrowings": [
    {
      "id": 1,
      "code": "BRW-20260107-0001",
      "user": {"name": "John Doe"},
      "item": {"name": "Laptop Dell XPS"},
      "status": "dipinjam",
      "borrow_date": "2026-01-05",
      "due_date": "2026-01-12"
    }
  ],
  "popularItems": [
    {
      "id": 1,
      "name": "Laptop Dell XPS",
      "borrowing_count": 25
    }
  ]
}
```

---

## Categories

### List Categories
**Endpoint:** `GET /api/categories`  
**Auth:** Required

**Query Parameters:**
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15)
- `search`: Search by name

**Example:** `GET /api/categories?search=elektronik&per_page=10`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Elektronik",
      "description": "Peralatan elektronik",
      "items_count": 25,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 5
  }
}
```

---

### Create Category
**Endpoint:** `POST /api/categories`  
**Auth:** Required (Admin only)

**Request:**
```json
{
  "name": "Elektronik",
  "description": "Peralatan elektronik"
}
```

**Response (201):**
```json
{
  "id": 1,
  "name": "Elektronik",
  "description": "Peralatan elektronik",
  "items_count": 0,
  "created_at": "2026-01-07T12:00:00Z"
}
```

**Validation Rules:**
- `name`: required, unique, max:255
- `description`: nullable, string

---

### Get Category
**Endpoint:** `GET /api/categories/{id}`  
**Auth:** Required

**Response (200):**
```json
{
  "id": 1,
  "name": "Elektronik",
  "description": "Peralatan elektronik",
  "items_count": 25,
  "items": [
    {
      "id": 1,
      "name": "Laptop Dell XPS",
      "code": "ITM-20260101-0001"
    }
  ]
}
```

---

### Update Category
**Endpoint:** `PUT /api/categories/{id}`  
**Auth:** Required (Admin only)

**Request:**
```json
{
  "name": "Elektronik Updated",
  "description": "Peralatan elektronik terbaru"
}
```

**Response (200):**
```json
{
  "id": 1,
  "name": "Elektronik Updated",
  "description": "Peralatan elektronik terbaru"
}
```

---

### Delete Category
**Endpoint:** `DELETE /api/categories/{id}`  
**Auth:** Required (Admin only)

**Response (204):** No content

**Error (400):**
```json
{
  "message": "Cannot delete category with existing items"
}
```

---

## Items

### List Items
**Endpoint:** `GET /api/items`  
**Auth:** Required

**Query Parameters:**
- `page`: Page number
- `per_page`: Items per page
- `search`: Search by name or code
- `category_id`: Filter by category
- `condition`: Filter by condition (baik, rusak, hilang)
- `sort`: Sort field (created_at, name, stock)
- `order`: Sort order (asc, desc)

**Example:** `GET /api/items?category_id=1&condition=baik&search=laptop`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "code": "ITM-20260101-0001",
      "name": "Laptop Dell XPS 13",
      "description": "Laptop dengan spesifikasi tinggi",
      "category": {
        "id": 1,
        "name": "Elektronik"
      },
      "stock": 5,
      "available_stock": 3,
      "image": "/storage/items/uuid.jpg",
      "condition": "baik",
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 50
  }
}
```

---

### Create Item
**Endpoint:** `POST /api/items`  
**Auth:** Required (Admin only)  
**Content-Type:** `multipart/form-data`

**Request:**
```json
{
  "name": "Laptop Dell XPS 13",
  "description": "Laptop dengan spesifikasi tinggi",
  "category_id": 1,
  "stock": 5,
  "condition": "baik",
  "image": <file>
}
```

**Response (201):**
```json
{
  "id": 1,
  "code": "ITM-20260107-0001",
  "name": "Laptop Dell XPS 13",
  "description": "Laptop dengan spesifikasi tinggi",
  "category_id": 1,
  "stock": 5,
  "available_stock": 5,
  "image": "/storage/items/uuid.jpg",
  "condition": "baik"
}
```

**Validation Rules:**
- `name`: required, max:255
- `description`: nullable
- `category_id`: required, exists:categories
- `stock`: required, integer, min:0
- `condition`: required, in:baik,rusak,hilang
- `image`: nullable, image, max:2048 (2MB)

---

### Get Item
**Endpoint:** `GET /api/items/{id}`  
**Auth:** Required

**Response (200):**
```json
{
  "id": 1,
  "code": "ITM-20260101-0001",
  "name": "Laptop Dell XPS 13",
  "description": "Laptop dengan spesifikasi tinggi",
  "category": {
    "id": 1,
    "name": "Elektronik"
  },
  "stock": 5,
  "available_stock": 3,
  "image": "/storage/items/uuid.jpg",
  "condition": "baik",
  "active_borrowings": [
    {
      "id": 1,
      "user": {"name": "John Doe"},
      "quantity": 1,
      "status": "dipinjam",
      "due_date": "2026-01-15"
    }
  ],
  "created_at": "2026-01-01T00:00:00Z"
}
```

---

### Update Item
**Endpoint:** `PUT /api/items/{id}`  
**Auth:** Required (Admin only)

**Request:**
```json
{
  "name": "Laptop Dell XPS 13 Updated",
  "description": "Updated description",
  "category_id": 1,
  "stock": 6,
  "condition": "baik"
}
```

**Response (200):**
```json
{
  "id": 1,
  "code": "ITM-20260101-0001",
  "name": "Laptop Dell XPS 13 Updated",
  "available_stock": 4
}
```

---

### Delete Item
**Endpoint:** `DELETE /api/items/{id}`  
**Auth:** Required (Admin only)

**Response (204):** No content

**Error (400):**
```json
{
  "message": "Tidak dapat menghapus barang yang sedang dipinjam"
}
```

---

### Search Suggestions
**Endpoint:** `GET /api/items/search-suggestions`  
**Auth:** Required

**Query Parameters:**
- `q`: Search query (min 2 chars)

**Example:** `GET /api/items/search-suggestions?q=lap`

**Response (200):**
```json
{
  "suggestions": [
    {
      "id": 1,
      "name": "Laptop Dell XPS 13",
      "code": "ITM-20260101-0001",
      "category": "Elektronik"
    },
    {
      "id": 2,
      "name": "Laptop HP Pavilion",
      "code": "ITM-20260101-0002",
      "category": "Elektronik"
    }
  ]
}
```

---

### Bulk Delete Items
**Endpoint:** `DELETE /api/items/bulk-delete`  
**Auth:** Required (Admin only)

**Request:**
```json
{
  "ids": [1, 2, 3]
}
```

**Response (200):**
```json
{
  "message": "Successfully deleted 3 items",
  "deleted_count": 3
}
```

---

## Borrowings

### List Borrowings
**Endpoint:** `GET /api/borrowings`  
**Auth:** Required

**Query Parameters:**
- `page`, `per_page`
- `status`: pending, dipinjam, dikembalikan, terlambat
- `user_id`: Filter by user
- `item_id`: Filter by item
- `from_date`, `to_date`: Date range

**Example:** `GET /api/borrowings?status=dipinjam&from_date=2026-01-01`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "code": "BRW-20260105-0001",
      "user": {
        "id": 2,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "item": {
        "id": 1,
        "name": "Laptop Dell XPS 13",
        "code": "ITM-20260101-0001"
      },
      "quantity": 1,
      "borrow_date": "2026-01-05",
      "due_date": "2026-01-12",
      "return_date": null,
      "status": "dipinjam",
      "notes": "Untuk presentasi",
      "approved_by": {
        "name": "Admin User"
      },
      "approved_at": "2026-01-05T10:00:00Z",
      "created_at": "2026-01-05T09:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 45
  }
}
```

---

### Create Borrowing
**Endpoint:** `POST /api/borrowings`  
**Auth:** Required

**Request:**
```json
{
  "item_id": 1,
  "quantity": 1,
  "borrow_date": "2026-01-07",
  "due_date": "2026-01-14",
  "notes": "Untuk presentasi"
}
```

**Response (201):**
```json
{
  "id": 10,
  "code": "BRW-20260107-0001",
  "user_id": 2,
  "item_id": 1,
  "quantity": 1,
  "borrow_date": "2026-01-07",
  "due_date": "2026-01-14",
  "status": "pending",
  "notes": "Untuk presentasi"
}
```

**Validation Rules:**
- `item_id`: required, exists:items
- `quantity`: required, integer, min:1
- `borrow_date`: required, date, after_or_equal:today
- `due_date`: required, date, after:borrow_date
- `notes`: nullable, string

---

### Approve Borrowing
**Endpoint:** `POST /api/borrowings/{id}/approve`  
**Auth:** Required (Admin only)

**Response (200):**
```json
{
  "id": 1,
  "status": "dipinjam",
  "approved_by": 1,
  "approved_at": "2026-01-07T12:00:00Z",
  "message": "Borrowing approved successfully"
}
```

**Error (400):**
```json
{
  "message": "Peminjaman sudah diproses"
}
```

---

### Return Borrowing
**Endpoint:** `POST /api/borrowings/{id}/return`  
**Auth:** Required (Admin only)

**Request (optional):**
```json
{
  "return_date": "2026-01-14"
}
```

**Response (200):**
```json
{
  "id": 1,
  "status": "dikembalikan",
  "return_date": "2026-01-14",
  "message": "Item returned successfully"
}
```

---

### Extend Borrowing
**Endpoint:** `POST /api/borrowings/{id}/extend`  
**Auth:** Required (Admin only)

**Request:**
```json
{
  "new_due_date": "2026-01-21"
}
```

**Response (200):**
```json
{
  "id": 1,
  "due_date": "2026-01-21",
  "message": "Due date extended successfully"
}
```

---

### My Borrowings
**Endpoint:** `GET /api/borrowings/my/list`  
**Auth:** Required

**Query Parameters:**
- `status`: Filter by status

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "code": "BRW-20260105-0001",
      "item": {
        "name": "Laptop Dell XPS 13",
        "image": "/storage/items/uuid.jpg"
      },
      "quantity": 1,
      "borrow_date": "2026-01-05",
      "due_date": "2026-01-12",
      "status": "dipinjam",
      "days_remaining": 5
    }
  ]
}
```

---

## Reports

### Borrowings Report
**Endpoint:** `GET /api/reports/borrowings`  
**Auth:** Required (Admin only)

**Query Parameters:**
- `from_date`, `to_date`
- `status`
- `category_id`

**Response (200):**
```json
{
  "summary": {
    "total_borrowings": 45,
    "active": 12,
    "completed": 30,
    "overdue": 3
  },
  "data": [...]
}
```

---

### Items Report
**Endpoint:** `GET /api/reports/items`  
**Auth:** Required (Admin only)

**Response (200):**
```json
{
  "summary": {
    "total_items": 150,
    "total_stock": 500,
    "available_stock": 350,
    "borrowed": 150
  },
  "by_category": [
    {
      "category": "Elektronik",
      "total_items": 50,
      "total_stock": 200
    }
  ]
}
```

---

### Overdue Report
**Endpoint:** `GET /api/reports/overdue`  
**Auth:** Required (Admin only)

**Response (200):**
```json
{
  "total_overdue": 3,
  "data": [
    {
      "id": 1,
      "code": "BRW-20260101-0001",
      "user": {"name": "John Doe"},
      "item": {"name": "Laptop Dell XPS 13"},
      "due_date": "2026-01-05",
      "days_overdue": 2
    }
  ]
}
```

---

### Monthly Report
**Endpoint:** `GET /api/reports/monthly`  
**Auth:** Required (Admin only)

**Query Parameters:**
- `year`: Year (default: current)
- `month`: Month (default: current)

**Response (200):**
```json
{
  "period": "January 2026",
  "borrowings": {
    "total": 25,
    "approved": 20,
    "pending": 3,
    "rejected": 2
  },
  "daily_stats": [
    {"date": "2026-01-01", "count": 5},
    {"date": "2026-01-02", "count": 3}
  ]
}
```

---

### Export Borrowings PDF
**Endpoint:** `GET /api/reports/export/borrowings/pdf`  
**Auth:** Required (Admin only)

**Query Parameters:**
- `from_date`, `to_date`
- `status`

**Response:** PDF file download

---

### Export Borrowings Excel
**Endpoint:** `GET /api/reports/export/borrowings/excel`  
**Auth:** Required (Admin only)

**Query Parameters:**
- `from_date`, `to_date`
- `status`

**Response:** Excel file download

---

## Profile

### Update Profile
**Endpoint:** `PUT /api/profile`  
**Auth:** Required

**Request:**
```json
{
  "name": "John Doe Updated",
  "email": "john.updated@example.com"
}
```

**Response (200):**
```json
{
  "id": 2,
  "name": "John Doe Updated",
  "email": "john.updated@example.com",
  "message": "Profile updated successfully"
}
```

---

### Update Password
**Endpoint:** `PUT /api/profile/password`  
**Auth:** Required

**Request:**
```json
{
  "current_password": "oldpassword",
  "password": "NewSecurePass123!",
  "password_confirmation": "NewSecurePass123!"
}
```

**Response (200):**
```json
{
  "message": "Password updated successfully"
}
```

**Validation Rules:**
- `current_password`: required
- `password`: required, min:8, confirmed, strong password

---

## Notifications

### List Notifications
**Endpoint:** `GET /api/notifications`  
**Auth:** Required

**Response (200):**
```json
{
  "data": [
    {
      "id": "uuid-1",
      "type": "App\\Notifications\\BorrowingApprovedNotification",
      "data": {
        "title": "Peminjaman Disetujui",
        "message": "Peminjaman Laptop Dell XPS 13 telah disetujui",
        "borrowing_id": 1
      },
      "read_at": null,
      "created_at": "2026-01-07T12:00:00Z"
    }
  ]
}
```

---

### Unread Count
**Endpoint:** `GET /api/notifications/unread-count`  
**Auth:** Required

**Response (200):**
```json
{
  "unread_count": 5
}
```

---

### Mark as Read
**Endpoint:** `POST /api/notifications/{id}/read`  
**Auth:** Required

**Response (200):**
```json
{
  "message": "Notification marked as read"
}
```

---

### Mark All as Read
**Endpoint:** `POST /api/notifications/mark-all-read`  
**Auth:** Required

**Response (200):**
```json
{
  "message": "All notifications marked as read"
}
```

---

### Delete Notification
**Endpoint:** `DELETE /api/notifications/{id}`  
**Auth:** Required

**Response (204):** No content

---

## Activity Logs

### List Activity Logs
**Endpoint:** `GET /api/activity-logs`  
**Auth:** Required (Admin only)

**Query Parameters:**
- `page`, `per_page`
- `log_name`: Filter by log name
- `causer_id`: Filter by user

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "log_name": "default",
      "description": "created",
      "subject_type": "App\\Models\\Item",
      "subject_id": 1,
      "causer": {
        "name": "Admin User"
      },
      "properties": {
        "attributes": {"name": "Laptop Dell XPS 13"}
      },
      "created_at": "2026-01-07T12:00:00Z"
    }
  ]
}
```

---

### Recent Activity
**Endpoint:** `GET /api/activity-logs/recent`  
**Auth:** Required (Admin only)

**Query Parameters:**
- `limit`: Number of recent logs (default: 10)

**Response (200):**
```json
{
  "data": [...]
}
```

---

### Get Activity for Model
**Endpoint:** `GET /api/activity-logs/{type}/{id}`  
**Auth:** Required (Admin only)

**Example:** `GET /api/activity-logs/items/1`

**Response (200):**
```json
{
  "data": [
    {
      "description": "updated",
      "causer": {"name": "Admin User"},
      "created_at": "2026-01-07T12:00:00Z"
    }
  ]
}
```

---

## Users (Admin Only)

### List Users
**Endpoint:** `GET /api/users`  
**Auth:** Required (Admin only)

**Query Parameters:**
- `page`, `per_page`
- `search`: Search by name or email
- `role`: Filter by role

**Response (200):**
```json
{
  "data": [
    {
      "id": 2,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "user",
      "created_at": "2026-01-01T00:00:00Z",
      "borrowings_count": 5
    }
  ]
}
```

---

### Create User
**Endpoint:** `POST /api/users`  
**Auth:** Required (Admin only)

**Request:**
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "role": "user"
}
```

**Response (201):**
```json
{
  "id": 10,
  "name": "Jane Doe",
  "email": "jane@example.com",
  "role": "user"
}
```

---

### Get User
**Endpoint:** `GET /api/users/{id}`  
**Auth:** Required (Admin only)

**Response (200):**
```json
{
  "id": 2,
  "name": "John Doe",
  "email": "john@example.com",
  "role": "user",
  "created_at": "2026-01-01T00:00:00Z",
  "active_borrowings": [...],
  "borrowings_history": [...]
}
```

---

### Update User
**Endpoint:** `PUT /api/users/{id}`  
**Auth:** Required (Admin only)

**Request:**
```json
{
  "name": "John Doe Updated",
  "email": "john.updated@example.com",
  "role": "admin"
}
```

**Response (200):**
```json
{
  "id": 2,
  "name": "John Doe Updated",
  "email": "john.updated@example.com",
  "role": "admin"
}
```

---

### Delete User
**Endpoint:** `DELETE /api/users/{id}`  
**Auth:** Required (Admin only)

**Response (204):** No content

**Error (400):**
```json
{
  "message": "Cannot delete user with active borrowings"
}
```

---

## Code Examples

### cURL Examples

**Login:**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

**Get Items (with auth):**
```bash
curl -X GET http://localhost:8000/api/items \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Create Item:**
```bash
curl -X POST http://localhost:8000/api/items \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Laptop Dell",
    "category_id": 1,
    "stock": 5,
    "condition": "baik"
  }'
```

---

### JavaScript (Axios) Examples

**Setup:**
```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// Add token to requests
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
```

**Login:**
```javascript
const login = async (email, password) => {
  const response = await api.post('/auth/login', { email, password });
  localStorage.setItem('token', response.data.token);
  return response.data.user;
};
```

**Get Items:**
```javascript
const getItems = async (params = {}) => {
  const response = await api.get('/items', { params });
  return response.data;
};
```

**Create Borrowing:**
```javascript
const createBorrowing = async (data) => {
  const response = await api.post('/borrowings', data);
  return response.data;
};
```

---

## Postman Collection

Download Postman collection: [Download Here](./postman_collection.json)

Or import manually:
1. Open Postman
2. Click "Import"
3. Select this file or paste URL
4. Set environment variable `API_URL` = `http://localhost:8000/api`
5. Set environment variable `TOKEN` after login

---

## Testing

### Test with Swagger UI
Access Swagger documentation at: `http://localhost:8000/api/documentation`

### Test with API Client
Recommended API clients:
- Postman
- Insomnia
- Thunder Client (VS Code)
- REST Client (VS Code)

---

## Support

For API support or bug reports:
- 📧 Email: api-support@yourdomain.com
- 🐛 Issues: [GitHub Issues](https://github.com/yourusername/sistem-inventaris-peminjaman/issues)
- 📖 Documentation: [Full Docs](README.md)

---

**Last Updated:** January 7, 2026  
**API Version:** v1.0.0
