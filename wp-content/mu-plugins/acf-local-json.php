<?php

function my_acf_json_save_point( $path ) {
    $dir = WP_CONTENT_DIR . '/acf-json';
    // Create directory if it doesn't exist
    if(!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}
add_filter( 'acf/settings/save_json', 'my_acf_json_save_point' );

function custom_acf_json_filename( $filename, $post, $load_path ) {
    $filename = str_replace(
        array(
            ' ',
            '_',
        ),
        array(
            '-',
            '-'
        ),
        $post['title']
    );

    $filename = strtolower( $filename ) . '.json';

    return $filename;
}
add_filter( 'acf/json/save_file_name', 'custom_acf_json_filename', 10, 3 );

function my_acf_json_load_point( $paths ) {
    // Remove the original path (optional).
    unset($paths[0]);

    // Append the new path and return it.
    $paths[] = WP_CONTENT_DIR . '/acf-json';

    return $paths;
}
add_filter( 'acf/settings/load_json', 'my_acf_json_load_point' );
