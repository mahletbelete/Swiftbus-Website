/**
 * SwiftBus Admin Profile Sync
 * 
 * This script handles profile data synchronization across all admin pages.
 * Include this script in all admin pages to ensure profile updates (name, avatar)
 * are reflected immediately across all open admin pages.
 */

(function() {
    'use strict';
    
    // ============================================
    // PROFILE SYNC FUNCTIONS
    // ============================================
    
    /**
     * Load cached profile data from localStorage
     */
    function loadCachedProfileData() {
        try {
            const cachedProfile = localStorage.getItem('adminProfileData');
            if (cachedProfile) {
                const profileData = JSON.parse(cachedProfile);
                console.log('📦 [ProfileSync] Loading cached profile data:', profileData);
                updateAdminUI(profileData);
            }
        } catch (error) {
            console.error('❌ [ProfileSync] Failed to load cached profile data:', error);
        }
    }
    
    /**
     * Update sidebar avatar with profile image
     */
    function updateSidebarAvatar(imageUrl) {
        if (!imageUrl) return;
        
        try {
            const sidebarAvatar = document.querySelector('.admin-avatar');
            if (sidebarAvatar) {
                // Check if image already exists
                let avatarImg = sidebarAvatar.querySelector('img');
                if (!avatarImg) {
                    avatarImg = document.createElement('img');
                    avatarImg.style.width = '100%';
                    avatarImg.style.height = '100%';
                    avatarImg.style.objectFit = 'cover';
                    avatarImg.style.borderRadius = '50%';
                    sidebarAvatar.appendChild(avatarImg);
                }
                avatarImg.src = imageUrl + '?t=' + Date.now();
                avatarImg.style.display = 'block';
                
                // Hide the icon
                const avatarIcon = sidebarAvatar.querySelector('i');
                if (avatarIcon) avatarIcon.style.display = 'none';
                
                console.log('✅ [ProfileSync] Sidebar avatar updated');
            }
        } catch (error) {
            console.error('❌ [ProfileSync] Failed to update sidebar avatar:', error);
        }
    }
    
    /**
     * Update admin UI elements with profile data
     */
    function updateAdminUI(profileData) {
        try {
            const displayName = profileData.full_name || profileData.name || 'Admin User';
            
            // Update sidebar admin name (handles both id variations)
            const adminNameElement = document.getElementById('adminName') || document.getElementById('sidebarAdminName');
            if (adminNameElement) {
                adminNameElement.textContent = displayName;
            }
            
            // Update header user name if exists
            const userNameElement = document.getElementById('userName');
            if (userNameElement) {
                userNameElement.textContent = displayName.split(' ')[0];
            }
            
            // Update profile name if on profile page
            const profileNameElement = document.getElementById('profileName');
            if (profileNameElement) {
                profileNameElement.textContent = displayName;
            }
            
            // Update sidebar avatar if profile image exists
            if (profileData.profile_image) {
                updateSidebarAvatar(profileData.profile_image);
            }
            
            console.log('✅ [ProfileSync] Admin UI updated with profile data');
        } catch (error) {
            console.error('❌ [ProfileSync] Failed to update admin UI:', error);
        }
    }
    
    /**
     * Setup profile sync listeners for cross-page updates
     */
    function setupProfileSyncListeners() {
        // Listen for custom event (same tab)
        window.addEventListener('adminProfileUpdated', function(e) {
            console.log('🔄 [ProfileSync] Profile updated event received:', e.detail);
            updateAdminUI(e.detail);
        });
        
        // Listen for storage event (other tabs/windows)
        window.addEventListener('storage', function(e) {
            if (e.key === 'adminProfileData' && e.newValue) {
                try {
                    const profileData = JSON.parse(e.newValue);
                    console.log('🔄 [ProfileSync] Profile updated from another tab:', profileData);
                    updateAdminUI(profileData);
                } catch (error) {
                    console.error('❌ [ProfileSync] Failed to parse profile data from storage event:', error);
                }
            }
        });
        
        console.log('✅ [ProfileSync] Profile sync listeners setup complete');
    }
    
    /**
     * Initialize profile sync on page load
     */
    function initProfileSync() {
        // Load cached profile data
        loadCachedProfileData();
        
        // Setup listeners for future updates
        setupProfileSyncListeners();
        
        console.log('✅ [ProfileSync] Admin profile sync initialized');
    }
    
    // Export functions for external use
    window.AdminProfileSync = {
        loadCachedProfileData: loadCachedProfileData,
        updateSidebarAvatar: updateSidebarAvatar,
        updateAdminUI: updateAdminUI,
        setupProfileSyncListeners: setupProfileSyncListeners,
        init: initProfileSync
    };
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileSync);
    } else {
        // DOM is already ready
        initProfileSync();
    }
    
})();
