<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reading plan created by a user for a book.
 *
 * @property int $id
 * @property int $user_id
 * @property int $book_id
 * @property Carbon $target_date
 * @property ReadingPlanStatus $status
 * @property Carbon|null $completed_at
 */
class ReadingPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * user_id, status and completed_at are controlled by application logic.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'book_id',
        'target_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'target_date' => 'date',
        'completed_at' => 'datetime',
        'status' => ReadingPlanStatus::class,
    ];

    /**
     * Get the user who owns the reading plan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the book associated with the reading plan.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
