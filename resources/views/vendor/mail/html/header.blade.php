@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ url('apple-touch-icon.png') }}" class="logo" width="36" height="36" alt="">
<span class="nama-aplikasi">{{ trim(strip_tags($slot)) ?: config('app.name') }}</span>
</a>
</td>
</tr>
