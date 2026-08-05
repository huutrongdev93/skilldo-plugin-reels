<?php

namespace Reels\Services;

use Reels\Models\Reel;
use SkillDo\Cms\Support\Cms;
use SkillDo\Cms\Support\Theme;

/**
 * Trang /{feed}/{slug} là một URL chia sẻ được, nên phải mang đúng tiêu đề,
 * mô tả và ảnh của video thay vì dùng chung meta của trang danh sách.
 *
 * Ba filter dưới đây do plugin skd-seo cung cấp — không bật plugin đó thì
 * các add_filter này chỉ nằm im, không gây lỗi.
 */
class SeoService
{
    public static function register(): void
    {
        add_filter('seo_title', [static::class, 'title']);
        add_filter('seo_description', [static::class, 'description']);
        add_filter('seo_image', [static::class, 'image']);
    }

    public static function title($title)
    {
        $reel = static::reel();

        return $reel ? $reel->title : $title;
    }

    public static function description($description)
    {
        $reel = static::reel();

        if (!$reel) return $description;

        $excerpt = trim(strip_tags(html_entity_decode((string) $reel->excerpt)));

        return $excerpt !== '' ? $excerpt : $description;
    }

    /**
     * Trả về đường dẫn thô — HeadService tự chạy Image::source()->link().
     */
    public static function image($image)
    {
        $reel = static::reel();

        return ($reel && !empty($reel->image)) ? $reel->image : $image;
    }

    protected static function reel(): ?Reel
    {
        if (!Theme::isPage('reels_web_detail')) return null;

        $reel = Cms::getData('reel');

        return ($reel instanceof Reel) ? $reel : null;
    }
}
