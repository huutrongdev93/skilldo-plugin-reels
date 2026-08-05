<?php

namespace Reels\Services;

use SkillDo\Cms\Support\Theme;
use SkillDo\Cms\Template\Assets\AssetPosition;

class AssetsService
{
    /**
     * Viewer full màn hình khá nặng, chỉ nạp trên hai trang của plugin thay vì
     * kéo theo toàn site.
     */
    public static function web(AssetPosition $header, AssetPosition $footer): void
    {
        if (!Theme::isPage('reels_web_index') && !Theme::isPage('reels_web_detail'))
        {
            return;
        }

        $header->add('reels-css', asset('reels::css/reels.css'))->minify(false);

        $footer->add('reels-js', asset('reels::js/reels.js'))->minify(false);
    }
}
