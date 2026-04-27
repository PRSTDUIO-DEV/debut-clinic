<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('cashier_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'promotion_id')) {
                $table->foreignId('promotion_id')->nullable()->after('coupon_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'referral_id')) {
                $table->foreignId('referral_id')->nullable()->after('promotion_id')->constrained('influencer_referrals')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'coupon_discount')) {
                $table->decimal('coupon_discount', 12, 2)->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('invoices', 'promotion_discount')) {
                $table->decimal('promotion_discount', 12, 2)->default(0)->after('coupon_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach (['coupon_id', 'promotion_id', 'referral_id'] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $table->dropConstrainedForeignId($col);
                }
            }
            foreach (['coupon_discount', 'promotion_discount'] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
