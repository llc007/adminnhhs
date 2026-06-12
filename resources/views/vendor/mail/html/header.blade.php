@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/logo.png') }}" class="logo" alt="{{ config('app.name', 'NHHS') }}" style="height: 75px; max-height: 75px; width: auto;">
</a>
</td>
</tr>
