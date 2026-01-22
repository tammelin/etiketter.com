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
        add_filter('use_block_editor_for_post', [$this, 'disable_gutenberg'], 10, 2);
        // WooCommerce cart item meta hooks
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'get_item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);
    }

    // Add a rewrite rule that will match against /app/.*
    // This will allow us to serve our Vue app on any URL that starts with /app/
    public function rewrite_rule(): void {
        add_rewrite_rule(
            '^etiketter/.*',
            'index.php?pagename=etiketter',
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

    public function disable_gutenberg(bool $use_block_editor, WP_Post $post): bool {
        if ($post->post_type === 'sticker') {
            return false;
        }
        return $use_block_editor;
    }

    function get_form_fields(): array {
        $fields = [
            'sizes' => get_field('sizes', 'option'),
            'colors' => get_field('colors', 'option'),
            'symbols' => get_field('symbols', 'option'),
        ];
        return $fields;
    }

    function post_submit_sticker(WP_REST_Request $request): array {
        $data = $request->get_json_params();
        $uuid = wp_generate_uuid4();
        $post_id = wp_insert_post([
            'post_title' => 'Sticker Order',
            'post_content' => json_encode($data),
            'post_status' => 'publish',
            'post_type' => 'sticker',
            'meta_input' => [
                'sticker_uuid' => $uuid,
            ]
        ]);
        if(is_wp_error($post_id)) {
            return [
                'status' => 'error',
                'message' => $post_id->get_error_message(),
            ];
        }
        // Add WC product with slug 'etikett' to cart with meta 'sticker_uuid' => $uuid
        $product_id = wc_get_product_id_by_sku('etikett');
        return [
            'status' => 'success',
            'data' => $data,
            'post_id' => $post_id,
            'sticker_uuid' => $uuid,
            'product_id' => $product_id,
        ];
    }

    // Capture sticker_uuid from add-to-cart request and store in cart item
    function add_cart_item_data(array $cart_item_data, int $product_id): array {
        if (isset($_REQUEST['sticker_uuid'])) {
            $cart_item_data['sticker_uuid'] = sanitize_text_field($_REQUEST['sticker_uuid']);
        }
        return $cart_item_data;
    }

    // Display sticker_uuid in cart (optional)
    function get_item_data(array $item_data, array $cart_item): array {
        if (isset($cart_item['sticker_uuid'])) {
            $item_data[] = [
                'key' => 'Etikett',
                'value' => $cart_item['sticker_uuid'],
            ];
        }
        return $item_data;
    }

    // Save sticker_uuid to order item meta on checkout
    function add_order_item_meta($item, $cart_item_key, $values, $order): void {
        if (isset($values['sticker_uuid'])) {
            $item->add_meta_data('sticker_uuid', $values['sticker_uuid']);
        }
    }

    public function get_sticker(WP_REST_Request $request): array {
        $uuid = $request->get_param('uuid') ?? '';
        $posts = get_posts([
            'post_type' => 'sticker',
            'meta_key' => 'sticker_uuid',
            'meta_value' => $uuid,
            'meta_compare' => '=',
            'posts_per_page' => 1,
        ]);
        if (empty($posts)) {
            return [
                'status' => 'error',
                'message' => 'Sticker not found',
            ];
        }
        $post = $posts[0];
        return [
            'status' => 'success',
            'post_id' => $post->ID,
            'data' => json_decode($post->post_content, true),
        ];
    }

    function register_routes(): void {
        register_rest_route('templ-stickers/v1', '/form-fields', [
            'methods' => 'GET',
            'callback' => [$this, 'get_form_fields'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('templ-stickers/v1', '/submit-sticker', [
            'methods' => 'POST',
            'callback' => [$this, 'post_submit_sticker'],
            'permission_callback' => '__return_true', // TODO: Validate nonce
        ]);
        register_rest_route('templ-stickers/v1', '/sticker/(?P<uuid>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_sticker'],
            'permission_callback' => '__return_true',
        ]);
    }

}
new Templ_Stickers();
