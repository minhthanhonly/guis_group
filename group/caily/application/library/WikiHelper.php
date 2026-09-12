<?php
require_once dirname(__FILE__) . '/connectionmysql.php';

class WikiHelper {

    /**
     * Generate a URL-safe slug from a title.
     * Converts to lowercase, replaces non-alphanumeric with hyphens,
     * collapses consecutive hyphens, and trims.
     */
    public static function generateSlug($title) {
        $slug = strtolower(trim((string)$title));
        $slug = preg_replace('/[^a-z0-9_\s-]/u', '', $slug);
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'untitled';
    }

    /**
     * Ensure slug is unique, appending a numeric suffix if needed.
     * $model must have $table set and connect() available.
     */
    public static function resolveUniqueSlug($model, $baseSlug, $excludeId = 0) {
        $baseSlug = self::generateSlug($baseSlug);
        $excludeId = intval($excludeId);
        $slug = $baseSlug;
        $counter = 1;
        $table = $model->table;
        while (true) {
            $where = $excludeId > 0
                ? "WHERE slug = '" . $model->quote($slug) . "' AND id <> " . $excludeId
                : "WHERE slug = '" . $model->quote($slug) . "'";
            $count = $model->fetchCount($table, $where, 'id');
            if ($count == 0) {
                break;
            }
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        return $slug;
    }

    /**
     * Check if HTML content has non-whitespace text.
     */
    public static function contentHasText($content) {
        $text = trim(strip_tags((string)$content));
        $text = str_replace("\xc2\xa0", ' ', $text);
        return $text !== '';
    }
}
