<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personils')) {
            return;
        }

        $shouldAddUnique = ! Schema::hasColumn('personils', 'password')
            && ! Schema::hasColumn('personils', 'role')
            && ! Schema::hasColumn('personils', 'remember_token');

        Schema::table('personils', function (Blueprint $table) use ($shouldAddUnique) {
            if ($shouldAddUnique) {
                $table->unique('nip');
            }

            if (! Schema::hasColumn('personils', 'password')) {
                $table->string('password')->nullable()->after('no_wa');
            }

            if (! Schema::hasColumn('personils', 'role')) {
                $table->string('role')->default(UserRole::Pengguna->value)->after('password');
            }

            if (! Schema::hasColumn('personils', 'remember_token')) {
                $table->rememberToken();
            }
        });

        DB::table('personils')
            ->whereNotNull('no_wa')
            ->orderBy('id')
            ->chunkById(100, function ($personils) {
                foreach ($personils as $personil) {
                    $password = $personil->no_wa ? Hash::make($personil->no_wa) : null;

                    DB::table('personils')
                        ->where('id', $personil->id)
                        ->update([
                            'password' => $password,
                            'role' => UserRole::Pengguna->value,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('personils', function (Blueprint $table) {
            $table->dropUnique(['nip']);
            $table->dropColumn(['password', 'role', 'remember_token']);
        });
    }
};
