#!/usr/bin/env bash
# Lance les tests du code serveur : unitaires (php) puis bout en bout (php + node + playwright).
# Usage : bash outils/tests/lancer-tests.sh
set -e
RACINE="$(cd "$(dirname "$0")/../.." && pwd)"
pkill -f "[p]hp -S 127.0.0.1:809" 2>/dev/null || true; pkill -f "[p]hp -S 127.0.0.1:8088" 2>/dev/null || true   # coupe un ancien serveur de test éventuel
T=/tmp/reine-cloud-tests/home
rm -rf /tmp/reine-cloud-tests && mkdir -p $T/abonnements $T/public_html/js

echo "== Tests unitaires =="
php "$RACINE/outils/tests/tests_unitaires.php" | grep -E "ECHEC|TOTAL"

php "$RACINE/outils/tests/tests_domaine.php" | grep -E "ECHEC|TOTAL"

echo "== Environnement de test (reproduit l'hébergement) =="
cp "$RACINE"/serveur/abonnements/*.php $T/abonnements/
cp -r "$RACINE/site/gestion" $T/public_html/
cp "$RACINE/site/facture.php" "$RACINE/site/recu.php" $T/public_html/
cp "$RACINE/site/js/qrcode.js" $T/public_html/js/
echo '/* ancien */' > $T/public_html/paiement.js
cat > $T/config-reine-cloud.php <<PHP
<?php return ['dsn'=>'sqlite:$T/test.sqlite','liens'=>['socle'=>'https://r.test/socle-config','libre'=>'https://r.test/libre-config']];
PHP
cat > $T/public_html/router.php <<PHP
<?php
\$GLOBALS['MAILER']=function(\$a,\$s,\$c){file_put_contents('$T/mails.log',"TO:\$a\nSUBJ:\$s\n\$c\n---\n",FILE_APPEND);return true;};
\$path=parse_url(\$_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with(\$path,'/gestion')){ if((\$_GET['sans']??'')!=='1'){\$_SERVER['REMOTE_USER']='medy';} chdir(__DIR__.'/gestion'); require __DIR__.'/gestion/index.php'; return true; }
if(\$path==='/facture.php'){ require __DIR__.'/facture.php'; return true; }
if(\$path==='/recu.php'){ require __DIR__.'/recu.php'; return true; }
if(\$path==='/js/qrcode.js'){ header('Content-Type: text/javascript'); readfile(__DIR__.'/js/qrcode.js'); return true; }
if(\$path==='/paiement.js'){ header('Content-Type: text/javascript'); readfile(__DIR__.'/paiement.js'); return true; }
return false;
PHP
sed -i "s#/tmp/reine-cloud-tests/home#$T#g" "$RACINE/outils/tests/test_bout_en_bout.js" 2>/dev/null || true

echo "== Tests bout en bout =="
( cd $T/public_html && php -S 127.0.0.1:8090 router.php >/dev/null 2>&1 & echo $! > /tmp/reine-cloud-tests/php.pid )
sleep 1
NODE_PATH="$(npm root -g)" node "$RACINE/outils/tests/test_bout_en_bout.js" | grep -E "ECHEC|!!|erreurs" || true
kill "$(cat /tmp/reine-cloud-tests/php.pid)" 2>/dev/null || true
echo "== Pages publiques =="
( cd "$RACINE/site" && php -S 127.0.0.1:8088 >/dev/null 2>&1 & echo $! > /tmp/reine-cloud-tests/php2.pid )
sleep 1
NODE_PATH="$(npm root -g)" node "$RACINE/outils/tests/test_pages_publiques.js" 8088 | grep -E "ECHEC|aucune erreur" || true
kill "$(cat /tmp/reine-cloud-tests/php2.pid)" 2>/dev/null || true
echo "Terminé : aucune ligne « ECHEC » ci-dessus = tout est bon."
