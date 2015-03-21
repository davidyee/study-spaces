<?php
defined('ABSPATH') OR exit; // do not expose the plugin if the core isn't loaded
/*
 * Plugin Name: ISSS Study Spaces
 * Plugin URI: http://www.isss.ca
 * Description: Plugin for displaying and searching various study locations on campus at the University of Alberta.
 * Author: David Yee
 * Version: 0.0.2
 * Author URI: http://www.davidvyee.com
 * Text Domain: isss-study-spaces-textdomain
 */

define ('ISSS_STUDY_SPACES__PLUGIN_URL', plugin_dir_url(__FILE__));
define ('ISSS_STUDY_SPACES__PLUGIN_DIR', plugin_dir_path(__FILE__));
define ('LOCATIONS', 'ualberta_locations');
define ('STUDY_SPACES', 'study_spaces');
define ('STUDY_TYPE', 'study_type');
define ('DAYS', 'days_open');
define ('PROXIMITY_TO_HUB', 'prox_to_hub');
define ('PROXIMITY_TO_SUB', 'prox_to_sub');
define ('PROXIMITY_TO_LRT', 'prox_to_lrt');
define ('PROXIMITY_TO_HEALTH_SCIENCES', 'prox_to_health_sciences');

require_once(ISSS_STUDY_SPACES__PLUGIN_DIR . 'proximity-meta-box.php');

// activation/deactivation/uninstall hooks based on example from kaiser
// http://wordpress.stackexchange.com/a/25979
register_activation_hook(__FILE__, array('StudySpaces', 'on_activation'));
register_deactivation_hook(__FILE__, array('StudySpaces', 'on_deactivation'));
register_uninstall_hook(__FILE__, array('StudySpaces', 'on_uninstall'));

add_action('plugins_loaded', array('StudySpaces', 'init'));

/**
 * Study spaces class handles the registration of custom post types and taxonomies for use in the WordPress backend.
 */
class StudySpaces
{
    protected static $instance;
    private static $custom_capabilities = array(
        'edit_study_space',
        'read_study_space',
        'delete_study_space',
        'edit_others_study_spaces',
        'publish_study_spaces',
        'edit_study_spaces',
        'read_private_study_spaces',
        'delete_study_spaces',
        'delete_private_study_spaces',
        'delete_published_study_spaces',
        'delete_others_study_spaces',
        'edit_private_study_spaces',
        'edit_published_study_spaces'
    );
    private static $proximities = array(
        array("Proximity to HUB", PROXIMITY_TO_HUB),
        array("Proximity to SUB", PROXIMITY_TO_SUB),
        array("Proximity to University LRT", PROXIMITY_TO_LRT),
        array("Proximity to Health Sciences", PROXIMITY_TO_HEALTH_SCIENCES)
    );

    public static function init()
    {
        is_null(self::$instance) AND self::$instance = new self;
        return self::$instance;
    }

    public static function on_activation()
    {
        if (!current_user_can('activate_plugins'))
            return;
        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("activate-plugin_{$plugin}");

        // define post types for flushing
        self::location_taxonomy();
        self::study_type_taxonomy();
        self::study_space_post_type();
        self::days_open_taxonomy();

        $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Holiday');

        foreach ($days as $day) {
            wp_insert_term($day, DAYS);
        }

        self::add_study_spaces_role();

        self::file_replace();

        // update permalink structure
        // the below updates the permalink
        // see http://codex.wordpress.org/Function_Reference/flush_rewrite_rules
        flush_rewrite_rules(); // update permalink structure

        // uncomment the following line to see the function in action
        // exit( var_dump( $_GET ) );
    }

    public static function on_deactivation()
    {
        if (!current_user_can('activate_plugins'))
            return;
        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("deactivate-plugin_{$plugin}");

        // uncomment the following line to see the function in action
        // exit( var_dump( $_GET ) );
    }

    public static function on_uninstall()
    {
        if (!current_user_can('activate_plugins'))
            return;
        check_admin_referer('bulk-plugins');

        // important: check if the file is the one that was registered during the uninstall hook.
        if (__FILE__ != WP_UNINSTALL_PLUGIN)
            return;

        // uncomment the following line to see the function in action
        // exit( var_dump( $_GET ) );
    }

    /**
     * Constructor will create the taxonomies and custom post type
     */
    public function __construct()
    {
        add_filter('manage_edit-' . STUDY_SPACES . '_columns', array($this, 'my_edit_study_spaces_columns'));
        add_action('manage_' . STUDY_SPACES . '_posts_custom_column', array($this, 'manage_study_spaces_columns'), 10, 2);

        // hook into the 'init' action
        add_action('init', array($this, 'location_taxonomy'), 0);
        add_action('init', array($this, 'study_type_taxonomy'), 0);
        add_action('init', array($this, 'study_space_post_type'), 0);
        add_action('init', array($this, 'days_open_taxonomy'), 0);

        new ProximityMetaBox(self::$proximities);
    }

    public function manage_study_spaces_columns($column, $post_id)
    {
        global $post;

        switch ($column) {
            case STUDY_TYPE :
            case LOCATIONS :
            case DAYS:
                $terms = get_the_terms($post_id, $column);

                if (!empty($terms)) { // if terms found
                    $out = array();
                    // loop through each term and link to the edit posts page for each respective term
                    foreach ($terms as $term) {
                        $out[] = sprintf('<a href="%s">%s</a>',
                            esc_url(add_query_arg(array('post_type' => $post->post_type, $column => $term->slug), 'edit.php')),
                            esc_html(sanitize_term_field('name', $term->name, $term->term_id, $column, 'display'))
                        );
                    }

                    echo join(', ', $out); // join the terms and delimit them with a comma
                } else { // no terms found
                    _e('No study type');
                }
                break;
            case PROXIMITY_TO_HUB:
            case PROXIMITY_TO_SUB:
            case PROXIMITY_TO_LRT:
            case PROXIMITY_TO_HEALTH_SCIENCES:
                $proximity = get_post_custom_values($column, $post_id);
                if (!empty($proximity)) {
                    echo($proximity[0]);
                } else {
                    _e('No Proximity');
                }
                break;
            default : // do nothing for everything else
                break;
        }
    }

    public function my_edit_study_spaces_columns($columns)
    {
        $remove_columns = array('author,');
        unset($columns['author']);

        $new_columns = array(
            'cb' => '<input type="checkbox" />',
            'title' => __('Study Space'),
            'taxonomy-' . LOCATIONS => __('Location'),
            PROXIMITY_TO_HUB => __('Proximity to HUB'),
            PROXIMITY_TO_SUB => __('Proximity to SUB'),
            PROXIMITY_TO_LRT => __('Proximity to University LRT'),
            PROXIMITY_TO_HEALTH_SCIENCES => __('Proximity to Health Sciences'),
        );

        return array_merge($new_columns, $columns);
    }

    // register the custom study space post type
    public static function study_space_post_type()
    {
        $labels = array(
            'name' => _x('Study Spaces', 'Post Type General Name', 'isss-study-spaces-textdomain'),
            'singular_name' => _x('Study Space', 'Post Type Singular Name', 'isss-study-spaces-textdomain'),
            'menu_name' => __('Study Spaces', 'isss-study-spaces-textdomain'),
            'parent_item_colon' => __('Parent Study Space:', 'isss-study-spaces-textdomain'),
            'all_items' => __('All Study Spaces', 'isss-study-spaces-textdomain'),
            'view_item' => __('View Study Space', 'isss-study-spaces-textdomain'),
            'add_new_item' => __('Add New Study Space', 'isss-study-spaces-textdomain'),
            'add_new' => __('Add Study Space', 'isss-study-spaces-textdomain'),
            'edit_item' => __('Edit Study Space', 'isss-study-spaces-textdomain'),
            'update_item' => __('Update Study Space', 'isss-study-spaces-textdomain'),
            'search_items' => __('Search Study Space', 'isss-study-spaces-textdomain'),
            'not_found' => __('Study Space not found', 'isss-study-spaces-textdomain'),
            'not_found_in_trash' => __('Study Space not found in Trash', 'isss-study-spaces-textdomain'),
        );
        $args = array(
            'label' => __('study_spaces', 'isss-study-spaces-textdomain'),
            'description' => __('Study spaces at the University of Alberta', 'isss-study-spaces-textdomain'),
            'labels' => $labels,
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'comments', 'revisions', 'custom-fields',),
            'taxonomies' => array('ualberta_locations'),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'menu_position' => 5,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => array('study_space', 'study_spaces'),
            'map_meta_cap' => true
        );
        register_post_type(STUDY_SPACES, $args);
    }

    // add the new capability to all roles having a certain built-in capability
    public static function add_study_spaces_role()
    {
        add_role('study_spaces', __('Study Spaces'),
            array(
                'read' => true, // allow reading the dashboard
                'upload_files' => true, // allow uploading featured images
                'delete_posts' => true, // allow deletion of own media uploads
            ));

        $result = get_role('study_spaces');
        if (null !== $result) {
            foreach (self::$custom_capabilities as $capability) {
                $result->add_cap($capability);
            }

            // add to roles with rights to edit other posts
            // ie: admin and editors
            $roles = get_editable_roles();
            foreach ($GLOBALS['wp_roles']->role_objects as $key => $role)
                if (isset($roles[$key]) && $role->has_cap('edit_others_posts')) {
                    foreach (self::$custom_capabilities as $capability) {
                        $role->add_cap($capability);
                    }
                }

            return true;
        } else {
            return false;
        }
    } // private function add_cap

    // register the custom study type taxonomy
    public static function study_type_taxonomy()
    {
        $labels = array(
            'name' => _x('Study Types', 'Taxonomy General Name', 'isss-study-spaces-textdomain'),
            'singular_name' => _x('Study Type', 'Taxonomy Singular Name', 'isss-study-spaces-textdomain'),
            'menu_name' => __('Study Type', 'isss-study-spaces-textdomain'),
            'all_items' => __('All Study Types', 'isss-study-spaces-textdomain'),
            'parent_item' => __('Study Type', 'isss-study-spaces-textdomain'),
            'parent_item_colon' => __('Parent Study Type:', 'isss-study-spaces-textdomain'),
            'new_item_name' => __('New Study Type', 'isss-study-spaces-textdomain'),
            'add_new_item' => __('Add New Study Type', 'isss-study-spaces-textdomain'),
            'edit_item' => __('Edit Study Type', 'isss-study-spaces-textdomain'),
            'update_item' => __('Update Study Type', 'isss-study-spaces-textdomain'),
            'separate_items_with_commas' => __('Separate Study Types with commas', 'isss-study-spaces-textdomain'),
            'search_items' => __('Search Study Types', 'isss-study-spaces-textdomain'),
            'add_or_remove_items' => __('Add or remove Study Types', 'isss-study-spaces-textdomain'),
            'choose_from_most_used' => __('Choose from the most used Study Types', 'isss-study-spaces-textdomain'),
            'not_found' => __('Study Type Not Found', 'isss-study-spaces-textdomain'),
        );
        $args = array(
            'labels' => $labels,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'capabilities' => array(
                'manage_terms' => 'edit_study_spaces',
                'edit_terms' => 'edit_study_spaces',
                'delete_terms' => 'edit_study_spaces',
                'assign_terms' => 'edit_study_spaces'
            ),
        );
        register_taxonomy(STUDY_TYPE, array(STUDY_SPACES), $args);
    }

    // register the custom location taxonomy
    public static function location_taxonomy()
    {
        $labels = array(
            'name' => _x('Locations', 'Taxonomy General Name', 'isss-study-spaces-textdomain'),
            'singular_name' => _x('Location', 'Taxonomy Singular Name', 'isss-study-spaces-textdomain'),
            'menu_name' => __('University Locations', 'isss-study-spaces-textdomain'),
            'all_items' => __('All locations', 'isss-study-spaces-textdomain'),
            'parent_item' => __('Parent Location', 'isss-study-spaces-textdomain'),
            'parent_item_colon' => __('Parent Location:', 'isss-study-spaces-textdomain'),
            'new_item_name' => __('New Location Name', 'isss-study-spaces-textdomain'),
            'add_new_item' => __('Add New Location', 'isss-study-spaces-textdomain'),
            'edit_item' => __('Edit Location', 'isss-study-spaces-textdomain'),
            'update_item' => __('Update Location', 'isss-study-spaces-textdomain'),
            'separate_items_with_commas' => __('Separate locations with commas', 'isss-study-spaces-textdomain'),
            'search_items' => __('Search locations', 'isss-study-spaces-textdomain'),
            'add_or_remove_items' => __('Add or remove locations', 'isss-study-spaces-textdomain'),
            'choose_from_most_used' => __('Choose from the most used locations', 'isss-study-spaces-textdomain'),
            'not_found' => __('Location Not Found', 'isss-study-spaces-textdomain'),
        );
        $args = array(
            'labels' => $labels,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'capabilities' => array(
                'manage_terms' => 'edit_study_spaces',
                'edit_terms' => 'edit_study_spaces',
                'delete_terms' => 'edit_study_spaces',
                'assign_terms' => 'edit_study_spaces'
            ),
        );
        register_taxonomy(LOCATIONS, array(STUDY_SPACES), $args);
    }

    // register custom days open taxonomy
    public static function days_open_taxonomy()
    {
        $labels = array(
            'name' => _x('Days Open', 'Taxonomy General Name', 'isss-study-spaces-textdomain'),
            'singular_name' => _x('Day Open', 'Taxonomy Singular Name', 'isss-study-spaces-textdomain'),
            'menu_name' => __('Days Open', 'isss-study-spaces-textdomain'),
            'all_items' => __('All Days', 'isss-study-spaces-textdomain'),
            'parent_item' => __('Parent Day', 'isss-study-spaces-textdomain'),
            'parent_item_colon' => __('Parent Day:', 'isss-study-spaces-textdomain'),
            'new_item_name' => __('New Day Name', 'isss-study-spaces-textdomain'),
            'add_new_item' => __('Add New Day', 'isss-study-spaces-textdomain'),
            'edit_item' => __('Edit Day', 'isss-study-spaces-textdomain'),
            'update_item' => __('Update Day', 'isss-study-spaces-textdomain'),
            'separate_items_with_commas' => __('Separate days with commas', 'isss-study-spaces-textdomain'),
            'search_items' => __('Search Days', 'isss-study-spaces-textdomain'),
            'add_or_remove_items' => __('Add or remove days', 'isss-study-spaces-textdomain'),
            'choose_from_most_used' => __('Choose from the most used days', 'isss-study-spaces-textdomain'),
            'not_found' => __('Day Not Found', 'isss-study-spaces-textdomain'),
        );
        $args = array(
            'labels' => $labels,
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'capabilities' => array(
                'manage_terms' => 'edit_study_spaces',
                'edit_terms' => 'edit_study_spaces',
                'delete_terms' => 'edit_study_spaces',
                'assign_terms' => 'edit_study_spaces'
            ),
        );
        register_taxonomy(DAYS, array(STUDY_SPACES), $args);
    }

    /**
     * Copies the custom search filter results page into the theme's folder under the search-filter sub-directory.
     */
    public static function file_replace()
    {
        $target_dir = get_stylesheet_directory() . '/search-filter';
        $source_file = ISSS_STUDY_SPACES__PLUGIN_DIR . 'search-filter/results.php';
        $target_file = $target_dir . '/results.php';

        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        if (!copy($source_file, $target_file)) {
            echo "Failed to copy $source_file to $target_file!\n";
        }
    }
}

?>