// Type definitions for API responses

export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'staff' | 'user';
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface Category {
  id: number;
  name: string;
  description: string | null;
  created_at: string;
  updated_at: string;
  items_count?: number;
}

export interface Item {
  id: number;
  name: string;
  code: string;
  description: string | null;
  category_id: number;
  category?: Category;
  stock: number;
  available_stock: number;
  condition: 'baik' | 'rusak';
  location: string | null;
  image: string | null;
  created_at: string;
  updated_at: string;
}

export interface Borrowing {
  id: number;
  user_id: number;
  user?: User;
  item_id: number;
  item?: Item;
  quantity: number;
  borrow_date: string;
  due_date: string;
  return_date: string | null;
  status: 'pending' | 'approved' | 'rejected' | 'returned' | 'overdue';
  notes: string | null;
  admin_notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface ApiResponse<T> {
  data: T;
  message?: string;
  status?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

export interface ValidationError {
  message: string;
  errors: Record<string, string[]>;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface AuthResponse {
  user: User;
  token: string;
}

export interface ItemFormData {
  name: string;
  code: string;
  description?: string;
  category_id: number;
  stock: number;
  condition: 'baik' | 'rusak';
  location?: string;
  image?: File | string;
}

export interface BorrowingFormData {
  item_id: number;
  quantity: number;
  borrow_date: string;
  due_date: string;
  notes?: string;
}

export interface ReportFilter {
  start_date?: string;
  end_date?: string;
  status?: string;
  category_id?: number;
  user_id?: number;
}

export interface DashboardStats {
  total_items: number;
  available_items: number;
  borrowed_items: number;
  overdue_borrowings: number;
  pending_borrowings: number;
  total_users: number;
  total_categories: number;
  recent_borrowings: Borrowing[];
}
