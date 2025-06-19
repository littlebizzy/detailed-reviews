# Detailed Reviews

## Changelog

### 1.0.0
- initial release
- supports Git Updater
- supports PHP 7.0 to 8.3
- forked from WP Review Site by Dan Grossman (retains some function names)
- no settings panel or UI, made purely for theme integration
- compatible with legacy WP Review Site data using the original `wp_ratings` table linked by comment ID
- supports 5-star reviews with multiple customizable rating categories per post
- includes frontend output functions for ratings input, and output using stars and/or progress bars
- outputs schema.org Review microdata per comment with individual star ratings
- outputs schema.org AggregateRating markup using average score and review count
- requires custom or child theme (e.g. comments template, single template, and functions.php)
- designed for classic PHP-based themes (not block/FSE themes)
