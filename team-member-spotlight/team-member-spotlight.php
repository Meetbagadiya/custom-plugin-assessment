<?php
/*
Plugin Name: Team Member Spotlight
Description: A plugin to manage team members and spotlight them on the homepage.
Version: 1.0
Author: Meet Bagadiya
*/
// Register Custom Post Type Portfolio
function create_portfolio_cpt() {
    $labels = array(
        'name' => 'Portfolios',
        'singular_name' => 'Portfolio',
        'menu_name' => 'Portfolios',
        'name_admin_bar' => 'Portfolio',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Portfolio',
        'new_item' => 'New Portfolio',
        'edit_item' => 'Edit Portfolio',
        'view_item' => 'View Portfolio',
        'all_items' => 'All Portfolios',
        'search_items' => 'Search Portfolios',
        'not_found' => 'No portfolios found.',
        'not_found_in_trash' => 'No portfolios found in Trash.',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail'),
        'menu_position' => 5,
        'register_meta_box_cb' => 'add_portfolio_metaboxes',
    );

    register_post_type('portfolio', $args);
}
add_action('init', 'create_portfolio_cpt');

// Add custom meta boxes
function add_portfolio_metaboxes() {
    add_meta_box('portfolio_meta', 'Portfolio Details', 'portfolio_meta_callback', 'portfolio', 'normal', 'high');
}

function portfolio_meta_callback($post) {
    wp_nonce_field('portfolio_meta_nonce', 'portfolio_meta_nonce');
    $project_url = get_post_meta($post->ID, 'project_url', true);
    $client_name = get_post_meta($post->ID, 'client_name', true);
    $project_date = get_post_meta($post->ID, 'project_date', true);
    ?>
    <label for="project_url">Project URL:</label>
    <input type="text" name="project_url" value="<?php echo esc_attr($project_url); ?>" /><br>
    <label for="client_name">Client Name:</label>
    <input type="text" name="client_name" value="<?php echo esc_attr($client_name); ?>" /><br>
    <label for="project_date">Project Date:</label>
    <input type="date" name="project_date" value="<?php echo esc_attr($project_date); ?>" /><br>
    <?php
}

// Save meta box data
function save_portfolio_meta($post_id) {
    if (!isset($_POST['portfolio_meta_nonce']) || !wp_verify_nonce($_POST['portfolio_meta_nonce'], 'portfolio_meta_nonce')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (isset($_POST['project_url'])) {
        update_post_meta($post_id, 'project_url', sanitize_text_field($_POST['project_url']));
    }
    if (isset($_POST['client_name'])) {
        update_post_meta($post_id, 'client_name', sanitize_text_field($_POST['client_name']));
    }
    if (isset($_POST['project_date'])) {
        update_post_meta($post_id, 'project_date', sanitize_text_field($_POST['project_date']));
    }
}
add_action('save_post', 'save_portfolio_meta');

// Add checkbox for 'mark as featured post'
function add_featured_checkbox() {
    add_meta_box('featured_meta', 'Featured Portfolio', 'featured_meta_callback', 'portfolio', 'side', 'high');
}

function featured_meta_callback($post) {
    $is_featured = get_post_meta($post->ID, 'featured_post', true);
    ?>
    <label for="featured_post">
        <input type="checkbox" name="featured_post" <?php checked($is_featured, 'yes'); ?> value="yes" /> Mark as Featured
    </label>
    <?php
}

add_action('add_meta_boxes', 'add_featured_checkbox');

function save_featured_meta($post_id) {
    if (isset($_POST['featured_post'])) {
        update_post_meta($post_id, 'featured_post', 'yes');
    } else {
        delete_post_meta($post_id, 'featured_post');
    }
}
add_action('save_post', 'save_featured_meta');

// Activation hook to create a custom database table
function tms_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'team_members';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        member_name varchar(100) NOT NULL,
        position varchar(100) NOT NULL,
        bio text NOT NULL,
        featured_until datetime DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'tms_activate');

// Admin page to manage team members
function tms_admin_menu() {
    add_menu_page('Team Members', 'Team Members', 'manage_options', 'team-member-spotlight', 'tms_admin_page', 'dashicons-groups', 6);
}
add_action('admin_menu', 'tms_admin_menu');

function tms_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'team_members';

    // Handle AJAX request to add new member
    if (isset($_POST['tms_add_member'])) {
        $member_name = sanitize_text_field($_POST['member_name']);
        $position = sanitize_text_field($_POST['position']);
        $bio = sanitize_textarea_field($_POST['bio']);
        $featured_until = sanitize_text_field($_POST['featured_until']);

        $wpdb->insert($table_name, array(
            'member_name' => $member_name,
            'position' => $position,
            'bio' => $bio,
            'featured_until' => $featured_until,
        ));
        echo '<div class="updated"><p>Member added successfully!</p></div>';
    }

    // Display members
    $members = $wpdb->get_results("SELECT * FROM $table_name");

    ?>
    <h1>Team Members</h1>
    <form method="post" id="tms_add_member_form">
        <input type="text" name="member_name" placeholder="Member Name" required />
        <input type="text" name="position" placeholder="Position" required />
        <textarea name="bio" placeholder="Bio" required></textarea>
        <input type="datetime-local" name="featured_until" required />
        <input type="submit" name="tms_add_member" value="Add Member" />
    </form>

    <h2>Current Members</h2>
    <table>
        <tr>
            <th>Name</th>
            <th>Position</th>
            <th>Bio</th>
            <th>Featured Until</th>
        </tr>
        <?php foreach ($members as $member) : ?>
            <tr>
                <td><?php echo esc_html($member->member_name); ?></td>
                <td><?php echo esc_html($member->position); ?></td>
                <td><?php echo esc_html($member->bio); ?></td>
                <td><?php echo esc_html($member->featured_until); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php
}

function tms_team_spotlight($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'team_members';

    $atts = shortcode_atts(array(
        'count' => 3,
        'position' => '',
    ), $atts);

    $query = "SELECT * FROM $table_name WHERE featured_until > NOW()";
    if (!empty($atts['position'])) {
        $query .= " AND position = '" . esc_sql($atts['position']) . "'";
    }
    $query .= " LIMIT " . intval($atts['count']);

    $members = $wpdb->get_results($query);

    if (empty($members)) {
        return "No featured members";
    }

    $output = '<div class="team-spotlight">';
    foreach ($members as $member) {
        $output .= '<div class="team-member">';
        $output .= '<h3>' . esc_html($member->member_name) . '</h3>';
        $output .= '<p>' . esc_html($member->position) . '</p>';
        $output .= '<p>' . esc_html($member->bio) . '</p>';
        $output .= '</div>';
    }
    $output .= '</div>';

    return $output;
}
add_shortcode('team_spotlight', 'tms_team_spotlight');

// Add custom fields to WooCommerce products
function add_custom_product_fields() {
    global $post;

    echo '<div class="options_group">';

    // Installation Required
    woocommerce_wp_checkbox(array(
        'id' => 'installation_required',
        'label' => __('Installation Required', 'woocommerce'),
        'description' => __('Check if installation is required for this product.', 'woocommerce'),
    ));

    // Warranty Period
    woocommerce_wp_select(array(
        'id' => 'warranty_period',
        'label' => __('Warranty Period', 'woocommerce'),
        'options' => array(
            '' => __('Select a warranty period', 'woocommerce'),
            '6_months' => __('6 months', 'woocommerce'),
            '1_year' => __('1 year', 'woocommerce'),
            '2_years' => __('2 years', 'woocommerce'),
        ),
    ));

    echo '</div>';
}
add_action('woocommerce_product_options_general_product_data', 'add_custom_product_fields');

// Save custom fields
function save_custom_product_fields($post_id) {
    $installation_required = isset($_POST['installation_required']) ? 'yes' : 'no';
    update_post_meta($post_id, 'installation_required', $installation_required);

    if (isset($_POST['warranty_period'])) {
        update_post_meta($post_id, 'warranty_period', sanitize_text_field($_POST['warranty_period']));
    }
}
add_action('woocommerce_process_product_meta', 'save_custom_product_fields');

// Display custom fields on single product pages
function display_custom_product_fields() {
    global $post;

    $installation_required = get_post_meta($post->ID, 'installation_required', true);
    $warranty_period = get_post_meta($post->ID, 'warranty_period', true);

    if ($installation_required === 'yes') {
        echo '<p><strong>' . __('Installation Required:', 'woocommerce') . '</strong> Yes</p>';
    }

    if (!empty($warranty_period)) {
        echo '<p><strong>' . __('Warranty Period:', 'woocommerce') . '</strong> ' . esc_html($warranty_period) . '</p>';
    }
}
add_action('woocommerce_single_product_summary', 'display_custom_product_fields', 25);