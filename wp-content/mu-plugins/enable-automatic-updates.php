<?php
/**
 * Plugin Name: Enable Automatic Updates
 * Description: This plugin is used to enable automatic updates for WordPress, which is otherwise disabled because of version control.
 * Author: Templ
 * Author URI: https://templ.io
 */

add_filter('automatic_updates_is_vcs_checkout', function(bool $checkout, string $context) : bool {
    if($context == ABSPATH) {
        $checkout = false;
    }
    return $checkout;
}, 10, 2);
