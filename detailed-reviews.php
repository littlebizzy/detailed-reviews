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

// inject review input table into comment form
function ratings_input_table() {
    $categories = get_option('rs_categories');
    if (!is_array($categories)) return;
    echo '<div class="ratings-input-table">';
    foreach ($categories as $cid => $label) {
        echo '<div class="ratings-row" data-cat-id="' . esc_attr($cid) . '">';
        echo '<label>' . esc_html($label) . ':</label> ';
        for ($i = 1; $i <= 5; $i++) {
            echo '<i class="fa-regular fa-star" data-rating="' . $i . '"></i>';
        }
        echo '<input type="hidden" name="rating_' . esc_attr($cid) . '" value="0">';
        echo '</div>';
    }
    echo '</div>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".ratings-row").forEach(function(row){let stars=row.querySelectorAll(".fa-star");stars.forEach(function(star,index){star.addEventListener("click",function(){let rating=index+1;stars.forEach(function(s,i){s.className=i<rating?"fa-solid fa-star":"fa-regular fa-star"});row.querySelector("input").value=rating})})})});</script>';
}

// save comment ratings as comment meta
add_action('comment_post', function($comment_id) {
    $categories = get_option('rs_categories');
    if (!is_array($categories)) return;
    foreach ($categories as $cid => $label) {
        if (isset($_POST['rating_' . $cid])) {
            $val = intval($_POST['rating_' . $cid]);
            if ($val >= 1 && $val <= 5) {
                add_comment_meta($comment_id, 'rs_ratings[' . $cid . ']', $val);
            }
        }
    }
});

// require ratings if post has _rs_categories
add_filter('preprocess_comment', function($commentdata) {
    $post_id = isset($commentdata['comment_post_ID']) ? (int) $commentdata['comment_post_ID'] : 0;
    if (!$post_id) return $commentdata;
    $enabled = get_post_meta($post_id, '_rs_categories', true);
    if (!$enabled) return $commentdata;
    $categories = get_option('rs_categories');
    if (!is_array($categories)) return $commentdata;
    foreach ($categories as $cid => $label) {
        if (in_array($cid, $enabled) && empty($_POST['rating_' . $cid])) {
            wp_die('Error: You must rate all review categories.');
        }
    }
    return $commentdata;
});

// get average per-post rating
function get_average_rating($post_id = null) {
    global $wpdb;
    $post_id = $post_id ?: get_the_ID();
    $comments = get_comments(['post_id' => $post_id, 'status' => 'approve']);
    $categories = get_option('rs_categories');
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
    $categories = get_option('rs_categories');
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

// fallback ratings table function
function ratings_table() {
    $categories = get_option('rs_categories');
    $post_id = get_the_ID();
    if (!$categories) return;
    echo '<ul class="ratings-table">';
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
        echo '<li>' . esc_html($label) . ': ' . $avg . ' / 5</li>';
    }
    echo '</ul>';
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
