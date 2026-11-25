<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReadingPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
            'status' => ['prohibited'],
        ];
    }

    /**
     * Add validation for an existing in-progress plan owned by the user.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user === null || $validator->errors()->has('book_id')) {
                return;
            }

            $hasInProgressPlan = ReadingPlan::query()
                ->where('user_id', $user->id)
                ->where('book_id', $this->integer('book_id'))
                ->where('status', ReadingPlanStatus::InProgress->value)
                ->exists();

            if ($hasInProgressPlan) {
                $validator->errors()->add(
                    'book_id',
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
            'book_id.required' => '書籍を選択してください。',
            'book_id.integer' => '書籍の値が正しくありません。',
            'book_id.exists' => '選択された書籍が存在しません。',
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
            'status.prohibited' => 'ステータスは指定できません。',
        ];
    }
}
