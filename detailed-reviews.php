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
		
// output unordered list of average ratings for a post
function ratings_list($custom_id = null, $return = false) {
	global $wpdb;
	$pid = get_the_ID();
	if (is_numeric($custom_id))
		$pid = $custom_id;

	$ratings = get_ratings($pid);
	if (count($ratings) == 0) return;

	$html = '<ul class="ratings">';
	foreach ($ratings as $cat => $rating) {
		$html .= '<li>';
		$html .= '<label class="rating_label">' . $cat . '</label> ';
		$html .= '<span class="rating_value">';
		$html .= ($rating > 0) ? num_to_stars($rating) : 'No Ratings';
		$html .= '</span></li>';
	}
	$html .= '</ul>';

	if ($return)
		return $html;
	echo $html;
}
		
// output div-based table of average ratings for a post
function ratings_table($custom_id = null, $return = false) {
	global $wpdb;
	$pid = get_the_ID();
	if (is_numeric($custom_id)) {
		$pid = $custom_id;
	}

	$ratings = get_ratings($pid);
	if (count($ratings) == 0) return;

	$html = '<div id="ratings">';
	foreach ($ratings as $cat => $rating) {
		$percent = round( (float) $rating / 5 * 100 );
		$html .= '<div class="rating_label">' . esc_html($cat) . '</div>';
		$html .= '<div class="rating_value"><div class="rating_fill" style="width: ' . $percent . '%"></div></div>';
	}
	$html .= '</div>';

	if ($return) return $html;
	echo $html;
}
	
// return ratings for a specific comment by category
function get_comment_ratings($custom_id = null) {
	global $wpdb, $comment;
	$cid = $comment->comment_ID;
	if (is_numeric($custom_id))
		$cid = $custom_id;

	$categories = get_option('rs_categories');

	$query = "SELECT rating_id, rating_value AS rating_value, {$wpdb->comments}.comment_post_ID AS comment_post_ID
			  FROM {$wpdb->ratings}
			  INNER JOIN {$wpdb->comments}
			  	ON {$wpdb->comments}.comment_ID = {$wpdb->ratings}.comment_id
			  WHERE {$wpdb->comments}.comment_ID = $cid
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

	foreach ($result as $rating) {
		if (!empty($show) && in_array($rating->rating_id, $show))
			$ratings[$categories[$rating->rating_id]] = $rating->rating_value;
	}

	return $ratings;
}

// return average rating for a single comment
function get_average_comment_rating($custom_id = null) {
	global $wpdb, $comment;
	$cid = $comment->comment_ID;
	if (is_numeric($custom_id))
		$cid = $custom_id;

	$ratings = get_comment_ratings($cid);
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

// output unordered list of ratings for a specific comment
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
		$html .= num_to_stars($rating);
		$html .= '</span></li>';
	}
	$html .= '</ul>';

	if ($return)
		return $html;
	echo $html;
}
	
// output table of ratings for a specific comment
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
		$html .= '<td class="rating_value">' . num_to_stars($rating) . '</td>';
		$html .= '</tr>';
	}
	$html .= '</table>';

	if ($return)
		return $html;
	echo $html;
}

// output input list of star ratings inside the comment form
function ratings_input_list($return = false) {
	$pid = get_the_ID();
	$categories = get_option('rs_categories');
	$show = get_post_meta($pid, '_rs_categories', true);
	if (empty($show)) return;

	$html = '<ul class="ratings">';
	foreach ($categories as $cid => $cat) {
		if (in_array($cid, $show)) {
			$html .= '<li>';
			$html .= '<label class="rating_label" style="float: left">' . $cat . '</label> ';
			$html .= '<div class="rating_value">';
			for ($i = 1; $i <= 5; $i++) {
				$html .= '<a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_' . $i . '" title="' . $i . '" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')"></a>';
			}
			$html .= '<input type="hidden" id="' . $cid . '_rating" name="' . $cid . '_rating" value="0" />';
			$html .= '</div></li>';
		}
	}
	$html .= '</ul>';

	if ($return)
		return $html;
	echo $html;
}

// output input table of star ratings inside the comment form
function ratings_input_table($return = false) {
	$pid = get_the_ID();
	$categories = get_option('rs_categories');
	$show = get_post_meta($pid, '_rs_categories', true);
	if (empty($show)) return;

	$html = '<table class="ratings">';
	foreach ($categories as $cid => $cat) {
		if (in_array($cid, $show)) {
			$html .= '<tr>';
			$html .= '<td class="rating_label">' . $cat . '</td>';
			$html .= '<td class="rating_value">';
			for ($i = 1; $i <= 5; $i++) {
				$html .= '<a onclick="rateIt(this, ' . $cid . ')" id="' . $cid . '_' . $i . '" title="' . $i . '" onmouseover="rating(this, ' . $cid . ')" onmouseout="rolloff(this, ' . $cid . ')">'
				       . '<i class="fa-solid fa-star"></i></a>';
			}
			$html .= '<input type="hidden" id="' . $cid . '_rating" name="' . $cid . '_rating" value="0" />';
			$html .= '</td></tr>';
		}
	}
	$html .= '</table>';

	if ($return)
		return $html;
	echo $html;
}

// output number of unique positive reviews for a post
function positive_reviews($custom_id = null) {
	$ratings = get_positive_negative_count($custom_id);
	echo $ratings['positive'];
}
	
// output number of unique negative reviews for a post
function negative_reviews($custom_id = null) {
	$ratings = get_positive_negative_count($custom_id);
	echo $ratings['negative'];
}

// return array of positive and negative review counts for a post
function get_positive_negative_count($custom_id = null) {
	global $wpdb;
	$pid = get_the_ID();
	if (is_numeric($custom_id))
		$pid = $custom_id;

	$categories = get_option('rs_categories');

	$query = "SELECT AVG(rating_value) AS rating_value
			  FROM {$wpdb->ratings}
			  INNER JOIN {$wpdb->comments}
			  	ON {$wpdb->comments}.comment_ID = {$wpdb->ratings}.comment_id
			  WHERE {$wpdb->comments}.comment_post_ID = $pid
			  	AND {$wpdb->comments}.comment_approved = 1
			  	AND {$wpdb->ratings}.rating_value > 0
			  GROUP BY {$wpdb->ratings}.comment_id";

	$result = $wpdb->get_results($query);

	$positive = 0;
	$negative = 0;
	foreach ($result as $row) {
		if ($row->rating_value >= 3) {
			$positive++;
		} else {
			$negative++;
		}
	}

	return array('positive' => $positive, 'negative' => $negative);
}

// round a number to the nearest 0.5
function round_to_half($num = 0) {
	return floor($num * 2) / 2;
}

// convert numeric rating to star spans
function num_to_stars($num) {
	$stars = round_to_half($num);
	$num = round($num, 2);

	$html = '';
	for ($i = 0; $i < floor($stars); $i++)
		$html .= '<span class="star-full" alt="' . $num . '"></span>';

	if (floor($stars) != $stars)
		$html .= '<span class="star-half" alt="' . $num . '"></span>';

	if (ceil($stars) < 5)
		for ($i = ceil($stars); $i < 5; $i++)
			$html .= '<span class="star-none" alt="' . $num . '"></span>';

	return $html;
}

// initialize review settings and filters
add_action('init', 'rs_init');

function rs_init() {
	wp_register_script('rs_js', plugins_url('detailed-reviews.js', __FILE__));
	wp_enqueue_script('rs_js');

	if (is_admin()) {
		add_action('save_post', 'wprs_box_hook', 5, 2);
	}

	// always embed schema-wrapped comment text
	add_filter('get_comment_text', 'rs_comment_text');

	// when comment is posted
	add_action('comment_post', 'rs_comment_posted');

	// require ratings on comments if enabled
	$rs_require_rating = get_option('rs_require_rating');
	if ($rs_require_rating)
		add_filter('preprocess_comment', 'rs_preprocess');

	// post sorting by rating or comment count
	$sort = get_option('rs_sort');
	if ($sort == 'rating' || (isset($_GET['v_orderby']) && $_GET['v_orderby'] == 'rating')) {
		add_filter('posts_fields', 'rs_weighted_fields');
		add_filter('posts_join', 'rs_weighted_join');
		add_filter('posts_groupby', 'rs_weighted_groupby');
		add_filter('posts_orderby', 'rs_weighted_orderby');
	} else if ($sort == 'comments' || (isset($_GET['v_orderby']) && $_GET['v_orderby'] == 'comments')) {
		add_filter('posts_orderby', 'rs_comments_orderby');
	}
}


// append comment rating table after comment text
function rs_comment_text($content) {
	global $comment;

	$comment_id = $comment->comment_ID;
	$comment_post_id = $comment->comment_post_ID;
	$categories = get_post_meta($comment_post_id, '_rs_categories', true);
	if (empty($categories)) return $content;

	$post_title = get_post_field( 'post_title', $comment_post_id );
	$date = esc_attr(get_comment_date('Y-m-d', $comment_id));
	$author_name = esc_html(get_comment_author($comment_id));
	$rating_value = substr(get_average_comment_rating($comment_id), 0, 4);

	ob_start();
	?>
	<div itemprop="review" itemscope itemtype="http://schema.org/Review">
		
		<?php
		$schema_item_type = apply_filters( 'detailed_reviews_itemreviewed_type', 'http://schema.org/Thing', $comment_post_id );
		?>
		<span itemprop="itemReviewed" itemscope itemtype="<?php echo esc_attr( $schema_item_type ); ?>">
    		<meta itemprop="name" content="<?php echo esc_attr( $post_title ); ?>">
		</span>

		<div itemprop="author" itemscope itemtype="http://schema.org/Person">
			<meta itemprop="name" content="<?php echo $author_name; ?>">
		</div>

		<meta itemprop="datePublished" content="<?php echo $date; ?>">

		<div itemprop="description"><?php echo $content; ?></div>

        <?php if ( $rating_value > 0 ) : ?>
                <div itemprop="reviewRating" itemscope itemtype="http://schema.org/Rating">
                        <small>Overall Score: (<span itemprop="ratingValue"><?php echo $rating_value; ?></span>/5.00)</small>
                        <meta itemprop="worstRating" content="1" />
                        <meta itemprop="bestRating" content="5" />
                </div>
        <?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
}


// validate ratings before saving a comment
function rs_preprocess($incoming_comment) {
	if ($incoming_comment['comment_type'] != 'pingback' && $incoming_comment['comment_type'] != 'trackback') {
		$pid = $incoming_comment['comment_post_ID'];
		$show = get_post_meta($pid, '_rs_categories', true);

		if (empty($show)) return $incoming_comment;

		foreach ($show as $cid) {
			if (!isset($_POST[$cid . '_rating']) || $_POST[$cid . '_rating'] == 0) {
				wp_die(
					'You must leave a rating with your comment. Go back and click on the stars to rate from 1 to 5. Your text appears below so that you can copy it into the form again:<br /><br />' . $incoming_comment['comment_content'],
					'Rating is Required'
				);
			}
		}
	}

	return $incoming_comment;
}
		
// save submitted ratings after comment is posted
function rs_comment_posted($comment_ID, $status = null) {
	global $wpdb;
	$categories = get_option('rs_categories');

	foreach ($categories as $id => $cat) {
		if (isset($_POST[$id . '_rating']) && $_POST[$id . '_rating'] > 0 && $_POST[$id . '_rating'] <= 5) {
			$wpdb->query($wpdb->prepare(
				"INSERT INTO {$wpdb->ratings} (comment_id, rating_id, rating_value) VALUES (%d, %d, %f)",
				$comment_ID, $id, $_POST[$id . '_rating']
			));
		}
	}
}
		
// output category checkboxes on post edit screen
function rs_rating_categories_box() {
	global $post;
	$categories = get_option('rs_categories');
	$mine = get_post_meta($post->ID, '_rs_categories', true);

	echo '<ul class="categorychecklist form-no-clear">';
	foreach ($categories as $id => $category) {
		echo '<li><input type="checkbox" name="rs_categories[]" value="' . $id . '"';
		if (!empty($mine) && in_array($id, $mine)) echo ' checked="checked"';
		echo '> <label>' . $category . '</label></li>';
	}
	echo '</ul>';
}

// save selected rating categories when post is saved
function wprs_box_hook($post_id, $post) {
	if ($post->post_type != 'revision' && isset($_POST['visitlink'])) {
		$categories = !empty($_POST['rs_categories']) ? $_POST['rs_categories'] : array();
		update_post_meta($post_id, '_rs_categories', $categories);
	}
}

// append ratings table or list to post content
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

// append comment ratings to comment content
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

// add weighted rating fields to post query
function rs_weighted_fields($content) {
	global $wpdb;

	$content .= ", (SUM({$wpdb->ratings}.rating_value) / COUNT({$wpdb->ratings}.rating_id)) AS rs_rating, ";
	$content .= "(COUNT({$wpdb->comments}.comment_ID) / (COUNT({$wpdb->comments}.comment_ID) + 10)) * ";
	$content .= "(SUM({$wpdb->ratings}.rating_value) / COUNT({$wpdb->ratings}.rating_id)) ";
	$content .= "+ (5 / (COUNT({$wpdb->comments}.comment_ID) + 10)) * 3 AS rs_weighted";

	return $content;
}

// join comments and ratings tables for weighted sorting
function rs_weighted_join($content) {
	global $wpdb;

	$content .= " LEFT OUTER JOIN {$wpdb->comments} ON {$wpdb->posts}.ID = {$wpdb->comments}.comment_post_ID "
	          . "AND {$wpdb->comments}.comment_approved = 1 "
	          . "LEFT OUTER JOIN {$wpdb->ratings} ON {$wpdb->comments}.comment_ID = {$wpdb->ratings}.comment_id "
	          . "AND {$wpdb->ratings}.rating_value > 0 ";

	return $content;
}
	
// add post id to group by clause for weighted sorting
function rs_weighted_groupby($content) {
	global $wpdb;
	if (!empty($content))
		return $content . ', ' . $wpdb->posts . '.ID';
	return $wpdb->posts . '.ID';
}

// order posts by weighted rating then by date
function rs_weighted_orderby($content) {
	global $wpdb;
	return 'rs_weighted DESC, ' . $wpdb->posts . '.post_date DESC';
}

// render post ratings average and stars
function detailed_reviews_render_stars( $rating, $max = 5 ) {
	$full_stars = floor( $rating );
	$half_star = ( $rating - $full_stars >= 0.25 && $rating - $full_stars < 0.75 );
	$empty_stars = $max - $full_stars - ( $half_star ? 1 : 0 );

	$html = '<span class="fa-stars">';
	for ( $i = 0; $i < $full_stars; $i++ ) {
		$html .= '<i class="fa-solid fa-star"></i>';
	}
	if ( $half_star ) {
		$html .= '<i class="fa-solid fa-star-half-stroke"></i>';
	}
	for ( $i = 0; $i < $empty_stars; $i++ ) {
		$html .= '<i class="fa-regular fa-star"></i>';
	}
	$html .= '</span>';
	return $html;
}

// output inline css for rating input styling
add_action('wp_head', function() {
	?>
	<style>
		table.ratings {
			width: 100%;
			font-size: 18px;
			border-collapse: collapse;
			background-color: #fff !important;
		}
		table.ratings tr:nth-child(even) {
			background-color: #f9f9f9 !important;
		}
		table.ratings tr:nth-child(odd) {
			background-color: #ffffff !important;
		}
		table.ratings tr:hover {
			background-color: #f1f1f1 !important;
		}
		table.ratings .rating_label {
			font-weight: 600;
			font-size: 18px;
			padding: 10px !important;
		}
		table.ratings .rating_value {
			padding: 10px !important;
		}
		table.ratings .rating_value i {
			font-size: 22px;
			cursor: pointer;
			margin-left: 4px;
			vertical-align: middle;
			color: #ddd;
			transition: color 0.05s linear;
		}
		table.ratings .rating_value a.on i,
		table.ratings .rating_value a.hovered i {
			color: #f0c040 !important;
		}
		.detailed-reviews-rating {
			font-size: 20px;
			vertical-align: middle;
			color: #f0c040;
		}
		.detailed-reviews-rating i {
			margin-right: 3px;
		}
		#ratings {
			margin-top: 15px;
			background: #fefefe;
			border: 1px solid #e0e0e0;
			border-radius: 6px;
			padding: 20px;
			font-size: 16px;
			width: 100%;
		}
		#ratings .rating_label {
			margin-bottom: 5px;
			font-weight: 600;
			color: #333;
		}
		#ratings .rating_value {
			margin-bottom: 15px;
			width: 100%;
			background: #eee;
			border-radius: 4px;
			height: 16px;
			position: relative;
			overflow: hidden;
		}
		#ratings .rating_fill {
			background: #f0c040;
			height: 100%;
			border-radius: 4px;
		}
	</style>
	<?php
});

// Ref: ChatGPT
// Ref: http://www.wpreviewsite.com
// Ref: http://www.dangrossman.info
