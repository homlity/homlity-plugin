=== Homlity Real Estate ===
Contributors: homlity
Tags: real estate, property, listings, agents, property management
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.5.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real estate plugin to manage property listings, agents, currencies, and multi-country configurations in WordPress.

== Description ==

**Homlity Real Estate** is a WordPress plugin built for the real estate industry. It allows agencies, brokers, and independent agents to create and manage professional property websites quickly and efficiently.

Designed for multi-country and multi-currency operation, it adapts to the needs of real estate professionals across Latin America, Spain, and beyond.

### Key Features

- **Custom property post type** for structured listing management.
- **Multi-country support** with country, city, zone, and neighborhood fields.
- **Multi-currency support** (COP, MXN, EUR, USD, and more).
- **Fully customizable**:
  - Property types (houses, apartments, offices, retail, land, warehouses, etc.).
  - Operation types (sale, rent, short-term, transfer, etc.).
  - Custom attributes (bedrooms, bathrooms, parking, area, amenities, etc.).
  - Property status (available, reserved, sold, rented, etc.).
- **Agent management**: link properties to agents with per-property contact details.
- **Real estate SEO**: clean URLs, metadata-ready structure, and JSON-LD/schema-friendly field layout.
- **Page builder integrations**: Elementor widgets, Divi module, WPBakery, and shortcodes.
- **Extensible architecture**: hooks, filters, and modular structure for ERP and CRM integrations.

### About Homlity

Homlity is a digital ecosystem focused on real estate web solutions, specializing in property website development, custom web applications, and integrations with real estate systems and ERPs.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the WordPress admin panel.
2. Activate the plugin from the **Plugins** menu.
3. Go to the **Real Estate** or **Properties** menu in the admin dashboard.
4. Configure your countries, currencies, property types, and operation types.
5. Create your first property and publish it.

== Frequently Asked Questions ==

= Is this plugin limited to a specific country? =

No. The plugin supports multiple countries and regions. You can configure countries, cities, and zones to match your market.

= Can I use multiple currencies? =

Yes. The plugin supports multiple currencies (COP, MXN, USD, EUR, etc.) and displays values based on your site configuration.

= Is it compatible with any WordPress theme? =

The plugin follows WordPress coding standards and works with most themes. For best results, use a modern or custom-built theme.

= Can I customize property types and operation types? =

Yes. You can define your own property types (house, apartment, lot, office, etc.) and operation types (sale, rent, seasonal, etc.) to match your business model.

= Is the plugin translation-ready? =

Yes. The plugin uses standard WordPress translation mechanisms (.po/.mo files) and works with WPML and Polylang.

= Who develops and maintains this plugin? =

Developed and maintained by **Ecosistema Inmobiliario Homlity**, a team specializing in real estate websites and digital solutions for the property sector.

== Screenshots ==

1. Property listing in the WordPress admin dashboard.
2. Property creation and editing form.
3. Configuration of property types, operation types, and attributes.
4. Public property detail page.
5. Public property listing on the WordPress site.

== Changelog ==

= 12.0.3 =
* WordPress.org compliance: readme translated to English.
* Reduced tags to 5.
* Updated "Tested up to" to WordPress 6.9.
* Plugin name changed to Homlity Real Estate.

= 1.0.0 =
* Initial release.
* Basic property management.
* Property types, operation types, and custom attributes.
* Multi-country and multi-currency support.
* Basic WordPress admin panel integration.

== Third-Party Libraries ==

This plugin bundles the following open-source libraries:

* **GLightbox** — MIT License. Lightbox gallery for property photos.
  https://github.com/biati-digital/glightbox
* **Guzzle HTTP** (guzzlehttp/guzzle) — MIT License. HTTP client used for CRM integrations.
  https://github.com/guzzle/guzzle
* **Guzzle Promises** (guzzlehttp/promises) — MIT License. Async promise support for Guzzle.
  https://github.com/guzzle/promises
* **Guzzle PSR-7** (guzzlehttp/psr7) — MIT License. PSR-7 HTTP message implementation.
  https://github.com/guzzle/psr7
* **Dompdf** (dompdf/dompdf) — LGPL-2.1 License. Generates PDF technical sheets for properties.
  https://github.com/dompdf/dompdf
* **PHP Font Library** (dompdf/php-font-lib) — LGPL-2.1-or-later License. Font handling for Dompdf.
  https://github.com/dompdf/php-font-lib
* **PHP SVG Library** (dompdf/php-svg-lib) — LGPL-3.0-or-later License. SVG rendering for Dompdf.
  https://github.com/dompdf/php-svg-lib
* **HTML5 PHP** (masterminds/html5) — MIT License. HTML5 parser used by Dompdf.
  https://github.com/Masterminds/html5-php
* **PSR HTTP Client** (psr/http-client) — MIT License. HTTP client interface.
  https://github.com/php-fig/http-client
* **PSR HTTP Factory** (psr/http-factory) — MIT License. HTTP factory interface.
  https://github.com/php-fig/http-factory
* **PSR HTTP Message** (psr/http-message) — MIT License. HTTP message interface.
  https://github.com/php-fig/http-message
* **PHP CSS Parser** (sabberworm/php-css-parser) — MIT License. CSS parsing for Dompdf.
  https://github.com/sabberworm/PHP-CSS-Parser

All MIT-licensed libraries are GPL-compatible. LGPL-licensed libraries (Dompdf, php-font-lib, php-svg-lib) are used as independent components and are compatible with GPL-2.0-or-later under the LGPL linking exception.

== External Services ==

This plugin may connect to the following external services:

= WhatsApp (Meta Platforms, Inc.) =
Used to generate contact links that open a WhatsApp conversation with a property agent, and to generate share links so visitors can send a property listing via WhatsApp.
* **Data sent:** The agent's phone number and an optional pre-filled text message (property title and URL) are included in the link URL. No data is sent to WhatsApp servers unless the visitor clicks the link.
* **When:** The link is generated on every property detail page that has an agent with a phone number configured. Data is only transmitted to WhatsApp when the visitor clicks the button.
* **Service provider:** Meta Platforms, Inc.
* **Privacy policy:** https://www.whatsapp.com/legal/privacy-policy
* **Terms of service:** https://www.whatsapp.com/legal/terms-of-service

= Google Maps (Google LLC) =
Used to display an interactive map of the property location, a Street View preview, and a "Get directions" link on property detail pages.
* **Data sent:** The property's geographic coordinates (latitude and longitude) stored by the site administrator. The visitor's IP address and browser information may be collected by Google when the map or Street View iframe loads.
* **When:** Only on property detail pages where geographic coordinates have been saved. The map iframe is loaded automatically when the visitor opens the page.
* **Service provider:** Google LLC.
* **Privacy policy:** https://policies.google.com/privacy
* **Terms of service:** https://developers.google.com/maps/terms-20180207

= YouTube (Google LLC) =
Used to embed property video tours directly on property detail pages.
* **Data sent:** The YouTube video ID is included in the iframe URL. The visitor's IP address and browser information may be collected by YouTube/Google when the iframe loads.
* **When:** Only on property detail pages that have a YouTube video URL saved. The iframe is loaded automatically when the visitor opens the page.
* **Service provider:** Google LLC.
* **Privacy policy:** https://policies.google.com/privacy
* **Terms of service:** https://www.youtube.com/t/terms

= OpenStreetMap Nominatim (Geocoding) =
Used to convert property addresses into geographic coordinates (latitude/longitude).
* **Data sent:** Street address, neighborhood, city, state/region, and country entered by the site administrator.
* **When:** Only when the administrator explicitly clicks the "Geocode" button in the property editor. No data is sent automatically.
* **Service:** Nominatim geocoding API, operated by the OpenStreetMap Foundation.
* **Privacy policy:** https://osmfoundation.org/wiki/Privacy_Policy
* **Terms of use:** https://operations.osmfoundation.org/policies/nominatim/

= CKEditor 5 (CKSource / Mateusz Bukowski Foundation) =
Used to provide a rich-text description editor for property listings inside the WordPress admin dashboard.
* **Data sent:** No property or user data is sent to CKEditor's servers. The browser downloads the CKEditor 5 JavaScript library file from the CDN when an administrator opens the property editor. The download request includes the visitor's IP address and browser information as standard HTTP headers.
* **When:** Only when an administrator opens the property editing screen in wp-admin. End users on the public website are never affected.
* **URL loaded:** https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js
* **Service provider:** CKSource Sp. z o.o. (operating as Mateusz Bukowski Foundation).
* **Privacy policy:** https://ckeditor.com/legal/privacy-policy/
* **Terms of service:** https://ckeditor.com/legal/ckeditor-oss-license/

= CRM Integrations (Optional) =
When a CRM integration is configured and enabled by the site administrator, the plugin synchronizes property data with external real estate CRM platforms.
* **Data sent:** Property details (title, description, price, location, media), agent information.
* **When:** Only when the administrator enables and configures a CRM connection. Disabled by default.
* **Note:** The specific privacy policy and terms of service depend on the CRM provider configured by the site administrator.

= Analytics / Visit Tracking (Optional) =
The plugin includes optional built-in analytics to count property visits, contact clicks, and PDF downloads. This feature is **disabled by default** and must be explicitly enabled by the site administrator.
* **Data stored:** Anonymized visitor identifier (random cookie), hashed IP address (SHA-256), hashed user-agent string (SHA-256), and event timestamps. All data is stored locally in the WordPress database and is never transmitted to external servers.
* **When:** Only when the "Enable Analytics" option is active in the plugin settings. The plugin respects the WP Consent API if a compatible consent plugin is installed.
* **Important:** When enabling this feature, site administrators are responsible for obtaining visitor consent in accordance with applicable privacy laws (GDPR, CCPA, etc.) and updating their site's privacy policy accordingly.

== Upgrade Notice ==

= 12.0.3 =
Maintenance release with WordPress.org compliance fixes.
