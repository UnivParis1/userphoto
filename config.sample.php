<?php 
// fichier de config

// librairies
$conf['lib']['cas'] = "lib/CAS-1.3.4/CAS.php";

// paramètres CAS
$conf['cas']['host'] = "cas.univ-paris1.fr";
$conf['cas']['port'] = "443";
$conf['cas']['uri'] = "cas";
$conf['cas.test']['host'] = "cas-test.univ-paris1.fr";
$conf['cas.test']['port'] = "443";
$conf['cas.test']['uri'] = "cas";

// paramètres de connexion à Apogee (pour la photo de l'etudiant)
$conf['apogee']['host'] = "xxxxxxxx";
$conf['apogee']['port'] = "1521";
$conf['apogee']['name'] = "APOPROD";
$conf['apogee']['user'] = "xxxxxxx";
$conf['apogee']['pwd'] = "xxxxxxx";

// paramètres de connexion au LDAP
$conf['ldap']['host'] = "ldap.univ-paris1.fr";
$conf['ldap']['port'] = "389";
$conf['ldap']['user'] = "xxxxxxxxxxxx";
$conf['ldap']['pwd'] = "xxxxxxxxxxx";
$conf['ldap.test']['host'] = "ldap-test.univ-paris1.fr";
$conf['ldap.test']['port'] = "389";
$conf['ldap.test']['user'] = "xxxxxxxxxxxx";
$conf['ldap.test']['pwd'] = "xxxxxxxxxxxxxx";

$conf['ldap.dn']['people'] = "ou=people,dc=univ-paris1,dc=fr";


?>
