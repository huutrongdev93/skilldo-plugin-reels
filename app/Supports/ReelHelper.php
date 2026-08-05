<?php

namespace Reels\Supports;

use SkillDo\Cms\Support\Option;
use SkillDo\Cms\Support\Url;
use Illuminate\Support\Str;

class ReelHelper
{
    public const COOKIE = 'reels_vid';

    protected static ?string $visitorKey = null;

    /**
     * Khoá định danh khách vãng lai, dùng để một người chỉ tính một lượt
     * xem / một lượt tim cho mỗi video.
     *
     * Cố ý KHÔNG dùng IP: khách cùng một mạng văn phòng hoặc cùng nhà mạng
     * di động sẽ dùng chung IP và chặn nhầm nhau. Cookie sai lệch khi khách
     * xoá cookie, nhưng sai theo hướng đếm thừa — chấp nhận được hơn.
     */
    public static function visitorKey(): string
    {
        if (static::$visitorKey !== null)
        {
            return static::$visitorKey;
        }

        $raw = (string) (request()->cookie(static::COOKIE) ?? ($_COOKIE[static::COOKIE] ?? ''));

        if (!preg_match('/^[a-f0-9]{32}$/', $raw))
        {
            $raw = bin2hex(random_bytes(16));

            if (!headers_sent())
            {
                setcookie(static::COOKIE, $raw, [
                    'expires'  => time() + 31536000,
                    'path'     => '/',
                    'secure'   => request()->getScheme() === 'https',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            $_COOKIE[static::COOKIE] = $raw;
        }

        return static::$visitorKey = sha1($raw);
    }

    public static function perPage(): int
    {
        $perPage = (int) Option::get('reels_per_page', config('reels::config.per_page', 12));

        return ($perPage >= 4 && $perPage <= 60) ? $perPage : 12;
    }

    public static function feedUrl(?string $tab = null): string
    {
        $url = Url::base(config('reels::config.slug', 'video'));

        return empty($tab) ? $url : $url . '?tab=' . $tab;
    }

    /*
    |--------------------------------------------------------------------------
    | Nút liên hệ
    |--------------------------------------------------------------------------
    | Dùng chung cấu hình Hệ Thống > Liên hệ > Mạng xã hội thay vì bắt nhập lại
    | riêng cho reels. Đọc thẳng Option chứ không gọi get_theme_social() — hàm
    | đó thuộc theme, plugin không được phụ thuộc vào theme đang bật.
    | Option rỗng thì nút tự ẩn.
    */

    public static function zaloLink(): string
    {
        return static::contactLink((string) Option::get('social_zalo', ''), 'https://zalo.me/');
    }

    public static function facebookLink(): string
    {
        return static::contactLink((string) Option::get('social_messenger_id', ''), 'https://m.me/');
    }

    /**
     * Admin có thể nhập cả link đầy đủ lẫn mỗi id — chuẩn hoá về một dạng.
     */
    protected static function contactLink(string $value, string $prefix): string
    {
        $value = trim($value);

        if ($value === '') return '';

        if (Str::startsWith($value, ['http://', 'https://'])) return $value;

        if (Str::startsWith($value, ['zalo.me/', 'm.me/', 'www.'])) return 'https://' . $value;

        return $prefix . ltrim($value, '/');
    }

    /**
     * 1234 → 1.2K. Cột nút bên phải rất hẹp nên không hiển thị số thô.
     */
    public static function shortNumber($number): string
    {
        $number = (int) $number;

        if ($number < 1000)
        {
            return (string) $number;
        }

        if ($number < 1000000)
        {
            return static::trimZero($number / 1000) . 'K';
        }

        return static::trimZero($number / 1000000) . 'M';
    }

    protected static function trimZero(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
