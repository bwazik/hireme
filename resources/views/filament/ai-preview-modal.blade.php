<div class="space-y-4">
    @if($error)
        <div class="p-4 text-sm text-yellow-800 bg-yellow-50 rounded-lg dark:bg-yellow-900/30 dark:text-yellow-300" role="alert">
            {{ $error }}
        </div>
    @else
        <div>
            <strong>Subject:</strong>
            <p class="mt-1 text-sm">{{ $preview['email_subject'] ?? '—' }}</p>
        </div>
        
        <div>
            <strong>Email Body:</strong>
            <div class="mt-1 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg text-sm font-mono">
                {!! nl2br(e($preview['email_body'] ?? '—')) !!}
            </div>
        </div>

        <div>
            <strong>WhatsApp Message:</strong>
            <div class="mt-1 p-3 bg-green-50 dark:bg-green-900/30 text-green-900 dark:text-green-300 rounded-lg text-sm font-mono border border-green-200 dark:border-green-800">
                {!! nl2br(e($preview['whatsapp_message'] ?? '—')) !!}
            </div>
        </div>
    @endif
</div>
