<?php

namespace App\Exceptions;

use RuntimeException;

class RegistrationNotEligibleException extends RuntimeException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly array $reasons = [],
    ) {
        $message = $reasons !== []
            ? implode(' ', $reasons)
            : 'أنت غير مؤهل للتسجيل في هذا البرنامج.';

        parent::__construct($message);
    }

    public function userMessage(): string
    {
        if ($this->reasons === []) {
            return 'غير مؤهل للتسجيل في هذا البرنامج.';
        }

        if (count($this->reasons) === 1) {
            return $this->reasons[0];
        }

        return "غير مؤهل للتسجيل:\n• ".implode("\n• ", $this->reasons);
    }
}
