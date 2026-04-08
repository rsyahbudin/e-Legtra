{{-- Reusable file display partial for existing uploads + file input --}}
{{-- Required variables: $question, $existingValue, $colorScheme (neutral|green) --}}

@php
    $borderClass = $colorScheme === 'green'
        ? 'border-green-200 bg-white dark:border-green-800 dark:bg-green-950/20'
        : 'border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-zinc-800';
    $labelClass = $colorScheme === 'green'
        ? 'text-green-800 dark:text-green-300'
        : 'text-neutral-700 dark:text-neutral-300';
    $hintClass = $colorScheme === 'green'
        ? 'text-green-700 dark:text-green-400'
        : 'text-neutral-500 dark:text-neutral-400';
@endphp

@if($existingValue)
    @if($question->QUEST_IS_MULTIPLE)
        @php $files = json_decode($existingValue, true) ?? []; @endphp
        @if(count($files) > 0)
            <div class="mb-3 space-y-2">
                <p class="text-xs font-semibold {{ $labelClass }}">Existing Uploads:</p>
                @foreach($files as $idx => $f)
                    @php
                        $fPath = is_array($f) ? $f['path'] : $f;
                        $fName = is_array($f) ? ($f['name'] ?? basename($fPath)) : basename($fPath);
                    @endphp
                    <div class="flex items-center justify-between rounded-lg border {{ $borderClass }} px-3 py-2 text-sm">
                        <a href="{{ Storage::disk('legal_docs')->url($fPath) }}" target="_blank" class="truncate text-blue-600 hover:underline dark:text-blue-400">
                            {{ $fName }}
                        </a>
                        @if($question->QUEST_IS_EDITABLE)
                            <button type="button" wire:click="removeFile('{{ $question->QUEST_CODE }}', {{ $idx }})" class="ml-3 text-xs font-medium text-red-500 hover:text-red-700">Remove</button>
                        @endif
                    </div>
                @endforeach
            </div>
            @if($question->QUEST_IS_EDITABLE)
                <div class="mb-2 text-xs {{ $hintClass }}">Upload new files below to append them to the list.</div>
            @endif
        @endif
    @else
        <div class="mb-3 flex items-center justify-between rounded-lg border {{ $borderClass }} px-3 py-2 text-sm">
            <a href="{{ Storage::disk('legal_docs')->url($existingValue) }}" target="_blank" class="truncate text-blue-600 hover:underline dark:text-blue-400">
                {{ basename($existingValue) }}
            </a>
            @if($question->QUEST_IS_EDITABLE)
                <button type="button" wire:click="removeFile('{{ $question->QUEST_CODE }}')" class="ml-3 text-xs font-medium text-red-500 hover:text-red-700">Remove</button>
            @endif
        </div>
        @if($question->QUEST_IS_EDITABLE)
            <div class="mb-2 text-xs {{ $hintClass }}">Upload a new file below to replace it.</div>
        @endif
    @endif
@endif

@if($question->QUEST_IS_EDITABLE)
    <input type="file" wire:model="dynamicFiles.{{ $question->QUEST_CODE }}" {{ $question->QUEST_ACCEPT ? 'accept='.$question->QUEST_ACCEPT : '' }} {{ $question->QUEST_IS_MULTIPLE ? 'multiple' : '' }} class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:text-neutral-400 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
    <div wire:loading wire:target="dynamicFiles.{{ $question->QUEST_CODE }}" class="mt-2 text-sm text-blue-600">Uploading...</div>
@endif
