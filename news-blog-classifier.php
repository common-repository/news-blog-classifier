<?php
/*
	Plugin Name: News Blog Classifier
	Plugin URI:  http://yunitairma.esy.es/news-blog-classifier.zip
	Description: This plugin is used to classify content blog to category: Health, Technology, Economy, and Sports
	Version:     1.0.0
	Author:      Irma Yunita
	Author URI:  https://profiles.wordpress.org/irmayunita
*/
/*
	Copyright 2017  Irma Yunita (email : irma.yunita@student.umn.ac.id)
	
	This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); //prevents direct access to the file

ini_set('max_execution_time', 2000);	
$nbc_plugin_dir_path = plugin_dir_path(__FILE__);

/**
 * import sql file and create category if does not exists when install plugin
 * hooked via register_activation_hook
 */
function nbc_install_news_blog_classifier() {
	global $wpdb;
	global $nbc_plugin_dir_path;
	
	
	//============= Import word training from sql file =============//
	//$filename = get_home_path().'wp-content/plugins/wp-news-blog-classifier/includes/news-blog-classifier-word-training.sql';
	$filename = plugins_url( 'includes/news-blog-classifier-word-training.sql', __FILE__ );
	// Temporary variable, used to store current query
	$templine = '';
	// Read in entire file
	$lines = file($filename);
	// Loop through each line
	foreach ($lines as $line)
	{
		// Skip it if it's a comment
		if (substr($line, 0, 2) == '--' || $line == '')
			continue;

		// Add this line to the current segment
		$templine .= $line;
		// If it has a semicolon at the end, it's the end of the query
		if (substr(trim($line), -1, 1) == ';')
		{
			// Perform the query
			$wpdb->query($templine) or print('Error performing query \'<strong>' . $templine . '\': ' . mysql_error() . '<br /><br />');
			// Reset temp variable to empty
			$templine = '';
		}
	}
	
	//============= Create category if does not exists =============//
	require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
	$required_category = ["Kesehatan", "Olahraga", "Teknologi", "Ekonomi"];
	foreach($required_category as $category){
		if(null == category_exists(strtolower($category))){
			wp_create_category($category);
		}
	}
	
}
register_activation_hook(__FILE__, 'nbc_install_news_blog_classifier');

/**
 * return plugin version
 */
function nbc_get_plugin_version(){
    if(!function_exists('get_plugin_data')){
        require_once(ABSPATH .'wp-admin/includes/plugin.php');
    }

    $nbc_plugin_data = get_plugin_data( __FILE__, false, false);
    $nbc_plugin_version = $nbc_plugin_data['Version'];
    return $nbc_plugin_version;
}

/**
 * only if the admin panel is being displayed
 */
if (is_admin ()) {
	add_action('admin_init', 'nbc_admin_init_actions');
	add_action('wp_ajax_nbc_define_category', 'nbc_define_category');
}

/**
 * init action
 */
function nbc_admin_init_actions(){
	global $pagenow;

	if(in_array($pagenow, array('post.php', 'post-new.php'))){ //page post.php or post-new.php is being displayed
		add_action('add_meta_boxes', 'nbc_meta_box_add');
		add_action('admin_enqueue_scripts', 'nbc_load_meta_box_scripts');
		add_action('save_post', 'nbc_save_category', 10, 2);
	}
}
 
/**
 * load JS and CSS on the post.php or post-new.php page
 */
function nbc_load_meta_box_scripts(){
    $nbc_plugin_url = plugin_dir_url( __FILE__ );
    //load CSS
    wp_enqueue_style('nbc_options_page_style', $nbc_plugin_url .'css/nbc-options-page.css', false, nbc_get_plugin_version());
	//load CSS JQuery UI
    wp_enqueue_style('nbc_progress_bar_style', $nbc_plugin_url .'css/nbc-jquery-ui.min.css', false, nbc_get_plugin_version());
    //Load JS
    wp_enqueue_script('nbc_meta_box_js', $nbc_plugin_url . 'js/nbc-get-category.js', array('jquery', 'jquery-ui-progressbar'), nbc_get_plugin_version());
    //Localize script
    wp_localize_script( 'nbc_meta_box_js', 'ajax_object', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nbc_plugin_url' => plugin_dir_url( __FILE__ ),
        'ajax_meta_box_nonce' => wp_create_nonce('nbc_meta_box_nonce')
    ) );
}

/**
 * add meta box
*/
function nbc_meta_box_add() {

  add_meta_box(
	'nbc-meta-box',      // Unique ID
	'News Blog Classifier',    // Title
	'nbc_meta_box_content',   // Callback function
	'post',         // Admin page (or post type)
	'normal',         // Context
	'default'         // Priority
  );
}

/**
 * meta box content
 */
function nbc_meta_box_content( $object, $box ) { 
	//Existing post category
    global $post;
    $existing_category = wp_get_object_terms($post->ID, "category", array("fields" => "names"));
   
    if (count($existing_category) > 0) {
        $currentCategory = 'Current category: <b>'. $existing_category[0]. '</b>';
    }
	?>

<?php wp_nonce_field( basename( __FILE__ ), 'nbc_meta_box_content' ); ?>
	<input type="hidden" name="nbc_new_define_category" id="nbc_new_define_category" value=""/>
	<p>
		<?php echo $currentCategory; ?>
	</p>
	<p>
		<input type="button" id="btn-define-category" class="button-secondary" onclick="nbc_get_classify()" value="Define Category" />
		<span class="nbc_help" title="This section will analyze your post category. It may take quite time consuming.">i</span>
	</p>
	<div id="nbc_progress_bar"><div class="progress-label"></div></div>
	<p id="nbc_content"></p>
	
<?php
	
	
}

/**
 * runs when the author requests category for their post
 */
function nbc_define_category(){
	global $nbc_plugin_dir_path;
	/* Check nonce */
    check_ajax_referer( 'nbc_meta_box_nonce', 'security' );
	
	if(isset($_POST['text_content'])){
		$textContent = strip_tags($_POST['text_content']);
		if($textContent == ''){
			return;
		}
		else{
			if(!function_exists('nbc_main_define_category_from_nbc_plugin')){
				require_once($nbc_plugin_dir_path . 'includes/knn-tfidf-implementation.php');
			}
			
			$categoryName = nbc_main_define_category_from_nbc_plugin($textContent);
			
			$sentence = ['category_name'=>$categoryName];
			
			echo json_encode($sentence);
		}
	}
	
	wp_die();
}

/**
 * runs when the author save or publish post
 */
function nbc_save_category($post_id, $post){
	if ($post->post_type == 'revision') return;
    if (!isset($_POST['nbc_new_define_category'])) return;
	
	$categoryName = $_POST['nbc_new_define_category'];
	
	require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
	if($id = category_exists(strtolower($categoryName))){
		$cat_ids[] = $id;
		//set category for post
		wp_set_post_categories($post_id, $cat_ids);
	}
}



/**
 * drop tables when uninstall
 * hooked via register_uninstall_hook
*/ 
function nbc_uninstall_news_blog_classifier() {
	global $wpdb;
	//Remove our table (if it exists)
	$wpdb->query("DROP TABLE IF EXISTS wp_nbc_word_details");
	$wpdb->query("DROP TABLE IF EXISTS wp_nbc_word_lists");
	$wpdb->query("DROP TABLE IF EXISTS wp_nbc_stopword_lists");
	$wpdb->query("DROP TABLE IF EXISTS wp_nbc_document_collections");
	$wpdb->query("DROP TABLE IF EXISTS wp_nbc_root_words");
	$wpdb->query("DROP TABLE IF EXISTS wp_nbc_words");
	$wpdb->query("DROP TABLE IF EXISTS wp_nbc_word_types");
	$wpdb->query("DROP TABLE IF EXISTS wp_nbc_categories");
}


register_uninstall_hook(__FILE__, 'nbc_uninstall_news_blog_classifier');
?>