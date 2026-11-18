<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'brand_name',
        'hero_title',
        'hero_subtitle',
        'hero_cta_text',
        'hero_cta_url',
        'hero_image_path',
        'hero_video_path',
        'favicon_path',
        'social_links',
        'chatbot_enabled',
    ];

    protected $casts = [
        'social_links' => 'array',
        'chatbot_enabled' => 'boolean',
    ];

    /**
     * Get the singleton settings record, creating default if absent.
     */
    public static function instance(): self
    {
        return self::firstOrCreate([], [
            'brand_name' => 'Portfolio',
            'hero_title' => 'Cinematic Portfolio',
            'hero_cta_text' => 'VIEW PROJECTS',
            'hero_cta_url' => '#selected-work',
            'chatbot_enabled' => true,
        ]);
    }

    /**
     * Validate whether a URL string uses safe schemes and formats.
     */
    public static function isSafeUrl(mixed $url, bool $allowAnchor = false): bool
    {
        if (!is_string($url) && $url !== null) {
            return false;
        }
        if ($url === null || trim($url) === '') {
            return false;
        }

        $url = trim($url);

        // Check for disallowed characters / dangerous pseudo-protocols
        if (preg_match('/^(javascript|vbscript|data):/i', $url)) {
            return false;
        }

        if ($allowAnchor && str_starts_with($url, '#')) {
            return (bool) preg_match('/^#[a-zA-Z0-9\-_]+$/', $url);
        }

        if (str_starts_with($url, '/')) {
            // Disallow protocol-relative URLs like '//evil.com'
            return !str_starts_with($url, '//');
        }

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['scheme'])) {
            return false;
        }

        return in_array(strtolower($parsed['scheme']), ['http', 'https'], true);
    }

    /**
     * Get the sanitized hero CTA URL with safe fallback.
     */
    public function safeHeroCtaUrl(): string
    {
        $url = $this->hero_cta_url;
        if (!empty($url) && self::isSafeUrl($url, true)) {
            return $url;
        }

        return '#selected-work';
    }

    /**
     * Determine video MIME type based on extension.
     */
    public static function videoMimeType(?string $path): string
    {
        if (empty($path)) {
            return 'video/mp4';
        }

        $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return match ($ext) {
            'webm' => 'video/webm',
            'ogg', 'ogv' => 'video/ogg',
            default => 'video/mp4',
        };
    }
}
