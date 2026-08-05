<?php

namespace Reels\Modules\Admin;

use Admin\Supports\Component;
use Admin\Supports\Components\BlockSystem;
use SkillDo\Cms\Support\Option;
use SkillDo\Http\Request;

/**
 * Cấu hình plugin nằm trong trang Hệ Thống (admin/system/reels) thay vì tự
 * dựng trang riêng — cơ chế admin_system_tabs đã lo sẵn form, nút lưu và ajax.
 */
class Setting
{
    /**
     * @hook admin_system_tabs
     */
    public static function register(array $tabs): array
    {
        $tabs['reels'] = [
            'group'       => 'common',
            'label'       => trans('reels::admin.setting.heading'),
            'description' => trans('reels::admin.setting.description'),
            'callback'    => [static::class, 'render'],
            'icon'        => '<i class="fad fa-photo-video"></i>',
            'position'    => 30,
        ];

        return $tabs;
    }

    public static function render(): void
    {
        $form = form();

        $form->number('reels_per_page', [
            'label' => trans('reels::admin.setting.per_page'),
            'min'   => 4,
            'max'   => 60,
        ], Option::get('reels_per_page', 12));

        // Nút Zalo / Messenger trong viewer đọc từ Hệ Thống > Liên hệ >
        // Mạng xã hội, không khai báo lại ở đây.
        $form->none('<p class="text-muted mb-0">' . trans('reels::admin.setting.social_note') . '</p>');

        echo Component::blockSystem(function (BlockSystem $block) use ($form) {
            $block->header(trans('reels::admin.setting.heading'))
                  ->description(trans('reels::admin.setting.description'));

            $block->content($form);
        });
    }

    /**
     * @hook admin_system_reels_save
     */
    public static function save(Request $request): void
    {
        $perPage = (int) $request->input('reels_per_page');

        Option::update('reels_per_page', ($perPage >= 4 && $perPage <= 60) ? $perPage : 12);
    }
}
