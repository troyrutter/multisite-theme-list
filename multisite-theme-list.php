<?php
/*
Plugin Name: Multisite Theme List
Description: A plugin to list every subsite in a WordPress multisite, displaying each site's name, URL, active theme, and a summary of theme usage across all sites.
Version: 1.0
Author: Troy Rutter
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Multisite_Theme_List {

    public function __construct() {
        add_action('network_admin_menu', array($this, 'add_admin_menu'));
    }

    public function add_admin_menu() {
        add_submenu_page(
           'themes.php', // Parent slug under Network Admin > Themes
            'Multisite Theme List', // Page title
            'Multisite Theme List', // Menu title
            'manage_network', // Capability required for network admins
            'multisite-theme-list', // Menu slug
            array($this, 'display_multisite_list') // Callback function
        );
    }

    public function display_multisite_list() {
        if (!is_multisite()) {
            echo '<div class="notice notice-error"><p>This plugin only works on multisite installations.</p></div>';
            return;
        }

        // Allow access on the initial load without a nonce
        if (isset($_GET['sort_by']) || isset($_GET['order'])) {

            // Unslash and sanitize the nonce before verifying
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'multisite_sort_nonce')) {
                wp_die(esc_html__('Unauthorized request.', 'multisite-theme-list'));
            }
        }

        // Get the current sorting options from query parameters
        $sort_by = isset($_GET['sort_by']) ? sanitize_text_field(wp_unslash($_GET['sort_by'])) : 'name';
        $order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'asc';
        $indicator = $order === 'asc' ? ' ↑' : ' ↓';
        $sites = get_sites();
        
        if (empty($sites)) {
            echo '<div class="notice notice-info"><p>No subsites found.</p></div>';
            return;
        }
        
        // Sort sites based on the selected column and order
        $sites = get_sites();
        usort($sites, function($a, $b) use ($sort_by, $order) {
            switch ($sort_by) {
                case 'url':
                $result = strcmp(get_site_url($a->blog_id), get_site_url($b->blog_id));
                break;
                case 'theme':
                $theme_a = wp_get_theme($a->blog_id)->get('Name');
                $theme_b = wp_get_theme($b->blog_id)->get('Name');
                $result = strcmp($theme_a, $theme_b);
                break;
                case 'name':
                default:
                $result = strcmp(get_blog_details($a->blog_id)->blogname, get_blog_details($b->blog_id)->blogname);
            }
            return $order === 'desc' ? -$result : $result;
        });

        // Determine the opposite order for the next click
        $next_order = $order === 'asc' ? 'desc' : 'asc';

        $theme_count = []; // Array to store theme usage counts

        echo '<div class="wrap">';
        echo '<h1>Multisite Theme List</h1>';
        echo '<table id="multisite-theme-table" class="widefat fixed striped">';
        echo '<thead><tr>';
         // Display each column header with sorting links and indicators
        echo '<th><a href="' . esc_url(add_query_arg(['sort_by' => 'name', 'order' => $next_order, '_wpnonce' => wp_create_nonce('multisite_sort_nonce')])) . '">Site Name' . ($sort_by === 'name' ? esc_html($indicator) : '') . '</a></th>';
        echo '<th><a href="' . esc_url(add_query_arg(['sort_by' => 'url', 'order' => $next_order, '_wpnonce' => wp_create_nonce('multisite_sort_nonce')])) . '">Site URL' . ($sort_by === 'url' ? esc_html($indicator) : '') . '</a></th>';
        echo '<th><a href="' . esc_url(add_query_arg(['sort_by' => 'theme', 'order' => $next_order, '_wpnonce' => wp_create_nonce('multisite_sort_nonce')])) . '">Active Theme' . ($sort_by === 'theme' ? esc_html($indicator) : '') . '</a></th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($sites as $site) {
            $site_id = $site->blog_id;
            $site_name = get_blog_details($site_id)->blogname;
            $site_url = get_site_url($site_id);

            // Switch to the subsite to get its current theme
            switch_to_blog($site_id);
            $theme = wp_get_theme();
            restore_current_blog();

            $theme_name = $theme->get('Name') . ' - ' . $theme->get('Version');

            // Increment the count for this theme in the array
            if (!isset($theme_count[$theme_name])) {
                $theme_count[$theme_name] = 0;
            }
            $theme_count[$theme_name]++;

            echo '<tr>';
            echo '<td>' . esc_html($site_name) . '</td>';
            echo '<td><a href="' . esc_url($site_url) . '" target="_blank">' . esc_html($site_url) . '</a></td>';
            echo '<td>' . esc_html($theme_name) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        // Display theme summary
        echo '<h2>Theme Usage Summary</h2>';
        echo '<ul>';
        foreach ($theme_count as $theme => $count) {
            echo '<li>' . esc_html($theme) . ': ' . esc_html($count) . ' site(s)</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
}

function multisite_theme_list_init() {
    new Multisite_Theme_List();
}
add_action('plugins_loaded', 'multisite_theme_list_init');
