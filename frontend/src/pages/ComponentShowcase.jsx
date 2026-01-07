import { useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    Card,
    Empty,
    Input,
    Loading,
    Modal,
    Pagination,
    SearchBar,
    Select
} from '../components/common';
import { useNotification } from '../contexts';

function ComponentShowcase() {
  const [modalOpen, setModalOpen] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const { showSuccess, showError, showWarning, showInfo } = useNotification();

  const categoryOptions = [
    { value: '1', label: 'Elektronik' },
    { value: '2', label: 'Furniture' },
    { value: '3', label: 'Alat Tulis' },
  ];

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">Component Showcase</h1>

      {/* Buttons */}
      <Card title="Buttons" subtitle="Various button variants and sizes">
        <div className="flex flex-wrap gap-4">
          <Button variant="primary">Primary</Button>
          <Button variant="secondary">Secondary</Button>
          <Button variant="success">Success</Button>
          <Button variant="danger">Danger</Button>
          <Button variant="warning">Warning</Button>
          <Button variant="outline">Outline</Button>
          <Button variant="primary" loading>Loading</Button>
          <Button variant="primary" disabled>Disabled</Button>
        </div>
        <div className="flex flex-wrap gap-4 mt-4">
          <Button variant="primary" size="sm">Small</Button>
          <Button variant="primary" size="md">Medium</Button>
          <Button variant="primary" size="lg">Large</Button>
        </div>
      </Card>

      {/* Badges */}
      <Card title="Badges" subtitle="Status indicators">
        <div className="flex flex-wrap gap-4">
          <Badge variant="default">Default</Badge>
          <Badge variant="primary">Primary</Badge>
          <Badge variant="success">Success</Badge>
          <Badge variant="danger">Danger</Badge>
          <Badge variant="warning">Warning</Badge>
          <Badge variant="info">Info</Badge>
        </div>
        <div className="flex flex-wrap gap-4 mt-4">
          <Badge size="sm">Small</Badge>
          <Badge size="md">Medium</Badge>
          <Badge size="lg">Large</Badge>
        </div>
      </Card>

      {/* Alerts */}
      <Card title="Alerts" subtitle="Alert messages">
        <div className="space-y-4">
          <Alert variant="success" title="Success!" message="Your action was successful." />
          <Alert variant="error" title="Error!" message="Something went wrong." />
          <Alert variant="warning" title="Warning!" message="Please check your input." />
          <Alert variant="info" message="This is an informational message." />
        </div>
      </Card>

      {/* Loading */}
      <Card title="Loading States">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h4 className="text-sm font-medium mb-2">Spinner</h4>
            <Loading variant="spinner" size="md" text="Loading..." />
          </div>
          <div>
            <h4 className="text-sm font-medium mb-2">Skeleton</h4>
            <Loading variant="skeleton" />
          </div>
        </div>
      </Card>

      {/* Empty State */}
      <Card title="Empty State">
        <Empty 
          title="No Items Found" 
          description="Try adjusting your search or filters"
          action={<Button variant="primary">Add New Item</Button>}
        />
      </Card>

      {/* Form Inputs */}
      <Card title="Form Inputs" subtitle="Input and Select components">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Input 
            label="Name" 
            placeholder="Enter your name"
            required
          />
          <Input 
            label="Email" 
            type="email"
            placeholder="your@email.com"
            error="This field is required"
          />
          <Select 
            label="Category"
            options={categoryOptions}
            placeholder="Select category"
            required
          />
          <SearchBar 
            placeholder="Search items..."
            onSearch={(value) => console.log('Search:', value)}
          />
        </div>
      </Card>

      {/* Pagination */}
      <Card title="Pagination">
        <Pagination 
          currentPage={currentPage}
          totalPages={10}
          onPageChange={setCurrentPage}
        />
      </Card>

      {/* Modal */}
      <Card title="Modal" subtitle="Dialog component">
        <Button variant="primary" onClick={() => setModalOpen(true)}>
          Open Modal
        </Button>
        <Modal
          isOpen={modalOpen}
          onClose={() => setModalOpen(false)}
          title="Example Modal"
          footer={
            <>
              <Button variant="outline" onClick={() => setModalOpen(false)}>
                Cancel
              </Button>
              <Button variant="primary" onClick={() => setModalOpen(false)}>
                Save
              </Button>
            </>
          }
        >
          <p className="text-gray-600">
            This is a modal dialog. You can use it for forms, confirmations, or any content.
          </p>
        </Modal>
      </Card>

      {/* Notifications */}
      <Card title="Notifications" subtitle="Toast messages">
        <div className="flex flex-wrap gap-4">
          <Button variant="success" onClick={() => showSuccess('Success message!')}>
            Show Success
          </Button>
          <Button variant="danger" onClick={() => showError('Error message!')}>
            Show Error
          </Button>
          <Button variant="warning" onClick={() => showWarning('Warning message!')}>
            Show Warning
          </Button>
          <Button variant="primary" onClick={() => showInfo('Info message!')}>
            Show Info
          </Button>
        </div>
      </Card>
    </div>
  );
}

export default ComponentShowcase;
