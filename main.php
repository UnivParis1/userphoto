<?php

// Constantes
if (!defined("USER_STUDENT")) define ("USER_STUDENT", "student");
if (!defined("USER_PERSONNEL")) define ("USER_PERSONNEL", "personnel");
if (!defined("USER_UNKNOWN")) define ("USER_UNKNOWN", "unknown");

if (!defined("VAL_PHOTO_ETU")) define ("VAL_PHOTO_ETU", "etu");
if (!defined("VAL_PHOTO_ETU_IAE")) define ("VAL_PHOTO_ETU_IAE", "etu-iae");  

if (!defined("LDAP_PRIMARY_AFFILIATION")) define ("LDAP_PRIMARY_AFFILIATION", "edupersonprimaryaffiliation");
if (!defined("LDAP_CIVILITE")) define ("LDAP_CIVILITE", "supanncivilite");
if (!defined("LDAP_UP1_TERMS_OF_USE")) define ("LDAP_UP1_TERMS_OF_USE", "up1termsofuse");
if (!defined("LDAP_NUMETU")) define ("LDAP_NUMETU", "supannetuid");
if (!defined("LDAP_UID")) define ("LDAP_UID", "uid");
if (!defined("LDAP_MAIL")) define ("LDAP_MAIL", "mail");
if (!defined("LDAP_EPPN")) define ("LDAP_EPPN", "edupersonprincipalname");
if (!defined("LDAP_MEMBER_OF")) define ("LDAP_MEMBER_OF", "memberof");

if (!defined("PARAM_NUMETU")) define ("PARAM_NUMETU", "numetu");
if (!defined("PARAM_UID")) define ("PARAM_UID", "uid");
if (!defined("PARAM_MAIL")) define ("PARAM_MAIL", "mail");
if (!defined("PARAM_EPPN")) define ("PARAM_EPPN", "eppn");
if (!defined("PARAM_LDAP_TEST")) define ("PARAM_LDAP_TEST", "ldap-test");
if (!defined("PARAM_CAS_TEST")) define ("PARAM_CAS_TEST", "cas-test");
if (!defined("PARAM_PENPAL")) define ("PARAM_PENPAL", "penpal");
if (!defined("PARAM_PENPAL_AFFILIATION")) define ("PARAM_PENPAL_AFFILIATION", "penpalAffiliation");
if (!defined("PARAM_APP_CLIENTE")) define ("PARAM_APP_CLIENTE", "app-cli");
if (!defined("PARAM_RATIO")) define ("PARAM_RATIO", "ratio");
if (!defined("PARAM_TYPE_PHOTO")) define ("PARAM_TYPE_PHOTO", "type-photo");

if (!defined("LDAP_PHOTO_ANNU"))  define ("LDAP_PHOTO_ANNU", "jpegphoto");
if (!defined("LDAP_PHOTO")) { 
   if (isset($_GET[PARAM_TYPE_PHOTO])) {
       if ($_GET[PARAM_TYPE_PHOTO] == VAL_PHOTO_ETU)  define ("LDAP_PHOTO", "jpegphoto;x-etu");
       if ($_GET[PARAM_TYPE_PHOTO] == VAL_PHOTO_ETU_IAE)  define ("LDAP_PHOTO", "jpegphoto;x-etu-iae");
   } else {
       define ("LDAP_PHOTO", "jpegphoto");
   }	   
}
if (!defined("TYPE_EMPTY")) define ("TYPE_EMPTY", "empty");
if (!defined("TYPE_FORBIDDEN")) define ("TYPE_FORBIDDEN", "forbidden");

if (!defined("LDAP_MALE_CIVILITE")) define ("LDAP_MALE_CIVILITE", "M.");

if (!defined("LDAP_ALLOW_PUBLIC")) define ("LDAP_ALLOW_PUBLIC", "{PHOTO}PUBLIC");
if (!defined("LDAP_ALLOW_STUDENT")) define ("LDAP_ALLOW_STUDENT", "{PHOTO}STUDENT");
if (!defined("LDAP_ALLOW_PERSONNEL")) define ("LDAP_ALLOW_PERSONNEL", "{PHOTO}INTRANET");
if (!defined("LDAP_ALLOW_PERSONNEL2")) define ("LDAP_ALLOW_PERSONNEL2", "{PHOTO}ACTIVE");

if (!defined("LDAP_MEMBEROF_ALLOW")) define ("LDAP_MEMBEROF_ALLOW", "cn=applications.userinfo.l2-users,ou=groups,dc=univ-paris1,dc=fr");
if (!defined("APP_USERINFO")) define ("APP_USERINFO", "userinfo");

if (!defined("ANONYMOUS_VALUE")) define ("ANONYMOUS_VALUE", "anonymous");

/* FONCTIONS PRINCIPALES */

/**
 * Recherche dans le LDAP les infos pour un user donné
 * Le filtre LDAP (sur un uid ou un numero étudiant) est passé en paramètre
 * Retourne un Array (attr1 => valeur1, attr2 => valeur2, etc.)
 */
function getLdapUserInfo($rLdap, $filter) {
	global $conf;
	$resUser = array();
    $wantedAttrs = array(LDAP_UID, LDAP_NUMETU, LDAP_CIVILITE, LDAP_PRIMARY_AFFILIATION,
			             LDAP_PHOTO, LDAP_PHOTO_ANNU, LDAP_UP1_TERMS_OF_USE, LDAP_MEMBER_OF);
	if ($filter != "" && $rLdap) {
		$result = ldap_search($rLdap, $conf['ldap.dn']['people'], $filter, $wantedAttrs, 0, 1);
		$entries = ldap_get_entries($rLdap, $result);
		if ($result and ($entries["count"]==1)) {
			foreach ($wantedAttrs as $attr) {
			   @$resUser[$attr] = $entries[0][strtolower($attr)];                           
			}
			if (isset($_GET[PARAM_TYPE_PHOTO]) && !isset($resUser[LDAP_PHOTO])) { // cas où on demande la photo de la carte mais qu'il n'y a pas l'attribut LDAP correspoondant
			   @$resUser[LDAP_PHOTO] = $resUser[LDAP_PHOTO_ANNU];	// fallback sur la photo de "jpegPhoto"
			}	
		} 		
	}
	return $resUser;
}

// LDAP connection and bind
function ldapConnect($ldapConf) {
	$rLdap = ldap_connect($ldapConf['host'], intval($ldapConf['port']));
	if ($rLdap) {
		$bind = ldap_bind($rLdap, $ldapConf['user'], $ldapConf['pwd']);
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
function getAuthUserInfo ($casIni, $rLdap) {
	global $conf;
	$resUser = array();
	require $conf['lib']['cas'];
	phpCAS::client(CAS_VERSION_2_0, $casIni['host'], intval($casIni['port']), $casIni['uri']);
	phpCAS::setNoCasServerValidation();
	phpCAS::handleLogoutRequests(false);
	phpCAS::setLang(PHPCAS_LANG_FRENCH);
	//$auth = phpCAS::checkAuthentication();   // check CAS authentication
	$auth = phpCAS::forceAuthentication();
	if ($auth) {     // le user est authentifié
		$userid = phpCAS::getUser();
		$filter = LDAP_UID."=$userid";
		$resUser = getLdapUserInfo($rLdap, $filter);		
	}
	return $resUser;
}

/**
 * Recherche dans le LDAP les infos du user en paramètre (en fonction des paramètres passés à l'URL)
 */
function getParamUserInfo($rLdap) {
	$filter = "";
	if (isset($_GET[PARAM_UID])) {   // on a un parametre uid
		$param = ldap_escape_string($_GET[PARAM_UID]);
		$filter = LDAP_UID."=$param";
	} elseif (isset($_GET[PARAM_NUMETU])) {  // on a un parametre numetu
		$param = ldap_escape_string($_GET[PARAM_NUMETU]);
		$filter = LDAP_NUMETU."=$param";
	} elseif (isset($_GET[PARAM_MAIL])) {  // on a un parametre mail
		$param = ldap_escape_string($_GET[PARAM_MAIL]);
		$filter = LDAP_MAIL."=$param";
	} elseif (isset($_GET[PARAM_EPPN])) {  // on a un parametre mail
                $param = ldap_escape_string($_GET[PARAM_EPPN]);
                $filter = LDAP_EPPN."=$param";
        }
	return getLdapUserInfo($rLdap, $filter);
}

/**
 * Redimensionne l'image le cas échéant (paramètre ratio dans l'url)
 */
function resizeImage($img, $isFile) {
    if (isset($_GET[PARAM_RATIO]) and $_GET[PARAM_RATIO]=="1") {
        if ($isFile) {  // $img contient le chemin du fichier (c'est donc une silhouette)
           $im = imagecreatefromjpeg($img);
        } else {
           $im = imagecreatefromstring($img);
        }
        $size = min(imagesx($im), imagesy($im));
        $newY = max(imagesx($im), imagesy($im))*11/100;
        $im2 = imagecrop($im, ['x' => 0, 'y' => $newY, 'width' => $size, 'height' => $size]);
        imagedestroy($im);
        if ($im2 !== FALSE) {
            imagejpeg($im2);            
        } else {  //echec du imagecrop (on affiche l'image sans redimensionnement)
            if ($isFile) {  
                readfile($img);
            } else {
                print $img;  
            }
        }
    } else {  // pas de paramètre ratio dans l'url, affiche de la photo telle quel (pas de redimensionnement)
        if ($isFile) {
            readfile($img);
        } else {
            print $img;
        }
    }
}

/**
 * Affiche la photo d'un user en fonction des autorisations données
 * Prend en paramètres : 
 * 1) le user dont on doit afficher la photo
 * 2) éventuellement le user dont on doit vérifier s'il a ou pas l'autorisation de voir la photo  
 * 3) éventuellement un 2nd user dont on doit vérifier s'il a ou pas l'autorisation de voir la photo
 */
function afficheUserPhoto($userPhoto, $userAutorisation=null, $userAutorisation2=null) {
	global $conf;	
	if (empty($userPhoto))  {  // photo "unknown", user inconnu (paramètre uid incorrect, etc.)
	    // on met une silhouette "neutre" (mais pas la même suivant le cas d'un accès authentifié ou non)
	    if (!is_null($userAutorisation) && empty($userAutorisation)) {   // le user qui veut voir la photo n'est pas authentifié
	       header("Content-type: image/svg+xml");
	       readfile(IMG_SILHOUETTE_FOR_PUBLIC);
	    } else {   // le user qui veut voir la photo est authentifié
	        header("Content-type: image/jpeg");
	        resizeImage(IMG_UNKNOWN_USER, true);
	    }
	} else {  
		if ($userPhoto[LDAP_PRIMARY_AFFILIATION][0] == USER_STUDENT && $conf['apogee']['photo']) {  // on doit rechercher la photo de l'étudiant dans Apogee
			$userPhoto[LDAP_PHOTO][0] = getPhotoEtu($userPhoto[LDAP_NUMETU][0]);
		}
		if ($userPhoto[LDAP_PHOTO][0] == null) {  // pas de photo trouvée 
		    if (!is_null($userAutorisation) && empty($userAutorisation)) { // le user qui veut voir la photo n'est pas authentifié, affichage d'une silhouette neutre
		        header("Content-type: image/svg+xml");
		        readfile(IMG_SILHOUETTE_FOR_PUBLIC);
		    } else {  // le user qui veut voir la photo est authentifié, affichage de la silhouette "empty"
		        header("Content-type: image/jpeg");
		        resizeImage(getSilhouetteGenre(@$userPhoto[LDAP_CIVILITE][0], TYPE_EMPTY), true);
		    }
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
			// affichage de la photo si autorisation, sinon silhouette "forbidden" (ou neutre pour user non authentifié)
			if ($autorisation) {  	
                check_param_v();
                header("Content-type: image/jpeg");
                resizeImage($userPhoto[LDAP_PHOTO][0], false);                
            }     
			else { 	
			    if (!is_null($userAutorisation) && empty($userAutorisation)) { // le user qui veut voir la photo n'est pas authentifié, affichage d'une silhouette neutre
			        header("Content-type: image/svg+xml");
			        readfile(IMG_SILHOUETTE_FOR_PUBLIC);
			    } else {  // le user qui veut voir la photo est authentifié, affichage de la silhouette "forbidden"
			        header("Content-type: image/jpeg");
			        resizeImage(getSilhouetteGenre(@$userPhoto[LDAP_CIVILITE][0], TYPE_FORBIDDEN), true);
			    }
			}			
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
	// -si $user et $userPhoto sont la même personne et qu'il n'y a pas de paramètre penpalAffiliation dans l'url
	// -si $user est membre d'un groupe LDAP autorisé à voir les photos depuis l'application userinfo
	$listDroits = $userPhoto[LDAP_UP1_TERMS_OF_USE];
	if (is_null($listDroits)) {
		$listDroits = array();
	}
	if ( ($profilUser==USER_STUDENT && in_array(LDAP_ALLOW_STUDENT, $listDroits)) ||
	     ($profilUser==USER_PERSONNEL && (in_array(LDAP_ALLOW_PERSONNEL, $listDroits) || in_array(LDAP_ALLOW_PERSONNEL2, $listDroits))) ||
		 ($profilUser!=USER_UNKNOWN && (($userPhoto[LDAP_UID][0] == $user[LDAP_UID][0] && !isset($_GET[PARAM_PENPAL_AFFILIATION])) 
		  || (in_array(LDAP_MEMBEROF_ALLOW, $user[LDAP_MEMBER_OF]) && isset($_GET[PARAM_APP_CLIENTE]) && $_GET[PARAM_APP_CLIENTE]==APP_USERINFO)))
	) {
		$autorisation = true;
	}				                       
	return $autorisation;
}


/**
 * Récupère la photo de l'étudiant dans Apogee, à partir du numéro étudiant
 */
function getPhotoEtu($numetu) {
	global $conf;
	$photoEtu = null;
	$confApogee = $conf['apogee'];  // recup de la conf Apogee dans le fichier ini
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
function apogeeConnect($confApo) {
	$db = "(DESCRIPTION=(ADDRESS_LIST =
	    (ADDRESS = (PROTOCOL = TCP)(HOST = ".$confApo['host'].")
	    (PORT = ".$confApo['port'].")))(CONNECT_DATA=(SID=".$confApo['name'].")))";
	$conn = oci_connect($confApo['user'], $confApo['pwd'], $db, 'utf8');
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


/**
 * Affiche la photo d'un user en fonction des autorisations données
 * Prend en paramètres : 
 * 1) le user dont on doit afficher la photo
 * 2) un tableau avec la liste des droits/profils pour lequel l'affichage de la photo doit être autorisé
 * Utilisée uniquement en mode "trusted"
 */
function afficheUserPhotoDroits($userPhoto, $listeDroits) {	
	global $conf;
	header("Content-type: image/jpeg");
	if (empty($userPhoto))  {  // photo "unknown", user inconnu 
		readfile(IMG_UNKNOWN_USER);
	} else {
		if ($userPhoto[LDAP_PRIMARY_AFFILIATION][0] == USER_STUDENT && $conf['apogee']['photo']) {  // on doit rechercher la photo de l'étudiant dans Apogee
			$userPhoto[LDAP_PHOTO][0] = getPhotoEtu($userPhoto[LDAP_NUMETU][0]);
		}
		if ($userPhoto[LDAP_PHOTO][0] == null) {  // pas de photo trouvée (silhouette "empty")
			readfile(getSilhouetteGenre(@$userPhoto[LDAP_CIVILITE][0], TYPE_EMPTY));
		} else {  // il y a une photo => on vérifie les autorisations pour savoir s'il faut l'afficher ou pas
			$autorisation = false;
			if (isset($userPhoto[LDAP_UP1_TERMS_OF_USE])) {
			  foreach ($userPhoto[LDAP_UP1_TERMS_OF_USE] as $droitAutorisation) {
				if (in_array($droitAutorisation, $listeDroits)) {
					$autorisation = true;
					break;
				}
			  }			
			}			
			// affichage de la photo si autorisation, sinon silhouette "forbidden"
			if ($autorisation) {
                                check_param_v();
                             	print $userPhoto[LDAP_PHOTO][0];
			}
                        else {	readfile(getSilhouetteGenre(@$userPhoto[LDAP_CIVILITE][0], TYPE_FORBIDDEN)); }
		}
	}		
}

/* Paramètre pour la prolongationENT qui va passer v=${modifyTimestamp} */
function check_param_v() {
	if (isset($_GET['v'])) {  
		//"private" car on ne veut pas qu'un forward proxy mette en cache une photo, outre passant la protection CAS
		header_remove("Pragma"); header("Cache-Control:private, max-age=86401");   // 1 journée seulement, pour qu'une machine partagée n'accumule des photos dans son cache
	}
}

?>
