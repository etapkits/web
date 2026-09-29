<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('official_code', 16)->unique();
            $table->string('enrollment_key', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('boards', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('lock_settings', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 16)->unique();
            $table->timestamp('approved_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('teacher_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'consumed_at']);
        });

        $this->assignExistingToFirstOrganization();
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_otps');
        Schema::dropIfExists('teachers');

        Schema::table('lock_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('boards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::dropIfExists('organizations');
        Schema::dropIfExists('super_admins');
    }

    private function assignExistingToFirstOrganization(): void
    {
        $needsOrg = DB::table('users')->whereNull('organization_id')->exists()
            || DB::table('boards')->whereNull('organization_id')->exists()
            || DB::table('lock_settings')->whereNull('organization_id')->exists();

        if (! $needsOrg) {
            return;
        }

        $key = (string) config('etakit.enrollment_key');

        if ($key === '') {
            $key = bin2hex(random_bytes(24));
        }

        $orgId = DB::table('organizations')->where('enrollment_key', $key)->value('id');

        if (! $orgId) {
            $orgId = DB::table('organizations')->insertGetId([
                'name' => (string) config('etakit.first_org_name', 'İlk kurum'),
                'official_code' => (string) config('etakit.first_org_code', '00000000'),
                'enrollment_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')->whereNull('organization_id')->update(['organization_id' => $orgId]);
        DB::table('boards')->whereNull('organization_id')->update(['organization_id' => $orgId]);
        DB::table('lock_settings')->whereNull('organization_id')->update(['organization_id' => $orgId]);
    }
};
