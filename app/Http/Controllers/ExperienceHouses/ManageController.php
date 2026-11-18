<?php

namespace App\Http\Controllers\ExperienceHouses;

use App\Http\Controllers\Controller;
use App\Models\ExperienceHouseManager;
use App\Models\ExperienceHouseRequest;
use App\Models\ExperienceHouseRequestEvent;
use App\Services\Documents\PrivateDocumentsStorage;
use App\Services\ExperienceHouses\ManagerAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * الواجهة المبسطة لمتابعة طلبات بيوت الخبرة (لغير التقنيين).
 */
class ManageController extends Controller
{
    public const TABS = [
        'all' => ['label' => 'جميع الحالات', 'icon' => 'clipboard', 'statuses' => ['new', 'in_review', 'scheduled', 'in_progress', 'completed', 'rejected']],
        'new' => ['label' => 'جديدة', 'icon' => 'inbox', 'statuses' => ['new']],
        'active' => ['label' => 'قيد المتابعة', 'icon' => 'play', 'statuses' => ['in_review', 'scheduled', 'in_progress']],
        'done' => ['label' => 'مكتملة', 'icon' => 'check', 'statuses' => ['completed']],
        'closed' => ['label' => 'معتذر عنها', 'icon' => 'x', 'statuses' => ['rejected']],
    ];

    public function __construct(private readonly ManagerAccess $access) {}

    // ───────── الدخول ─────────

    public function loginForm(Request $request): View|RedirectResponse
    {
        if ($this->access->current($request)) {
            return redirect()->route('experience-houses.manage.index');
        }

        return view('experience-houses.manage.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'email.required' => 'اكتب بريدك الإلكتروني.',
            'email.email' => 'تأكد من كتابة البريد بشكل صحيح.',
            'password.required' => 'اكتب كلمة المرور.',
        ]);

        $manager = $this->access->attempt($data['email'], $data['password']);
        if (! $manager) {
            return back()->withErrors(['email' => 'البريد أو كلمة المرور غير صحيحة.'])->onlyInput('email');
        }

        $this->access->login($request, $manager);

        return redirect()->intended(route('experience-houses.manage.index'));
    }

    public function forgotForm(Request $request): View|RedirectResponse
    {
        if ($this->access->current($request)) {
            return redirect()->route('experience-houses.manage.index');
        }

        return view('experience-houses.manage.forgot');
    }

    public function sendLoginLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']], [
            'email.required' => 'اكتب بريدك الإلكتروني.',
            'email.email' => 'تأكد من كتابة البريد بشكل صحيح.',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $manager = ExperienceHouseManager::query()->where('email', $email)->where('is_active', true)->first();

        if ($manager) {
            $this->access->sendLink($manager, 'login');
        }

        // نفس الرسالة دائماً حتى لا نكشف من لديه حساب
        return redirect()->route('experience-houses.manage.forgot')->with('sent_to', $email);
    }

    /** صفحة إنشاء كلمة المرور من رابط الدعوة أو الاستعادة. */
    public function authenticate(string $token): View|RedirectResponse
    {
        $row = $this->access->peek($token);
        if (! $row) {
            return redirect()->route('experience-houses.manage.forgot')
                ->with('link_error', 'انتهت صلاحية الرابط أو سبق استخدامه. اطلب رابطاً جديداً.');
        }

        return view('experience-houses.manage.set-password', [
            'token' => $token,
            'member' => $row->manager,
            'invite' => $row->purpose === 'invite' || $row->manager->password === null,
        ]);
    }

    public function setPassword(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'password.required' => 'اكتب كلمة المرور.',
            'password.min' => 'كلمة المرور 8 أحرف على الأقل.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
        ]);

        $manager = $this->access->setPasswordWithToken($token, (string) $request->input('password'));
        if (! $manager) {
            return redirect()->route('experience-houses.manage.forgot')
                ->with('link_error', 'انتهت صلاحية الرابط أو سبق استخدامه. اطلب رابطاً جديداً.');
        }

        $this->access->login($request, $manager);

        return redirect()->route('experience-houses.manage.index')->with('welcome', true);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->access->logout($request);

        return redirect()->route('experience-houses.manage.login');
    }

    // ───────── الطلبات ─────────

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'all';
        $q = trim((string) $request->query('q', ''));

        $counts = [];
        foreach (self::TABS as $key => $def) {
            $counts[$key] = ExperienceHouseRequest::query()->whereIn('status', $def['statuses'])->count();
        }

        $requests = ExperienceHouseRequest::query()
            ->whereIn('status', self::TABS[$tab]['statuses'])
            ->when($q !== '', function (Builder $query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $query->where(fn (Builder $w) => $w
                    ->where('organization_name', 'like', $like)
                    ->orWhere('reference_no', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('contact_name', 'like', $like));
            })
            ->orderByRaw("case when priority = 'urgent' then 0 else 1 end")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('experience-houses.manage.index', [
            'tabs' => self::TABS,
            'tab' => $tab,
            'counts' => $counts,
            'requests' => $requests,
            'q' => $q,
        ]);
    }

    public function show(ExperienceHouseRequest $experienceRequest): View
    {
        return view('experience-houses.manage.show', ['r' => $experienceRequest->load(['handledBy', 'events' => fn ($q) => $q->with('manager')->latest()->latest('id')])]);
    }

    public function updateStatus(Request $request, ExperienceHouseRequest $experienceRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ExperienceHouseRequest::STATUSES))],
        ]);

        $experienceRequest->forceFill([
            'status' => $data['status'],
            'status_changed_at' => now(),
            'handled_by_id' => $request->attributes->get('eh_manager')->id,
        ])->save();

        return back()->with('toast', 'تم تحديث الحالة إلى «'.ExperienceHouseRequest::STATUSES[$data['status']].'»');
    }

    public function saveNote(Request $request, ExperienceHouseRequest $experienceRequest): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], [
            'body.required' => 'اكتب التعليق أولاً.',
        ]);

        $experienceRequest->events()->create([
            'type' => ExperienceHouseRequestEvent::TYPE_COMMENT,
            'manager_id' => $request->attributes->get('eh_manager')->id,
            'body' => trim($data['body']),
        ]);

        return back()->with('toast', 'أُضيف التعليق');
    }

    public function attachment(ExperienceHouseRequest $experienceRequest): StreamedResponse
    {
        $disk = PrivateDocumentsStorage::disk();
        abort_unless($experienceRequest->attachment_path && $disk->exists($experienceRequest->attachment_path), 404);

        return $disk->download($experienceRequest->attachment_path, $experienceRequest->attachment_name ?? 'attachment');
    }

    // ───────── الفريق ─────────

    public function team(): View
    {
        return view('experience-houses.manage.team', [
            'members' => ExperienceHouseManager::query()->where('is_active', true)->orderByRaw('activated_at is null')->orderBy('name')->get(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
        ], [
            'name.required' => 'اكتب الاسم.',
            'email.required' => 'اكتب البريد الإلكتروني.',
            'email.email' => 'تأكد من كتابة البريد بشكل صحيح.',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $inviter = $request->attributes->get('eh_manager');

        $member = ExperienceHouseManager::query()->firstOrNew(['email' => $email]);
        if ($member->exists && $member->is_active && $member->activated_at) {
            return back()->withErrors(['email' => 'هذا البريد لديه حساب مفعّل بالفعل.'])->withInput();
        }

        $member->fill(['name' => $data['name'], 'is_active' => true, 'invited_by_id' => $member->invited_by_id ?? $inviter->id])->save();

        if (! $this->access->sendLink($member, 'invite', $inviter)) {
            return back()->with('toast_error', 'تمت الإضافة لكن تعذّر إرسال البريد. جرّب «إعادة الإرسال» بعد قليل.');
        }

        return back()->with('toast', 'أُرسل إيميل التفعيل إلى '.$email);
    }

    public function resend(Request $request, ExperienceHouseManager $member): RedirectResponse
    {
        abort_unless($member->is_active, 404);
        $ok = $this->access->sendLink($member, $member->activated_at ? 'login' : 'invite', $request->attributes->get('eh_manager'));

        return back()->with($ok ? 'toast' : 'toast_error', $ok ? 'أُعيد الإرسال إلى '.$member->email : 'تعذّر إرسال البريد، حاول لاحقاً.');
    }

    public function remove(Request $request, ExperienceHouseManager $member): RedirectResponse
    {
        if ($member->id === $request->attributes->get('eh_manager')->id) {
            return back()->with('toast_error', 'لا يمكنك إزالة نفسك.');
        }

        $member->forceFill(['is_active' => false])->save();

        return back()->with('toast', 'تمت إزالة '.$member->name.' من الفريق');
    }
}
