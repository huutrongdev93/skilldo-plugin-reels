<?php

return [

    'menu' => [
        'root'    => 'Video Reels',
        'list'    => 'Danh sách video',
        'add'     => 'Thêm video',
        'setting' => 'Cài đặt',
    ],

    'role' => [
        'group'   => 'Video Reels',
        'view'    => 'Xem danh sách video',
        'add'     => 'Thêm video',
        'edit'    => 'Sửa video',
        'delete'  => 'Xóa video',
        'setting' => 'Cấu hình Video Reels',
    ],

    'name' => 'Video',

    'form' => [
        'group' => [
            'info'    => 'Thông tin video',
            'video'   => 'Nguồn video',
            'attach'  => 'Nội dung đính kèm',
            'display' => 'Hiển thị',
            'seo'     => 'Đường dẫn',
        ],
        'slug'      => 'Đường dẫn tĩnh',
        'slug_note' => 'Bỏ trống sẽ tự sinh từ tiêu đề. Trùng đường dẫn sẽ tự thêm hậu tố -1, -2...',
        'title'       => 'Tiêu đề',
        'description' => 'Mô tả ngắn',
        'source'      => 'Nguồn video',
        'source_upload'  => 'Tải lên file MP4',
        'source_youtube' => 'Link YouTube',
        'video_file'  => 'File video (mp4)',
        'video_file_note' => 'Nên dùng video dọc tỉ lệ 9:16, dung lượng càng nhỏ càng tải nhanh.',
        'video_url'   => 'Đường dẫn YouTube',
        'video_url_note' => 'Dán link dạng https://www.youtube.com/watch?v=... hoặc https://youtu.be/...',
        'product'     => 'Sản phẩm',
        'product_note'=> 'Mỗi video gắn đúng một sản phẩm, hiển thị kèm nút Mua ngay.',

        'attach_type'    => 'Gắn nội dung',
        'attach_product' => 'Sản phẩm',
        'attach_custom'  => 'Tự nhập (bài viết, dịch vụ...)',
        'attach_none'    => 'Không gắn gì',
        'attach_no_shop' => 'Website chưa bật plugin bán hàng (sicommerce) nên chỉ gắn được nội dung tự nhập.',
        'attach_title'   => 'Tên hiển thị',
        'attach_url'     => 'Đường dẫn',
        'attach_url_note'=> 'Slug của bài viết/trang (vd: dich-vu-massage) hoặc link đầy đủ bắt đầu bằng https://',
        'attach_price'   => 'Giá / nhãn phụ',
        'attach_price_note' => 'Không bắt buộc. Nhập tự do: "Từ 500.000₫", "Liên hệ"...',
        'attach_button'  => 'Chữ trên nút',
        'attach_button_note' => 'Bỏ trống sẽ dùng "Xem chi tiết".',
        'attach_image'   => 'Ảnh đại diện',
        'image'       => 'Ảnh poster',
        'image_note'  => 'Bắt buộc khi tải file MP4. Với YouTube, bỏ trống sẽ tự lấy thumbnail.',
        'public'      => 'Trạng thái',
        'public_show' => 'Hiển thị',
        'public_hide' => 'Ẩn',
        'order'       => 'Thứ tự',
    ],

    'table' => [
        'title'   => 'Video',
        'attach'  => 'Đính kèm',
        'view'    => 'Lượt xem',
        'like'    => 'Lượt tim',
        'source'  => 'Nguồn',
        'created' => 'Ngày tạo',
        'search'  => 'Tìm theo tiêu đề...',
        'all_source' => 'Tất cả nguồn',
        'all_status' => 'Tất cả trạng thái',
    ],

    'delete' => [
        'heading'     => 'Xóa video',
        'description' => 'Bạn chắc chắn muốn xóa video <b>:title</b>?',
        'empty'       => 'Không có dữ liệu để xóa',
    ],

    'validate' => [
        'video_file' => 'Vui lòng chọn file video MP4.',
        'video_url'  => 'Đường dẫn YouTube không hợp lệ.',
        'image'      => 'Vui lòng chọn ảnh poster cho video tải lên.',
        'attach_title' => 'Vui lòng nhập tên hiển thị cho nội dung đính kèm.',
        'attach_url'   => 'Vui lòng nhập đường dẫn cho nội dung đính kèm.',
    ],

    'setting' => [
        'heading'     => 'Video Reels',
        'description' => 'Cấu hình hiển thị cho khu vực video ngắn.',
        'per_page'    => 'Số video mỗi trang',
        'social_note' => 'Nút Zalo và Messenger trong trình xem video lấy từ '
            . '<b>Hệ Thống → Liên hệ → Mạng xã hội</b> (mục <i>Zalo</i> và '
            . '<i>Facebook messenger Id</i>). Để trống mục nào thì nút tương ứng tự ẩn.',
        'saved'       => 'Đã lưu cài đặt',
    ],
];
