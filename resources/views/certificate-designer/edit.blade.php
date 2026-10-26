@extends('layouts.certificate-designer')

@section('title', 'تصميم شهادة '.$program->title)

@section('content')
    <div class="certificate-designer" id="certificate-designer">
        <header class="certificate-designer__bar">
            <div class="certificate-designer__title">
                <strong>{{ $program->title }}</strong>
                <span>تصميم الشهادة</span>
            </div>
            <p class="certificate-designer__status" id="certificate-designer-status" role="status"></p>
            <div class="certificate-designer__actions">
                <a class="sui-btn sui-btn--ghost sui-btn--md" href="{{ $backUrl }}">رجوع للبرنامج</a>
                <button class="sui-btn sui-btn--secondary sui-btn--md" type="button" id="certificate-designer-preview">معاينة PDF</button>
                <button class="sui-btn sui-btn--primary sui-btn--md" type="button" id="certificate-designer-save">حفظ</button>
            </div>
        </header>

        <div class="certificate-designer__workspace">
            <aside class="certificate-designer__panel">
                <h2>الحقول</h2>
                <ul class="certificate-designer__fields" id="certificate-designer-fields">
                    @foreach ($designer['fields'] as $field)
                        <li>
                            <label>
                                <input type="checkbox" data-field="{{ $field['key'] }}" @checked(isset($designer['elements'][$field['key']]))>
                                {{ $field['label'] }}
                            </label>
                        </li>
                    @endforeach
                </ul>
                <label class="certificate-designer__hours" for="certificate-designer-hours">عدد الساعات</label>
                <input id="certificate-designer-hours" type="number" min="0" max="9999.99" step="0.01" inputmode="decimal">
                <label class="certificate-designer__upload" for="certificate-designer-background">خلفية التصميم</label>
                <input id="certificate-designer-background" type="file" accept="image/png,image/jpeg">
                <p class="certificate-designer__note">مقاس A4 أفقي (297×210mm). PNG أو JPG، بحد أقصى 5MB.</p>
            </aside>

            <main class="certificate-designer__stage">
                <div class="certificate-canvas" id="certificate-canvas">
                    <div class="certificate-guide certificate-guide--y" id="certificate-guide-y" hidden></div>
                    <div class="certificate-guide certificate-guide--x" id="certificate-guide-x" hidden></div>
                </div>
            </main>

            <aside class="certificate-designer__panel" id="certificate-designer-settings">
                <h2>إعدادات العنصر</h2>
                <p class="certificate-designer__empty" id="certificate-designer-empty">اختر عنصراً من الشهادة لتعديل خطه وموضعه.</p>
                <div id="certificate-designer-settings-form" hidden>
                    <label class="is-text-only" for="certificate-font">الخط</label>
                    <select class="is-text-only" id="certificate-font">
                        <option value="shamel">شامل</option>
                        <option value="ibmplex">IBM Plex Arabic</option>
                    </select>
                    <label class="is-text-only" for="certificate-weight">الوزن</label>
                    <select class="is-text-only" id="certificate-weight">
                        <option value="regular">عادي</option>
                        <option value="bold">عريض</option>
                    </select>
                    <label for="certificate-size">الحجم</label>
                    <input id="certificate-size" type="number" min="8" max="96" step="1">
                    <p class="certificate-designer__unit" id="certificate-size-unit">نقطة</p>
                    <label class="is-text-only" for="certificate-color">اللون</label>
                    <div class="certificate-designer__color is-text-only">
                        <input id="certificate-color" type="color" value="#1a1a1a">
                        <input id="certificate-color-hex" type="text" maxlength="7" spellcheck="false" dir="ltr">
                    </div>
                    <p class="is-text-only" id="certificate-align-label">المحاذاة</p>
                    <div class="certificate-designer__align is-text-only" id="certificate-align">
                        <button type="button" data-align="right">يمين</button>
                        <button type="button" data-align="center">وسط</button>
                        <button type="button" data-align="left">يسار</button>
                    </div>
                    <label class="is-text-only" for="certificate-width">العرض (%)</label>
                    <input class="is-text-only" id="certificate-width" type="number" min="1" max="100" step="1">
                </div>
            </aside>
        </div>
    </div>
    <script type="application/json" id="certificate-designer-data">@json($designer)</script>
@endsection
