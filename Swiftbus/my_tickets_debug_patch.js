// Add this script to my-tickets.html for enhanced debugging
// Insert this right after the existing <script src="js/api.js"></script> line

console.log('🔍 My Tickets Debug Patch Loaded');

// Override the loadTickets function with enhanced logging
const originalLoadTickets = window.loadTickets;
window.loadTickets = async function() {
    console.log('🚀 loadTickets() called');
    
    const loadingState = document.getElementById('loadingState');
    const noTicketsState = document.getElementById('noTicketsState');
    const ticketsGrid = document.getElementById('ticketsGrid');
    
    try {
        console.log('📋 Clearing existing tickets...');
        allTickets = [];
        
        console.log('🔗 Calling swiftBusAPI.getUserBookings()...');
        const response = await swiftBusAPI.getUserBookings();
        console.log('📊 API Response received:', response);
        
        if (response.success && response.data && response.data.bookings && response.data.bookings.length > 0) {
            console.log('✅ Found bookings:', response.data.bookings.length);
            console.log('📋 Raw bookings data:', response.data.bookings);
            
            console.log('⚙️ Processing bookings...');
            allTickets = response.data.bookings.map((booking, index) => {
                console.log(`Processing booking ${index + 1}:`, booking);
                const processed = createTicketFromAPIBooking(booking);
                console.log(`Processed ticket ${index + 1}:`, processed);
                return processed;
            });
            
            console.log('✅ All tickets processed:', allTickets);
        } else {
            console.log('⚠️ No bookings found in response');
            console.log('Response success:', response.success);
            console.log('Response data:', response.data);
            console.log('Bookings array:', response.data?.bookings);
        }
        
        // Hide loading state
        console.log('🔄 Updating UI...');
        loadingState.style.display = 'none';
        
        // Show/hide no tickets state
        if (allTickets.length === 0) {
            console.log('📭 Showing no tickets state');
            noTicketsState.style.display = 'block';
            ticketsGrid.innerHTML = '';
        } else {
            console.log('🎫 Displaying tickets:', allTickets.length);
            noTicketsState.style.display = 'none';
            displayTickets(allTickets);
        }
        
        console.log('✅ loadTickets() completed successfully');
        
    } catch (error) {
        console.error('❌ Error in loadTickets():', error);
        console.error('Error stack:', error.stack);
        
        loadingState.style.display = 'none';
        
        // Show detailed error message
        ticketsGrid.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: white; border-radius: 15px; box-shadow: var(--shadow);">
                <i class="fas fa-exclamation-triangle" style="font-size: 60px; color: var(--warning); margin-bottom: 20px;"></i>
                <h3 style="color: var(--dark); margin-bottom: 15px;">Error Loading Tickets</h3>
                <p style="color: var(--gray); margin-bottom: 20px;">Error: ${error.message}</p>
                <p style="color: var(--gray); font-size: 12px;">Check browser console for details</p>
                <button class="action-btn btn-download" onclick="loadTickets()" style="display: inline-flex;">
                    <i class="fas fa-refresh"></i>
                    Retry
                </button>
            </div>
        `;
    }
};

// Override checkUserLogin with enhanced logging
const originalCheckUserLogin = window.checkUserLogin;
window.checkUserLogin = async function() {
    console.log('🔐 checkUserLogin() called');
    
    try {
        console.log('🔍 Checking user authentication...');
        console.log('Current URL:', window.location.href);
        
        const response = await swiftBusAPI.checkSession();
        console.log('🔐 Auth response:', response);
        
        if (response.success && response.data) {
            currentUser = response.data;
            isLoggedIn = true;
            console.log('✅ User authenticated:', currentUser);
            updateUserDisplay();
            showTicketsContent();
            loadTickets();
        } else {
            console.log('❌ Authentication failed');
            console.log('Response success:', response.success);
            console.log('Response data:', response.data);
            isLoggedIn = false;
            currentUser = null;
            showLoginPrompt();
        }
    } catch (error) {
        console.error('❌ Authentication check failed:', error);
        isLoggedIn = false;
        currentUser = null;
        showLoginPrompt();
    }
};

// Add API call logging
const originalRequest = swiftBusAPI.request;
swiftBusAPI.request = async function(endpoint, options = {}) {
    console.log('🌐 API Request:', endpoint, options);
    
    try {
        const result = await originalRequest.call(this, endpoint, options);
        console.log('📊 API Response:', endpoint, result);
        return result;
    } catch (error) {
        console.error('❌ API Error:', endpoint, error);
        throw error;
    }
};

console.log('✅ Debug patch applied successfully');

// Add a manual test function
window.debugMyTickets = async function() {
    console.log('🧪 Manual debug test started');
    
    try {
        console.log('1. Testing authentication...');
        const authResponse = await swiftBusAPI.checkSession();
        console.log('Auth result:', authResponse);
        
        console.log('2. Testing getUserBookings...');
        const bookingsResponse = await swiftBusAPI.getUserBookings();
        console.log('Bookings result:', bookingsResponse);
        
        console.log('3. Testing data processing...');
        if (bookingsResponse.success && bookingsResponse.data && bookingsResponse.data.bookings) {
            const processed = bookingsResponse.data.bookings.map(createTicketFromAPIBooking);
            console.log('Processed tickets:', processed);
        }
        
        console.log('✅ Manual debug test completed');
    } catch (error) {
        console.error('❌ Manual debug test failed:', error);
    }
};

console.log('💡 Run debugMyTickets() in console for manual testing');