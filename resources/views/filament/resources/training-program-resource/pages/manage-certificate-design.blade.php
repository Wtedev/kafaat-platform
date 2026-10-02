<x-filament-panels::page>
    @if ($approvalErrors !== [])
        <div class="certificate-designer">
            <ul class="cd-errors">
                @foreach ($approvalErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @error('backgroundUpload')
        <div class="certificate-designer"><p class="cd-errors">{{ $message }}</p></div>
    @enderror
    @error('elementImage')
        <div class="certificate-designer"><p class="cd-errors">{{ $message }}</p></div>
    @enderror

    @if ($showRegeneratePrompt)
        <div class="certificate-designer">
            <div class="cd-modal">
                <div class="cd-modal-card">
                    <p>يوجد {{ $olderCertificateCount }} شهادة صادرة بالتصميم السابق، هل تريد إعادة توليدها بالتصميم الجديد؟</p>
                    <div class="cd-actions">
                        <button type="button" class="cd-btn cd-btn-primary" wire:click="confirmRegeneration(true)">نعم</button>
                        <button type="button" class="cd-btn" wire:click="confirmRegeneration(false)">لاحقاً</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <input id="certificate-background-upload" class="cd-hidden-file" type="file" accept="image/png,image/jpeg" wire:model="backgroundUpload">

    <div
        class="certificate-designer"
        wire:ignore
        x-data="certificateDesigner(@js($designer))"
        x-on:certificate-background-updated.window="onBackground($event.detail)"
        x-on:certificate-design-approved.window="onApproved($event.detail)"
        x-on:certificate-preview-ready.window="window.open($event.detail.url, '_blank')"
    >
        <style>
            @font-face {
                font-family: "IBM Plex Sans Arabic";
                src: url("{{ route('certificate-fonts.show', ['font' => 'IBMPlexSansArabic-Regular.ttf']) }}") format("truetype");
                font-weight: 400;
                font-display: swap;
            }
            @font-face {
                font-family: "IBM Plex Sans Arabic";
                src: url("{{ route('certificate-fonts.show', ['font' => 'IBMPlexSansArabic-Bold.ttf']) }}") format("truetype");
                font-weight: 700;
                font-display: swap;
            }
        </style>

        <div class="cd-top">
            <div class="cd-title">
                <strong x-text="programName"></strong>
                <span class="cd-status">حالة القالب: <b x-text="statusLabel"></b></span>
            </div>
            <div class="cd-actions">
                <label class="cd-btn" for="certificate-background-upload">رفع الخلفية</label>
                <button type="button" class="cd-btn" x-on:click="preview()">معاينة PDF</button>
                <button type="button" class="cd-btn" x-on:click="saveDraft()">حفظ كمسودة</button>
                <button type="button" class="cd-btn cd-btn-primary" x-on:click="approve()">اعتماد التصميم</button>
            </div>
            <p class="cd-hint">يفضّل 3508 × 2480 بكسل (A4 أفقي بدقة 300dpi). PNG أو JPG حتى 10 ميغابايت.</p>
        </div>

        <div class="cd-layout">
            <aside class="cd-side">
                <section>
                    <h3>الحقول</h3>
                    <template x-for="field in fields" :key="field.key">
                        <div class="cd-field-row">
                            <span x-text="field.label"></span>
                            <button type="button" class="cd-btn" x-on:click="addField(field.key)">إضافة</button>
                        </div>
                    </template>
                    <div class="cd-field-row">
                        <span>نص ثابت</span>
                        <button type="button" class="cd-btn" x-on:click="addText()">إضافة</button>
                    </div>
                    <div class="cd-field-row">
                        <span>رمز QR للتحقق</span>
                        <button type="button" class="cd-btn" x-on:click="addQr()">إضافة</button>
                    </div>
                    <div class="cd-field-row">
                        <span>صورة (توقيع/ختم)</span>
                        <button type="button" class="cd-btn" x-on:click="addImage()">إضافة</button>
                    </div>
                </section>

                <section x-show="selectedElement" x-cloak>
                    <h3>خصائص العنصر المحدد</h3>
                    <label class="cd-prop">س (مم)<input type="number" step="0.1" :value="mm('x')" x-on:change="setMm('x', $event.target.value)"></label>
                    <label class="cd-prop">ص (مم)<input type="number" step="0.1" :value="mm('y')" x-on:change="setMm('y', $event.target.value)"></label>
                    <label class="cd-prop">العرض (مم)<input type="number" step="0.1" :value="mm('width')" x-on:change="setMm('width', $event.target.value)"></label>
                    <label class="cd-prop">الارتفاع (مم)<input type="number" step="0.1" :value="mm('height')" x-on:change="setMm('height', $event.target.value)"></label>

                    <template x-if="selectedElement && (selectedElement.type === 'field' || selectedElement.type === 'text')">
                        <div>
                            <label class="cd-prop">الخط
                                <select x-model="selectedElement.font_family" x-on:change="markDirty()">
                                    <template x-for="font in fonts" :key="font">
                                        <option :value="font" x-text="font"></option>
                                    </template>
                                </select>
                            </label>
                            <label class="cd-prop">الحجم pt<input type="number" step="0.5" min="1" x-model.number="selectedElement.font_size_pt" x-on:change="markDirty()"></label>
                            <label class="cd-prop">الوزن
                                <select x-model="selectedElement.font_weight" x-on:change="markDirty()">
                                    <option value="regular">عادي</option>
                                    <option value="bold">عريض</option>
                                </select>
                            </label>
                            <label class="cd-prop">اللون<input type="color" x-model="selectedElement.color" x-on:change="markDirty()"></label>
                            <div class="cd-prop">المحاذاة
                                <div class="cd-align">
                                    <button type="button" class="cd-btn" x-on:click="selectedElement.align = 'right'; markDirty()">يمين</button>
                                    <button type="button" class="cd-btn" x-on:click="selectedElement.align = 'center'; markDirty()">وسط</button>
                                    <button type="button" class="cd-btn" x-on:click="selectedElement.align = 'left'; markDirty()">يسار</button>
                                </div>
                            </div>
                            <template x-if="selectedElement.type === 'field'">
                                <div>
                                    <label class="cd-prop">نص قبل<input type="text" x-model="selectedElement.prefix" x-on:input="markDirty()"></label>
                                    <label class="cd-prop">نص بعد<input type="text" x-model="selectedElement.suffix" x-on:input="markDirty()"></label>
                                </div>
                            </template>
                            <template x-if="selectedElement.type === 'text'">
                                <label class="cd-prop">النص<input type="text" x-model="selectedElement.text" x-on:input="markDirty()"></label>
                            </template>
                            <label class="cd-prop">التصغير التلقائي
                                <input type="checkbox" x-model="selectedElement.auto_shrink" x-on:change="markDirty()">
                            </label>
                            <label class="cd-prop" x-show="selectedElement.auto_shrink">الحد الأدنى للخط
                                <input type="number" step="0.5" min="1" x-model.number="selectedElement.min_font_size_pt" x-on:change="markDirty()">
                            </label>
                        </div>
                    </template>

                    <template x-if="selectedElement && selectedElement.type === 'image'">
                        <label class="cd-prop">ملف الصورة
                            <input type="file" accept="image/png,image/jpeg" x-on:change="uploadImage($event)">
                        </label>
                    </template>

                    <button type="button" class="cd-btn cd-btn-danger" x-on:click="removeSelected()">حذف</button>
                </section>

                <section x-on:change="markDirty()">
                    <h3>شروط الأحقية</h3>
                    <label class="cd-rule">النمط
                        <select x-model="eligibility.mode" x-on:change="onModeChange()">
                            <template x-for="mode in modes" :key="mode.value">
                                <option :value="mode.value" x-text="mode.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="cd-rule" x-show="eligibility.mode === 'attendance_only' || eligibility.mode === 'both'">
                        يُشترط حضور لا يقل عن
                        <input type="number" min="0" max="100" step="0.01" x-model="eligibility.min_attendance">
                        %
                    </label>
                    <label class="cd-rule" x-show="eligibility.mode === 'score_only' || eligibility.mode === 'both'">
                        يُشترط درجة لا تقل عن
                        <input type="number" min="0" max="100" step="0.01" x-model="eligibility.min_score">
                    </label>
                    <label class="cd-rule" x-show="eligibility.mode === 'average'">
                        يُشترط متوسط لا يقل عن
                        <input type="number" min="0" max="100" step="0.01" x-model="eligibility.min_average">
                        %
                    </label>
                    <label class="cd-rule" x-show="eligibility.mode === 'min_approved_hours'">
                        يُشترط ساعات معتمدة لا تقل عن
                        <input type="number" min="0" step="0.01" x-model="eligibility.min_approved_hours">
                    </label>
                    <label class="cd-rule">
                        إصدار تلقائي عند تحقق الشروط
                        <input type="checkbox" x-model="eligibility.auto_issue">
                    </label>
                    <label class="cd-rule">
                        التسجيل مقبول أو مكتمل
                        <input type="checkbox" x-model="eligibility.require_completed_status">
                    </label>
                    <label class="cd-rule">
                        بعد انتهاء النشاط
                        <input type="checkbox" x-model="eligibility.require_activity_ended">
                    </label>
                    <p class="cd-summary" x-text="summary()"></p>
                </section>

                <section>
                    <h3>نسخ التصميم من نشاط آخر</h3>
                    <select x-model="copySourceId">
                        <option value="">اختر نشاطاً</option>
                        <template x-for="program in copyOptions" :key="program.id">
                            <option :value="program.id" x-text="program.title"></option>
                        </template>
                    </select>
                    <button type="button" class="cd-btn" x-on:click="copy()">نسخ الخلفية والعناصر</button>
                </section>
            </aside>

            <div class="cd-workspace">
                <div class="cd-tools">
                    <label><input type="checkbox" x-model="showGrid"> شبكة</label>
                    <label><input type="checkbox" x-model="showRuler"> مسطرة</label>
                    <label>تكبير
                        <input type="range" min="0.5" max="2" step="0.1" x-model.number="zoom">
                    </label>
                    <label>
                        <input type="checkbox" x-model="useRealValues" x-on:change="toggleReal()">
                        عرض بقيم حقيقية
                    </label>
                    <select x-show="useRealValues" x-model="registrationId" x-on:change="loadReal()">
                        <option value="">اختر مستفيداً</option>
                        <template x-for="person in registrations" :key="person.id">
                            <option :value="person.id" x-text="person.name"></option>
                        </template>
                    </select>
                </div>
                <div class="cd-stage">
                    <div class="cd-page" x-ref="page" :class="{ 'cd-grid': showGrid }" :style="pageStyle()" tabindex="0">
                        <template x-if="backgroundUrl">
                            <img class="cd-bg" :src="backgroundUrl" alt="">
                        </template>
                        <template x-if="showRuler">
                            <div class="cd-ruler"></div>
                        </template>
                        <template x-for="element in elements" :key="element.id">
                            <div
                                class="cd-el"
                                :class="{ 'is-selected': element.id === selectedId }"
                                :style="boxStyle(element)"
                                x-on:pointerdown="startDrag($event, element)"
                            >
                                <template x-if="element.type === 'image' && imageUrls[element.image_path]">
                                    <img :src="imageUrls[element.image_path]" alt="" style="width:100%;height:100%;object-fit:contain;pointer-events:none;">
                                </template>
                                <template x-if="element.type === 'qr'">
                                    <span style="font-size:12px;text-align:center;">QR</span>
                                </template>
                                <template x-if="element.type === 'field' || element.type === 'text'">
                                    <span :style="textStyleCss(element)" x-text="label(element)"></span>
                                </template>
                                <template x-if="element.id === selectedId">
                                    <div>
                                        <i class="cd-handle nw" x-on:pointerdown="startResize($event, element, 'nw')"></i>
                                        <i class="cd-handle ne" x-on:pointerdown="startResize($event, element, 'ne')"></i>
                                        <i class="cd-handle sw" x-on:pointerdown="startResize($event, element, 'sw')"></i>
                                        <i class="cd-handle se" x-on:pointerdown="startResize($event, element, 'se')"></i>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div class="cd-guide-v" x-show="guideX !== null" :style="`left:${guideX}%`"></div>
                        <div class="cd-guide-h" x-show="guideY !== null" :style="`top:${guideY}%`"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="{{ asset('css/certificate-designer.css') }}?v=1">
    <script src="{{ asset('js/filament/certificate-designer.js') }}?v=1"></script>
</x-filament-panels::page>
