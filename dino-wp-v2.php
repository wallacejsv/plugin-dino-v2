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

function update_post_dino($id, $title, $body, $summary, $cat_id, $releaseId, $date, $isChange, $titleTrim) {
    // if($isChange == 1 || $isChange == true) {
        $post_update = array(
            'ID'           => $id,
            // 'post_title'   => $title . "<img src='#'>",
            'post_title'   => $title,
            'post_content' => $body,
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_excerpt' => $summary,
            'post_category' => array($cat_id),
            'post_name' => $titleTrim . '-' . $releaseId,
            'post_date' => $date
        );
        wp_update_post( $post_update );
    // }
}

//insert news dino in table wp_posts
function insert_posts($cat_id, $pageSize = 10) {
    require_once(ABSPATH . 'wp-admin/includes/taxonomy.php'); 

    global $wpdb;
    global $wp_roles;
    global $post;
    global $wp_query;

    $cat_id = get_option('dino_plugin_category_id');

    $slug_admin_category = get_option('dino_plugin_slug_news');
    wp_update_term($cat_id, 'category', array(
        'name' => $slug_admin_category,
        'slug' => $slug_admin_category,
    ));

    //get notícia partner
    $partner_id = get_option('dino_plugin_id');
    //get image partner
    $partner_image = get_option('dino_plugin_image');
    
    if($partner_id != "") {
        $urlWithPartner = "api.dino.com.br/v4/news/".$partner_id."/?pagesize=".$pageSize;
        $json = dino_file_get_contents($urlWithPartner);
        $result = json_decode($json);

        if (isset($result->Items)) {
            $cont = 0;
            foreach ($result->Items as $item) {
                $title = $item->Title;
                $body = $item->Body;
                $quote = $item->Quote;
                $summary = $item->Summary;
                $releaseId = $item->ReleaseId;
                $imageRelease = $item->Image != null ? $item->Image->Url : "";
                $replaceImageQuality80 = str_replace("?quality=100&width=620", "?quality=80&width=620", $imageRelease);
                $replaceImage = str_replace("?quality=80&width=620", "", $replaceImageQuality80);
                $date = $item->PublishedDate;
                //remove spaces title
                $titleTrim = trim($title);

                $isChange = $item->IsAlterado;
                $place = $item->Place;

                //check if the api release is in the wp_posts table
                $post_if = $wpdb->get_var("SELECT count(post_title) FROM $wpdb->posts WHERE post_title like '%$titleTrim%'");

                $post_if_id = $wpdb->get_var("SELECT count(ID) FROM $wpdb->posts WHERE post_name like '%$releaseId%'");
                
                $post_if_page_news = $wpdb->get_var("SELECT count(post_title) FROM $wpdb->posts WHERE post_title like '%Notícias corporativas%'");
                //if you do not have the release in the base wp_posts insert it


                if($partner_image == "image" && $item->Image != null) {
                   
                    if($post_if < 1){

                        $response = (object) array();
                        $post_temp = wp_insert_post( array('post_title' => $title, 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => $place . ' ' .date('j/n/Y', strtotime($date)) . ' - ' . $quote . $body, 'post_excerpt' => $summary, 'post_category' => array($cat_id), 'post_name' => $titleTrim . '-' . $releaseId, 'post_date' => $date ));
        
                        //$response->id = $post_temp;   
                        $tmp = get_post( $post_temp );
        
                        //add release id ao post wp
                        add_post_meta( $tmp->ID, 'idRelease', $releaseId );
        
                        //add image ao post wp
                        uploadRemoteImageAndAttach($replaceImage , $tmp->ID);

                        //update post if isAlterado true
                        // update_post_dino($tmp, $title, $body, $summary, $cat_id, $releaseId, $date, $isChange, $titleTrim);
                    }
                }

                if($partner_image == "noimage" || !$partner_image) {
                    if($post_if < 1){
                        $response = (object) array();
                        $post_temp = wp_insert_post( array('post_title' => $title, 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => $place . ' ' .date('j/n/Y', strtotime($date)) . ' - ' . $quote . $body, 'post_excerpt' => $summary, 'post_category' => array($cat_id), 'post_name' => $titleTrim . '-' . $releaseId, 'post_date' => $date ) );
        
                        //$response->id = $post_temp;   
                        $tmp = get_post( $post_temp );
        
                        //add release id ao post wp
                        add_post_meta( $tmp->ID, 'idRelease', $releaseId );
        
                        //add image ao post wp
                        uploadRemoteImageAndAttach($replaceImage , $tmp->ID);

                        //update post if isAlterado true
                        // update_post_dino($tmp, $title, $body, $summary, $cat_id, $releaseId, $date, $isChange, $titleTrim);
                    }
                }

                
            }
            wp_reset_postdata();
        }
    } else { ?>
        <!-- <script>alert("Vá em configurações - DINO Notícias e preencha o campo 'ID Parceiro'");</script> -->
    <?php }
}


add_action('wp', 'myplugin_schedule_cron');
function myplugin_schedule_cron() {
  if ( !wp_next_scheduled( 'myplugin_cron' ) )
    wp_schedule_event(time(), 'customTime2', 'myplugin_cron');
}

// the CRON hook for firing function
add_action('myplugin_cron', 'myplugin_cron_function');
// the actual function
function myplugin_cron_function() {
    $home_url = home_url();
    wp_mail('w.vieira@dino.com.br','Cron Worked DINO - ' . $home_url, date('r'));
    $id_category = get_cat_ID('Notícias corporativas');
    insert_posts($id_category, 20);
}

add_filter('cron_schedules', 'myplugin_cron_add_intervals');
function myplugin_cron_add_intervals( $schedules ) {
  $schedules['customTime2'] = array(
    'interval' => 600,
    'display' => __('10 minutos cron')
  );
  return $schedules;
}
// add_action('init', 'insert_posts');


function isAlterado($query) {
    global $wp_query;
    global $wpdb;
    global $wp_roles;
    global $post;

    if($wp_query->is_single) {
        
        $partner_id = get_option('dino_plugin_id');
        if($partner_id != "") {
            $postName = $wp_query->query['name'];

            $postNameSplitArray = preg_split("/\-/", $postName);

            $releaseId = end($postNameSplitArray);
            
            $url = "api.dino.com.br/v4/news/" . $releaseId . "/dino";
            $json = dino_file_get_contents($url);
            $result = json_decode($json);
            $montaNoticia = $result->Item;
            
            $title = $montaNoticia->Title;
            $titleTrim = trim($title);
            $body = $montaNoticia->Body;
            $quote = $montaNoticia->Quote;
            $summary = $montaNoticia->Summary;
            $date = $montaNoticia->PublishedDate;

            $imageRelease = $montaNoticia->Image->Url != null ? $montaNoticia->Image->Url : "";
            $replaceImageQuality80 = str_replace("?quality=100&width=620", "?quality=80&width=620", $imageRelease);
            $replaceImage = str_replace("?quality=80&width=620", "", $replaceImageQuality80);

            $place = $montaNoticia->Place;
            $isChange = $montaNoticia->IsAlterado;

            
            
            $cat_id = get_option('dino_plugin_category_id');

            if(!is_admin() && $query->is_main_query()) {
                if($isChange) {
                    $post_wpdb = $wpdb->get_results('SELECT * FROM wp_posts WHERE post_name LIKE "%' . $releaseId . '%" AND post_status = "publish"'); 
                    $id_single = (int) $post_wpdb[0]->ID;
                    update_post_dino($id_single, $title, $body, $summary, $cat_id, $releaseId, $date, $isChange, $titleTrim);
                }
            }
            
        }
    }
    
}
add_action('pre_get_posts', 'isAlterado');


function load_posts_not_exists($query) {
    global $wp_query;
    global $wpdb;
    global $wp_roles;
    global $post;
    

    if($wp_query->is_single) {
        
        $partner_id = get_option('dino_plugin_id');
        if($partner_id != "") {
            $postName = $wp_query->query['name'];

            $postNameSplitArray = preg_split("/\-/", $postName);
            
            $post_if = $wpdb->get_var("SELECT COUNT(post_name) FROM $wpdb->posts WHERE post_name like '%$postName%'");
            if ($post_if == '0') {

                // $releaseId = substr($postName, -6); 
                $releaseId = end($postNameSplitArray);
                
                $url = "api.dino.com.br/v4/news/" . $releaseId . "/dino";
                $json = dino_file_get_contents($url);
                $result = json_decode($json);
                $montaNoticia = $result->Item;
                
                $title = $montaNoticia->Title;
                $titleTrim = trim($title);
                $body = $montaNoticia->Body;
                $quote = $montaNoticia->Quote;
                $summary = $montaNoticia->Summary;
                $date = $montaNoticia->PublishedDate;

                $imageRelease = $montaNoticia->Image->Url != null ? $montaNoticia->Image->Url : "";
                $replaceImageQuality80 = str_replace("?quality=100&width=620", "?quality=80&width=620", $imageRelease);
                $replaceImage = str_replace("?quality=80&width=620", "", $replaceImageQuality80);

                $place = $montaNoticia->Place;
                $isChange = $montaNoticia->IsAlterado;
                
                $cat_id = get_option('dino_plugin_category_id');

                $post_if_old = $wpdb->get_var("SELECT count(post_title) FROM $wpdb->posts WHERE post_title like '%$titleTrim%'");
                // var_dump($post_if_old);
                // var_dump($titleTrim);
                
                if($post_if_old == '0') {
                    if(!is_admin() && $query->is_main_query()) {
                        // var_dump("inseriu");
                        $post_temp = wp_insert_post( array('post_title' => $title, 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => $place . ' ' .date('j/n/Y', strtotime($date)) . ' - ' . $quote . $body, 'post_excerpt' => $summary, 'post_category' => array($cat_id), 'post_name' => $titleTrim . '-' . $releaseId, 'post_date' => $date ));

                        $tmp = get_post( $post_temp );
                
                        //add release id ao post wp
                        add_post_meta( $tmp->ID, 'idRelease', $releaseId );

                        //add image ao post wp
                        uploadRemoteImageAndAttach($replaceImage , $tmp->ID);
                        
                    }
                }
            }
        }
    }
}
add_action('pre_get_posts', 'load_posts_not_exists');

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
    // $id_category = get_cat_ID('Notícias corporativas');
    // insert_posts($id_category, 50);
}

register_deactivation_hook( __FILE__, 'deactivate_plugin' );
function deactivate_plugin() {
    $id_category = get_cat_ID('Notícias corporativas');
    wp_delete_category( $id_category );
}

function get_cat_slug($cat_id) {
	$cat_id = (int) $cat_id;
    $category = get_category($cat_id);
    return $category->slug;
}

function admin_js() {
    wp_enqueue_script('admin-js', plugins_url('dino-wp-v2/assets/js/admin.js', dirname(__FILE__)));
    wp_enqueue_style('admin-css', plugins_url('dino-wp-v2/assets/css/admin.css', dirname(__FILE__)));

    wp_register_script( 'plugin_dino_script_php', plugins_url('dino-wp-v2/assets/js/admin.js', dirname(__FILE__)) );
    wp_enqueue_script( 'plugin_dino_script_php');

    $id_category_options = get_option('dino_plugin_category_id');
    $arrayData = array(
        'id_category_dino_slug' => get_cat_slug($id_category_options),
        'id_category_dino' => $id_category_options,
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
}

function form_admin() { ?>
    <div class="wrap wrap-dino-plugin-admin">
        <h2>DINO - Configurações</h2>
        <h3 class="message">"Essa página de configuração carrega uma grande quantidade de notícias, com isso ela pode se tornar mais lenta."</h3>

        <div class="preload" style="display: none;">
            <img src="http://institucional.dino.com.br/wp-content/themes/osum-by-honryou/assets/images/gif-icone-logo-dino.gif"/>
            <p>Isso pode levar alguns minutos.<br> Estamos pegando as notícias para seu site.</p>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields('dino_option_group'); ?>

            <?php 
            $id_category = get_cat_ID('Notícias corporativas');

            $slug_admin_category = get_option('dino_plugin_slug_news');
            $id_category_options = get_option('dino_plugin_category_id');

            if ($slug_admin_category != "") {
                $id_category = get_option('dino_plugin_category_id');
                wp_update_term($id_category, 'category', array(
                    'name' => $slug_admin_category,
                    'slug' => $slug_admin_category,
                ));
            }

            // insert_posts($id_category, 50);
            ?>
             <input type="hidden" name="dino_plugin_category_id" value="<?php echo esc_attr($id_category); ?>">

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
            
            
            <?php submit_button("Salvar"); ?>
            
        </form>
    </div>
<?php
}

