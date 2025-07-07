-- Create comment_likes table for comment like functionality
CREATE TABLE IF NOT EXISTS `caily_comment_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_comment_user` (`comment_id`, `user_id`),
  KEY `comment_id` (`comment_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
ALTER TABLE `caily_comment_likes` 
ADD INDEX `idx_comment_likes_comment_id` (`comment_id`),
ADD INDEX `idx_comment_likes_user_id` (`user_id`),
ADD INDEX `idx_comment_likes_created_at` (`created_at`); 