{* Smarty *}

<div style="margin-left:120px; width:400px">
	{#filtre_perso_texte1#}
</div>
<br>
<form method="POST" action="" target="_blank">
	<input type="hidden" name="old_filtre_id" id="old_filtre_id" value="{$filtre_perso.filtre_perso_id}" />
	<div class="form-group row col-md-12 align-items-center">
		<label class="col-md-3 col-form-label">{#filtre_perso_nom#} :</label>
		<div class="col-md-9">
			<input name="filtre_perso_nom" id="filtre_perso_nom" type="text" class="form-control" maxlength="100" value="{$filtre_perso.filtre_perso_nom|xss_protect}" />
		</div>
	</div>
	<div class="form-group row col-md-12 align-items-center">
		<div class="col-md-3"></div>
		<div class="col-md-8">
			<br />
			<input type="button" value="{if $filtre_perso.saved eq 1}{#filtre_perso_modifier#}{else}{#filtre_perso_creer#}{/if}" class="btn btn-primary" onClick="xajax_filtre_perso_save('{$filtre_perso.filtre_perso_id}',  $('#filtre_perso_nom').val())" />
			
			{if $filtre_perso.saved eq 1}
				<a class="btn btn-warning" href="javascript:xajax_filtre_perso_delete('{$filtre_perso.filtre_perso_id}');void(0);" onclick="javascript:return confirm('{#confirm#|xss_protect}')">{#winPeriode_supprimer#}</a>
			{/if}
			{if $filtre_perso.saved eq 1}
				<br><br>
				<input type="button" value="{#filtre_perso_appliquer#|escape:"html"}" class="btn btn-danger" onClick="xajax_filtre_perso_appliquer('{$filtre_perso.filtre_perso_id}')" />
			{/if}
		</div>
	</div>
</form>
