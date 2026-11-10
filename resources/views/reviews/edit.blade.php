<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            レビューを編集
        </h2>
    </x-slot>

    <div class="px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="mx-auto max-w-3xl">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-gray-50 px-6 py-5 sm:px-10">
                    <p class="text-sm font-medium text-gray-500">レビューする書籍</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ $review->book->title }}</p>
                </div>

                <form action="{{ route('reviews.update', $review) }}" method="POST" class="px-6 py-7 sm:px-10 sm:py-10" novalidate>
                    @csrf
                    @method('PUT')

                    <fieldset>
                        <legend class="text-sm font-semibold text-gray-700">
                            評価 <span class="text-red-500" aria-hidden="true">*</span>
                        </legend>
                        <p class="mt-1 text-sm text-gray-500">星を選択してください</p>

                        <div class="review-rating mt-3" aria-label="5段階評価">
                            @for($i = 5; $i >= 1; $i--)
                                <input
                                    type="radio"
                                    name="rating"
                                    id="rating-{{ $i }}"
                                    value="{{ $i }}"
                                    @checked((int) old('rating', $review->rating) === $i)
                                    required
                                >
                                <label for="rating-{{ $i }}" title="{{ $i }}点" aria-label="{{ $i }}点">★</label>
                            @endfor
                        </div>

                        @error('rating')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="mt-8">
                        <label for="comment" class="block text-sm font-semibold text-gray-700">
                            コメント <span class="text-red-500" aria-hidden="true">*</span>
                        </label>
                        <textarea
                            name="comment"
                            id="comment"
                            rows="6"
                            class="mt-2 block w-full resize-y rounded-lg border-gray-300 px-4 py-3 text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500"
                            placeholder="この書籍を読んだ感想を入力してください"
                            required
                        >{{ old('comment', $review->comment) }}</textarea>
                        @error('comment')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-10 flex flex-col-reverse gap-3 border-t border-gray-200 pt-6 sm:flex-row sm:justify-end">
                        <a
                            href="{{ route('books.show', $review->book) }}"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 sm:w-32"
                        >
                            キャンセル
                        </a>
                        <button
                            type="submit"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-blue-600 px-7 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-36"
                        >
                            更新する
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
