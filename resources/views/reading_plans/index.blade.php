<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            読書計画
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('reading-plans.index') }}" class="flex items-center space-x-2">
                    <label for="status" class="text-sm text-gray-700">状態:</label>
                    <select name="status" id="status" onchange="this.form.submit()" class="w-full sm:w-auto rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">すべて</option>
                        <option value="{{ \App\Enums\ReadingPlanStatus::InProgress->value }}" @selected($currentStatus === \App\Enums\ReadingPlanStatus::InProgress->value)>進行中</option>
                        <option value="{{ \App\Enums\ReadingPlanStatus::Completed->value }}" @selected($currentStatus === \App\Enums\ReadingPlanStatus::Completed->value)>完了</option>
                        <option value="{{ \App\Enums\ReadingPlanStatus::Expired->value }}" @selected($currentStatus === \App\Enums\ReadingPlanStatus::Expired->value)>期限切れ</option>
                    </select>
                </form>

                <a href="{{ route('reading-plans.create') }}" class="self-start sm:self-auto bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    新規計画作成
                </a>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($readingPlans->isEmpty())
                        <p class="text-gray-500">該当する読書計画はありません。</p>
                    @else
                        <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">書籍</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">期日</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">完了日</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状態</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($readingPlans as $plan)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('books.show', $plan->book) }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $plan->book->title }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $plan->target_date->format('Y-m-d') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $plan->completed_at?->format('Y-m-d') ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @switch($plan->status)
                                                @case(\App\Enums\ReadingPlanStatus::InProgress)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">進行中</span>
                                                    @break
                                                @case(\App\Enums\ReadingPlanStatus::Completed)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">完了</span>
                                                    @break
                                                @case(\App\Enums\ReadingPlanStatus::Expired)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">期限切れ</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                            @if($plan->status !== \App\Enums\ReadingPlanStatus::Completed)
                                                <form action="{{ route('reading-plans.complete', $plan) }}" method="POST" class="inline" novalidate>
                                                    @csrf
                                                    <button type="submit" class="text-green-600 hover:text-green-900">読了する</button>
                                                </form>
                                                <a href="{{ route('reading-plans.edit', $plan) }}" class="text-indigo-600 hover:text-indigo-900">編集</a>
                                            @endif

                                            <form action="{{ route('reading-plans.destroy', $plan) }}" method="POST" class="inline" onsubmit="return confirm('本当に削除しますか？');" novalidate>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">削除</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
