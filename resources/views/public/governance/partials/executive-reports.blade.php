@props(['documents'])

@include('public.governance.partials.document-list', [
    'documents' => $documents,
    'categoriesKey' => 'governance.executive_report_categories',
    'categoryOrderKey' => 'governance.executive_report_category_order',
    'emptyTitle' => 'لا توجد تقارير تنفيذية منشورة حالياً',
    'downloadPrefix' => 'executive-report',
])
