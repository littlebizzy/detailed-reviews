<?php
/*
Plugin Name: Detailed Reviews
Plugin URI: https://www.littlebizzy.com/plugins/detailed-reviews
Description: Allows 5-star reviews with multiple categories per post. Compatible with legacy WP Review Site data.
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

// define categories constant if not already defined
if ( ! defined('DETAILED_REVIEWS_CATEGORIES') ) {
    define('DETAILED_REVIEWS_CATEGORIES', []);
}

// enqueue javascript
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('detailed-reviews-js', plugin_dir_url(__FILE__) . 'detailed-reviews.js', [], null, true);
});

// inject legacy style review input table into comment form
function ratings_input_table() {
    if ( ! defined('DETAILED_REVIEWS_CATEGORIES') || empty(DETAILED_REVIEWS_CATEGORIES) ) {
        return;
    }

    echo '<table class="ratings"><tbody>';
    foreach (DETAILED_REVIEWS_CATEGORIES as $index => $label) {
        echo '<tr><td class="rating_label">' . esc_html($label) . '</td><td class="rating_value">';
        for ( $i = 1; $i <= 5; $i++ ) {
            $id = $index . '_' . $i;
            echo '<a onclick="return rateIt(this,' . $index . ')" id="' . $id . '" title="' . $i . '" onmouseover="return rating(this,' . $index . ')" onmouseout="return rolloff(this,' . $index . ')"></a>';
        }
        echo '<input type="hidden" id="' . $index . '_rating" name="' . $index . '_rating" value="0"></td></tr>';
    }
    echo '</tbody></table>';
}

// save comment ratings as comment meta
add_action('comment_post', function($comment_id) {
    $categories = DETAILED_REVIEWS_CATEGORIES;
    if (!is_array($categories)) return;
    foreach ($categories as $cid => $label) {
        if (isset($_POST[$cid . '_rating'])) {
            $val = intval($_POST[$cid . '_rating']);
            if ($val >= 1 && $val <= 5) {
                add_comment_meta($comment_id, 'rs_ratings[' . $cid . ']', $val);
            }
        }
    }
});

// require ratings if post has _rs_categories
add_filter('preprocess_comment', function($commentdata) {
    $categories = DETAILED_REVIEWS_CATEGORIES;
    if (!is_array($categories)) return $commentdata;
    foreach ($categories as $cid => $label) {
        if (empty($_POST['rating_' . $cid])) {
            wp_die('error: all review categories must be rated.');
        }
    }
    return $commentdata;
});

// get average per-post rating
function get_average_rating($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $comments = get_comments(['post_id' => $post_id, 'status' => 'approve']);
    $categories = DETAILED_REVIEWS_CATEGORIES;
    if (!$comments || !$categories) return 0;
    $sum = 0;
    $count = 0;
    foreach ($comments as $comment) {
        foreach ($categories as $cid => $label) {
            $val = get_comment_meta($comment->comment_ID, 'rs_ratings[' . $cid . ']', true);
            if ($val) {
                $sum += $val;
                $count++;
            }
        }
    }
    return ($count > 0) ? round($sum / $count, 2) : 0;
}

// get average rating of a single comment
function get_average_comment_rating($comment_id = null) {
    $comment_id = $comment_id ?: get_comment_ID();
    $categories = DETAILED_REVIEWS_CATEGORIES;
    if (!$categories) return 0;
    $sum = 0;
    $count = 0;
    foreach ($categories as $cid => $label) {
        $val = get_comment_meta($comment_id, 'rs_ratings[' . $cid . ']', true);
        if ($val) {
            $sum += $val;
            $count++;
        }
    }
    return ($count > 0) ? round($sum / $count, 2) : 0;
}

// legacy-style frontend ratings table
function ratings_table() {
    $categories = DETAILED_REVIEWS_CATEGORIES;
    $post_id = get_the_ID();
    if (!$categories) return;
    echo '<div id="ratings">';
    foreach ($categories as $cid => $label) {
        $total = 0;
        $count = 0;
        $comments = get_comments(['post_id' => $post_id, 'status' => 'approve']);
        foreach ($comments as $comment) {
            $val = get_comment_meta($comment->comment_ID, 'rs_ratings[' . $cid . ']', true);
            if ($val) {
                $total += $val;
                $count++;
            }
        }
        $avg = ($count > 0) ? round($total / $count, 2) : 0;
        echo '<div class="rating_label">' . esc_html($label) . '</div><div class="rating_value">';
        for ( $i = 1; $i <= 5; $i++ ) {
            if ( $avg >= $i ) {
                echo '<span class="star-full" alt="' . esc_attr($avg) . '"></span>';
            } elseif ( $avg >= ( $i - 0.5 ) ) {
                echo '<span class="star-half" alt="' . esc_attr($avg) . '"></span>';
            } else {
                echo '<span class="star-none" alt="' . esc_attr($avg) . '"></span>';
            }
        }
        echo '</div>';
    }
    echo '<div class="clear-zero"></div></div>';
}

// render schema.org aggregate rating block
function aggregate_rating_block($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $comments = get_approved_comments($post_id);
    $total_rating = 0;
    $rating_count = 0;
    foreach ($comments as $comment) {
        $sum = 0;
        $filled = 0;
        foreach (array_keys(DETAILED_REVIEWS_CATEGORIES) as $cid) {
            $val = get_comment_meta($comment->comment_ID, 'rs_ratings[' . $cid . ']', true);
            if ($val > 0) {
                $sum += $val;
                $filled++;
            }
        }
        if ($filled > 0) {
            $total_rating += round($sum / $filled, 2);
            $rating_count++;
        }
    }
    $average = $rating_count ? round($total_rating / $rating_count, 2) : 0;
    echo '<span itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">';
    echo '<span class="starrr" data-rating="' . esc_attr($average) . '"></span>&nbsp;&nbsp;';
    echo '(<span itemprop="ratingValue">' . esc_html(number_format($average, 2)) . '</span>/5.00)';
    echo '<meta itemprop="reviewCount" content="' . esc_attr($rating_count) . '">';
    echo '<meta itemprop="worstRating" content="1">';
    echo '<meta itemprop="bestRating" content="5">';
    echo '</span>';
}

// sort posts by average rating
add_filter('posts_fields', function($fields) {
    if (is_admin()) return $fields;
    if (get_query_var('v_orderby') === 'rating') {
        $fields .= ', (SELECT AVG(meta_value+0) FROM wp_commentmeta WHERE comment_id IN (SELECT comment_ID FROM wp_comments WHERE wp_comments.comment_post_ID = wp_posts.ID AND comment_approved = 1) AND meta_key LIKE "rs_ratings[%") AS rating_avg';
    }
    return $fields;
});

add_filter('posts_orderby', function($orderby) {
    if (is_admin()) return $orderby;
    if (get_query_var('v_orderby') === 'rating') {
        return 'rating_avg DESC';
    }
    return $orderby;
});
