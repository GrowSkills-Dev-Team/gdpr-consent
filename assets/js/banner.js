/**
 * GDPR Consent Manager JavaScript
 * Vanilla JS implementation with full accessibility support
 */

(function() {
    'use strict';
    
    // Configuration
    const STORAGE_KEY = 'gdprConsent';
    const CONSENT_EXPIRY_MONTHS = 6;
    
    // DOM elements
    let banner = null;
    let modal = null;
    let floatButton = null;
    let focusableElements = [];
    let lastFocusedElement = null;
    
    // Consent state
    let consentData = {
        functional: true,
        statistics: false,
        marketing: false,
        embedded_media: false,
        timestamp: null
    };
    
    /**
     * Initialize the consent manager
     */
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        // Get DOM elements
        banner = document.getElementById('gdpr-consent-banner');
        modal = document.getElementById('gdpr-consent-modal');
        floatButton = document.getElementById('gdpr-float-button');
        
        if (!banner || !modal || !floatButton) {
            console.warn('GDPR Consent Manager: Banner, modal, or float button not found');
            return;
        }
        
        // Load existing consent
        loadConsent();
        
        // Check if consent is needed
        if (needsConsent()) {
            showBanner();
        } else {
            // Show float button and load approved scripts
            showFloatButton();
            loadApprovedScripts();
        }
        
        // Process YouTube embeds
        processYouTubeEmbeds();
        
        // Setup event listeners
        setupEventListeners();
    }
    
    /**
     * Load consent from localStorage and cookie
     */
    function loadConsent() {
        try {
            // First try localStorage
            let stored = localStorage.getItem(STORAGE_KEY);
            
            // If not in localStorage, try cookie
            if (!stored) {
                const cookieMatch = document.cookie.match(/(?:^|; )gdprConsent=([^;]*)/);
                if (cookieMatch) {
                    stored = decodeURIComponent(cookieMatch[1]);
                }
            }
            
            if (stored) {
                const parsed = JSON.parse(stored);
                if (parsed && parsed.timestamp) {
                    consentData = { ...consentData, ...parsed };
                }
            }
        } catch (e) {
            console.warn('GDPR Consent Manager: Error loading consent data', e);
        }
    }
    
    /**
     * Save consent to localStorage and cookie
     */
    function saveConsent() {
        try {
            consentData.timestamp = Date.now();
            
            // Save to localStorage
            localStorage.setItem(STORAGE_KEY, JSON.stringify(consentData));
            
            // Also save to cookie for PHP to read
            const expireDate = new Date();
            expireDate.setMonth(expireDate.getMonth() + CONSENT_EXPIRY_MONTHS);
            
            document.cookie = `gdprConsent=${encodeURIComponent(JSON.stringify(consentData))}; expires=${expireDate.toUTCString()}; path=/; SameSite=Lax`;
        } catch (e) {
            console.warn('GDPR Consent Manager: Error saving consent data', e);
        }
    }
    
    /**
     * Check if consent is needed (expired or not given)
     */
    function needsConsent() {
        if (!consentData.timestamp) {
            return true;
        }
        
        const sixMonthsAgo = Date.now() - (CONSENT_EXPIRY_MONTHS * 30 * 24 * 60 * 60 * 1000);
        return consentData.timestamp < sixMonthsAgo;
    }
    
    /**
     * Show the consent banner
     */
    function showBanner() {
        if (!banner) return;
        
        banner.style.display = 'block';
        banner.setAttribute('aria-hidden', 'false');
        
        // Focus first interactive element
        const firstButton = banner.querySelector('button');
        if (firstButton) {
            firstButton.focus();
        }
        
        // Add body class to prevent scrolling if needed
        document.body.classList.add('gdpr-banner-visible');
    }
    
    /**
     * Hide the consent banner with animation
     */
    function hideBanner() {
        if (!banner) return;
        
        // Add hiding animation
        banner.classList.add('gdpr-banner-hiding');
        
        // Wait for animation to finish, then hide and show float button
        setTimeout(() => {
            banner.style.display = 'none';
            banner.setAttribute('aria-hidden', 'true');
            banner.classList.remove('gdpr-banner-hiding');
            document.body.classList.remove('gdpr-banner-visible');
            
            // Show float button after banner is hidden
            showFloatButton();
        }, 500);
    }
    
    /**
     * Show the floating button with animation
     */
    function showFloatButton() {
        if (!floatButton) return;
        
        floatButton.style.display = 'flex';
        floatButton.classList.add('gdpr-float-showing');
        
        // Remove animation class after animation completes
        setTimeout(() => {
            floatButton.classList.remove('gdpr-float-showing');
        }, 500);
    }
    
    /**
     * Hide the floating button with animation
     */
    function hideFloatButton() {
        if (!floatButton) return;
        
        floatButton.classList.add('gdpr-float-hiding');
        
        // Wait for animation to finish, then hide
        setTimeout(() => {
            floatButton.style.display = 'none';
            floatButton.classList.remove('gdpr-float-hiding');
        }, 300);
    }
    
    /**
     * Show the settings modal
     */
    function showModal() {
        if (!modal) return;
        
        // Store currently focused element
        lastFocusedElement = document.activeElement;
        
        // Set current consent state in checkboxes
        updateModalCheckboxes();
        
        // Show modal
        modal.style.display = 'block';
        modal.setAttribute('aria-hidden', 'false');
        
        // Setup focus trap
        setupFocusTrap();
        
        // Focus first element in modal
        const closeButton = modal.querySelector('#gdpr-modal-close');
        if (closeButton) {
            closeButton.focus();
        }
        
        // Prevent body scrolling
        document.body.style.overflow = 'hidden';
    }
    
    /**
     * Hide the settings modal
     */
    function hideModal() {
        if (!modal) return;
        
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        
        // Restore body scrolling
        document.body.style.overflow = '';
        
        // Restore focus
        if (lastFocusedElement) {
            lastFocusedElement.focus();
        }
    }
    
    /**
     * Update modal checkboxes with current consent state
     */
    function updateModalCheckboxes() {
        const checkboxes = {
            'gdpr-statistics': consentData.statistics,
            'gdpr-marketing': consentData.marketing,
            'gdpr-embedded-media': consentData.embedded_media
        };
            
        Object.keys(checkboxes).forEach(id => {
            const checkbox = document.getElementById(id);
            if (checkbox) {
                checkbox.checked = checkboxes[id];
            }
        });
    }
    
    /**
     * Setup focus trap for modal
     */
    function setupFocusTrap() {
        focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        if (focusableElements.length > 0) {
            // Add keydown listener for focus trap
            modal.addEventListener('keydown', handleFocusTrap);
        }
    }
    
    /**
     * Handle focus trap keyboard navigation
     */
    function handleFocusTrap(e) {
        if (e.key !== 'Tab') return;
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        if (e.shiftKey) {
            // Shift + Tab
            if (document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            }
        } else {
            // Tab
            if (document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    }
    
    /**
     * Setup all event listeners
     */
    function setupEventListeners() {
        // Banner buttons
        const acceptAllBtn = document.getElementById('gdpr-accept-all');
        const settingsBtn = document.getElementById('gdpr-settings');
        
        if (acceptAllBtn) {
            acceptAllBtn.addEventListener('click', handleAcceptAll);
        }
        
        if (settingsBtn) {
            settingsBtn.addEventListener('click', handleShowSettings);
        }
        
        // Float button
        if (floatButton) {
            floatButton.addEventListener('click', handleShowSettings);
        }
        
        // Modal buttons
        const closeBtn = document.getElementById('gdpr-modal-close');
        const saveBtn = document.getElementById('gdpr-save-settings');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', hideModal);
        }
        
        if (saveBtn) {
            saveBtn.addEventListener('click', handleSaveSettings);
        }
        
        // Modal overlay click
        const overlay = modal.querySelector('.gdpr-modal-overlay');
        if (overlay) {
            overlay.addEventListener('click', hideModal);
        }
        
        // Escape key handler
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (modal.style.display === 'block') {
                    hideModal();
                }
            }
        });
    }
    
    /**
     * Handle accept all button click
     */
    function handleAcceptAll() {
        consentData = {
            functional: true,
            statistics: true,
            marketing: true,
            embedded_media: true,
            timestamp: Date.now()
        };
        
        saveConsent();
        hideBanner();
        loadApprovedScripts();
        processYouTubeEmbeds();
    }
    
    /**
     * Handle show settings button click
     */
    function handleShowSettings() {
        showModal();
    }
    
    /**
     * Handle save settings button click
     */
    function handleSaveSettings() {
        // Get checkbox states
        const statisticsCheckbox = document.getElementById('gdpr-statistics');
        const marketingCheckbox = document.getElementById('gdpr-marketing');
        const embeddedMediaCheckbox = document.getElementById('gdpr-embedded-media');
        
        // Update consent data
        consentData = {
            functional: true, // Always true
            statistics: statisticsCheckbox ? statisticsCheckbox.checked : false,
            marketing: marketingCheckbox ? marketingCheckbox.checked : false,
            embedded_media: embeddedMediaCheckbox ? embeddedMediaCheckbox.checked : false,
            timestamp: Date.now()
        };
        
        saveConsent();
        hideModal();
        hideBanner();
        loadApprovedScripts();
        processYouTubeEmbeds();
    }
    
    /**
     * Load approved scripts based on consent
     */
    function loadApprovedScripts() {
        // Check if we already have scripts available
        if (typeof gdprScripts !== 'undefined') {
            // Load scripts directly if available
            if (consentData.statistics && gdprScripts.statistics) {
                loadScript('statistics', gdprScripts.statistics);
            }
            
            if (consentData.marketing && gdprScripts.marketing) {
                loadScript('marketing', gdprScripts.marketing);
            }
            
            if (consentData.embedded_media && gdprScripts.embedded_media) {
                loadScript('embedded_media', gdprScripts.embedded_media);
            }
        } else {
            // Scripts not available - fetch via AJAX
            fetchScriptsViaAjax();
        }
    }
    
    /**
     * Fetch scripts via AJAX based on current consent
     */
    function fetchScriptsViaAjax() {
        if (typeof gdprAjax === 'undefined') {
            return;
        }
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'gdpr_get_scripts');
        formData.append('nonce', gdprAjax.nonce);
        formData.append('consent', JSON.stringify(consentData));
        
        // Make AJAX request
        fetch(gdprAjax.url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                // Load the scripts we received
                if (consentData.statistics && data.data.statistics) {
                    loadScript('statistics', data.data.statistics);
                }
                
                if (consentData.marketing && data.data.marketing) {
                    loadScript('marketing', data.data.marketing);
                }
                
                if (consentData.embedded_media && data.data.embedded_media) {
                    loadScript('embedded_media', data.data.embedded_media);
                }
            }
        })
        .catch(error => {
            console.error('GDPR: Failed to fetch scripts:', error);
        });
    }
    
    /**
     * Load a script for a specific category
     */
    function loadScript(category, scriptContent) {
        if (!scriptContent || scriptContent.trim() === '') {
            return;
        }
        
        // Check if script already loaded
        if (document.querySelector(`[data-gdpr-category="${category}"]`)) {
            return;
        }
        
        try {
            // Create a container div for the script content
            const container = document.createElement('div');
            container.setAttribute('data-gdpr-category', category);
            container.innerHTML = scriptContent;
            
            // Append to head
            document.head.appendChild(container);
            
            // Execute any script tags
            const scripts = container.querySelectorAll('script');
            scripts.forEach(script => {
                const newScript = document.createElement('script');
                
                // Copy attributes
                Array.from(script.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                
                // Copy content
                if (script.src) {
                    newScript.src = script.src;
                } else {
                    newScript.textContent = script.textContent;
                }
                
                // Replace old script with new one
                script.parentNode.replaceChild(newScript, script);
            });
        } catch (e) {
            console.warn(`GDPR Consent Manager: Error loading ${category} scripts`, e);
        }
    }
    
    /**
     * Process YouTube embeds - replace with placeholders if not consented
     */
    function processYouTubeEmbeds() {
        const youtubeIframes = document.querySelectorAll('iframe[src*="youtube.com"], iframe[src*="youtu.be"]');
        
        youtubeIframes.forEach((iframe, index) => {
            if (consentData.embedded_media) {
                // Consent given - ensure iframe is visible
                showYouTubeEmbed(iframe);
            } else {
                // No consent - replace with placeholder
                replaceYouTubeWithPlaceholder(iframe);
            }
        });
        
        // Also process existing placeholders if consent is now given
        if (consentData.embedded_media) {
            const placeholders = document.querySelectorAll('.gdpr-youtube-placeholder');
            
            if (placeholders.length > 0) {
                // For YouTube embeds with API, just refresh the page for reliable functionality
                const hasYouTubeAPI = Array.from(placeholders).some(placeholder => {
                    const src = placeholder.getAttribute('data-youtube-src');
                    return src && src.includes('enablejsapi=1');
                });
                
                if (hasYouTubeAPI) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                    return;
                }
                
                // For simple YouTube embeds without API, restore normally
                placeholders.forEach((placeholder, index) => {
                    const originalSrc = placeholder.getAttribute('data-youtube-src');
                    if (originalSrc) {
                        restoreYouTubeEmbed(placeholder, originalSrc);
                    }
                });
            }
        }
    }
    
    /**
     * Replace YouTube iframe with consent placeholder
     */
    function replaceYouTubeWithPlaceholder(iframe) {
        // Don't replace if already replaced
        if (iframe.getAttribute('data-gdpr-replaced')) {
            return;
        }
        
        const placeholder = document.createElement('div');
        placeholder.className = 'gdpr-youtube-placeholder';
        placeholder.setAttribute('role', 'button');
        placeholder.setAttribute('tabindex', '0');
        placeholder.setAttribute('data-youtube-src', iframe.src);
        
        // Store original dimensions
        const width = iframe.width || iframe.getAttribute('width') || '560';
        const height = iframe.height || iframe.getAttribute('height') || '315';
        placeholder.setAttribute('data-width', width);
        placeholder.setAttribute('data-height', height);
        
        // Store original iframe attributes that might be important for the theme
        if (iframe.id) {
            placeholder.setAttribute('data-original-id', iframe.id);
        }
        if (iframe.className) {
            placeholder.setAttribute('data-original-class', iframe.className);
        }
        
        // Get video title if available
        const title = iframe.getAttribute('title') || 'YouTube video';
        placeholder.setAttribute('aria-label', `${title} - ${gdprTexts.youtubeLoadButton || 'Click to load YouTube video'}`);
        
        // Create placeholder content
        const textElement = document.createElement('div');
        textElement.className = 'gdpr-youtube-placeholder-text';
        textElement.textContent = gdprTexts.youtubeLoadButton || 'Click to load YouTube video';
        
        const noticeElement = document.createElement('div');
        noticeElement.className = 'gdpr-youtube-placeholder-notice';
        noticeElement.textContent = gdprTexts.youtubeNotice || 'This content is blocked until you accept embedded media cookies.';
        
        placeholder.appendChild(textElement);
        placeholder.appendChild(noticeElement);
        
        // Add click handler
        placeholder.addEventListener('click', function() {
            showConsentModalForYouTube();
        });
        
        // Add keyboard handler
        placeholder.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                showConsentModalForYouTube();
            }
        });
        
        // Replace iframe with placeholder
        iframe.parentNode.replaceChild(placeholder, iframe);
        iframe.setAttribute('data-gdpr-replaced', 'true');
    }
    
    /**
     * Show consent modal specifically for YouTube
     */
    function showConsentModalForYouTube() {
        // Pre-check embedded media checkbox
        const embeddedMediaCheckbox = document.getElementById('gdpr-embedded-media');
        if (embeddedMediaCheckbox) {
            embeddedMediaCheckbox.checked = true;
        }
        
        showModal();
    }
    
    /**
     * Restore YouTube embed from placeholder
     */
    function restoreYouTubeEmbed(placeholder, originalSrc) {
        const iframe = document.createElement('iframe');
        
        // Set common YouTube iframe attributes - keep original src exactly as it was
        iframe.src = originalSrc;
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
        
        // Copy dimensions if available
        const width = placeholder.getAttribute('data-width') || '560';
        const height = placeholder.getAttribute('data-height') || '315';
        iframe.width = width;
        iframe.height = height;
        
        // Copy any other attributes that might be important for the theme
        const originalId = placeholder.getAttribute('data-original-id');
        if (originalId) {
            iframe.id = originalId;
        }
        
        const originalClass = placeholder.getAttribute('data-original-class');
        if (originalClass) {
            iframe.className = originalClass;
        }
        
        // Replace placeholder with iframe
        if (placeholder.parentNode) {
            const parentElement = placeholder.parentNode;
            parentElement.replaceChild(iframe, placeholder);
            
            // Trigger events to notify the theme that new content has been loaded
            setTimeout(() => {
                // Simple approach: trigger basic events for simple embeds
                window.dispatchEvent(new Event('resize'));
                
                const event = new CustomEvent('gdpr:youtube:restored', {
                    detail: { iframe: iframe, originalSrc: originalSrc },
                    bubbles: true
                });
                document.dispatchEvent(event);
                parentElement.dispatchEvent(event);
                
                if (window.jQuery) {
                    window.jQuery(iframe).trigger('load');
                    window.jQuery(document).trigger('gdpr:youtube:restored', [iframe]);
                }
            }, 100);
        }
    }
    
    /**
     * Show YouTube embed (ensure it's not hidden)
     */
    function showYouTubeEmbed(iframe) {
        iframe.style.display = '';
        iframe.removeAttribute('data-gdpr-replaced');
    }
    
    /**
     * Show the consent banner again (for settings changes)
     */
    function showBannerAgain() {
        if (!banner) return;
        
        // Hide float button first
        hideFloatButton();
        
        // Show banner with animation
        banner.style.display = 'block';
        banner.setAttribute('aria-hidden', 'false');
        banner.classList.add('gdpr-banner-showing');
        
        // Focus first interactive element
        const firstButton = banner.querySelector('button');
        if (firstButton) {
            firstButton.focus();
        }
        
        // Add body class
        document.body.classList.add('gdpr-banner-visible');
        
        // Remove animation class after animation completes
        setTimeout(() => {
            banner.classList.remove('gdpr-banner-showing');
        }, 500);
    }

    /**
     * Public API for manually showing the banner again
     */
    window.gdprShowBanner = function() {
        showBannerAgain();
    };
    
    /**
     * Public API for manually triggering consent modal
     */
    window.gdprShowSettings = function() {
        if (modal) {
            showModal();
        }
    };
    
    /**
     * Public API for checking consent status
     */
    window.gdprHasConsent = function(category) {
        return consentData[category] || false;
    };
    
    /**
     * Public API for getting all consent data
     */
    window.gdprGetConsent = function() {
        return { ...consentData };
    };
    
    // Initialize when DOM is ready
    init();
    
})();
