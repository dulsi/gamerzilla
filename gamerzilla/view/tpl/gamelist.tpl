<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>GAMES</h2>
	</div>
	<div class="section-subtitle-wrapper">
		<h3>{{if $title}}{{$title}}{{else}}Order{{/if}}</h3>
	</div>
	<div class="section-content-wrapper">
			<table class="w-100">
				<tr>
					<th width=60%>Name</th>
					<th width=20%>Earned</th>
					<th width=20%>Total</th>
				</tr>
				{{foreach $items as $item}}
				<tr>
					<td><a href="{{$item.url}}"><img src="{{$item.url}}/show" alt="[{{$item.name}}]"> {{$item.name}}</a></td>
					<td>{{$item.earned}}</td>
					<td>{{$item.total}}</td>
				</tr>
				{{/foreach}}
			</table>
	</div>
</div>
