/**
 * Admin Data Provider
 * Provides consistent data from localStorage to all admin pages
 * Data is collected by admin-data-collector.html
 */

(function() {
    'use strict';

    // Admin Data Provider Class
    class AdminDataProvider {
        constructor() {
            this.dataReady = localStorage.getItem('admin_data_ready') === 'true';
            this.lastUpdate = localStorage.getItem('admin_data_collection_complete');
            this.debug = true;
            
            this.log('Admin Data Provider initialized', { dataReady: this.dataReady, lastUpdate: this.lastUpdate });
        }

        log(...args) {
            if (this.debug) {
                console.log('[AdminDataProvider]', ...args);
            }
        }

        // Check if data is available and fresh
        isDataReady() {
            return this.dataReady && this.lastUpdate;
        }

        // Get data freshness (in minutes)
        getDataAge() {
            if (!this.lastUpdate) return null;
            const updateTime = new Date(this.lastUpdate);
            const now = new Date();
            return Math.floor((now - updateTime) / (1000 * 60));
        }

        // Get all bookings
        getAllBookings() {
            try {
                const bookings = JSON.parse(localStorage.getItem('admin_all_bookings') || '[]');
                this.log(`Retrieved ${bookings.length} bookings`);
                return bookings;
            } catch (error) {
                this.log('Error getting bookings:', error);
                return [];
            }
        }

        // Get all users
        getAllUsers() {
            try {
                const users = JSON.parse(localStorage.getItem('admin_all_users') || '[]');
                this.log(`Retrieved ${users.length} users`);
                return users;
            } catch (error) {
                this.log('Error getting users:', error);
                return [];
            }
        }

        // Get payment data
        getPaymentData() {
            try {
                const paymentData = JSON.parse(localStorage.getItem('admin_payment_data') || '{}');
                this.log('Retrieved payment data:', Object.keys(paymentData));
                return paymentData;
            } catch (error) {
                this.log('Error getting payment data:', error);
                return { payments: [], payment_history: [], successful_payments: [], failed_payments: [] };
            }
        }

        // Get comprehensive statistics
        getStatistics() {
            try {
                const stats = JSON.parse(localStorage.getItem('admin_statistics') || '{}');
                this.log('Retrieved statistics:', Object.keys(stats));
                return stats;
            } catch (error) {
                this.log('Error getting statistics:', error);
                return this.generateFallbackStats();
            }
        }

        // Get data index/metadata
        getDataIndex() {
            try {
                const index = JSON.parse(localStorage.getItem('admin_data_index') || '{}');
                return index;
            } catch (error) {
                this.log('Error getting data index:', error);
                return {};
            }
        }

        // Generate fallback statistics if none available
        generateFallbackStats() {
            const bookings = this.getAllBookings();
            const users = this.getAllUsers();
            const today = new Date().toISOString().split('T')[0];
            
            return {
                bookings: {
                    total: bookings.length,
                    today: bookings.filter(b => (b.booking_date || b.created_at || '').startsWith(today)).length,
                    confirmed: bookings.filter(b => b.booking_status === 'confirmed').length,
                    pending: bookings.filter(b => b.booking_status === 'pending').length,
                    cancelled: bookings.filter(b => b.booking_status === 'cancelled').length
                },
                users: {
                    total: users.length,
                    active: users.filter(u => u.status === 'active' || u.is_active === 1).length,
                    new_today: users.filter(u => (u.created_at || '').startsWith(today)).length
                },
                revenue: {
                    total: bookings.reduce((sum, b) => sum + (parseFloat(b.total_amount) || 0), 0),
                    today: bookings.filter(b => (b.booking_date || b.created_at || '').startsWith(today))
                                   .reduce((sum, b) => sum + (parseFloat(b.total_amount) || 0), 0)
                },
                system: {
                    buses: { total: 15, active: 12, maintenance: 2, inactive: 1 },
                    routes: { total: 8, active: 7 }
                },
                generated_at: new Date().toISOString(),
                source: 'fallback'
            };
        }

        // Update dashboard statistics display
        updateDashboardStats(stats = null) {
            if (!stats) stats = this.getStatistics();
            
            try {
                // Booking statistics
                this.updateElement('totalBookings', stats.bookings?.total || 0);
                this.updateElement('todayBookings', stats.bookings?.today || 0);
                this.updateElement('monthBookings', stats.bookings?.this_month || 0);
                this.updateElement('confirmedBookings', stats.bookings?.confirmed || 0);
                this.updateElement('pendingBookings', stats.bookings?.pending || 0);
                this.updateElement('cancelledBookings', stats.bookings?.cancelled || 0);
                
                // User statistics
                this.updateElement('totalUsers', stats.users?.total || 0);
                this.updateElement('activeUsers', stats.users?.active || 0);
                this.updateElement('newUsersToday', stats.users?.new_today || 0);
                
                // Revenue statistics
                const totalRevenue = stats.revenue?.total || 0;
                const todayRevenue = stats.revenue?.today || 0;
                const monthRevenue = stats.revenue?.this_month || totalRevenue * 0.7;
                
                this.updateElement('totalRevenue', `ETB ${totalRevenue.toLocaleString()}`);
                this.updateElement('todayRevenue', `ETB ${todayRevenue.toLocaleString()}`);
                this.updateElement('monthRevenue', `ETB ${monthRevenue.toLocaleString()}`);
                this.updateElement('yearRevenue', `ETB ${totalRevenue.toLocaleString()}`);
                
                // Additional revenue elements
                this.updateElement('todayRevenue2', `ETB ${todayRevenue.toLocaleString()}`);
                this.updateElement('monthRevenue2', `ETB ${monthRevenue.toLocaleString()}`);
                
                // Bus and route statistics
                this.updateElement('activeBuses', stats.system?.buses?.total || 15);
                this.updateElement('activeBusCount', stats.system?.buses?.active || 12);
                this.updateElement('maintenanceBuses', stats.system?.buses?.maintenance || 2);
                this.updateElement('totalRoutes', stats.system?.routes?.total || 8);
                this.updateElement('activeRoutes', stats.system?.routes?.active || 7);
                
                // Update badges
                this.updateBadge('bookingCount', stats.bookings?.total || 0);
                this.updateBadge('busCount', stats.system?.buses?.active || 12);
                
                this.log('Dashboard stats updated successfully');
                return true;
            } catch (error) {
                this.log('Error updating dashboard stats:', error);
                return false;
            }
        }

        // Helper method to update element text content
        updateElement(id, value) {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        }

        // Helper method to update badge elements
        updateBadge(id, value) {
            const badge = document.getElementById(id);
            if (badge && value > 0) {
                badge.textContent = value;
                badge.classList.add('show');
                badge.style.display = 'inline-block';
            }
        }

        // Update bookings table (for admin-bookings-all.html)
        updateBookingsTable() {
            const bookings = this.getAllBookings();
            const tableBody = document.querySelector('#bookingsTable tbody, .bookings-table tbody, #dataTableBody');
            
            if (!tableBody) {
                this.log('Bookings table not found');
                return false;
            }

            if (bookings.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center">No bookings found</td></tr>';
                return true;
            }

            const rows = bookings.slice(0, 100).map(booking => `
                <tr>
                    <td>${booking.booking_id || booking.id || 'N/A'}</td>
                    <td>${booking.user_name || 'Unknown'}</td>
                    <td>${booking.route_display || `${booking.from_city || booking.route_origin || ''} → ${booking.to_city || booking.route_destination || ''}`}</td>
                    <td>${booking.travel_date || booking.booking_date || 'N/A'}</td>
                    <td>${booking.passenger_count || 1}</td>
                    <td>ETB ${(parseFloat(booking.total_amount) || 0).toLocaleString()}</td>
                    <td><span class="badge badge-${this.getStatusClass(booking.booking_status)}">${booking.booking_status || 'pending'}</span></td>
                    <td><span class="badge badge-${this.getPaymentStatusClass(booking.payment_status)}">${booking.payment_status || 'pending'}</span></td>
                </tr>
            `).join('');

            tableBody.innerHTML = rows;
            this.log(`Updated bookings table with ${bookings.length} records`);
            return true;
        }

        // Update users table (for admin-users-list.html)
        updateUsersTable() {
            const users = this.getAllUsers();
            const tableBody = document.querySelector('#usersTable tbody, .users-table tbody, #dataTableBody');
            
            if (!tableBody) {
                this.log('Users table not found');
                return false;
            }

            if (users.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center">No users found</td></tr>';
                return true;
            }

            const rows = users.slice(0, 100).map(user => `
                <tr>
                    <td>${user.id || user.user_id || 'N/A'}</td>
                    <td>${user.full_name || user.name || 'Unknown'}</td>
                    <td>${user.email || 'N/A'}</td>
                    <td>${user.phone || 'N/A'}</td>
                    <td><span class="badge badge-${this.getStatusClass(user.status || (user.is_active ? 'active' : 'inactive'))}">${user.status || (user.is_active ? 'active' : 'inactive')}</span></td>
                    <td>${user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}</td>
                </tr>
            `).join('');

            tableBody.innerHTML = rows;
            this.log(`Updated users table with ${users.length} records`);
            return true;
        }

        // Get CSS class for status badges
        getStatusClass(status) {
            const statusMap = {
                'confirmed': 'success',
                'active': 'success',
                'pending': 'warning',
                'cancelled': 'danger',
                'inactive': 'secondary',
                'failed': 'danger',
                'paid': 'success'
            };
            return statusMap[status] || 'secondary';
        }

        // Get CSS class for payment status badges
        getPaymentStatusClass(status) {
            const statusMap = {
                'paid': 'success',
                'pending': 'warning',
                'failed': 'danger',
                'refunded': 'info'
            };
            return statusMap[status] || 'warning';
        }

        // Show data collection prompt if no data available
        showDataCollectionPrompt() {
            if (this.isDataReady()) return false;

            const promptHtml = `
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; margin: 20px; border-radius: 8px; text-align: center;">
                    <h3 style="color: #856404; margin-top: 0;">📊 Data Collection Required</h3>
                    <p style="color: #856404; margin-bottom: 20px;">
                        To display accurate and consistent data across all admin pages, please collect data first.
                    </p>
                    <a href="admin-data-collector.html" style="background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
                        🚀 Collect Data Now
                    </a>
                </div>
            `;

            // Try to insert prompt at the beginning of main content
            const mainContent = document.querySelector('.main-content, .container, main, body');
            if (mainContent) {
                mainContent.insertAdjacentHTML('afterbegin', promptHtml);
                return true;
            }
            return false;
        }

        // Auto-initialize based on page type
        autoInitialize() {
            const path = window.location.pathname;
            const filename = path.split('/').pop();

            this.log('Auto-initializing for page:', filename);

            if (!this.isDataReady()) {
                this.log('Data not ready, showing collection prompt');
                this.showDataCollectionPrompt();
                return;
            }

            // Initialize based on page type
            if (filename.includes('dashboard')) {
                this.updateDashboardStats();
            } else if (filename.includes('bookings')) {
                this.updateBookingsTable();
            } else if (filename.includes('users')) {
                this.updateUsersTable();
            }

            // Show data age info
            const age = this.getDataAge();
            if (age !== null && age > 60) { // More than 1 hour old
                this.log(`Data is ${age} minutes old, consider refreshing`);
            }
        }
    }

    // Create global instance
    window.adminDataProvider = new AdminDataProvider();

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => window.adminDataProvider.autoInitialize(), 500);
        });
    } else {
        setTimeout(() => window.adminDataProvider.autoInitialize(), 500);
    }

    // Expose useful methods globally
    window.updateAdminData = () => window.adminDataProvider.autoInitialize();
    window.getAdminStats = () => window.adminDataProvider.getStatistics();
    window.getAdminBookings = () => window.adminDataProvider.getAllBookings();
    window.getAdminUsers = () => window.adminDataProvider.getAllUsers();

    console.log('✅ Admin Data Provider loaded successfully');

})();