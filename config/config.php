<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Đường dẫn trang danh sách video
    |--------------------------------------------------------------------------
    | Đọc ở thời điểm đăng ký route nên phải nằm trong config chứ không phải
    | Option — bảng `system` chưa chắc sẵn sàng khi route được nạp.
    | Đổi giá trị này thì trang chuyển thành /{slug} và /{slug}/{video-slug}.
    */
    'slug' => 'video',

    /*
    |--------------------------------------------------------------------------
    | Số video mỗi trang (lưới + mỗi lần load thêm trong viewer)
    |--------------------------------------------------------------------------
    | Có thể ghi đè trong Admin > Video Reels > Cài đặt (option `reels_per_page`).
    */
    'per_page' => 12,

    /*
    |--------------------------------------------------------------------------
    | Số slide giữ thẻ <video> ở hai phía slide đang xem
    |--------------------------------------------------------------------------
    | Slide ngoài phạm vi này chỉ hiển thị poster để không giữ quá nhiều
    | video decoder cùng lúc trên mobile.
    */
    'preload' => 2,
];
