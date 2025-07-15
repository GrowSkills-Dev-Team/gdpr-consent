<?php
/**
 * Settings page for GDPR Consent Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('GDPR Consent Settings', 'gdpr-consent'); ?></h1>
    
    <div class="gdpr-admin-content">
        <div class="gdpr-admin-main">
            <form method="post" action="options.php">
                <?php
                settings_fields('gdpr_consent_settings');
                do_settings_sections('gdpr-consent-settings');
                submit_button();
                ?>
            </form>
        </div>
        
        <div class="gdpr-admin-sidebar">
            <div class="postbox">
                <h3 class="hndle"><?php echo esc_html__('Usage Instructions', 'gdpr-consent'); ?></h3>
                <div class="inside">
                    <h4><?php echo esc_html__('How it works:', 'gdpr-consent'); ?></h4>
                    <ol>
                        <li><?php echo esc_html__('Add your tracking scripts in the appropriate categories below', 'gdpr-consent'); ?></li>
                        <li><?php echo esc_html__('Scripts will only load after user consent', 'gdpr-consent'); ?></li>
                        <li><?php echo esc_html__('YouTube embeds are automatically blocked until consent', 'gdpr-consent'); ?></li>
                        <li><?php echo esc_html__('Consent expires after 6 months and banner will reappear', 'gdpr-consent'); ?></li>
                        <li><?php echo esc_html__('Use Smart Detection to only show banner when needed', 'gdpr-consent'); ?></li>
                    </ol>
                    
                    <h4><?php echo esc_html__('Smart Detection:', 'gdpr-consent'); ?></h4>
                    <p><?php echo esc_html__('When enabled, the plugin automatically detects pages with third-party embeds (YouTube, Vimeo, etc.) and only shows the cookie banner on those pages.', 'gdpr-consent'); ?></p>
                    <p><strong><?php echo esc_html__('Benefits:', 'gdpr-consent'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Reduces unnecessary cookie notifications', 'gdpr-consent'); ?></li>
                        <li><?php echo esc_html__('Better user experience', 'gdpr-consent'); ?></li>
                        <li><?php echo esc_html__('Automatically adapts to your content', 'gdpr-consent'); ?></li>
                        <li><?php echo esc_html__('GDPR compliant - only asks when needed', 'gdpr-consent'); ?></li>
                    </ul>
                    
                    <h4><?php echo esc_html__('Categories:', 'gdpr-consent'); ?></h4>
                    <ul>
                        <li><strong><?php echo esc_html__('Functional:', 'gdpr-consent'); ?></strong> <?php echo esc_html__('Always enabled, essential for website operation', 'gdpr-consent'); ?></li>
                        <li><strong><?php echo esc_html__('Statistics:', 'gdpr-consent'); ?></strong> <?php echo esc_html__('Google Analytics, tracking pixels (optional)', 'gdpr-consent'); ?></li>
                        <li><strong><?php echo esc_html__('Marketing:', 'gdpr-consent'); ?></strong> <?php echo esc_html__('Facebook Pixel, Google Ads (optional)', 'gdpr-consent'); ?></li>
                        <li><strong><?php echo esc_html__('Embedded Media:', 'gdpr-consent'); ?></strong> <?php echo esc_html__('YouTube, Vimeo, social media embeds (optional)', 'gdpr-consent'); ?></li>
                    </ul>
                    
                    <h4><?php echo esc_html__('Category Visibility:', 'gdpr-consent'); ?></h4>
                    <p><?php echo esc_html__('In the settings below, you can choose which categories to show to users. Only show categories your website actually uses for a cleaner user experience.', 'gdpr-consent'); ?></p>
                </div>
            </div>
            
            <div class="postbox">
                <h3 class="hndle"><?php echo esc_html__('Example Scripts', 'gdpr-consent'); ?></h3>
                <div class="inside">
                    <h4><?php echo esc_html__('Google Analytics (Statistics):', 'gdpr-consent'); ?></h4>
                    <textarea readonly rows="6" style="width: 100%; font-family: monospace; font-size: 0.75rem;">
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'GA_MEASUREMENT_ID');
</script></textarea>
                    
                    <h4><?php echo esc_html__('Facebook Pixel (Marketing):', 'gdpr-consent'); ?></h4>
                    <textarea readonly rows="8" style="width: 100%; font-family: monospace; font-size: 0.75rem;">
<!-- Facebook Pixel -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', 'YOUR_PIXEL_ID');
fbq('track', 'PageView');
</script></textarea>
                </div>
            </div>
            
            <div class="postbox">
                <h3 class="hndle"><?php echo esc_html__('Developer Options', 'gdpr-consent'); ?></h3>
                <div class="inside">
                    <h4><?php echo esc_html__('Debug Mode:', 'gdpr-consent'); ?></h4>
                    <p><?php echo esc_html__('Add this line to your wp-config.php file to always show the cookie banner during development:', 'gdpr-consent'); ?></p>
                    <textarea readonly rows="1" style="width: 100%; font-family: monospace; font-size: 0.75rem;">define('GDPR_CONSENT_DEBUG', true);</textarea>
                    
                    <h4><?php echo esc_html__('Current Status:', 'gdpr-consent'); ?></h4>
                    <ul>
                        <li><strong><?php echo esc_html__('Debug Mode:', 'gdpr-consent'); ?></strong> 
                            <?php echo (defined('GDPR_CONSENT_DEBUG') && GDPR_CONSENT_DEBUG) ? 
                                '<span style="color: #d63638;">' . esc_html__('Enabled', 'gdpr-consent') . '</span>' : 
                                '<span style="color: #00a32a;">' . esc_html__('Disabled', 'gdpr-consent') . '</span>'; ?>
                        </li>
                        <li><strong><?php echo esc_html__('Smart Detection:', 'gdpr-consent'); ?></strong> 
                            <?php 
                            $options = get_option('gdpr_consent_scripts', array());
                            $smart_detect = isset($options['smart_detect']) && $options['smart_detect'];
                            echo $smart_detect ? 
                                '<span style="color: #00a32a;">' . esc_html__('Enabled', 'gdpr-consent') . '</span>' : 
                                '<span style="color: #d63638;">' . esc_html__('Disabled', 'gdpr-consent') . '</span>'; 
                            ?>
                        </li>
                        <li><strong><?php echo esc_html__('Current Page Has Embeds:', 'gdpr-consent'); ?></strong> 
                            <?php 
                            $gdpr_manager = new GDPR_Consent_Manager();
                            $has_embeds = $gdpr_manager->has_third_party_embeds();
                            echo $has_embeds ? 
                                '<span style="color: #d63638;">' . esc_html__('Yes', 'gdpr-consent') . '</span>' : 
                                '<span style="color: #00a32a;">' . esc_html__('No', 'gdpr-consent') . '</span>'; 
                            ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.gdpr-admin-content {
    display: flex;
    gap: 1.25rem;
}

.gdpr-admin-main {
    flex: 2;
}

.gdpr-admin-sidebar {
    flex: 1;
    max-width: 21.875rem;
}

.gdpr-admin-sidebar .postbox {
    margin-bottom: 1.25rem;
}

.gdpr-admin-sidebar .inside {
    padding: 0.9375rem;
}

.gdpr-admin-sidebar h4 {
    margin-top: 0.9375rem;
    margin-bottom: 0.5rem;
}

.gdpr-admin-sidebar ul,
.gdpr-admin-sidebar ol {
    margin-left: 1.25rem;
}

.gdpr-admin-sidebar li {
    margin-bottom: 0.3125rem;
}
</style>
