<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kegiatans')) {
            return;
        }

        Schema::table('kegiatans', function (Blueprint $table) {
            if (! Schema::hasColumn('kegiatans', 'sifat_surat')) {
                $table->string('sifat_surat')
                    ->nullable()
                    ->after('surat_undangan');
            }

            if (! Schema::hasColumn('kegiatans', 'perlu_tindak_lanjut')) {
                $table->boolean('perlu_tindak_lanjut')
                    ->default(false)
                    ->after('sifat_surat');
            }
        });

        $kegiatans = DB::table('kegiatans')->select(
            'id',
            'jenis_surat',
            'surat_undangan',
            'waktu',
            'tempat',
            'batas_tindak_lanjut',
            'tindak_lanjut_deadline'
        )->get();

        foreach ($kegiatans as $kegiatan) {
            $jenis = $kegiatan->jenis_surat ?? null;

            if (in_array($jenis, ['undangan', 'undangan_tindak_lanjut'], true)) {
                $sifat = 'undangan';
            } elseif ($kegiatan->surat_undangan || $kegiatan->waktu || $kegiatan->tempat) {
                $sifat = 'undangan';
            } else {
                $sifat = 'lainnya';
            }

            $perluTl = in_array($jenis, ['tindak_lanjut', 'undangan_tindak_lanjut'], true);

            if (! $perluTl && ($kegiatan->batas_tindak_lanjut || $kegiatan->tindak_lanjut_deadline)) {
                $perluTl = true;
            }

            DB::table('kegiatans')
                ->where('id', $kegiatan->id)
                ->update([
                    'sifat_surat' => $sifat,
                    'perlu_tindak_lanjut' => $perluTl,
                ]);
        }

        Schema::table('kegiatans', function (Blueprint $table) {
            if (Schema::hasColumn('kegiatans', 'jenis_surat')) {
                $table->dropColumn('jenis_surat');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kegiatans')) {
            return;
        }

        Schema::table('kegiatans', function (Blueprint $table) {
            if (! Schema::hasColumn('kegiatans', 'jenis_surat')) {
                $table->string('jenis_surat')
                    ->default('undangan')
                    ->after('surat_undangan');
            }
        });

        $kegiatans = DB::table('kegiatans')->select(
            'id',
            'sifat_surat',
            'perlu_tindak_lanjut'
        )->get();

        foreach ($kegiatans as $kegiatan) {
            $sifat = $kegiatan->sifat_surat ?? null;
            $perluTl = (bool) ($kegiatan->perlu_tindak_lanjut ?? false);

            if ($sifat === 'undangan') {
                $jenis = $perluTl ? 'undangan_tindak_lanjut' : 'undangan';
            } else {
                $jenis = $perluTl ? 'tindak_lanjut' : 'undangan';
            }

            DB::table('kegiatans')
                ->where('id', $kegiatan->id)
                ->update(['jenis_surat' => $jenis]);
        }

        Schema::table('kegiatans', function (Blueprint $table) {
            $columns = ['sifat_surat', 'perlu_tindak_lanjut'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('kegiatans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
