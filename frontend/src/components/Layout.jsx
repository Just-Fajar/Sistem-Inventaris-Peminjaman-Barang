import { Outlet } from 'react-router-dom';
import Header from './Header';
import Sidebar from './Sidebar';

function Layout() {
  return (
    <div className="flex h-screen bg-gray-100 dark:bg-gray-950 transition-colors">
      {/* Sidebar */}
      <Sidebar />

      {/* Main Content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Header */}
        <Header />

        {/* Page Content */}
        <main className="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-950 text-gray-900 dark:text-gray-100 transition-colors">
          <div className="container mx-auto px-6 py-8">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}

export default Layout;
