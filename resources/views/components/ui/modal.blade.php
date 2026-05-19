@props(['id', 'title' => 'Modal', 'size' => 'md'])
@php
    $sizes = ['sm' => 'max-w-md', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl'];
@endphp
<div id="{{ $id }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40" onclick="if(event.target===this)closeModal('{{ $id }}')">
    <div class="bg-white rounded-lg {{ $sizes[$size] }} w-full mx-4 shadow-lg">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200">
            <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
            <button onclick="closeModal('{{ $id }}')" class="text-slate-400 hover:text-slate-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-5 py-4">{{ $slot }}</div>
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
