<?php
/*
Plugin Name: Detailed Reviews
Plugin URI: https://www.littlebizzy.com/plugins/detailed-reviews
Description: Allows 5-star reviews with multiple categories, compatible with legacy WP Review Site data.
Version: 1.0.0
Author: LittleBizzy
Author URI: https://www.littlebizzy.com
Requires PHP: 7.0
Tested up to: 6.7
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Update URI: false
GitHub Plugin URI: littlebizzy/detailed-reviews
Primary Branch: master
Text Domain: detailed-reviews
*/

// prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// override wordpress.org with git updater
add_filter( 'gu_override_dot_org', function( $overrides ) {
    $overrides[] = 'detailed-reviews/detailed-reviews.php';
    return $overrides;
}, 999 );
	
// set up custom table aliases
global $wpdb;
$wpdb->ratings = $wpdb->prefix . 'rs_ratings';
$wpdb->visitlinks = $wpdb->prefix . 'rs_visit_links';

// return average ratings for each category of a post
function get_ratings($custom_id = null) {
	global $wpdb;
	$pid = get_the_ID();
	if (is_numeric($custom_id))
		$pid = $custom_id;

	$categories = get_option('rs_categories');

	$query = "SELECT rating_id, SUM(rating_value) / COUNT(rating_value) AS rating_value
			  FROM {$wpdb->ratings}
			  INNER JOIN {$wpdb->comments}
			  	ON {$wpdb->comments}.comment_ID = {$wpdb->ratings}.comment_id
			  WHERE {$wpdb->comments}.comment_post_ID = $pid
			  	AND {$wpdb->comments}.comment_approved = 1
			  GROUP BY rating_id
			  ORDER BY rating_id";

	$result = $wpdb->get_results($query);
	$show = get_post_meta($pid, '_rs_categories', true);

	$ratings = array();
	foreach ($categories as $cid => $cat) {
		if (!empty($show) && in_array($cid, $show))
			$ratings[$cat] = 0;
	}

	if (count($result) > 0) {
		foreach ($result as $rating) {
			if (!empty($show) && in_array($rating->rating_id, $show))
				$ratings[$categories[$rating->rating_id]] = $rating->rating_value;
		}
	}

	return $ratings;
}
	
// return average rating across all categories for a post
function get_average_rating($custom_id = null) {
	global $wpdb;
	$pid = get_the_ID();
	if (is_numeric($custom_id))
		$pid = $custom_id;

	$ratings = get_ratings($pid);

	$sum = 0;
	$count = 0;
	foreach ($ratings as $rating) {
		if ($rating > 0) {
			$sum += $rating;
			$count++;
		}
	}

	return ($count > 0) ? $sum / $count : 0;
}
		
	/*
	 * Outputs an unordered list with average ratings for a specified post. If used within 
	 * The Loop with no arguments, it will display the ratings for the post being displayed. 
	 * The post ID can be overridden with the $custom_id parameter. The output format will be:
	 * 
	 * <ul class="ratings">
	 *  <li><label class="rating_label">Category 1</label> <span class="rating_value"><img src="star.png">...</span></li>
	 *  <li><label class="rating_label">Category 2</label> <span class="rating_value"><img src="star.png">...</span></li>
	 *  <li><label class="rating_label">Category 3</label> <span class="rating_value"><img src="star.png">...</span></li>
	 * </ul>
	 * 
	 */

	function ratings_list($custom_id = null, $return = false) {
		global $id, $wpdb;
		$pid = $id;
		if (is_numeric($custom_id))
			$pid = $custom_id;
					
		$ratings = get_ratings($pid);
		if (count($ratings) == 0) return;
			
		$html = '<ul class="ratings">';
		foreach ($ratings as $cat => $rating) {
			$html .= '<li>';
			$html .= '<label class="rating_label">' . $cat . '</label> ';
			$html .= '<span class="rating_value">';
			
			if ($rating > 0)
				$html .= num_to_stars($rating);
			else
				$html .= 'No Ratings';
			
			$html .= '</span></li>';
		}
		$html .= "</ul>";
		
		if ($return)
			return $html;
		echo $html;
		
	}
		
	/*
	 * Outputs a table with average ratings for a specified post. If used within 
	 * The Loop with no arguments, it will display the ratings for the post being displayed. 
	 * The post ID can be overridden with the $custom_id parameter. The output format will be:
	 * 
	 * <table class="ratings">
	 *  <tr><td class="rating_label">Category 1</td><td class="rating_value"><img src="star.png">...</td></tr>
	 *  <tr><td class="rating_label">Category 2</td><td class="rating_value"><img src="star.png">...</td></tr>
	 *  <tr><td class="rating_label">Category 3</td><td class="rating_value"><img src="star.png">...</td></tr>
	 * </table>
	 * 
	 */
	function ratings_table($custom_id = null, $return = false) {
		global $id, $wpdb;
		$pid = $id;
		if (is_numeric($custom_id))
			$pid = $custom_id;
		
		$ratings = get_ratings($pid);
		if (count($ratings) == 0) return;
			
		$html = '<div id="ratings">';
		foreach ($ratings as $cat => $rating) {
			
			$html .= '<div class="rating_label">' . $cat . '</div>';
			$html .= '<div class="rating_value">';						if ($cat == "OVERALL QUALITY") {				$html .= "";			}
			
			if ($rating > 0)
				$html .= num_to_stars($rating);
			else
				$html .= num_to_stars($rating);
			
			$html .= '</div>';
		}
		$html .= '<div class="clear-zero"></div></div>';

		if ($return)
			return $html;
		echo $html;

	}
	
	/*
	 * Returns a keyed array of ratings for a specified comment. The format of the array:
	 * array( [Category 1] => 2.5, [Category 2] => 3.2, [Category 3] => 4.5 )
	 *
	 * Use num_to_stars to convert numeric values to star images.
	 */
	function get_comment_ratings($custom_id = null) {
		global $wpdb, $comment;
		$pid = $comment->comment_ID;
		if (is_numeric($custom_id))
			$pid = $custom_id;
					
		$categories = get_option('rs_categories');
		
		$query = "SELECT rating_id, rating_value AS `rating_value`, {$wpdb->comments}.comment_post_ID AS `comment_post_ID`
				  FROM {$wpdb->ratings} 
				  INNER JOIN {$wpdb->comments} 
				  	ON {$wpdb->comments}.comment_ID = {$wpdb->ratings}.comment_id 
				  WHERE {$wpdb->comments}.comment_ID = $pid 
				  ORDER BY rating_id";
				  	
		$result = $wpdb->get_results($query);
		
		if (count($result) == 0) return array();
		
		$pid = $result[0]->comment_post_ID;

		$show = get_post_meta($pid, '_rs_categories', true);

		$ratings = array();
		foreach ($categories as $cid => $cat) {
			if (!empty($show) && in_array($cid, $show))		
				$ratings[$cat] = 0;
		}
				
		if (count($result) > 0) {
			foreach ($result as $rating) {
				if (!empty($show) && in_array($rating->rating_id, $show))
					$ratings[$categories[$rating->rating_id]] = $rating->rating_value;
			}
		}
		return $ratings;
	}
	
	/*
	 * Returns the average of the ratings associated with a single review
	 */
	function get_average_comment_rating($custom_id = null) {
		global $wpdb, $comment;
		$pid = $comment->comment_ID;
		if (is_numeric($custom_id))
			$pid = $custom_id;
		
		$ratings = get_comment_ratings($pid);
		$sum = 0;
		$count = 0;
		foreach ($ratings as $rating) {
			if ($rating > 0) {	
				$sum += $rating;
				$count++;
			}
		}
		
		return ($count > 0) ? $sum / $count : 0;	
	}
	
	/*
	 * Outputs an unordered list with ratings given with a specified comment. If used within 
	 * the comment loop with no arguments, it will display the ratings for the comment being displayed. 
	 * The comment ID can be overridden with the $custom_id parameter. The output format will be:
	 * 
	 * <ul class="ratings">
	 *  <li><label class="rating_label">Category 1</label> <span class="rating_value"><img src="star.png">...</span></li>
	 *  <li><label class="rating_label">Category 2</label> <span class="rating_value"><img src="star.png">...</span></li>
	 *  <li><label class="rating_label">Category 3</label> <span class="rating_value"><img src="star.png">...</span></li>
	 * </ul>
	 * 
	 */
	function comment_ratings_list($custom_id = null, $return = false) {
		global $comment, $wpdb;
		$cid = $comment->comment_ID;
		if (is_numeric($custom_id))
			$cid = $custom_id;
				
		$ratings = get_comment_ratings($cid);
		if (count($ratings) == 0) return;

		$html = '<ul class="ratings">';
		foreach ($ratings as $cat => $rating) {
			$html .= '<li>';
			$html .= '<label class="rating_label">' . $cat . '</label> ';
			$html .= '<span class="rating_value">';
			
			if ($rating > 0)
				$html .= num_to_stars($rating);
			else
				$html .= num_to_stars($rating);
			
			$html .= '</span>';
			$html .= '</li>';
		}
		$html .= "</ul>";
		
		if ($return)
			return $html;
		echo $html;

	}
	
	/*
	 * Outputs a table with ratings given with a specified comment. If used within 
	 * the comment loop with no arguments, it will display the ratings for the comment being displayed. 
	 * The comment ID can be overridden with the $custom_id parameter. The output format will be:
	 * 
	 * <table class="ratings">
	 *  <tr><td class="rating_label">Category 1</td><td class="rating_value"><img src="star.png">...</td></tr>
	 *  <tr><td class="rating_label">Category 2</td><td class="rating_value"><img src="star.png">...</td></tr>
	 *  <tr><td class="rating_label">Category 3</td><td class="rating_value"><img src="star.png">...</td></tr>
	 * </table>
	 * 
	 */
	function comment_ratings_table($custom_id = null, $return = false) {
		global $comment, $wpdb;
		$cid = $comment->comment_ID;
		if (is_numeric($custom_id))
			$cid = $custom_id;
				
		$ratings = get_comment_ratings($cid);
		if (count($ratings) == 0) return;
			
		$html = '<table class="ratings">';
		foreach ($ratings as $cat => $rating) {
			$html .= '<tr>';
			$html .= '<td class="rating_label">' . $cat . '</td>';
			$html .= '<td class="rating_value">';
			
			if ($rating > 0)
				$html .= num_to_stars($rating);
			else
				$html .= num_to_stars($rating);
			
			$html .= '</td>';
			$html .= '</tr>';
		}
		$html .= "</table>";
		
		if ($return)
			return $html;
		echo $html;

	}
	
	/* 
	 * Displays the HTML and JavaScript to collect star ratings within the comment form.
	 * Styled with an unordered list.
	 */
	function ratings_input_list($return = false) {
	
		global $id;
		
		$categories = get_option('rs_categories');
		$show = get_post_meta($id, '_rs_categories', true);
		if (empty($show)) return;
	
		$html = '<ul class="ratings">';
		foreach ($categories as $cid => $cat) {
			if (in_array($cid, $show)) {
				$html .= '<li>';
				$html .= '<label class="rating_label" style="float: left">' . $cat . '</label> ';
				$html .= '<div class="rating_value">';
				$html .= '<a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_1" title="1" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>
	                  <a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_2" title="2" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>
	                  <a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_3" title="3" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>
	                  <a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_4" title="4" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>
	                  <a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_5" title="5" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>
	                  <input type="hidden" id="' . $cid . '_rating" name="' . $cid . '_rating" value="0" />';
				$html .= '</div>';
				$html .= '</li>';
			}
		}
		$html .= "</ul>";
		
		if ($return)
			return $html;
		echo $html;
	
	}
	
	/* 
	 * Displays the HTML and JavaScript to collect star ratings within the comment form.
	 * Styled with a table.
	 */
	function ratings_input_table($return = false) {

		global $id;
		
		$categories = get_option('rs_categories');
		$show = get_post_meta($id, '_rs_categories', true);
		if (empty($show)) return;
	
		$html = '<table class="ratings">';
		foreach ($categories as $cid => $cat) {
			if (in_array($cid, $show)) {
				$html .= '<tr>';
				$html .= '<td class="rating_label">' . $cat . '</td>';
				$html .= '<td class="rating_value">';
				$html .= '<a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_1" title="1" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>
	                  <a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_2" title="2" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>
	                  <a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_3" title="3" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>
	                  <a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_4" title="4" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>
	                  <a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_5" title="5" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>
	                  <input type="hidden" id="' . $cid . '_rating" name="' . $cid . '_rating" value="0" />';
				$html .= '</td>';
				$html .= '</tr>';
			}
		}
		$html .= "</table>";	

		if ($return === true)
			return $html;
		echo $html;
	
	}
	
	/*
	* Displays the number of unique raters whose average rating for this post was 3 stars or higher
	*/
	function positive_reviews($custom_id = null) {
	
		$ratings = get_positive_negative_count($custom_id);
		echo $ratings['positive'];
		
	}
	
	/*
	* Displays the number of unique raters whose average rating for this post was less than 3 stars
	*/
	function negative_reviews($custom_id = null) {
	
		$ratings = get_positive_negative_count($custom_id);
		echo $ratings['negative'];

	}
	
	/*
	* Returns an array containing the positive and negative review counts for a post
	*/
	function get_positive_negative_count($custom_id = null) {
		global $id, $wpdb;
		$pid = $id;
		if (is_numeric($custom_id))
			$pid = $custom_id;
		
		$categories = get_option('rs_categories');
		
		$query = "SELECT AVG(rating_value) AS `rating_value` 
				  FROM {$wpdb->ratings} 
				  INNER JOIN {$wpdb->comments} 
				  	ON {$wpdb->comments}.comment_ID = {$wpdb->ratings}.comment_id 
				  WHERE {$wpdb->comments}.comment_post_ID = $pid 
				  	AND {$wpdb->comments}.comment_approved = 1
				  	AND {$wpdb->ratings}.rating_value > 0
				  GROUP BY {$wpdb->ratings}.comment_id";

		$result = $wpdb->get_results($query);

		$positive = 0; $negative = 0;
		if (count($result) > 0) {
			foreach ($result as $row) {
				if ($row->rating_value >= 3)
					$positive++;
				else if ($row->rating_value > 0)
					$negative++;
			}
		}

		return array('positive' => $positive, 'negative' => $negative);

	}
	
	function round_to_half($num = 0) {
		return floor($num * 2) / 2;
	}
	
	function num_to_stars($num) {
	
		$stars = round_to_half($num);
		$num = round($num, 2);
	
		$html = "";
		for ($i = 0; $i < floor($stars); $i++)
			$html .= '<span class="star-full" alt="' . $num . '" /></span>';

		if (floor($stars) != $stars)
			$html .= '<span class="star-half" alt="' . $num . '" /></span>';
	
		if (ceil($stars) < 5)
			for ($i = ceil($stars); $i < 5; $i++)
				$html .= '<span class="star-none" alt="' . $num . '" /></span>';
		
		return $html;
	}
	
	add_action('init', 'rs_init');
		
	function rs_init() {
	
		wp_register_script('rs_js', get_bloginfo('wpurl') . '/wp-content/themes/college/js/review-site.js');
		wp_enqueue_script('rs_js');
		
		//Settings stuff
		if (is_admin()) {
			add_action('save_post', 'wprs_box_hook', 5, 2);
			
			$rs_comment_embed = get_option('rs_comment_embed');
			if (empty($rs_comment_embed))
				add_filter('get_comment_text', 'rs_comment_text');
		}
			
		//When comment is posted
		add_action('comment_post', 'rs_comment_posted');
		
		$rs_require_rating = get_option('rs_require_rating');
		if ($rs_require_rating)
			add_filter('preprocess_comment','rs_preprocess');
		
		//Post sorting
		$sort = get_option('rs_sort');
		if ($sort == 'rating' || (isset($_GET["v_orderby"]) && $_GET["v_orderby"] == "rating")) {
			add_filter('posts_fields', 'rs_weighted_fields');
			add_filter('posts_join', 'rs_weighted_join');
			add_filter('posts_groupby', 'rs_weighted_groupby');
			add_filter('posts_orderby', 'rs_weighted_orderby');
		} else if ($sort == 'comments' || (isset($_GET["v_orderby"]) && $_GET["v_orderby"] == "comments")) {
			add_filter('posts_orderby', 'rs_comments_orderby');
		}
		
	}
		
	function rs_comment_text($content) {
		ob_start();
		comment_ratings_table();
		$table = ob_get_contents();
		ob_end_clean();
		
		return $content . "<br />" . $table;
	}
	
	/* Fires before a comment is saved */
	
	function rs_preprocess($incoming_comment) {
		
		if ($incoming_comment['comment_type'] != 'pingback' && $incoming_comment['comment_type'] != 'trackback') {
			
			$pid = $incoming_comment['comment_post_ID'];
			$show = get_post_meta($pid, '_rs_categories', true);
			
			if (empty($show)) return $incoming_comment;
			
			foreach ($show as $cid) {
				$msg .= "Comparing $cid to " . $_POST[$cid . '_rating'] . "\n";
				if (!isset($_POST[$cid . '_rating']) || $_POST[$cid . '_rating'] == 0) {
					wp_die("You must leave a rating with your comment. Go back and click on the stars to rate from 1 to 5. Your text appears below so that you can copy it into the form again:<br /><br />" . $incoming_comment['comment_content'], "Rating is Required");
				}
			}
	
		}
		return $incoming_comment;
	
	}
		
	/* Fires after a comment is saved */
	function rs_comment_posted($comment_ID, $status = null) {
		global $wpdb;		
		$categories = get_option('rs_categories');
		
		foreach ($categories as $id => $cat) {
			if (isset($_POST[$id . '_rating']) && $_POST[$id . '_rating'] > 0 && $_POST[$id . '_rating'] <= 5) {
				$query = "INSERT INTO " . $wpdb->ratings . " (comment_id, rating_id, rating_value) VALUES (" . $comment_ID . ", " . $id . ", " . $_POST[$id . '_rating'] . ")";
				$wpdb->query($query);
			}
		}
		
	}
		
	/* Adds boxes to the post/page write/edit screens */
	
	function rs_rating_categories_box() {
		global $post;
		$categories = get_option('rs_categories');
		$mine = get_post_meta($post->ID, '_rs_categories', true);
		echo '<ul class="categorychecklist form-no-clear" >';
		foreach ($categories as $id => $category) {
			echo '<li><input type="checkbox" name="rs_categories[]" value="' . $id . '" ';
			if (!empty($mine) && in_array($id, $mine)) echo 'checked="checked" ';
			echo '/> <label>' . $category . '</label></li>';
		}
		echo '</ul>';
	}
	
	function wprs_box_hook($post_id, $post) {
		if ($post->post_type != 'revision' && isset($_POST['visitlink'])) {
			$categories = !empty($_POST['rs_categories']) ? $_POST['rs_categories'] : array();
			update_post_meta($post_id, '_rs_categories', $categories);
		}
	}
	
	function embed_ratings_table($content) {
		if (get_option('rs_embed_format') == 'table') {
			if (get_option('rs_post_embed') == 'top')
				return ratings_table(null, true) . $content;
			return $content . ratings_table(null, true);
		}	
		if (get_option('rs_post_embed') == 'top')
			return ratings_list(null, true) . $content;
		return $content . ratings_list(null, true);
	}
	
	function embed_comment_ratings_table($content) {
		if (get_option('rs_embed_format') == 'table') {
			if (get_option('rs_comment_embed') == 'top')
				return comment_ratings_table(null, true) . $content;
			return $content . comment_ratings_table(null, true);
		}
		if (get_option('rs_comment_embed') == 'top')
			return comment_ratings_list(null, true) . $content;
		return $content . comment_ratings_list(null, true);
	}
	
	function rs_weighted_fields($content) {
		global $wpdb;
		$content .= ", (SUM(" . $wpdb->ratings . ".rating_value) / COUNT(" . $wpdb->ratings . ".rating_id)) AS `rs_rating`, ";
		$content .= "(COUNT(" . $wpdb->comments . ".comment_ID) / (COUNT(" . $wpdb->comments . ".comment_ID) + 10)) * ";
		$content .= "(SUM(" . $wpdb->ratings . ".rating_value) / COUNT(" . $wpdb->ratings . ".rating_id)) ";
		$content .= "+ (5 / (COUNT(" . $wpdb->comments . ".comment_ID) + 10)) * 3 AS `rs_weighted`";
		return $content;
	}
	
	function rs_weighted_join($content) {
		global $wpdb;
		$content .= " LEFT OUTER JOIN " . $wpdb->comments . " ON " . $wpdb->posts . ".ID = " . $wpdb->comments . ".comment_post_ID "
					. "AND " . $wpdb->comments . ".comment_approved = 1 "
					. "LEFT OUTER JOIN " . $wpdb->ratings . " ON " . $wpdb->comments . ".comment_ID = " . $wpdb->ratings . ".comment_id AND " . $wpdb->ratings . ".rating_value > 0 ";
		return $content;
	}
		
	function rs_weighted_groupby($content) {
		global $wpdb;
		if (!empty($content))
			return $content . ", " . $wpdb->posts . ".ID";
		return $wpdb->posts . ".ID";
	}
	
	function rs_weighted_orderby($content) {
		global $wpdb;
		return "`rs_weighted` DESC, " . $wpdb->posts . ".post_date DESC";
	}

// Ref: http://www.wpreviewsite.com
// Ref: http://www.dangrossman.info
