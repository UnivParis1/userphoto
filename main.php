<?php

// Constantes
if (!defined("USER_STUDENT")) define ("USER_STUDENT", "student");
if (!defined("USER_PERSONNEL")) define ("USER_PERSONNEL", "personnel");
if (!defined("USER_UNKNOWN")) define ("USER_UNKNOWN", "unknown");

if (!defined("LDAP_PRIMARY_AFFILIATION")) define ("LDAP_PRIMARY_AFFILIATION", "edupersonprimaryaffiliation");
if (!defined("LDAP_PHOTO")) define ("LDAP_PHOTO", "jpegphoto");
if (!defined("LDAP_CIVILITE")) define ("LDAP_CIVILITE", "supanncivilite");
if (!defined("LDAP_UP1_TERMS_OF_USE")) define ("LDAP_UP1_TERMS_OF_USE", "up1termsofuse");
if (!defined("LDAP_NUMETU")) define ("LDAP_NUMETU", "supannetuid");
if (!defined("LDAP_UID")) define ("LDAP_UID", "uid");
if (!defined("LDAP_MEMBER_OF")) define ("LDAP_MEMBER_OF", "memberof");

if (!defined("PARAM_NUMETU")) define ("PARAM_NUMETU", "numetu");
if (!defined("PARAM_UID")) define ("PARAM_UID", "uid");
if (!defined("PARAM_TEST")) define ("PARAM_TEST", "test");
if (!defined("PARAM_PENPAL")) define ("PARAM_PENPAL", "penpal");
if (!defined("PARAM_LOGIN")) define ("PARAM_LOGIN", "login");

if (!defined("TYPE_EMPTY")) define ("TYPE_EMPTY", "empty");
if (!defined("TYPE_FORBIDDEN")) define ("TYPE_FORBIDDEN", "forbidden");

if (!defined("LDAP_MALE_CIVILITE")) define ("LDAP_MALE_CIVILITE", "M.");

if (!defined("LDAP_ALLOW_PUBLIC")) define ("LDAP_ALLOW_PUBLIC", "{PHOTO}PUBLIC");
if (!defined("LDAP_ALLOW_STUDENT")) define ("LDAP_ALLOW_STUDENT", "{PHOTO}STUDENT");
if (!defined("LDAP_ALLOW_PERSONNEL")) define ("LDAP_ALLOW_PERSONNEL", "{PHOTO}INTRANET");
if (!defined("LDAP_ALLOW_PERSONNEL2")) define ("LDAP_ALLOW_PERSONNEL2", "{PHOTO}ACTIVE");

if (!defined("LDAP_MEMBEROF_ALLOW")) define ("LDAP_MEMBEROF_ALLOW", "cn=applications.userinfo.l2-users,ou=groups,dc=univ-paris1,dc=fr");

// fonction generique qui va parser le fichier de config
function getConfValues ($section) {
   $ini_array = parse_ini_file("conf/conf.ini", true);
   return $ini_array[$section];
}

/* FONCTIONS PRINCIPALES */

/**
 * Recherche dans le LDAP les infos pour un user donné
 * Le filtre LDAP (sur un uid ou un numero étudiant) est passé en paramètre
 * Retourne un Array (attr1 => valeur1, attr2 => valeur2, etc.)
 */
function getLdapUserInfo($ldapIni, $rLdap, $filter) {
	$resUser = array();
    $wantedAttrs = array(LDAP_UID, LDAP_NUMETU, LDAP_CIVILITE, LDAP_PRIMARY_AFFILIATION,
			             LDAP_PHOTO, LDAP_UP1_TERMS_OF_USE, LDAP_MEMBER_OF);
	if ($filter != "" && $rLdap) {
		$result = ldap_search($rLdap, $ldapIni['peopleDn'], $filter, $wantedAttrs, 0, 1);
		$entries = ldap_get_entries($rLdap, $result);
		if ($result and ($entries["count"]==1)) {
			foreach ($wantedAttrs as $attr) {
				@$resUser[$attr] = $entries[0][strtolower($attr)];
		 	}
		} 		
	}
	return $resUser;
}

// LDAP connection and bind
function ldapConnect($conf) {
	$rLdap = ldap_connect($conf['host'], intval($conf['port']));
	if ($rLdap) {
		$bind = ldap_bind($rLdap, $conf['user'], $conf['pwd']);
		if (!$bind)  $rLdap=false;
	}
	return $rLdap;
}

// LDAP close connection
function ldapClose($rLdap) {
	ldap_close($rLdap);
}

/**
 * Recherche dans le LDAP les infos du user authentifié (le cas échéant) 
 */
function getAuthUserInfo ($casIni, $ldapIni, $rLdap) {
	$resUser = array();
	require $casIni['libPath'];
	phpCAS::client(CAS_VERSION_2_0, $casIni['host'], intval($casIni['port']), $casIni['url'], false);
	phpCAS::setNoCasServerValidation();
	phpCAS::setLang(PHPCAS_LANG_FRENCH);
	$auth = phpCAS::checkAuthentication();   // check CAS authentication
	if ($auth) {     // le user est authentifié
		$userid = phpCAS::getUser();
		$filter = LDAP_UID."=$userid";
		$resUser = getLdapUserInfo($ldapIni, $rLdap, $filter);		
	}
	return $resUser;
}

/**
 * Recherche dans le LDAP les infos du user en paramètre (en fonction des paramètres passés à l'URL)
 */
function getParamUserInfo($ldapIni, $rLdap) {
	$filter = "";
	if (isset($_GET[PARAM_UID])) {   // on a un parametre uid
		$param = ldap_escape_string($_GET[PARAM_UID]);
		$filter = LDAP_UID."=$param";
	} elseif (isset($_GET[PARAM_NUMETU])) {  // on a un parametre numetu
		$param = ldap_escape_string($_GET[PARAM_NUMETU]);
		$filter = LDAP_NUMETU."=$param";
	}
	return getLdapUserInfo($ldapIni, $rLdap, $filter);
}

/**
 * Affiche la photo d'un user en fonction des autorisations données
 * Prend en paramètres : 
 * 1) le user dont on doit afficher la photo
 * 2) éventuellement le user dont on doit vérifier s'il a ou pas l'autorisation de voir la photo  
 * 3) éventuellement un 2nd user dont on doit vérifier s'il a ou pas l'autorisation de voir la photo
 */
function afficheUserPhoto($userPhoto, $userAutorisation=null, $userAutorisation2=null) {
	if (isset($_GET['v'])) {  // paramètre pour la prolongationENT qui va passer v=${modifyTimestamp}
		//"private" car on ne veut pas qu'un forward proxy mette en cache une photo, outre passant la protection CAS
		header_remove("Pragma"); header("Cache-Control:private, max-age=86401");   // 1 journée seulement, pour qu'une machine partagée n'accumule des photos dans son cache
	} 
	header("Content-type: image/jpeg");
	if (empty($userPhoto))  {  // photo "unknown", user inconnu (user non authentifié, paramètre uid incorrect)
		readfile(IMG_UNKNOWN_USER);
	} else {  
		if ($userPhoto[LDAP_PRIMARY_AFFILIATION][0] == USER_STUDENT) {  // on doit rechercher la photo de l'étudiant dans Apogee
			$userPhoto[LDAP_PHOTO][0] = getPhotoEtu($userPhoto[LDAP_NUMETU][0]);
		}
		if ($userPhoto[LDAP_PHOTO][0] == null) {  // pas de photo trouvée (silhouette "empty")
			readfile(getSilhouetteGenre(@$userPhoto[LDAP_CIVILITE][0], TYPE_EMPTY));
		} else {  // il y a une photo => on vérifie les autorisations pour savoir s'il faut l'afficher ou pas
			$autorisation = false; 
			if (is_null($userAutorisation) && is_null($userAutorisation2)) {  
				$autorisation = true;   // on donne l'autorisation, on affiche la photo du user authentifié (cas de l'url sans paramètres)   
			} else {  
				if (!is_null($userPhoto[LDAP_UP1_TERMS_OF_USE]) && in_array(LDAP_ALLOW_PUBLIC, $userPhoto[LDAP_UP1_TERMS_OF_USE])) { 
					$autorisation = true;        // l'autorisation "public" a été donnée sur la photo (pas besoin d'autres vérifications)
				} else {
					if ((is_null($userAutorisation2) && checkAutorisationUser($userPhoto, $userAutorisation)) || 
	                    (!is_null($userAutorisation2) && checkAutorisationUser($userPhoto, $userAutorisation) && checkAutorisationUser($userPhoto, $userAutorisation2))
	                ) {
						$autorisation = true;
					}									
				}				
			}
			// affichage de la photo si autorisation, sinon silhouette "forbidden"
			if ($autorisation)  	print $userPhoto[LDAP_PHOTO][0];  
			else 	readfile(getSilhouetteGenre(@$userPhoto[LDAP_CIVILITE][0], TYPE_FORBIDDEN));				
		}
	}
}


/**
 * Vérifie si $user a le droit de voir la photo de $userPhoto
 */
function checkAutorisationUser($userPhoto, $user) {
	$autorisation = false;
	$profilUser = USER_UNKNOWN;
	if (!empty($user)) {
	 	if ($user[LDAP_PRIMARY_AFFILIATION][0] == USER_STUDENT)  $profilUser = USER_STUDENT;
		else  $profilUser = USER_PERSONNEL;
	}
	// $user pourra voir la photo de $userPhoto si l'une des conditions suivantes est remplie : 
	// -si $user est un étudiant et que $userPhoto a autorisé la diffusion de sa photo aux étudiants 
	// -si $user est un personnel et que $userPhoto a autorisé la diffusion de sa photo aux personnels
	// -si $user et $userPhoto sont la même personne
	// -si $user est membre d'un groupe LDAP autorisé à voir les photos
	$listDroits = $userPhoto[LDAP_UP1_TERMS_OF_USE];
	if (is_null($listDroits)) {
		$listDroits = array();
	}
	if ( ($profilUser==USER_STUDENT && in_array(LDAP_ALLOW_STUDENT, $listDroits)) ||
	     ($profilUser==USER_PERSONNEL && (in_array(LDAP_ALLOW_PERSONNEL, $listDroits) || in_array(LDAP_ALLOW_PERSONNEL2, $listDroits))) ||
		 ($profilUser!=USER_UNKNOWN && ($userPhoto[LDAP_UID][0] == $user[LDAP_UID][0] || in_array(LDAP_MEMBEROF_ALLOW, $user[LDAP_MEMBER_OF])))
	) {
		$autorisation = true;
	}				                       
	return $autorisation;
}


/**
 * Récupère la photo de l'étudiant dans Apogee, à partir du numéro étudiant
 */
function getPhotoEtu($numetu) {
	$photoEtu = null;
	$confApogee = getConfValues('apogee');  // recup de la conf Apogee dans le fichier ini
	$conn = apogeeConnect($confApogee);  // connexion à Apogee
	if ($conn) {
		$stmt = oci_parse($conn, 'select photo from up1_photo where cod_etu = :numetu ');
		oci_bind_by_name($stmt, ':numetu', $numetu);
		oci_execute($stmt);
		if ($row = oci_fetch_array($stmt, OCI_NUM+OCI_RETURN_LOBS)) {
			$photoEtu = $row[0];
		}
		oci_free_statement($stmt);  // libération des ressources réservées par le statement
	}	
	oci_close($conn);   // libération des ressources réservées par la connexion
	return $photoEtu;
}

// Connection Apogee
function apogeeConnect($conf) {
	$db = "(DESCRIPTION=(ADDRESS_LIST =
	    (ADDRESS = (PROTOCOL = TCP)(HOST = ".$conf['host'].")
	    (PORT = ".$conf['port'].")))(CONNECT_DATA=(SID=".$conf['name'].")))";
	$conn = oci_connect($conf['user'], $conf['pwd'], $db, 'utf8');
	return $conn;
}

// taken more mantisbt
function ldap_escape_string($p_string) {
	$t_find = array( '\\', '*', '(', ')', '/', "\x00" );
	$t_replace = array( '\5c', '\2a', '\28', '\29', '\2f', '\00' );
	$t_string = str_replace( $t_find, $t_replace, $p_string );
	return $t_string;
}

// retourne le fichier image de la silhouette en fonction du genre (à partir de la civilité)
// et du type (photo interdite ou inexistante)
function getSilhouetteGenre($civilite, $typeSilhouette) {
	if ($civilite == null) {
		return IMG_UNKNOWN_USER;
	} else {
		if ($typeSilhouette == TYPE_EMPTY) {    // photo inexistante
			if ($civilite == LDAP_MALE_CIVILITE)  return IMG_EMPTY_MALE;
			else 								  return IMG_EMPTY_FEMALE;
		} else {     // photo interdite
			if ($civilite == LDAP_MALE_CIVILITE)  return IMG_FORBIDDEN_MALE;
			else 								  return IMG_FORBIDDEN_FEMALE;
		}
	}
}


?>