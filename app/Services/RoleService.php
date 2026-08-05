<?php

namespace Reels\Services;

class RoleService
{
    /**
     * Nhóm quyền hiển thị trong trang phân quyền (plugin user-role-editor).
     */
    public static function group($group)
    {
        $group['reels'] = [
            'label'        => trans('reels::admin.role.group'),
            'capabilities' => array_keys(static::capabilities()),
        ];

        return $group;
    }

    public static function label($label): array
    {
        return array_merge($label, static::capabilities());
    }

    public static function capabilities(): array
    {
        $label = [];

        $label['reels_view']    = trans('reels::admin.role.view');
        $label['reels_add']     = trans('reels::admin.role.add');
        $label['reels_edit']    = trans('reels::admin.role.edit');
        $label['reels_delete']  = trans('reels::admin.role.delete');
        $label['reels_setting'] = trans('reels::admin.role.setting');

        return apply_filters('reels_capabilities', $label);
    }
}
