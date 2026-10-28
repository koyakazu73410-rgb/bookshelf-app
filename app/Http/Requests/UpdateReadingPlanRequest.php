<?php

namespace App\Http\Requests;

use App\Models\ReadingPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingPlanRequest extends FormRequest
{
    /**
     * Determine if the authenticated user may update the reading plan.
     */
    public function authorize(): bool
    {
        $readingPlan = $this->route('reading_plan');

        return $readingPlan instanceof ReadingPlan
            && $this->user()?->can('update', $readingPlan) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_date' => ['required', 'date', 'after_or_equal:today'],
            'book_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_date.required' => '目標日を入力してください。',
            'target_date.date' => '目標日は正しい日付で入力してください。',
            'target_date.after_or_equal' => '目標日は本日以降の日付を入力してください。',
            'book_id.prohibited' => '書籍は変更できません。',
            'status.prohibited' => 'ステータスは変更できません。',
        ];
    }
}
