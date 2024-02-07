<?php

// Register Invoice CPT
function gt_register_invoice_cpt() {
    register_post_type('gt_invoice', [
        'labels' => [
            'name'               => esc_html__( 'Invoices', 'gt-service-manager' ),
            'singular_name'      => esc_html__( 'Invoice', 'gt-service-manager' ),
            'add_new'            => esc_html__( 'Add Invoice', 'gt-service-manager' ),
            'add_new_item'       => esc_html__( 'Add new Invoice', 'gt-service-manager' ),
            'all_items'          => esc_html__( 'All Invoices', 'gt-service-manager' ),
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => false, // Prevent as top-level menu
        'supports' => ['title', 'thumbnail'],
    ]);
    
}

// Hooking up the functions
add_action('init', 'gt_register_invoice_cpt');


function gt_add_invoice_meta_boxes() {
    add_meta_box('gt_invoice_details', 'Invoice Details', 'gt_invoice_details_callback', 'gt_invoice', 'normal', 'high');
}
add_action('add_meta_boxes_gt_invoice', 'gt_add_invoice_meta_boxes');


function gt_invoice_details_callback($post) {
    wp_nonce_field('gt_invoice_save_details', 'gt_invoice_details_nonce');

    // Check if we're adding a new invoice or editing an existing one
    $is_new_invoice = $post->post_status == 'auto-draft';

    // Fetch the last invoice number and increment it for the new invoice
    $last_invoice_number = get_option('gt_last_invoice_number', 0);
    $new_invoice_number = $is_new_invoice ? $last_invoice_number + 1 : get_post_meta($post->ID, '_gt_invoice_number', true);

    // Display the invoice number field with the default or existing number
    echo '<p><label for="gt_invoice_number">' . esc_html__('Invoice Number:', 'gt-service-manager') . '</label>';
    echo '<input type="text" id="gt_invoice_number" name="gt_invoice_number" value="' . esc_attr($new_invoice_number) . '" class="widefat" /></p>';

    // Dropdown to select a service from existing services
    gt_dropdown_services($post->ID);

    // Dropdown to select a customer from existing customers
    gt_dropdown_customers($post->ID);
}

// Helper function to generate a dropdown of existing services
function gt_dropdown_services($post_id) {
    $selected_service = get_post_meta($post_id, '_gt_selected_service', true);
    $services = get_posts(['post_type' => 'gt_service', 'numberposts' => -1]);

    echo '<p><label for="gt_selected_service">' . esc_html__('Select Service:', 'gt-service-manager') . '</label>';
    echo '<select id="gt_selected_service" name="gt_selected_service" class="widefat">';
    foreach ($services as $service) {
        echo '<option value="' . esc_attr($service->ID) . '"' . selected($selected_service, $service->ID, false) . '>' . esc_html($service->post_title) . '</option>';
    }
    echo '</select></p>';
}

// Helper function to generate a dropdown of existing customers
function gt_dropdown_customers($post_id) {
    $selected_customer = get_post_meta($post_id, '_gt_selected_customer', true);
    $customers = get_posts(['post_type' => 'gt_customer', 'numberposts' => -1]);

    echo '<p><label for="gt_selected_customer">' . esc_html__('Select Customer:', 'gt-service-manager') . '</label>';
    echo '<select id="gt_selected_customer" name="gt_selected_customer" class="widefat">';
    foreach ($customers as $customer) {
        echo '<option value="' . esc_attr($customer->ID) . '"' . selected($selected_customer, $customer->ID, false) . '>' . esc_html($customer->post_title) . '</option>';
    }
    echo '</select></p>';
}


function gt_save_invoice_meta($post_id) {
    // Check for nonce and validate it to ensure form submission is legitimate
    if (!isset($_POST['gt_invoice_details_nonce']) || !wp_verify_nonce($_POST['gt_invoice_details_nonce'], 'gt_invoice_save_details')) {
        return;
    }

    // Check for auto-save to prevent unintended overwrites
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Update meta for invoice number, selected service, and selected customer
    if (isset($_POST['gt_invoice_number'])) {
        update_post_meta($post_id, '_gt_invoice_number', sanitize_text_field($_POST['gt_invoice_number']));
    }

    if (isset($_POST['gt_selected_service'])) {
        update_post_meta($post_id, '_gt_selected_service', sanitize_text_field($_POST['gt_selected_service']));
    }

    if (isset($_POST['gt_selected_customer'])) {
        update_post_meta($post_id, '_gt_selected_customer', sanitize_text_field($_POST['gt_selected_customer']));
    }
}
add_action('save_post_gt_invoice', 'gt_save_invoice_meta');




function gt_custom_invoice_template($template) {
    if (is_singular('gt_invoice')) { // Check if viewing a single invoice
        $custom_template = plugin_dir_path(__FILE__) . 'templates/gt-invoice-template.php'; // Specify the path to your custom template
        if (file_exists($custom_template)) {
            return $custom_template; // Use the custom template
        }
    }

    return $template; // Return default template for other cases
}
add_filter('template_include', 'gt_custom_invoice_template', 99);


function gt_enqueue_invoice_styles() {
    if (is_singular('gt_invoice')) {
        wp_enqueue_style('gt-invoice-style', plugin_dir_url(__FILE__) . 'css/invoice-style.css'); // Path to your CSS file
        // wp_enqueue_script('gt-invoice-script', plugin_dir_url(__FILE__) . 'js/invoice-script.js', array('jquery'), '', true); // Uncomment to enqueue custom scripts
    }
}
add_action('wp_enqueue_scripts', 'gt_enqueue_invoice_styles');