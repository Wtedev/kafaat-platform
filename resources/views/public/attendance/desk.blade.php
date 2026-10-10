@extends('layouts.public')

@section('title', 'التحضير')

@section('content')
    <livewire:attendance.trainer-desk :token="$token" />
@endsection
