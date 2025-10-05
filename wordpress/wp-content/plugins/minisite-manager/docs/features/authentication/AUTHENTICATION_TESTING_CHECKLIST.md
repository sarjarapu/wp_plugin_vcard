# Authentication Feature Testing Checklist

## 🎯 Test Plan for New Authentication Feature

### **1. Login Functionality (`/account/login`)**

#### ✅ **Test Cases:**
- [ ] **Valid Login**: Enter correct username/email and password
  - Expected: Redirect to dashboard or specified redirect_to URL
  - Expected: User is logged in and session is active

- [ ] **Invalid Credentials**: Enter wrong username or password
  - Expected: Show error message "Invalid username or email address" or similar
  - Expected: Stay on login page

- [ ] **Empty Fields**: Submit form with empty username or password
  - Expected: Show error "Please enter both username/email and password"

- [ ] **Remember Me**: Check "Remember Me" checkbox and login
  - Expected: User stays logged in longer (WordPress remember functionality)

- [ ] **Redirect After Login**: Login with redirect_to parameter
  - Expected: Redirect to the specified URL after successful login

#### 🔍 **What to Check:**
- Form submission works
- Error messages display correctly
- Success redirects work
- Session management works
- Nonce security is working

---

### **2. Registration Functionality (`/account/register`)**

#### ✅ **Test Cases:**
- [ ] **Valid Registration**: Enter valid username, email, and password
  - Expected: Account created successfully
  - Expected: User is automatically logged in
  - Expected: Redirect to dashboard

- [ ] **Invalid Email**: Enter invalid email format
  - Expected: Show error "Please enter a valid email address"

- [ ] **Weak Password**: Enter password less than 6 characters
  - Expected: Show error "Password must be at least 6 characters long"

- [ ] **Empty Fields**: Submit form with empty required fields
  - Expected: Show error "Please fill in all required fields"

- [ ] **Duplicate Username/Email**: Try to register with existing username/email
  - Expected: Show appropriate WordPress error message

#### 🔍 **What to Check:**
- Form validation works
- User creation in WordPress database
- Auto-login after registration
- Error handling for duplicates
- User role assignment (if MINISITE_ROLE_USER is defined)

---

### **3. Dashboard Functionality (`/account/dashboard`)**

#### ✅ **Test Cases:**
- [ ] **Logged In User**: Access dashboard while logged in
  - Expected: Dashboard page loads with user information
  - Expected: User data is displayed correctly

- [ ] **Not Logged In**: Access dashboard without being logged in
  - Expected: Redirect to login page with redirect_to parameter
  - Expected: After login, redirect back to dashboard

#### 🔍 **What to Check:**
- Authentication check works
- User data is displayed
- Redirect logic works
- Template rendering is correct

---

### **4. Logout Functionality (`/account/logout`)**

#### ✅ **Test Cases:**
- [ ] **Logged In User**: Click logout while logged in
  - Expected: User is logged out
  - Expected: Redirect to login page
  - Expected: Session is cleared

- [ ] **Not Logged In**: Access logout URL without being logged in
  - Expected: Redirect to login page

#### 🔍 **What to Check:**
- Session termination works
- Redirect to login page
- User is no longer authenticated

---

### **5. Forgot Password Functionality (`/account/forgot`)**

#### ❌ **KNOWN ISSUE: NOT WORKING**
- [ ] **Valid Username/Email**: Enter existing username or email
  - Expected: Show success message "Password reset email sent. Please check your inbox"
  - Expected: Email is sent (check email logs or inbox)
  - **STATUS**: ❌ NOT WORKING - Needs investigation

- [ ] **Invalid Username/Email**: Enter non-existent username or email
  - Expected: Show error "Invalid username or email address"
  - **STATUS**: ❌ NOT WORKING - Needs investigation

- [ ] **Empty Field**: Submit form with empty username field
  - Expected: Show error "Please enter your username or email address"
  - **STATUS**: ❌ NOT WORKING - Needs investigation

#### 🔍 **What to Check:**
- Email sending functionality
- Success/error message display
- Form validation works
- WordPress password reset integration

#### 🐛 **Bug Report:**
- **Issue**: Forgot Password functionality is completely non-functional
- **Symptoms**: Form submission doesn't work, no error/success messages
- **Priority**: Medium (other auth features work fine)
- **Status**: Needs investigation and fix

---

### **6. Template and UI Testing**

#### ✅ **Test Cases:**
- [ ] **Template Rendering**: All pages render without errors
  - Expected: No PHP errors or warnings
  - Expected: Templates load correctly with Timber

- [ ] **Form Elements**: All form fields are present and functional
  - Expected: Input fields, buttons, checkboxes work
  - Expected: Form styling is correct

- [ ] **Error Messages**: Error messages display properly
  - Expected: Error messages are visible and styled correctly
  - Expected: Success messages display when appropriate

- [ ] **Responsive Design**: Pages work on different screen sizes
  - Expected: Forms are usable on mobile and desktop

---

### **7. Security Testing**

#### ✅ **Test Cases:**
- [ ] **Nonce Verification**: Try to submit forms without proper nonce
  - Expected: Form submission fails with security error

- [ ] **CSRF Protection**: Test cross-site request forgery protection
  - Expected: Forms are protected against CSRF attacks

- [ ] **Input Sanitization**: Test with malicious input
  - Expected: Input is properly sanitized and escaped

---

### **8. Integration Testing**

#### ✅ **Test Cases:**
- [ ] **WordPress Integration**: Test with WordPress user system
  - Expected: Users are created in WordPress database
  - Expected: WordPress authentication functions work

- [ ] **Session Management**: Test session persistence
  - Expected: Login sessions work correctly
  - Expected: Logout clears sessions properly

- [ ] **Redirect Logic**: Test all redirect scenarios
  - Expected: Redirects work as expected in all cases

---

## 🐛 **Common Issues to Watch For:**

1. **Template Errors**: Check for missing template files or Timber errors
2. **Database Issues**: Verify user creation and session management
3. **Email Issues**: Check if password reset emails are being sent
4. **Redirect Loops**: Ensure no infinite redirect loops
5. **Form Validation**: Verify all validation rules work correctly
6. **Error Handling**: Check that errors are displayed properly

---

## 📝 **Testing Notes:**

- Test each functionality thoroughly
- Document any issues found
- Check browser console for JavaScript errors
- Check server logs for PHP errors
- Test with different user roles if applicable

---

## ✅ **Success Criteria:**

Authentication features status:
- [x] **Login works** with valid/invalid credentials ✅ WORKING
- [x] **Registration creates users** and auto-logs them in ✅ WORKING  
- [x] **Dashboard shows** for logged-in users, redirects others ✅ WORKING
- [x] **Logout clears sessions** and redirects properly ✅ WORKING
- [ ] **Forgot password** sends emails and shows appropriate messages ❌ NOT WORKING
- [x] **All forms validate** input correctly ✅ WORKING
- [x] **Security measures** (nonces, sanitization) work ✅ WORKING
- [x] **Templates render** without errors ✅ WORKING
- [x] **Integration with WordPress** user system works ✅ WORKING

### **Overall Status: 8/9 Features Working (89% Success Rate)**

---

**Happy Testing! 🚀**
