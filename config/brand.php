<?php

return [

    'primary' => '#335483',
    'primary_dark' => '#243a55',
    'primary_light' => '#e9eff6',
    'primary_border' => '#c5d4e4',

    'secondary' => '#1a9399',
    'accent' => '#fbbb2e',
    'danger' => '#ec6056',
    'sanad' => '#4f53a3',

    'secondary_light' => '#e6f5f6',
    'secondary_border' => '#b8e0e2',
    'danger_light' => '#fdeeed',
    'danger_border' => '#f5c4c0',
    'accent_light' => '#fef6e6',
    'accent_border' => '#f5dfa8',
    'sanad_light' => '#ededf7',
    'sanad_border' => '#c8cae8',

    'font' => 'FF Shamel',
    'font_path' => 'fonts/shamel',

    'classes' => [
        'alert_success' => 'rounded-xl border border-[#b8e0e2] bg-[#e6f5f6] text-brand-secondary px-4 py-3 text-sm',
        'alert_danger' => 'rounded-xl border border-[#f5c4c0] bg-[#fdeeed] text-brand-danger px-4 py-3 text-sm',
        'btn_primary' => 'inline-flex items-center justify-center rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:opacity-95',
        'btn_secondary' => 'inline-flex items-center justify-center rounded-xl bg-brand-secondary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-95',
        'badge_secondary' => 'bg-[#e6f5f6] text-brand-secondary ring-1 ring-[#b8e0e2]',
        'badge_primary' => 'bg-brand-light text-brand ring-1 ring-brand-border',
        'badge_accent' => 'bg-[#fef6e6] text-brand ring-1 ring-[#f5dfa8]',
        'badge_danger' => 'bg-[#fdeeed] text-brand-danger ring-1 ring-[#f5c4c0]',
        'badge_sanad' => 'bg-[#ededf7] text-brand-sanad ring-1 ring-[#c8cae8]',
        'tone_primary' => 'bg-brand-light text-brand ring-1 ring-brand-border',
        'tone_secondary' => 'bg-[#e6f5f6] text-brand-secondary ring-1 ring-[#b8e0e2]',
        'tone_accent' => 'bg-[#fef6e6] text-brand ring-1 ring-[#f5dfa8]',
        'tone_muted' => 'bg-gray-100 text-gray-700 ring-1 ring-gray-200',
    ],

    'news_categories' => [
        'إطلاق' => 'class="bg-[#e9eff6] text-[#335483] ring-1 ring-[#c5d4e4]"',
        'ورشة عمل' => 'class="bg-[#e6f5f6] text-[#1a9399] ring-1 ring-[#b8e0e2]"',
        'شراكة' => 'class="bg-[#fef6e6] text-[#335483] ring-1 ring-[#f5dfa8]"',
        'برامج' => 'class="bg-[#dce8f5] text-[#335483] ring-1 ring-[#c5d4e4]"',
        'تقارير' => 'class="bg-[#d4f0f2] text-[#1a9399] ring-1 ring-[#b8e0e2]"',
        'فعالية' => 'class="bg-[#fdeeed] text-[#ec6056] ring-1 ring-[#f5c4c0]"',
        'أخرى' => 'class="bg-[#F3F7FB] text-[#6B7280] ring-1 ring-gray-200"',
    ],

    'image_gradients' => [
        'linear-gradient(135deg, #e9eff6, #dce8f5)',
        'linear-gradient(135deg, #e6f5f6, #c5e8ea)',
        'linear-gradient(135deg, #fef6e6, #f5dfa8)',
        'linear-gradient(135deg, #e9eff6, #c5d4e4)',
        'linear-gradient(135deg, #fdeeed, #f5c4c0)',
    ],

    'logos' => [
        'kafaat' => 'images/brand/kafaat-logo.svg',
        'kafaat_mark' => 'images/brand/kafaat-mark.svg',
        'kafaat_white' => 'images/brand/kafaat-logo-white.svg',
        // Raster mark — SVG is unreliable in most email clients.
        'kafaat_mail' => 'images/brand/kafaat-logo-mail.png',
    ],

];
