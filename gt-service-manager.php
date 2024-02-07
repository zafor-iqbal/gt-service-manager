<?php
/**
 * Plugin Name: GT Service Manager
 * Plugin URI: http://zaforiqbal.com
 * Description: A custom plugin to manage services and customers efficiently within WordPress, allowing admins to create services with details, pricing, and assignment to customers.
 * Version: 1.0
 * Author: Zafor Iqbal
 * Author URI: http://zaforiqbal.com
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */


 require_once plugin_dir_path(__FILE__) . 'includes/gt-invoice-cpt.php';



// Register Custom Post Types
function gt_register_custom_post_types() {
    // Service CPT
    register_post_type('gt_service', [
        'labels' => [
            'name'               => esc_html__( 'Services', 'gt-service-manager' ),
			'singular_name'      => esc_html__( 'Service', 'gt-service-manager' ),
            'add_new'            => esc_html__( 'Add Service', 'gt-service-manager' ),
			'add_new_item'       => esc_html__( 'Add new Service', 'gt-service-manager' ),
            'all_items'          => esc_html__( 'All Services', 'gt-service-manager' ),
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => false, // Prevent as top-level menu
        'supports' => ['title', 'thumbnail'],
    ]);

    // Customer CPT
    register_post_type('gt_customer', [
        'labels' => [
            'name' => 'Customers',
            'singular_name' => 'Customer',
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => false, // Prevent as top-level menu
        'supports' => ['title', 'thumbnail'],
    ]);
}
add_action('init', 'gt_register_custom_post_types');


// Add Admin Menu
function gt_add_admin_menu() {
    // Main menu item
    add_menu_page('GT Service Manager', 'GT Service Manager', 'manage_options', 'gt-service-manager', 'gt_service_manager_main_page', 'dashicons-admin-generic', 6);

    // Add 'Service' as a submenu
    add_submenu_page('gt-service-manager', 'Services', 'Services', 'manage_options', 'edit.php?post_type=gt_service');

    // Add 'Customer' as a submenu
    add_submenu_page('gt-service-manager', 'Customers', 'Customers', 'manage_options', 'edit.php?post_type=gt_customer');

    // Add 'Invoice' as a submenu under 'GT Service Manager'
    add_submenu_page(
        'gt-service-manager',          // Parent slug
        'Invoices',                    // Page title
        'Invoices',                    // Menu title
        'manage_options',              // Capability
        'edit.php?post_type=gt_invoice' // Menu slug - points to the 'Invoice' CPT
    );
}
add_action('admin_menu', 'gt_add_admin_menu');

function gt_service_manager_main_page() {
    echo '<div class="wrap"><h1>Welcome to GT Service Manager</h1></div>';
}

// Add Meta Boxes for Service Details
function gt_add_service_meta_boxes() {
    add_meta_box('gt_service_details', 'Service Details', 'gt_service_details_callback', 'gt_service', 'normal', 'default');
    add_meta_box('gt_service_price', 'Service Price and Currency', 'gt_service_price_callback', 'gt_service', 'side', 'default');
    add_meta_box('gt_service_recurring', 'Recurring Options', 'gt_service_recurring_callback', 'gt_service', 'side', 'default');
    add_meta_box('gt_assign_customer', 'Assign to Customer', 'gt_assign_customer_callback', 'gt_service', 'side', 'default');
}
add_action('add_meta_boxes', 'gt_add_service_meta_boxes');

// Meta Box Callbacks
function gt_service_details_callback($post) {
    wp_nonce_field('gt_save_service_details', 'gt_service_details_nonce');
    $details = get_post_meta($post->ID, '_gt_service_details', true);
    echo '<textarea id="gt_service_details" name="gt_service_details" rows="5" style="width:100%">' . esc_textarea($details) . '</textarea>';
}

function gt_service_price_callback($post) {
    wp_nonce_field('gt_save_service_price', 'gt_service_price_nonce');
    $price = get_post_meta($post->ID, '_gt_service_price', true);
    $currency = get_post_meta($post->ID, '_gt_service_currency', true);
    $currencies = ['USD' => '&#36; - US Dollar', 'EUR' => '&euro; - Euro', 'GBP' => '&pound; - British Pound', 'JPY' => '&yen; - Japanese Yen'];
    echo '<input type="number" id="gt_service_price" name="gt_service_price" value="' . esc_attr($price) . '" style="width:70%">';
    echo '<select id="gt_service_currency" name="gt_service_currency" style="width:29%">';
    foreach ($currencies as $key => $symbol) {
        echo '<option value="' . esc_attr($key) . '"' . selected($currency, $key, false) . '>' . esc_html($symbol) . '</option>';
    }
    echo '</select>';
}

function gt_service_recurring_callback($post) {
    wp_nonce_field('gt_save_service_recurring', 'gt_service_recurring_nonce');
    $recurring = get_post_meta($post->ID, '_gt_service_recurring', true);
    $options = ['Not Recurring', 'Monthly', '3 Monthly', 'Half Yearly', 'Yearly'];
    echo '<select id="gt_service_recurring" name="gt_service_recurring" style="width:100%">';
    foreach ($options as $option) {
        echo '<option value="' . esc_attr($option) . '"' . selected($recurring, $option, false) . '>' . esc_html($option) . '</option>';
    }
    echo '</select>';
}

function gt_assign_customer_callback($post) {
    wp_nonce_field('gt_save_assign_customer', 'gt_assign_customer_nonce');
    $assigned_customer = get_post_meta($post->ID, '_gt_assigned_customer', true);
    $customers = get_posts(['post_type' => 'gt_customer', 'numberposts' => -1]);
    echo '<select id="gt_assigned_customer" name="gt_assigned_customer" style="width:100%">';
    foreach ($customers as $customer) {
        echo '<option value="' . esc_attr($customer->ID) . '"' . selected($assigned_customer, $customer->ID, false) . '>' . esc_html($customer->post_title) . '</option>';
    }
    echo '</select>';
}

// Save Meta Box Data with Validation and Sanitization
function gt_save_service_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['gt_service_details_nonce']) && wp_verify_nonce($_POST['gt_service_details_nonce'], 'gt_save_service_details')) {
        update_post_meta($post_id, '_gt_service_details', sanitize_textarea_field($_POST['gt_service_details']));
    }

    if (isset($_POST['gt_service_price_nonce']) && wp_verify_nonce($_POST['gt_service_price_nonce'], 'gt_save_service_price')) {
        $price = sanitize_text_field($_POST['gt_service_price']);
        if (is_numeric($price) && $price >= 0) {
            update_post_meta($post_id, '_gt_service_price', $price);
        }

        $currency = sanitize_text_field($_POST['gt_service_currency']);
        if (in_array($currency, ['USD', 'EUR', 'GBP', 'JPY'], true)) {
            update_post_meta($post_id, '_gt_service_currency', $currency);
        }
    }

    if (isset($_POST['gt_service_recurring_nonce']) && wp_verify_nonce($_POST['gt_service_recurring_nonce'], 'gt_save_service_recurring')) {
        $recurring = sanitize_text_field($_POST['gt_service_recurring']);
        if (in_array($recurring, ['Not Recurring', 'Monthly', '3 Monthly', 'Half Yearly', 'Yearly'], true)) {
            update_post_meta($post_id, '_gt_service_recurring', $recurring);
        }
    }

    if (isset($_POST['gt_assign_customer_nonce']) && wp_verify_nonce($_POST['gt_assign_customer_nonce'], 'gt_save_assign_customer')) {
        $customer_id = sanitize_text_field($_POST['gt_assigned_customer']);
        if (!empty($customer_id) && get_post_type($customer_id) === 'gt_customer') {
            update_post_meta($post_id, '_gt_assigned_customer', $customer_id);
        } else {
            delete_post_meta($post_id, '_gt_assigned_customer');
        }
    }
}
add_action('save_post_gt_service', 'gt_save_service_meta');


function gt_add_service_columns($columns) {
    // Add new columns while preserving existing ones
    $new_columns = array(
        'customer_name' => 'Customer Name',
        'service_details' => 'Details',
        'service_price' => 'Price',
        'recurring_data' => 'Recurring'
    );

    // Place the new columns after the title column
    $columns = array_slice($columns, 0, 2, true) + $new_columns + array_slice($columns, 2, null, true);

    return $columns;
}
add_filter('manage_gt_service_posts_columns', 'gt_add_service_columns');

function gt_custom_service_column($column, $post_id) {
    switch ($column) {
        case 'customer_name':
            $customer_id = get_post_meta($post_id, '_gt_assigned_customer', true);
            if (!empty($customer_id)) {
                $customer = get_post($customer_id);
                // Check if the customer post exists and display the name, otherwise show 'No customer assigned'
                echo $customer ? esc_html($customer->post_title) : 'No customer assigned';
            } else {
                echo 'No customer assigned';
            }
            break;
        case 'service_details':
            $details = get_post_meta($post_id, '_gt_service_details', true);
            echo esc_html($details);
            break;
        case 'service_price':
            $price = get_post_meta($post_id, '_gt_service_price', true);
            echo esc_html($price);
            break;
        case 'recurring_data':
            $recurring = get_post_meta($post_id, '_gt_service_recurring', true);
            echo esc_html($recurring);
            break;
    }
}
add_action('manage_gt_service_posts_custom_column', 'gt_custom_service_column', 10, 2);




function gt_service_sortable_columns($columns) {
    $columns['customer_name'] = 'customer_name'; // The value 'customer_name' should match the actual query variable used for sorting.
    return $columns;
}
add_filter('manage_edit-gt_service_sortable_columns', 'gt_service_sortable_columns');


// Customer CPT


function gt_register_customer_meta_boxes() {
    add_meta_box(
        'gt_customer_details',           // Unique ID for the meta box
        'Customer Details',              // Title of the meta box
        'gt_customer_details_callback',  // Callback function to render the meta box content
        'gt_customer',                   // Post type
        'normal',                        // Context where the meta box should appear ('normal', 'side', 'advanced')
        'high'                           // Priority within the context
    );
}
add_action('add_meta_boxes_gt_customer', 'gt_register_customer_meta_boxes');


function gt_customer_details_callback($post) {
    wp_nonce_field('gt_save_customer_details', 'gt_customer_details_nonce');

    // Retrieve existing meta values (if any) from the database
    $home_address = get_post_meta($post->ID, '_gt_home_address', true);
    $office_address = get_post_meta($post->ID, '_gt_office_address', true);
    $personal_phone = get_post_meta($post->ID, '_gt_personal_phone', true);
    $home_phone = get_post_meta($post->ID, '_gt_home_phone', true);
    $office_phone = get_post_meta($post->ID, '_gt_office_phone', true);
    $birth_date = get_post_meta($post->ID, '_gt_birth_date', true);

    // HTML for the form fields
    echo '<p><label for="gt_home_address">Home Address:</label><br />';
    echo '<input type="text" id="gt_home_address" name="gt_home_address" value="' . esc_attr($home_address) . '" class="widefat" /></p>';

    echo '<p><label for="gt_office_address">Office Address:</label><br />';
    echo '<input type="text" id="gt_office_address" name="gt_office_address" value="' . esc_attr($office_address) . '" class="widefat" /></p>';

    echo '<p><label for="gt_personal_phone">Personal Phone:</label><br />';
    echo '<input type="text" id="gt_personal_phone" name="gt_personal_phone" value="' . esc_attr($personal_phone) . '" class="widefat" /></p>';

    echo '<p><label for="gt_home_phone">Home Phone:</label><br />';
    echo '<input type="text" id="gt_home_phone" name="gt_home_phone" value="' . esc_attr($home_phone) . '" class="widefat" /></p>';

    echo '<p><label for="gt_office_phone">Office Phone:</label><br />';
    echo '<input type="text" id="gt_office_phone" name="gt_office_phone" value="' . esc_attr($office_phone) . '" class="widefat" /></p>';

    echo '<p><label for="gt_birth_date">Birth Date:</label><br />';
    echo '<input type="date" id="gt_birth_date" name="gt_birth_date" value="' . esc_attr($birth_date) . '" class="widefat" /></p>';
}


function gt_save_customer_details($post_id) {
    if (!isset($_POST['gt_customer_details_nonce']) || !wp_verify_nonce($_POST['gt_customer_details_nonce'], 'gt_save_customer_details')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    update_post_meta($post_id, '_gt_home_address', sanitize_text_field($_POST['gt_home_address']));
    update_post_meta($post_id, '_gt_office_address', sanitize_text_field($_POST['gt_office_address']));
    update_post_meta($post_id, '_gt_personal_phone', sanitize_text_field($_POST['gt_personal_phone']));
    update_post_meta($post_id, '_gt_home_phone', sanitize_text_field($_POST['gt_home_phone']));
    update_post_meta($post_id, '_gt_office_phone', sanitize_text_field($_POST['gt_office_phone']));
    update_post_meta($post_id, '_gt_birth_date', sanitize_text_field($_POST['gt_birth_date']));
}
add_action('save_post_gt_customer', 'gt_save_customer_details');


function gt_add_customer_columns($columns) {
    // Inserting custom columns at specific positions, just after the 'title'
    $columns = array_slice($columns, 0, 2, true) 
                + array(
                    'home_address' => 'Home Address',
                    'office_address' => 'Office Address',
                    'personal_phone' => 'Personal Phone',
                    'home_phone' => 'Home Phone',
                    'office_phone' => 'Office Phone',
                    'birth_date' => 'Date of Birth'
                ) 
                + array_slice($columns, 2, NULL, true);

    return $columns;
}
add_filter('manage_edit-gt_customer_columns', 'gt_add_customer_columns');

function gt_customer_column_content($column_name, $post_id) {
    switch ($column_name) {
        case 'home_address':
            // Sanitize and display the home address
            $home_address = get_post_meta($post_id, '_gt_home_address', true);
            echo esc_html($home_address);
            break;

        case 'office_address':
            // Sanitize and display the office address
            $office_address = get_post_meta($post_id, '_gt_office_address', true);
            echo esc_html($office_address);
            break;

        case 'personal_phone':
            // Sanitize and format the personal phone
            $personal_phone = get_post_meta($post_id, '_gt_personal_phone', true);
            echo esc_html($personal_phone); // Consider applying a formatting function if needed
            break;

        case 'home_phone':
            // Sanitize and format the home phone
            $home_phone = get_post_meta($post_id, '_gt_home_phone', true);
            echo esc_html($home_phone); // Consider applying a formatting function if needed
            break;

        case 'office_phone':
            // Sanitize and format the office phone
            $office_phone = get_post_meta($post_id, '_gt_office_phone', true);
            echo esc_html($office_phone); // Consider applying a formatting function if needed
            break;

        case 'birth_date':
            // Sanitize, format, and display the birth date
            $birth_date = get_post_meta($post_id, '_gt_birth_date', true);
            if (!empty($birth_date)) {
                // Format the date according to the site's date format settings
                echo esc_html(date_i18n(get_option('date_format'), strtotime($birth_date)));
            } else {
                echo '—'; // Display a placeholder if the birth date is not set
            }
            break;
    }
}
add_action('manage_gt_customer_posts_custom_column', 'gt_customer_column_content', 10, 2);





// Flush rewrite rules on plugin activation and deactivation
function gt_service_manager_activate() {
    gt_register_custom_post_types();
    gt_register_invoice_cpt();
    delete_option( 'rewrite_rules' );
}
register_activation_hook(__FILE__, 'gt_service_manager_activate');

function gt_service_manager_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'gt_service_manager_deactivate');