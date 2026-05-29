<div class="space-y-4 p-4">
    <div><strong>Company:</strong> {{ $log->company->name ?? '—' }}</div>
    <div><strong>Channel:</strong> {{ ucfirst($log->channel) }}</div>
    <div><strong>Replied At:</strong> {{ $log->replied_at?->format('Y-m-d H:i') ?? '—' }}</div>
    <div>
        <strong>Reply Content:</strong>
        <pre class="mt-2 bg-gray-50 p-3 rounded text-sm whitespace-pre-wrap">{{ $log->reply_content ?? 'No content recorded.' }}</pre>
    </div>
    <div>
        <strong>Original Message Sent:</strong>
        <pre class="mt-2 bg-gray-50 p-3 rounded text-sm whitespace-pre-wrap">{{ $log->outreachMessage?->email_body ?? '—' }}</pre>
    </div>
</div>
