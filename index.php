// Register Custom Post Types
function gt_register_custom_post_types() {
// Service CPT
register_post_type('gt_service', [
'labels' => [
'name' => 'Services',
'singular_name' => 'Service',
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
add_menu_page('GT Service Manager', 'GT Service Manager', 'manage_options', 'gt-service-manager',
'gt_service_manager_main_page', 'dashicons-admin-generic', 6);

// Add 'Service' as a submenu
add_submenu_page('gt-service-manager', 'Services', 'Services', 'manage_options', 'edit.php?post_type=gt_service');

// Add 'Customer' as a submenu
add_submenu_page('gt-service-manager', 'Customers', 'Customers', 'manage_options', 'edit.php?post_type=gt_customer');
}
add_action('admin_menu', 'gt_add_admin_menu');

function gt_service_manager_main_page() {
echo '<div class="wrap">
    <h1>Welcome to GT Service Manager</h1>
</div>';
}

// Add Meta Boxes for Service Details
function gt_add_service_meta_boxes() {
add_meta_box('gt_service_details', 'Service Details', 'gt_service_details_callback', 'gt_service', 'normal', 'default');
add_meta_box('gt_service_price', 'Service Price and Currency', 'gt_service_price_callback', 'gt_service', 'side',
'default');
add_meta_box('gt_service_recurring', 'Recurring Options', 'gt_service_recurring_callback', 'gt_service', 'side',
'default');
add_meta_box('gt_assign_customer', 'Assign to Customer', 'gt_assign_customer_callback', 'gt_service', 'side',
'default');
}
add_action('add_meta_boxes', 'gt_add_service_meta_boxes');

// Meta Box Callbacks
function gt_service_details_callback($post) {
wp_nonce_field('gt_save_service_details', 'gt_service_details_nonce');
$details = get_post_meta($post->ID, '_gt_service_details', true);
echo '<textarea id="gt_service_details" name="gt_service_details" rows="5"
    style="width:100%">' . esc_textarea($details) . '</textarea>';
}

function gt_service_price_callback($post) {
wp_nonce_field('gt_save_service_price', 'gt_service_price_nonce');
$price = get_post_meta($post->ID, '_gt_service_price', true);
$currency = get_post_meta($post->ID, '_gt_service_currency', true);
$currencies = ['USD' => '&#36; - US Dollar', 'EUR' => '&euro; - Euro', 'GBP' => '&pound; - British Pound', 'JPY' =>
'&yen; - Japanese Yen'];
echo '<input type="number" id="gt_service_price" name="gt_service_price" value="' . esc_attr($price) . '"
    style="width:70%">';
echo '<select id="gt_service_currency" name="gt_service_currency" style="width:29%">';
    foreach ($currencies as $key => $symbol) {
    echo '<option value="' . esc_attr($key) . '"' . selected($currency, $key, false) . '>' . esc_html($symbol) . '
    </option>';
    }
    echo '</select>';
}

function gt_service_recurring_callback($post) {
wp_nonce_field('gt_save_service_recurring', 'gt_service_recurring_nonce');
$recurring = get_post_meta($post->ID, '_gt_service_recurring', true);
$options = ['Not Recurring', 'Monthly', '3 Monthly', 'Half Yearly', 'Yearly'];
echo '<select id="gt_service_recurring" name="gt_service_recurring" style="width:100%">';
    foreach ($options as $option) {
    echo '<option value="' . esc_attr($option) . '"' . selected($recurring, $option, false) . '>' . esc_html($option) .
        '</option>';
    }
    echo '</select>';
}

function gt_assign_customer_callback($post) {
wp_nonce_field('gt_save_assign_customer', 'gt_assign_customer_nonce');
$assigned_customer = get_post_meta($post->ID, '_gt_assigned_customer', true);
$customers = get_posts(['post_type' => 'gt_customer', 'numberposts' => -1]);
echo '<select id="gt_assigned_customer" name="gt_assigned_customer" style="width:100%">';
    foreach ($customers as $customer) {
    echo '<option value="' . esc_attr($customer->ID) . '"' . selected($assigned_customer, $customer->ID, false) . '>' .
        esc_html($customer->post_title) . '</option>';
    }
    echo '</select>';
}

// Save Meta Box Data with Validation and Sanitization
function gt_save_service_meta($post_id) {
if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
if (!current_user_can('edit_post', $post_id)) return;

if (isset($_POST['gt_service_details_nonce']) && wp_verify_nonce($_POST['gt_service_details_nonce'],
'gt_save_service_details')) {
update_post_meta($post_id, '_gt_service_details', sanitize_textarea_field($_POST['gt_service_details']));
}

if (isset($_POST['gt_service_price_nonce']) && wp_verify_nonce($_POST['gt_service_price_nonce'],
'gt_save_service_price')) {
$price = sanitize_text_field($_POST['gt_service_price']);
if (is_numeric($price) && $price >= 0) {
update_post_meta($post_id, '_gt_service_price', $price);
}

$currency = sanitize_text_field($_POST['gt_service_currency']);
if (in_array($currency, ['USD', 'EUR', 'GBP', 'JPY'], true)) {
update_post_meta($post_id, '_gt_service_currency', $currency);
}
}

if (isset($_POST['gt_service_recurring_nonce']) && wp_verify_nonce($_POST['gt_service_recurring_nonce'],
'gt_save_service_recurring')) {
$recurring = sanitize_text_field($_POST['gt_service_recurring']);
if (in_array($recurring, ['Not Recurring', 'Monthly', '3 Monthly', 'Half Yearly', 'Yearly'], true)) {
update_post_meta($post_id, '_gt_service_recurring', $recurring);
}
}

if (isset($_POST['gt_assign_customer_nonce']) && wp_verify_nonce($_POST['gt_assign_customer_nonce'],
'gt_save_assign_customer')) {
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
$columns['customer_name'] = 'customer_name'; // The value 'customer_name' should match the actual query variable used
for sorting.
return $columns;
}
add_filter('manage_edit-gt_service_sortable_columns', 'gt_service_sortable_columns');








// Flush rewrite rules on plugin activation and deactivation
function gt_service_manager_activate() {
gt_register_custom_post_types();
flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'gt_service_manager_activate');

function gt_service_manager_deactivate() {
flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'gt_service_manager_deactivate');