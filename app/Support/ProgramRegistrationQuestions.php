<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Extra questions asked on a normal program registration, one step at a time.
 */
final class ProgramRegistrationQuestions
{
    /**
     * @return list<array{key: string, label: string, type: string, required: bool, placeholder: string, options: list<string>}>
     */
    public static function forTatawaaWaei(): array
    {
        return self::normalize([
            [
                'key' => 'linkedin_url',
                'label' => 'رابط حسابك الحالي على منصة LinkedIn (إن وجد)',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://linkedin.com/in/username',
            ],
            [
                'key' => 'major',
                'label' => 'التخصص الأكاديمي أو المهني الحالي',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'مثال: علوم حاسب، تقنية معلومات',
            ],
            [
                'key' => 'content_experience',
                'label' => 'هل لديك خبرة سابقة في صناعة المحتوى أو الكتابة الرقمية؟',
                'type' => 'choice',
                'required' => true,
                'options' => [
                    'نعم، لدي خبرة جيدة',
                    'مبتدئ؛ ولكن شغوف بالتعلم',
                    'لا توجد خبرة',
                ],
            ],
            [
                'key' => 'ai_knowledge',
                'label' => 'ما مدى إلمامك بأدوات الذكاء الاصطناعي؟',
                'type' => 'choice',
                'required' => true,
                'options' => [
                    'أستخدمها بشكل مستمر',
                    'أستخدمها بشكل محدود',
                    'لم يسبق لي استخدامها',
                ],
            ],
            [
                'key' => 'reason_to_join',
                'label' => 'لماذا ترغب في الانضمام لبرنامج «تطوع واعي»؟ وماذا تتوقع أن يضيف لهويتك المهنية؟',
                'type' => 'textarea',
                'required' => true,
                'placeholder' => 'اكتب إجابتك هنا بوضوح...',
            ],
            [
                'key' => 'commitment_ready',
                'label' => 'البرنامج يتطلب التزاماً مستمراً لإنتاج ومراجعة المحتوى، هل أنت مستعد للالتزام بالمهام التطوعية؟',
                'type' => 'choice',
                'required' => true,
                'options' => [
                    'نعم، ملتزم تماماً',
                    'نعم، حسب تفرغي',
                    'لا',
                ],
            ],
            [
                'key' => 'acknowledgment',
                'label' => 'إقرار بالالتزام: أقر أنا المدون اسمي أدناه بالالتزام التام حتى نهاية البرنامج ديسمبر 2026م. (فضلاً أضف الاسم الثلاثي ثم السجل المدني)',
                'type' => 'pledge',
                'required' => true,
                'placeholder' => 'مثال: الاسم الثلاثي 1234567890',
            ],
        ]);
    }

    /**
     * @return list<array{key: string, label: string, type: string, required: bool, placeholder: string, options: list<string>}>
     */
    public static function normalize(mixed $questions): array
    {
        if (! is_array($questions)) {
            return [];
        }

        $normalized = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $key = trim((string) ($question['key'] ?? ''));
            $label = trim((string) ($question['label'] ?? ''));
            $type = (string) ($question['type'] ?? 'text');

            if ($key === '' || $label === '' || ! preg_match('/^[a-z][a-z0-9_]{0,40}$/', $key)) {
                continue;
            }

            if (! in_array($type, ['text', 'textarea', 'choice', 'pledge'], true)) {
                $type = 'text';
            }

            $options = [];
            if ($type === 'choice') {
                foreach ((array) ($question['options'] ?? []) as $option) {
                    $option = trim((string) $option);
                    if ($option !== '') {
                        $options[] = $option;
                    }
                }
                if ($options === []) {
                    continue;
                }
            }

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => (bool) ($question['required'] ?? false),
                'placeholder' => trim((string) ($question['placeholder'] ?? '')),
                'options' => $options,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{key: string, label: string, type: string, required: bool, placeholder: string, options: list<string>}>  $questions
     * @return array<string, list<mixed>>
     */
    public static function rules(array $questions): array
    {
        $rules = [];

        foreach ($questions as $question) {
            $field = $question['required'] ? ['required'] : ['nullable'];
            $field[] = 'string';
            $field[] = 'max:2000';

            if ($question['type'] === 'choice') {
                $field[] = Rule::in($question['options']);
            }

            if ($question['type'] === 'pledge') {
                $field[] = 'regex:/^\s*\S+(?:\s+\S+){2,}\s+\d{10}\s*$/u';
            }

            if ($question['key'] === 'linkedin_url') {
                $field[] = 'url';
            }

            $rules['answers.'.$question['key']] = $field;
        }

        return $rules;
    }

    /**
     * @param  list<array{key: string, label: string, type: string, required: bool, placeholder: string, options: list<string>}>  $questions
     * @return array<string, string>
     */
    public static function messages(array $questions): array
    {
        $messages = [];

        foreach ($questions as $question) {
            $key = 'answers.'.$question['key'];
            $messages[$key.'.required'] = 'أجب عن هذا السؤال قبل المتابعة.';
            $messages[$key.'.in'] = 'اختر إحدى الإجابات المتاحة.';
            $messages[$key.'.url'] = 'أدخل رابطاً صحيحاً، أو اترك الحقل فارغاً.';
            $messages[$key.'.regex'] = 'اكتب الاسم الثلاثي ثم السجل المدني المكوّن من 10 أرقام.';
        }

        return $messages;
    }

    /**
     * @param  list<array{key: string, label: string, type: string, required: bool, placeholder: string, options: list<string>}>  $questions
     * @return array<string, string>
     */
    public static function answersFrom(Request $request, array $questions): array
    {
        $answers = [];

        foreach ($questions as $question) {
            $value = trim((string) $request->input('answers.'.$question['key'], ''));
            if ($value === '') {
                continue;
            }
            $answers[$question['key']] = preg_replace('/\s+/u', ' ', $value) ?? $value;
        }

        return $answers;
    }

    /**
     * @param  list<array{key: string, label: string, type: string, required: bool, placeholder: string, options: list<string>}>  $questions
     * @param  array<string, mixed>|null  $answers
     * @return list<array{label: string, value: string}>
     */
    public static function present(array $questions, ?array $answers): array
    {
        $answers ??= [];
        $rows = [];

        foreach ($questions as $question) {
            $value = trim((string) ($answers[$question['key']] ?? ''));
            if ($value === '') {
                continue;
            }
            $rows[] = [
                'label' => $question['label'],
                'value' => $value,
            ];
        }

        foreach ($answers as $key => $value) {
            if (collect($questions)->contains(fn (array $question): bool => $question['key'] === (string) $key)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $rows[] = [
                'label' => (string) $key,
                'value' => $value,
            ];
        }

        return $rows;
    }
}
