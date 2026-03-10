<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAdminPermissionsAndFileBlockingToUsers extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('admin_permissions')->nullable()->after('root_admin');
            $table->unsignedTinyInteger('admin_file_blocking_enabled')->default(0)->after('admin_permissions');
            $table->text('admin_file_blocking_terms')->nullable()->after('admin_file_blocking_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['admin_permissions', 'admin_file_blocking_enabled', 'admin_file_blocking_terms']);
        });
    }
}
