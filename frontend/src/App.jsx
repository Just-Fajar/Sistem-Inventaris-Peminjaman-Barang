import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import Layout from './components/Layout';
import BorrowingDetail from './pages/BorrowingDetail';
import BorrowingForm from './pages/BorrowingForm';
import BorrowingList from './pages/BorrowingList';
import CategoryList from './pages/CategoryList';
import Dashboard from './pages/Dashboard';
import ItemDetail from './pages/ItemDetail';
import ItemForm from './pages/ItemForm';
import ItemList from './pages/ItemList';
import Login from './pages/Login';
import Profile from './pages/Profile';
import Reports from './pages/Reports';
import ReturnForm from './pages/ReturnForm';
import UserForm from './pages/UserForm';
import UserList from './pages/UserList';
import { authService } from './services/authService';

// Protected Route Component
function ProtectedRoute({ children }) {
  if (!authService.isAuthenticated()) {
    return <Navigate to="/login" replace />;
  }
  return children;
}

function App() {
  return (
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
          <Route path="dashboard" element={<Dashboard />} />
          
          {/* Items Routes */}
          <Route path="items" element={<ItemList />} />
          <Route path="items/create" element={<ItemForm />} />
          <Route path="items/:id" element={<ItemDetail />} />
          <Route path="items/:id/edit" element={<ItemForm />} />
          
          {/* Categories Routes */}
          <Route path="categories" element={<CategoryList />} />
          
          {/* Borrowings Routes */}
          <Route path="borrowings" element={<BorrowingList />} />
          <Route path="borrowings/create" element={<BorrowingForm />} />
          <Route path="borrowings/:id" element={<BorrowingDetail />} />
          <Route path="borrowings/:id/return" element={<ReturnForm />} />
          
          {/* Reports Routes */}
          <Route path="reports" element={<Reports />} />
          
          {/* Users Routes (Admin Only) */}
          <Route path="users" element={<UserList />} />
          <Route path="users/create" element={<UserForm />} />
          <Route path="users/:id/edit" element={<UserForm />} />
          
          {/* Profile Routes */}
          <Route path="profile" element={<Profile />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;

