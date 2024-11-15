<?php
/**
    * Plugin Name: Free Image CDN
    * Plugin URI: https://garridodiaz.com/free-image-cdn-for-wordpress/
    * Description: Speed your loading site and images using a Free Image CDN powered by wsrv.nl service
    * Version: 1.1
    * Author: Chema
    * Author URI: https://garridodiaz.com
    * License: GPL2
*/
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * freeImageCDN class
 *
 * inspired by Zachary Scott <zac@zacscott.net>
 * https://gist.github.com/zacscott/abb94e6289bcd129f4e7ba2680d65290
 */
class freeImageCDN {
    const MAIN_FILE = __FILE__;
    static $content_url;

    // Define your CDN URL and image extensions as static properties
    private static $cdn_url = "wsrv.nl/?url=";
    private static $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp','tiff', 'svg');

    public function __construct() {

        // Get the content directory URL minus the http://
        self::$content_url = str_replace( ['http://','https://'], '', content_url() );
        add_filter('plugin_row_meta', [$this, 'addPluginRowMeta'], 10, 2);

        if (!$this->is_local_site()) { //change this to FALSE since we are developing
            add_action( 'wp_enqueue_scripts', array( $this, 'dns_prefetch' ) );
            add_action( 'template_redirect', array( $this, 'start_buffering' ) );
        }
    }

    // Adds the DNS prefetch meta fields for the wsrv.nl server
    public function dns_prefetch() {
        wp_enqueue_script( 'dns-prefetch-wsrv', '//wsrv.nl', array(), false, true );
    }

     // Start the output buffering
    public function start_buffering() {
        ob_start( array( $this, 'add_cdn' ) );
    }

    // Adds the CDN URL to any match in the HTML
    public function add_cdn($content) {
        // Define a regular expression pattern to match image links with the full URL format, we search for the content_url including the protocol HTTPS since there might be another CDN in front, like i0.wp.com
        $pattern = '/(' . preg_quote(content_url(), '/') . '\/[^\s"\']+\.(' . implode('|', self::$image_extensions) . '))/i';

        // Define the replacement URL with the full domain
        $replacement = 'https://' . self::$cdn_url . '$1';

        // Use preg_replace to replace all matched image links with the CDN URL
        $content = preg_replace($pattern, $replacement, $content);

        // Remove the redundant 'https://' from the URL, not needed using wsrv for now... but good to have, commented for now since impacts performance.
        //$content = str_replace(self::$cdn_url . content_url(),self::$cdn_url . $this->content_url , $content);

        return $content;
    }

    /**
     * If the site is a local site.
     *
     * @since 1.3.0
     *
     * @return bool
     */
    // Check if the site is a local site
    public function is_local_site() {
        $site_url = site_url();

        // Check for localhost and sites using an IP only first.
        $is_local = $site_url && false === strpos($site_url, '.');

        if ('local' === wp_get_environment_type()) {
            $is_local = true;
        }

        $known_local = array(
            '#\.local$#i',
            '#\.localhost$#i',
            '#\.test$#i',
            '#\.docksal$#i',      // Docksal.
            '#\.docksal\.site$#i', // Docksal.
            '#\.dev\.cc$#i',       // ServerPress.
            '#\.lndo\.site$#i',    // Lando.
            '#^https?://127\.0\.0\.1$#',
        );

        if (!$is_local) {
            foreach ($known_local as $url) {
                if (preg_match($url, $site_url)) {
                    $is_local = true;
                    break;
                }
            }
        }

        return $is_local;
    }

    /**
     * Add links to settings and sponsorship in plugin row meta.
     *
     * @param array $plugin_meta The existing plugin meta.
     * @param string $plugin_file The plugin file path.
     * @return array Modified plugin meta with added links.
     */
    public function addPluginRowMeta($plugin_meta, $plugin_file)
    {
        if (plugin_basename(self::MAIN_FILE) !== $plugin_file) {
            return $plugin_meta;
        }

        $plugin_meta[] = sprintf(
            '<a href="%1$s"><span class="dashicons dashicons-star-filled" aria-hidden="true" style="font-size:14px;line-height:1.3"></span>%2$s</a>',
            'https://paypal.me/chema/10EUR',
            esc_html_x('Sponsor', 'verb', 'free-image-cdn')
        );

        return $plugin_meta;
    }

}

// Boot
new freeImageCDN();
