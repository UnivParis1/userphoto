<?php
if (!defined("IMG_EMPTY_FEMALE")) define ("IMG_EMPTY_FEMALE", "../img/empty-female.jpg");
if (!defined("IMG_EMPTY_MALE")) define ("IMG_EMPTY_MALE", "../img/empty-male.jpg");
if (!defined("IMG_FORBIDDEN_FEMALE")) define ("IMG_FORBIDDEN_FEMALE", "../img/forbidden-female.jpg");
if (!defined("IMG_FORBIDDEN_MALE")) define ("IMG_FORBIDDEN_MALE", "../img/forbidden-male.jpg");
if (!defined("IMG_UNKNOWN_USER")) define ("IMG_UNKNOWN_USER", "../img/unknown-user.jpg");

include '../config.php';
include '../main.php';

// config : recuperation des valeurs (par section)
$ldapIni = $conf['ldap'];

if (isset($_GET[PARAM_LDAP_TEST])) {  // config avec LDAP de test
	$ldapIni = $conf['ldap.test'];	
}

// connection ldap
$rLdap = ldapConnect($ldapIni);

// on recherche dans le LDAP les infos de la (ou des) personne(s) en fonction des paramètres passés dans l'URL
$userPenpal = array();
if (isset($_GET[PARAM_PENPAL])) {   // on a un parametre penpal
	$param = ldap_escape_string($_GET[PARAM_PENPAL]);
	$filter = LDAP_UID."=$param";
	$userPenpal = getLdapUserInfo($rLdap, $filter);
}
$userUid = getParamUserInfo($rLdap);  // on a un parametre uid ou numetu (sinon, retourne un tableau vide)

// close ldap connection
ldapClose($rLdap);

// on va appeler la fonction qui va afficher la photo en fonction des autorisations données
// cette fonction prend en paramètres :
// 1) le user dont on doit afficher la photo 
// 2) le user dont on doit vérifier s'il a ou pas l'autorisation de voir la photo
// ou 2bis) le profil pour lequel l'affichage de la photo doit être autorisé
if (isset($_GET[PARAM_PENPAL]) && (isset($_GET[PARAM_UID]) || isset($_GET[PARAM_NUMETU]))) {
	afficheUserPhoto($userUid, $userPenpal);
} elseif ((isset($_GET[PARAM_UID]) || isset($_GET[PARAM_NUMETU])) && isset($_GET[LDAP_UP1_TERMS_OF_USE])) {
	$listeDroits= explode(";", $_GET[LDAP_UP1_TERMS_OF_USE]);
    afficheUserPhotoDroits($userUid, $listeDroits);	  
} elseif (isset($_GET[PARAM_UID]) || isset($_GET[PARAM_NUMETU])) { // juste l'uid (ou le numetu), afficher la photo dans tous les cas (en trusted)
	afficheUserPhoto($userUid);
} else {	
	// s'il manque le paramètre uid (ou numetu), ou s'il n'y a aucun paramètre dans l'url, retourne une erreur 400
	$msg = "<b>Error : Your request is missing a required parameter</b>";
	header("HTTP/1.0 400 $msg");
	echo("$msg\n");
}


?>
