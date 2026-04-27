<?php

use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BirthdayCampaignController;
use App\Http\Controllers\Api\ClosingController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\ConsentController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CrmController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DevController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FollowUpController;
use App\Http\Controllers\Api\FollowUpRuleController;
use App\Http\Controllers\Api\InfluencerController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LabController;
use App\Http\Controllers\Api\LiffController;
use App\Http\Controllers\Api\LineWebhookController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MessagingProviderController;
use App\Http\Controllers\Api\MisController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OpdCardController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\QcController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RichMenuController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\SystemHealthController;
use App\Http\Controllers\Api\TimeClockController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\PublicMarketingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth endpoints (rate-limited by route throttle)
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('auth.login');

    // Dev quick-login (only available in local environment)
    Route::get('dev/quick-accounts', [DevController::class, 'quickAccounts'])->name('dev.quick-accounts');

    // LINE webhook (public, signature-verified inside; uses providerId not bound model to skip branch scope)
    Route::post('webhooks/line/{providerId}', [LineWebhookController::class, 'handle'])->name('webhooks.line');

    // LIFF endpoints (public, id_token-verified inside)
    Route::post('liff/link-patient', [LiffController::class, 'linkPatient'])->name('liff.link-patient');
    Route::post('liff/me', [LiffController::class, 'me'])->name('liff.me');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/switch-branch', [AuthController::class, 'switchBranch'])->name('auth.switch-branch');

        // Admin endpoints (RBAC enforced)
        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('admin/roles', [RoleController::class, 'index'])->name('admin.roles.index');
            Route::put('admin/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('admin.roles.sync-permissions');
        });

        // Branch-scoped resources go below this group.
        Route::middleware('branch')->group(function () {
            Route::get('ping', fn () => response()->json(['data' => ['ok' => true, 'branch' => app('branch.id')]]));

            // Lookups (read-only, available to any authenticated user with branch context)
            Route::prefix('lookups')->group(function () {
                Route::get('doctors', [LookupController::class, 'doctors'])->name('lookups.doctors');
                Route::get('rooms', [LookupController::class, 'rooms'])->name('lookups.rooms');
                Route::get('procedures', [LookupController::class, 'procedures'])->name('lookups.procedures');
                Route::get('customer-groups', [LookupController::class, 'customerGroups'])->name('lookups.customer-groups');
            });

            // Patients
            Route::middleware('permission:patients.view')->group(function () {
                Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
                Route::get('patients/{patient:uuid}', [PatientController::class, 'show'])->name('patients.show');
            });
            Route::middleware('permission:patients.create')->post('patients', [PatientController::class, 'store'])->name('patients.store');
            Route::middleware('permission:patients.update')->put('patients/{patient:uuid}', [PatientController::class, 'update'])->name('patients.update');
            Route::middleware('permission:patients.delete')->delete('patients/{patient:uuid}', [PatientController::class, 'destroy'])->name('patients.destroy');

            // OPD Card aggregate (per-tab) endpoints
            Route::middleware('permission:patients.view')->group(function () {
                Route::get('patients/{patient:uuid}/visits', [OpdCardController::class, 'visits'])->name('opd.visits');
                Route::get('patients/{patient:uuid}/photos', [OpdCardController::class, 'photos'])->name('opd.photos');
                Route::get('patients/{patient:uuid}/consents', [OpdCardController::class, 'consents'])->name('opd.consents');
                Route::get('patients/{patient:uuid}/courses', [OpdCardController::class, 'courses'])->name('opd.courses');
                Route::get('patients/{patient:uuid}/financial', [OpdCardController::class, 'financial'])->name('opd.financial');
                Route::get('patients/{patient:uuid}/lab-results', [OpdCardController::class, 'labResults'])->name('opd.lab');
            });

            // Appointments
            Route::get('appointments/available-slots', [AppointmentController::class, 'availableSlots'])->name('appointments.slots');
            Route::middleware('permission:appointments.view')->group(function () {
                Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
                Route::get('appointments/{appointment:uuid}', [AppointmentController::class, 'show'])->name('appointments.show');
            });
            Route::middleware('permission:appointments.create')->post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
            Route::middleware('permission:appointments.update')->patch('appointments/{appointment:uuid}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
            Route::middleware('permission:appointments.cancel')->delete('appointments/{appointment:uuid}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');
            Route::middleware('permission:appointments.create')->post('appointments/quick-create', [AppointmentController::class, 'quickCreate'])->name('appointments.quick-create');

            // Follow-ups
            Route::middleware('permission:crm.view')->group(function () {
                Route::get('follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
                Route::get('follow-ups/stats', [FollowUpController::class, 'stats'])->name('follow-ups.stats');
                Route::get('follow-ups/{followUp}', [FollowUpController::class, 'show'])->name('follow-ups.show');
            });
            Route::middleware('permission:appointments.update')->group(function () {
                Route::patch('follow-ups/{followUp}/status', [FollowUpController::class, 'updateStatus'])->name('follow-ups.status');
                Route::post('follow-ups/{followUp}/contact', [FollowUpController::class, 'recordContact'])->name('follow-ups.contact');
                Route::delete('follow-ups/{followUp}', [FollowUpController::class, 'destroy'])->name('follow-ups.destroy');
            });

            // Audit logs (read-only)
            Route::middleware('permission:audit.view')->group(function () {
                Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
                Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
                Route::get('audit-logs/{id}', [AuditLogController::class, 'show'])->name('audit-logs.show')->where('id', '[0-9]+');
            });

            // Visits / POS
            Route::middleware('permission:visits.view')->group(function () {
                Route::get('visits/today', [VisitController::class, 'todayActive'])->name('visits.today');
                Route::get('visits/{visit:uuid}', [VisitController::class, 'show'])->name('visits.show');
            });
            Route::middleware('permission:visits.create')->post('visits', [VisitController::class, 'open'])->name('visits.open');
            Route::middleware('permission:visits.update')->group(function () {
                Route::patch('visits/{visit:uuid}/vital-signs', [VisitController::class, 'updateVitalSigns'])->name('visits.vitals');
                Route::post('visits/{visit:uuid}/invoice-items', [VisitController::class, 'addItem'])->name('visits.add-item');
                Route::delete('visits/{visit:uuid}/invoice-items/{item}', [VisitController::class, 'removeItem'])->name('visits.remove-item');
            });
            Route::middleware('permission:visits.checkout')->post('visits/{visit:uuid}/checkout', [VisitController::class, 'checkoutAction'])->name('visits.checkout');

            // Commissions
            Route::middleware('permission:finance.commission.view')->group(function () {
                Route::get('commissions', [CommissionController::class, 'index'])->name('commissions.index');
                Route::get('commissions/summary', [CommissionController::class, 'summary'])->name('commissions.summary');
                Route::get('commission-rates', [CommissionController::class, 'rates'])->name('commission-rates.index');
                Route::get('visits/{visit:uuid}/commission-preview', [CommissionController::class, 'preview'])->name('visits.commission-preview');
            });
            Route::middleware('permission:settings.manage')->post('commission-rates', [CommissionController::class, 'storeRate'])->name('commission-rates.store');

            // Reports
            Route::middleware('permission:finance.reports.view')->group(function () {
                Route::get('reports/payment-mix', [ReportController::class, 'paymentMix'])->name('api.reports.payment-mix');
                Route::get('reports/daily-pl', [ReportController::class, 'dailyPL'])->name('api.reports.daily-pl');
                Route::get('reports/doctor-performance', [ReportController::class, 'doctorPerformance'])->name('api.reports.doctor-performance');
                Route::get('reports/procedure-performance', [ReportController::class, 'procedurePerformance'])->name('api.reports.procedure-performance');
                // Sprint 15: 15 additional reports
                Route::get('reports/revenue-by-customer-group', [ReportController::class, 'revenueByCustomerGroup'])->name('api.reports.revenue-by-customer-group');
                Route::get('reports/revenue-by-source', [ReportController::class, 'revenueBySource'])->name('api.reports.revenue-by-source');
                Route::get('reports/cohort-retention', [ReportController::class, 'cohortRetention'])->name('api.reports.cohort-retention');
                Route::get('reports/demographics', [ReportController::class, 'demographics'])->name('api.reports.demographics');
                Route::get('reports/stock-value', [ReportController::class, 'stockValueSnapshot'])->name('api.reports.stock-value');
                Route::get('reports/receiving-history', [ReportController::class, 'receivingHistory'])->name('api.reports.receiving-history');
                Route::get('reports/course-outstanding', [ReportController::class, 'courseOutstanding'])->name('api.reports.course-outstanding');
                Route::get('reports/wallet-outstanding', [ReportController::class, 'walletOutstanding'])->name('api.reports.wallet-outstanding');
                Route::get('reports/member-topup-trend', [ReportController::class, 'memberTopupTrend'])->name('api.reports.member-topup-trend');
                Route::get('reports/commission-pending-vs-paid', [ReportController::class, 'commissionPendingVsPaid'])->name('api.reports.commission-pending-vs-paid');
                Route::get('reports/birthday-this-month', [ReportController::class, 'birthdayThisMonth'])->name('api.reports.birthday-this-month');
                Route::get('reports/lab-turnaround', [ReportController::class, 'labTurnaround'])->name('api.reports.lab-turnaround');
                Route::get('reports/doctor-utilization', [ReportController::class, 'doctorUtilization'])->name('api.reports.doctor-utilization');
                Route::get('reports/room-utilization', [ReportController::class, 'roomUtilization'])->name('api.reports.room-utilization');
                Route::get('reports/photo-upload-frequency', [ReportController::class, 'photoUploadFrequency'])->name('api.reports.photo-upload-frequency');
                Route::get('reports/refund-history', [ReportController::class, 'refundHistory'])->name('api.reports.refund-history');
            });

            // MIS Executive
            Route::middleware('permission:finance.reports.view')->group(function () {
                Route::get('mis/dashboard', [MisController::class, 'dashboard'])->name('mis.dashboard');
                Route::get('mis/charts', [MisController::class, 'charts'])->name('mis.charts');
                Route::get('mis/top-procedures', [MisController::class, 'topProcedures'])->name('mis.top-procedures');
                Route::get('mis/top-doctors', [MisController::class, 'topDoctors'])->name('mis.top-doctors');
                Route::get('mis/top-customer-groups', [MisController::class, 'topCustomerGroups'])->name('mis.top-customer-groups');
                Route::get('mis/top-patients', [MisController::class, 'topPatients'])->name('mis.top-patients');
            });

            // Marketing — Coupons
            Route::middleware('permission:marketing.coupon.view')->group(function () {
                Route::get('marketing/coupons', [CouponController::class, 'index'])->name('coupons.index');
                Route::get('marketing/coupons/{coupon}/redemptions', [CouponController::class, 'redemptions'])->name('coupons.redemptions');
                Route::post('marketing/coupons/validate', [CouponController::class, 'validateCode'])->name('coupons.validate');
            });
            Route::middleware('permission:marketing.coupon.manage')->group(function () {
                Route::post('marketing/coupons', [CouponController::class, 'store'])->name('coupons.store');
                Route::post('marketing/coupons/generate', [CouponController::class, 'generate'])->name('coupons.generate');
            });

            // Marketing — Promotions
            Route::middleware('permission:marketing.promotion.view')->group(function () {
                Route::get('marketing/promotions', [PromotionController::class, 'index'])->name('promotions.index');
                Route::get('marketing/promotions/active', [PromotionController::class, 'active'])->name('promotions.active');
                Route::post('marketing/promotions/{promotion}/preview', [PromotionController::class, 'preview'])->name('promotions.preview');
            });
            Route::middleware('permission:marketing.promotion.manage')->group(function () {
                Route::post('marketing/promotions', [PromotionController::class, 'store'])->name('promotions.store');
                Route::put('marketing/promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
            });

            // Marketing — Influencers
            Route::middleware('permission:marketing.influencer.view')->group(function () {
                Route::get('marketing/influencers', [InfluencerController::class, 'index'])->name('influencers.index');
                Route::get('marketing/influencers/{influencer}/campaigns', [InfluencerController::class, 'campaigns'])->name('influencers.campaigns');
                Route::get('marketing/campaigns/{campaign}/report', [InfluencerController::class, 'campaignReport'])->name('influencers.campaign-report');
                Route::get('marketing/referrals', [InfluencerController::class, 'referrals'])->name('influencers.referrals');
            });
            Route::middleware('permission:marketing.influencer.manage')->group(function () {
                Route::post('marketing/influencers', [InfluencerController::class, 'store'])->name('influencers.store');
                Route::put('marketing/influencers/{influencer}', [InfluencerController::class, 'update'])->name('influencers.update');
                Route::post('marketing/influencers/{influencer}/campaigns', [InfluencerController::class, 'storeCampaign'])->name('influencers.campaigns.store');
            });

            // Marketing — Reviews
            Route::middleware('permission:marketing.review.view')->group(function () {
                Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
                Route::get('reviews/aggregate', [ReviewController::class, 'aggregate'])->name('reviews.aggregate');
            });
            Route::middleware('permission:marketing.review.manage')->group(function () {
                Route::post('reviews/request', [ReviewController::class, 'request'])->name('reviews.request');
                Route::patch('reviews/{review}/moderate', [ReviewController::class, 'moderate'])->name('reviews.moderate');
            });

            // Marketing — LINE Rich Menus
            Route::middleware('permission:marketing.rich_menu.view')->group(function () {
                Route::get('line/rich-menus', [RichMenuController::class, 'index'])->name('rich-menus.index');
            });
            Route::middleware('permission:marketing.rich_menu.manage')->group(function () {
                Route::post('line/rich-menus', [RichMenuController::class, 'store'])->name('rich-menus.store');
                Route::post('line/rich-menus/{richMenu}/sync', [RichMenuController::class, 'sync'])->name('rich-menus.sync');
                Route::delete('line/rich-menus/{richMenu}', [RichMenuController::class, 'destroy'])->name('rich-menus.destroy');
            });

            // ───── QC (Module #26) ─────

            Route::middleware('permission:qc.view')->group(function () {
                Route::get('qc/checklists', [QcController::class, 'checklistsIndex'])->name('qc.checklists.index');
                Route::get('qc/runs', [QcController::class, 'runsIndex'])->name('qc.runs.index');
                Route::get('qc/runs/{run}', [QcController::class, 'runShow'])->name('qc.runs.show');
                Route::get('qc/summary', [QcController::class, 'summary'])->name('qc.summary');
            });
            Route::middleware('permission:qc.perform')->group(function () {
                Route::post('qc/runs', [QcController::class, 'runStart'])->name('qc.runs.start');
                Route::post('qc/runs/{run}/items', [QcController::class, 'runRecordItem'])->name('qc.runs.record');
                Route::post('qc/runs/{run}/complete', [QcController::class, 'runComplete'])->name('qc.runs.complete');
            });
            Route::middleware('permission:qc.manage')->group(function () {
                Route::post('qc/checklists', [QcController::class, 'checklistsStore'])->name('qc.checklists.store');
                Route::put('qc/checklists/{checklist}', [QcController::class, 'checklistsUpdate'])->name('qc.checklists.update');
                Route::delete('qc/checklists/{checklist}', [QcController::class, 'checklistsDestroy'])->name('qc.checklists.destroy');
            });

            // ───── System Health (Module #26) ─────

            Route::middleware('permission:system.health.view')->group(function () {
                Route::get('admin/system-health', [SystemHealthController::class, 'snapshot'])->name('system.health');
                Route::get('admin/system-health/cron-history', [SystemHealthController::class, 'cronHistory'])->name('system.cron');
            });
            Route::middleware('permission:system.queue.manage')->group(function () {
                Route::post('admin/queue/retry/{jobId}', [SystemHealthController::class, 'retryFailedJob'])->name('system.queue.retry');
            });

            // ───── Admin Settings (Module #25) ─────

            // Branches: super_admin only
            Route::middleware('permission:branches.manage')->group(function () {
                Route::get('admin/branches', [SettingsController::class, 'branchesIndex'])->name('branches.index');
                Route::post('admin/branches', [SettingsController::class, 'branchesStore'])->name('branches.store');
                Route::put('admin/branches/{branch}', [SettingsController::class, 'branchesUpdate'])->name('branches.update');
                Route::delete('admin/branches/{branch}', [SettingsController::class, 'branchesDestroy'])->name('branches.destroy');
            });

            // Settings entities (settings.view + settings.manage)
            Route::middleware('permission:settings.view')->group(function () {
                Route::get('admin/rooms', [SettingsController::class, 'roomsIndex'])->name('rooms.index');
                Route::get('admin/banks', [SettingsController::class, 'banksIndex'])->name('banks.index');
                Route::get('admin/customer-groups', [SettingsController::class, 'customerGroupsIndex'])->name('customer-groups.index');
                Route::get('admin/suppliers', [SettingsController::class, 'suppliersIndex'])->name('suppliers.index');
                Route::get('admin/procedures', [SettingsController::class, 'proceduresIndex'])->name('procedures.index');
                Route::get('admin/products', [SettingsController::class, 'productsIndex'])->name('products.index');
                Route::get('admin/product-categories', [SettingsController::class, 'productCategoriesIndex'])->name('product-categories.index');
                Route::get('admin/expense-categories', [SettingsController::class, 'expenseCategoriesIndex'])->name('admin.expense-categories.index');
            });
            Route::middleware('permission:settings.manage')->group(function () {
                // Rooms
                Route::post('admin/rooms', [SettingsController::class, 'roomsStore'])->name('rooms.store');
                Route::put('admin/rooms/{room}', [SettingsController::class, 'roomsUpdate'])->name('rooms.update');
                Route::delete('admin/rooms/{room}', [SettingsController::class, 'roomsDestroy'])->name('rooms.destroy');
                // Banks
                Route::post('admin/banks', [SettingsController::class, 'banksStore'])->name('banks.store');
                Route::put('admin/banks/{bank}', [SettingsController::class, 'banksUpdate'])->name('banks.update');
                Route::delete('admin/banks/{bank}', [SettingsController::class, 'banksDestroy'])->name('banks.destroy');
                // Customer Groups
                Route::post('admin/customer-groups', [SettingsController::class, 'customerGroupsStore'])->name('customer-groups.store');
                Route::put('admin/customer-groups/{customerGroup}', [SettingsController::class, 'customerGroupsUpdate'])->name('customer-groups.update');
                Route::delete('admin/customer-groups/{customerGroup}', [SettingsController::class, 'customerGroupsDestroy'])->name('customer-groups.destroy');
                // Suppliers
                Route::post('admin/suppliers', [SettingsController::class, 'suppliersStore'])->name('suppliers.store');
                Route::put('admin/suppliers/{supplier}', [SettingsController::class, 'suppliersUpdate'])->name('suppliers.update');
                Route::delete('admin/suppliers/{supplier}', [SettingsController::class, 'suppliersDestroy'])->name('suppliers.destroy');
                // Procedures
                Route::post('admin/procedures', [SettingsController::class, 'proceduresStore'])->name('procedures.store');
                Route::put('admin/procedures/{procedure}', [SettingsController::class, 'proceduresUpdate'])->name('procedures.update');
                Route::delete('admin/procedures/{procedure}', [SettingsController::class, 'proceduresDestroy'])->name('procedures.destroy');
                // Products
                Route::post('admin/products', [SettingsController::class, 'productsStore'])->name('products.store');
                Route::put('admin/products/{product}', [SettingsController::class, 'productsUpdate'])->name('products.update');
                Route::delete('admin/products/{product}', [SettingsController::class, 'productsDestroy'])->name('products.destroy');
                // Product Categories
                Route::post('admin/product-categories', [SettingsController::class, 'productCategoriesStore'])->name('product-categories.store');
                Route::put('admin/product-categories/{productCategory}', [SettingsController::class, 'productCategoriesUpdate'])->name('product-categories.update');
                Route::delete('admin/product-categories/{productCategory}', [SettingsController::class, 'productCategoriesDestroy'])->name('product-categories.destroy');
                // Expense Categories
                Route::post('admin/expense-categories', [SettingsController::class, 'expenseCategoriesStore'])->name('admin.expense-categories.store');
                Route::put('admin/expense-categories/{expenseCategory}', [SettingsController::class, 'expenseCategoriesUpdate'])->name('admin.expense-categories.update');
                Route::delete('admin/expense-categories/{expenseCategory}', [SettingsController::class, 'expenseCategoriesDestroy'])->name('admin.expense-categories.destroy');
                // Reorder
                Route::post('admin/{entity}/reorder', [SettingsController::class, 'reorder'])->name('settings.reorder');
            });

            // HR — Staff
            Route::middleware('permission:users.view')->group(function () {
                Route::get('admin/staff', [StaffController::class, 'index'])->name('staff.index');
                Route::get('admin/staff/{user}', [StaffController::class, 'show'])->name('staff.show');
            });
            Route::middleware('permission:users.create')->group(function () {
                Route::post('admin/staff', [StaffController::class, 'store'])->name('staff.store');
            });
            Route::middleware('permission:users.update')->group(function () {
                Route::put('admin/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
                Route::put('admin/staff/{user}/profile', [StaffController::class, 'updateProfile'])->name('staff.profile.update');
                Route::post('admin/staff/{user}/assign-roles', [StaffController::class, 'assignRoles'])->name('staff.assign-roles');
                Route::post('admin/staff/{user}/assign-branches', [StaffController::class, 'assignBranches'])->name('staff.assign-branches');
                Route::post('admin/staff/{user}/compensation-rules', [StaffController::class, 'storeCompensationRule'])->name('staff.compensation.store');
                Route::delete('admin/staff/{user}/compensation-rules/{rule}', [StaffController::class, 'destroyCompensationRule'])->name('staff.compensation.destroy');
            });
            Route::middleware('permission:users.delete')->group(function () {
                Route::delete('admin/staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
            });

            // HR — Time Clock (logged-in user can see their own status)
            Route::get('time-clock/me', [TimeClockController::class, 'me'])->name('time-clock.me');
            Route::middleware('permission:time_clock.view')->group(function () {
                Route::get('admin/time-clocks', [TimeClockController::class, 'index'])->name('time-clock.index');
                Route::get('admin/time-clocks/summary', [TimeClockController::class, 'summary'])->name('time-clock.summary');
            });
            Route::middleware('permission:time_clock.manage')->group(function () {
                Route::post('admin/time-clocks', [TimeClockController::class, 'manualEntry'])->name('time-clock.manual');
            });

            // HR — Payroll
            Route::middleware('permission:payroll.view')->group(function () {
                Route::get('admin/payrolls', [PayrollController::class, 'index'])->name('payrolls.index');
                Route::get('admin/payrolls/{payroll}', [PayrollController::class, 'show'])->name('payrolls.show');
            });
            Route::middleware('permission:payroll.manage')->group(function () {
                Route::post('admin/payrolls/preview', [PayrollController::class, 'preview'])->name('payrolls.preview');
                Route::post('admin/payrolls/{payroll}/finalize', [PayrollController::class, 'finalize'])->name('payrolls.finalize');
                Route::post('admin/payrolls/{payroll}/mark-paid', [PayrollController::class, 'markPaid'])->name('payrolls.mark-paid');
                Route::patch('admin/payrolls/{payroll}/items/{item}', [PayrollController::class, 'adjustItem'])->name('payrolls.items.adjust');
            });

            // Expenses + categories
            Route::middleware('permission:finance.expense.view')->group(function () {
                Route::get('expense-categories', [ExpenseController::class, 'categories'])->name('expense-categories.index');
                Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            });
            Route::middleware('permission:finance.expense.manage')->group(function () {
                Route::post('expense-categories', [ExpenseController::class, 'storeCategory'])->name('expense-categories.store');
                Route::put('expense-categories/{category}', [ExpenseController::class, 'updateCategory'])->name('expense-categories.update');
                Route::delete('expense-categories/{category}', [ExpenseController::class, 'destroyCategory'])->name('expense-categories.destroy');
                Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
                Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
                Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
            });

            // Daily closing
            Route::middleware('permission:finance.closing.view')->group(function () {
                Route::get('closings', [ClosingController::class, 'index'])->name('closings.index');
                Route::get('closings/{closing}', [ClosingController::class, 'show'])->name('closings.show');
            });
            Route::middleware('permission:finance.closing.perform')->group(function () {
                Route::post('closings/prepare', [ClosingController::class, 'prepare'])->name('closings.prepare');
                Route::post('closings/{closing}/commit', [ClosingController::class, 'commit'])->name('closings.commit');
                Route::post('closings/{closing}/reopen', [ClosingController::class, 'reopen'])->name('closings.reopen');
            });

            // Inventory
            Route::middleware('permission:inventory.view')->group(function () {
                Route::get('inventory/products', [InventoryController::class, 'products'])->name('inventory.products');
                Route::get('inventory/warehouses', [InventoryController::class, 'warehouses'])->name('inventory.warehouses');
                Route::get('inventory/stock-levels', [InventoryController::class, 'stockLevels'])->name('inventory.stock-levels');
                Route::get('inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');
                Route::get('inventory/expiry-alerts', [InventoryController::class, 'expiryAlerts'])->name('inventory.expiry-alerts');
                Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
                Route::get('inventory/receivings', [InventoryController::class, 'receivings'])->name('inventory.receivings');
                Route::get('inventory/requisitions', [InventoryController::class, 'requisitions'])->name('inventory.requisitions');
            });
            Route::middleware('permission:settings.manage')->post('inventory/products', [InventoryController::class, 'storeProduct'])->name('inventory.products.store');
            Route::middleware('permission:inventory.receive')->post('inventory/receivings', [InventoryController::class, 'storeReceiving'])->name('inventory.receivings.store');
            Route::middleware('permission:inventory.requisition.create')->post('inventory/requisitions', [InventoryController::class, 'storeRequisition'])->name('inventory.requisitions.store');
            Route::middleware('permission:inventory.requisition.approve')->group(function () {
                Route::post('inventory/requisitions/{requisition}/approve', [InventoryController::class, 'approveRequisition'])->name('inventory.requisitions.approve');
                Route::post('inventory/requisitions/{requisition}/reject', [InventoryController::class, 'rejectRequisition'])->name('inventory.requisitions.reject');
            });
            Route::middleware('permission:inventory.adjust')->post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

            // Member wallet
            Route::middleware('permission:member.view')->group(function () {
                Route::get('members', [MemberController::class, 'index'])->name('members.index');
                Route::get('members/{patientUuid}', [MemberController::class, 'show'])->name('members.show')->where('patientUuid', '[0-9a-f-]{36}');
                Route::get('members/{patientUuid}/transactions', [MemberController::class, 'transactions'])->name('members.transactions')->where('patientUuid', '[0-9a-f-]{36}');
            });
            Route::middleware('permission:member.topup')->post('members/{patientUuid}/deposit', [MemberController::class, 'deposit'])->name('members.deposit')->where('patientUuid', '[0-9a-f-]{36}');
            Route::middleware('permission:member.manage')->post('members/{patientUuid}/adjust', [MemberController::class, 'adjust'])->name('members.adjust')->where('patientUuid', '[0-9a-f-]{36}');
            Route::middleware('permission:member.refund')->post('members/{patientUuid}/refund', [MemberController::class, 'refund'])->name('members.refund')->where('patientUuid', '[0-9a-f-]{36}');

            // Courses
            Route::middleware('permission:course.view')->group(function () {
                Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
                Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');
                Route::get('patients/{patientUuid}/courses-list', [CourseController::class, 'byPatient'])->name('courses.by-patient')->where('patientUuid', '[0-9a-f-]{36}');
            });
            Route::middleware('permission:course.manage')->group(function () {
                Route::post('courses/{course}/use-session', [CourseController::class, 'useSession'])->name('courses.use-session');
                Route::post('courses/{course}/cancel', [CourseController::class, 'cancel'])->name('courses.cancel');
            });

            // Photos (uploaded files)
            Route::middleware('permission:media.upload')->post('patients/{patientUuid}/photos', [PhotoController::class, 'store'])->name('photos.store')->where('patientUuid', '[0-9a-f-]{36}');
            Route::middleware('permission:media.delete')->delete('photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');

            // Consent templates (admin)
            Route::middleware('permission:patients.view')->get('consent-templates', [ConsentController::class, 'templates'])->name('consent-templates.index');
            Route::middleware('permission:consent.template.manage')->group(function () {
                Route::post('consent-templates', [ConsentController::class, 'storeTemplate'])->name('consent-templates.store');
                Route::put('consent-templates/{template}', [ConsentController::class, 'updateTemplate'])->name('consent-templates.update');
                Route::delete('consent-templates/{template}', [ConsentController::class, 'destroyTemplate'])->name('consent-templates.destroy');
            });

            // Patient consents (per-patient lifecycle)
            Route::middleware('permission:patients.update')->post('patients/{patientUuid}/consents', [ConsentController::class, 'createForPatient'])->name('consents.store')->where('patientUuid', '[0-9a-f-]{36}');
            Route::middleware('permission:consent.sign')->post('consents/{consent}/sign', [ConsentController::class, 'sign'])->name('consents.sign');
            Route::middleware('permission:consent.void')->post('consents/{consent}/void', [ConsentController::class, 'void'])->name('consents.void');

            // Lab tests catalog
            Route::middleware('permission:lab.view')->get('lab-tests', [LabController::class, 'tests'])->name('lab-tests.index');
            Route::middleware('permission:lab.catalog.manage')->group(function () {
                Route::post('lab-tests', [LabController::class, 'storeTest'])->name('lab-tests.store');
                Route::put('lab-tests/{test}', [LabController::class, 'updateTest'])->name('lab-tests.update');
                Route::delete('lab-tests/{test}', [LabController::class, 'destroyTest'])->name('lab-tests.destroy');
            });

            // Lab orders
            Route::middleware('permission:lab.view')->group(function () {
                Route::get('lab-orders', [LabController::class, 'orders'])->name('lab-orders.index');
                Route::get('lab-orders/{order}', [LabController::class, 'show'])->name('lab-orders.show');
                Route::get('patients/{patientUuid}/lab-orders', [LabController::class, 'byPatient'])->name('lab-orders.by-patient')->where('patientUuid', '[0-9a-f-]{36}');
            });
            Route::middleware('permission:lab.order')->group(function () {
                Route::post('lab-orders', [LabController::class, 'storeOrder'])->name('lab-orders.store');
                Route::post('lab-orders/{order}/cancel', [LabController::class, 'cancelOrder'])->name('lab-orders.cancel');
            });
            Route::middleware('permission:lab.result')->group(function () {
                Route::post('lab-orders/{order}/results', [LabController::class, 'recordResults'])->name('lab-orders.results');
                Route::post('lab-orders/{order}/report', [LabController::class, 'attachReport'])->name('lab-orders.report');
            });

            // CRM Segments
            Route::middleware('permission:crm.view')->group(function () {
                Route::get('crm/segments', [CrmController::class, 'segments'])->name('crm.segments.index');
                Route::get('crm/segments/{segment}', [CrmController::class, 'showSegment'])->name('crm.segments.show');
                Route::get('crm/segments/{segment}/preview', [CrmController::class, 'previewSegment'])->name('crm.segments.preview');
                Route::get('crm/templates', [CrmController::class, 'templates'])->name('crm.templates.index');
                Route::get('crm/campaigns', [CrmController::class, 'campaigns'])->name('crm.campaigns.index');
                Route::get('crm/campaigns/{campaign}', [CrmController::class, 'showCampaign'])->name('crm.campaigns.show');
            });
            Route::middleware('permission:crm.segments.manage')->group(function () {
                Route::post('crm/segments', [CrmController::class, 'storeSegment'])->name('crm.segments.store');
                Route::put('crm/segments/{segment}', [CrmController::class, 'updateSegment'])->name('crm.segments.update');
                Route::delete('crm/segments/{segment}', [CrmController::class, 'destroySegment'])->name('crm.segments.destroy');
            });
            Route::middleware('permission:crm.templates.manage')->group(function () {
                Route::post('crm/templates', [CrmController::class, 'storeTemplate'])->name('crm.templates.store');
                Route::put('crm/templates/{template}', [CrmController::class, 'updateTemplate'])->name('crm.templates.update');
                Route::delete('crm/templates/{template}', [CrmController::class, 'destroyTemplate'])->name('crm.templates.destroy');
            });
            Route::middleware('permission:crm.broadcast.send')->group(function () {
                Route::post('crm/campaigns', [CrmController::class, 'storeCampaign'])->name('crm.campaigns.store');
                Route::post('crm/campaigns/{campaign}/send', [CrmController::class, 'sendNowCampaign'])->name('crm.campaigns.send');
                Route::post('crm/campaigns/{campaign}/cancel', [CrmController::class, 'cancelCampaign'])->name('crm.campaigns.cancel');
            });

            // Notifications (per-user)
            Route::middleware('permission:notifications.view')->group(function () {
                Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
                Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
                Route::patch('notifications/{notification}/mark-read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
                Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
                Route::get('notification-preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
                Route::put('notification-preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
            });

            // Birthday Campaigns + Follow-up Rules (admin)
            Route::middleware('permission:notifications.manage')->group(function () {
                Route::get('birthday-campaigns', [BirthdayCampaignController::class, 'index'])->name('birthday-campaigns.index');
                Route::get('birthday-campaigns/{campaign}', [BirthdayCampaignController::class, 'show'])->name('birthday-campaigns.show');
                Route::post('birthday-campaigns', [BirthdayCampaignController::class, 'store'])->name('birthday-campaigns.store');
                Route::put('birthday-campaigns/{campaign}', [BirthdayCampaignController::class, 'update'])->name('birthday-campaigns.update');
                Route::delete('birthday-campaigns/{campaign}', [BirthdayCampaignController::class, 'destroy'])->name('birthday-campaigns.destroy');
                Route::post('birthday-campaigns/{campaign}/send-now', [BirthdayCampaignController::class, 'sendNow'])->name('birthday-campaigns.send-now');

                Route::get('follow-up-rules', [FollowUpRuleController::class, 'index'])->name('follow-up-rules.index');
                Route::post('follow-up-rules', [FollowUpRuleController::class, 'store'])->name('follow-up-rules.store');
                Route::put('follow-up-rules/{rule}', [FollowUpRuleController::class, 'update'])->name('follow-up-rules.update');
                Route::delete('follow-up-rules/{rule}', [FollowUpRuleController::class, 'destroy'])->name('follow-up-rules.destroy');
            });

            // Dashboard summary widgets
            Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

            // Messaging providers (admin)
            Route::middleware('permission:messaging.providers.view')->group(function () {
                Route::get('messaging-providers', [MessagingProviderController::class, 'index'])->name('messaging-providers.index');
            });
            Route::middleware('permission:messaging.providers.manage')->group(function () {
                Route::post('messaging-providers', [MessagingProviderController::class, 'store'])->name('messaging-providers.store');
                Route::put('messaging-providers/{provider}', [MessagingProviderController::class, 'update'])->name('messaging-providers.update');
                Route::delete('messaging-providers/{provider}', [MessagingProviderController::class, 'destroy'])->name('messaging-providers.destroy');
                Route::post('messaging-providers/{provider}/test', [MessagingProviderController::class, 'test'])->name('messaging-providers.test');
            });
            Route::middleware('permission:messaging.logs.view')->group(function () {
                Route::get('messaging-logs', [MessagingProviderController::class, 'logs'])->name('messaging-logs.index');
            });
            Route::middleware('permission:messaging.logs.retry')->group(function () {
                Route::post('messaging-logs/{log}/retry', [MessagingProviderController::class, 'retryLog'])->name('messaging-logs.retry');
            });

            // Accounting: CoA
            Route::middleware('permission:accounting.coa.view')->group(function () {
                Route::get('accounting/coa', [AccountingController::class, 'coaIndex'])->name('accounting.coa.index');
            });
            Route::middleware('permission:accounting.coa.manage')->group(function () {
                Route::post('accounting/coa', [AccountingController::class, 'coaStore'])->name('accounting.coa.store');
                Route::put('accounting/coa/{account}', [AccountingController::class, 'coaUpdate'])->name('accounting.coa.update');
            });

            // Accounting: PR
            Route::middleware('permission:accounting.pr.view')->group(function () {
                Route::get('accounting/pr', [AccountingController::class, 'prIndex'])->name('accounting.pr.index');
                Route::get('accounting/pr/{pr}', [AccountingController::class, 'prShow'])->name('accounting.pr.show');
            });
            Route::middleware('permission:accounting.pr.manage')->group(function () {
                Route::post('accounting/pr', [AccountingController::class, 'prStore'])->name('accounting.pr.store');
                Route::post('accounting/pr/{pr}/submit', [AccountingController::class, 'prSubmit'])->name('accounting.pr.submit');
                Route::post('accounting/pr/{pr}/approve', [AccountingController::class, 'prApprove'])->name('accounting.pr.approve');
                Route::post('accounting/pr/{pr}/reject', [AccountingController::class, 'prReject'])->name('accounting.pr.reject');
                Route::post('accounting/pr/{pr}/convert', [AccountingController::class, 'prConvertToPo'])->name('accounting.pr.convert');
            });

            // Accounting: PO
            Route::middleware('permission:accounting.po.view')->group(function () {
                Route::get('accounting/po', [AccountingController::class, 'poIndex'])->name('accounting.po.index');
                Route::get('accounting/po/{po}', [AccountingController::class, 'poShow'])->name('accounting.po.show');
            });
            Route::middleware('permission:accounting.po.manage')->group(function () {
                Route::post('accounting/po/{po}/send', [AccountingController::class, 'poSend'])->name('accounting.po.send');
                Route::post('accounting/po/{po}/receive', [AccountingController::class, 'poReceive'])->name('accounting.po.receive');
                Route::post('accounting/po/{po}/cancel', [AccountingController::class, 'poCancel'])->name('accounting.po.cancel');
            });

            // Accounting: Disbursements
            Route::middleware('permission:accounting.disbursement.view')->group(function () {
                Route::get('accounting/disbursements', [AccountingController::class, 'disbursementIndex'])->name('accounting.disbursements.index');
            });
            Route::middleware('permission:accounting.disbursement.manage')->group(function () {
                Route::post('accounting/disbursements', [AccountingController::class, 'disbursementStore'])->name('accounting.disbursements.store');
                Route::post('accounting/disbursements/{disbursement}/approve', [AccountingController::class, 'disbursementApprove'])->name('accounting.disbursements.approve');
                Route::post('accounting/disbursements/{disbursement}/pay', [AccountingController::class, 'disbursementPay'])->name('accounting.disbursements.pay');
            });

            // Accounting: Tax Invoices
            Route::middleware('permission:accounting.tax.view')->group(function () {
                Route::get('accounting/tax-invoices', [AccountingController::class, 'taxIndex'])->name('accounting.tax.index');
            });
            Route::middleware('permission:accounting.tax.manage')->group(function () {
                Route::post('accounting/tax-invoices', [AccountingController::class, 'taxIssue'])->name('accounting.tax.issue');
                Route::post('accounting/tax-invoices/{taxInvoice}/void', [AccountingController::class, 'taxVoid'])->name('accounting.tax.void');
            });

            // Accounting: Reports
            Route::middleware('permission:accounting.ledger.view')->group(function () {
                Route::get('accounting/reports/ledger', [AccountingController::class, 'ledger'])->name('accounting.reports.ledger');
                Route::get('accounting/reports/trial-balance', [AccountingController::class, 'trialBalance'])->name('accounting.reports.trial-balance');
                Route::get('accounting/reports/cash-flow', [AccountingController::class, 'cashFlow'])->name('accounting.reports.cash-flow');
                Route::get('accounting/reports/tax-summary', [AccountingController::class, 'taxSummary'])->name('accounting.reports.tax-summary');
            });
        });
    });

    // Public marketing endpoints (no auth)
    Route::post('public/reviews/{token}', [PublicMarketingController::class, 'submitReview'])->name('public.reviews.submit');

    // Public time-clock kiosk (PIN-authenticated, no Sanctum)
    Route::post('public/time-clock/in', [TimeClockController::class, 'clockInPin'])->name('public.time-clock.in');
    Route::post('public/time-clock/out', [TimeClockController::class, 'clockOutPin'])->name('public.time-clock.out');
});
