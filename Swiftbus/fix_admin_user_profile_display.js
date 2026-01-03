// Quick fix script to update admin pages with proper user profile display
// This script contains the proper updateUserProfile function that should be added to all admin pages

const properUpdateUserProfileFunction = `
        function updateUserProfile() {
            if (currentUser) {
                // Extract name information properly - handle all possible field variations
                const firstName = currentUser.firstName || 
                                currentUser.name?.split(' ')[0] || 
                                currentUser.full_name?.split(' ')[0] || 
                                'Admin';
                                
                const fullName = currentUser.name || 
                               currentUser.full_name || 
                               \`\${currentUser.firstName || ''} \${currentUser.lastName || ''}\`.trim() || 
                               'Admin User';
                               
                const email = currentUser.email || 'admin@swiftbus.com';
                
                console.log('Updating admin profile with:', { 
                    firstName, 
                    fullName, 
                    email, 
                    originalData: currentUser 
                });
                
                // Update sidebar admin name
                const adminNameElement = document.getElementById('sidebarAdminName');
                if (adminNameElement) {
                    adminNameElement.textContent = fullName;
                    console.log('Updated sidebar admin name to:', fullName);
                }
                
                // Update any other admin name elements
                const adminNameElements = document.querySelectorAll('.admin-name, #adminName, #userName');
                adminNameElements.forEach(element => {
                    if (element) {
                        element.textContent = firstName;
                    }
                });
                
                // Update email elements
                const emailElements = document.querySelectorAll('.admin-email, #adminEmail, #userEmail');
                emailElements.forEach(element => {
                    if (element) {
                        element.textContent = email;
                    }
                });
                
                // Update profile image if available
                const profileImageElement = document.getElementById('profileImage');
                if (profileImageElement && currentUser.profile_image) {
                    profileImageElement.src = currentUser.profile_image;
                }
                
                // Update any dropdown user info
                const dropdownUserName = document.getElementById('dropdownUserName');
                if (dropdownUserName) {
                    dropdownUserName.textContent = fullName;
                }
                
                // Update profile form fields if they exist (for admin-profile.html)
                const profileFullNameInput = document.getElementById('profileFullName');
                if (profileFullNameInput) {
                    profileFullNameInput.value = fullName;
                }
                
                const profileEmailInput = document.getElementById('profileEmail');
                if (profileEmailInput) {
                    profileEmailInput.value = email;
                }
                
                const profilePhoneInput = document.getElementById('profilePhone');
                if (profilePhoneInput && currentUser.phone) {
                    profilePhoneInput.value = currentUser.phone;
                }
                
                console.log('✅ Admin profile updated successfully');
            } else {
                console.warn('⚠️ currentUser is null, cannot update profile');
            }
        }`;

const properAuthenticationPattern = `
        async function checkAuthenticationAndLoadData() {
            try {
                console.log('🔐 Checking admin authentication...');
                
                const response = await swiftBusAPI.checkSession();
                console.log('📡 Auth response:', response);
                
                if (!response.success || !response.data) {
                    console.log('❌ User not authenticated, redirecting to login');
                    window.location.href = 'login.html';
                    return;
                }
                
                // IMPORTANT: Set currentUser to the response data
                currentUser = response.data;
                console.log('✅ User authenticated:', currentUser);
                
                if (currentUser.role !== 'admin') {
                    console.log('❌ Access denied - User role:', currentUser.role);
                    alert(\`Access denied. Admin privileges required.\\nYour role: \${currentUser.role}\`);
                    window.location.href = 'index.html';
                    return;
                }
                
                // IMPORTANT: Call updateUserProfile after setting currentUser
                updateUserProfile();
                
                // Load page-specific data
                await loadPageData();
                
                console.log('✅ Admin page loaded successfully');
                
            } catch (error) {
                console.error('💥 Authentication check failed:', error);
                alert(\`Authentication failed: \${error.message}\\n\\nPlease try refreshing the page or logging in again.\`);
                window.location.href = 'login.html';
            }
        }`;

console.log('Admin User Profile Fix Script');
console.log('============================');
console.log('');
console.log('This script contains the proper functions needed to fix admin user profile display.');
console.log('');
console.log('Key fixes:');
console.log('1. Proper currentUser variable assignment: currentUser = response.data;');
console.log('2. Call updateUserProfile() after authentication');
console.log('3. Handle all possible name field variations (name, full_name, firstName/lastName)');
console.log('4. Update all relevant DOM elements with user information');
console.log('');
console.log('Apply these patterns to all admin pages:');
console.log('- admin-dashboard.html ✅ (already fixed)');
console.log('- admin-bookings-all.html ✅ (already fixed)');
console.log('- admin-profile.html (needs fix)');
console.log('- admin-buses-list.html (needs fix)');
console.log('- admin-routes-list.html (needs fix)');
console.log('- admin-schedules-list.html (needs fix)');
console.log('- admin-users-list.html (needs fix)');