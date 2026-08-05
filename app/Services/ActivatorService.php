<?php

namespace Reels\Services;

use SkillDo\Cms\Support\Option;
use SkillDo\Cms\Support\Role;
use SkillDo\Support\Path;

/**
 * Phải idempotent: index.php gọi lại qua restart() mỗi lần bật plugin,
 * nên mọi bước ở đây đều kiểm tra trước khi ghi.
 */
class ActivatorService
{
    public static function activate(): void
    {
        $db = include Path::plugin('reels/database/database.php');

        $db->up();

        static::options();

        static::role();
    }

    /**
     * Option chỉ còn số video mỗi trang. Nút Zalo / Messenger dùng chung
     * social_zalo và social_messenger_id ở Hệ Thống > Liên hệ > Mạng xã hội.
     */
    public static function options(): void
    {
        $defaults = [
            'reels_per_page' => 12,
        ];

        foreach ($defaults as $name => $value)
        {
            // Option::get trả về default khi chưa tồn tại — dùng một sentinel để
            // phân biệt "chưa có" với "đã có nhưng đang để rỗng".
            if (Option::get($name, '__reels_missing__') === '__reels_missing__')
            {
                Option::add($name, $value);
            }
        }

        // Bản 1.0.0 từng tạo hai option riêng cho Zalo/Messenger. Xoá đi cho
        // site đã cài bản cũ khỏi ôm dữ liệu chết — activate() chạy lại được
        // qua restart() nên đây là chỗ đặt bước dọn.
        foreach (['reels_zalo_oa', 'reels_facebook_page'] as $legacy)
        {
            if (Option::get($legacy, '__reels_missing__') !== '__reels_missing__')
            {
                Option::delete($legacy);
            }
        }
    }

    public static function role(): void
    {
        $roles = ['root', 'administrator'];

        $permissions = array_keys(RoleService::capabilities());

        foreach ($roles as $roleKey)
        {
            $role = Role::get($roleKey);

            if (empty($role)) continue;

            foreach ($permissions as $permission)
            {
                $role->add($permission);
            }
        }
    }
}
