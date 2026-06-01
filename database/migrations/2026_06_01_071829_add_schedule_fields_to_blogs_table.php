<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('blogs', function (Blueprint $table) {
        $table->timestamp('scheduled_at')->nullable()->after('status');
        $table->timestamp('published_at')->nullable()->after('scheduled_at');
        $table->boolean('is_published')->default(false)->after('published_at');
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('blogs', function (Blueprint $table) {
        $table->dropColumn([
            'scheduled_at',
            'published_at',
            'is_published'
        ]);
    });
}
};
