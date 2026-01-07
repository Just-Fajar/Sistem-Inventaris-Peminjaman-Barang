import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import Input from '../../components/common/Input'

describe('Input Component', () => {
  it('renders input field', () => {
    render(<Input placeholder="Enter text" />)
    expect(screen.getByPlaceholderText('Enter text')).toBeInTheDocument()
  })

  it('renders with label', () => {
    render(<Input label="Username" />)
    expect(screen.getByText('Username')).toBeInTheDocument()
    expect(screen.getByRole('textbox')).toBeInTheDocument()
  })

  it('shows required asterisk when required', () => {
    render(<Input label="Email" required />)
    expect(screen.getByText('*')).toBeInTheDocument()
  })

  it('displays error message when error prop is provided', () => {
    render(<Input label="Email" error="Email is required" />)
    expect(screen.getByText('Email is required')).toBeInTheDocument()
  })

  it('applies error styling when error exists', () => {
    render(<Input error="Invalid input" />)
    const input = screen.getByRole('textbox')
    expect(input).toHaveClass('border-red-300')
  })

  it('handles user input', async () => {
    const user = userEvent.setup()
    render(<Input placeholder="Type here" />)
    
    const input = screen.getByPlaceholderText('Type here')
    await user.type(input, 'Hello World')
    
    expect(input).toHaveValue('Hello World')
  })

  it('disables input when disabled prop is true', () => {
    render(<Input disabled placeholder="Disabled" />)
    expect(screen.getByPlaceholderText('Disabled')).toBeDisabled()
  })

  it('applies disabled styling', () => {
    render(<Input disabled />)
    expect(screen.getByRole('textbox')).toHaveClass('bg-gray-100 cursor-not-allowed')
  })

  it('accepts different input types', () => {
    const { rerender } = render(<Input type="email" />)
    expect(screen.getByRole('textbox')).toHaveAttribute('type', 'email')

    rerender(<Input type="password" />)
    const passwordInput = document.querySelector('input[type="password"]')
    expect(passwordInput).toBeInTheDocument()
  })

  it('forwards ref correctly', () => {
    const ref = vi.fn()
    render(<Input ref={ref} />)
    expect(ref).toHaveBeenCalled()
  })

  it('sets aria attributes correctly when error exists', () => {
    render(<Input id="test-input" error="Error message" />)
    const input = screen.getByRole('textbox')
    
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toHaveAttribute('aria-describedby', 'test-input-error')
  })

  it('applies custom className', () => {
    render(<Input className="custom-wrapper" />)
    const wrapper = screen.getByRole('textbox').parentElement
    expect(wrapper).toHaveClass('custom-wrapper')
  })

  it('passes additional props to input element', () => {
    render(<Input maxLength={10} data-testid="custom-input" />)
    const input = screen.getByTestId('custom-input')
    
    expect(input).toHaveAttribute('maxLength', '10')
  })
})
