<x-mail::message>
# Hi {{ $name }}, here's what happened {{ $period }}

@foreach ($sections as $section)
## [{{ $section['community']['name'] }}]({{ $section['community']['url'] }})

@if ($section['new_posts'] > count($section['posts']))
{{ $section['new_posts'] }} new posts. Here are the most popular:

@elseif ($section['new_posts'] > 0)
{{ $section['new_posts'] }} new {{ Str::plural('post', $section['new_posts']) }}:

@endif
@foreach ($section['posts'] as $post)
**[{{ $post['title'] }}]({{ $post['url'] }})**<br>
{{ $post['excerpt'] }}<br>
<span style="color:#6b7280;font-size:13px">{{ $post['author'] }} · {{ $post['likes'] }} {{ Str::plural('like', $post['likes']) }} · {{ $post['comments'] }} {{ Str::plural('comment', $post['comments']) }}</span>

@endforeach
@if ($section['events'])
**Coming up**

@foreach ($section['events'] as $event)
- [{{ $event['title'] }}]({{ $event['url'] }}): {{ $event['when'] }}
@endforeach

@endif
<x-mail::button :url="$section['community']['url']">
Open {{ $section['community']['name'] }}
</x-mail::button>

@endforeach
<x-slot:subcopy>
You get this {{ $period === 'today' ? 'daily' : 'weekly' }} digest because you're a member of these communities.
[Unsubscribe from digests]({{ $unsubscribe }}) · [Email settings]({{ $settings }})
</x-slot:subcopy>
</x-mail::message>
