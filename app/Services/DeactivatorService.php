<?php

namespace Reels\Services;

use SkillDo\Cms\Support\Option;

class DeactivatorService
{
    public static function uninstall(): void
    {
        schema()->dropIfExists('reels_reactions');

        schema()->dropIfExists('reels');

        // social_zalo / social_messenger_id là của Hệ Thống, không phải của
        // plugin — gỡ reels không được đụng vào.
        foreach (['reels_per_page', 'reels_zalo_oa', 'reels_facebook_page'] as $name)
        {
            Option::delete($name);
        }
    }
}
