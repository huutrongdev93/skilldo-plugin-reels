<?php

use Reels\Modules\Admin\Reel\Form;
use Reels\Modules\Admin\Setting;
use Reels\Services\AssetsService;
use Reels\Services\RoleService;
use Reels\Services\SeoService;
use SkillDo\Cms\Menu\AdminMenu;

/*
|--------------------------------------------------------------------------
| Admin Navigation
|--------------------------------------------------------------------------
*/
add_action('admin_navigation', function ()
{
    AdminMenu::add('reels', trans('reels::admin.menu.root'), 'reels', [
        'icon'     => '<i class="fad fa-photo-video"></i>',
        'role'     => 'reels_view',
        'position' => 26,
    ]);

    AdminMenu::addSub('reels', 'reels-list', trans('reels::admin.menu.list'), 'reels', [
        'role' => 'reels_view',
    ]);

    AdminMenu::addSub('reels', 'reels-add', trans('reels::admin.menu.add'), 'reels/add', [
        'role' => ['reels_view', 'reels_edit'],
    ]);

    AdminMenu::addSub('reels', 'reels-setting', trans('reels::admin.menu.setting'), 'system/reels', [
        'role' => 'reels_setting',
    ]);
});

/*
|--------------------------------------------------------------------------
| Form Admin — fields, buttons và xử lý dữ liệu module `reels`
|--------------------------------------------------------------------------
*/
add_filter('manage_reels_input', [Form::class, 'fields']);
add_filter('manage_reels_input', [Form::class, 'buttons'], 20, 2);
add_filter('insert_data_reels_before_save', [Form::class, 'data'], 10, 3);
add_filter('check_save_reels_before', [Form::class, 'check'], 10, 4);

/*
|--------------------------------------------------------------------------
| Trang cấu hình — Hệ Thống > Video Reels (admin/system/reels)
|--------------------------------------------------------------------------
*/
add_filter('admin_system_tabs', [Setting::class, 'register'], 15);
add_action('admin_system_reels_save', [Setting::class, 'save']);

/*
|--------------------------------------------------------------------------
| Phân quyền
|--------------------------------------------------------------------------
*/
add_filter('user_role_editor_group', [RoleService::class, 'group']);
add_filter('user_role_editor_label', [RoleService::class, 'label']);

/*
|--------------------------------------------------------------------------
| Assets frontend — chỉ nạp trên trang video, không kéo theo toàn site
|--------------------------------------------------------------------------
*/
add_action('theme_custom_assets', [AssetsService::class, 'web'], 10, 2);

/*
|--------------------------------------------------------------------------
| SEO cho trang chi tiết video
|--------------------------------------------------------------------------
*/
SeoService::register();
