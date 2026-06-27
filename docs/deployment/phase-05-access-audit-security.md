# نشر المرحلة 5 — الصلاحيات والسجلات

1. Backup قاعدة البيانات
2. نشر الكود
3. `php artisan migrate`
4. `php artisan db:seed --class=RolesAndPermissionsSeeder`
5. `php artisan permission:cache-reset` (إن وُجد)
6. مراجعة من يحصل على `beneficiaries.identity.view_full`
7. Smoke test: كشف هوية، تنزيل CV، تصدير Excel، Filament audit/security logs
