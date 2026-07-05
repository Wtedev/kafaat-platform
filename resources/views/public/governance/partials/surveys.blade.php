@props(['documents'])

@include('public.governance.partials.document-list', [
    'documents' => $documents,
    'categoriesKey' => 'governance.survey_categories',
    'categoryOrderKey' => 'governance.survey_category_order',
    'emptyTitle' => 'لا توجد استطلاعات منشورة حالياً',
    'downloadPrefix' => 'survey',
])
