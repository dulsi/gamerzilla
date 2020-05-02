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
					<th width=20%>Status</th>
					<th width=60%>Name</th>
					<th width=20%>Progress</th>
				</tr>
				{{foreach $items as $item}}
				<tr>
					<td><img src="{{$base_url}}/{{$item.trophy_name}}/{{$item.achieved}}/show" /></td>
					<td>{{$item.trophy_name}}</a></td>
					<td>{{if $item.max_progress}}{{if $item.achieved}}{{$item.max_progress}}{{else}}{{$item.progress}}{{/if}} / {{$item.max_progress}}{{/if}}</td>
				</tr>
    <tr><td colspan="3">{{$item.trophy_desc}}</td></tr>
				{{/foreach}}
			</table>
	</div>
</div>
