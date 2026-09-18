=== WC - APG City ===
Contributors: artprojectgroup
Donate link: https://artprojectgroup.es/tienda/donacion
Tags: city, state, postcode, geonames, google maps
Requires at least: 5.1
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 2.1.2
WC requires at least: 5.6
WC tested up to: 11.1.0
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Adds to WooCommerce an automatic city name generated from the postcode.

== Description ==
**WC - APG City** adds to your WooCommerce store a new automatic city field generated from the postcode via the GeoNames API or the Google Maps API.

= Features =
* Fully compatible with the Checkout block in the WordPress block editor.
* Includes a local GeoNames database that is downloaded and updated monthly to improve performance and reduce external API queries.
* You can choose between the GeoNames API and the Google Maps API.
* You must add your own Google Maps API Key or GeoNames username.
* Your API credentials are never sent to the browser: every lookup is made by the server.
* You can customize the default text of the select field.
* You can customize the text of the option used to switch to a text field.
* You can block modifications to the city and province (state) fields.
* You can customize the background color of the locked fields.
* If the postcode is shared by more than one city, the customer can select the correct city name from the list returned by GeoNames or Google Maps.
* If the city is not in the list or cannot be found in either API, the customer can manually enter their city name.
* It also selects the province (state), as long as the name matches the one obtained from GeoNames or Google Maps.

= Translations =
* English: by [Art Project Group](https://artprojectgroup.es/) (default language).
* Spanish: by [Art Project Group](https://artprojectgroup.es/).

= More information =
You can learn more about **WC - APG City** on our [official website](https://artprojectgroup.es/plugins-para-woocommerce/wc-apg-city), and follow the development on [GitHub](https://github.com/artprojectgroup/wc-apg-city).

== Installation ==
1. Install the plugin in one of the following ways:
 * Upload the `wc-apg-city` folder to the `/wp-content/plugins/` directory via FTP.
 * Upload the full ZIP file via *Plugins -> Add New -> Upload* in the WordPress administration panel.
 * Search for **WC - APG City** in *Plugins -> Add New* and click *Install Now*.
2. Activate the plugin through the *Plugins* menu in the WordPress administration panel.
3. Configure the plugin in *WooCommerce -> City field* or through the *Settings* link on the plugins page.

== Frequently Asked Questions ==
= Is configuration needed? =
Just select between the GeoNames API and the Google Maps API, and add your own Google Maps API Key or GeoNames username.

= Which API is better? =
It depends on many factors, but the one that has given us the best results is the GeoNames API. In any case, if no result is found in the selected API, it will search again in the other API.

= Where can I get support? =
**WC - APG City** is a free plugin. **Art Project Group** does not provide free technical support, but offers a paid [technical support](https://artprojectgroup.es/tienda/ticket-de-soporte) service for installation and configuration.

== Screenshots ==
1. Screenshot of WC - APG City.
2. Screenshot of WC - APG City. Billing and shipping forms.
3. Screenshot of WC - APG City. Billing and shipping forms. Checkout block.

== Changelog ==
= 2.1.2 =
* Fixed: on a classic checkout the city field stopped filling in, because the plugin loaded the Checkout block script instead of the classic one.
= 2.1.1 =
* Fixed: a fatal error on WordPress 5.0; the plugin now declares that it needs 5.1.
* The postcode import now rejects a malformed batch instead of sending a broken query.
* Code cleanup so the plugin passes the wordpress.org Plugin Check with no warnings.
= 2.1.0 =
* Security: the Google Maps API Key and the GeoNames username are no longer sent to the browser.
* Security: the customizable texts of the city field can no longer inject code into the checkout.
* Security: postcode and country are validated before every lookup, and lookups are rate limited per IP.
* Security: the downloaded GeoNames file is no longer publicly accessible.
* Fixed: shop managers can now save the settings.
* Fixed: the province is filled in for city names with an apostrophe, and for Austria, France and Portugal with Google Maps.
* Fixed: the province field is released when the customer types the city manually.
* Fixed: the city selector is no longer discarded on load in the Checkout block, which now also works on block themes.
* Fixed: the city field works in browsers that do not report a user agent.
* Fixed: an API outage is no longer cached as "no results".
* Fixed: the local database refills itself if its table is emptied, instead of staying empty for good.
* The city field is no longer altered in the admin area, which the plugin was never meant to change.
* Minor fixes: locked fields color, PHP notices on a fresh install, style flicker and uninstall cleanup.
* Compatible with WordPress 7.2 and WooCommerce 11.1.0.
= 2.0.4 =
* Minor fix.
= 2.0.3 =
* Minor fix.
= 2.0.2 =
* Text and translation fixes.
* Screenshot updated.
= 2.0.1 =
* Added a new field to customize the background color of locked fields.
* Minor fix.
= 2.0.0 =
* Added full compatibility with the Checkout block.
* New local GeoNames database with monthly import. **Update sponsored by [Bestway](https://bestwaystore.es)**.
* General performance improvements.
* Minor fix.
= 1.4.0.1 =
* Minor fix.
= 1.4.0 =
* Added a new field to enter the GeoNames username.
* Complete code adaptation to the security standards required by WordPress.
* Minor fix.
= 1.3.0.3 =
* Minor fix.
= 1.3.0.2 =
* Minor fix.
= 1.3.0.1 =
* Minor fix.
= 1.3 =
* Added new functionality to block fields. **Update sponsored by [Gardiun](https://gardiun.com/)**.
* New JavaScript functions and controls.
* Screenshot updated.
= 1.2.0.2 =
* Header updated.
* Stylesheet updated.
* Screenshot updated.
= 1.2.0.1 =
* Minor fix.
= 1.2 =
* Two new fields have been added to customize texts.
= 1.1.0.2 =
* Minor fix.
= 1.1.0.1 =
* Minor fix.
= 1.1 =
* JavaScript issue fix.
= 1.0.2.1 =
* Minor fix.
= 1.0.2 =
* Minor fix.
= 1.0.1.6 =
* Minor fix.
= 1.0.1.5 =
* Minor fix.
= 1.0.1.4 =
* Minor fix.
= 1.0.1.3 =
* Minor fix.
= 1.0.1.2 =
* Added WooCommerce 3.4 compatibility.
= 1.0.1.1 =
* Header updated.
* Stylesheet updated.
* Screenshot updated.
= 1.0.1 =
* Minor fix.
* New screenshot.
= 1.0 =
* Added Google Maps API Key.
* Added automatic search in both APIs in case of no results in the selected API.
* Added the replacement of the select field by an input field if no results are found in either API.
* Added an option in the select field that replaces it with an input field to let the customer manually enter their city.
* Minor fixes.
= 0.3.6.3 =
* Fixed localization.
= 0.3.6.2 =
* Support for multisite installations.
= 0.3.6.1 =
* Fixed GeoNames API support.
= 0.3.6 =
* GeoNames API support over iThemes Security.
= 0.3.5.1 =
* Avoid running on Microsoft Internet Explorer 11 or earlier.
= 0.3.5 =
* Added JavaScript to My Account form fields.
= 0.3.4 =
* Added select field self-opening with multiple cities.
= 0.3.3 =
* Fixed JavaScript bugs.
= 0.3.2 =
* Cloning original CSS class field.
= 0.3.1 =
* Fixed incompatibility with websites with an SSL certificate.
= 0.3 =
* Added GeoNames API support.
* New settings screen to select the API.
* New screenshot.
= 0.2.1 =
* New call to Google Maps API.
= 0.2 =
* Fixed JavaScript bugs.
* Added state name selection.
= 0.1 =
* Initial version.

== Upgrade Notice ==
= 2.1.2 =
* Fixes the city field on classic checkouts, broken since 2.1.0. Update recommended.
= 2.1.1 =
* Maintenance release. It also fixes a fatal error on WordPress 5.0.
= 2.1.0 =
* Security update: API credentials are no longer exposed to the browser and lookups are validated and rate limited. Update recommended.

== Thanks ==
Thanks to everyone who uses the plugin, helps improve it, makes a donation or encourages us with their comments.

If you find this plugin useful, you can support its development with a [small donation](https://artprojectgroup.es/tienda/donacion).

== External Services ==
1. To the GeoNames services, to download and update, on a monthly basis, the full local database of cities and postcodes, as well as to perform queries to its API when there is no information in the local database.
 - It sends the country and the postcode.
 - More information: https://www.geonames.org/export/

2. To the Google Maps API, to obtain the city and state/province name from the postcode and country when this option is selected in the plugin settings.
 - It sends the country and the postcode.
 - More information: https://policies.google.com/privacy
