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
					<th width=20%>Acheived</th>
					<th width=20%>Progress</th>
				</tr>
				{{foreach $items as $item}}
				<tr>
					<td>{{$item.trophy_name}}</a></td>
					<td>{{$item.achieved}}</td>
					<td>{{$item.progress}} / {{$item.max_progress}}</td>
				</tr>
				{{/foreach}}
			</table>
	</div>
</div>
