<?php

use Reels\Models\Reel;
use Reels\Services\ReelService;
use SkillDo\Cms\Element\Element;
use SkillDo\Cms\Support\Theme;

/**
 * Video ngắn — lưới / slider video lấy từ plugin Reels.
 *
 * Dữ liệu đi qua ReelService::feed() y hệt trang /video, nên element và trang
 * danh sách không bao giờ lệch thứ tự hay lệch số lượng vì hai bộ điều kiện
 * viết rời nhau.
 *
 * Trình xem tại chỗ là bản rút gọn nằm trong assets của element: reels.js của
 * plugin bám vào #reels-page và chỉ được nạp trên hai trang của plugin
 * (AssetsService::web), nên không dùng lại được ở trang chủ.
 */
class ReelsListElement extends Element
{
    public function __construct()
    {
        parent::__construct('ReelsListElement', trans('reels::admin.element.name'));

        $this->assets('assets/reels-list.css');

        $this->assets('assets/reels-list.js');

        $this->setTags('reels', 'video', 'tiktok', 'short');
    }

    public function icon(): string
    {
        return '<i class="fa-duotone fa-solid fa-photo-film"></i>';
    }

    public function category(): string
    {
        return 'general';
    }

    public function form(): void
    {
        $this->tabs('generate')->adds(function (\SkillDo\Cms\Form\Form $form)
        {
            $form->text('title', [
                'label'    => trans('reels::admin.element.title'),
                'language' => true,
            ]);

            $form->select('source', ['label' => trans('reels::admin.element.source')])->options([
                Reel::TAB_NEW  => trans('reels::web.tab.new'),
                Reel::TAB_VIEW => trans('reels::web.tab.view'),
                Reel::TAB_LIKE => trans('reels::web.tab.like'),
            ]);

            $form->number('limit', ['label' => trans('reels::admin.element.limit')]);

            $form->tab('layout', ['label' => trans('reels::admin.element.layout')])->options([
                'slider' => '<i class="fa-thin fa-arrows-left-right"></i>&nbsp;'.trans('reels::admin.element.layout_slider'),
                'grid'   => '<i class="fa-thin fa-grid-2"></i>&nbsp;'.trans('reels::admin.element.layout_grid'),
            ]);

            $form->addResponsive('numberShow', [
                'label' => trans('reels::admin.element.number_show'),
                'type'  => \SkillDo\Cms\Form\Field\NumericSelector::class,
                'min'   => 1,
                'max'   => 8,
            ]);

            $form->switch('showName', ['label' => trans('reels::admin.element.show_name')])->display('inline');

            $form->switch('showViews', ['label' => trans('reels::admin.element.show_views')])->display('inline');

            $form->switch('showAttach', ['label' => trans('reels::admin.element.show_attach')])->display('inline');

            $form->switch('preview', [
                'label' => trans('reels::admin.element.preview'),
                'note'  => trans('reels::admin.element.preview_note'),
            ])->display('inline');

            $form->select('clickAction', ['label' => trans('reels::admin.element.click_action')])->options([
                'popup' => trans('reels::admin.element.click_popup'),
                'page'  => trans('reels::admin.element.click_page'),
            ]);

            $form->switch('showMore', ['label' => trans('reels::admin.element.show_more')])->display('inline');

            $form->text('moreText', [
                'label'    => trans('reels::admin.element.more_text'),
                'language' => true,
            ])->condition('showMore', [1]);
        });

        $this->tabs('style')->adds(function (\SkillDo\Cms\Form\Form $form)
        {
            $form->addGroup(function (\SkillDo\Cms\Form\Form $form)
            {
                $form->textBuilding('headingStyle')->popup(false);
            }, $this->groupFormBox(trans('reels::admin.element.style_heading'), 'reelsElHeadingStyle', true));

            $form->addGroup(function (\SkillDo\Cms\Form\Form $form)
            {
                $form->boxBuilding('itemStyle')->popup(false);
            }, $this->groupFormBox(trans('reels::admin.element.style_item'), 'reelsElItemStyle'));

            $form->addGroup(function (\SkillDo\Cms\Form\Form $form)
            {
                $form->textBuilding('nameStyle')->popup(false);
            }, $this->groupFormBox(trans('reels::admin.element.style_name'), 'reelsElNameStyle'));

            $form->addGroup(function (\SkillDo\Cms\Form\Form $form)
            {
                $form->buttonBuilding('buttonStyle')->popup(false);
            }, $this->groupFormBox(trans('reels::admin.element.style_button'), 'reelsElButtonStyle'));
        });

        parent::form();
    }

    public function widget(): void
    {
        $tab = ReelService::normalizeTab($this->options->source ?? Reel::TAB_NEW);

        // Chặn trên để một cấu hình lỡ tay (999) không kéo cả bảng ra trang chủ.
        $limit = max(1, min(60, (int) ($this->options->limit ?? 8)));

        $feed = ReelService::feed($tab, 1, $limit);

        if (noItems($feed['items']))
        {
            echo $this->empty();

            return;
        }

        Theme::view($this->getDir().'views/view', [
            'options' => $this->options,
            'reels'   => $feed['items'],
            'tab'     => $tab,
            // Payload chỉ cần cho trình xem tại chỗ; chế độ mở trang video mà
            // vẫn nhúng JSON là tốn băng thông vô ích.
            'payload' => (($this->options->clickAction ?? 'popup') === 'popup')
                ? ReelService::payload($feed['items'])
                : [],
        ]);
    }

    public function default(): void
    {
        $defaults = [
            'title'             => '',
            'source'            => Reel::TAB_NEW,
            'limit'             => 8,
            'layout'            => 'slider',
            'showName'          => 1,
            'showViews'         => 1,
            'showAttach'        => 1,
            'preview'           => 1,
            'clickAction'       => 'popup',
            'showMore'          => 1,
            'moreText'          => trans('reels::web.view_all'),
            'desktopNumberShow' => 5,
            'tabletNumberShow'  => 3,
            'mobileNumberShow'  => 2,
        ];

        foreach ($defaults as $key => $value)
        {
            $this->options->{$key} = $this->options->{$key} ?? $value;
        }

        $this->options->headingStyle = $this->options->headingStyle ?? [
            'typography' => [
                'fontSize'   => ['desktop' => '22'],
                'fontWeight' => '700',
            ],
            'color' => ['active' => 'color', 'color' => '#111111'],
        ];

        $this->options->itemStyle = $this->options->itemStyle ?? [
            'background' => [
                'active' => 'classic',
                'color'  => ['active' => 'color', 'color' => '#000000'],
            ],
            'border' => [
                'style'  => '',
                'radius' => ['top' => '12', 'right' => '12', 'bottom' => '12', 'left' => '12', 'linked' => '1'],
            ],
            'boxShadow' => [
                'color'    => 'rgba(0,0,0,0.12)',
                'x'        => '0',
                'y'        => '2',
                'blur'     => '10',
                'spread'   => '0',
                'position' => 'outline',
            ],
        ];

        $this->options->nameStyle = $this->options->nameStyle ?? [
            'typography' => [
                'fontSize'   => ['desktop' => '14'],
                'fontWeight' => '600',
            ],
            'color' => ['active' => 'color', 'color' => '#111111'],
        ];
    }

    public function cssBuilder(): string
    {
        $this->cssVariables('--reels-el-columns', (int) ($this->options->desktopNumberShow ?? 5));

        $this->cssVariables('--reels-el-columns-tablet', (int) ($this->options->tabletNumberShow ?? 3));

        $this->cssVariables('--reels-el-columns-mobile', (int) ($this->options->mobileNumberShow ?? 2));

        $this->cssSelector('.reels-el__heading', [
            'data'  => $this->options->headingStyle ?? [],
            'style' => 'text',
        ]);

        $this->cssSelector('.reels-el__media', [
            'data'  => $this->options->itemStyle ?? [],
            'style' => 'box',
        ]);

        $this->cssSelector([
            'normal' => '.reels-el__name',
            'hover'  => '.reels-el__card:hover .reels-el__name',
        ], [
            'data'  => $this->options->nameStyle ?? [],
            'style' => 'text',
        ]);

        $this->cssSelector('.reels-product__buy', [
            'data'  => $this->options->buttonStyle ?? [],
            'style' => 'button',
        ]);

        return $this->cssBuild();
    }
}
