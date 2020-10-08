<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>GAMES - Privacy</h2>
	</div>
	<form action="/gamerzilla/{{$channel}}/.privacy" method="post">

	<!-- input type='hidden' name='form_security_token' value='{{$token}}' -->

	<div id="block_default_container" class="clearfix form-group checkbox">
		<label for="id_block_default">Block by default</label>
		<div class="float-right"><input type="checkbox" name='block_default' id='id_block_default' value="1" {{if $block_default}}checked="checked"{{/if}}"   /><label class="switchlabel" for='id_block_default'> <span class="onoffswitch-inner" data-on='' data-off=''></span><span class="onoffswitch-switch"></span></label></div>
		<small class="form-text text-muted">Check to block access achievement information by default.</small>
	</div>
	<div class="admin-submit-wrapper">
		<input type="submit" name="submit" class="btn btn-primary" value="Submit" />
	</div>
	</form>
</div>
