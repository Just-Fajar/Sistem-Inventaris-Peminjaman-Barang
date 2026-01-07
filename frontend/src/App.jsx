import { lazy, Suspense } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import Loading from './components/common/Loading';
import ErrorBoundary from './components/ErrorBoundary';
import OfflineBanner from './components/common/OfflineBanner';
import { authService } from './services/authService';

// Eager load Layout and Login (critical paths)
import Layout from './components/Layout';
import Login from './pages/Login';

// Lazy load all other pages for code splitting
const Dashboard = lazy(() => import('./pages/Dashboard'));
const ItemList = lazy(() => import('./pages/ItemList'));
const ItemForm = lazy(() => import('./pages/ItemForm'));
const ItemDetail = lazy(() => import('./pages/ItemDetail'));
const CategoryList = lazy(() => import('./pages/CategoryList'));
const BorrowingList = lazy(() => import('./pages/BorrowingList'));
const BorrowingForm = lazy(() => import('./pages/BorrowingForm'));
const BorrowingDetail = lazy(() => import('./pages/BorrowingDetail'));
const ReturnForm = lazy(() => import('./pages/ReturnForm'));
const Reports = lazy(() => import('./pages/Reports'));
const UserList = lazy(() => import('./pages/UserList'));
const UserForm = lazy(() => import('./pages/UserForm'));
const Profile = lazy(() => import('./pages/Profile'));

// Protected Route Component
function ProtectedRoute({ children }) {
  if (!authService.isAuthenticated()) {
    return <Navigate to="/login" replace />;
  }
  return children;
}

// Suspense wrapper for lazy loaded routes
function SuspenseWrapper({ children }) {
  return (
    <Suspense fallback={<Loading />}>
      {children}
    </Suspense>
  );
}

function App() {
  return (
    <ErrorBoundary>
      <OfflineBanner />
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />
        
        {/* Protected Routes with Layout */}
        <Route
          path="/"
          element={
            <ProtectedRoute>
              <Layout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route path="dashboard" element={<SuspenseWrapper><Dashboard /></SuspenseWrapper>} />
          
          {/* Items Routes */}
          <Route path="items" element={<SuspenseWrapper><ItemList /></SuspenseWrapper>} />
          <Route path="items/create" element={<SuspenseWrapper><ItemForm /></SuspenseWrapper>} />
          <Route path="items/:id" element={<SuspenseWrapper><ItemDetail /></SuspenseWrapper>} />
          <Route path="items/:id/edit" element={<SuspenseWrapper><ItemForm /></SuspenseWrapper>} />
          
          {/* Categories Routes */}
          <Route path="categories" element={<SuspenseWrapper><CategoryList /></SuspenseWrapper>} />
          
          {/* Borrowings Routes */}
          <Route path="borrowings" element={<SuspenseWrapper><BorrowingList /></SuspenseWrapper>} />
          <Route path="borrowings/create" element={<SuspenseWrapper><BorrowingForm /></SuspenseWrapper>} />
          <Route path="borrowings/:id" element={<SuspenseWrapper><BorrowingDetail /></SuspenseWrapper>} />
          <Route path="borrowings/:id/return" element={<SuspenseWrapper><ReturnForm /></SuspenseWrapper>} />
          
          {/* Reports Routes */}
          <Route path="reports" element={<SuspenseWrapper><Reports /></SuspenseWrapper>} />
          
          {/* Users Routes (Admin Only) */}
          <Route path="users" element={<SuspenseWrapper><UserList /></SuspenseWrapper>} />
          <Route path="users/create" element={<SuspenseWrapper><UserForm /></SuspenseWrapper>} />
          <Route path="users/:id/edit" element={<SuspenseWrapper><UserForm /></SuspenseWrapper>} />
          
          {/* Profile Routes */}
          <Route path="profile" element={<SuspenseWrapper><Profile /></SuspenseWrapper>} />
        </Route>
      </Routes>
    </BrowserRouter>
    </ErrorBoundary>
  );
}

export default App;
