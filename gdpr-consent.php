<?php
/*
Plugin Name: GDPR Consent Manager
Description: GDPR/ePrivacy compliant cookie consent manager with script blocking and YouTube embed management.
Version: 1.0.3
Author: Growskills
Text Domain: gdpr-consent
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
    'gdpr-consent' // dit moet overeenkomen met de plugin directory/slug
);

$updateChecker->getVcsApi()->enableReleaseAssets();
$updateChecker->debugMode = true;

define('GDPR_CONSENT_VERSION', '1.0.3');
define('GDPR_CONSENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDPR_CONSENT_PLUGIN_URL', plugin_dir_url(__FILE__));

class GDPR_Consent_Manager {
    
    public function __construct() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_footer', array($this, 'debug_embed_detection'));
        
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
        load_plugin_textdomain('gdpr-consent', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
        
        wp_localize_script('gdpr-consent-banner', 'gdprScripts', $consent_scripts);
        wp_localize_script('gdpr-consent-banner', 'gdprTexts', array(
            'cookieBannerTitle' => $this->get_text('We use cookies', 'Wij gebruiken cookies'),
            'cookieBannerText' => $this->get_text('This website uses cookies to ensure you get the best experience on our website. You can choose which categories of cookies you allow.', 'Deze website gebruikt cookies om ervoor te zorgen dat u de beste ervaring op onze website krijgt. U kunt kiezen welke categorieën cookies u toestaat.'),
            'acceptAll' => $this->get_text('Accept All', 'Alles accepteren'),
            'settings' => $this->get_text('Settings', 'Instellingen'),
            'modalTitle' => $this->get_text('Cookie Settings', 'Cookie-instellingen'),
            'modalDescription' => $this->get_text('Choose which cookies you want to accept. You can change these settings at any time.', 'Kies welke cookies u wilt accepteren. U kunt deze instellingen op elk moment wijzigen.'),
            'functional' => $this->get_text('Functional', 'Functioneel'),
            'functionalDescription' => $this->get_text('These cookies are necessary for the website to function and cannot be switched off.', 'Deze cookies zijn noodzakelijk voor het functioneren van de website en kunnen niet worden uitgeschakeld.'),
            'statistics' => $this->get_text('Statistics', 'Statistieken'),
            'statisticsDescription' => $this->get_text('These cookies help us understand how visitors interact with our website.', 'Deze cookies helpen ons begrijpen hoe bezoekers omgaan met onze website.'),
            'marketing' => $this->get_text('Marketing', 'Marketing'),
            'marketingDescription' => $this->get_text('These cookies are used to show you relevant advertising.', 'Deze cookies worden gebruikt om u relevante advertenties te tonen.'),
            'embeddedMedia' => $this->get_text('Embedded Media', 'Ingesloten media'),
            'embeddedMediaDescription' => $this->get_text('These cookies allow embedded content like YouTube videos.', 'Deze cookies maken ingesloten content zoals YouTube-video\'s mogelijk.'),
            'save' => $this->get_text('Save Settings', 'Instellingen opslaan'),
            'close' => $this->get_text('Close', 'Sluiten'),
            'youtubeLoadButton' => $this->get_text('Click to load YouTube video', 'Klik om YouTube-video te laden'),
            'youtubeNotice' => $this->get_text('This content is blocked until you accept embedded media cookies.', 'Deze content is geblokkeerd totdat u cookies voor ingesloten media accepteert.')
        ));
    }

    public function block_scripts() {
        echo "<!-- GDPR Consent Manager: Scripts will be loaded after consent -->\n";
    }
    public function render_banner() {
        ?>
        <div id="gdpr-consent-banner" class="gdpr-banner" role="dialog" aria-labelledby="gdpr-banner-title" aria-describedby="gdpr-banner-description" style="display: none;">
            <div class="gdpr-banner-content">
                <div class="gdpr-banner-text">
                    <h2 id="gdpr-banner-title"><?php echo esc_html($this->get_text('We use cookies', 'Wij gebruiken cookies')); ?></h2>
                    <p id="gdpr-banner-description"><?php echo esc_html($this->get_text('This website uses cookies to ensure you get the best experience on our website. You can choose which categories of cookies you allow.', 'Deze website gebruikt cookies om ervoor te zorgen dat u de beste ervaring op onze website krijgt. U kunt kiezen welke categorieën cookies u toestaat.')); ?></p>
                </div>
                <div class="gdpr-banner-buttons">
                    <button id="gdpr-accept-all" class="gdpr-btn gdpr-btn-primary" type="button">
                        <?php echo esc_html($this->get_text('Accept All', 'Alles accepteren')); ?>
                    </button>
                    <button id="gdpr-settings" class="gdpr-btn gdpr-btn-secondary" type="button">
                        <?php echo esc_html($this->get_text('Settings', 'Instellingen')); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <button id="gdpr-float-button" class="gdpr-float-button" type="button" aria-label="<?php echo esc_attr($this->get_text('Cookie Settings', 'Cookie-instellingen')); ?>" style="display: none;">
        </button>
        
        <div id="gdpr-consent-modal" class="gdpr-modal" role="dialog" aria-labelledby="gdpr-modal-title" aria-describedby="gdpr-modal-description" style="display: none;">
            <div class="gdpr-modal-overlay"></div>
            <div class="gdpr-modal-content" role="document">
                <div class="gdpr-modal-header">
                    <h2 id="gdpr-modal-title"><?php echo esc_html($this->get_text('Cookie Settings', 'Cookie-instellingen')); ?></h2>
                    <button id="gdpr-modal-close" class="gdpr-modal-close" type="button" aria-label="<?php echo esc_attr($this->get_text('Close', 'Sluiten')); ?>">
                        &times;
                    </button>
                </div>
                <div class="gdpr-modal-body">
                    <p id="gdpr-modal-description"><?php echo esc_html($this->get_text('Choose which cookies you want to accept. You can change these settings at any time.', 'Kies welke cookies u wilt accepteren. U kunt deze instellingen op elk moment wijzigen.')); ?></p>
                    
                    <!-- Functional cookies (always shown) -->
                    <div class="gdpr-category">
                        <div class="gdpr-category-header">
                            <label>
                                <input type="checkbox" id="gdpr-functional" checked disabled>
                                <span class="gdpr-category-title"><?php echo esc_html($this->get_text('Functional', 'Functioneel')); ?></span>
                            </label>
                        </div>
                        <p class="gdpr-category-description"><?php echo esc_html($this->get_text('These cookies are necessary for the website to function and cannot be switched off.', 'Deze cookies zijn noodzakelijk voor het functioneren van de website en kunnen niet worden uitgeschakeld.')); ?></p>
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
                                <span class="gdpr-category-title"><?php echo esc_html($this->get_text('Statistics', 'Statistieken')); ?></span>
                            </label>
                        </div>
                        <p class="gdpr-category-description"><?php echo esc_html($this->get_text('These cookies help us understand how visitors interact with our website.', 'Deze cookies helpen ons begrijpen hoe bezoekers omgaan met onze website.')); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Marketing category
                    if (in_array('marketing', $visible_categories)): ?>
                    <div class="gdpr-category">
                        <div class="gdpr-category-header">
                            <label>
                                <input type="checkbox" id="gdpr-marketing">
                                <span class="gdpr-category-title"><?php echo esc_html($this->get_text('Marketing', 'Marketing')); ?></span>
                            </label>
                        </div>
                        <p class="gdpr-category-description"><?php echo esc_html($this->get_text('These cookies are used to show you relevant advertising.', 'Deze cookies worden gebruikt om u relevante advertenties te tonen.')); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Embedded Media category
                    if (in_array('embedded_media', $visible_categories)): ?>
                    <div class="gdpr-category">
                        <div class="gdpr-category-header">
                            <label>
                                <input type="checkbox" id="gdpr-embedded-media">
                                <span class="gdpr-category-title"><?php echo esc_html($this->get_text('Embedded Media', 'Ingesloten media')); ?></span>
                            </label>
                        </div>
                        <p class="gdpr-category-description"><?php echo esc_html($this->get_text('These cookies allow embedded content like YouTube videos.', 'Deze cookies maken ingesloten content zoals YouTube-video\'s mogelijk.')); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="gdpr-modal-footer">
                    <button id="gdpr-save-settings" class="gdpr-btn gdpr-btn-primary" type="button">
                        <?php echo esc_html($this->get_text('Save Settings', 'Instellingen opslaan')); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('GDPR Consent Settings', 'gdpr-consent'),
            __('GDPR Consent', 'gdpr-consent'),
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
            __('Cookie Category Scripts', 'gdpr-consent'),
            array($this, 'scripts_section_callback'),
            'gdpr-consent-settings'
        );
        
        add_settings_field(
            'statistics_scripts',
            __('Statistics Scripts', 'gdpr-consent'),
            array($this, 'statistics_scripts_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );
        
        add_settings_field(
            'marketing_scripts',
            __('Marketing Scripts', 'gdpr-consent'),
            array($this, 'marketing_scripts_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );
        
        add_settings_field(
            'embedded_media_scripts',
            __('Embedded Media Scripts', 'gdpr-consent'),
            array($this, 'embedded_media_scripts_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );
        
        // Add smart detection setting
        add_settings_field(
            'smart_detect',
            __('Smart Detection', 'gdpr-consent'),
            array($this, 'smart_detect_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );
        
        // Add category visibility settings
        add_settings_field(
            'visible_categories',
            __('Visible Categories', 'gdpr-consent'),
            array($this, 'visible_categories_callback'),
            'gdpr-consent-settings',
            'gdpr_consent_scripts_section'
        );
    }
    
    public function sanitize_scripts($input) {
        $sanitized = array();
        
        if (isset($input['statistics'])) {
            $sanitized['statistics'] = wp_kses_post($input['statistics']);
        }
        
        if (isset($input['marketing'])) {
            $sanitized['marketing'] = wp_kses_post($input['marketing']);
        }
        
        if (isset($input['embedded_media'])) {
            $sanitized['embedded_media'] = wp_kses_post($input['embedded_media']);
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
        echo '<p>' . esc_html__('Add JavaScript code for each cookie category. These scripts will only be loaded when users consent to the respective category.', 'gdpr-consent') . '</p>';
    }
    
    public function statistics_scripts_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $value = isset($scripts['statistics']) ? $scripts['statistics'] : '';
        ?>
        <textarea name="gdpr_consent_scripts[statistics]" rows="10" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php echo esc_html__('Add Google Analytics, tracking pixels, or other statistics scripts here.', 'gdpr-consent'); ?></p>
        <?php
    }
    public function marketing_scripts_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $value = isset($scripts['marketing']) ? $scripts['marketing'] : '';
        ?>
        <textarea name="gdpr_consent_scripts[marketing]" rows="10" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php echo esc_html__('Add Facebook Pixel, Google Ads, or other marketing scripts here.', 'gdpr-consent'); ?></p>
        <?php
    }
    public function embedded_media_scripts_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $value = isset($scripts['embedded_media']) ? $scripts['embedded_media'] : '';
        ?>
        <textarea name="gdpr_consent_scripts[embedded_media]" rows="10" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php echo esc_html__('Add scripts required for embedded content like YouTube, Vimeo, etc.', 'gdpr-consent'); ?></p>
        <?php
    }
    
    public function smart_detect_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $value = isset($scripts['smart_detect']) ? $scripts['smart_detect'] : 0;
        ?>
        <label>
            <input type="checkbox" name="gdpr_consent_scripts[smart_detect]" value="1" <?php checked($value, 1); ?> />
            <?php echo esc_html__('Only show cookie banner when third-party embeds are detected (optional)', 'gdpr-consent'); ?>
        </label>
        <p class="description">
            <?php echo esc_html__('By default, the cookie banner is shown on all pages for consistency and compliance. Enable this option only if you want to show the banner exclusively on pages with YouTube, Vimeo, or other third-party embeds.', 'gdpr-consent'); ?>
            <br>
            <?php echo esc_html__('Note: Add "define(\'GDPR_CONSENT_DEBUG\', true);" to wp-config.php to always show the banner during development.', 'gdpr-consent'); ?>
        </p>
        <?php
    }
    
    public function visible_categories_callback() {
        $scripts = get_option('gdpr_consent_scripts', array());
        $visible_categories = isset($scripts['visible_categories']) && is_array($scripts['visible_categories']) 
            ? $scripts['visible_categories'] 
            : array('statistics', 'marketing', 'embedded_media');
        
        $categories = array(
            'statistics' => __('Statistics', 'gdpr-consent'),
            'marketing' => __('Marketing', 'gdpr-consent'),
            'embedded_media' => __('Embedded Media', 'gdpr-consent')
        );
        ?>
        <fieldset>
            <legend class="screen-reader-text"><?php echo esc_html__('Select which cookie categories to show to users', 'gdpr-consent'); ?></legend>
            <p><?php echo esc_html__('Choose which cookie categories you want to show to users. Functional cookies are always shown and cannot be disabled.', 'gdpr-consent'); ?></p>
            
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
                <?php echo esc_html__('Only show categories that your website actually uses. For example, if you don\'t use Google Analytics or Facebook Pixel, you can hide the Statistics and Marketing categories.', 'gdpr-consent'); ?>
            </p>
        </fieldset>
        <?php
    }
    
    public function settings_page() {
        include_once GDPR_CONSENT_PLUGIN_DIR . 'includes/settings-page.php';
    }
    
    private function create_mo_file() {
        $po_file = dirname(__FILE__) . '/languages/gdpr-consent-nl_NL.po';
        $mo_file = dirname(__FILE__) . '/languages/gdpr-consent-nl_NL.mo';
        
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
    
    private function get_text($english, $dutch) {
        $locale = get_locale();
        if (($locale === 'nl_NL' || strpos($locale, 'nl') === 0) && __($english, 'gdpr-consent') === $english) {
            return $dutch;
        }
        
        return __($english, 'gdpr-consent');
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
}
new GDPR_Consent_Manager();