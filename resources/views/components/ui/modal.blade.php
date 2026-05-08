@props(['id', 'title' => 'Modal', 'size' => 'md'])
@php
    $sizes = ['sm' => 'max-w-md', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl'];
@endphp
<div id="{{ $id }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-ink-900/40 backdrop-blur-sm" onclick="if(event.target===this)closeModal('{{ $id }}')">
    <div class="bg-white rounded-2xl {{ $sizes[$size] }} w-full mx-4 shadow-xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-ink-100">
            <h3 class="font-display text-lg font-semibold">{{ $title }}</h3>
            <button onclick="closeModal('{{ $id }}')" class="p-1 hover:bg-ink-100 rounded">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-6 py-5">{{ $slot }}</div>
    </div>
</div>
@once
@push('scripts')
<script>
function openModal(id){ const el=document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id){ const el=document.getElementById(id); el.classList.add('hidden'); el.classList.remove('flex'); }
</script>
@endpush
@endonce
