/**
 * SwiftBus API Integration
 * 
 * This file provides JavaScript functions to interact with the PHP backend APIs
 */

class SwiftBusAPI {
    constructor() {
        // Simplified URL construction for XAMPP
        // Check if we're running on localhost (XAMPP)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            // Running on XAMPP - use simple relative path
            this.baseURL = window.location.origin;
            const currentPath = window.location.pathname.replace(/\/[^\/]*$/, '');
            this.apiURL = this.baseURL + currentPath + '/api';
        } else {
            // Running on live server
            this.baseURL = window.location.origin;
            const currentPath = window.location.pathname.replace(/\/[^\/]*$/, '');
            this.apiURL = this.baseURL + currentPath + '/api';
        }
        
        // Debug: Log the constructed URLs
        console.log('SwiftBus API Configuration:');
        console.log('- Base URL:', this.baseURL);
        console.log('- API URL:', this.apiURL);
        console.log('- Current location:', window.location.href);
    }

    /**
     * Make API request
     */
    async request(endpoint, options = {}) {
        const url = `${this.apiURL}/${endpoint}`;
        const config = {
            credentials: 'include', // Include cookies/session
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        try {
            console.log('Making API request to:', url);
            const response = await fetch(url, config);
            
            // Try to parse JSON response regardless of status code
            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                // If JSON parsing fails, create error response
                data = {
                    success: false,
                    message: `Server returned ${response.status}: ${response.statusText}`
                };
            }
            
            console.log('API Response:', data);
            
            // If response is not ok but we have JSON data, return the JSON (it may contain error details)
            if (!response.ok && data && data.message) {
                // Return the parsed JSON response which should contain the proper error message
                return data;
            } else if (!response.ok) {
                // If no JSON or no message, create generic error
                return {
                    success: false,
                    message: `HTTP ${response.status}: ${response.statusText}`
                };
            }
            
            // Always return the data, let the calling code handle success/failure
            return data;
        } catch (error) {
            console.error('API Error Details:', {
                message: error.message,
                url: url,
                config: config
            });
            
            // Return more specific error messages
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                return {
                    success: false,
                    message: `Cannot connect to API at ${url}. Please check if the server is running and the file exists.`
                };
            } else if (error.message.includes('HTTP 404')) {
                return {
                    success: false,
                    message: `API file not found at ${url}. Please check if the file exists.`
                };
            } else if (error.message.includes('HTTP 500')) {
                return {
                    success: false,
                    message: `Server error. Please check PHP error logs for details.`
                };
            } else {
                return {
                    success: false,
                    message: `Connection error: ${error.message}`
                };
            }
        }
    }

    // Authentication APIs
    async register(userData) {
        return this.request('auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
    }

    async login(credentials) {
        return this.request('auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify(credentials)
        });
    }

    async logout() {
        return this.request('auth.php?action=logout', {
            method: 'POST'
        });
    }

    async checkSession() {
        return this.request('auth.php?action=check_session');
    }

    async changePassword(passwordData) {
        return this.request('auth.php?action=change_password', {
            method: 'POST',
            body: JSON.stringify(passwordData)
        });
    }

    async updateProfile(profileData) {
        return this.request('auth.php?action=update_profile', {
            method: 'POST',
            body: JSON.stringify(profileData)
        });
    }

    async uploadAvatar(formData) {
        return this.request('auth.php?action=upload_avatar', {
            method: 'POST',
            body: formData,
            headers: {} // Remove Content-Type header for FormData
        });
    }

    // Search APIs
    async searchBuses(searchParams) {
        const params = new URLSearchParams(searchParams);
        return this.request(`search.php?action=search_buses&${params}`);
    }

    async getCities() {
        return this.request('search.php?action=get_cities');
    }

    async getRoutes() {
        return this.request('search.php?action=get_routes');
    }

    async getAvailableSeats(scheduleId, date) {
        return this.request(`search.php?action=get_available_seats&schedule_id=${scheduleId}&date=${date}`);
    }

    async getPopularRoutes() {
        return this.request('search.php?action=get_popular_routes');
    }

    // Booking APIs
    async createBooking(bookingData) {
        return this.request('booking_clean.php?action=create_booking', {
            method: 'POST',
            body: JSON.stringify(bookingData)
        });
    }

    async createSimpleBooking(bookingData) {
        // Redirect to unified booking creation
        return this.request('booking_clean.php?action=create_booking', {
            method: 'POST',
            body: JSON.stringify(bookingData)
        });
    }

    async getUserBookings(filters = {}) {
        const params = new URLSearchParams(filters);
        return this.request(`booking_clean.php?action=get_user_bookings&${params}`);
    }

    async getBookingDetails(bookingId) {
        return this.request(`booking_clean.php?action=get_booking_details&booking_id=${bookingId}`);
    }

    async cancelBooking(bookingId, reason = '') {
        return this.request('booking_clean.php?action=cancel_booking', {
            method: 'POST',
            body: JSON.stringify({ booking_id: bookingId, reason })
        });
    }

    async getBookingStats() {
        return this.request('booking_clean.php?action=get_booking_stats');
    }

    // Seat Management APIs
    async getAvailableSeats(busCompany, busType, route, departureDate, departureTime) {
        const params = new URLSearchParams({
            bus_company: busCompany,
            bus_type: busType,
            route: route,
            departure_date: departureDate,
            departure_time: departureTime
        });
        return this.request(`booking_clean.php?action=get_available_seats&${params}`);
    }

    async reserveSeats(seatData) {
        return this.request('booking_clean.php?action=reserve_seats', {
            method: 'POST',
            body: JSON.stringify(seatData)
        });
    }

    async releaseSeats(reservationId) {
        return this.request('booking_clean.php?action=release_seats', {
            method: 'POST',
            body: JSON.stringify({ reservation_id: reservationId })
        });
    }

    // Payment APIs
    async processPayment(paymentData) {
        return this.request('payment.php?action=process_payment', {
            method: 'POST',
            body: JSON.stringify(paymentData)
        });
    }

    async verifyPayment(paymentId) {
        return this.request(`payment.php?action=verify_payment&payment_id=${paymentId}`);
    }

    async getPaymentMethods() {
        return this.request('payment.php?action=get_payment_methods');
    }

    async getPaymentStatus(bookingId) {
        return this.request(`payment.php?action=get_payment_status&booking_id=${bookingId}`);
    }

    // Admin APIs
    async getAdminStats() {
        return this.request('admin.php?action=get_dashboard_stats');
    }

    async getAllBookings(filters = {}) {
        const params = new URLSearchParams(filters);
        return this.request(`admin.php?action=get_all_bookings&${params}`);
    }

    async updateBookingStatus(bookingId, status) {
        return this.request('admin.php?action=update_booking_status', {
            method: 'PUT',
            body: JSON.stringify({ booking_id: bookingId, status })
        });
    }

    async getBuses(filters = {}) {
        const params = new URLSearchParams(filters);
        return this.request(`admin.php?action=get_buses&${params}`);
    }

    async createBus(busData) {
        return this.request('admin.php?action=create_bus', {
            method: 'POST',
            body: JSON.stringify(busData)
        });
    }

    async getSchedules(filters = {}) {
        const params = new URLSearchParams(filters);
        return this.request(`admin.php?action=get_schedules&${params}`);
    }

    async createSchedule(scheduleData) {
        return this.request('admin.php?action=create_schedule', {
            method: 'POST',
            body: JSON.stringify(scheduleData)
        });
    }

    async getRevenueReport(startDate, endDate) {
        return this.request(`admin.php?action=get_revenue_report&start_date=${startDate}&end_date=${endDate}`);
    }

    // Dashboard APIs
    async getDashboardStats(userType = 'admin') {
        const action = userType === 'admin' ? 'get_admin_stats' : 'get_user_stats';
        return this.request(`dashboard.php?action=${action}`);
    }

    async getNotifications() {
        return this.request('dashboard.php?action=get_notifications');
    }

    async getRecentActivities() {
        return this.request('dashboard.php?action=get_recent_activities');
    }

    async getUpcomingTrips() {
        return this.request('dashboard.php?action=get_upcoming_trips');
    }

    async getSystemAlerts() {
        return this.request('dashboard.php?action=get_system_alerts');
    }
}

// Create global API instance
window.swiftBusAPI = new SwiftBusAPI();

/**
 * Utility functions for common operations
 */
class SwiftBusUtils {
    static formatCurrency(amount) {
        return `ETB ${parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
    }

    static formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    }

    static formatTime(timeString) {
        const time = new Date(`2000-01-01T${timeString}`);
        return time.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        });
    }

    static showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        // Add styles if not already added
        if (!document.getElementById('notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-styles';
            styles.textContent = `
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    min-width: 300px;
                    max-width: 500px;
                    padding: 15px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    animation: slideIn 0.3s ease;
                }
                .notification-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
                .notification-error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
                .notification-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
                .notification-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
                .notification-content { display: flex; align-items: center; gap: 10px; }
                .notification-close { background: none; border: none; cursor: pointer; margin-left: auto; }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(styles);
        }

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    static getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    static async handleAPIError(error, defaultMessage = 'An error occurred') {
        console.error('API Error:', error);
        const message = error.message || defaultMessage;
        this.showNotification(message, 'error');
    }

    static validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    static validatePhone(phone) {
        // Ethiopian phone number validation
        const phoneRegex = /^\+251[0-9]{9}$/;
        return phoneRegex.test(phone.replace(/\s/g, ''));
    }

    static generateQRCode(data, canvasId) {
        // This function requires QRCode.js library
        if (typeof QRCode !== 'undefined') {
            const canvas = document.getElementById(canvasId);
            if (canvas) {
                QRCode.toCanvas(canvas, data, { width: 200, height: 200 });
            }
        }
    }

    static downloadTicket(bookingData) {
        // Generate a simple ticket PDF or image
        const ticketContent = `
            SwiftBus Ticket
            ===============
            Booking ID: ${bookingData.booking_id}
            Route: ${bookingData.route.origin.name} → ${bookingData.route.destination.name}
            Date: ${this.formatDate(bookingData.travel_date)}
            Time: ${this.formatTime(bookingData.schedule.departure_time)}
            Seats: ${bookingData.selected_seats.join(', ')}
            Amount: ${this.formatCurrency(bookingData.total_amount)}
            
            Please arrive 30 minutes before departure.
        `;

        const blob = new Blob([ticketContent], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `SwiftBus_Ticket_${bookingData.booking_id}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
}

// Create global utils instance
window.swiftBusUtils = SwiftBusUtils;

/**
 * Authentication helper functions
 */
class AuthManager {
    static async checkAuth() {
        try {
            const response = await swiftBusAPI.checkSession();
            return response.data;
        } catch (error) {
            return null;
        }
    }

    static async requireAuth(redirectTo = 'login.html') {
        const user = await this.checkAuth();
        if (!user) {
            window.location.href = redirectTo;
            return null;
        }
        return user;
    }

    static async requireAdmin(redirectTo = 'login.html') {
        const user = await this.checkAuth();
        if (!user || user.role !== 'admin') {
            window.location.href = redirectTo;
            return null;
        }
        return user;
    }

    static async logout() {
        try {
            await swiftBusAPI.logout();
            window.location.href = 'index.html';
        } catch (error) {
            swiftBusUtils.handleAPIError(error, 'Logout failed');
        }
    }
}

// Create global auth manager
window.authManager = AuthManager;

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add loading indicator styles
    if (!document.getElementById('loading-styles')) {
        const styles = document.createElement('style');
        styles.id = 'loading-styles';
        styles.textContent = `
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }
            .loading-spinner {
                width: 50px;
                height: 50px;
                border: 5px solid #f3f3f3;
                border-top: 5px solid #1a73e8;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(styles);
    }
});

/**
 * Loading indicator helper
 */
class LoadingManager {
    static show() {
        if (!document.getElementById('loading-overlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(overlay);
        }
    }

    static hide() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    }
}

window.loadingManager = LoadingManager;