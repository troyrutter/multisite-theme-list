<?php
/*
Plugin Name: Multisite Theme List
Description: A plugin to list every subsite in a WordPress multisite, displaying each site's name, URL, active theme, and a summary of theme usage across all sites.
Version: 1.0
Author: Troy Rutter
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Multisite_Theme_List {

    public function __construct() {
        add_action('network_admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
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

    public function enqueue_scripts() {
        // Only enqueue on the plugin's page
        if (isset($_GET['page']) && $_GET['page'] === 'multisite-theme-list') {
            wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css');
            wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', array('jquery'), null, true);

            // Disable pagination
            wp_add_inline_script('datatables-js', 'jQuery(document).ready(function($) { $("#multisite-theme-table").DataTable({ paging: false }); });');
   
        }
    }

    public function display_multisite_list() {
        if (!is_multisite()) {
            echo '<div class="notice notice-error"><p>This plugin only works on multisite installations.</p></div>';
            return;
        }

        $sites = get_sites();
        if (empty($sites)) {
            echo '<div class="notice notice-info"><p>No subsites found.</p></div>';
            return;
        }

        $theme_count = []; // Array to store theme usage counts

        echo '<div class="wrap">';
        echo '<h1>Multisite Theme List</h1>';
        echo '<table id="multisite-theme-table" class="widefat fixed striped">';
        echo '<thead><tr><th>Site Name</th><th>Site URL</th><th>Active Theme and Version</th></tr></thead>';
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
