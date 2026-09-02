		{* Smarty *}
 		<div class="navbar fixed-bottom navbar-light bg-white footer justify-content-center" id="footerbar">
			<a target="_blank" href="https://www.soplanning.org">www.soplanning.org</a>
			<span class="noprint">&nbsp;-&nbsp;</span>
			<a href="mailto:support@soplanning.org" class="noprint">{#soplanning_support#}</a>
			<span class="noprint">&nbsp;-&nbsp;</span>
			<a href="mailto:support@soplanning.org" class="noprint">{#soplanning_proposer#}</a>
		</div>
		<div class="modal" tabindex="-1" role="dialog" id="myModal">
			<div class="modal-dialog modal-dialog-normal" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">...</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
					</div>
				</div>
			</div>
		</div>
		<div class="modal" id="alertModal" >
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-body">
					</div>
				</div>
			</div>
		</div>
		<div class="modal" tabindex="-1" role="dialog" id="myBigModal">
			<div class="modal-dialog modalBig" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">...</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
					</div>
				</div>
			</div>
		</div>
		<div class="modal" tabindex="-1" role="dialog" id="xajaxErrorModal">
			<div class="modal-dialog modal-xl" role="document">
				<div class="modal-content border-danger">
					<div class="modal-header bg-danger text-white">
						<h5 class="modal-title">&#9888; Server Error - Please report to support</h5>
						<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<textarea id="xajaxErrorContent" class="form-control" rows="20" style="font-family:monospace;font-size:12px;" readonly></textarea>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>

		<script src="{$BASE}/assets/plugins/bootstrap3-typeahead/bootstrap3-typeahead.min.js"></script>
		<script src="{$BASE}/assets/plugins/jquery-ui-1.13.2.custom/i18n/datepicker-{$lang}.js"></script>
		<script src="{$BASE}/assets/plugins/bootstrap-4.6.2/js/bootstrap.bundle.min.js"></script>
		<script src="{$BASE}/assets/plugins/bootstrap-datepicker-1.10.0/js/bootstrap-datepicker.min.js"></script>
		<script src="{$BASE}/assets/plugins/bootstrap-datepicker-1.10.0/locales/bootstrap-datepicker.{if $lang eq "en"}en-GB{else}{$lang}{/if}.min.js" charset="UTF-8"></script>

		{$xajax}
		<script>
		{literal}
		$(".modal").draggable({
			handle: ".modal-header"
		});

		function xajaxShowError(msg) {
			var el = document.getElementById('xajaxErrorContent');
			if (el && typeof $ !== 'undefined' && $.fn.modal) {
				el.value = msg;
				$('#xajaxErrorModal').modal('show');
			} else {
				alert(msg);
			}
		}

		// datepicker activation
		$('.datepicker').datepicker({ 			
			calendarWeeks: true,
			language: "{/literal}{$lang}{literal}",
			format: "{/literal}{$smarty.const.CONFIG_DATE_DATEPICKER}{literal}",
			autoclose: true,
			todayHighlight: true,
			orientation: "bottom left"
		});

		// tooltip activation
		$('.tooltipster').tooltip({
			html: true,
			placement: 'auto',
			boundary: 'window'
		});

		{/literal}
		
		{if isset($user)}
			setTimeout("xajax_checkAvailableVersion('header');", 10000);
		{/if}
		{literal}
		var showFooter = false;
		$('#footerbar').mouseenter(function(){showFooter=true;});
		$('#footerbar').mouseleave(function(){showFooter=false;});

		$(function(){
			$(window).scroll(function() {	
			$('#footerbar').show();
			setTimeout(() => {  
					if (showFooter===false)
					{
						$('#footerbar').fadeOut(); 
					}
				}, 2500);

		});
		
		})

		{/literal}

		/*
		function registerServiceWorker() {
		  if ('serviceWorker' in navigator) {
			 navigator.serviceWorker.register('{$BASE}/serviceWorker.js') //
				.then(function(reg){
					console.log("service worker registered");
				}).catch(function(err) {
					console.log(err)
				});
		  }
		  else {
			console.log("Could not find serviceWorker in navigator");
		  }
		}
		registerServiceWorker();
		*/

		</script>
	{if isset($user) && $user.user_id neq 'publicspl' && isset($smarty.const.CONFIG_AI_OLLAMA_URL) && $smarty.const.CONFIG_AI_OLLAMA_URL neq '' && isset($smarty.const.CONFIG_AI_OLLAMA_MODEL) && $smarty.const.CONFIG_AI_OLLAMA_MODEL neq ''}
		{include file="ai_chat_widget.tpl"}
	{/if}
	</body>
</html>