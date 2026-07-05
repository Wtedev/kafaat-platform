@props(['documents'])

@include('public.governance.partials.document-list', [
    'documents' => $documents,
    'categoriesKey' => 'governance.general_assembly_minute_categories',
    'categoryOrderKey' => 'governance.general_assembly_minute_category_order',
    'emptyTitle' => 'لا توجد محاضر منشورة حالياً',
    'downloadPrefix' => 'minutes',
])
