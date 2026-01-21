<?php
/**
 * Plugin Name: Templ Etiketter
 * Description: Etikettgenerator för WordPress.
 * Update URI: null
 */

// Define the plugin directory
define('TEMPL_STICKERS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TEMPL_STICKERS_PLUGIN_URL', plugin_dir_url(__FILE__));

class Templ_Stickers {

    public function __construct() {
        add_action('init', [$this, 'rewrite_rule']);
        add_shortcode('templ-stickers', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_scripts']);
        add_filter('script_loader_tag', [$this, 'script_loader_tag'], 10, 3);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    // Add a rewrite rule that will match against /app/.*
    // This will allow us to serve our Vue app on any URL that starts with /app/
    public function rewrite_rule(): void {
        add_rewrite_rule(
            '^app/.*',
            'index.php?pagename=app',
            'top'
        );
    }

    // The shortcode will output the Vue app container
    public function shortcode(): string {
        wp_enqueue_script('templ-stickers');
        wp_enqueue_style('templ-stickers');
        return '<div id="app"></div>';
    }

    public function register_scripts(): void {
        // Find file like index-*.js in vue/dist/assets/ folder
        $files = glob(TEMPL_STICKERS_PLUGIN_DIR . '/vue/dist/assets/index-*.js');
        foreach($files as $file) {
            $file_url = str_replace(TEMPL_STICKERS_PLUGIN_DIR, TEMPL_STICKERS_PLUGIN_URL, $file);
            wp_register_script('templ-stickers', $file_url, [], null, true);
        }
        // Find file like index-*.css in vue/dist/assets/ folder
        $files = glob(TEMPL_STICKERS_PLUGIN_DIR . '/vue/dist/assets/index-*.css');
        foreach($files as $file) {
            $file_url = str_replace(TEMPL_STICKERS_PLUGIN_DIR, TEMPL_STICKERS_PLUGIN_URL, $file);
            wp_register_style('templ-stickers', $file_url, [], null);
        }
    }

    // Add type="module" to script tag
    public function script_loader_tag(string $tag, string $handle, string $src): string {
        if($handle == 'templ-stickers') {
            $tag = '<script src="' . esc_url( $src ) . '" type="module"></script>';
        }
        return $tag;
    }

    function get_form_fields(): array {
        $fields = [
            'sizes' => get_field('sizes', 'option'),
            'colors' => get_field('colors', 'option'),
            'symbols' => get_field('symbols', 'option'),
        ];
        return $fields;
    }

    function register_routes(): void {
        register_rest_route('templ-stickers/v1', '/form-fields', [
            'methods' => 'GET',
            'callback' => [$this, 'get_form_fields'],
            'permission_callback' => '__return_true',
        ]);
    }

}
new Templ_Stickers();
