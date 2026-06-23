@php
    use App\Support\OrganizationalStructureCatalog;

    $org = OrganizationalStructureCatalog::data();
    $ceo = $org['ceo'];
    $departments = $org['departments'];

    $branchStaff = static function (array $dept): array {
        $out = [];
        foreach ($dept['sub_units'] ?? [] as $unit) {
            $out[] = ['name' => $unit['name'], 'title' => $unit['title'], 'kind' => 'unit'];
        }
        foreach ($dept['members'] ?? [] as $member) {
            $out[] = ['name' => $member['name'], 'title' => $member['title'] ?? null, 'kind' => 'member'];
        }

        return $out;
    };
@endphp

<div class="oc-shell" dir="rtl">
    <p class="oc-shell__hint">مرّر أفقياً لاستكشاف الهيكل الكامل · {{ count($departments) }} إدارات رئيسية</p>

    <div class="oc-scroll" tabindex="0" aria-label="منطقة تمرير الهيكل التنظيمي">
        <div class="oc-tree" aria-label="الهيكل التنظيمي لجمعية كفاءات">
            <ul class="oc-level oc-level--root">
                <li class="oc-node oc-node--root">
                    <div class="oc-card oc-card--ceo">
                        <span class="oc-card__badge">القيادة التنفيذية</span>
                        <div class="oc-avatar oc-avatar--ceo" aria-hidden="true">{{ OrganizationalStructureCatalog::initials($ceo['name']) }}</div>
                        <p class="oc-card__name">{{ $ceo['name'] }}</p>
                        <p class="oc-card__role">{{ $ceo['title'] }}</p>
                    </div>

                    <div class="oc-trunk" aria-hidden="true"></div>

                    <ul class="oc-level oc-level--departments">
                        @foreach($departments as $dept)
                            @php
                                $staff = $branchStaff($dept);
                                $subDepts = $dept['sub_departments'] ?? [];
                                $isGroupOnly = ! empty($dept['group_only']);
                            @endphp
                            <li class="oc-node oc-node--branch {{ $subDepts !== [] ? 'oc-node--has-subdepts' : '' }}">
                                <div class="oc-branch-head">
                                    <span class="oc-dept-pill {{ $isGroupOnly ? 'oc-dept-pill--group' : '' }}">{{ $dept['name'] }}</span>

                                    @if(! $isGroupOnly && isset($dept['manager']))
                                        <div class="oc-card oc-card--manager">
                                            <div class="oc-avatar oc-avatar--manager" aria-hidden="true">{{ OrganizationalStructureCatalog::initials($dept['manager']['name']) }}</div>
                                            <p class="oc-card__name oc-card__name--sm">{{ $dept['manager']['name'] }}</p>
                                            <p class="oc-card__role">{{ $dept['manager']['title'] }}</p>
                                        </div>
                                    @endif
                                </div>

                                @if($subDepts !== [])
                                    <div class="oc-staff-trunk" aria-hidden="true"></div>
                                    <ul class="oc-level oc-level--subdepts">
                                        @foreach($subDepts as $sub)
                                            @php $subStaff = $branchStaff($sub); @endphp
                                            <li class="oc-node oc-node--subdept">
                                                <div class="oc-subdept-block">
                                                    <span class="oc-dept-pill oc-dept-pill--sub">{{ $sub['name'] }}</span>
                                                    <div class="oc-card oc-card--manager oc-card--sub-manager">
                                                        <div class="oc-avatar oc-avatar--manager" aria-hidden="true">{{ OrganizationalStructureCatalog::initials($sub['manager']['name']) }}</div>
                                                        <p class="oc-card__name oc-card__name--sm">{{ $sub['manager']['name'] }}</p>
                                                        <p class="oc-card__role">{{ $sub['manager']['title'] }}</p>
                                                    </div>
                                                </div>

                                                @if($subStaff !== [])
                                                    <div class="oc-staff-trunk oc-staff-trunk--short" aria-hidden="true"></div>
                                                    <ul class="oc-level oc-level--staff">
                                                        @foreach($subStaff as $child)
                                                            <li class="oc-node oc-node--leaf">
                                                                <div class="oc-card oc-card--staff">
                                                                    <div class="oc-avatar oc-avatar--staff" aria-hidden="true">{{ OrganizationalStructureCatalog::initials($child['name']) }}</div>
                                                                    <p class="oc-card__name oc-card__name--xs">{{ $child['name'] }}</p>
                                                                    @if(filled($child['title']))
                                                                        <p class="oc-card__role oc-card__role--member">{{ $child['title'] }}</p>
                                                                    @endif
                                                                </div>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif($staff !== [])
                                    <div class="oc-staff-trunk" aria-hidden="true"></div>
                                    <ul class="oc-level oc-level--staff">
                                        @foreach($staff as $child)
                                            <li class="oc-node oc-node--leaf">
                                                <div class="oc-card oc-card--staff {{ $child['kind'] === 'unit' ? 'oc-card--unit' : '' }}">
                                                    <div class="oc-avatar oc-avatar--staff" aria-hidden="true">{{ OrganizationalStructureCatalog::initials($child['name']) }}</div>
                                                    <p class="oc-card__name oc-card__name--xs">{{ $child['name'] }}</p>
                                                    @if(filled($child['title']))
                                                        <p class="oc-card__role {{ $child['kind'] === 'unit' ? 'oc-card__role--unit' : 'oc-card__role--member' }}">{{ $child['title'] }}</p>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>

<style>
    .oc-shell {
        --oc-brand: #335483;
        --oc-brand-dark: #243a55;
        --oc-teal: #1a9399;
        --oc-line: #c5d4e4;
        --oc-line-strong: #94a8c4;
        --oc-text: #111827;
        --oc-muted: #6b7280;
        --oc-card-w: 10.75rem;
        --oc-gap-v: 1.5rem;
        margin-block: 0.25rem 0.5rem;
    }

    .oc-shell__hint {
        margin: 0 0 0.75rem;
        text-align: center;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--oc-muted);
    }

    .oc-scroll {
        overflow-x: auto;
        overflow-y: visible;
        padding: 0.5rem 0.5rem 1.5rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: var(--oc-line-strong) transparent;
        border-radius: 1rem;
    }

    .oc-scroll:focus-visible {
        outline: 2px solid var(--oc-brand);
        outline-offset: 3px;
    }

    .oc-tree,
    .oc-tree ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .oc-tree {
        display: inline-block;
        min-width: max(100%, 58rem);
        width: max-content;
        margin-inline: auto;
    }

    .oc-level {
        position: relative;
        display: flex;
        justify-content: center;
    }

    .oc-level--root {
        flex-direction: column;
        align-items: center;
    }

    .oc-node--root {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0;
    }

    .oc-trunk {
        width: 2px;
        height: var(--oc-gap-v);
        background: var(--oc-line-strong);
        flex-shrink: 0;
    }

    .oc-level--departments {
        flex-wrap: nowrap;
        justify-content: center;
        gap: 0;
        padding-top: 0;
        position: relative;
    }

    .oc-level--departments::before {
        content: '';
        position: absolute;
        top: 0;
        right: 5%;
        left: 5%;
        height: 2px;
        background: var(--oc-line-strong);
    }

    .oc-level--departments > .oc-node--branch {
        position: relative;
        text-align: center;
        padding: var(--oc-gap-v) 0.5rem 0;
        flex: 0 0 auto;
        min-width: var(--oc-card-w);
        max-width: 13.5rem;
    }

    .oc-node--has-subdepts {
        max-width: 14.5rem;
    }

    .oc-level--departments > .oc-node--branch::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 2px;
        height: var(--oc-gap-v);
        background: var(--oc-line-strong);
    }

    .oc-branch-head {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.65rem;
    }

    .oc-staff-trunk {
        width: 2px;
        height: 1rem;
        margin: 0.35rem auto 0;
        background: var(--oc-line);
    }

    .oc-staff-trunk--short {
        height: 0.65rem;
        margin-top: 0.25rem;
    }

    .oc-level--subdepts {
        flex-direction: column;
        align-items: center;
        gap: 0.85rem;
        padding: 0;
        width: 100%;
    }

    .oc-node--subdept {
        width: 100%;
        padding: 0;
    }

    .oc-subdept-block {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.55rem;
        padding: 0.65rem 0.35rem;
        border-radius: 1rem;
        background: #f8fafc;
        border: 1px dashed #d1dce8;
    }

    .oc-level--staff {
        flex-direction: column;
        align-items: center;
        gap: 0.65rem;
        padding: 0;
    }

    .oc-node--leaf {
        width: 100%;
        padding: 0;
    }

    .oc-dept-pill {
        display: block;
        width: 100%;
        max-width: 11.5rem;
        padding: 0.42rem 0.7rem;
        border-radius: 9999px;
        font-size: 0.68rem;
        font-weight: 800;
        line-height: 1.5;
        color: var(--oc-brand);
        background: #e9eff6;
        border: 1px solid #c5d4e4;
        box-shadow: 0 2px 8px -4px rgba(51, 84, 131, 0.2);
        text-wrap: balance;
    }

    .oc-dept-pill--group {
        max-width: 12.5rem;
        font-size: 0.72rem;
        background: linear-gradient(135deg, #e9eff6, #dce8f5);
        border-color: #94a8c4;
    }

    .oc-dept-pill--sub {
        max-width: 100%;
        font-size: 0.64rem;
        background: #fff;
        border-style: solid;
        border-color: #c5d4e4;
        box-shadow: none;
    }

    .oc-card {
        position: relative;
        z-index: 1;
        width: var(--oc-card-w);
        margin-inline: auto;
        padding: 0.85rem 0.65rem 0.75rem;
        border-radius: 1rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 6px 20px -10px rgba(51, 84, 131, 0.18);
        transition: transform 0.22s cubic-bezier(.22, 1, .36, 1), box-shadow 0.22s ease;
    }

    .oc-card--sub-manager {
        width: 9.75rem;
        padding: 0.7rem 0.55rem 0.6rem;
        box-shadow: 0 4px 14px -8px rgba(51, 84, 131, 0.15);
    }

    .oc-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 32px -12px rgba(51, 84, 131, 0.22);
    }

    .oc-card--ceo {
        width: min(100%, 15.5rem);
        padding: 1.25rem 1rem 1.1rem;
        border-color: #c5d4e4;
        background: linear-gradient(160deg, #ffffff 0%, #f0f5fa 100%);
        box-shadow: 0 16px 40px -14px rgba(51, 84, 131, 0.28);
    }

    .oc-card--ceo:hover {
        transform: translateY(-4px);
    }

    .oc-card--manager {
        border-color: color-mix(in srgb, var(--oc-brand) 25%, #e5e7eb);
        background: linear-gradient(180deg, #ffffff, #f8fafc);
    }

    .oc-card--staff {
        width: 9.5rem;
        padding: 0.65rem 0.5rem 0.55rem;
        border-radius: 0.85rem;
        background: #fafbfc;
    }

    .oc-card--unit {
        border-color: color-mix(in srgb, var(--oc-teal) 35%, #e5e7eb);
        background: linear-gradient(180deg, #ffffff, #f0fafb);
    }

    .oc-card__badge {
        display: inline-block;
        margin-bottom: 0.65rem;
        padding: 0.2rem 0.6rem;
        border-radius: 9999px;
        font-size: 0.62rem;
        font-weight: 700;
        color: var(--oc-brand);
        background: #e9eff6;
    }

    .oc-avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.55rem;
        border-radius: 9999px;
        font-weight: 800;
        color: #fff;
        flex-shrink: 0;
    }

    .oc-avatar--ceo {
        width: 3.5rem;
        height: 3.5rem;
        font-size: 1rem;
        background: linear-gradient(135deg, var(--oc-brand), var(--oc-brand-dark));
        box-shadow: 0 6px 18px -6px rgba(51, 84, 131, 0.5);
    }

    .oc-avatar--manager {
        width: 2.35rem;
        height: 2.35rem;
        font-size: 0.72rem;
        background: var(--oc-brand);
    }

    .oc-card--sub-manager .oc-avatar--manager {
        width: 2.1rem;
        height: 2.1rem;
        font-size: 0.68rem;
    }

    .oc-avatar--staff {
        width: 2rem;
        height: 2rem;
        font-size: 0.65rem;
        background: var(--oc-teal);
    }

    .oc-card__name {
        font-size: 0.82rem;
        font-weight: 800;
        line-height: 1.45;
        color: var(--oc-text);
        overflow-wrap: anywhere;
        text-wrap: balance;
    }

    .oc-card__name--sm { font-size: 0.78rem; }
    .oc-card__name--xs { font-size: 0.72rem; }

    .oc-card__role {
        margin-top: 0.2rem;
        font-size: 0.68rem;
        font-weight: 600;
        color: var(--oc-teal);
        line-height: 1.4;
        text-wrap: balance;
    }

    .oc-card__role--unit {
        color: var(--oc-brand);
        font-weight: 700;
    }

    .oc-card__role--member {
        color: var(--oc-muted);
        font-weight: 600;
    }

    @media (min-width: 1280px) {
        .oc-tree {
            min-width: max(100%, 68rem);
        }
    }

    @media print {
        .oc-shell__hint { display: none; }
        .oc-scroll { overflow: visible; }
        .oc-card:hover { transform: none; }
    }
</style>
