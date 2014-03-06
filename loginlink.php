<?php
/*
 Plugin Name: Login via Link
 Plugin URI: http://agilepixel.io/
 Description: Enables user login via url link
 Version: 0.1
 Author: Agile Pixel
 Author URI: http://agilepixel.io/
 License: GPL2
 License URI: license.txt
 */

register_activation_hook(__FILE__, 'loginlink_settings_init');

add_filter('rewrite_rules_array', 'loginlink_rewrite_rules');

add_filter('query_vars', 'loginlink_query_vars');

add_filter('manage_users_columns', 'loginlink_add_user_column');

add_action('manage_users_custom_column',  'loginlink_user_column_content', 10, 3);

add_action('pre_get_posts','loginlink_init');

add_action('admin_notices', 'loginlink_alarm');

function loginlink_init()
{
    if ($login = get_query_var('loginlink')) {

        $iv_size = mcrypt_get_iv_size('cast-128', MCRYPT_MODE_ECB);

        $key = get_option('loginlink_secret_key');

        //$permalink = get_option('permalink_structure');

        $login = rawurldecode($login);

        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $decrypt = mcrypt_decrypt('cast-128', $key, base64_decode($login) , MCRYPT_MODE_ECB, $iv);

        $creds = array();
        
        if (preg_match('/^([A-z _\-@\.]+)\[(.*)$/', $decrypt,$matches)) {
            $creds['user_login'] = $matches[1];
            $creds['user_password'] = $matches[2];
            if ($user = get_user_by('login', $matches[1])) {
                $check = md5($user->data->user_pass);

                if (trim($check) == trim($matches[2])) {
                    wp_set_auth_cookie($user->data->ID);

                    if ($redirect = get_query_var('logingoto')) {
                        wp_redirect( get_bloginfo('url') . $redirect );
                        exit;
                    } else {
                        wp_redirect( home_url() );
                        exit;
                    }
                }
            }
        }

    }
}

function loginlink_alarm()
{
    if (!function_exists('mcrypt_encrypt')) {
        echo '<div class="updated"><p>The login link plugin requires the mcrypt php extension to be installed on your server.</p></div>';
    }
}

function loginlink_settings_init()
{
    global $wp_rewrite;
    if (!get_option('loginlink_secret_key')) {
        require_once( ABSPATH . WPINC . '/pluggable.php' );
        $secret_key = wp_generate_password( 12, true, true );
        update_option('loginlink_secret_key',$secret_key,'','no');
    }
    $wp_rewrite->flush_rules();
}

function loginlink_rewrite_rules($rules)
{
        global $wp_rewrite;
        $newRule = array('loginlink/(.+)' => 'index.php?loginlink='.$wp_rewrite->preg_index(1));
        $newRules = $newRule + $rules;

        return $newRules;
}

function loginlink_query_vars($qvars)
{
        $qvars[] = 'loginlink';
        $qvars[] = 'logingoto';

        return $qvars;
}

function loginlink_add_user_column($columns)
{
    $columns['loginlink'] = 'Login Link';

    return $columns;
}

function loginlink_user_column_content($value, $column_name, $user_id)
{
    //$permalink = get_option('permalink_structure');
    $user = get_userdata( $user_id );
    if ('loginlink' == $column_name) {

        $loginurl = home_url();

        $loginurl .= "/?loginlink=";

        $return = "";

        $iv_size = mcrypt_get_iv_size('cast-128', MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $key = get_option('loginlink_secret_key');

            $hash = md5($user->data->user_pass);
        $text = $user->data->user_login."[".$hash."";
        $crypttext = mcrypt_encrypt('cast-128', $key, $text, MCRYPT_MODE_ECB, $iv);
        $crypttext = base64_encode($crypttext);
        $loginurl .= rawurlencode($crypttext);

        $return .= "<a href=\"".$loginurl."\">Login Link (right click copy)</a>";

        if (has_filter('loginlink_userlistlink')) {
            $return = apply_filters('loginlink_userlistlink',$return,$loginurl);
        }

        return $return;
    } else {
        return $value;
    }
}
