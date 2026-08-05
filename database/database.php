<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class () extends Migration
{
    public function up(): void
    {
        if (!schema()->hasTable('reels'))
        {
            schema()->create('reels', function (Blueprint $table)
            {
                $table->increments('id');
                $table->string('title', 255)->collation('utf8mb4_unicode_ci');
                $table->string('slug', 255)->collation('utf8mb4_unicode_ci');
                $table->string('source', 20)->default('upload');
                $table->string('video_file', 500)->collation('utf8mb4_unicode_ci')->nullable();
                $table->string('video_url', 500)->collation('utf8mb4_unicode_ci')->nullable();
                $table->string('image', 500)->collation('utf8mb4_unicode_ci')->nullable();
                // Tên cột `excerpt` là bắt buộc chứ không phải tuỳ ý: trait
                // ModelLanguage chỉ đồng bộ được title/name/excerpt/content sang
                // bảng `language`, đặt tên khác thì mô tả mất khi đổi ngôn ngữ.
                $table->text('excerpt')->nullable();
                $table->string('attach_type', 20)->default('product');
                $table->integer('product_id')->default(0);
                $table->string('attach_title', 255)->collation('utf8mb4_unicode_ci')->nullable();
                $table->string('attach_image', 500)->collation('utf8mb4_unicode_ci')->nullable();
                $table->string('attach_price', 100)->collation('utf8mb4_unicode_ci')->nullable();
                $table->string('attach_url', 500)->collation('utf8mb4_unicode_ci')->nullable();
                $table->string('attach_button', 100)->collation('utf8mb4_unicode_ci')->nullable();
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedInteger('like_count')->default(0);
                $table->tinyInteger('public')->default(1);
                $table->integer('order')->default(0);
                $table->integer('user_created')->default(0);
                $table->integer('user_updated')->default(0);
                $table->dateTime('created')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->dateTime('updated')->nullable();

                $table->unique('slug');
                $table->index('product_id');
                $table->index('view_count');
                $table->index('like_count');
                $table->index('public');
                $table->index('created');
            });
        }

        // Nâng cấp bảng đã cài từ bản 1.0.0 (chỉ gắn được sản phẩm).
        // active() chạy lại được qua restart() nên đặt ở đây là đủ, không cần
        // hệ thống version migration riêng.
        $this->addMissingColumns('reels', [
            'attach_type'   => fn(Blueprint $t) => $t->string('attach_type', 20)->default('product'),
            'attach_title'  => fn(Blueprint $t) => $t->string('attach_title', 255)->collation('utf8mb4_unicode_ci')->nullable(),
            'attach_image'  => fn(Blueprint $t) => $t->string('attach_image', 500)->collation('utf8mb4_unicode_ci')->nullable(),
            'attach_price'  => fn(Blueprint $t) => $t->string('attach_price', 100)->collation('utf8mb4_unicode_ci')->nullable(),
            'attach_url'    => fn(Blueprint $t) => $t->string('attach_url', 500)->collation('utf8mb4_unicode_ci')->nullable(),
            'attach_button' => fn(Blueprint $t) => $t->string('attach_button', 100)->collation('utf8mb4_unicode_ci')->nullable(),
        ]);

        // Bảng dedupe lượt xem / lượt tim của khách vãng lai. view_count và
        // like_count trên bảng reels là counter denormalize để sort tab được
        // bằng index; bảng này chỉ để biết một khách đã tính rồi hay chưa.
        if (!schema()->hasTable('reels_reactions'))
        {
            schema()->create('reels_reactions', function (Blueprint $table)
            {
                $table->increments('id');
                $table->integer('reel_id')->default(0);
                $table->string('type', 10)->default('view');
                $table->string('visitor_key', 64);
                $table->dateTime('created')->default(DB::raw('CURRENT_TIMESTAMP'));

                $table->unique(['reel_id', 'type', 'visitor_key'], 'reels_reactions_unique');
                $table->index('reel_id');
            });
        }
    }

    /**
     * Thêm cột còn thiếu vào bảng đã tồn tại. Gom một lần ALTER cho tất cả cột
     * thiếu thay vì mỗi cột một lần.
     *
     * @param array<string, callable(Blueprint): mixed> $columns
     */
    protected function addMissingColumns(string $table, array $columns): void
    {
        if (!schema()->hasTable($table)) return;

        $missing = [];

        foreach ($columns as $name => $definition)
        {
            if (!schema()->hasColumn($table, $name)) $missing[$name] = $definition;
        }

        if (empty($missing)) return;

        schema()->table($table, function (Blueprint $blueprint) use ($missing)
        {
            foreach ($missing as $definition) $definition($blueprint);
        });
    }

    public function down(): void
    {
        schema()->dropIfExists('reels_reactions');
        schema()->dropIfExists('reels');
    }
};
