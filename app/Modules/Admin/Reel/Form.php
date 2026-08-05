<?php

namespace Reels\Modules\Admin\Reel;

use Reels\Models\Reel;
use SkillDo\Cms\FormAdmin\FormAdmin;
use SkillDo\Cms\Support\Admin;
use SkillDo\Cms\Support\Image;
use SkillDo\Cms\Support\Language;
use SkillDo\Cms\Support\Url;
use SkillDo\Http\Request;
use SkillDo\Validate\Rule;
use Illuminate\Support\Str;

class Form
{
    public static function fields(FormAdmin $form): FormAdmin
    {
        $form->empty()->setModel(Reel::class)->setHeadingShow(true);

        $form->lang()
            ->addGroup('info', trans('reels::admin.form.group.info'))
            ->text('title', [
                'label'       => trans('reels::admin.form.title'),
                'validations' => [
                    Language::default() => Rule::make(trans('reels::admin.form.title'))
                        ->notEmpty()
                        ->string()
                        ->between(2, 255),
                ],
            ])
            ->textarea('excerpt', ['label' => trans('reels::admin.form.description')]);

        // ---------- Cột trái: nguồn video ----------
        // Hai field video dùng chung một select `source`; field không thuộc
        // nguồn đang chọn bị ẩn bằng data-condition nên không cần JS riêng.
        $form->leftBottom()
            ->addGroup('video', trans('reels::admin.form.group.video'))
            ->select2('source', static::sourceOptions(), [
                'label'       => trans('reels::admin.form.source'),
                'validations' => Rule::make(trans('reels::admin.form.source'))
                    ->notEmpty()
                    ->in(array_keys(static::sourceOptions())),
            ])
            ->video('video_file', [
                'label'     => trans('reels::admin.form.video_file'),
                'note'      => trans('reels::admin.form.video_file_note'),
                'condition' => ['name' => 'source', 'value' => [Reel::SOURCE_UPLOAD]],
            ])
            ->text('video_url', [
                'label'       => trans('reels::admin.form.video_url'),
                'note'        => trans('reels::admin.form.video_url_note'),
                'placeholder' => 'https://www.youtube.com/watch?v=...',
                'condition'   => ['name' => 'source', 'value' => [Reel::SOURCE_YOUTUBE]],
            ]);

        // ---------- Cột trái: nội dung đính kèm ----------
        $attach = $form->leftBottom()->addGroup('attach', trans('reels::admin.form.group.attach'), 20);

        $attach->select2('attach_type', Reel::attachTypeOptions(), [
            'label' => trans('reels::admin.form.attach_type'),
            'note'  => Reel::hasProductPicker() ? null : trans('reels::admin.form.attach_no_shop'),
        ]);

        // Popover 'products' do sicommerce đăng ký. Sicommerce tắt mà vẫn gọi
        // popoverAdvance thì PopoverAdvance::output() `new $classes()` trên một
        // class không tồn tại → fatal, hỏng luôn cả trang thêm video.
        if (Reel::hasProductPicker())
        {
            $attach->popoverAdvance('product_id', [
                'label'     => trans('reels::admin.form.product'),
                'note'      => trans('reels::admin.form.product_note'),
                'search'    => 'products',
                'multiple'  => false,
                'condition' => ['name' => 'attach_type', 'value' => [Reel::ATTACH_PRODUCT]],
            ]);
        }

        $custom = ['name' => 'attach_type', 'value' => [Reel::ATTACH_CUSTOM]];

        $attach
            ->text('attach_title', [
                'label'     => trans('reels::admin.form.attach_title'),
                'condition' => $custom,
            ])
            ->text('attach_url', [
                'label'       => trans('reels::admin.form.attach_url'),
                'note'        => trans('reels::admin.form.attach_url_note'),
                'placeholder' => 'dich-vu-massage-da-nang',
                'condition'   => $custom,
            ])
            ->text('attach_price', [
                'label'       => trans('reels::admin.form.attach_price'),
                'note'        => trans('reels::admin.form.attach_price_note'),
                'placeholder' => 'Từ 500.000₫',
                'start'       => 6,
                'condition'   => $custom,
            ])
            ->text('attach_button', [
                'label'       => trans('reels::admin.form.attach_button'),
                'note'        => trans('reels::admin.form.attach_button_note'),
                'placeholder' => trans('reels::web.view_detail'),
                'start'       => 6,
                'condition'   => $custom,
            ])
            ->image('attach_image', [
                'label'     => trans('reels::admin.form.attach_image'),
                'condition' => $custom,
            ]);

        // ---------- Cột phải ----------
        $form->right()
            ->addGroup('display', trans('reels::admin.form.group.display'))
            ->image('image', [
                'label' => trans('reels::admin.form.image'),
                'note'  => trans('reels::admin.form.image_note'),
            ])
            ->select2('public', [
                1 => trans('reels::admin.form.public_show'),
                0 => trans('reels::admin.form.public_hide'),
            ], ['label' => trans('reels::admin.form.public')])
            ->number('order', ['label' => trans('reels::admin.form.order')]);

        $form->right()
            ->addGroup('seo', trans('reels::admin.form.group.seo'), 20)
            ->text('slug', [
                'label' => trans('reels::admin.form.slug'),
                'note'  => trans('reels::admin.form.slug_note'),
            ]);

        return $form;
    }

    public static function buttons(FormAdmin $form, $object = null): FormAdmin
    {
        $buttons = [];

        $buttons['save'] = Admin::button('save');

        $buttons['back'] = Admin::button('white', [
            'href'          => Url::admin('reels'),
            'icon'          => Admin::icon('back'),
            'text'          => trans('button.back'),
            'class'         => 'btn-back-to-redirect',
            'data-redirect' => 'table_id_reels_admin',
        ]);

        $form->setButtons($buttons);

        return $form;
    }

    public static function sourceOptions(): array
    {
        return Reel::sourceOptions();
    }

    /*
    |--------------------------------------------------------------------------
    | Hook dữ liệu
    |--------------------------------------------------------------------------
    */

    /**
     * @hook insert_data_reels_before_save
     */
    public static function data($insertData, Request $request, $dataOutside = [])
    {
        $id = (int) $request->input('id');

        $source = ($insertData['source'] ?? Reel::SOURCE_UPLOAD) === Reel::SOURCE_YOUTUBE
            ? Reel::SOURCE_YOUTUBE
            : Reel::SOURCE_UPLOAD;

        $insertData['source'] = $source;

        // Dọn field của nguồn không được chọn, nếu không đổi từ YouTube sang
        // upload sẽ để lại link cũ và frontend nhúng nhầm iframe.
        if ($source === Reel::SOURCE_YOUTUBE)
        {
            $insertData['video_file'] = '';
        }
        else
        {
            $insertData['video_url'] = '';
        }

        // Video YouTube không cần poster thủ công — lấy luôn thumbnail chính chủ.
        if ($source === Reel::SOURCE_YOUTUBE && empty($insertData['image']) && !empty($insertData['video_url']))
        {
            $insertData['image'] = (string) Image::youtube($insertData['video_url'])->link();
        }

        $insertData = static::attachData($insertData);

        $insertData['slug'] = static::uniqueSlug($insertData['slug'] ?? '', $insertData['title'] ?? '', $id);

        return $insertData;
    }

    /**
     * Chuẩn hoá phần đính kèm và dọn dữ liệu của kiểu KHÔNG được chọn — đổi từ
     * sản phẩm sang item tự nhập mà còn sót product_id thì frontend hiển thị
     * nhầm sản phẩm cũ.
     */
    protected static function attachData(array $insertData): array
    {
        $type = $insertData['attach_type'] ?? Reel::ATTACH_PRODUCT;

        if (!array_key_exists($type, Reel::attachTypeOptions()))
        {
            $type = Reel::hasProductPicker() ? Reel::ATTACH_PRODUCT : Reel::ATTACH_CUSTOM;
        }

        $insertData['attach_type'] = $type;

        if ($type !== Reel::ATTACH_PRODUCT)
        {
            $insertData['product_id'] = 0;
        }
        else
        {
            $insertData['product_id'] = (int) ($insertData['product_id'] ?? 0);
        }

        if ($type !== Reel::ATTACH_CUSTOM)
        {
            foreach (['attach_title', 'attach_image', 'attach_price', 'attach_url', 'attach_button'] as $field)
            {
                $insertData[$field] = '';
            }
        }

        return $insertData;
    }

    /**
     * @hook check_save_reels_before — trả về giá trị "lỗi" (string|SKD_Error)
     *       thì core dừng lưu; trả về $check gốc thì đi tiếp.
     */
    public static function check($check, Request $request, $insertData = [], $dataOutside = [])
    {
        if ($check) return $check;

        $attachError = static::checkAttach($insertData);

        if ($attachError) return $attachError;

        $source = $insertData['source'] ?? Reel::SOURCE_UPLOAD;

        if ($source === Reel::SOURCE_YOUTUBE)
        {
            if (empty(Url::getYoutubeID($insertData['video_url'] ?? '')))
            {
                return trans('reels::admin.validate.video_url');
            }

            return $check;
        }

        if (empty($insertData['video_file']))
        {
            return trans('reels::admin.validate.video_file');
        }

        // Không có poster thì lưới chỉ còn ô đen — FileManager không sinh
        // thumbnail cho video nên ảnh này phải do người đăng chọn.
        if (empty($insertData['image']))
        {
            return trans('reels::admin.validate.image');
        }

        return $check;
    }

    /**
     * Item tự nhập chỉ có nghĩa khi có đủ tên và đường dẫn — thiếu một trong
     * hai thì khối đính kèm không hiển thị được, để lọt sẽ thành "lưu xong mà
     * ngoài web chẳng thấy gì".
     */
    protected static function checkAttach(array $insertData)
    {
        if (($insertData['attach_type'] ?? '') !== Reel::ATTACH_CUSTOM) return null;

        if (empty(trim((string) ($insertData['attach_title'] ?? ''))))
        {
            return trans('reels::admin.validate.attach_title');
        }

        if (empty(trim((string) ($insertData['attach_url'] ?? ''))))
        {
            return trans('reels::admin.validate.attach_url');
        }

        return null;
    }

    /**
     * Slug không được trùng vì route /{feed}/{slug} tra cứu theo nó và DB có
     * unique index — đụng trùng mà không xử lý ở đây thì lưu sẽ đổ lỗi SQL.
     */
    protected static function uniqueSlug(string $slug, string $title, int $id = 0): string
    {
        $slug = Str::slug(Str::clear($slug !== '' ? $slug : $title));

        if ($slug === '')
        {
            $slug = 'video';
        }

        $base = $slug;

        $i = 1;

        while (static::slugExists($slug, $id))
        {
            $slug = $base . '-' . $i;

            $i++;
        }

        return $slug;
    }

    protected static function slugExists(string $slug, int $id): bool
    {
        $query = Reel::where('slug', $slug);

        if (!empty($id))
        {
            $query->where('id', '<>', $id);
        }

        return $query->count() > 0;
    }
}
