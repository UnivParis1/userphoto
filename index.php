<?php
if (!defined("IMG_EMPTY_FEMALE")) define ("IMG_EMPTY_FEMALE", "img/empty-female.jpg");
if (!defined("IMG_EMPTY_MALE")) define ("IMG_EMPTY_MALE", "img/empty-male.jpg");
if (!defined("IMG_FORBIDDEN_FEMALE")) define ("IMG_FORBIDDEN_FEMALE", "img/forbidden-female.jpg");
if (!defined("IMG_FORBIDDEN_MALE")) define ("IMG_FORBIDDEN_MALE", "img/forbidden-male.jpg");
if (!defined("IMG_UNKNOWN_USER")) define ("IMG_UNKNOWN_USER", "img/unknown-user.jpg");
if (!defined("IMG_SILHOUETTE_FOR_PUBLIC")) define ("IMG_SILHOUETTE_FOR_PUBLIC", "img/silhouette.svg");

include 'config.php';
include 'main.php';

// config : recuperation des valeurs (par section)
$casIni = $conf['cas'];
$ldapIni = $conf['ldap'];

if (isset($_GET[PARAM_CAS_TEST])) {  // config avec CAS de test
	$casIni = $conf['cas.test'];
}
if (isset($_GET[PARAM_LDAP_TEST])) {  // config avec LDAP de test
	$ldapIni = $conf['ldap.test'];
}

$posAtCharacter = strpos($_GET[PARAM_UID], "@");
if ($posAtCharacter!==false) {   // il y a un caractère @ dans l'uid, on affiche directement la silhouette générique
    header("Content-type: image/svg+xml");
    readfile(IMG_SILHOUETTE_FOR_PUBLIC);
} 
else {
    // connection ldap
    $rLdap = ldapConnect($ldapIni);

    // on recherche dans le LDAP les infos de la personne authentifiée (le cas échéant)
    // sauf dans le cas où on a "penpalAffiliation=anonymous" (ex: annuaire public) 
    $userAuth = array();
    if (!isset($_GET[PARAM_PENPAL_AFFILIATION]) || ($_GET[PARAM_PENPAL_AFFILIATION]!=ANONYMOUS_VALUE)) {
         $userAuth = getAuthUserInfo($casIni, $rLdap);
    }

    // on recherche dans le LDAP les infos de la (ou des) personne(s) en fonction des paramètres passés dans l'URL
    $userPenpal = array();
    if (isset($_GET[PARAM_PENPAL])) {   // on a un parametre penpal
	   $param = ldap_escape_string($_GET[PARAM_PENPAL]);
	   $filter = LDAP_UID."=$param";
	   $userPenpal = getLdapUserInfo($rLdap, $filter);
    }
    $userParam = getParamUserInfo($rLdap);  // on a un parametre uid ou numetu (sinon, retourne un tableau vide)

    // close ldap connection
    ldapClose($rLdap);


    // on va appeler la fonction qui va afficher la photo en fonction des autorisations données
    // cette fonction prend en paramètres : 
    // 1) le user dont on doit afficher la photo
    // 2) éventuellement le user dont on doit vérifier s'il a ou pas l'autorisation de voir la photo  
    // 3) éventuellement un 2nd user dont on doit vérifier s'il a ou pas l'autorisation de voir la photo
    if (!isset($_GET[PARAM_PENPAL]) && !isset($_GET[PARAM_UID]) && !isset($_GET[PARAM_NUMETU])) { 
    	afficheUserPhoto($userAuth);   
    } elseif (!isset($_GET[PARAM_PENPAL]) && (isset($_GET[PARAM_UID]) || isset($_GET[PARAM_NUMETU]))) { 
       	afficheUserPhoto($userParam, $userAuth);   
    } elseif (isset($_GET[PARAM_PENPAL]) && !(isset($_GET[PARAM_UID]) || isset($_GET[PARAM_NUMETU])))	{
    	afficheUserPhoto($userAuth, $userPenpal);
    } elseif (isset($_GET[PARAM_PENPAL]) && (isset($_GET[PARAM_UID]) || isset($_GET[PARAM_NUMETU]))) {
    	afficheUserPhoto($userParam, $userAuth, $userPenpal);
    }
}

?>


