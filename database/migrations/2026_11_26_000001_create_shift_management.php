<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('timezone', 32);
            $table->boolean('active')->default(true);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
        });
        DB::table('positions')->insert([
            ['code' => 'KASIR', 'name' => 'Kasir'],
            ['code' => 'BAKER', 'name' => 'Baker'],
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 32)->unique();
            $table->string('name');
            $table->string('role', 16);
            $table->string('password');
            $table->boolean('must_change_password')->default(true);
            $table->unsignedInteger('session_version')->default(1);
            $table->dateTime('disabled_at')->nullable();
            $table->foreignId('outlet_id')->nullable()->constrained();
            $table->foreignId('position_id')->nullable()->constrained();
            $table->date('joined_on')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('supervisor_assignments', function (Blueprint $table) {
            $table->foreignId('outlet_id')->primary()->constrained();
            $table->foreignId('user_id')->unique()->constrained();
        });

        Schema::create('schedule_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained();
            $table->date('monday');
            $table->unsignedInteger('version')->default(1);
            $table->unique(['outlet_id', 'monday']);
        });

        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_week_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('position_id')->constrained();
            $table->date('work_date');
            $table->string('shift', 8);
            $table->unsignedInteger('version')->default(1);
            $table->unique(['user_id', 'work_date']);
        });

        Schema::create('swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('partner_id')->constrained('users');
            $table->foreignId('position_id')->constrained();
            $table->string('status', 24);
            $table->text('reason');
            $table->dateTime('deadline');
            $table->index(['outlet_id', 'status', 'deadline']);
            $table->timestamps();
        });

        Schema::create('swap_days', function (Blueprint $table) {
            $table->foreignId('swap_id')->constrained();
            $table->foreignId('schedule_day_id')->constrained();
            $table->string('before', 8);
            $table->string('after', 8);
            $table->unsignedInteger('base_version');
            $table->primary(['swap_id', 'schedule_day_id']);
        });

        Schema::create('cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('swap_id')->constrained();
            $table->foreignId('requester_id')->constrained('users');
            $table->string('status', 24);
            $table->text('reason');
            $table->timestamps();
        });

        Schema::create('schedule_reservations', function (Blueprint $table) {
            $table->foreignId('schedule_day_id')->primary()->constrained();
            $table->foreignId('swap_id')->constrained();
        });

        Schema::create('schedule_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('actor_id')->constrained('users');
            $table->foreignId('swap_id')->nullable()->constrained();
            $table->string('kind', 24);
            $table->text('reason');
            $table->json('details');
            $table->timestamp('created_at');
        });

        Schema::create('acknowledgements', function (Blueprint $table) {
            $table->foreignId('schedule_change_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('confirmed_at')->nullable();
            $table->primary(['schedule_change_id', 'user_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('message');
            $table->string('target');
            $table->timestamp('created_at');
            $table->timestamp('read_at')->nullable();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->foreignId('swap_id')->nullable()->constrained();
            $table->foreignId('cancellation_id')->nullable()->constrained();
            $table->string('event', 32);
            $table->text('reason')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'notifications', 'acknowledgements', 'schedule_changes', 'schedule_reservations', 'cancellations', 'swap_days', 'swaps', 'schedule_days', 'schedule_weeks', 'supervisor_assignments', 'users', 'positions', 'outlets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
