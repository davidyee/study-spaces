<?php
/**
 * Created by PhpStorm.
 * User: David
 * Date: 2015-01-14
 * Time: 3:37 AM
 */

class ProximityMetaBox
{
    private $proximities;

    public function __construct($proximities){
        $this->proximities = $proximities;
        add_action('add_meta_boxes', array($this, 'add_proximity_to_hub_meta_box'));
        add_action('save_post', array($this, 'save'));
    }

    /**
     * Adds the meta box container.
     */
    public function add_proximity_to_hub_meta_box($post_type)
    {
        $post_types = array(STUDY_SPACES);     // limit meta box to certain post types
        if (in_array($post_type, $post_types)) {
            add_meta_box(
                'proximities_meta_box'
                , __('Proximities', 'isss-study-spaces-textdomain')
                , array($this, 'render_meta_box_content')
                , $post_type
                , 'advanced'
                , 'high'
            );
        }
    }

    /**
     * Save the meta when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save($post_id)
    {
        /*
         * We need to verify this call came from the our screen and with proper authorization,
         * because save_post can be triggered at other times.
         */

        // check if our nonce is set.
        if (!isset($_POST['myplugin_inner_custom_box_nonce']))
            return $post_id;

        $nonce = $_POST['myplugin_inner_custom_box_nonce'];

        // verify that the nonce is valid.
        if (!wp_verify_nonce($nonce, 'myplugin_inner_custom_box'))
            return $post_id;

        // if this is an autosave, our form has not been submitted so we don't want to do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return $post_id;

        // check the user's permissions.
        if ('page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id))
                return $post_id;
        } else {
            if (!current_user_can('edit_post', $post_id))
                return $post_id;
        }

        // it is safe for us to save the data now.
        foreach ($this->proximities as $proximity) {
            $mydata = sanitize_text_field($_POST["$proximity[1]"]); // sanitize the user input
            update_post_meta($post_id, "$proximity[1]", $mydata); // update the meta field
        }
    }

    /**
     * Render Meta Box content.
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box_content($post)
    {
        // add a nonce field so we can check for it later.
        wp_nonce_field('myplugin_inner_custom_box', 'myplugin_inner_custom_box_nonce');

        // display the form using the current value.
        foreach ($this->proximities as $proximity) {
            $saved_prox = get_post_meta($post->ID, $proximity[1], true);
            if (empty($saved_prox)) $saved_prox = 0; // default distance

            echo '<p></p><label for="' . $proximity[1] . '">';
            _e("$proximity[0]", 'isss-study-spaces-textdomain');
            echo '</label> ';
            echo '<input type="number" id="' . $proximity[1] . '" name="' . $proximity[1] . '" min="0" max="1000"';
            echo ' value="' . esc_attr($saved_prox) . '" size="25" /> meters</p>';
        }
    }
} 