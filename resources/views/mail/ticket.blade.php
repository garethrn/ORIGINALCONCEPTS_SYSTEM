<x-mail::message>
# Ticket {{ $ticket->reference() }}

**{{ $ticket->subject }}**

@if ($event === 'opened')
A ticket has been opened.
@elseif ($event === 'comment')
There is a new reply.
@else
The ticket status is now {{ $ticket->status->label() }}.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
