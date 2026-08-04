<x-mail::message>
# {{ $greeting }}

وصل رد جديد من فريق الدعم على تذكرتك.

**رقم التذكرة:** {{ $ticketNumber }}  
**الموضوع:** {{ $ticketSubject }}  
**الحالة الحالية:** {{ $ticketStatus }}

---

**رد فريق الدعم:**

{{ $replyBody }}

<x-mail::button :url="$ticketUrl">
عرض التذكرة والرد
</x-mail::button>

يرجى عدم الرد على هذا البريد. يمكنك الرد عبر المنصة من خلال الزر أعلاه.

مع تحيات فريق كفاءات
</x-mail::message>
