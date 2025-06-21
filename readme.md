# Detailed Reviews

## Changelog

### 1.0.0
- initial release
- supports Git Updater
- supports PHP 7.0 to 8.3
- forked from WP Review Site by Dan Grossman (retains some function names)
- no settings panel or UI, made purely for theme integration
- compatible with legacy WP Review Site data using the original `rs_ratings` table linked by comment ID
- supports 5-star reviews with multiple customizable rating categories per post
- includes frontend output functions for ratings input, and output using stars and/or progress bars
- outputs schema.org Review microdata per comment with individual star ratings
- outputs schema.org AggregateRating markup using average score and review count
- requires custom or child theme (e.g. comments template, single template, and functions.php)
- designed for classic PHP-based themes (not block/FSE themes)

#### Required theme constants

- `define( 'DETAILED_REVIEWS_CATEGORIES', [ ... ] )` — globally sets the list of required rating categories, overrides any legacy database options which are no longer used

#### Required theme functions
- `ratings_input_table()` — displays the ratings form within the comment form
- `ratings_table()` — outputs the average ratings table for a post with progress bars
- `detailed_reviews_render_stars( $average )` — renders star icons based on average score

#### Optional theme hooks
- `add_filter( 'detailed_reviews_itemreviewed_type', ... )` — overrides schema.org item type (e.g. `CollegeOrUniversity` instead of `Thing`)

Credit to Dan Grossman and WP Review Site for the original functionality
