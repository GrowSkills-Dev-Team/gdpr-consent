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
            // Error loading consent data
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
            // Error saving consent data
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
            // Show float button before hiding banner
            showFloatButton();
            // Move focus to floatButton for accessibility
            if (floatButton) {
                floatButton.focus();
            } else {
                document.body.focus();
            }
            banner.style.display = 'none';
            banner.setAttribute('aria-hidden', 'true');
            banner.classList.remove('gdpr-banner-hiding');
            document.body.classList.remove('gdpr-banner-visible');
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
    const saveBtn = document.getElementById('gdpr-save-settings');
        if (acceptAllBtn) {
            acceptAllBtn.addEventListener('click', function(e) {
                handleAcceptAll(e);
            });
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
    
    // Disable blockers after consent is given
    function disableBlockersAfterConsent() {
        // Unblock Facebook Pixel
        if (window.fbq && window.fbq.callMethod) {
            window.fbq.loaded = true;
        }
        // Unblock Google Analytics gtag
        if (window.gtag) {
            window.gtag = function() {
                // Native gtag will be loaded by injected script
            };
        }
        // Unblock legacy Google Analytics
        if (window.ga) {
            window.ga = function() {
                // Native ga will be loaded by injected script
            };
        }
        // Unblock Google Tag Manager
        if (window.dataLayer && window.dataLayer.push) {
            window.dataLayer.push = Array.prototype.push;
        }
        // Remove consentRequired flag
        window.gdprConsentRequired = false;
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
        disableBlockersAfterConsent();
        injectTestScript();
    }
    
    // Inject testscript bij statistics consent
    function injectTestScript() {
        const scriptTag = document.createElement('script');
    scriptTag.text = "";
        scriptTag.setAttribute('data-gdpr-category', 'statistics');
        document.head.appendChild(scriptTag);
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
    if (typeof gdprScripts !== 'undefined') {
            // Check if any scripts are present, otherwise fallback to AJAX
            const hasStatistics = consentData.statistics && gdprScripts.statistics && gdprScripts.statistics.trim() !== '';
            const hasMarketing = consentData.marketing && gdprScripts.marketing && gdprScripts.marketing.trim() !== '';
            const hasEmbedded = consentData.embedded_media && gdprScripts.embedded_media && gdprScripts.embedded_media.trim() !== '';
            if (hasStatistics) {
                loadScript('statistics', gdprScripts.statistics);
            }
            if (hasMarketing) {
                loadScript('marketing', gdprScripts.marketing);
            }
            if (hasEmbedded) {
                loadScript('embedded_media', gdprScripts.embedded_media);
            }
            // If no scripts present, fetch via AJAX
            if (!hasStatistics && !hasMarketing && !hasEmbedded) {
                fetchScriptsViaAjax();
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
        .then(response => {
            return response.json();
        })
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
            // Failed to fetch scripts
        });
    }
    
    /**
     * Decode HTML entities in a string
     */
    function decodeHtmlEntities(str) {
        const txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
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
            // Vind alle <script> blokken in de content
            const scriptRegex = /<script(.*?)>([\s\S]*?)<\/script>/gi;
            let match;
            let injected = false;
            while ((match = scriptRegex.exec(scriptContent))) {
                const scriptTag = document.createElement('script');
                // Zet eventuele attributen (zoals async, src)
                const attrRegex = /(\w+)(=["'](.*?)["'])?/g;
                let attrMatch;
                while ((attrMatch = attrRegex.exec(match[1]))) {
                    if (attrMatch[1] !== undefined && attrMatch[3] !== undefined) {
                        scriptTag.setAttribute(attrMatch[1], attrMatch[3]);
                    } else if (attrMatch[1] !== undefined) {
                        scriptTag.setAttribute(attrMatch[1], '');
                    }
                }
                scriptTag.setAttribute('data-gdpr-category', category);
                scriptTag.text = match[2];
                document.head.appendChild(scriptTag);
                injected = true;
            }
            // Injecteer overige HTML (zoals img, noscript) direct in body
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = scriptContent;
            Array.from(tempDiv.childNodes).forEach(node => {
                if (node.nodeName === 'IMG' || node.nodeName === 'NOSCRIPT') {
                    document.body.appendChild(node.cloneNode(true));
                    injected = true;
                }
            });
            // ...
        } catch (e) {
            // Error loading scripts
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
        
        // placeholder.appendChild(textElement);
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
    
    // Debug: Confirm we reach DOMContentLoaded listener setup
    document.addEventListener('DOMContentLoaded', init);

    // ...
    
    // Show admin warning if CSP blocks scripts
    function showCspAdminWarning() {
        if (typeof window.gdprCspWarningShown !== 'undefined') return;
        window.gdprCspWarningShown = true;
        // Only show for admins (simple check: WP admin bar present)
        if (!document.getElementById('wpadminbar')) return;
        const warning = document.createElement('div');
        warning.style.background = '#fff3cd';
        warning.style.color = '#856404';
        warning.style.border = '0.0625rem solid #ffeeba';
        warning.style.padding = '1rem';
        warning.style.margin = '1rem';
        warning.style.fontSize = '1rem';
        warning.style.zIndex = '99999';
        warning.innerHTML = '<strong>GDPR Consent Manager:</strong> Externe scripts worden mogelijk geblokkeerd door de Content Security Policy (CSP) van deze site. Voeg de benodigde domeinen toe aan de <code>script-src</code> directive in je CSP. Voorbeeld:<br><code>script-src \'self\' https://www.googletagmanager.com https://www.youtube.com https://connect.facebook.net;</code><br>Zie de plugin documentatie voor meer info.';
        document.body.appendChild(warning);
    }

    // Detect CSP blocking (simple test: try een extern script laden)
    function testCspBlocking() {
        const testScript = document.createElement('script');
        testScript.src = 'https://www.googletagmanager.com/gtag/js?id=G-TESTCSP';
        testScript.onerror = function() {
            showCspAdminWarning();
        };
        document.head.appendChild(testScript);
    }

    // Run CSP test alleen voor admins
    if (document.getElementById('wpadminbar')) {
        testCspBlocking();
    }
})();
