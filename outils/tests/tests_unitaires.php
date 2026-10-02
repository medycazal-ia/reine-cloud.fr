<?php
require dirname(__DIR__, 2) . '/serveur/abonnements/lib.php';
$ok=0;$ko=0;
function eq($nom,$a,$b){global $ok,$ko; if($a===$b){$ok++;} else {$ko++; echo "ECHEC $nom : ".json_encode($a)." != ".json_encode($b)."\n";}}
eq('fin janvier', mois_suivant('2026-01-31',31),'2026-02-28');
eq('fev->mars', mois_suivant('2026-02-28',31),'2026-03-31');
eq('normal', mois_suivant('2026-10-27',27),'2026-11-27');
eq('annee', mois_suivant('2026-12-15',15),'2027-01-15');
$e='2026-10-27';
eq('avant', etapes_dues('2026-10-19',$e,[])[0],null);
eq('J-7', etapes_dues('2026-10-20',$e,[])[0],'J-7');
eq('J-7 deja', etapes_dues('2026-10-20',$e,['J-7'])[0],null);
eq('J-1', etapes_dues('2026-10-26',$e,['J-7'])[0],'J-1');
eq('J-1 marque J-7', etapes_dues('2026-10-26',$e,[])[1],['J-7','J-1']);
eq('jour J deja J-1', etapes_dues('2026-10-27',$e,['J-7','J-1'])[0],null);
eq('J+3', etapes_dues('2026-10-30',$e,['J-7','J-1'])[0],'J+3');
eq('trop tard', etapes_dues('2026-11-30',$e,[])[0],null);

$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); db($pdo);
$env=[]; $GLOBALS['MAILER']=function($a,$s,$c) use(&$env){$env[]=[$a,$s,$c];return true;};
$cfg=['liens'=>['socle'=>'https://checkout.revolut.com/pay/socle','libre'=>'https://checkout.revolut.com/pay/libre']];
$pdo->exec("INSERT INTO abonnes (nom,email,offre,libelle,montant,jour,prochaine_echeance) VALUES ('Alice','a@x.fr','socle','Le socle',19.99,27,'2026-10-27'),('Bob','b@x.fr','agent','3 agents',250,27,'2026-10-27')");
eq('rien a J-8', count(traiter_rappels($pdo,'2026-10-19',$cfg)),0);
$r=traiter_rappels($pdo,'2026-10-20',$cfg); eq('2 envois J-7',count($r),2); eq('mails',count($env),2);
eq('lien socle', str_contains($env[0][2],'pay/socle') && !str_contains($env[0][2],'saisissez'),true);
eq('lien libre', str_contains($env[1][2],'pay/libre') && str_contains($env[1][2],'250,00'),true);
eq('pas de doublon', count(traiter_rappels($pdo,'2026-10-21',$cfg)),0);
$r=traiter_rappels($pdo,'2026-10-26',$cfg); eq('J-1 x2',count($r),2);
eq('sujet J-1', str_contains($env[2][1],'demain'),true);
marquer_paye($pdo,1);
$n=$pdo->query("SELECT prochaine_echeance FROM abonnes WHERE id=1")->fetchColumn(); eq('avance',$n,'2026-11-27');
eq('paiement enregistre',(int)$pdo->query("SELECT COUNT(*) FROM paiements")->fetchColumn(),1);
$r=traiter_rappels($pdo,'2026-10-30',$cfg); eq('J+3 seulement Bob',count($r),1); eq('J+3 Bob',str_contains($r[0],'Bob'),true);
eq('mail retard', str_contains(end($env)[2],'article 6'),true);
$pdo->exec("UPDATE abonnes SET actif=0 WHERE id=2"); $env=[];
eq('resilie', count(traiter_rappels($pdo,'2026-10-31',$cfg)),0);
$GLOBALS['MAILER']=fn()=>false; $pdo->exec("UPDATE abonnes SET actif=1, prochaine_echeance='2026-12-01' WHERE id=2");
$r=traiter_rappels($pdo,'2026-11-25',$cfg); eq('echec signale',str_contains($r[0]??'','ÉCHEC'),true);
$GLOBALS['MAILER']=function($a,$s,$c) use(&$env){$env[]=1;return true;};
eq('reessai', count(traiter_rappels($pdo,'2026-11-26',$cfg)),2);
echo "OK: $ok, ECHECS: $ko\n";
echo "--- exemple de mail J-7 (socle) ---\n"; 
// --- nouveaux tests : lien personnel, changement de prix, mode manuel, ponctuel ---
$cfg=['liens'=>['socle'=>'https://r.test/socle','libre'=>'https://r.test/libre'],'montant_socle'=>19.99];
eq('socle prix normal', lien_pour(['offre'=>'socle','montant'=>19.99,'lien_perso'=>''],$cfg),['https://r.test/socle',false]);
eq('socle prix change -> libre', lien_pour(['offre'=>'socle','montant'=>25,'lien_perso'=>''],$cfg),['https://r.test/libre',true]);
eq('lien perso prioritaire', lien_pour(['offre'=>'socle','montant'=>19.99,'lien_perso'=>'https://r.test/perso'],$cfg),['https://r.test/perso',false]);
eq('lien perso invalide ignore', lien_pour(['offre'=>'agent','montant'=>99,'lien_perso'=>'javascript:alert(1)'],$cfg),['https://r.test/libre',true]);
eq('lien_valide http refuse', lien_valide('http://x.fr'),false);
eq('lien_valide https ok', lien_valide('https://checkout.revolut.com/pay/abc'),true);
$p2=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); db($p2);
$env2=[]; $GLOBALS['MAILER']=function($a,$s,$c) use(&$env2){$env2[]=$a;return true;};
$p2->exec("INSERT INTO abonnes (nom,email,offre,libelle,montant,jour,prochaine_echeance,mode) VALUES ('Manuel','m@x.fr','socle','Le socle',19.99,27,'2026-10-27','manuel'),('Auto','a@x.fr','socle','Le socle',19.99,27,'2026-10-27','auto')");
eq('mode manuel non envoye', count(traiter_rappels($p2,'2026-10-26',$cfg)),1); eq('seul auto', $env2,['a@x.fr']);
[$s,$c]=mail_ponctuel('Cadrage',199.0,'https://r.test/libre',true,'PP-20260929-ABCD'); eq('ponctuel montant libre', str_contains($c,'saisissez exactement le montant : 199,00 €'),true); eq('ponctuel sans nom', !str_contains($c,'Cabinet') && str_contains($c,'PP-20260929-ABCD'),true);
journaliser($p2,'test','x@y.fr','detail'); eq('journal', (int)$p2->query('SELECT COUNT(*) FROM journal')->fetchColumn(),1);
// migration : ancienne base sans les colonnes
$p3=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p3->exec("CREATE TABLE abonnes (id INTEGER PRIMARY KEY AUTOINCREMENT, nom VARCHAR(120) NOT NULL, email VARCHAR(200) NOT NULL, offre VARCHAR(20) NOT NULL, libelle VARCHAR(120) NOT NULL, montant DECIMAL(8,2) NOT NULL, jour INT NOT NULL, prochaine_echeance DATE NOT NULL, actif INT NOT NULL DEFAULT 1, cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
creer_tables($p3); $cols=array_column($p3->query("PRAGMA table_info(abonnes)")->fetchAll(PDO::FETCH_ASSOC),'name'); eq('migration colonnes', in_array('mode',$cols)&&in_array('lien_perso',$cols),true);
// --- e-mails d'échéance sans nom ni intitulé libre ---
$a=['id'=>7,'nom'=>'Cabinet Secret','libelle'=>'Consultations cardiologie','offre'=>'agent','montant'=>99,'prochaine_echeance'=>'2026-10-27'];
foreach(['J-7','J-1','J+3'] as $e){[$su,$co]=construire_mail($a,$e,'https://r.test/x',false);
  eq("mail $e sans nom", !str_contains($su.$co,'Cabinet Secret') && !str_contains($su.$co,'cardiologie'),true);
  eq("mail $e reference", str_contains($su,'AB-0007'),true);}
// --- reçu anonyme, facture par lien ---
$p4=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); db($p4);
$env4=[]; $GLOBALS['MAILER']=function($a,$s,$c) use(&$env4){$env4[]=[$a,$s,$c];return true;};
$p4->exec("INSERT INTO abonnes (nom,email,offre,libelle,montant,jour,prochaine_echeance) VALUES ('Cabinet Secret','s@x.fr','socle','Consultations',19.99,27,'2026-10-27')");
rattacher_clients($p4);
$fid=marquer_paye($p4,1,true); $f=facture_par_id($p4,$fid);
eq('facture payee', $f['statut'],'payee'); eq('jeton recu rempli', strlen($f['jeton_recu']),32); eq('jetons distincts', $f['jeton']!==$f['jeton_recu'],true);
eq('recu envoye', count($env4),1); $m=$env4[0];
eq('recu sans nom', !str_contains($m[1].$m[2],'Cabinet Secret') && !str_contains($m[2],'Consultations'),true);
eq('recu contient lien recu', str_contains($m[2],'recu.php?r='.$f['jeton_recu']),true);
eq('recu ne contient PAS la facture', !str_contains($m[2],'facture.php'),true);
eq('recu reference commande', str_contains($m[2],$f['numero']),true);
$html=recu_html($f); eq('page recu anonyme', !str_contains($html,'Cabinet Secret'),true);
eq('page recu a le lien facture', str_contains($html,'facture.php?t='.$f['jeton']),true);
eq('facture nominative', str_contains(facture_html($f),'Cabinet Secret'),true);
$p4->exec("INSERT INTO factures (numero,date_facture,nom,objet,montant,statut,jeton) VALUES ('F-2026-0099','2026-01-01','X','Y',1,'emise','abc')");
creer_tables($p4); eq('rattrapage jeton_recu', strlen((string)$p4->query("SELECT jeton_recu FROM factures WHERE numero='F-2026-0099'")->fetchColumn()),32);
eq('recu refuse si non payee', envoyer_recu($p4,(int)$p4->query("SELECT id FROM factures WHERE numero='F-2026-0099'")->fetchColumn()),false);
echo "TOTAL apres ajouts -> OK: $ok, ECHECS: $ko\n";
// --- lecture des liens publiés (ne rien effacer par erreur) ---
$tmpjs=sys_get_temp_dir().'/paiement-test.js';
file_put_contents($tmpjs,"window.LIENS_PAIEMENT = {\n  socle: \"https://checkout.revolut.com/pay/abc\",\n  agent: \"\",\n  cadrage: \"javascript:alert(1)\",\n  libre: \"https://checkout.revolut.com/pay/zzz\"\n};\n");
$l=lire_paiement_js($tmpjs); eq('lecture socle',$l['socle'],'https://checkout.revolut.com/pay/abc'); eq('lecture agent vide',$l['agent'],'');
eq('lecture lien douteux ignoré',$l['cadrage'],''); eq('lecture libre',$l['libre'],'https://checkout.revolut.com/pay/zzz');
eq('fichier absent',lire_paiement_js('/chemin/inexistant.js'),[]);
echo "TOTAL liens -> OK: $ok, ECHECS: $ko\n";
