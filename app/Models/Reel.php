<?php

namespace Reels\Models;

use SkillDo\Cms\Support\Image;
use SkillDo\Cms\Support\Url;
use SkillDo\Database\Eloquent\Builder;
use SkillDo\Database\Eloquent\Model;
use SkillDo\Traits\Eloquent\ModelLanguage;
use Illuminate\Support\Str;

class Reel extends Model
{
    use ModelLanguage;

    public const SOURCE_UPLOAD  = 'upload';
    public const SOURCE_YOUTUBE = 'youtube';

    /** Kiểu nội dung đính kèm dưới video */
    public const ATTACH_NONE    = 'none';
    public const ATTACH_PRODUCT = 'product';
    public const ATTACH_CUSTOM  = 'custom';

    public const TAB_NEW  = 'new';
    public const TAB_VIEW = 'view';
    public const TAB_LIKE = 'like';

    protected string $table = 'reels';

    protected array $columns = [
        'title'      => ['string'],
        'slug'       => ['slug'],
        'source'     => ['string', self::SOURCE_UPLOAD],
        'video_file' => ['file'],
        'video_url'  => ['string'],
        'image'      => ['image'],
        'excerpt'       => ['wysiwyg'],
        'attach_type'   => ['string', self::ATTACH_PRODUCT],
        'product_id'    => ['int', 0],
        'attach_title'  => ['string'],
        'attach_image'  => ['image'],
        'attach_price'  => ['string'],
        'attach_url'    => ['string'],
        'attach_button' => ['string'],
        'view_count' => ['int', 0],
        'like_count' => ['int', 0],
        'public'     => ['int', 1],
        'order'      => ['int', 0],
    ];

    /**
     * Sản phẩm đã nạp, cache theo vòng đời request để lưới 12 video không
     * bắn 12 query giống nhau khi nhiều video trỏ về cùng một sản phẩm.
     */
    protected static array $productCache = [];

    public function __construct($attributes = [])
    {
        $this->language = 'reels';

        parent::__construct($attributes);
    }

    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_UPLOAD  => trans('reels::admin.form.source_upload'),
            self::SOURCE_YOUTUBE => trans('reels::admin.form.source_youtube'),
        ];
    }

    /**
     * Bộ chọn sản phẩm chỉ dùng được khi sicommerce đăng ký popover 'products'
     * và class handler nạp được. Site không bán hàng vẫn gắn được item tự nhập.
     */
    public static function hasProductPicker(): bool
    {
        $aliases = app()->bound('cmsAliases') ? app('cmsAliases') : [];

        $class = $aliases['popover']['products'] ?? null;

        return !empty($class) && class_exists($class);
    }

    /**
     * Lựa chọn nội dung đính kèm. Xếp mục hợp lý nhất lên đầu vì form thêm mới
     * lấy option đầu tiên làm mặc định.
     */
    public static function attachTypeOptions(): array
    {
        $options = [];

        if (static::hasProductPicker())
        {
            $options[self::ATTACH_PRODUCT] = trans('reels::admin.form.attach_product');
        }

        $options[self::ATTACH_CUSTOM] = trans('reels::admin.form.attach_custom');
        $options[self::ATTACH_NONE]   = trans('reels::admin.form.attach_none');

        return $options;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('public', 1);
    }

    /**
     * Sắp xếp theo tab đang xem. Tab không hợp lệ rơi về "mới nhất".
     */
    public function scopeTab(Builder $query, ?string $tab): Builder
    {
        return match ($tab) {
            self::TAB_VIEW => $query->orderBy('view_count', 'desc')->orderBy('id', 'desc'),
            self::TAB_LIKE => $query->orderBy('like_count', 'desc')->orderBy('id', 'desc'),
            default        => $query->orderBy('order')->orderBy('created', 'desc')->orderBy('id', 'desc'),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Giá trị dẫn xuất
    |--------------------------------------------------------------------------
    | Cố tình là method chứ không phải accessor kiểu getXxxAttribute():
    | Model::__get của framework này chỉ tra $columns → relation → attributes,
    | KHÔNG gọi mutator. Đặt tên getXxx() để __get('xxx') không vô tình chạy
    | truy vấn rồi vẫn trả null.
    */

    public function isYoutube(): bool
    {
        return $this->source === self::SOURCE_YOUTUBE;
    }

    public function getYoutubeId(): string
    {
        if (!$this->isYoutube() || empty($this->video_url)) return '';

        return (string) Url::getYoutubeID($this->video_url);
    }

    /**
     * Nguồn phát: URL file mp4, hoặc ID YouTube khi nhúng iframe.
     *
     * Cột `video_file` được kiểu dữ liệu `file` cắt bỏ tiền tố thư mục upload
     * (macro File::clear), nên phải ghép lại config('media.source') mới ra
     * đường dẫn công khai.
     */
    public function getVideoSrc(): string
    {
        if ($this->isYoutube())
        {
            return $this->getYoutubeId();
        }

        $file = (string) $this->video_file;

        if ($file === '') return '';

        if (Str::isUrl($file)) return $file;

        return Url::asset(config('media.source') . ltrim($file, '/'));
    }

    /**
     * Ảnh nền của video. YouTube không cần poster thủ công vì có thumbnail sẵn;
     * video upload thì bắt buộc có ảnh (FileManager không tự sinh poster).
     *
     * Trả URL tuyệt đối vì poster còn được JS nhét vào background-image của
     * slide, nơi không chắc thẻ <base> của theme có tác dụng.
     */
    public function getPoster(): string
    {
        if (!empty($this->image))
        {
            return Url::asset((string) Image::medium($this->image)->link());
        }

        if ($this->isYoutube() && !empty($this->video_url))
        {
            return Url::asset((string) Image::youtube($this->video_url)->link());
        }

        return '';
    }

    public function getPermalink(): string
    {
        return Url::base(config('reels::config.slug', 'video') . '/' . $this->slug);
    }

    /**
     * Sản phẩm đính kèm. Trả null khi chưa gắn hoặc khi plugin sicommerce tắt.
     */
    public function getProduct()
    {
        $productId = (int) $this->product_id;

        if (empty($productId) || !class_exists(\Ecommerce\Models\Product::class))
        {
            return null;
        }

        if (!array_key_exists($productId, static::$productCache))
        {
            $product = \Ecommerce\Models\Product::whereKey($productId)
                ->select('id', 'title', 'slug', 'image', 'price', 'price_sale')
                ->first();

            static::$productCache[$productId] = hasItems($product) ? $product : null;
        }

        return static::$productCache[$productId];
    }

    /**
     * Nội dung đính kèm đã chuẩn hoá về MỘT dạng chung cho cả sản phẩm lẫn
     * item tự nhập, để view chỉ phải biết một cấu trúc.
     *
     * Trả null khi không gắn gì, hoặc khi gắn sản phẩm mà sicommerce đang tắt
     * / sản phẩm đã bị xoá.
     *
     * @return array{title:string,url:string,image:string,price:string,priceOld:string,button:string}|null
     */
    public function getAttachment(): ?array
    {
        $type = (string) $this->attach_type;

        // Bản ghi tạo từ 1.0.0 chưa có cột attach_type
        if ($type === '') $type = self::ATTACH_PRODUCT;

        if ($type === self::ATTACH_CUSTOM)
        {
            $title = trim((string) $this->attach_title);
            $url   = trim((string) $this->attach_url);

            if ($title === '' || $url === '') return null;

            return [
                'title'    => $title,
                'url'      => Url::permalink($url),
                'image'    => empty($this->attach_image) ? '' : (string) Image::thumb($this->attach_image)->link(),
                'price'    => trim((string) $this->attach_price),
                'priceOld' => '',
                'button'   => trim((string) $this->attach_button) ?: trans('reels::web.view_detail'),
            ];
        }

        if ($type !== self::ATTACH_PRODUCT) return null;

        $product = $this->getProduct();

        if (!hasItems($product)) return null;

        return [
            'title'    => (string) $product->title,
            'url'      => Url::permalink((string) $product->slug),
            'image'    => empty($product->image) ? '' : (string) Image::thumb($product->image)->link(),
            'price'    => static::price($product->price_sale ?: $product->price),
            'priceOld' => empty($product->price_sale) ? '' : static::price($product->price),
            'button'   => trans('reels::web.buy_now'),
        ];
    }

    /**
     * Định dạng giá theo đúng cấu hình tiền tệ của shop. Prd thuộc sicommerce
     * nên phải kiểm tra tồn tại — reels chạy được cả khi không có shop.
     */
    protected static function price($value): string
    {
        if (!class_exists(\Ecommerce\Supports\Prd::class)) return '';

        return empty($value)
            ? (string) \Ecommerce\Supports\Prd::priceNone()
            : (string) \Ecommerce\Supports\Prd::price($value);
    }
}
