@extends('layouts.portal')
@section('title', 'تعديل بياناتي')

@section('content')
<x-portal.settings-shell title="تعديل بياناتي" subtitle="الصورة، الاسم، الهوية، والمسمى الوظيفي." max-width="max-w-3xl">
    @include('portal.partials.profile-form', ['user' => $user])
</x-portal.settings-shell>
@endsection
