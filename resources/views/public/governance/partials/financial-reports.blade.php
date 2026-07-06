@props(['documents'])

@include('public.governance.partials.document-list', [
    'documents' => $documents,
    'categoriesKey' => 'governance.financial_report_categories',
    'categoryOrderKey' => 'governance.financial_report_category_order',
    'emptyTitle' => 'لا توجد تقارير مالية منشورة حالياً',
    'downloadPrefix' => 'financial-report',
])
