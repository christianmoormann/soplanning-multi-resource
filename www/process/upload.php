<?php
require 'base.inc';
require BASE . '/../config.inc';
require BASE . '/../includes/header.inc';

$type=$_POST['type'];
// securise link_id
$linkid=preg_replace( '/[^a-z0-9]+/', '0', strtolower($_POST['linkid']));
$upload_dir = UPLOAD_DIR."$linkid/"; // upload directory 
if(strlen(trim($linkid)) == 0){
	echo 'Error, please contact support';
	die;
}

// Si on fait un upload de fichiers
if ($type=='upload')
{
	// Pour tous les fichiers, on tente de les uploader
	for($i=0; $i<count($_FILES); $i++){	
		$infos = pathinfo(mb_convert_encoding($_FILES["fichier-$i"]['name'], 'ISO-8859-1', 'UTF-8'));
		$filename = cleanStr($infos['filename'])  . '.' . $infos['extension'];
		$extensions = explode(',', CONFIG_WHITELIST_UPLOAD);
		if(!in_array(strtolower($infos['extension']), $extensions)){
			echo $smarty->getConfigVars('File_not_allowed');
			exit;
		}
		$tmp_dir = $_FILES["fichier-$i"]['tmp_name'];
		$fileSize = $_FILES["fichier-$i"]['size'];
			 
		// Verification du répertoire
		if(!file_exists(UPLOAD_DIR) || !is__writable(UPLOAD_DIR)) {
			$msg=preg_replace('/filename/',$filename,$smarty->getConfigVars('upload_fichier_erreur_ecriture_repertoire'));
			echo $msg;
			exit;
		} else {
			// Création du répertoire si nécessaire
			@mkdir($upload_dir);
			
			// Si il existe déjà, on ne l'écrase pas
			if (file_exists($upload_dir.$filename))
			{
				$msg=preg_replace('/filename/',$filename,$smarty->getConfigVars('upload_fichier_existe_deja'));
				echo $msg;
			}else
			{
				// vérification de la taille du fichier
				if ($fileSize > MAX_SIZE_UPLOAD)
				{
					$msg=preg_replace('/filename/',$filename,$smarty->getConfigVars('upload_fichier_erreur_taille'));
					echo $msg;
					exit;
				}
				
				// chargement du fichier
				if(!(move_uploaded_file($tmp_dir,$upload_dir.$filename)))
				{
					$msg=preg_replace('/filename/',$filename,$smarty->getConfigVars('upload_fichier_erreur_chargement_fichier'));
					echo $msg;
					exit;
				}else
				{
					if (!file_exists($upload_dir.$filename))
					{
						$msg=preg_replace('/filename/',$filename,$smarty->getConfigVars('upload_fichier_erreur_chargement_fichier'));
						echo $msg;
						exit;
					}else
					{
						
						$allowedTypes = [
							'jpg'  => ['image/jpeg', 'image/pjpeg'],
							'jpeg' => ['image/jpeg', 'image/pjpeg'],
							'png'  => ['image/png'],
							'gif'  => ['image/gif'],
							'pdf'  => ['application/pdf'],
							'doc'  => ['application/msword'],
							'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
							'xls'  => ['application/vnd.ms-excel'],
							'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
						];

						// Validation
						$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
						$mimeType = mime_content_type($upload_dir.$filename); // ou finfo_file()

						if (!isset($allowedTypes[$extension])) {
							$msg=preg_replace('/filename/',$filename . '1',$smarty->getConfigVars('upload_fichier_mauvais_mime_type'));
							echo $msg;
							exit;
						}

						if (!in_array($mimeType, $allowedTypes[$extension])) {
							$msg=preg_replace('/filename/',$filename . '2',$smarty->getConfigVars('upload_fichier_mauvais_mime_type'));
							echo $msg;
							exit;
						}						
						
						$msg=preg_replace('/filename/',$filename,$smarty->getConfigVars('upload_fichier_chargement_ok'));
						echo $msg;
					}
				}
			}
		}
	}
}

// Si on fait un delete de fichiers
if ($type=='delete')
{
		// basename is used to avoid a full path => can only by a file in the directory
		$filename=mb_convert_encoding(basename($_POST['fichier_to_delete']), 'ISO-8859-1', 'UTF-8');
		if (file_exists($upload_dir.$filename))
		{
			if (@unlink($upload_dir.$filename))
			{
				// Suppression du repertoire si le dossier n'est pas vide
				if(!glob($upload_dir."*"))
				{
					rrmdir($upload_dir);
				}
				$msg=preg_replace('/filename/',$filename,$smarty->getConfigVars('upload_fichier_delete_ok'));
				echo $msg;
			}else
			{
				$msg=preg_replace('/filename/',$filename,$smarty->getConfigVars('upload_fichier_delete_ko'));
				echo $msg;
				exit;
			}
		}
}

// Si on fait un delete de tous les fichiers (annulation d'une nouvelle tâche non enregistrée)
if ($type=='deletenew')
{
	rrmdir($upload_dir);
}

// update de la tâche si periode_id
if (!empty($_POST['periodeid'])) {
	// Mise à jour de la tâche actuelle
	$periode = new Periode();
	$periode->db_load(array('periode_id', '=', (int)$_POST['periodeid']));		
	if (!empty($_POST['fichiers']))
	{
		$periode->fichiers=replaceAccents(mb_convert_encoding($_POST['fichiers'], 'ISO-8859-1', 'UTF-8'));
	}else 
	{
		$periode->fichiers = null;
	}
	$periode->db_save();
	
	// Mise à jour de toutes les tâches liées
	$sql = "UPDATE planning_periode SET fichiers = " . val2sql($periode->fichiers) . " WHERE link_id = " . val2sql($linkid);
	db_query($sql);
}	

?>