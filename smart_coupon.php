<?php
/**
 * @package Smart Coupon
 */
/*
Plugin Name: Smart Coupon
Plugin URI: https://smartcoupon.co
Description: Smart Coupon, plugin to connect your wordpress store with Smart Coupon Database. Just 2 step installation.One discount coupon code can be used in multible online stores for many times.
Version: 1.1.2
Author: UniqueStar Net Solution
Author URI: http://uniq-star.com
Text Domain: smart_coupon
*/
ini_set('log_errors','On');
ini_set('display_errors','Off');
ini_set('error_reporting', E_ALL );
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
add_action('admin_menu', 'smart_add_admin_menu');
add_action('admin_init', 'smart_settings_init');

function smart_add_admin_menu()
{
    
    add_submenu_page('woocommerce', 'Smart Coupon', 'Smart Coupon', 'manage_options', 'smart_coupon', 'smart_options_page');
    
}


function smart_settings_init()
{
    
    register_setting('pluginPage', 'smart_settings');
    
    add_settings_section('smart_pluginPage_section', '', 'smart_settings_section_callback', 'pluginPage');
    
    add_settings_field('smart_text_field_0', 'Your Api key', 'smart_text_field_0_render', 'pluginPage', 'smart_pluginPage_section');
    
    add_settings_field('smart_text_field_1', 'Website URL', 'smart_text_field_1_render', 'pluginPage', 'smart_pluginPage_section');
    
}
function smart_settings_section_callback()
{

}

function smart_text_field_0_render()
{
    
    $options = get_option('smart_settings');
?>
   <input type='text' name='smart_settings[smart_text_field_0]' value='<?php
    echo $options['smart_text_field_0'];
?>'  style="width:50%;">
    <?php
    
}

function smart_text_field_1_render()
{
    
    $options = get_option('smart_settings');
?>
   <input type='text' name='' value='<?php
    echo 'https://smartcoupon.co';
?>' style="width:50%;" readonly>
    <?php
    
}


function smart_options_page()
{
    
?>
   <form action='options.php' method='post' style=" background: white;
    padding: 15px;
    margin-top: 20px;
    margin-right: 15px;
    margin-left: 15px;">

        <h2>Smart Coupon</h2>

        <?php
    settings_fields('pluginPage');
    do_settings_sections('pluginPage');
    submit_button();
?>

    </form>
    <?php
    
}





$hook_to      = 'woocommerce_thankyou';
$what_to_hook = 'smart_when_place_order';
$prioriy      = 111;
$num_of_arg   = 1;
add_action($hook_to, $what_to_hook, $prioriy, $num_of_arg);

function smart_when_place_order($order_id)
{
    if (!session_id()) {
        @session_cache_limiter('private, must-revalidate'); //private_no_expire
        @session_cache_expire(0);
        @session_start();
    }
    if (isset($_SESSION['coupon_code']) && $_SESSION['coupon_code'] != "") {
        $coupon_code = $_SESSION['coupon_code'];
        global $woocommerce;
        $order      = wc_get_order( $order_id );
        $before_discount = wc_format_decimal( $order->get_subtotal(), 2 );
        $after_discount  = wc_format_decimal( $order->get_total(), 2 );

        $options         = get_option('smart_settings');
        $api             = $options['smart_text_field_0'];
        $url             = "https://smartcoupon.co/public/api/use-coupon";
        $response        = wp_remote_post($url, array(
            'method' => 'POST',
            'body' => array(
                'coupon' => $coupon_code,
                'before_discount' => $before_discount,
                'after_discount' => $after_discount
            ),
            'headers' => array(
                'Authorization' => 'Bearer ' . $api
            )
        ));
    }
    if (isset($_SESSION['coupon_id']) && $_SESSION['coupon_id'] != "") {
        wp_delete_post($_SESSION['coupon_id']);
    }
}
add_filter('plugin_action_links_smart-coupon/smart_coupon.php', 'smart_action_links');
function smart_action_links($links)
{
    $smart_links = array(
        '<a href="' . admin_url('admin.php?page=smart_coupon') . '">Settings</a>'
    );
    return array_merge($links, $smart_links);
}

function smart_action_on_notice($message)
{
    global $woocommerce;
    if (strpos($message, 'does not exist') !== false || strpos($message, 'غير صحيح') !== false || strpos($message,'غير موجودة') !== false) {
        
        $coupon = $_POST['coupon_code'];
        
        $coupon_code = wc_format_coupon_code($coupon);
        
        // Get the coupon.
        $the_coupon = new WC_Coupon($coupon_code);
        // Check it can be used with cart.
        if (!$the_coupon->is_valid()) {
            $options = get_option('smart_settings');
            $api     = $options['smart_text_field_0'];
            
            
            
            
            $url = "https://smartcoupon.co/public/api/validate-coupon";
            
            $response = wp_remote_post($url, array(
                'method' => 'POST',
                'body' => array(
                    'coupon' => $coupon
                ),
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api
                )
            ));
            $response = json_decode($response['body']);
            if ($response->status) {
                $coupon_code = $coupon_code; // Code
                $amount      = $response->body->discount; // Amount
                if ($response->body->discount_type == 'percent') {
                    $discount_type = 'percent'; // Type: fixed_cart, percent, fixed_product, percent_product
                } else {
                    $discount_type = 'fixed_cart'; // Type: fixed_cart, percent, fixed_product, percent_product
                }
                $coupon = array(
                    'post_title' => $coupon_code,
                    'post_content' => '',
                    'post_status' => 'publish',
                    'post_author' => 1,
                    'post_type' => 'shop_coupon'
                );
                
                $new_coupon_id = wp_insert_post($coupon);
                
                // Add meta
                update_post_meta($new_coupon_id, 'discount_type', $discount_type);
                update_post_meta($new_coupon_id, 'coupon_amount', $amount);
                update_post_meta($new_coupon_id, 'individual_use', 'yes');
                update_post_meta($new_coupon_id, 'product_ids', '');
                update_post_meta($new_coupon_id, 'exclude_product_ids', '');
                update_post_meta($new_coupon_id, 'usage_limit', '');
                update_post_meta($new_coupon_id, 'expiry_date', '');
                update_post_meta($new_coupon_id, 'apply_before_tax', 'yes');
                update_post_meta($new_coupon_id, 'free_shipping', 'no');
                
                $woocommerce->cart->add_discount($coupon_code);
                if (!session_id()) {
                    @session_cache_limiter('private, must-revalidate'); //private_no_expire
                    @session_cache_expire(0);
                    @session_start();
                }
                //echo $new_coupon_id;
                $_SESSION['coupon_id']   = $new_coupon_id;
                $_SESSION['coupon_code'] = $coupon_code;
                echo "<script>setTimeout(function(){ jQuery('ul.woocommerce-error').addClass('woocommerce-message').removeClass('woocommerce-error'); },100);</script>";
                return 'Your Coupon code has been applied successfully';
            } else {
                
                return 'Invalid Coupon code';
                
            }
            
        }
        
        
        
        
    } else {
        return $message;
    }
}
add_filter('woocommerce_add_error', 'smart_action_on_notice');


?>