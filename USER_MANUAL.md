# 📖 User Manual

**Sistem Inventaris & Peminjaman Barang**  
**Version:** 1.0.0  
**Last Updated:** 7 Januari 2026

---

## 📋 Table of Contents

1. [Getting Started](#getting-started)
2. [Login & Authentication](#login--authentication)
3. [Dashboard Overview](#dashboard-overview)
4. [Item Management](#item-management)
5. [Category Management](#category-management)
6. [Borrowing Workflow](#borrowing-workflow)
7. [Reports & Analytics](#reports--analytics)
8. [Notifications](#notifications)
9. [Profile Management](#profile-management)
10. [User Management (Admin)](#user-management-admin)
11. [Activity Logs (Admin)](#activity-logs-admin)
12. [FAQ](#faq)
13. [Troubleshooting](#troubleshooting)

---

## 🚀 Getting Started

### Accessing the Application

1. Open your web browser (Chrome, Firefox, Safari, or Edge)
2. Navigate to: `https://inventaris.example.com`
3. You will see the login page

### System Requirements

**Browser Requirements:**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

**Internet Connection:**
- Minimum: 1 Mbps
- Recommended: 5 Mbps or higher

---

## 🔐 Login & Authentication

### First Time Login

**For Admin:**
- Email: `admin@example.com`
- Password: `password`

**For Regular User:**
- Email: `user@example.com`
- Password: `password`

> ⚠️ **Important:** Change your password immediately after first login!

### How to Login

1. Enter your **email address** in the Email field
2. Enter your **password** in the Password field
3. Click **"Login"** button
4. You will be redirected to the Dashboard

![Login Page Screenshot](./docs/images/login-page.png)

### Forgot Password

1. Click **"Forgot Password?"** link on the login page
2. Enter your registered email address
3. Check your email for reset password link
4. Click the link and enter your new password
5. Login with your new password

### Logout

1. Click your **profile icon** in the top-right corner
2. Select **"Logout"** from the dropdown menu
3. You will be redirected to the login page

---

## 📊 Dashboard Overview

After successful login, you will see the Dashboard with:

### Statistics Cards (Top Section)

**For All Users:**
- **Total Items** - Total available items in inventory
- **Active Borrowings** - Currently borrowed items
- **Pending Requests** - Awaiting approval (Admin only)

**For Admin:**
- **Total Users** - Number of registered users
- **Overdue Items** - Items past due date
- **Monthly Borrowings** - This month's borrowing count

![Dashboard Screenshot](./docs/images/dashboard.png)

### Recent Activities (Middle Section)

Shows latest borrowing activities with:
- Borrowing code
- User name
- Item name
- Status (Pending, Approved, Borrowed, Returned)
- Dates

### Popular Items (Bottom Section)

Displays most frequently borrowed items with:
- Item name
- Category
- Number of borrowings

---

## 📦 Item Management

### Viewing Items

1. Click **"Items"** in the sidebar menu
2. You will see a list of all items with:
   - Item image
   - Item code
   - Item name
   - Category
   - Stock (Total / Available)
   - Condition
   - Actions (View, Edit, Delete)

![Items List Screenshot](./docs/images/items-list.png)

### Searching Items

**Search by name or code:**
1. Type in the **search box** at the top
2. Results update automatically
3. Clear search to show all items

**Filter by category:**
1. Click **"Category"** dropdown
2. Select a category
3. Items will be filtered

**Filter by condition:**
1. Click **"Condition"** dropdown
2. Select: Good (Baik), Damaged (Rusak), or Lost (Hilang)

### Adding New Item (Admin Only)

1. Click **"+ Add Item"** button
2. Fill in the form:
   - **Item Name** (required) - e.g., "Laptop Dell XPS 13"
   - **Category** (required) - Select from dropdown
   - **Description** (optional) - Item details
   - **Stock** (required) - Number of items (e.g., 5)
   - **Condition** (required) - Select: Good/Damaged/Lost
   - **Image** (optional) - Upload item photo (max 2MB)
3. Click **"Save"** button
4. Success notification will appear

![Add Item Form](./docs/images/add-item.png)

**Image Guidelines:**
- Format: JPG, PNG, or GIF
- Maximum size: 2MB
- Recommended dimensions: 800x600px

### Viewing Item Details

1. Click **"View"** icon (👁️) on any item
2. You will see:
   - Full item information
   - Current stock status
   - Active borrowings
   - Borrowing history
3. Click **"Back"** to return to items list

### Editing Item (Admin Only)

1. Click **"Edit"** icon (✏️) on any item
2. Update the information
3. Click **"Save Changes"**
4. Success notification will appear

### Deleting Item (Admin Only)

1. Click **"Delete"** icon (🗑️) on any item
2. Confirm deletion dialog will appear
3. Click **"Yes, Delete"** to confirm
4. Item will be deleted

> ⚠️ **Warning:** Items with active borrowings cannot be deleted!

### Bulk Delete (Admin Only)

1. Check the boxes next to items you want to delete
2. Click **"Delete Selected"** button
3. Confirm deletion
4. Selected items will be deleted

---

## 📂 Category Management

### Viewing Categories

1. Click **"Categories"** in the sidebar
2. See list of all categories with:
   - Category name
   - Description
   - Number of items in category
   - Actions

### Adding New Category (Admin Only)

1. Click **"+ Add Category"** button
2. Fill in:
   - **Category Name** (required) - e.g., "Elektronik"
   - **Description** (optional) - Category details
3. Click **"Save"**

### Editing Category (Admin Only)

1. Click **"Edit"** on any category
2. Update the information
3. Click **"Save Changes"**

### Deleting Category (Admin Only)

1. Click **"Delete"** on any category
2. Confirm deletion

> ⚠️ **Warning:** Categories with existing items cannot be deleted!

---

## 🔄 Borrowing Workflow

### For Regular Users

#### Step 1: Request Borrowing

1. Go to **"Items"** menu
2. Find the item you want to borrow
3. Click **"Borrow"** button
4. Fill in the borrowing form:
   - **Quantity** (required) - How many units you need
   - **Borrow Date** (required) - When you want to borrow
   - **Due Date** (required) - When you will return
   - **Notes** (optional) - Purpose of borrowing
5. Click **"Submit Request"**
6. Wait for admin approval

![Borrowing Form](./docs/images/borrow-form.png)

**Validation Rules:**
- Quantity cannot exceed available stock
- Borrow date must be today or future date
- Due date must be after borrow date
- Maximum borrowing period: 30 days

#### Step 2: Check Request Status

1. Go to **"My Borrowings"** menu
2. See your borrowing requests with statuses:
   - **Pending** ⏳ - Waiting for approval
   - **Approved** ✅ - Approved by admin
   - **Borrowed** 📦 - Currently in your possession
   - **Returned** ✓ - Successfully returned
   - **Overdue** ⚠️ - Past due date
   - **Rejected** ✗ - Request denied

![My Borrowings](./docs/images/my-borrowings.png)

#### Step 3: Return Item

When you're done using the item:
1. Return the physical item to admin
2. Admin will mark it as returned in the system
3. You will receive a notification

### For Admin Users

#### Approve/Reject Borrowing Request

1. Go to **"Borrowings"** menu
2. Find pending requests (marked with 🟡)
3. Click **"View"** to see details
4. Review the request:
   - User information
   - Item details
   - Borrowing period
   - Purpose/notes
5. Click:
   - **"Approve"** ✅ - To allow borrowing
   - **"Reject"** ❌ - To deny request
6. Add rejection reason if rejecting
7. User will receive email notification

![Approve Borrowing](./docs/images/approve-borrowing.png)

#### Mark as Returned

When user returns the item:
1. Go to **"Borrowings"** menu
2. Find the active borrowing
3. Click **"Mark as Returned"**
4. Optionally enter actual return date
5. Click **"Confirm Return"**
6. Stock will be updated automatically

#### Extend Borrowing Period

If user needs more time:
1. Find the borrowing record
2. Click **"Extend"**
3. Enter new due date
4. Click **"Save"**
5. User will be notified

---

## 📈 Reports & Analytics

### Borrowings Report (Admin Only)

1. Go to **"Reports"** > **"Borrowings Report"**
2. Set filters:
   - **Date Range** - From and To dates
   - **Status** - All, Borrowed, Returned, Overdue
   - **Category** - Filter by item category
   - **User** - Filter by specific user
3. Click **"Generate Report"**
4. View report with:
   - Total borrowings
   - Success rate
   - Average borrowing time
   - List of all borrowings in period

![Borrowings Report](./docs/images/borrowings-report.png)

**Export Options:**
- **PDF** 📄 - Click "Export PDF" button
- **Excel** 📊 - Click "Export Excel" button

### Items Report (Admin Only)

1. Go to **"Reports"** > **"Items Report"**
2. See comprehensive statistics:
   - Total items in inventory
   - Total stock units
   - Available stock
   - Currently borrowed
   - Items by category
   - Items by condition
3. View charts and graphs

### Overdue Report (Admin Only)

1. Go to **"Reports"** > **"Overdue Report"**
2. See all overdue borrowings:
   - Borrowing code
   - User name
   - Item name
   - Due date
   - Days overdue
   - Contact information
3. Take action:
   - Send reminder email
   - Contact user directly
   - Mark as returned when received

### Monthly Report (Admin Only)

1. Go to **"Reports"** > **"Monthly Report"**
2. Select month and year
3. View:
   - Total borrowings in month
   - Daily borrowing trend
   - Peak borrowing days
   - Popular items
   - User activity
4. Export to PDF or Excel

---

## 🔔 Notifications

### Viewing Notifications

1. Click the **bell icon** (🔔) in the top-right corner
2. See list of recent notifications
3. Unread notifications have blue dot indicator
4. Click any notification to view details

![Notifications](./docs/images/notifications.png)

### Types of Notifications

**For Users:**
- ✅ Borrowing approved
- ❌ Borrowing rejected
- ⏰ Due date reminder (3 days before)
- ⚠️ Overdue warning
- 📅 Borrowing period extended
- ✓ Return confirmed

**For Admin:**
- 📝 New borrowing request
- ⏰ Items due today
- ⚠️ Overdue items alert
- 📦 Low stock warning
- 👤 New user registered

### Managing Notifications

**Mark as read:**
- Click on notification to mark as read
- Or hover and click "Mark as read" icon

**Mark all as read:**
1. Open notifications panel
2. Click **"Mark all as read"** button

**Delete notification:**
1. Hover over notification
2. Click delete icon (🗑️)
3. Notification will be removed

### Email Notifications

You will also receive email notifications for important events. Check your email inbox regularly.

**To update email notification preferences:**
1. Go to Profile Settings
2. Click "Notification Preferences"
3. Enable/disable email notifications
4. Click "Save"

---

## 👤 Profile Management

### Viewing Your Profile

1. Click your **profile picture/name** in top-right corner
2. Select **"Profile"** from dropdown
3. See your profile information:
   - Name
   - Email
   - Role
   - Member since
   - Total borrowings
   - Active borrowings

### Updating Profile Information

1. Go to your profile page
2. Click **"Edit Profile"** button
3. Update:
   - Full Name
   - Email address
   - Profile picture
4. Click **"Save Changes"**
5. Success message will appear

![Edit Profile](./docs/images/edit-profile.png)

### Changing Password

1. Go to your profile page
2. Click **"Change Password"** button
3. Fill in:
   - **Current Password** (required)
   - **New Password** (required, min 8 characters)
   - **Confirm New Password** (required, must match)
4. Click **"Update Password"**
5. You will be logged out
6. Login with your new password

**Password Requirements:**
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (@$!%*?&)

### Uploading Profile Picture

1. Go to Edit Profile
2. Click **"Choose File"** under profile picture
3. Select image (JPG, PNG, max 2MB)
4. Click **"Upload"**
5. Picture will be updated

---

## 👥 User Management (Admin)

### Viewing All Users

1. Go to **"Users"** menu (Admin only)
2. See list of all registered users:
   - Name
   - Email
   - Role (Admin/User)
   - Status (Active/Inactive)
   - Total borrowings
   - Member since
   - Actions

![Users List](./docs/images/users-list.png)

### Adding New User

1. Click **"+ Add User"** button
2. Fill in registration form:
   - **Full Name** (required)
   - **Email** (required, must be unique)
   - **Password** (required, min 8 chars)
   - **Confirm Password** (required)
   - **Role** (required) - Select Admin or User
3. Click **"Create User"**
4. New user will receive welcome email

### Editing User

1. Click **"Edit"** on any user
2. Update user information
3. Can change:
   - Name
   - Email
   - Role
   - Status (Active/Inactive)
4. Click **"Save Changes"**

### Deleting User

1. Click **"Delete"** on any user
2. Confirm deletion
3. User will be removed

> ⚠️ **Warning:** Users with active borrowings cannot be deleted!

### Viewing User Activity

1. Click **"View"** on any user
2. See detailed information:
   - Profile details
   - Borrowing history
   - Active borrowings
   - Activity timeline
3. Take actions if needed

---

## 📋 Activity Logs (Admin)

### Viewing Activity Logs

1. Go to **"Activity Logs"** menu (Admin only)
2. See chronological list of all system activities:
   - User actions (create, update, delete)
   - Timestamp
   - User who performed action
   - Target (item, borrowing, user, etc.)
   - Changes made

![Activity Logs](./docs/images/activity-logs.png)

### Filtering Activity Logs

**By date:**
1. Select date range
2. Click "Filter"

**By user:**
1. Select user from dropdown
2. Click "Filter"

**By action type:**
1. Select action (Created, Updated, Deleted)
2. Click "Filter"

**By model:**
1. Select type (Items, Borrowings, Users)
2. Click "Filter"

### Viewing Activity Details

1. Click on any activity log entry
2. See full details:
   - What changed
   - Old values
   - New values
   - When it happened
   - Who did it
   - Why (if reason provided)

---

## ❓ FAQ

### General Questions

**Q: How long can I borrow an item?**  
A: Maximum borrowing period is 30 days. You can request extension if needed.

**Q: Can I borrow multiple items at once?**  
A: Yes, you can submit multiple borrowing requests.

**Q: What happens if I return an item late?**  
A: You will receive overdue notifications. Repeated late returns may result in temporary borrowing suspension.

**Q: Can I cancel my borrowing request?**  
A: Yes, you can cancel pending requests. Contact admin for approved requests.

**Q: How do I know if my request is approved?**  
A: You will receive email notification and in-app notification.

### Technical Questions

**Q: I forgot my password. What should I do?**  
A: Click "Forgot Password?" on login page and follow the reset instructions.

**Q: Why can't I upload images?**  
A: Check file size (max 2MB) and format (JPG, PNG, GIF only).

**Q: The website is slow. What can I do?**  
A: Clear your browser cache, use modern browser, or check your internet connection.

**Q: I'm not receiving email notifications.**  
A: Check spam folder or contact admin to verify your email address.

**Q: Can I use the system on mobile?**  
A: Yes, the system is mobile-responsive and works on smartphones and tablets.

### Admin Questions

**Q: How do I handle damaged items?**  
A: Update item condition to "Damaged" and reduce stock if needed.

**Q: Can I restore deleted items?**  
A: No, deletions are permanent. Be careful when deleting.

**Q: How often should I run reports?**  
A: Weekly for monitoring, monthly for analysis and planning.

**Q: What if stock becomes negative?**  
A: System prevents this. Adjust stock manually if needed.

---

## 🔧 Troubleshooting

### Common Issues

#### Cannot Login

**Problem:** Invalid credentials error

**Solutions:**
1. Check email and password are correct
2. Password is case-sensitive
3. Try "Forgot Password?" to reset
4. Clear browser cookies
5. Contact admin if still cannot login

#### Page Not Loading

**Problem:** Blank page or loading forever

**Solutions:**
1. Refresh the page (F5 or Ctrl+R)
2. Clear browser cache (Ctrl+Shift+Delete)
3. Try different browser
4. Check internet connection
5. Contact IT support if persists

#### Image Upload Failed

**Problem:** Cannot upload item or profile images

**Solutions:**
1. Check file size (must be under 2MB)
2. Check file format (JPG, PNG, GIF only)
3. Try compressing image
4. Try different image
5. Contact admin if continues

#### Notifications Not Showing

**Problem:** Not receiving notifications

**Solutions:**
1. Check notification settings
2. Reload the page
3. Check email spam folder
4. Verify email address in profile
5. Contact admin to check server settings

#### Error Messages

**Problem:** Red error messages appear

**Solutions:**
1. Read the error message carefully
2. Check if required fields are filled
3. Verify data format (dates, numbers)
4. Try again after a few minutes
5. Screenshot error and contact support

---

## 📞 Support & Contact

### Getting Help

**Email Support:**
📧 support@inventaris.example.com

**Phone Support:**
📱 +62 XXX XXX XXXX
(Monday - Friday, 9 AM - 5 PM)

**Live Chat:**
💬 Available in bottom-right corner
(during business hours)

### Reporting Issues

When reporting issues, please provide:
1. Your username
2. What you were trying to do
3. What happened instead
4. Screenshot of error (if any)
5. Browser and device you're using

### Feature Requests

Have ideas for new features?
📝 Email: features@inventaris.example.com

---

## 📚 Additional Resources

- **Video Tutorials:** [YouTube Channel](https://youtube.com/example)
- **Quick Start Guide:** [PDF Download](./docs/quick-start.pdf)
- **Admin Guide:** [PDF Download](./docs/admin-guide.pdf)
- **API Documentation:** [View Online](./API_DOCUMENTATION.md)
- **Developer Guide:** [View Online](./DEVELOPER_GUIDE.md)

---

## 📝 Version History

**Version 1.0.0** (January 7, 2026)
- Initial release
- Core borrowing features
- Reports and analytics
- Email notifications
- Activity logging

---

**Need more help?** Contact our support team at support@inventaris.example.com

**Thank you for using Sistem Inventaris & Peminjaman Barang! 🎉**
