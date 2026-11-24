<x-mail::message>
# {{ ucfirst($label) }} {{ $document->number }}

Please find the PDF attached. This message was sent by {{ config('app.name') }}.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
