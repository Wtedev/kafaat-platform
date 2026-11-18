<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل طلب بيوت الخبرة: وصول الطلب، تغيير الحالة، تعليقات الفريق.
 */
class ExperienceHouseRequestEvent extends Model
{
    public const TYPE_CREATED = 'created';

    public const TYPE_STATUS = 'status';

    public const TYPE_COMMENT = 'comment';

    protected $fillable = ['request_id', 'manager_id', 'type', 'from_status', 'to_status', 'body'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ExperienceHouseRequest::class, 'request_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(ExperienceHouseManager::class, 'manager_id');
    }
}
