<div class="space-y-2">
    @if (count($answers) > 0)
        {{-- Summary Header --}}
        <div class="bg-gray-50 rounded-lg p-3 border">
            <div class="flex justify-between items-center text-sm">
                <span class="font-medium">Quiz Results Summary</span>
                <div class="flex gap-3">
                    <span class="text-green-600 font-medium">{{ $total_score }}/{{ $max_score }} pts</span>
                    <span
                        class="px-2 py-1 rounded text-xs font-medium
                        {{ $percentage >= 90
                            ? 'bg-green-100 text-green-800'
                            : ($percentage >= 80
                                ? 'bg-blue-100 text-blue-800'
                                : ($percentage >= 70
                                    ? 'bg-yellow-100 text-yellow-800'
                                    : 'bg-red-100 text-red-800')) }}">
                        {{ $percentage }}%
                    </span>
                </div>
            </div>
        </div>

        {{-- Questions List --}}
        <div class="space-y-2">
            @foreach ($answers as $index => $answer)
                <div
                    class="border rounded-lg p-3 {{ $answer['is_correct'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                    <div class="flex items-start gap-2">
                        <span class="text-lg {{ $answer['is_correct'] ? 'text-green-600' : 'text-red-600' }}">
                            {{ $answer['is_correct'] ? '✓' : '✗' }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 mb-2">
                                Q{{ $index + 1 }}: {{ $answer['question'] }}
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-gray-500">Student Answer:</span>
                                    <span class="ml-1 {{ $answer['is_correct'] ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $answer['user_answer'] }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Correct Answer:</span>
                                    <span class="ml-1 text-green-700">{{ $answer['correct_answer'] }}</span>
                                </div>
                            </div>

                            <div class="mt-1 text-xs text-gray-600">
                                Points: {{ $answer['earned_marks'] }}/{{ $answer['marks'] }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-6 text-gray-500">
            <div class="text-sm">No quiz answers found</div>
        </div>
    @endif
</div>
