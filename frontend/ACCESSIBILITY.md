# Accessibility (a11y) Guidelines

## Overview
This project aims to be accessible to all users, including those with disabilities. We follow WCAG 2.1 Level AA guidelines.

## Automated Testing
- **ESLint Plugin**: `eslint-plugin-jsx-a11y` configured in eslint.config.js
- **Browser Extension**: Use axe DevTools for Chrome/Firefox
- **Lighthouse**: Run accessibility audits in Chrome DevTools

## Manual Testing Checklist

### Keyboard Navigation
- [ ] All interactive elements accessible via Tab key
- [ ] Focus indicators visible on all interactive elements
- [ ] Tab order follows logical reading order
- [ ] No keyboard traps
- [ ] Skip navigation links provided

### Screen Reader Support
- [ ] Proper heading hierarchy (h1, h2, h3, etc.)
- [ ] Alt text for all images
- [ ] ARIA labels where needed
- [ ] Form labels associated with inputs
- [ ] Error messages announced
- [ ] Loading states announced

### Visual Design
- [ ] Color contrast ratio at least 4.5:1 for text
- [ ] Color contrast ratio at least 3:1 for UI components
- [ ] Information not conveyed by color alone
- [ ] Text resizable up to 200% without loss of content
- [ ] Focus indicators have sufficient contrast

### Forms
- [ ] All form inputs have associated labels
- [ ] Required fields indicated
- [ ] Error messages clear and helpful
- [ ] Validation errors associated with fields
- [ ] Success messages announced

### Dynamic Content
- [ ] Loading states communicated
- [ ] Error states communicated
- [ ] Success messages communicated
- [ ] Live regions for dynamic updates

## Tools

### Browser Extensions
- **axe DevTools**: https://www.deque.com/axe/devtools/
- **WAVE**: https://wave.webaim.org/extension/
- **Lighthouse**: Built into Chrome DevTools

### Screen Readers
- **NVDA** (Windows, Free): https://www.nvaccess.org/
- **JAWS** (Windows, Paid): https://www.freedomscientific.com/products/software/jaws/
- **VoiceOver** (Mac/iOS, Built-in)
- **TalkBack** (Android, Built-in)

## Running Tests

```bash
# Run ESLint with accessibility rules
npm run lint

# Check for accessibility violations
# 1. Install axe DevTools extension
# 2. Open DevTools > axe DevTools tab
# 3. Click "Scan ALL of my page"

# Run Lighthouse audit
# 1. Open Chrome DevTools
# 2. Go to Lighthouse tab
# 3. Select "Accessibility" category
# 4. Click "Generate report"
```

## Common Issues & Solutions

### Missing Alt Text
```jsx
// ❌ Bad
<img src="logo.png" />

// ✅ Good
<img src="logo.png" alt="Company Logo" />

// ✅ Decorative images
<img src="decoration.png" alt="" role="presentation" />
```

### Form Labels
```jsx
// ❌ Bad
<input type="text" placeholder="Name" />

// ✅ Good
<label htmlFor="name">Name</label>
<input id="name" type="text" />

// ✅ Alternative with aria-label
<input type="text" aria-label="Name" />
```

### Button Accessibility
```jsx
// ❌ Bad - div used as button
<div onClick={handleClick}>Click me</div>

// ✅ Good
<button onClick={handleClick}>Click me</button>

// ✅ Icon button with label
<button onClick={handleClose} aria-label="Close">
  <CloseIcon />
</button>
```

### Focus Management
```jsx
// ✅ Visible focus indicator
button:focus {
  outline: 2px solid blue;
  outline-offset: 2px;
}

// ✅ Skip focus on non-interactive elements
<div tabIndex={-1}>This won't receive focus</div>
```

### ARIA Labels
```jsx
// ✅ Loading state
<div role="status" aria-live="polite">
  {loading && <span>Loading...</span>}
</div>

// ✅ Error message
<div role="alert" aria-live="assertive">
  {error && <span>{error}</span>}
</div>
```

## Resources

- **WCAG Guidelines**: https://www.w3.org/WAI/WCAG21/quickref/
- **A11y Project**: https://www.a11yproject.com/
- **MDN Accessibility**: https://developer.mozilla.org/en-US/docs/Web/Accessibility
- **React Accessibility**: https://react.dev/learn/accessibility

## Reporting Issues

If you find an accessibility issue:
1. Check if it's caught by ESLint or axe DevTools
2. Document the issue with screenshots
3. Test with a screen reader if possible
4. Create an issue with [a11y] tag
5. Suggest a solution if you can

## Continuous Improvement

- Run accessibility audits regularly
- Include accessibility in code reviews
- Test with real screen readers
- Get feedback from users with disabilities
- Stay updated with WCAG guidelines
