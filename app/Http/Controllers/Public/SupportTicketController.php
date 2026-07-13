<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function store(Request $request, SupportTicketService $tickets): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:191'],
                'subject' => ['required', 'string', 'max:200'],
                'body' => ['required', 'string', 'min:10', 'max:4000'],
                'page_url' => ['nullable', 'string', 'max:500'],
            ],
            [
                'name.required' => 'الاسم مطلوب.',
                'email.required' => 'البريد الإلكتروني مطلوب.',
                'email.email' => 'صيغة البريد غير صحيحة.',
                'subject.required' => 'موضوع المشكلة مطلوب.',
                'body.required' => 'وصف المشكلة مطلوب.',
                'body.min' => 'يرجى كتابة وصف أوضح للمشكلة (10 أحرف على الأقل).',
            ],
        );

        if ($user !== null) {
            $validated['name'] = $user->name ?: $validated['name'];
            $validated['email'] = $user->email ?: $validated['email'];
        }

        $tickets->create($validated, $user);

        return back()->with('success', 'تم استلام تذكرتك بنجاح. سيتواصل فريق كفاءات معك قريباً.');
    }
}
