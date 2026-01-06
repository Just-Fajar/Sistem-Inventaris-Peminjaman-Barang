import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import Modal from '../../components/common/Modal'

describe('Modal Component', () => {
  beforeEach(() => {
    // Reset body overflow before each test
    document.body.style.overflow = 'unset'
  })

  afterEach(() => {
    // Cleanup after each test
    document.body.style.overflow = 'unset'
  })

  it('does not render when isOpen is false', () => {
    render(<Modal isOpen={false} title="Test Modal">Content</Modal>)
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('renders when isOpen is true', () => {
    render(<Modal isOpen={true} title="Test Modal">Content</Modal>)
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('displays the title', () => {
    render(<Modal isOpen={true} title="My Modal">Content</Modal>)
    expect(screen.getByText('My Modal')).toBeInTheDocument()
  })

  it('displays the children content', () => {
    render(<Modal isOpen={true} title="Test">Hello World</Modal>)
    expect(screen.getByText('Hello World')).toBeInTheDocument()
  })

  it('calls onClose when close button is clicked', async () => {
    const handleClose = vi.fn()
    const user = userEvent.setup()
    
    render(<Modal isOpen={true} title="Test" onClose={handleClose}>Content</Modal>)
    
    const closeButton = screen.getByLabelText('Close modal')
    await user.click(closeButton)
    
    expect(handleClose).toHaveBeenCalledTimes(1)
  })

  it('calls onClose when overlay is clicked', async () => {
    const handleClose = vi.fn()
    const user = userEvent.setup()
    
    render(<Modal isOpen={true} title="Test" onClose={handleClose}>Content</Modal>)
    
    const overlay = document.querySelector('.fixed.inset-0.bg-gray-500')
    await user.click(overlay)
    
    expect(handleClose).toHaveBeenCalledTimes(1)
  })

  it('calls onClose when Escape key is pressed', async () => {
    const handleClose = vi.fn()
    const user = userEvent.setup()
    
    render(<Modal isOpen={true} title="Test" onClose={handleClose}>Content</Modal>)
    
    await user.keyboard('{Escape}')
    
    expect(handleClose).toHaveBeenCalledTimes(1)
  })

  it('applies correct size classes', () => {
    const { rerender } = render(
      <Modal isOpen={true} title="Test" size="sm">Content</Modal>
    )
    expect(document.querySelector('.max-w-md')).toBeInTheDocument()

    rerender(<Modal isOpen={true} title="Test" size="lg">Content</Modal>)
    expect(document.querySelector('.max-w-2xl')).toBeInTheDocument()

    rerender(<Modal isOpen={true} title="Test" size="xl">Content</Modal>)
    expect(document.querySelector('.max-w-4xl')).toBeInTheDocument()
  })

  it('renders footer when provided', () => {
    const footer = (
      <>
        <button>Cancel</button>
        <button>Save</button>
      </>
    )
    
    render(
      <Modal isOpen={true} title="Test" footer={footer}>
        Content
      </Modal>
    )
    
    expect(screen.getByText('Cancel')).toBeInTheDocument()
    expect(screen.getByText('Save')).toBeInTheDocument()
  })

  it('locks body scroll when opened', () => {
    const { rerender } = render(
      <Modal isOpen={true} title="Test">Content</Modal>
    )
    
    expect(document.body.style.overflow).toBe('hidden')

    rerender(<Modal isOpen={false} title="Test">Content</Modal>)
    expect(document.body.style.overflow).toBe('unset')
  })

  it('has correct aria attributes', () => {
    render(<Modal isOpen={true} title="Test Modal">Content</Modal>)
    const dialog = screen.getByRole('dialog')
    
    expect(dialog).toHaveAttribute('aria-modal', 'true')
    expect(dialog).toHaveAttribute('aria-labelledby', 'modal-title')
  })

  it('does not call onClose when content is clicked', async () => {
    const handleClose = vi.fn()
    const user = userEvent.setup()
    
    render(<Modal isOpen={true} title="Test" onClose={handleClose}>Content</Modal>)
    
    const content = screen.getByText('Content')
    await user.click(content)
    
    expect(handleClose).not.toHaveBeenCalled()
  })
})
