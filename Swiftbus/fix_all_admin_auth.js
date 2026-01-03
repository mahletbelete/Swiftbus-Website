// This script will help fix authentication in all remaining admin pages
// Run this in browser console after loading each admin page

const newAuthFunction = `
        async function checkAdminAccess() {
            try {
                console.log('🔐 Starting admin access check...');
                
                // Check if swiftBusAPI is available
                if (typeof swiftBusAPI === 'undefined') {
                    console.error('❌ swiftBusAPI not available');
                    alert('System error: API not loaded. Please refresh the page.');
                    return;
                }
                
                console.log('✅ swiftBusAPI available, checking session...');
                
                // Use the same API-based authentication as other admin pages
                const response = await swiftBusAPI.checkSession();
                
                console.log('📡 API Response:', response);
                
                if (!response.success || !response.data) {
                    // Not logged in - redirect to login
                    console.log('❌ User not authenticated, redirecting to login');
                    window.location.href = 'login.html';
                    return;
                }
                
                const userData = response.data;
                console.log('✅ User authenticated:', userData);
                
                // Check if user has admin role
                if (userData.role !== 'admin') {
                    console.log('❌ Access denied - User role:', userData.role);
                    alert(\`Access denied. Admin privileges required.\\nYour role: \${userData.role}\`);
                    window.location.href = 'index.html';
                    return;
                }
                
                // User is authenticated and is admin - proceed
                console.log('🎉 Admin access granted for:', userData.name || userData.full_name);
                
                // Update UI with user data
                if (document.getElementById('adminName')) {
                    document.getElementById('adminName').textContent = userData.name || userData.full_name;
                }
                if (document.getElementById('userName')) {
                    document.getElementById('userName').textContent = (userData.name || userData.full_name).split(' ')[0];
                }
                
                // Sync localStorage for compatibility
                localStorage.setItem('swiftbus_user_loggedin', 'true');
                localStorage.setItem('swiftbus_user_data', JSON.stringify(userData));
                
                console.log('✅ Admin page loaded successfully');
                
                return true; // Success
                
            } catch (error) {
                console.error('💥 Authentication check failed:', error);
                alert(\`Authentication failed: \${error.message}\\n\\nPlease try refreshing the page or logging in again.\`);
                window.location.href = 'login.html';
                return false;
            }
        }
`;

console.log('New authentication function ready to replace old ones:');
console.log(newAuthFunction);

// Pages that need to be fixed:
const pagesToFix = [
    'admin-routes-list.html',
    'admin-routes-add.html', 
    'admin-routes-edit.html',
    'admin-schedules-list.html',
    'admin-bookings-all.html'
];

console.log('Pages that need authentication fix:', pagesToFix);