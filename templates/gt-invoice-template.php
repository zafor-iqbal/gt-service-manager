<?php
// Ensure this file is being loaded by WordPress
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

get_header(); ?>

<main id="main-content">
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <h1 class="entry-title"><?php the_title(); ?> Hi</h1>
        </header>

        <div class="entry-content">
            <?php
            // Example: Fetch and display custom post meta data
            $invoice_number = get_post_meta(get_the_ID(), '_gt_invoice_number', true);
            echo '<p>Invoice Number: ' . esc_html($invoice_number) . '</p>';
            echo "Hello";
            // Implement additional invoice details here

            ?>
        </div>
    </article>
</main>

<?php get_footer(); ?>