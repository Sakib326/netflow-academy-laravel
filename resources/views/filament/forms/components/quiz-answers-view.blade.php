@php
    $data = $getAnswersData();
    $answers = $data['answers'];
    $summary = $data['summary'];
@endphp

<div class="space-y-3" x-data="{ showDetails: false }">
    @if (count($answers) > 0)
        {{-- Summary Header --}}
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold text-gray-900">Quiz Results</h4>
                <button type="button" x-on:click="showDetails = !showDetails"
                    class="text-xs bg-white border border-gray-300 rounded px-2 py-1 hover:bg-gray-50">
                    <span x-show="!showDetails">Show Details</span>
                    <span x-show="showDetails">Hide Details</span>
                </button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div class="text-center">
                    <div class="font-medium text-gray-900">
                        {{ $summary['correct_answers'] }}/{{ $summary['total_questions'] }}</div>
                    <div class="text-xs text-gray-500">Correct</div>
                </div>
                <div class="text-center">
                    <div class="font-medium text-gray-900">{{ $summary['total_score'] }}/{{ $summary['max_score'] }}
                    </div>
                    <div class="text-xs text-gray-500">Points</div>
                </div>
                <div class="text-center">
                    <div
                        class="font-medium 
                        {{ $summary['percentage'] >= 90
                            ? 'text-green-600'
                            : ($summary['percentage'] >= 80
                                ? 'text-blue-600'
                                : ($summary['percentage'] >= 70
                                    ? 'text-yellow-600'
                                    : 'text-red-600')) }}">
                        {{ $summary['percentage'] }}%
                    </div>
                    <div class="text-xs text-gray-500">Score</div>
                </div>
                <div class="text-center">
                    <div
                        class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                        {{ $summary['percentage'] >= 90
                            ? 'bg-green-100 text-green-800'
                            : ($summary['percentage'] >= 80
                                ? 'bg-blue-100 text-blue-800'
                                : ($summary['percentage'] >= 70
                                    ? 'bg-yellow-100 text-yellow-800'
                                    : 'bg-red-100 text-red-800')) }}">
                        @if ($summary['percentage'] >= 90)
                            Excellent
                        @elseif($summary['percentage'] >= 80)
                            Good
                        @elseif($summary['percentage'] >= 70)
                            Fair
                        @elseif($summary['percentage'] >= 60)
                            Poor
                        @else
                            Fail
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Detailed Results --}}
        <div x-show="showDetails" x-collapse class="space-y-2">
            @foreach ($answers as $index => $answer)
                <div
                    class="border rounded-lg p-3 text-sm
                    {{ $answer['is_correct'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">

                    <div class="flex items-start gap-2">
                        <div
                            class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium
                            {{ $answer['is_correct'] ? 'bg-green-500 text-white' : 'bg-red-500 text-white' }}">
                            {{ $index + 1 }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 mb-2">{{ $answer['question'] }}</p>

                            <div class="space-y-1 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-600 w-20">Student:</span>
                                    <span class="{{ $answer['is_correct'] ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $answer['user_answer'] }}
                                    </span>
                                    @if ($answer['is_correct'])
                                        <span class="text-green-600">✓</span>
                                    @else
                                        <span class="text-red-600">✗</span>
                                    @endif
                                </div>

                                @if (!$answer['is_correct'])
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-600 w-20">Correct:</span>
                                        <span class="text-green-700">{{ $answer['correct_answer'] }}</span>
                                        <span class="text-green-600">✓</span>
                                    </div>
                                @endif

                                <div class="flex items-center gap-2">
                                    <span class="text-gray-600 w-20">Points:</span>
                                    <span
                                        class="font-medium">{{ $answer['earned_marks'] }}/{{ $answer['marks'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
            <div class="text-gray-500 text-sm">
                <div class="mb-1">No quiz answers found</div>
                <div class="text-xs">This submission may not contain quiz data or the quiz structure has changed.</div>
            </div>
        </div>
    @endif
</div>
