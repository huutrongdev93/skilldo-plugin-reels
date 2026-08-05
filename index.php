<?php

use SkillDo\Support\Path;

class Reels
{
    private string $name = 'reels';

    public function active(): void
    {
        include_once Path::plugin('reels/app/Services/RoleService.php');
        include_once Path::plugin('reels/app/Services/ActivatorService.php');

        \Reels\Services\ActivatorService::activate();
    }

    /**
     * Chạy mỗi lần bật lại plugin đã cài — active() chỉ chạy một lần trong đời
     * nên migration của bản cập nhật sau phải đi qua đây mới tới được site đang chạy.
     */
    public function restart(): void
    {
        $this->active();
    }

    public function uninstall(): void
    {
        include_once Path::plugin('reels/app/Services/DeactivatorService.php');

        \Reels\Services\DeactivatorService::uninstall();
    }
}
