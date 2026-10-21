<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->json('locales')->nullable()->after('color');
        });

        foreach (DB::table('channels')->get(['id', 'locale']) as $channel) {
            DB::table('channels')->where('id', $channel->id)->update([
                'locales' => json_encode([$channel->locale ?: 'es']),
            ]);
        }

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('video_tasks', function (Blueprint $table) {
            $table->string('locale', 5)->default('es');
        });

        $localesByChannel = DB::table('channels')->get(['id', 'locales'])
            ->mapWithKeys(fn ($channel) => [
                $channel->id => json_decode($channel->locales, true) ?: ['es'],
            ]);

        foreach (DB::table('video_tasks')->get(['id', 'channel_id']) as $task) {
            $channelLocales = $task->channel_id
                ? ($localesByChannel->get($task->channel_id) ?? ['es'])
                : ['es'];

            DB::table('video_tasks')->where('id', $task->id)->update([
                'locale' => $channelLocales[0] ?? 'es',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('locale', 5)->default('es')->after('color');
        });

        foreach (DB::table('channels')->get(['id', 'locales']) as $channel) {
            $locales = json_decode($channel->locales, true) ?: ['es'];

            DB::table('channels')->where('id', $channel->id)->update([
                'locale' => $locales[0] ?? 'es',
            ]);
        }

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('locales');
        });

        Schema::table('video_tasks', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
