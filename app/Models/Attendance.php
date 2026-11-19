<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'check_in',
        'check_out',
        'delay_minutes',
        'overtime_minutes',
        'status',
        'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'delay_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'is_manual' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
