<?php

namespace App\Exceptions;

use RuntimeException;

class ProgramBroadcastException extends RuntimeException
{
    public static function emptyAudience(): self
    {
        return new self('لا يوجد مستلمون مطابقون لمعايير الجمهور. لا يمكن الإرسال.');
    }

    public static function notDraft(): self
    {
        return new self('لا يمكن تعديل أو إرسال رسالة بدأت عملية إرسالها مسبقاً.');
    }

    public static function concurrentSend(): self
    {
        return new self('جاري إرسال هذه الرسالة بالفعل أو تم إرسالها. تم منع الإرسال المكرر.');
    }

    public static function cannotRetry(): self
    {
        return new self('لا توجد رسائل فاشلة قابلة لإعادة المحاولة.');
    }

    public static function unauthorized(): self
    {
        return new self('ليس لديك صلاحية تنفيذ هذا الإجراء.');
    }

    public static function immutableContent(): self
    {
        return new self('لا يمكن تعديل موضوع أو محتوى الرسالة بعد بدء الإرسال.');
    }
}
