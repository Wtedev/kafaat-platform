<?php

namespace Database\Seeders;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class VolunteerOpportunitySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first()
            ?? User::where('email', 'admin@kafaat.test')->first();

        $opportunities = [
            [
                'title'          => 'مساعد إداري لملتقى كفاءات',
                'description'    => 'ساهم في تنظيم وإدارة الملتقى السنوي لمنصة كفاءات من خلال دعم فرق العمل الإدارية والتنسيق بين المشاركين.',
                'capacity'       => 15,
                'hours_expected' => 20,
                'start_date'     => Carbon::parse('2026-06-01'),
                'end_date'       => Carbon::parse('2026-06-15'),
            ],
            [
                'title'          => 'مدرّب أساسيات الحاسوب للمجتمع',
                'description'    => 'تدريب أفراد المجتمع على أساسيات استخدام الحاسوب والإنترنت في مراكز التعليم المجتمعي المحلية.',
                'capacity'       => 10,
                'hours_expected' => 30,
                'start_date'     => Carbon::parse('2026-06-10'),
                'end_date'       => Carbon::parse('2026-07-10'),
            ],
            [
                'title'          => 'مشرف معسكر التطوير الشخصي',
                'description'    => 'الإشراف على تنظيم معسكر التطوير الشخصي للطلاب الجامعيين وقيادة جلسات الأنشطة والورش.',
                'capacity'       => 20,
                'hours_expected' => 40,
                'start_date'     => Carbon::parse('2026-07-01'),
                'end_date'       => Carbon::parse('2026-07-05'),
            ],
            [
                'title'          => 'مرشد طلابي في رحلات التوجيه المهني',
                'description'    => 'مرافقة مجموعات من الطلاب خلال جولات التوجيه المهني في الشركات والمؤسسات وتيسير نقاشات الإرشاد.',
                'capacity'       => 8,
                'hours_expected' => 16,
                'start_date'     => Carbon::parse('2026-06-20'),
                'end_date'       => Carbon::parse('2026-08-20'),
            ],
            [
                'title'          => 'متطوع في حملات التوعية المجتمعية',
                'description'    => 'المشاركة في حملات التوعية بأهمية التعلم المستمر والتطوير المهني عبر المجمعات والفعاليات المجتمعية.',
                'capacity'       => null,
                'hours_expected' => 10,
                'start_date'     => Carbon::parse('2026-05-15'),
                'end_date'       => Carbon::parse('2026-09-15'),
            ],
            [
                'title'          => 'مساعد تقني لدعم المنصة الرقمية',
                'description'    => 'تقديم الدعم التقني للمستخدمين الجدد على المنصة وإرشادهم لاستخدام أدوات التعلم الإلكتروني.',
                'capacity'       => 12,
                'hours_expected' => 25,
                'start_date'     => Carbon::parse('2026-06-01'),
                'end_date'       => Carbon::parse('2026-12-31'),
            ],
        ];

        foreach ($opportunities as $data) {
            $slug = Str::slug($data['title']);
            if (empty($slug)) {
                $slug = 'opp-' . Str::random(6);
            }

            VolunteerOpportunity::firstOrCreate(
                ['slug' => $slug],
                [
                    'title'          => $data['title'],
                    'description'    => $data['description'],
                    'capacity'       => $data['capacity'],
                    'hours_expected' => $data['hours_expected'],
                    'start_date'     => $data['start_date'],
                    'end_date'       => $data['end_date'],
                    'status'         => OpportunityStatus::Published,
                    'published_at'   => now(),
                    'created_by'     => $admin?->id,
                ]
            );
        }
    }
}
