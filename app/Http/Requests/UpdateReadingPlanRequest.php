<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * Prevent another in-progress plan for the same user and book.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $readingPlan = $this->route('reading_plan');
            $user = $this->user();

            if (! $readingPlan instanceof ReadingPlan || $user === null) {
                return;
            }

            $hasAnotherInProgressPlan = ReadingPlan::query()
                ->where('user_id', $user->id)
                ->where('book_id', $readingPlan->book_id)
                ->where('status', ReadingPlanStatus::InProgress->value)
                ->where('id', '!=', $readingPlan->id)
                ->exists();

            if ($hasAnotherInProgressPlan) {
                $validator->errors()->add(
                    'target_date',
                    'この書籍は既に進行中の読書計画が存在します。'
                );
            }
        });
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
            'book_id.prohibited' => '書籍は変更できません。',
            'status.prohibited' => 'ステータスは変更できません。',
        ];
    }
}
