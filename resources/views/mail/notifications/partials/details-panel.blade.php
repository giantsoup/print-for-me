<table class="details-list" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($details as $detail)
<tr class="details-list-row">
<td class="details-list-label">
{{ $detail['label'] }}
</td>
<td class="details-list-value">
@if (isset($detail['url']))
<a href="{{ $detail['url'] }}">{{ $detail['value'] }}</a>
@else
{{ $detail['value'] }}
@endif
</td>
</tr>
@endforeach
</table>
