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

// return average ratings for each category of a post
function get_ratings( $custom_id = null ) {
    global $wpdb;

    $pid = get_the_ID();
    if ( is_numeric( $custom_id ) ) {
        $pid = (int) $custom_id;
    }

    $categories = defined( 'DETAILED_REVIEWS_CATEGORIES' ) && is_array( DETAILED_REVIEWS_CATEGORIES )
        ? DETAILED_REVIEWS_CATEGORIES
        : array();
    if ( empty( $categories ) ) {
        return array();
    }

    $query = $wpdb->prepare(
        "SELECT rating_id, SUM(rating_value) / COUNT(rating_value) AS rating_value
        FROM {$wpdb->ratings}
        INNER JOIN {$wpdb->comments}
            ON {$wpdb->comments}.comment_ID = {$wpdb->ratings}.comment_id
        WHERE {$wpdb->comments}.comment_post_ID = %d
            AND {$wpdb->comments}.comment_approved = 1
        GROUP BY rating_id
        ORDER BY rating_id",
        $pid
    );
    $result = $wpdb->get_results( $query );

    $ratings = array();
    foreach ( $categories as $cid => $cat ) {
        $ratings[ $cat ] = 0;
    }

    if ( ! empty( $result ) ) {
        foreach ( $result as $rating ) {
            if ( isset( $categories[ $rating->rating_id ] ) ) {
                $ratings[ $categories[ $rating->rating_id ] ] = $rating->rating_value;
            }
        }
    }

    return $ratings;
}
	
// return average rating across all categories for a post
function get_average_rating( $custom_id = null ) {
    $pid = get_the_ID();
    if ( is_numeric( $custom_id ) ) {
        $pid = (int) $custom_id;
    }

    $ratings = get_ratings( $pid );
    if ( empty( $ratings ) ) {
        return 0;
    }

    $sum   = 0;
    $count = 0;
    foreach ( $ratings as $rating ) {
        if ( $rating > 0 ) {
            $sum   += $rating;
            $count++;
        }
    }

    return ( $count > 0 ) ? $sum / $count : 0;
}
		
// output unordered list of average ratings for a post
function ratings_list( $custom_id = null, $return = false ) {
    $pid = get_the_ID();
    if ( is_numeric( $custom_id ) ) {
        $pid = (int) $custom_id;
    }

    $ratings = get_ratings( $pid );
    if ( empty( $ratings ) ) {
        return;
    }

    $html = '<ul class="ratings">';
    foreach ( $ratings as $cat => $rating ) {
        $html .= '<li>';
        $html .= '<label class="rating_label">' . esc_html( $cat ) . '</label> ';
        $html .= '<span class="rating_value">';
        $html .= ( $rating > 0 ) ? num_to_stars( $rating ) : 'No Ratings';
        $html .= '</span></li>';
    }
    $html .= '</ul>';

    if ( $return ) {
        return $html;
    }
    echo $html;
}
		
// output div-based table of average ratings for a post
function ratings_table( $custom_id = null, $return = false ) {
    $pid = get_the_ID();
    if ( is_numeric( $custom_id ) ) {
        $pid = (int) $custom_id;
    }

    $ratings = get_ratings( $pid );
    if ( empty( $ratings ) ) {
        return;
    }

    $html = '<div id="ratings">';
    foreach ( $ratings as $cat => $rating ) {
        $percent = round( (float) $rating / 5 * 100 );
        $percent = absint( $percent );
        $html   .= '<div class="rating_label">' . esc_html( $cat ) . '</div>';
        $html   .= '<div class="rating_value"><div class="rating_fill" style="width:' . esc_attr( $percent ) . '%"></div></div>';
    }
    $html .= '</div>';

    if ( $return ) {
        return $html;
    }
    echo $html;
}
	
// return ratings for a specific comment by category
function get_comment_ratings( $custom_id = null ) {
    global $wpdb, $comment;
    $cid = isset( $comment->comment_ID ) ? (int) $comment->comment_ID : 0;
    if ( is_numeric( $custom_id ) ) {
        $cid = (int) $custom_id;
    }
    if ( $cid < 1 ) {
        return array();
    }

    $categories = defined( 'DETAILED_REVIEWS_CATEGORIES' ) && is_array( DETAILED_REVIEWS_CATEGORIES )
        ? DETAILED_REVIEWS_CATEGORIES
        : array();
    if ( empty( $categories ) ) {
        return array();
    }

    $query = $wpdb->prepare(
        "SELECT rating_id, rating_value, {$wpdb->comments}.comment_post_ID
        FROM {$wpdb->ratings}
        INNER JOIN {$wpdb->comments}
            ON {$wpdb->comments}.comment_ID = {$wpdb->ratings}.comment_id
        WHERE {$wpdb->comments}.comment_ID = %d
        ORDER BY rating_id",
        $cid
    );
    $result = $wpdb->get_results( $query );
    if ( empty( $result ) ) {
        return array();
    }

    $ratings = array_fill_keys( array_values( $categories ), 0 );
    foreach ( $result as $rating ) {
        if ( isset( $categories[ $rating->rating_id ] ) ) {
            $ratings[ $categories[ $rating->rating_id ] ] = $rating->rating_value;
        }
    }

    return $ratings;
}

// return average rating for a single comment
function get_average_comment_rating( $custom_id = null ) {
    global $comment;

    $cid = isset( $comment->comment_ID ) ? (int) $comment->comment_ID : 0;
    if ( is_numeric( $custom_id ) ) {
        $cid = (int) $custom_id;
    }
    if ( $cid < 1 ) {
        return 0;
    }

    $ratings = get_comment_ratings( $cid );
    if ( empty( $ratings ) ) {
        return 0;
    }

    $sum   = 0;
    $count = 0;
    foreach ( $ratings as $rating ) {
        if ( $rating > 0 ) {
            $sum   += $rating;
            $count++;
        }
    }

    return ( $count > 0 ) ? $sum / $count : 0;
}

// output unordered list of ratings for a specific comment
function comment_ratings_list( $custom_id = null, $return = false ) {
    global $comment;

    $cid = isset( $comment->comment_ID ) ? (int) $comment->comment_ID : 0;
    if ( is_numeric( $custom_id ) ) {
        $cid = (int) $custom_id;
    }
    if ( $cid < 1 ) {
        return;
    }

    $ratings = get_comment_ratings( $cid );
    if ( empty( $ratings ) ) {
        return;
    }

    $html = '<ul class="ratings">';
    foreach ( $ratings as $cat => $rating ) {
        $html .= '<li>';
        $html .= '<label class="rating_label">' . esc_html( $cat ) . '</label> ';
        $html .= '<span class="rating_value">' . num_to_stars( $rating ) . '</span>';
        $html .= '</li>';
    }
    $html .= '</ul>';

    if ( $return ) {
        return $html;
    }
    echo $html;
}

// output table of ratings for a specific comment
function comment_ratings_table( $custom_id = null, $return = false ) {
    global $comment;

    $cid = isset( $comment->comment_ID ) ? (int) $comment->comment_ID : 0;
    if ( is_numeric( $custom_id ) ) {
        $cid = (int) $custom_id;
    }
    if ( $cid < 1 ) {
        return;
    }

    $ratings = get_comment_ratings( $cid );
    if ( empty( $ratings ) ) {
        return;
    }

    $html = '<table class="ratings">';
    foreach ( $ratings as $cat => $rating ) {
        $html .= '<tr>';
        $html .= '<td class="rating_label">' . esc_html( $cat ) . '</td>';
        $html .= '<td class="rating_value">' . num_to_stars( $rating ) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';

    if ( $return ) {
        return $html;
    }

    echo $html;
}

// output input list of star ratings inside the comment form
function ratings_input_list( $return = false ) {
    $categories = defined( 'DETAILED_REVIEWS_CATEGORIES' ) && is_array( DETAILED_REVIEWS_CATEGORIES ) ? DETAILED_REVIEWS_CATEGORIES : array();
    if ( empty( $categories ) ) {
        return;
    }

    $html = '<ul class="ratings">';
    foreach ( $categories as $cid => $cat ) {
        $cid   = (int) $cid;
        $cat   = esc_html( $cat );
        $html .= '<li>';
        $html .= '<label class="rating_label">' . $cat . '</label>';
        $html .= '<div class="rating_value">';
        for ( $i = 1; $i <= 5; $i++ ) {
            $star_id = esc_attr( $cid . '_' . $i );
            $html   .= '<a id="' . $star_id . '" title="' . esc_attr( $i ) . '" onclick="rateIt(this,' . esc_js( $cid ) . ')" onmouseover="rating(this,' . esc_js( $cid ) . ')" onmouseout="rolloff(this,' . esc_js( $cid ) . ')"></a>';
        }
        $html .= '<input type="hidden" id="' . esc_attr( $cid . '_rating' ) . '" name="' . esc_attr( $cid . '_rating' ) . '" value="0" />';
        $html .= '</div>';
        $html .= '</li>';
    }
    $html .= '</ul>';

    if ( $return ) {
        return $html;
    }

    echo $html;
}

// output input table of star ratings inside the comment form
function ratings_input_table() {
    if ( ! defined( 'DETAILED_REVIEWS_CATEGORIES' ) || ! is_array( DETAILED_REVIEWS_CATEGORIES ) ) {
        return;
    }

    echo '<table class="ratings">';
    foreach ( DETAILED_REVIEWS_CATEGORIES as $id => $label ) {
        echo '<tr>';
        echo '<td class="rating_label">' . esc_html( $label ) . '</td>';
        echo '<td class="rating_value">';
        for ( $i = 1; $i <= 5; $i++ ) {
            printf(
                '<a id="%s" onclick="rateIt(this,%s)" onmouseover="rating(this,%s)" onmouseout="rolloff(this,%s)"><i class="fa fa-star"></i></a>',
                esc_attr( $id . '_' . $i ),
                esc_js( $id ),
                esc_js( $id ),
                esc_js( $id )
            );
        }
        echo '<input type="hidden" name="' . esc_attr( $id . '_rating' ) . '" id="' . esc_attr( $id . '_rating' ) . '" value="0" />';
        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

// output number of unique positive reviews for a post
function positive_reviews( $custom_id = null ) {
    $ratings = get_positive_negative_count( $custom_id );
    if ( isset( $ratings['positive'] ) ) {
        echo intval( $ratings['positive'] );
    }
}

// output number of unique negative reviews for a post
function negative_reviews( $custom_id = null ) {
    $ratings = get_positive_negative_count( $custom_id );
    if ( isset( $ratings['negative'] ) ) {
        echo intval( $ratings['negative'] );
    }
}

// return array of positive and negative review counts for a post
function get_positive_negative_count( $custom_id = null ) {
    global $wpdb;

    $pid = get_the_ID();
    if ( is_numeric( $custom_id ) ) {
        $pid = (int) $custom_id;
    }

    $query = "
        SELECT AVG(rating_value) AS rating_value
        FROM {$wpdb->ratings}
        INNER JOIN {$wpdb->comments}
            ON {$wpdb->comments}.comment_ID = {$wpdb->ratings}.comment_id
        WHERE {$wpdb->comments}.comment_post_ID = %d
            AND {$wpdb->comments}.comment_approved = 1
            AND {$wpdb->ratings}.rating_value > 0
        GROUP BY {$wpdb->ratings}.comment_id
    ";

    $prepared = $wpdb->prepare( $query, $pid );
    $results  = $wpdb->get_results( $prepared );

    $positive = 0;
    $negative = 0;

    foreach ( $results as $row ) {
        if ( $row->rating_value >= 3 ) {
            $positive++;
        } else {
            $negative++;
        }
    }

    return array(
        'positive' => $positive,
        'negative' => $negative,
    );
}

// round a number to the nearest 0.5
function round_to_half( $num = 0 ) {
    return floor( $num * 2 ) / 2;
}

// convert numeric rating to star spans
function num_to_stars( $num ) {
    $stars   = round_to_half( $num );
    $display = number_format( $num, 2 );
    $html    = '';
    $full    = floor( $stars );

    for ( $i = 0; $i < $full; $i++ ) {
        $html .= '<span class="star-full" aria-label="' . esc_attr( $display ) . '"></span>';
    }

    if ( $stars > $full ) {
        $html .= '<span class="star-half" aria-label="' . esc_attr( $display ) . '"></span>';
    }

    $empty = 5 - ceil( $stars );
    for ( $i = 0; $i < $empty; $i++ ) {
        $html .= '<span class="star-none" aria-label="' . esc_attr( $display ) . '"></span>';
    }

    return $html;
}

// initialize review settings and filters
add_action( 'init', 'rs_init' );

function rs_init() {
    // register and enqueue front-end script
    if ( ! is_admin() ) {
        wp_enqueue_script(
            'rs-js',
            plugin_dir_url( __FILE__ ) . 'detailed-reviews.js',
            array( 'jquery' ),
            filemtime( plugin_dir_path( __FILE__ ) . 'detailed-reviews.js' ),
            true
        );
    }

    // embed schema-wrapped comment content
    add_filter( 'get_comment_text', 'rs_comment_text' );

    // save ratings when comment is posted
    add_action( 'comment_post', 'rs_comment_posted' );

    // enforce ratings input
    add_filter( 'preprocess_comment', 'rs_preprocess' );

    // apply custom post sorting based on v_orderby parameter
    $orderby = filter_input( INPUT_GET, 'v_orderby', FILTER_SANITIZE_STRING );
    if ( 'rating' === $orderby ) {
        add_filter( 'posts_fields',   'rs_weighted_fields' );
        add_filter( 'posts_join',     'rs_weighted_join' );
        add_filter( 'posts_groupby',  'rs_weighted_groupby' );
        add_filter( 'posts_orderby',  'rs_weighted_orderby' );
    } elseif ( 'comments' === $orderby ) {
        add_filter( 'posts_orderby', 'rs_comments_orderby' );
    }
}

// append comment rating table after comment text
function rs_comment_text( $content ) {
    global $comment;

    if ( ! isset( $comment->comment_ID, $comment->comment_post_ID ) ) {
        return $content;
    }

    $comment_id = (int) $comment->comment_ID;
    $comment_post_id = (int) $comment->comment_post_ID;
    $categories = defined( 'DETAILED_REVIEWS_CATEGORIES' ) && is_array( DETAILED_REVIEWS_CATEGORIES ) ? DETAILED_REVIEWS_CATEGORIES : array();
    if ( empty( $categories ) ) {
        return $content;
    }

    $post_title = get_post_field( 'post_title', $comment_post_id );
    $author_name = get_comment_author( $comment_id );
    $date_iso = get_comment_date( 'c', $comment_id );
    $rating_value = floatval( get_average_comment_rating( $comment_id ) );
    $rating_format = number_format( $rating_value, 2 );

    $schema_item_type = apply_filters( 'detailed_reviews_itemreviewed_type', 'https://schema.org/Thing', $comment_post_id );

    ob_start();
    ?>
    <div itemprop="review" itemscope itemtype="https://schema.org/Review">
        <div itemprop="itemReviewed" itemscope itemtype="<?php echo esc_attr( $schema_item_type ); ?>">
            <meta itemprop="name" content="<?php echo esc_attr( $post_title ); ?>">
        </div>
        <div itemprop="author" itemscope itemtype="https://schema.org/Person">
            <meta itemprop="name" content="<?php echo esc_attr( $author_name ); ?>">
        </div>
        <meta itemprop="datePublished" content="<?php echo esc_attr( $date_iso ); ?>"/>
        <div itemprop="description"><?php echo wp_kses_post( $content ); ?></div>
        <?php if ( $rating_value > 0 ) : ?>
            <div itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                <small>Overall Score: (<span itemprop="ratingValue"><?php echo esc_html( $rating_format ); ?></span>/5.00)</small>
                <meta itemprop="worstRating" content="1"/>
                <meta itemprop="bestRating" content="5"/>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// validate ratings before saving a comment
function rs_preprocess( $incoming_comment ) {
    $type = $incoming_comment['comment_type'] ?? '';
    if ( in_array( $type, array( 'pingback', 'trackback' ), true ) ) {
        return $incoming_comment;
    }

    $active_categories = defined( 'DETAILED_REVIEWS_CATEGORIES' ) && is_array( DETAILED_REVIEWS_CATEGORIES )
        ? array_keys( DETAILED_REVIEWS_CATEGORIES )
        : array();
    if ( empty( $active_categories ) ) {
        return $incoming_comment;
    }

    $pid = isset( $incoming_comment['comment_post_ID'] ) ? (int) $incoming_comment['comment_post_ID'] : 0;

    foreach ( $active_categories as $category_id ) {
        $key  = $category_id . '_rating';
        $rate = filter_input(
            INPUT_POST,
            $key,
            FILTER_VALIDATE_INT,
            array( 'options' => array( 'min_range' => 1, 'max_range' => 5 ) )
        );
        if ( false === $rate ) {
            $message  = 'you must rate all required categories from 1 to 5 before submitting your review. your comment text appears below so you can copy and resubmit it:<br><br>';
            $message .= wp_kses_post( $incoming_comment['comment_content'] );
            $message .= '<br><br><a href="' . esc_url( get_permalink( $pid ) ) . '#respond">← go back to review form</a>';
            wp_die( $message, 'rating required' );
        }
    }

    return $incoming_comment;
}
		
// save submitted ratings after comment is posted
function rs_comment_posted( $comment_ID, $status = '' ) {
    global $wpdb;

    $categories = defined( 'DETAILED_REVIEWS_CATEGORIES' ) && is_array( DETAILED_REVIEWS_CATEGORIES )
        ? DETAILED_REVIEWS_CATEGORIES
        : array();

    foreach ( $categories as $id => $label ) {
        $key    = $id . '_rating';
        $rating = filter_input(
            INPUT_POST,
            $key,
            FILTER_VALIDATE_INT,
            array( 'options' => array( 'min_range' => 1, 'max_range' => 5 ) )
        );

        if ( false === $rating ) {
            continue;
        }

        $wpdb->insert(
            $wpdb->ratings,
            array(
                'comment_id'   => $comment_ID,
                'rating_id'    => (int) $id,
                'rating_value' => $rating,
            ),
            array( '%d', '%d', '%f' )
        );
    }
}

// add weighted rating fields to post query
function rs_weighted_fields( $fields ) {
    global $wpdb;

    $avg = "SUM({$wpdb->ratings}.rating_value) / COUNT({$wpdb->ratings}.rating_id)";
    $count = "COUNT({$wpdb->comments}.comment_ID)";
    $weight = "( ( $count / ( $count + 10 ) ) * $avg + ( 5 / ( $count + 10 ) ) * 3 )";

    $fields .= ", $avg AS rs_rating, $weight AS rs_weighted";

    return $fields;
}

// join comments and ratings tables for weighted sorting
function rs_weighted_join( $join ) {
    global $wpdb;

    $join .= " LEFT JOIN {$wpdb->comments} AS rc
        ON rc.comment_post_ID = {$wpdb->posts}.ID
        AND rc.comment_approved = 1";

    $join .= " LEFT JOIN {$wpdb->ratings} AS rr
        ON rr.comment_id = rc.comment_ID
        AND rr.rating_value > 0";

    return $join;
}

// add post id to group by clause for weighted sorting
function rs_weighted_groupby( $groupby ) {
    global $wpdb;

    if ( ! empty( $groupby ) ) {
        return $groupby . ', ' . $wpdb->posts . '.ID';
    }

    return $wpdb->posts . '.ID';
}

// order posts by weighted rating then by date
function rs_weighted_orderby( $orderby ) {
    global $wpdb;

    return 'rs_weighted DESC, ' . $wpdb->posts . '.post_date DESC';
}

// render post ratings average and stars
function detailed_reviews_render_stars( $rating, $max = 5 ) {
    $rating = (float) $rating;
    $max     = (int)   $max;
    $full    = floor( $rating );
    $half    = ( $rating - $full ) >= 0.25 && ( $rating - $full ) < 0.75;
    $empty   = $max - $full - ( $half ? 1 : 0 );
    $html    = '<span class="fa-stars">';

    for ( $i = 0; $i < $full; $i++ ) {
        $html .= '<i class="fa-solid fa-star"></i>';
    }

    if ( $half ) {
        $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
    }

    for ( $i = 0; $i < $empty; $i++ ) {
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
