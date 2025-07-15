# GDPR Consent Manager - Smart Detection Update

## 🚀 Nieuwe Features

### 1. **Slimme Detectie (Smart Detection)**
De plugin detecteert nu automatisch of een pagina third-party embeds bevat en toont alleen dan de cookie banner.

#### Voordelen:
- ✅ Geen onnodige cookie-meldingen voor bezoekers
- ✅ Betere gebruikerservaring
- ✅ Automatische aanpassing aan je content
- ✅ GDPR compliant - vraagt alleen wanneer nodig

#### Ondersteunde Platforms:
- **Video:** YouTube, Vimeo, Dailymotion, Wistia, Brightcove
- **Social Media:** Twitter/X, Instagram, Facebook, TikTok, LinkedIn, Pinterest
- **Audio:** Spotify, SoundCloud, Apple Music
- **Analytics:** Google Analytics, Google Tag Manager, Facebook Pixel
- **Maps:** Google Maps
- **WordPress:** [embed], [video], [audio] shortcodes

### 2. **Debug Modus voor Developers**
Voeg toe aan je `wp-config.php`:
```php
define('GDPR_CONSENT_DEBUG', true);
```

Dit zorgt ervoor dat de cookie banner altijd zichtbaar is tijdens ontwikkeling.

### 3. **Verbeterde Admin Interface**
- Status indicator (Debug Mode, Smart Detection, Current Page Has Embeds)
- Duidelijke instructies
- Meer voorbeelden

## 🛠️ Gebruik

### Standaard Gebruik (Aanbevolen)
1. Ga naar **Instellingen > GDPR Consent**
2. Vink **"Only show cookie banner when third-party embeds are detected"** aan
3. Voeg je tracking scripts toe in de juiste categorieën
4. Klik op **"Save Changes"**

Nu toont de plugin alleen de cookie banner op pagina's met third-party content!

### Altijd Tonen (Oude Gedrag)
1. Ga naar **Instellingen > GDPR Consent**
2. Laat **"Smart Detection"** uitgevinkt
3. De banner wordt altijd getoond

### Development/Testing
1. Voeg `define('GDPR_CONSENT_DEBUG', true);` toe aan `wp-config.php`
2. De banner wordt altijd getoond, ongeacht instellingen

## 📊 Status Controle

In de admin interface zie je:
- **Debug Mode:** Enabled/Disabled
- **Smart Detection:** Enabled/Disabled  
- **Current Page Has Embeds:** Yes/No

## 🔧 Technische Details

### Detectie Logica
De plugin controleert:
1. Post/page content op embed URLs
2. WordPress shortcodes ([embed], [video], etc.)
3. iframe tags met third-party sources
4. Script tags met tracking/analytics URLs

### Performance
- Minimale impact: detectie gebeurt alleen op pagina load
- Geen extra database queries
- Caching-vriendelijk

## 🎯 Resultaat

✅ **Plugin staat standaard aan**  
✅ **Laadt alleen scripts en banner wanneer nodig**  
✅ **Klant hoeft niets te doen**  
✅ **Developer behoudt controle met debug mode**  
✅ **Betere UX - minder onnodige cookie-meldingen**  
✅ **GDPR compliant - vraagt alleen consent wanneer relevant**

## 📝 Migratiepad

Bestaande installaties:
- Smart Detection is standaard **uitgeschakeld**
- Bestaand gedrag blijft hetzelfde
- Klanten kunnen optioneel Smart Detection inschakelen

Nieuwe installaties:
- Smart Detection is standaard **ingeschakeld**
- Optimale gebruikerservaring vanaf dag één

---

## Originele Features

A comprehensive WordPress plugin for GDPR/ePrivacy compliance with cookie consent management, script blocking, and YouTube embed control.

## Features

- **Automatic Cookie Banner**: Displays via `wp_footer` without shortcodes
- **Modal Settings**: Granular control over cookie categories
- **Script Blocking**: Prevents analytics/marketing scripts until consent
- **YouTube Embed Management**: Blocks embeds until consent, shows clickable placeholders
- **6-Month Expiry**: Automatically re-prompts for consent after 6 months
- **Fully Accessible**: ARIA labels, focus traps, keyboard navigation
- **WPML Ready**: All strings translatable with `__()` function
- **Vanilla JavaScript**: No jQuery dependency
- **Caching Compatible**: Minimal dynamic output

## Cookie Categories

1. **Functional**: Always enabled, essential for website operation
2. **Statistics**: Google Analytics, tracking pixels, user behavior analysis
3. **Marketing**: Facebook Pixel, Google Ads, advertising networks
4. **Embedded Media**: YouTube videos, Vimeo, social media embeds

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Go to Settings > GDPR Consent to configure scripts

## Configuration

### Adding Scripts

Navigate to **Settings > GDPR Consent** in your WordPress admin:

1. **Statistics Scripts**: Add Google Analytics, tracking pixels
2. **Marketing Scripts**: Add Facebook Pixel, Google Ads code
3. **Embedded Media Scripts**: Add any scripts required for embeds

#### Example: Google Analytics (Statistics)
```html
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'GA_MEASUREMENT_ID');
</script>
```

#### Example: Facebook Pixel (Marketing)
```html
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
</script>
```

### YouTube Embed Handling

The plugin automatically detects YouTube iframes and:
- Replaces them with clickable placeholders if no consent
- Shows placeholders with "Click to load YouTube video" text
- Restores original embeds when consent is given

## JavaScript API

The plugin provides a JavaScript API for advanced usage:

```javascript
// Check if user has consented to a category
if (window.gdprHasConsent('statistics')) {
    // Load additional analytics
}

// Get all consent data
const consent = window.gdprGetConsent();
console.log(consent); // { functional: true, statistics: false, ... }

// Show settings modal programmatically
window.gdprShowSettings();
```

## Accessibility Features

- **ARIA Labels**: All elements properly labeled
- **Focus Management**: Focus trap in modal, proper focus restoration
- **Keyboard Navigation**: Full keyboard support (Tab, Enter, Escape)
- **Screen Reader Support**: Semantic HTML with proper roles
- **High Contrast Support**: Respects user preferences

## Multilingual Support

The plugin is WPML-ready with all strings wrapped in `__()` functions:

- Text domain: `gdpr-consent`
- POT file included for translations
- Example Dutch (nl_NL) translation provided

### Adding Translations

1. Copy `/languages/gdpr-consent.pot` to your language file
2. Translate strings using Poedit or similar tools
3. Save as `gdpr-consent-{locale}.po` in the languages folder

## Consent Storage

Consent is stored in browser localStorage with:
- Key: `gdprConsent`
- Expiry: 6 months (configurable via `CONSENT_EXPIRY_MONTHS`)
- Format: JSON with categories and timestamp

## Caching Compatibility

The plugin is designed to work with caching plugins:
- Static HTML output (no dynamic PHP in frontend)
- JavaScript handles all consent logic
- No admin-ajax calls that bypass cache

## Security

- All script inputs sanitized with `wp_kses_post()`
- Safe script injection using DOM methods
- XSS protection through proper escaping
- No eval() or innerHTML with user content

## File Structure

```
/gdpr-consent/
├── gdpr-consent.php          # Main plugin file
├── includes/
│   └── settings-page.php     # Admin settings page
├── assets/
│   ├── css/
│   │   └── banner.css        # Banner and modal styles
│   └── js/
│       └── banner.js         # Consent management logic
└── languages/
    ├── gdpr-consent.pot      # Translation template
    └── gdpr-consent-nl_NL.po # Dutch translation
```

## Browser Support

- Modern browsers (ES6+ support required)
- IE11+ (with polyfills if needed)
- Mobile browsers (responsive design)
- Screen readers and assistive technology

## Legal Compliance

This plugin helps with GDPR/ePrivacy compliance but:
- You must add proper privacy policy links
- Review scripts to ensure they comply with regulations
- Consider consulting legal experts for full compliance
- Test thoroughly in your specific use case

## Changelog

### 1.0.0
- Initial release
- Full GDPR consent management
- YouTube embed blocking
- Multilingual support
- Accessibility features
- Admin settings interface

## Support

For support, customization, or additional features, contact the development team.
