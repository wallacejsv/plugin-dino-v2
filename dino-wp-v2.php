<?php
/*
  Plugin Name: DINO WP v2
  Plugin URI: http://www.dino.com.br
  Description: Ferramenta para visualização dea notícias distribuídas pelo DINO - Visibilidade Online.
  Version: 3.0
  Author: DINO
  Author URI: http://www.dino.com.br
  License: GPL2
 */

include_once "curl.php";

function uploadRemoteImageAndAttach($image_url, $parent_id) {
    $image = $image_url;
    $get = wp_remote_get($image);
    $type = wp_remote_retrieve_header($get, 'content-type');
    $mirror = wp_upload_bits( basename( $image ), '', wp_remote_retrieve_body( $get ) );

    $filename = $mirror['file'];
    $filetype = wp_check_filetype( basename( $filename ), null );

    $wp_upload_dir = wp_upload_dir();

    $attachment = array(
        'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ), 
        'post_mime_type' => $filetype['type'],
        'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    $attach_id = wp_insert_attachment( $attachment, $filename, $parent_id );

    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    set_post_thumbnail( $parent_id, $attach_id );
}


//insert news dino in table wp_posts
function insert_posts($cat_id) {
    require_once(ABSPATH . 'wp-admin/includes/taxonomy.php'); 

    global $wpdb;
    global $wp_roles;
    global $post;
    global $wp_query;

    $slug_admin_category = get_option('dino_plugin_slug_news');
    // $cat_id = get_option('dino_plugin_category_id2');
    // $cat_id = get_cat_ID('Notícias corporativas');
    
    wp_update_term($cat_id, 'category', array(
        'name' => $slug_admin_category,
        'slug' => $slug_admin_category,
    ));

    //get notícia partner
    $partner_id = get_option('dino_plugin_id');
    //get image partner
    $partner_image = get_option('dino_plugin_image');
    
    if($partner_id != "") {
        $urlWithPartner = "http://api.dino.com.br/v2/news/".$partner_id."/?pagesize=5";
        $json = dino_file_get_contents($urlWithPartner);
        $result = json_decode($json);

        if (isset($result->Items)) {
            $cont = 0;
            foreach ($result->Items as $item) {
                $title = $item->Title;
                $body = $item->Body;
                $summary = $item->Summary;
                $releaseId = $item->ReleaseId;
                $imageRelease = $item->Image != null ? $item->Image->Url : "";
                $replaceImageQuality80 = str_replace("?quality=100&width=620", "?quality=80&width=620", $imageRelease);
                $replaceImage = str_replace("?quality=80&width=620", "", $replaceImageQuality80);
    
                //remove spaces title
                $titleTrim = trim($title);
    
                //check if the api release is in the wp_posts table
                $post_if = $wpdb->get_var("SELECT count(post_title) FROM $wpdb->posts WHERE post_title like '%$titleTrim%'");
                
                $post_if_page_news = $wpdb->get_var("SELECT count(post_title) FROM $wpdb->posts WHERE post_title like '%Notícias corporativas%'");
                //if you do not have the release in the base wp_posts insert it

                if($partner_image == "image" && $item->Image != null) {
                    if($post_if < 1){
                        $response = (object) array();
                        $post_temp = wp_insert_post( array('post_title' => $title, 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => $body, 'post_excerpt' => $summary, 'post_category' => array($cat_id), 'post_name' => $titleTrim . '-' . $releaseId ) );
        
                        //$response->id = $post_temp;   
                        $tmp = get_post( $post_temp );
        
                        //add release id ao post wp
                        add_post_meta( $tmp->ID, 'idRelease', $releaseId );
        
                        //add image ao post wp
                        uploadRemoteImageAndAttach($replaceImage , $tmp->ID);
                    }
                }
                if($partner_image == "noimage" || !$partner_image) {
                    if($post_if < 1){
                        $response = (object) array();
                        $post_temp = wp_insert_post( array('post_title' => $title, 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => $body, 'post_excerpt' => $summary, 'post_category' => array($cat_id), 'post_name' => $titleTrim . '-' . $releaseId ) );
        
                        //$response->id = $post_temp;   
                        $tmp = get_post( $post_temp );
        
                        //add release id ao post wp
                        add_post_meta( $tmp->ID, 'idRelease', $releaseId );
        
                        //add image ao post wp
                        uploadRemoteImageAndAttach($replaceImage , $tmp->ID);
                    }
                }

                
            }
            wp_reset_postdata();
        }
    } else { ?>
        <script>alert("Vá em configurações - DINO Notícias e preencha o campo 'ID Parceiro'");</script>
    <?php }
}

function my_cron_schedules($schedules){
    if(!isset($schedules["20min"])){
        $schedules["20min"] = array(
            'interval' => 20*60,
            'display' => __('Once every 20 minutes'));
    }
    if(!isset($schedules["5m"])){
        $schedules["5m"] = array(
            'interval' => 5*60,
            'display' => __('Once every 5 minutes'));
    }
    return $schedules;
}
add_filter('cron_schedules','my_cron_schedules');

// add_action('init', 'insert_posts');
// cron get news api
add_action( 'init', function () { 
    // if( !wp_next_scheduled( 'expire_5m_min' ) ) { 
        wp_schedule_event( time(), '5m', 'expire_5m_min' ); 
    // } 
    add_action( 'expire_5m_min', 'insert_posts' ); 
});


//create page noticias-corporativas
function createPageDino(){

    $page = array(
        'post_title' => wp_strip_all_tags( 'Notícias corporativas' ),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => do_shortcode("[get-posts-dino]")
    );

    wp_insert_post($page);
}

//create table dino news with releases
register_activation_hook ( __FILE__, 'on_activate' );
function on_activate() {
    wp_create_category("Notícias corporativas");
}

register_deactivation_hook( __FILE__, 'deactivate_plugin' );
function deactivate_plugin() {

}

function admin_js() {
    wp_enqueue_script('admin-js', plugins_url('dino-wp-v2/assets/js/admin.js', dirname(__FILE__)));
    wp_enqueue_style('admin-css', plugins_url('dino-wp-v2/assets/css/admin.css', dirname(__FILE__)));

    wp_register_script( 'plugin_dino_script_php', plugins_url('dino-wp-v2/assets/js/admin.js', dirname(__FILE__)) );
    wp_enqueue_script( 'plugin_dino_script_php');

    $id_category_options2 = get_option('dino_plugin_category_id2');
    $arrayData = array(
        'id_category_dino_slug' => get_cat_slug($id_category_options2),
        'id_category_dino' => $id_category_options2,
    );

    //Realizo a chamada ao método.
    wp_localize_script('plugin_dino_script_php', 'objeto_javascript', $arrayData);
}
add_action('admin_enqueue_scripts', 'admin_js');

//add canonical noticias dino
function add_canonical_head() {
    global $post;
    if(!is_home()) { ?>
        <link rel="canonical" href="<?php echo "//noticias.dino.com.br/" . $post->post_name; ?>" />
    <?php }
}
remove_action('wp_head', 'rel_canonical');
add_action('wp_head', 'add_canonical_head', 1);

//add posts in shortcode
function get_some_posts($atts) {
    $cat_id = get_cat_ID( 'Dino' );
    global $post;

    $a = shortcode_atts([
      'post_type' => 'post',
      'cat' => $cat_id,
      'posts_per_page' => 10
    ], $atts);

    $myposts = get_posts( array(
        'posts_per_page' => -1,
        'offset'         => 1,
        'category'       => $cat_id
    ) );

    $attachments = get_posts( array(
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'post_parent'    => $post->ID
    ) );
 
    if ( $myposts ) {
        foreach ( $myposts as $post ) :
            setup_postdata( $post );
            ?>
            <div class="posts-dino">
                <uL>
                    <li>
                        <a href="<?php echo $post->guid; ?>"><?php echo $post->post_title; ?></a>
                    </li>
                </ul>
            </div>
        <?php
        endforeach; 
        wp_reset_postdata();
    }
}
  
function shortcodes_init() {
    add_shortcode('get-posts-dino','get_some_posts');
}
add_action('init', 'shortcodes_init');

if(is_admin()) {
    add_action('admin_menu', 'admin_dino_menu');
    add_action ('admin_init', 'register_mysettings');
}
function admin_dino_menu() {
    add_options_page('Dino Admin Options', "DINO - Notícias", 'manage_options', 'dino-admin', 'dino_admin_menu_options');
}

function dino_admin_menu_options(){
    if(!current_user_can('manage_options')) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    form_admin();
}

function register_mysettings() {
    register_setting ('dino_option_group', 'dino_plugin_id');
    register_setting('dino_option_group', 'dino_plugin_image');
    register_setting('dino_option_group', 'dino_plugin_slug_news');
    register_setting('dino_option_group', 'dino_plugin_category_id');
    register_setting('dino_option_group', 'dino_plugin_category_id2');
}

function form_admin() { ?>
    <div class="wrap wrap-dino-plugin-admin">
        <h2>DINO - Configurações</h2>
        <form method="post" action="options.php">
            <?php settings_fields('dino_option_group'); ?>

            <?php 
            // $cat_id = get_cat_ID('Notícias corporativas');
            $id_category = get_cat_ID('Notícias corporativas');

            $slug_admin_category = get_option('dino_plugin_slug_news');
            $id_category_options = get_option('dino_plugin_category_id');
            $id_category_options2 = get_option('dino_plugin_category_id2');
            if ($slug_admin_category != "") {
                $id_category = get_option('dino_plugin_category_id2');
                wp_update_term($id_category, 'category', array(
                    'name' => $slug_admin_category,
                    'slug' => $slug_admin_category,
                ));
            }

            insert_posts($id_category);
            ?>
             <input type="hidden" name="dino_plugin_category_id" value="<?php echo esc_attr($id_category_options); ?>">
             <input type="hidden" name="dino_plugin_category_id2" value="<?php echo esc_attr($id_category); ?>">

            <label>ID parceiro:</label>
            
            <input type="text" name="dino_plugin_id" value="<?php echo esc_attr(get_option('dino_plugin_id')); ?>">
            <br />
            <br />
            <label>Nome da página com as nototícias dino</label>

            <input type="text" name="dino_plugin_slug_news" value="<?php echo esc_attr(get_option('dino_plugin_slug_news')); ?>">

            <div class="checks-images">
                <div class="item-check">
                    <input type="radio" name="dino_plugin_image" value="image" class="com-imagem-dino" <?php echo get_option('dino_plugin_image') != "" && get_option('dino_plugin_image') == "image" ? "checked" : "" ?>>
                    <label>Receber notícias somente com imagens</label>
                </div>
                <div class="item-check">
                    <input type="radio" name="dino_plugin_image" value="noimage" <?php echo get_option('dino_plugin_image') != "" && get_option('dino_plugin_image') == "noimage" ? "checked" : "" ?>>
                    <label>Receber notícias com imagens e sem imagens</label>
                </div>
            </div>
            
            
            <?php submit_button(); ?>
            
        </form>
    </div>
<?php
}

function get_cat_slug($cat_id) {
	$cat_id = (int) $cat_id;
	$category = get_category($cat_id);
	return $category->slug;
}