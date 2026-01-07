# 💻 Developer Guide

**Sistem Inventaris & Peminjaman Barang**  
**Version:** 1.0.0  
**Last Updated:** 7 Januari 2026

---

## 📋 Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Project Structure](#project-structure)
4. [Coding Standards](#coding-standards)
5. [Backend Development](#backend-development)
6. [Frontend Development](#frontend-development)
7. [Database Management](#database-management)
8. [Testing](#testing)
9. [API Development](#api-development)
10. [Common Development Tasks](#common-development-tasks)
11. [Debugging](#debugging)
12. [Git Workflow](#git-workflow)
13. [Performance Optimization](#performance-optimization)
14. [Security Best Practices](#security-best-practices)
15. [Troubleshooting](#troubleshooting)

---

## 📖 Introduction

Welcome to the Sistem Inventaris & Peminjaman Barang development team! This guide will help you understand the codebase, development workflow, and best practices.

### Technology Stack

**Backend:**
- Laravel 12 (PHP 8.2+)
- MySQL 8.0
- Redis (caching & queues)
- Laravel Sanctum (authentication)

**Frontend:**
- React 19
- Vite 6
- Tailwind CSS v4
- React Router v7
- Axios (HTTP client)
- React Hook Form (forms)

**Development Tools:**
- Docker & Docker Compose
- PHPUnit (backend testing)
- Vitest (frontend testing)
- Laravel Telescope (debugging)
- Swagger/OpenAPI (API docs)

---

## 🚀 Getting Started

### Prerequisites

**Required:**
- PHP 8.2 or higher
- Composer 2.x
- Node.js 20.x or higher
- MySQL 8.0 or higher
- Git
- Redis (optional, for development)

**Recommended:**
- Docker Desktop
- VS Code with extensions:
  - PHP Intelephense
  - Laravel Extension Pack
  - ESLint
  - Prettier
  - Tailwind CSS IntelliSense

### Initial Setup

```bash
# Clone repository
git clone https://github.com/yourusername/sistem-inventaris-peminjaman.git
cd sistem-inventaris-peminjaman

# Backend setup
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link

# Frontend setup
cd frontend
npm install
cp .env.example .env
cd ..

# Start development servers
# Terminal 1: Backend
php artisan serve

# Terminal 2: Frontend
cd frontend
npm run dev

# Terminal 3: Queue worker (optional)
php artisan queue:work

# Terminal 4: Laravel Telescope (optional)
php artisan telescope:install
php artisan migrate
```

### Using Docker (Recommended)

```bash
# Start all services
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed

# Access:
# - Frontend: http://localhost:5173
# - Backend: http://localhost:8000
# - MySQL: localhost:3306
# - Redis: localhost:6379
```

---

## 📁 Project Structure

### Backend Structure

```
app/
├── Console/
│   ├── Commands/          # Custom Artisan commands
│   │   └── ArchiveOldData.php
│   └── Kernel.php
├── Exceptions/
│   └── Handler.php        # Global exception handling
├── Exports/               # Excel export classes
│   └── BorrowingsExport.php
├── Http/
│   ├── Controllers/       # API controllers
│   │   ├── Api/
│   │   │   ├── ActivityLogController.php
│   │   │   ├── BorrowingController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── ItemController.php
│   │   │   ├── NotificationController.php
│   │   │   └── ReportController.php
│   │   └── AuthController.php
│   ├── Middleware/        # Custom middleware
│   │   ├── AdminMiddleware.php
│   │   ├── CheckBanned.php
│   │   └── SecurityHeaders.php
│   ├── Requests/          # Form request validation
│   │   ├── StoreBorrowingRequest.php
│   │   ├── StoreItemRequest.php
│   │   └── UpdateItemRequest.php
│   └── Resources/         # API resources (transformers)
│       ├── BorrowingResource.php
│       ├── ItemResource.php
│       └── UserResource.php
├── Jobs/                  # Queue jobs
│   ├── SendBorrowingNotification.php
│   └── SendOverdueNotification.php
├── Models/                # Eloquent models
│   ├── Borrowing.php
│   ├── Category.php
│   ├── Item.php
│   └── User.php
├── Notifications/         # Email/notification classes
│   ├── BorrowingApproved.php
│   └── BorrowingOverdue.php
├── Providers/             # Service providers
│   ├── AppServiceProvider.php
│   └── RouteServiceProvider.php
├── Rules/                 # Custom validation rules
│   └── StrongPassword.php
└── Services/              # Business logic services
    ├── BorrowingService.php
    └── ItemService.php
```

### Frontend Structure

```
frontend/
├── public/                # Static assets
├── src/
│   ├── assets/           # Images, fonts, etc.
│   ├── components/       # Reusable components
│   │   ├── common/
│   │   │   ├── Button.jsx
│   │   │   ├── Input.jsx
│   │   │   ├── Modal.jsx
│   │   │   └── Table.jsx
│   │   ├── layout/
│   │   │   ├── Header.jsx
│   │   │   ├── Sidebar.jsx
│   │   │   └── Footer.jsx
│   │   └── LazyImage.jsx
│   ├── contexts/         # React Context (state management)
│   │   ├── AuthContext.jsx
│   │   └── NotificationContext.jsx
│   ├── hooks/            # Custom React hooks
│   │   ├── useAuth.js
│   │   ├── useDebounce.js
│   │   └── usePagination.js
│   ├── pages/            # Page components
│   │   ├── auth/
│   │   │   ├── Login.jsx
│   │   │   └── Register.jsx
│   │   ├── borrowings/
│   │   │   ├── BorrowingsList.jsx
│   │   │   ├── MyBorrowings.jsx
│   │   │   └── BorrowingForm.jsx
│   │   ├── items/
│   │   │   ├── ItemsList.jsx
│   │   │   ├── ItemForm.jsx
│   │   │   └── ItemDetail.jsx
│   │   └── Dashboard.jsx
│   ├── services/         # API service layer
│   │   ├── api.js
│   │   ├── authService.js
│   │   ├── itemService.js
│   │   └── borrowingService.js
│   ├── utils/            # Utility functions
│   │   ├── formatters.js
│   │   ├── validators.js
│   │   └── constants.js
│   ├── App.jsx           # Main app component
│   ├── main.jsx          # Entry point
│   └── routes.jsx        # Route definitions
├── .env.example          # Environment variables template
├── package.json          # Dependencies
├── vite.config.js        # Vite configuration
├── tailwind.config.js    # Tailwind CSS config
└── vitest.config.js      # Vitest test config
```

---

## 📝 Coding Standards

### PHP/Laravel Standards

**Follow PSR-12:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Item;
use App\Http\Requests\StoreItemRequest;
use App\Http\Resources\ItemResource;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    /**
     * Display a listing of items.
     */
    public function index(): JsonResponse
    {
        $items = Item::with('category')
            ->latest()
            ->paginate(15);
            
        return response()->json([
            'data' => ItemResource::collection($items),
            'meta' => [
                'current_page' => $items->currentPage(),
                'total' => $items->total(),
            ],
        ]);
    }
}
```

**Key Points:**
- Use type hints for parameters and return types
- Use DocBlocks for documentation
- Use camelCase for methods
- Use snake_case for database columns
- Use PascalCase for class names
- One class per file
- Blank line between methods

### JavaScript/React Standards

**Follow Airbnb Style Guide:**

```javascript
import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import { itemService } from '@/services/itemService';
import Button from '@/components/common/Button';

/**
 * ItemsList component displays paginated items
 */
const ItemsList = ({ categoryId, onItemSelect }) => {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchItems();
  }, [categoryId]);

  const fetchItems = async () => {
    try {
      setLoading(true);
      const response = await itemService.getItems({ categoryId });
      setItems(response.data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div className="items-list">
      {items.map(item => (
        <div key={item.id} onClick={() => onItemSelect(item)}>
          <h3>{item.name}</h3>
          <p>{item.description}</p>
        </div>
      ))}
    </div>
  );
};

ItemsList.propTypes = {
  categoryId: PropTypes.number,
  onItemSelect: PropTypes.func.isRequired,
};

ItemsList.defaultProps = {
  categoryId: null,
};

export default ItemsList;
```

**Key Points:**
- Use functional components with hooks
- Use PropTypes for type checking
- Use camelCase for variables and functions
- Use PascalCase for components
- Destructure props
- Use arrow functions
- Use template literals
- Use optional chaining (?.)
- Use async/await instead of .then()

### Naming Conventions

**Variables:**
```php
// PHP
$itemCount = 10;
$isActive = true;
$userList = [];

// JavaScript
const itemCount = 10;
const isActive = true;
const userList = [];
```

**Functions/Methods:**
```php
// PHP
public function getUserBorrowings() {}
public function approveBorrowing() {}

// JavaScript
const getUserBorrowings = () => {};
const approveBorrowing = () => {};
```

**Classes/Components:**
```php
// PHP
class BorrowingService {}
class ItemController {}

// JavaScript
const ItemsList = () => {};
const BorrowingForm = () => {};
```

**Files:**
```
// PHP
ItemController.php
BorrowingService.php
StoreItemRequest.php

// JavaScript
ItemsList.jsx
borrowingService.js
useAuth.js
```

---

## 🔧 Backend Development

### Creating a New Controller

```bash
# Create controller
php artisan make:controller Api/ExampleController --api

# Create with resource methods
php artisan make:controller Api/ExampleController --resource
```

**Example Controller:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Example;
use App\Http\Requests\StoreExampleRequest;
use App\Http\Requests\UpdateExampleRequest;
use App\Http\Resources\ExampleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ExampleController extends Controller
{
    /**
     * Display a listing of examples.
     */
    public function index(): JsonResponse
    {
        $examples = Example::query()
            ->when(request('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->paginate(15);

        return response()->json([
            'data' => ExampleResource::collection($examples),
            'meta' => [
                'current_page' => $examples->currentPage(),
                'per_page' => $examples->perPage(),
                'total' => $examples->total(),
            ],
        ]);
    }

    /**
     * Store a newly created example.
     */
    public function store(StoreExampleRequest $request): JsonResponse
    {
        $example = Example::create($request->validated());

        return response()->json(
            new ExampleResource($example),
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified example.
     */
    public function show(Example $example): JsonResponse
    {
        return response()->json(new ExampleResource($example));
    }

    /**
     * Update the specified example.
     */
    public function update(
        UpdateExampleRequest $request,
        Example $example
    ): JsonResponse {
        $example->update($request->validated());

        return response()->json(new ExampleResource($example));
    }

    /**
     * Remove the specified example.
     */
    public function destroy(Example $example): Response
    {
        $example->delete();

        return response()->noContent();
    }
}
```

### Creating Services

**Why use services?**
- Separate business logic from controllers
- Reusable code
- Easier to test
- Single Responsibility Principle

**Example Service:**

```php
<?php

namespace App\Services;

use App\Models\Borrowing;
use App\Models\Item;
use App\Jobs\SendBorrowingNotification;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BorrowingService
{
    /**
     * Create a new borrowing request.
     */
    public function createBorrowing(array $data): Borrowing
    {
        return DB::transaction(function () use ($data) {
            // Check item availability
            $item = Item::findOrFail($data['item_id']);
            
            if ($item->available_stock < $data['quantity']) {
                throw new \Exception('Insufficient stock');
            }

            // Create borrowing
            $borrowing = Borrowing::create([
                'code' => $this->generateCode(),
                'user_id' => auth()->id(),
                'item_id' => $data['item_id'],
                'quantity' => $data['quantity'],
                'borrow_date' => $data['borrow_date'],
                'due_date' => $data['due_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
            ]);

            // Send notification to admin
            SendBorrowingNotification::dispatch($borrowing);

            return $borrowing;
        });
    }

    /**
     * Approve borrowing request.
     */
    public function approveBorrowing(Borrowing $borrowing): bool
    {
        if ($borrowing->status !== 'pending') {
            throw new \Exception('Borrowing already processed');
        }

        return DB::transaction(function () use ($borrowing) {
            $borrowing->update([
                'status' => 'dipinjam',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Update item stock
            $borrowing->item->decrement('available_stock', $borrowing->quantity);

            return true;
        });
    }

    /**
     * Generate unique borrowing code.
     */
    private function generateCode(): string
    {
        $date = Carbon::now()->format('Ymd');
        $count = Borrowing::whereDate('created_at', today())->count() + 1;
        
        return sprintf('BRW-%s-%04d', $date, $count);
    }
}
```

### Creating Models

```bash
# Create model with migration
php artisan make:model Example -m

# Create model with migration, factory, and seeder
php artisan make:model Example -mfs
```

**Example Model:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Item extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'category_id',
        'stock',
        'available_stock',
        'image',
        'condition',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'stock' => 'integer',
        'available_stock' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the category that owns the item.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the borrowings for the item.
     */
    public function borrowings()
    {
        return $this->hasMany(Borrowing::class);
    }

    /**
     * Get active borrowings.
     */
    public function activeBorrowings()
    {
        return $this->borrowings()
            ->whereIn('status', ['pending', 'dipinjam']);
    }

    /**
     * Check if item is available.
     */
    public function isAvailable(): bool
    {
        return $this->available_stock > 0 && $this->condition === 'baik';
    }

    /**
     * Configure activity logging.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'stock', 'condition'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

### Validation with Form Requests

```bash
php artisan make:request StoreItemRequest
```

**Example Form Request:**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\StrongPassword;

class StoreItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'stock' => ['required', 'integer', 'min:0'],
            'condition' => ['required', 'in:baik,rusak,hilang'],
            'image' => ['nullable', 'image', 'max:2048'], // 2MB
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama barang',
            'category_id' => 'kategori',
            'stock' => 'stok',
            'condition' => 'kondisi',
            'image' => 'gambar',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama barang harus diisi',
            'category_id.required' => 'Kategori harus dipilih',
            'category_id.exists' => 'Kategori tidak valid',
            'stock.min' => 'Stok tidak boleh negatif',
            'image.max' => 'Ukuran gambar maksimal 2MB',
        ];
    }
}
```

---

## ⚛️ Frontend Development

### Creating Components

**Folder structure:**
```
src/components/
├── common/          # Reusable UI components
├── layout/          # Layout components
└── features/        # Feature-specific components
```

**Example Component:**

```jsx
import React, { useState } from 'react';
import PropTypes from 'prop-types';
import Button from '@/components/common/Button';
import Input from '@/components/common/Input';

/**
 * ItemForm component for creating/editing items
 */
const ItemForm = ({ item, onSubmit, onCancel }) => {
  const [formData, setFormData] = useState({
    name: item?.name || '',
    description: item?.description || '',
    category_id: item?.category_id || '',
    stock: item?.stock || 0,
    condition: item?.condition || 'baik',
  });

  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(false);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value,
    }));
    
    // Clear error for this field
    if (errors[name]) {
      setErrors(prev => ({
        ...prev,
        [name]: undefined,
      }));
    }
  };

  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.name.trim()) {
      newErrors.name = 'Nama barang harus diisi';
    }
    
    if (!formData.category_id) {
      newErrors.category_id = 'Kategori harus dipilih';
    }
    
    if (formData.stock < 0) {
      newErrors.stock = 'Stok tidak boleh negatif';
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }
    
    setLoading(true);
    try {
      await onSubmit(formData);
    } catch (error) {
      console.error('Submit error:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <Input
        label="Nama Barang"
        name="name"
        value={formData.name}
        onChange={handleChange}
        error={errors.name}
        required
      />
      
      <Input
        label="Deskripsi"
        name="description"
        type="textarea"
        value={formData.description}
        onChange={handleChange}
        rows={4}
      />
      
      <Input
        label="Stok"
        name="stock"
        type="number"
        value={formData.stock}
        onChange={handleChange}
        error={errors.stock}
        min="0"
        required
      />
      
      <div className="flex gap-2 justify-end">
        <Button
          type="button"
          variant="secondary"
          onClick={onCancel}
          disabled={loading}
        >
          Batal
        </Button>
        <Button
          type="submit"
          variant="primary"
          loading={loading}
        >
          {item ? 'Update' : 'Simpan'}
        </Button>
      </div>
    </form>
  );
};

ItemForm.propTypes = {
  item: PropTypes.shape({
    name: PropTypes.string,
    description: PropTypes.string,
    category_id: PropTypes.number,
    stock: PropTypes.number,
    condition: PropTypes.string,
  }),
  onSubmit: PropTypes.func.isRequired,
  onCancel: PropTypes.func.isRequired,
};

ItemForm.defaultProps = {
  item: null,
};

export default ItemForm;
```

### Creating Custom Hooks

```javascript
// src/hooks/usePagination.js
import { useState, useEffect } from 'react';

/**
 * Custom hook for handling pagination
 */
export const usePagination = (fetchFunction, dependencies = []) => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState({
    currentPage: 1,
    perPage: 15,
    total: 0,
    lastPage: 1,
  });

  useEffect(() => {
    fetchData();
  }, [pagination.currentPage, ...dependencies]);

  const fetchData = async () => {
    try {
      setLoading(true);
      const response = await fetchFunction({
        page: pagination.currentPage,
        per_page: pagination.perPage,
      });
      
      setData(response.data);
      setPagination(prev => ({
        ...prev,
        total: response.meta.total,
        lastPage: response.meta.last_page,
      }));
      
      setError(null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const goToPage = (page) => {
    setPagination(prev => ({
      ...prev,
      currentPage: page,
    }));
  };

  const nextPage = () => {
    if (pagination.currentPage < pagination.lastPage) {
      goToPage(pagination.currentPage + 1);
    }
  };

  const prevPage = () => {
    if (pagination.currentPage > 1) {
      goToPage(pagination.currentPage - 1);
    }
  };

  const refresh = () => {
    fetchData();
  };

  return {
    data,
    loading,
    error,
    pagination,
    goToPage,
    nextPage,
    prevPage,
    refresh,
  };
};
```

### API Service Layer

```javascript
// src/services/itemService.js
import api from './api';

export const itemService = {
  /**
   * Get all items with filters
   */
  getItems: async (params = {}) => {
    const response = await api.get('/items', { params });
    return response.data;
  },

  /**
   * Get single item by ID
   */
  getItem: async (id) => {
    const response = await api.get(`/items/${id}`);
    return response.data;
  },

  /**
   * Create new item
   */
  createItem: async (data) => {
    const formData = new FormData();
    Object.keys(data).forEach(key => {
      if (data[key] !== null && data[key] !== undefined) {
        formData.append(key, data[key]);
      }
    });
    
    const response = await api.post('/items', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  /**
   * Update item
   */
  updateItem: async (id, data) => {
    const response = await api.put(`/items/${id}`, data);
    return response.data;
  },

  /**
   * Delete item
   */
  deleteItem: async (id) => {
    await api.delete(`/items/${id}`);
  },

  /**
   * Search items
   */
  searchItems: async (query) => {
    const response = await api.get('/items/search-suggestions', {
      params: { q: query },
    });
    return response.data.suggestions;
  },
};
```

---

## 🗄️ Database Management

### Creating Migrations

```bash
# Create migration
php artisan make:migration create_examples_table

# Create migration for table modification
php artisan make:migration add_status_to_examples_table

# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Rollback all migrations
php artisan migrate:reset

# Refresh database
php artisan migrate:fresh
```

**Example Migration:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')
                ->constrained()
                ->onDelete('restrict');
            $table->integer('stock')->default(0);
            $table->integer('available_stock')->default(0);
            $table->string('image')->nullable();
            $table->enum('condition', ['baik', 'rusak', 'hilang'])
                ->default('baik');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['category_id', 'condition']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
```

### Creating Seeders

```bash
# Create seeder
php artisan make:seeder ExampleSeeder

# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=ExampleSeeder
```

**Example Seeder:**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Item;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::all();

        $items = [
            [
                'name' => 'Laptop Dell XPS 13',
                'description' => 'Laptop dengan spesifikasi tinggi',
                'stock' => 5,
                'condition' => 'baik',
            ],
            [
                'name' => 'Projector Epson',
                'description' => 'Projector untuk presentasi',
                'stock' => 3,
                'condition' => 'baik',
            ],
            // ... more items
        ];

        foreach ($items as $itemData) {
            $category = $categories->random();
            
            Item::create([
                'code' => 'ITM-' . now()->format('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'category_id' => $category->id,
                'name' => $itemData['name'],
                'description' => $itemData['description'],
                'stock' => $itemData['stock'],
                'available_stock' => $itemData['stock'],
                'condition' => $itemData['condition'],
            ]);
        }
    }
}
```

### Creating Factories

```bash
php artisan make:factory ExampleFactory --model=Example
```

**Example Factory:**

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => 'ITM-' . now()->format('Ymd') . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'category_id' => Category::factory(),
            'stock' => $this->faker->numberBetween(1, 20),
            'available_stock' => function (array $attributes) {
                return $attributes['stock'];
            },
            'condition' => $this->faker->randomElement(['baik', 'rusak']),
        ];
    }

    /**
     * Indicate that the item is damaged.
     */
    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition' => 'rusak',
        ]);
    }

    /**
     * Indicate that the item has low stock.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $this->faker->numberBetween(1, 3),
        ]);
    }
}
```

---

## 🧪 Testing

### Backend Testing (PHPUnit)

**Create test:**
```bash
# Feature test
php artisan make:test ItemControllerTest

# Unit test
php artisan make:test ItemServiceTest --unit

# Run tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test
php artisan test --filter=ItemControllerTest
```

**Example Feature Test:**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'user']);
    }

    public function test_can_list_items(): void
    {
        Item::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'code', 'stock'],
                ],
                'meta' => ['total', 'current_page'],
            ]);
    }

    public function test_admin_can_create_item(): void
    {
        $category = Category::factory()->create();
        
        $itemData = [
            'name' => 'Test Item',
            'category_id' => $category->id,
            'stock' => 5,
            'condition' => 'baik',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/items', $itemData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Test Item']);

        $this->assertDatabaseHas('items', $itemData);
    }

    public function test_user_cannot_create_item(): void
    {
        $category = Category::factory()->create();
        
        $itemData = [
            'name' => 'Test Item',
            'category_id' => $category->id,
            'stock' => 5,
            'condition' => 'baik',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/items', $itemData);

        $response->assertStatus(403);
    }

    public function test_cannot_delete_item_with_active_borrowings(): void
    {
        $item = Item::factory()
            ->hasBorrowings(1, ['status' => 'dipinjam'])
            ->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/items/{$item->id}");

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'Tidak dapat menghapus barang yang sedang dipinjam',
            ]);
    }
}
```

**Example Unit Test:**

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Item;
use App\Models\Borrowing;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_available_returns_true_when_stock_available(): void
    {
        $item = Item::factory()->create([
            'available_stock' => 5,
            'condition' => 'baik',
        ]);

        $this->assertTrue($item->isAvailable());
    }

    public function test_is_available_returns_false_when_no_stock(): void
    {
        $item = Item::factory()->create([
            'available_stock' => 0,
            'condition' => 'baik',
        ]);

        $this->assertFalse($item->isAvailable());
    }

    public function test_is_available_returns_false_when_damaged(): void
    {
        $item = Item::factory()->create([
            'available_stock' => 5,
            'condition' => 'rusak',
        ]);

        $this->assertFalse($item->isAvailable());
    }

    public function test_active_borrowings_relationship(): void
    {
        $item = Item::factory()->create();
        
        // Create active borrowing
        $activeBorrowing = Borrowing::factory()->create([
            'item_id' => $item->id,
            'status' => 'dipinjam',
        ]);
        
        // Create returned borrowing
        Borrowing::factory()->create([
            'item_id' => $item->id,
            'status' => 'dikembalikan',
        ]);

        $this->assertCount(1, $item->activeBorrowings);
        $this->assertTrue(
            $item->activeBorrowings->contains($activeBorrowing)
        );
    }
}
```

### Frontend Testing (Vitest)

**Run tests:**
```bash
cd frontend

# Run tests
npm test

# Run with coverage
npm run test:coverage

# Run in watch mode
npm run test:watch
```

**Example Component Test:**

```javascript
// src/components/common/__tests__/Button.test.jsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Button from '../Button';

describe('Button', () => {
  it('renders with text', () => {
    render(<Button>Click me</Button>);
    expect(screen.getByText('Click me')).toBeInTheDocument();
  });

  it('calls onClick when clicked', () => {
    const handleClick = vi.fn();
    render(<Button onClick={handleClick}>Click me</Button>);
    
    fireEvent.click(screen.getByText('Click me'));
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('is disabled when loading', () => {
    render(<Button loading>Click me</Button>);
    
    const button = screen.getByRole('button');
    expect(button).toBeDisabled();
  });

  it('applies variant classes correctly', () => {
    const { rerender } = render(<Button variant="primary">Primary</Button>);
    let button = screen.getByRole('button');
    expect(button).toHaveClass('bg-blue-600');

    rerender(<Button variant="secondary">Secondary</Button>);
    button = screen.getByRole('button');
    expect(button).toHaveClass('bg-gray-600');
  });
});
```

---

## 🐛 Debugging

### Laravel Telescope

**Install and setup:**
```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

**Access:** `http://localhost:8000/telescope`

**Features:**
- Request monitoring
- Query monitoring
- Job monitoring
- Exception tracking
- Log viewer
- Cache operations

### Debug Tools

**Laravel Debugbar:**
```bash
composer require barryvdh/laravel-debugbar --dev
```

**React DevTools:**
- Install Chrome/Firefox extension
- Inspect component tree
- View props and state
- Profile performance

### Logging

**Backend:**
```php
// Log levels
Log::emergency('System is down!');
Log::alert('Action required');
Log::critical('Critical condition');
Log::error('Error occurred');
Log::warning('Warning message');
Log::notice('Normal but significant');
Log::info('Informational message');
Log::debug('Debug information');

// Log with context
Log::info('User logged in', [
    'user_id' => $user->id,
    'ip' => request()->ip(),
]);

// Log to specific channel
Log::channel('slack')->error('Something went wrong');
```

**Frontend:**
```javascript
// Use console methods
console.log('Normal log');
console.info('Info message');
console.warn('Warning');
console.error('Error');
console.table(arrayOfObjects);

// Use debugger
function problematicFunction() {
  debugger; // Execution will pause here
  // ... rest of code
}
```

---

## 📝 Git Workflow

### Branch Naming Convention

```
feature/item-management
bugfix/login-error
hotfix/security-patch
refactor/service-layer
docs/api-documentation
test/borrowing-controller
```

### Commit Message Format

```
type(scope): subject

body (optional)

footer (optional)
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Formatting
- `refactor`: Code restructuring
- `test`: Adding tests
- `chore`: Maintenance

**Examples:**
```bash
git commit -m "feat(items): add bulk delete functionality"
git commit -m "fix(auth): resolve token expiration issue"
git commit -m "docs(api): update endpoint documentation"
git commit -m "test(borrowing): add integration tests"
```

### Workflow

```bash
# Create feature branch
git checkout -b feature/new-feature

# Make changes and commit
git add .
git commit -m "feat: add new feature"

# Push to remote
git push origin feature/new-feature

# Create pull request on GitHub
# After review and approval, merge to main
```

---

## ⚡ Performance Optimization

### Backend Optimization

**Query Optimization:**
```php
// Bad - N+1 problem
$items = Item::all();
foreach ($items as $item) {
    echo $item->category->name; // Queries for each item
}

// Good - Eager loading
$items = Item::with('category')->get();
foreach ($items as $item) {
    echo $item->category->name; // No additional queries
}

// Better - Select only needed columns
$items = Item::with('category:id,name')
    ->select('id', 'name', 'category_id')
    ->get();
```

**Caching:**
```php
use Illuminate\Support\Facades\Cache;

// Cache for 1 hour
$items = Cache::remember('items.all', 3600, function () {
    return Item::with('category')->get();
});

// Cache tags (with Redis)
Cache::tags(['items', 'categories'])->put('key', $value, 3600);

// Clear cache
Cache::forget('items.all');
Cache::tags(['items'])->flush();
```

**Queue Jobs:**
```php
// Dispatch to queue
SendEmailNotification::dispatch($user);

// Dispatch with delay
SendReminderEmail::dispatch($user)->delay(now()->addHours(24));

// Chain jobs
Bus::chain([
    new ProcessOrder($order),
    new SendInvoice($order),
    new UpdateInventory($order),
])->dispatch();
```

### Frontend Optimization

**Code Splitting:**
```javascript
// Lazy load routes
const ItemsList = React.lazy(() => import('./pages/items/ItemsList'));
const BorrowingsList = React.lazy(() => import('./pages/borrowings/BorrowingsList'));

// Use in routes
<Suspense fallback={<Loading />}>
  <Routes>
    <Route path="/items" element={<ItemsList />} />
    <Route path="/borrowings" element={<BorrowingsList />} />
  </Routes>
</Suspense>
```

**Memoization:**
```javascript
import { useMemo, useCallback } from 'react';

const ItemsList = ({ items, onItemClick }) => {
  // Memoize expensive calculations
  const sortedItems = useMemo(() => {
    return items.sort((a, b) => a.name.localeCompare(b.name));
  }, [items]);

  // Memoize callbacks
  const handleClick = useCallback((item) => {
    onItemClick(item);
  }, [onItemClick]);

  return (
    <div>
      {sortedItems.map(item => (
        <ItemCard key={item.id} item={item} onClick={handleClick} />
      ))}
    </div>
  );
};
```

---

## 🔒 Security Best Practices

### Backend Security

**Input Validation:**
```php
// Always validate user input
$request->validate([
    'email' => 'required|email',
    'password' => 'required|min:8',
]);

// Sanitize output
{{ $user->name }} // Escaped by default in Blade
{!! $html !!} // Unescaped - use carefully
```

**SQL Injection Prevention:**
```php
// Bad - vulnerable to SQL injection
DB::select("SELECT * FROM users WHERE email = '$email'");

// Good - use parameter binding
DB::select("SELECT * FROM users WHERE email = ?", [$email]);

// Best - use Eloquent
User::where('email', $email)->first();
```

**XSS Prevention:**
```php
// Sanitize HTML input
use HTMLPurifier;

$clean = HTMLPurifier::clean($dirtyHtml);
```

### Frontend Security

**XSS Prevention:**
```javascript
// Bad - vulnerable to XSS
element.innerHTML = userInput;

// Good - React escapes by default
<div>{userInput}</div>

// For HTML content, use DOMPurify
import DOMPurify from 'dompurify';
const clean = DOMPurify.sanitize(dirtyHTML);
```

**CSRF Protection:**
```javascript
// Laravel Sanctum automatically handles CSRF for SPA
// Ensure credentials are included in requests
axios.defaults.withCredentials = true;
```

---

## 🔧 Troubleshooting

### Common Backend Issues

**Issue: 500 Internal Server Error**
```bash
# Check logs
tail -f storage/logs/laravel.log

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check permissions
chmod -R 775 storage bootstrap/cache
```

**Issue: Database Connection Failed**
```bash
# Check .env database credentials
# Test connection
php artisan tinker
>>> DB::connection()->getPdo();
```

**Issue: Queue Jobs Not Processing**
```bash
# Check queue connection
php artisan queue:listen

# Clear failed jobs
php artisan queue:flush

# Restart queue workers
php artisan queue:restart
```

### Common Frontend Issues

**Issue: CORS Error**
```php
// config/cors.php
'paths' => ['api/*'],
'allowed_origins' => ['http://localhost:5173'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

**Issue: Build Fails**
```bash
# Clear node modules and reinstall
rm -rf node_modules package-lock.json
npm install

# Clear Vite cache
npm run clean
```

---

## 📚 Additional Resources

- **Laravel Documentation:** https://laravel.com/docs
- **React Documentation:** https://react.dev
- **Tailwind CSS:** https://tailwindcss.com/docs
- **Vite Guide:** https://vitejs.dev/guide/
- **PHPUnit:** https://phpunit.de/documentation.html
- **Vitest:** https://vitest.dev

---

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`php artisan test` && `npm test`)
5. Commit your changes (`git commit -m 'feat: add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

---

**Happy Coding! 🚀**

For questions or support, contact: dev-team@inventaris.example.com
