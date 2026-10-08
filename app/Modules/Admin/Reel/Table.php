<?php

namespace Reels\Modules\Admin\Reel;

use Reels\Models\Reel;
use SkillDo\Cms\Form\Form;
use SkillDo\Cms\Support\Admin;
use SkillDo\Cms\Support\Url;
use SkillDo\Cms\Table\Columns\ColumnBadge;
use SkillDo\Cms\Table\Columns\ColumnImage;
use SkillDo\Cms\Table\Columns\ColumnText;
use SkillDo\Cms\Table\Columns\ColumnView;
use SkillDo\Cms\Table\SKDObjectTable;
use SkillDo\Database\Eloquent\Builder;
use SkillDo\Http\Request;

class Table extends SKDObjectTable
{
    protected string $module = 'reels';

    protected mixed $model = Reel::class;

    public function getColumns(): array
    {
        $this->_column_headers = [];

        $this->_column_headers['cb'] = 'cb';

        $this->_column_headers['image'] = [
            'label'  => trans('table.image'),
            // getPoster(): ảnh đã chọn, không có thì thumbnail YouTube (L186). ColumnImage đưa link YouTube
            // tuyệt đối qua Image::medium nên ô trống.
            'column' => fn($item, $args) => ColumnView::make('image', $item, $args)->html(function () use ($item) {
                $poster = $item->getPoster();

                echo $poster === ''
                    ? \Image::medium('')->attributes(['style' => 'width:60px;max-width:60px;'])->html()
                    : '<img src="' . html_escape($poster) . '" alt="" loading="lazy" style="width:60px;max-width:60px;">';
            }),
        ];

        $this->_column_headers['title'] = [
            'label'  => trans('reels::admin.table.title'),
            'column' => fn($item, $args) => ColumnText::make('title', $item, $args)
                ->title()
                ->description(fn($item) => $item->source === Reel::SOURCE_YOUTUBE
                    ? trans('reels::admin.form.source_youtube')
                    : trans('reels::admin.form.source_upload')),
        ];

        $this->_column_headers['attach'] = [
            'label'  => trans('reels::admin.table.attach'),
            'column' => fn($item, $args) => ColumnView::make('attach', $item, $args)
                ->html(function () use ($item) {
                    $attachment = $item->getAttachment();

                    if (!hasItems($attachment)) { echo '—'; return; }

                    $badge = $item->attach_type === Reel::ATTACH_CUSTOM
                        ? trans('reels::admin.form.attach_custom')
                        : trans('reels::admin.form.attach_product');

                    echo html_escape($attachment['title'])
                        . '<br><small class="text-muted">' . html_escape($badge) . '</small>';
                }),
        ];

        $this->_column_headers['view_count'] = [
            'label'  => trans('reels::admin.table.view'),
            'column' => fn($item, $args) => ColumnText::make('view_count', $item, $args)->number(),
        ];

        $this->_column_headers['like_count'] = [
            'label'  => trans('reels::admin.table.like'),
            'column' => fn($item, $args) => ColumnText::make('like_count', $item, $args)->number(),
        ];

        $this->_column_headers['public'] = [
            'label'  => trans('reels::admin.form.public'),
            'column' => fn($item, $args) => ColumnBadge::make('public', $item, $args)
                ->color(fn($state): string => (int) $state === 1 ? 'success' : 'secondary')
                ->label(fn($state): string => (int) $state === 1
                    ? trans('reels::admin.form.public_show')
                    : trans('reels::admin.form.public_hide')),
        ];

        $this->_column_headers['created'] = [
            'label'  => trans('reels::admin.table.created'),
            'column' => fn($item, $args) => ColumnText::make('created', $item, $args)->datetime(),
        ];

        $this->_column_headers['action'] = trans('table.action');

        return apply_filters('manage_reels_columns', $this->_column_headers);
    }

    public function actionButton($item, $module, $table): array
    {
        $buttons = [];

        $buttons[] = Admin::button('blue', [
            'href'    => Url::admin('reels/edit/' . $item->id),
            'class'   => ['btn-sm'],
            'icon'    => Admin::icon('edit'),
            'tooltip' => trans('button.update'),
        ]);

        $buttons[] = Admin::btnConfirm('red', [
            'icon'        => Admin::icon('delete'),
            'class'       => ['btn-sm'],
            'action'      => 'delete',
            'ajax'        => 'Reels\Ajax\Admin\ReelAjax::delete',
            'model'       => $module,
            'heading'     => trans('reels::admin.delete.heading'),
            'description' => trans('reels::admin.delete.description', ['title' => html_escape($item->title)]),
            'trash'       => false,
            'id'          => $item->id,
        ]);

        return apply_filters('admin_reels_table_columns_action', $buttons, $item);
    }

    public function headerButton(): array
    {
        return [
            Admin::button('add', ['href' => Url::admin('reels/add')]),
            Admin::button('reload'),
        ];
    }

    public function headerSearch(Form $form, Request $request): Form
    {
        $form->text('keyword', ['placeholder' => trans('reels::admin.table.search')], $request->input('keyword'));

        $form->select2('source', $request->input('source'))
            ->options(['' => trans('reels::admin.table.all_source')] + Reel::sourceOptions());

        $form->select2('public', $request->input('public'))
            ->options([
                ''  => trans('reels::admin.table.all_status'),
                '1' => trans('reels::admin.form.public_show'),
                '0' => trans('reels::admin.form.public_hide'),
            ]);

        return $form;
    }

    public function queryFilter(Builder $query, Request $request): Builder
    {
        $keyword = $request->input('keyword');

        if (!empty($keyword))
        {
            $query->where('title', 'like', '%' . $keyword . '%');
        }

        $source = $request->input('source');

        if (!empty($source))
        {
            $query->where('source', $source);
        }

        $public = $request->input('public');

        if ($public !== null && $public !== '')
        {
            $query->where('public', (int) $public);
        }

        return $query;
    }

    public function queryDisplay(Builder $query, Request $request, $data = []): Builder
    {
        $query = parent::queryDisplay($query, $request, $data);

        return $query->orderBy('order')->orderBy('id', 'desc');
    }
}
