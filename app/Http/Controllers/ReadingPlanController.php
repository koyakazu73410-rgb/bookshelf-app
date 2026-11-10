<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * Display the authenticated user's reading plans.
     */
    public function index(Request $request): View
    {
        $query = $request->user()
            ->readingPlans()
            ->with('book')
            ->latest();

        $currentStatus = $request->string('status')->toString();

        $status = ReadingPlanStatus::tryFrom($currentStatus);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $readingPlans = $query->get();

        return view('reading_plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * Display the reading plan creation form.
     */
    public function create(): View
    {
        $books = Book::orderBy('title')->get();

        return view('reading_plans.create', compact('books'));
    }

    /**
     * Store a reading plan owned by the authenticated user.
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $readingPlan = $request->user()
            ->readingPlans()
            ->make($request->validated());

        $readingPlan->status = ReadingPlanStatus::InProgress;
        $readingPlan->completed_at = null;
        $readingPlan->save();

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を登録しました。');
    }

    /**
     * Display the reading plan editing form.
     */
    public function edit(ReadingPlan $reading_plan): View
    {
        $this->authorize('update', $reading_plan);

        return view('reading_plans.edit', ['readingPlan' => $reading_plan]);
    }

    /**
     * Update the target date of the reading plan.
     */
    public function update(
        UpdateReadingPlanRequest $request,
        ReadingPlan $reading_plan
    ): RedirectResponse {
        $reading_plan->target_date = $request->validated('target_date');

        if ($reading_plan->status === ReadingPlanStatus::Expired) {
            $reading_plan->status = ReadingPlanStatus::InProgress;
            $reading_plan->completed_at = null;
        }

        $reading_plan->save();

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * Delete the reading plan.
     */
    public function destroy(ReadingPlan $reading_plan): RedirectResponse
    {
        $this->authorize('delete', $reading_plan);

        DB::transaction(function () use ($reading_plan): void {
            $reading_plan->delete();
        });

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * Mark the reading plan as completed.
     */
    public function complete(ReadingPlan $reading_plan): RedirectResponse
    {
        $this->authorize('complete', $reading_plan);

        $reading_plan->status = ReadingPlanStatus::Completed;
        $reading_plan->completed_at = now();
        $reading_plan->save();

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を読了にしました。');
    }
}
