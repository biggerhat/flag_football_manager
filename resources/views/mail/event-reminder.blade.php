{!! $team->name !!} — {{ $date->format('l, F j') }}

@foreach ($events as $event)
- {!! $event['kind'] !!}: {!! $event['title'] !!}{!! $event['time'] ? ' at '.$event['time'] : '' !!}{!! $event['location'] ? ' ('.$event['location'].')' : '' !!}

@endforeach
Open the app: {{ route('dashboard') }}
You get this because you turned on email reminders. Turn them off under Settings → Profile.
