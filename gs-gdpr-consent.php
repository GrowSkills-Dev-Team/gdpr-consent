<?php
/*
Plugin Name: GS GDPR Consent Manager
Description: GDPR/ePrivacy compliant cookie consent manager with script blocking and YouTube embed management.
Version: 2.1.5
Author: Growskills
Text Domain: gs-gdpr-consent
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Include de plugin update checker
require plugin_dir_path(__FILE__) . 'plugin-update-checker-master/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/GrowSkills-Dev-Team/gdpr-consent',
    __FILE__,
    'gs-gdpr-consent' // dit moet overeenkomen met de plugin directory/slug
);

$updateChecker->getVcsApi()->enableReleaseAssets();

define('GDPR_CONSENT_VERSION', '2.1.5');
define('GDPR_CONSENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDPR_CONSENT_PLUGIN_URL', plugin_dir_url(__FILE__));

class GDPR_Consent_Manager {
    public function cookie_policy_page_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $selected = isset($scripts['cookie_policy_page']) ? $scripts['cookie_policy_page'] : '';
        $pages = get_pages(array('sort_column' => 'post_title', 'sort_order' => 'asc'));
        echo '<select name="gdpr_consent_scripts[cookie_policy_page]">';
        echo '<option value="">' . esc_html__('-- Selecteer een pagina --', 'gs-gdpr-consent') . '</option>';
        foreach ($pages as $page) {
            printf(
                '<option value="%d" %s>%s</option>',
                esc_attr($page->ID),
                selected($selected, $page->ID, false),
                esc_html($page->post_title)
            );
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Kies de pagina waar je cookiebeleid staat. Deze wordt gelinkt in de cookiebanner.', 'gs-gdpr-consent') . '</p>';
    }
    
    public function __construct() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_footer', array($this, 'debug_embed_detection'));
        add_action('admin_notices', array($this, 'csp_admin_warning'));
        
        // Initialize conditionally based on settings and content
        $this->maybe_initialize_frontend();
    }
    
    /**
     * Initialize frontend (always show banner unless smart detection is explicitly enabled)
     */
    public function maybe_initialize_frontend() {
        $options = get_option('gdpr_consent_scripts', array());
        $use_smart_detect = isset($options['smart_detect']) && $options['smart_detect'];
        
        // By default, always show the banner for consistency and compliance
        if (!$use_smart_detect) {
            $this->initialize_frontend();
            return;
        }
        
        // Only use smart detection if explicitly enabled
        // Force enable in debug mode
        if (defined('GDPR_CONSENT_DEBUG') && GDPR_CONSENT_DEBUG) {
            $this->initialize_frontend();
            return;
        }
        
        // For smart detection, we need to check later when post content is available
        add_action('wp', array($this, 'check_and_initialize_frontend'));
    }
    
    /**
     * Check for embeds and initialize frontend if needed (called on 'wp' hook)
     */
    public function check_and_initialize_frontend() {
        // Smart detection is enabled - check if we have embeds
        if ($this->has_third_party_embeds()) {
            $this->initialize_frontend();
        } else {
            // If no embeds detected but we're viewing a post/page, 
            // add a late check in case content is loaded later
            add_action('wp_footer', array($this, 'late_embed_check'), 5);
        }
    }
    
    /**
     * Late check for embeds in case they're added dynamically
     */
    public function late_embed_check() {
        // Check one more time in case content was loaded dynamically
        if ($this->has_third_party_embeds()) {
            // If we find embeds now, initialize for next page load
            $this->initialize_frontend();
        }
    }
    
    /**
     * Initialize frontend functionality
     */
    public function initialize_frontend() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_banner'));
        add_action('wp_head', array($this, 'block_scripts'), 1);
        
        // Block YouTube IFrame API unless user has embedded media consent
        add_action('wp_enqueue_scripts', array($this, 'block_youtube_api'), 1);
        add_action('wp_print_scripts', array($this, 'block_youtube_api_scripts'), 1);
        
        // Add AJAX handlers for fetching scripts after consent
        add_action('wp_ajax_gdpr_get_scripts', array($this, 'ajax_get_scripts'));
        add_action('wp_ajax_nopriv_gdpr_get_scripts', array($this, 'ajax_get_scripts'));
    }
    
    /**
     * Check if current page/post has third-party embeds
     */
    public function has_third_party_embeds($content = null) {
        global $post;
        
        // Debug mode - always return true
        if (defined('GDPR_CONSENT_DEBUG') && GDPR_CONSENT_DEBUG) {
            return true;
        }
        
        // If no content provided, try to get current post content
        if (!$content && isset($post) && $post) {
            $content = $post->post_content;
        }
        
        // If still no content, check if we're on a page that might have embeds
        if (!$content) {
            // Check if we're on a single post/page
            if (is_single() || is_page()) {
                $post_id = get_the_ID();
                if ($post_id) {
                    $content = get_post_field('post_content', $post_id);
                }
            }
        }
        
        // If we still don't have content, check if it's a dynamic page that might have embeds
        if (!$content) {
            // Check for common page templates or dynamic content
            if (is_home() || is_front_page() || is_category() || is_tag() || is_archive()) {
                // For dynamic pages, we might want to check recent posts or featured content
                $recent_posts = get_posts(array(
                    'numberposts' => 5,
                    'post_status' => 'publish'
                ));
                
                foreach ($recent_posts as $recent_post) {
                    if ($this->has_third_party_embeds($recent_post->post_content)) {
                        return true;
                    }
                }
            }
        }
        
        // Pattern to match common third-party embeds
        $embed_patterns = array(
            // Video platforms - make these more specific
            '/youtube\.com\/embed\/[a-zA-Z0-9_-]+/i',
            '/youtube\.com\/watch\?v=[a-zA-Z0-9_-]+/i',
            '/youtu\.be\/[a-zA-Z0-9_-]+/i',
            '/vimeo\.com\/[0-9]+/i',
            '/dailymotion\.com/i',
            '/wistia\.com/i',
            '/brightcove\.com/i',
            
            // Social media
            '/twitter\.com/i',
            '/x\.com/i',
            '/instagram\.com/i',
            '/facebook\.com/i',
            '/tiktok\.com/i',
            '/linkedin\.com/i',
            '/pinterest\.com/i',
            
            // Audio platforms
            '/spotify\.com/i',
            '/soundcloud\.com/i',
            '/apple\.com\/music/i',
            
            // Maps and analytics
            '/google\.com\/maps/i',
            '/googletagmanager\.com/i',
            '/google-analytics\.com/i',
            '/facebook\.net/i',
            
            // WordPress shortcodes and embeds
            '/\[embed[^\]]*\]/i',
            '/\[video[^\]]*\]/i',
            '/\[audio[^\]]*\]/i',
            '/\[youtube[^\]]*\]/i',
            '/\[vimeo[^\]]*\]/i',
            '/\[instagram[^\]]*\]/i',
            '/\[twitter[^\]]*\]/i',
            
            // iframe embeds - more specific patterns
            '/<iframe[^>]*src=["\'][^"\']*youtube\.com[^"\']*["\'][^>]*>/i',
            '/<iframe[^>]*src=["\'][^"\']*vimeo\.com[^"\']*["\'][^>]*>/i',
            '/<iframe[^>]*src=["\'][^"\']*dailymotion\.com[^"\']*["\'][^>]*>/i',
            '/<iframe[^>]*src=["\'][^"\']*facebook\.com[^"\']*["\'][^>]*>/i',
            '/<iframe[^>]*src=["\'][^"\']*twitter\.com[^"\']*["\'][^>]*>/i',
            '/<iframe[^>]*src=["\'][^"\']*instagram\.com[^"\']*["\'][^>]*>/i',
            '/<iframe[^>]*src=["\'][^"\']*tiktok\.com[^"\']*["\'][^>]*>/i',
            '/<iframe[^>]*src=["\'][^"\']*spotify\.com[^"\']*["\'][^>]*>/i',
            '/<iframe[^>]*src=["\'][^"\']*soundcloud\.com[^"\']*["\'][^>]*>/i',
            
            // Script includes (analytics, tracking, etc.)
            '/<script[^>]*src=["\'][^"\']*google-analytics[^"\']*["\'][^>]*>/i',
            '/<script[^>]*src=["\'][^"\']*googletagmanager[^"\']*["\'][^>]*>/i',
            '/<script[^>]*src=["\'][^"\']*facebook\.net[^"\']*["\'][^>]*>/i',
            '/<script[^>]*src=["\'][^"\']*twitter\.com[^"\']*["\'][^>]*>/i',
            '/<script[^>]*src=["\'][^"\']*instagram\.com[^"\']*["\'][^>]*>/i',
        );
        
        if ($content) {
            foreach ($embed_patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    // Log what was found for debugging
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("GDPR Consent: Found third-party embed with pattern: " . $pattern);
                    }
                    return true;
                }
            }
        }
        
        // Also check the entire page content including widgets, etc.
        if (is_singular()) {
            global $wp_query;
            if (isset($wp_query->post) && $wp_query->post) {
                $full_content = apply_filters('the_content', $wp_query->post->post_content);
                if ($full_content && $full_content !== $content) {
                    foreach ($embed_patterns as $pattern) {
                        if (preg_match($pattern, $full_content)) {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("GDPR Consent: Found third-party embed in filtered content with pattern: " . $pattern);
                            }
                            return true;
                        }
                    }
                }
            }
        }
        
        // Check for WordPress auto-embeds (when user just pastes a URL)
        if ($content) {
            // Look for bare YouTube URLs that WordPress would auto-embed
            if (preg_match('/(?:^|\s)(https?:\/\/(?:www\.|m\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)[a-zA-Z0-9_-]+)(?:\s|$)/i', $content)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("GDPR Consent: Found YouTube auto-embed URL");
                }
                return true;
            }
            
            // Look for bare Vimeo URLs
            if (preg_match('/(?:^|\s)(https?:\/\/(?:www\.)?vimeo\.com\/[0-9]+)(?:\s|$)/i', $content)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("GDPR Consent: Found Vimeo auto-embed URL");
                }
                return true;
            }
        }
        
        return false;
    }
    public function load_textdomain() {
        $locale = determine_locale();
        $this->create_mo_file();
        load_plugin_textdomain('gs-gdpr-consent', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'gdpr-consent-banner',
            GDPR_CONSENT_PLUGIN_URL . 'assets/css/banner.css',
            array(),
            GDPR_CONSENT_VERSION
        );
        wp_enqueue_script(
            'gdpr-consent-banner',
            GDPR_CONSENT_PLUGIN_URL . 'assets/js/banner.js',
            array(),
            GDPR_CONSENT_VERSION,
            true
        );
        
        $consent_scripts = get_option('gdpr_consent_scripts', array(
            'statistics' => '',
            'marketing' => '',
            'embedded_media' => '',
            'visible_categories' => array('statistics', 'marketing', 'embedded_media')
        ));
        
        // Check if user already has consent - only pass scripts if they do
        $has_consent = false;
        $current_consent = array();
        if (isset($_COOKIE['gdprConsent'])) {
            $consent_data = json_decode(stripslashes($_COOKIE['gdprConsent']), true);
            if ($consent_data && is_array($consent_data)) {
                $has_consent = true;
                $current_consent = $consent_data;
            }
        }
        
        // Prepare scripts based on consent status
        $decoded_scripts = array();
        if ($has_consent) {
            // User has given consent - decode only the scripts they've approved
            $decoded_scripts['statistics'] = (!empty($consent_scripts['statistics']) && isset($current_consent['statistics']) && $current_consent['statistics']) ? base64_decode($consent_scripts['statistics']) : '';
            $decoded_scripts['marketing'] = (!empty($consent_scripts['marketing']) && isset($current_consent['marketing']) && $current_consent['marketing']) ? base64_decode($consent_scripts['marketing']) : '';
            $decoded_scripts['embedded_media'] = (!empty($consent_scripts['embedded_media']) && isset($current_consent['embedded_media']) && $current_consent['embedded_media']) ? base64_decode($consent_scripts['embedded_media']) : '';
        } else {
            // No consent yet - don't pass any scripts
            $decoded_scripts['statistics'] = '';
            $decoded_scripts['marketing'] = '';
            $decoded_scripts['embedded_media'] = '';
        }
        $decoded_scripts['visible_categories'] = isset($consent_scripts['visible_categories']) ? $consent_scripts['visible_categories'] : array('statistics', 'marketing', 'embedded_media');
        
        // Pass scripts to JavaScript (only approved ones if consent exists, empty if no consent)
        wp_localize_script('gdpr-consent-banner', 'gdprScripts', $decoded_scripts);
        wp_localize_script('gdpr-consent-banner', 'gdprAjax', array(
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdpr_get_scripts')
        ));
        wp_localize_script('gdpr-consent-banner', 'gdprTexts', array(
            'cookieBannerTitle' => __('We use cookies', 'gs-gdpr-consent'),
            'cookieBannerText' => __('This website uses cookies to ensure you get the best experience on our website. You can choose which categories of cookies you allow.', 'gs-gdpr-consent'),
            'acceptAll' => __('Accept All', 'gs-gdpr-consent'),
            'settings' => __('Settings', 'gs-gdpr-consent'),
            'modalTitle' => __('Cookie Settings', 'gs-gdpr-consent'),
            'modalDescription' => __('Choose which cookies you want to accept. You can change these settings at any time.', 'gs-gdpr-consent'),
            'functional' => __('Functional', 'gs-gdpr-consent'),
            'functionalDescription' => __('These cookies are necessary for the website to function and cannot be switched off.', 'gs-gdpr-consent'),
            'statistics' => __('Statistics', 'gs-gdpr-consent'),
            'statisticsDescription' => __('These cookies help us understand how visitors interact with our website.', 'gs-gdpr-consent'),
            'marketing' => __('Marketing', 'gs-gdpr-consent'),
            'marketingDescription' => __('These cookies are used to show you relevant advertising.', 'gs-gdpr-consent'),
            'embeddedMedia' => __('Embedded Media', 'gs-gdpr-consent'),
            'embeddedMediaDescription' => __('These cookies allow embedded content like YouTube videos.', 'gs-gdpr-consent'),
            'save' => __('Save Settings', 'gs-gdpr-consent'),
            'close' => __('Close', 'gs-gdpr-consent'),
            'youtubeLoadButton' => __('Click to load YouTube video', 'gs-gdpr-consent'),
            'youtubeNotice' => __('This content is blocked until you accept embedded media cookies. Click here to change your settings.', 'gs-gdpr-consent')
        ));
    }

    public function block_scripts() {
        // Check if user already has consent
        $has_consent = false;
        if (isset($_COOKIE['gdprConsent'])) {
            $consent = json_decode(stripslashes($_COOKIE['gdprConsent']), true);
            if ($consent && isset($consent['marketing']) && $consent['marketing']) {
                $has_consent = true;
            }
        }
        
        // Only block if no marketing consent
        if (!$has_consent) {
            ?>
            <script>
            // GDPR Consent Manager - Block tracking scripts
            window.gdprConsentRequired = true;
            
            // Block Facebook Pixel
            if (typeof window.fbq === 'undefined') {
                window.fbq = function() { 
                    console.log('GDPR: Facebook Pixel blocked - marketing consent required'); 
                    return false;
                };
                window.fbq.queue = [];
                window.fbq.loaded = false;
                window.fbq.version = '2.0';
                window.fbq.callMethod = function() {
                    console.log('GDPR: Facebook Pixel callMethod blocked');
                    return false;
                };
            }
            
            // Block Google Analytics gtag
            if (typeof window.gtag === 'undefined') {
                window.gtag = function() { 
                    console.log('GDPR: Google Analytics (gtag) blocked - statistics consent required'); 
                };
            }
            
            // Block legacy Google Analytics
            if (typeof window.ga === 'undefined') {
                window.ga = function() { 
                    console.log('GDPR: Google Analytics (ga) blocked - statistics consent required'); 
                };
            }
            
            // Prevent dataLayer initialization for GTM
            if (typeof window.dataLayer === 'undefined') {
                window.dataLayer = [];
                window.dataLayer.push = function() {
                    console.log('GDPR: Google Tag Manager blocked - consent required');
                };
            }
            
            console.log('GDPR Consent Manager: Tracking scripts blocked - consent required');
            </script>
            <?php
        }
    }
    public function render_banner() {
        $options = get_option('gdpr_consent_scripts', array());
        $policy_page_id = isset($options['cookie_policy_page']) ? absint($options['cookie_policy_page']) : 0;
        $policy_url = '';
        if ($policy_page_id) {
            $policy_url = get_permalink($policy_page_id);
        }
        ?>
        <div id="gdpr-consent-banner" class="gdpr-banner" role="dialog" aria-labelledby="gdpr-banner-title" aria-describedby="gdpr-banner-description" style="display: none;">
            <div class="gdpr-banner-content">
                <div class="gdpr-banner-text">
                    <h2 id="gdpr-banner-title"><?php echo esc_html(__('We use cookies', 'gs-gdpr-consent')); ?></h2>
                    <p id="gdpr-banner-description">
                        <?php echo esc_html(__('This website uses cookies to ensure you get the best experience on our website. You can choose which categories of cookies you allow.', 'gs-gdpr-consent')); ?>
                        <?php if ($policy_url): ?>
                            <?php echo ' ' . sprintf(
                                _x('Read our <a href="%s" target="_blank" rel="noopener">cookie policy</a>.', 'cookie policy link', 'gs-gdpr-consent'),
                                esc_url($policy_url)
                            ); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="gdpr-banner-buttons">
                    <button id="gdpr-accept-all" class="gdpr-btn gdpr-btn-primary" type="button">
                        <?php echo esc_html(__('Accept All', 'gs-gdpr-consent')); ?>
                    </button>
                    <button id="gdpr-settings" class="gdpr-btn gdpr-btn-secondary" type="button">
                        <?php echo esc_html(__('Settings', 'gs-gdpr-consent')); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <button id="gdpr-float-button" class="gdpr-float-button" type="button" aria-label="<?php echo esc_attr(__('Cookie Settings', 'gs-gdpr-consent')); ?>" style="display: none;">
        </button>
        
        <div id="gdpr-consent-modal" class="gdpr-modal" role="dialog" aria-labelledby="gdpr-modal-title" aria-describedby="gdpr-modal-description" style="display: none;">
            <div class="gdpr-modal-overlay"></div>
            <div class="gdpr-modal-content" role="document">
                <div class="gdpr-modal-header">
                    <h2 id="gdpr-modal-title"><?php echo esc_html(__('Cookie Settings', 'gs-gdpr-consent')); ?></h2>
                    <button id="gdpr-modal-close" class="gdpr-modal-close" type="button" aria-label="<?php echo esc_attr(__('Close', 'gs-gdpr-consent')); ?>">
                        <svg id="Laag_1" xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 14 14">
                            <path d="M8.414,7L13.707,1.707c.391-.391.391-1.023,0-1.414s-1.023-.391-1.414,0l-5.293,5.293L1.707.293C1.316-.098.684-.098.293.293S-.098,1.316.293,1.707l5.293,5.293L.293,12.293c-.391.391-.391,1.023,0,1.414.195.195.451.293.707.293s.512-.098.707-.293l5.293-5.293,5.293,5.293c.195.195.451.293.707.293s.512-.098.707-.293c.391-.391.391-1.023,0-1.414l-5.293-5.293Z"/>
                        </svg>
                    </button>
                </div>
                <div class="gdpr-modal-body">
                    <p id="gdpr-modal-description">
                        <?php echo esc_html(__('Choose which cookies you want to accept. You can change these settings at any time.', 'gs-gdpr-consent')); ?>
                        <?php if ($policy_url): ?>
                            <?php echo ' ' . sprintf(
                                _x('Read our <a href="%s" target="_blank" rel="noopener">cookie policy</a>.', 'cookie policy link', 'gs-gdpr-consent'),
                                esc_url($policy_url)
                            ); ?>
                        <?php endif; ?>
                    </p>
                    
                    <!-- Functional cookies (always shown) -->
                    <div class="gdpr-category">
                        <div class="gdpr-category-header">
                            <label>
                                <input type="checkbox" id="gdpr-functional" checked disabled>
                                <span class="gdpr-category-title"><?php echo esc_html(__('Functional', 'gs-gdpr-consent')); ?></span>
                            </label>
                        </div>
                        <p class="gdpr-category-description"><?php echo esc_html(__('These cookies are necessary for the website to function and cannot be switched off.', 'gs-gdpr-consent')); ?></p>
                    </div>
                    
                    <?php
                    // Get visible categories setting
                    $options = get_option('gdpr_consent_scripts', array());
                    $visible_categories = isset($options['visible_categories']) && is_array($options['visible_categories']) 
                        ? $options['visible_categories'] 
                        : array('statistics', 'marketing', 'embedded_media');
                    
                    // Statistics category
                    if (in_array('statistics', $visible_categories)): ?>
                    <div class="gdpr-category">
                        <div class="gdpr-category-header">
                            <label>
                                <input type="checkbox" id="gdpr-statistics">
                                <span class="gdpr-category-title"><?php echo esc_html(__('Statistics', 'gs-gdpr-consent')); ?></span>
                            </label>
                        </div>
                        <p class="gdpr-category-description"><?php echo esc_html(__('These cookies help us understand how visitors interact with our website.', 'gs-gdpr-consent')); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Marketing category
                    if (in_array('marketing', $visible_categories)): ?>
                    <div class="gdpr-category">
                        <div class="gdpr-category-header">
                            <label>
                                <input type="checkbox" id="gdpr-marketing">
                                <span class="gdpr-category-title"><?php echo esc_html(__('Marketing', 'gs-gdpr-consent')); ?></span>
                            </label>
                        </div>
                        <p class="gdpr-category-description"><?php echo esc_html(__('These cookies are used to show you relevant advertising.', 'gs-gdpr-consent')); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Embedded Media category
                    if (in_array('embedded_media', $visible_categories)): ?>
                    <div class="gdpr-category">
                        <div class="gdpr-category-header">
                            <label>
                                <input type="checkbox" id="gdpr-embedded-media">
                                <span class="gdpr-category-title"><?php echo esc_html(__('Embedded Media', 'gs-gdpr-consent')); ?></span>
                            </label>
                        </div>
                        <p class="gdpr-category-description"><?php echo esc_html(__('These cookies allow embedded content like YouTube videos.', 'gs-gdpr-consent')); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="gdpr-modal-footer">
                    <button id="gdpr-save-settings" class="gdpr-btn gdpr-btn-primary" type="button">
                        <?php echo esc_html(__('Save Settings', 'gs-gdpr-consent')); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('GDPR Consent Settings', 'gs-gdpr-consent'),
            __('GDPR Consent', 'gs-gdpr-consent'),
            'manage_options',
            'gdpr-consent-settings',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('gdpr_consent_settings', 'gdpr_consent_scripts', array(
            'sanitize_callback' => array($this, 'sanitize_scripts')
        ));
        
        add_settings_section(
            'gdpr_consent_scripts_section',
            __('Cookie Category Scripts', 'gs-gdpr-consent'),
            array($this, 'scripts_section_callback'),
            'gdpr-consent-settings'
        );

        // Add cookie policy page setting
        add_settings_field(
            'cookie_policy_page',
            __('Cookiebeleid pagina', 'gs-gdpr-consent'),
            array($this, 'cookie_policy_page_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );
        
        add_settings_field(
            'statistics_scripts',
            __('Statistics Scripts', 'gs-gdpr-consent'),
            array($this, 'statistics_scripts_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );
        
        add_settings_field(
            'marketing_scripts',
            __('Marketing Scripts', 'gs-gdpr-consent'),
            array($this, 'marketing_scripts_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );
        
        add_settings_field(
            'embedded_media_scripts',
            __('Embedded Media Scripts', 'gs-gdpr-consent'),
            array($this, 'embedded_media_scripts_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );
        
        // Add smart detection setting
        add_settings_field(
            'smart_detect',
            __('Smart Detection', 'gs-gdpr-consent'),
            array($this, 'smart_detect_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );
        
        // Add category visibility settings
        add_settings_field(
            'visible_categories',
            __('Visible Categories', 'gs-gdpr-consent'),
            array($this, 'visible_categories_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );

    }
    
    public function sanitize_scripts($input) {
        $sanitized = array();
        
        if (isset($input['statistics'])) {
            // Store scripts safely - encode to prevent execution
            $sanitized['statistics'] = base64_encode($input['statistics']);
        }
        
        if (isset($input['marketing'])) {
            // Store scripts safely - encode to prevent execution
            $sanitized['marketing'] = base64_encode($input['marketing']);
        }
        
        if (isset($input['embedded_media'])) {
            // Store scripts safely - encode to prevent execution
            $sanitized['embedded_media'] = base64_encode($input['embedded_media']);
        }
        
            // Sanitize cookie policy page
            if (isset($input['cookie_policy_page'])) {
                $sanitized['cookie_policy_page'] = absint($input['cookie_policy_page']);
            }
        
        // Sanitize smart detect option
        if (isset($input['smart_detect'])) {
            $sanitized['smart_detect'] = absint($input['smart_detect']) ? 1 : 0;
        }
        
        // Sanitize visible categories
        if (isset($input['visible_categories']) && is_array($input['visible_categories'])) {
            $allowed_categories = array('statistics', 'marketing', 'embedded_media');
            $sanitized['visible_categories'] = array();
            foreach ($input['visible_categories'] as $category) {
                if (in_array($category, $allowed_categories)) {
                    $sanitized['visible_categories'][] = sanitize_text_field($category);
                }
            }
        } else {
            // Default to all categories if none selected
            $sanitized['visible_categories'] = array('statistics', 'marketing', 'embedded_media');
        }
        
        return $sanitized;
    }
    
    public function scripts_section_callback() {
        echo '<p>' . esc_html__('Add JavaScript code for each cookie category. These scripts will only be loaded when users consent to the respective category.', 'gs-gdpr-consent') . '</p>';
    }
    
    public function statistics_scripts_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $value = isset($scripts['statistics']) ? base64_decode($scripts['statistics']) : '';
        ?>
        <textarea name="gdpr_consent_scripts[statistics]" rows="10" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php echo esc_html__('Add Google Analytics, tracking pixels, or other statistics scripts here.', 'gs-gdpr-consent'); ?></p>
        <?php
    }
    public function marketing_scripts_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $value = isset($scripts['marketing']) ? base64_decode($scripts['marketing']) : '';
        ?>
        <textarea name="gdpr_consent_scripts[marketing]" rows="10" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php echo esc_html__('Add Facebook Pixel, Google Ads, or other marketing scripts here.', 'gs-gdpr-consent'); ?></p>
        <?php
    }
    public function embedded_media_scripts_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $value = isset($scripts['embedded_media']) ? base64_decode($scripts['embedded_media']) : '';
        ?>
        <textarea name="gdpr_consent_scripts[embedded_media]" rows="10" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php echo esc_html__('Add scripts required for embedded content like YouTube, Vimeo, etc.', 'gs-gdpr-consent'); ?></p>
        <?php
    }
    
    public function smart_detect_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $value = isset($scripts['smart_detect']) ? $scripts['smart_detect'] : 0;
        ?>
        <label>
            <input type="checkbox" name="gdpr_consent_scripts[smart_detect]" value="1" <?php checked($value, 1); ?> />
            <?php echo esc_html__('Only show cookie banner when third-party embeds are detected (optional)', 'gs-gdpr-consent'); ?>
        </label>
        <p class="description">
            <?php echo esc_html__('By default, the cookie banner is shown on all pages for consistency and compliance. Enable this option only if you want to show the banner exclusively on pages with YouTube, Vimeo, or other third-party embeds.', 'gs-gdpr-consent'); ?>
            <br>
            <?php echo esc_html__('Note: Add "define(\'GDPR_CONSENT_DEBUG\', true);" to wp-config.php to always show the banner during development.', 'gs-gdpr-consent'); ?>
        </p>
        <?php
    }
    
    public function visible_categories_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $visible_categories = isset($scripts['visible_categories']) && is_array($scripts['visible_categories']) 
            ? $scripts['visible_categories'] 
            : array('statistics', 'marketing', 'embedded_media');
        
        $categories = array(
            'statistics' => __('Statistics', 'gs-gdpr-consent'),
            'marketing' => __('Marketing', 'gs-gdpr-consent'),
            'embedded_media' => __('Embedded Media', 'gs-gdpr-consent')
        );
        ?>
        <fieldset>
            <legend class="screen-reader-text"><?php echo esc_html__('Select which cookie categories to show to users', 'gs-gdpr-consent'); ?></legend>
            <p><?php echo esc_html__('Choose which cookie categories you want to show to users. Functional cookies are always shown and cannot be disabled.', 'gs-gdpr-consent'); ?></p>
            
            <?php foreach ($categories as $key => $label): ?>
                <label style="display: block; margin-bottom: 0.5rem;">
                    <input type="checkbox" 
                           name="gdpr_consent_scripts[visible_categories][]" 
                           value="<?php echo esc_attr($key); ?>" 
                           <?php checked(in_array($key, $visible_categories)); ?> />
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
            
            <p class="description">
                <?php echo esc_html__('Only show categories that your website actually uses. For example, if you don\'t use Google Analytics or Facebook Pixel, you can hide the Statistics and Marketing categories.', 'gs-gdpr-consent'); ?>
            </p>
        </fieldset>
        <?php
    }
    
    public function settings_page() {
        include_once GDPR_CONSENT_PLUGIN_DIR . 'includes/settings-page.php';
    }
    
    // Admin CSP warning on settings page
    public function csp_admin_warning() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'gdpr-consent-settings') return;
        echo '<div class="notice notice-warning"><p><strong>GDPR Consent Manager:</strong> Externe scripts kunnen worden geblokkeerd door de Content Security Policy (CSP) van deze site. Voeg de benodigde domeinen toe aan de <code>script-src</code> directive in je CSP. Voorbeeld:<br><code>script-src \'self\' https://www.googletagmanager.com https://www.youtube.com https://connect.facebook.net;</code><br>Zie de plugin documentatie voor meer info.</p></div>';
    }
    
    private function create_mo_file() {
        $po_file = dirname(__FILE__) . '/languages/gs-gdpr-consent-nl_NL.po';
        $mo_file = dirname(__FILE__) . '/languages/gs-gdpr-consent-nl_NL.mo';
        if (!file_exists($po_file) || file_exists($mo_file)) {
            return;
        }
        require_once(ABSPATH . 'wp-includes/pomo/po.php');
        require_once(ABSPATH . 'wp-includes/pomo/mo.php');
        $po = new PO();
        if ($po->import_from_file($po_file)) {
            $mo = new MO();
            $mo->headers = $po->headers;
            $mo->entries = $po->entries;
            $mo->export_to_file($mo_file);
        }
    }
    
    private function get_text($english, $dutch = '') {
        // For backwards compatibility, but encourage using __() directly
        return __($english, 'gs-gdpr-consent');
    }
    
    /**
     * Debug function to test embed detection
     */
    public function debug_embed_detection() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['gdpr_debug']) && $_GET['gdpr_debug'] === 'detect') {
            global $post;
            
            echo '<div style="background: #fff; padding: 1.25rem; border: 0.0625rem solid #ccc; margin: 1.25rem; position: fixed; top: 3.125rem; right: 1.25rem; z-index: 999999; max-width: 25rem;">';
            echo '<h3>GDPR Debug Info</h3>';
            echo '<p><strong>Current Post ID:</strong> ' . (isset($post) && $post ? $post->ID : 'None') . '</p>';
            echo '<p><strong>Is Single:</strong> ' . (is_single() ? 'Yes' : 'No') . '</p>';
            echo '<p><strong>Is Page:</strong> ' . (is_page() ? 'Yes' : 'No') . '</p>';
            echo '<p><strong>Current URL:</strong> ' . esc_html($_SERVER['REQUEST_URI']) . '</p>';
            
            $options = get_option('gdpr_consent_scripts', array());
            $use_smart_detect = isset($options['smart_detect']) && $options['smart_detect'];
            echo '<p><strong>Smart Detection:</strong> ' . ($use_smart_detect ? 'Enabled' : 'Disabled') . '</p>';
            
            $has_embeds = $this->has_third_party_embeds();
            echo '<p><strong>Has Third-Party Embeds:</strong> ' . ($has_embeds ? 'Yes' : 'No') . '</p>';
            
            if (isset($post) && $post) {
                echo '<p><strong>Post Content Preview:</strong></p>';
                echo '<textarea style="width: 100%; height: 6.25rem;">' . esc_textarea(substr($post->post_content, 0, 500)) . '...</textarea>';
            }
            
            echo '<p><a href="' . remove_query_arg('gdpr_debug') . '">Close Debug</a></p>';
            echo '</div>';
        }
    }
    
    /**
     * AJAX handler to get scripts after consent is given
     */
    public function ajax_get_scripts() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'gdpr_get_scripts')) {
            wp_die('Security check failed');
        }
        
        // Get consent data from POST
        $consent_data = isset($_POST['consent']) ? json_decode(stripslashes($_POST['consent']), true) : array();
        
        if (!is_array($consent_data)) {
            wp_die('Invalid consent data');
        }
        
        // Get stored scripts
        $consent_scripts = get_option('gdpr_consent_scripts', array(
            'statistics' => '',
            'marketing' => '',
            'embedded_media' => ''
        ));
        
        // Prepare response with only approved scripts
        $response = array();
        
        if (isset($consent_data['statistics']) && $consent_data['statistics'] && !empty($consent_scripts['statistics'])) {
            $response['statistics'] = base64_decode($consent_scripts['statistics']);
        }
        
        if (isset($consent_data['marketing']) && $consent_data['marketing'] && !empty($consent_scripts['marketing'])) {
            $response['marketing'] = base64_decode($consent_scripts['marketing']);
        }
        
        if (isset($consent_data['embedded_media']) && $consent_data['embedded_media'] && !empty($consent_scripts['embedded_media'])) {
            $response['embedded_media'] = base64_decode($consent_scripts['embedded_media']);
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Block YouTube IFrame API scripts if no embedded media consent
     */
    public function block_youtube_api() {
        // Check if user has embedded media consent
        $has_consent = false;
        if (isset($_COOKIE['gdprConsent'])) {
            $consent = json_decode(stripslashes($_COOKIE['gdprConsent']), true);
            if ($consent && isset($consent['embedded_media']) && $consent['embedded_media']) {
                $has_consent = true;
            }
        }
        
        if (!$has_consent) {
            // Dequeue YouTube API scripts that might be loaded by themes/plugins
            wp_dequeue_script('youtube-iframe-api');
            wp_dequeue_script('youtube-api');
            wp_dequeue_script('yt-iframe-api');
            wp_dequeue_script('google-youtube-api');
            
            // Remove scripts with YouTube API URLs
            global $wp_scripts;
            if (isset($wp_scripts->queue)) {
                foreach ($wp_scripts->queue as $handle) {
                    if (isset($wp_scripts->registered[$handle])) {
                        $script = $wp_scripts->registered[$handle];
                        if (strpos($script->src, 'youtube.com/iframe_api') !== false ||
                            strpos($script->src, 'youtube.com/player_api') !== false) {
                            wp_dequeue_script($handle);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Block YouTube API scripts in the HTML output if no consent
     */
    public function block_youtube_api_scripts() {
        // Check if user has embedded media consent
        $has_consent = false;
        if (isset($_COOKIE['gdprConsent'])) {
            $consent = json_decode(stripslashes($_COOKIE['gdprConsent']), true);
            if ($consent && isset($consent['embedded_media']) && $consent['embedded_media']) {
                $has_consent = true;
            }
        }
        
        if (!$has_consent) {
            // Start output buffering to catch and modify script tags
            ob_start(array($this, 'filter_youtube_api_scripts'));
        }
    }
    
    /**
     * Filter YouTube API scripts from HTML output
     */
    public function filter_youtube_api_scripts($buffer) {
        // Remove YouTube IFrame API script tags
        $patterns = array(
            '/<script[^>]*src=["\']?[^"\']*youtube\.com\/iframe_api[^"\']*["\']?[^>]*><\/script>/i',
            '/<script[^>]*src=["\']?[^"\']*youtube\.com\/player_api[^"\']*["\']?[^>]*><\/script>/i',
            '/<script[^>]*>.*?youtube\.com\/iframe_api.*?<\/script>/is',
        );
        
        foreach ($patterns as $pattern) {
            $buffer = preg_replace($pattern, '<!-- YouTube API script blocked by GDPR consent -->', $buffer);
        }
        
        return $buffer;
    }
}
new GDPR_Consent_Manager();