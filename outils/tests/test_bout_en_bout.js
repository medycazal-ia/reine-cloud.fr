const { chromium } = require('playwright');
const { execSync } = require('child_process');
const B='http://127.0.0.1:8090/gestion/';
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
const ctx=await b.newContext({viewport:{width:1200,height:900},acceptDownloads:true});const p=await ctx.newPage();
const errs=[];p.on('pageerror',e=>errs.push(e.message));p.on('dialog',d=>d.accept());
const bad=async(nom)=>{const t=await p.content();const m=t.match(/(Warning|Fatal error|Notice|Deprecated|Parse error)[^<]{0,120}/);if(m)console.log('  !! PHP',nom,m[0]);};
const ok=(c,m)=>console.log((c?'OK  ':'ECHEC ')+m);
// 1. chaque onglet
for(const o of ['tableau','clients','demandes','paiements','compta','domaines','technique','parametres']){const r=await p.goto(B+'?o='+o);ok(r.status()===200,'onglet '+o+' -> '+r.status());await bad(o);}
// sans protection
const r0=await p.goto(B+'?sans=1');ok(r0.status()===403,'sans protection -> 403');
// 2. paramètres
await p.goto(B+'?o=parametres');await p.fill('input[name=seuil_ca]','30000');await p.click('button:has-text("Enregistrer les paramètres")');
ok((await p.textContent('.msg')).includes('enregistrés'),'paramètres enregistrés');
// 3. client
await p.goto(B+'?o=clients&nouveau=1');await p.fill('input[name=nom]','Dr Test');await p.fill('input[name=entreprise]','Cabinet Test');await p.fill('input[name=email]','cabinet@test.fr');
await p.fill('input[name=adresse]','1 rue de la Paix, 75002 Paris');await p.selectOption('select[name=statut]','client');await p.click('button:has-text("Créer")');
ok((await p.textContent('h1')).includes('Cabinet Test'),'client créé + fiche');
const cid=new URL(p.url()).searchParams.get('id');
// 4. abonnement pour ce client
await p.goto(B+'?o=paiements&client='+cid);
const fa=p.locator('h2:has-text("Nouvel abonnement") + form');
await fa.locator('input[name=montant]').fill('19,99');await fa.locator('input[name=echeance]').fill('2026-10-27');await fa.locator('button:has-text("Ajouter")').click();
ok((await p.textContent('.msg')).includes('Abonnement ajouté'),'abonnement ajouté');
await p.click('button:has-text("Payé + reçu par e-mail")');
const msg=await p.textContent('.msg');ok(msg.includes('F-2026-0001'),'paiement -> facture F-2026-0001 ('+msg.slice(0,70)+')');
ok((await p.textContent('table tr:nth-child(2) td:nth-child(4)')).includes('27/11/2026'),'échéance avancée au 27/11/2026');
const mails=require('fs').readFileSync('/tmp/reine-cloud-tests/home/mails.log','utf8');ok(mails.includes('recu.php?r=')&&mails.includes('cabinet@test.fr'),'e-mail de reçu envoyé avec lien');
ok(!mails.includes('facture.php?t=')&&!mails.includes('Cabinet Test')&&!mails.includes('Dr Test'),'e-mail SANS nom et SANS facture');
// 5. compta : facture manuelle + encaissement
await p.goto(B+'?o=compta&client='+cid);
const fc=p.locator('h2:has-text("Créer une facture") + form');
await fc.locator('input[name=objet]').fill('Cadrage agent sur mesure');await fc.locator('input[name=montant]').fill('199');await fc.locator('button:has-text("Créer la facture")').click();
ok((await p.textContent('.msg')).includes('F-2026-0002'),'facture manuelle F-2026-0002');
await p.click('tr:has-text("F-2026-0002") button:has-text("Marquer payée")');
ok((await p.textContent('.msg')).includes('payée'),'facture encaissée');
const txt=await p.textContent('body');ok(txt.includes('218,99'),'total encaissé 218,99 € affiché');
const dl=await Promise.all([p.waitForEvent('download'),p.click('a:has-text("Télécharger en CSV")')]);
const csv=require('fs').readFileSync(await dl[0].path(),'utf8');ok(csv.includes('F-2026-0001')&&csv.includes('19,99')&&csv.includes('199,00'),'export CSV livre des recettes');
// 6. facture publique
const jeton=execSync("sqlite3 /tmp/reine-cloud-tests/home/test.sqlite \"select jeton from factures where numero='F-2026-0001'\" 2>/dev/null || php -r '\$p=new PDO(\"sqlite:/tmp/reine-cloud-tests/home/test.sqlite\");echo \$p->query(\"select jeton from factures where numero=\\\"F-2026-0001\\\"\")->fetchColumn();'").toString().trim();
const pub=await ctx.newPage();const rp=await pub.goto('http://127.0.0.1:8090/facture.php?t='+jeton);
ok(rp.status()===200&&(await pub.textContent('body')).includes('ACQUITTÉE')&&(await pub.textContent('body')).includes('293 B'),'facture publique acquittée + mention TVA');
const jr=execSync("php -r '\$p=new PDO(\"sqlite:/tmp/reine-cloud-tests/home/test.sqlite\");echo \$p->query(\"select jeton_recu from factures where numero=\\\"F-2026-0001\\\"\")->fetchColumn();'").toString().trim();
const rr=await pub.goto('http://127.0.0.1:8090/recu.php?r='+jr);await pub.waitForSelector('#qr svg',{timeout:5000}).catch(()=>{});
const rt=await pub.textContent('body');
ok(rr.status()===200&&rt.includes('F-2026-0001')&&!rt.includes('Cabinet Test')&&!rt.includes('cabinet@test.fr'),'reçu public anonyme (référence, sans nom)');
ok((await pub.$$('#qr svg')).length===1&&(await pub.getAttribute('.facture a','href')).includes('facture.php?t='+jeton),'reçu : QR code + lien vers la facture');
ok((await pub.goto('http://127.0.0.1:8090/recu.php?r='+jeton)).status()===404,'le jeton de facture ne donne pas le reçu');
await p.goto(B+'?o=compta&qr=1');await p.waitForSelector('.qr svg',{timeout:5000}).catch(()=>{});
ok((await p.$$('.qr svg')).length===2,'vue admin : 2 QR codes (facture + reçu)');
const r404=await pub.goto('http://127.0.0.1:8090/facture.php?t=00000000000000000000000000000000');ok(r404.status()===404,'jeton inconnu -> 404');
// 7. paiement.js publié
await p.goto(B+'?o=paiements');
await p.fill('input[name=lien_socle]','https://checkout.revolut.com/pay/abc');await p.fill('input[name=lien_libre]','javascript:alert(1)');await p.click('button:has-text("Enregistrer et publier")');
ok((await p.textContent('.msg')).includes('https://'),'lien non https refusé');
await p.fill('input[name=lien_socle]','https://checkout.revolut.com/pay/abc');await p.fill('input[name=lien_libre]','https://checkout.revolut.com/pay/libre');await p.click('button:has-text("Enregistrer et publier")');
await p.waitForTimeout(500);const js=require('fs').readFileSync('/tmp/reine-cloud-tests/home/public_html/paiement.js','utf8');
ok(js.includes('"https://checkout.revolut.com/pay/abc"')&&js.includes('libre: "https://checkout.revolut.com/pay/libre"')&&js.includes('agent: ""'),'paiement.js publié');
await p.goto(B+'?o=paiements');const fp=p.locator('h2:has-text("ponctuel") + p + form');
await fp.locator('input[name=nom]').fill('Client X');await fp.locator('input[name=email]').fill('x@ex.fr');await fp.locator('input[name=motif]').fill('Cadrage');await fp.locator('input[name=montant]').fill('199');await fp.locator('button:has-text("Envoyer")').click();
const mp=await p.textContent('.msg');ok(mp.includes('PP-'),'lien ponctuel envoyé avec référence PP-');
const mails2=require('fs').readFileSync('/tmp/reine-cloud-tests/home/mails.log','utf8');ok(!mails2.split('---').filter(x=>x.includes('x@ex.fr')).join('').includes('Client X'),'lien ponctuel SANS nom');
// 8. demandes
execSync("php -r '\$p=new PDO(\"sqlite:/tmp/reine-cloud-tests/home/test.sqlite\");\$p->exec(\"INSERT INTO demandes (nom,email,sujet,message) VALUES (\\\"Zoe\\\",\\\"zoe@ex.fr\\\",\\\"Agent IA\\\",\\\"Bonjour\\nje voudrais un agent\\\")\");'");
await p.goto(B+'?o=demandes');ok((await p.textContent('body')).includes('zoe@ex.fr'),'demande visible');
await p.click('button:has-text("Créer le client")');ok((await p.textContent('h1')).includes('Zoe'),'client créé depuis la demande');
// 9. technique + tableau
await p.goto(B+'?o=technique');await p.click('button:has-text("Envoyer un e-mail de test")');ok((await p.textContent('.msg')).includes('test envoyé'),'e-mail de test');
const dl2=await Promise.all([p.waitForEvent('download'),p.click('tr:has-text("factures") a:has-text("Télécharger")')]);
ok(require('fs').readFileSync(await dl2[0].path(),'utf8').includes('F-2026-0002'),'export CSV table factures');
await p.goto(B+'?o=tableau');const t=await p.textContent('body');ok(t.includes('218,99')&&t.includes('19,99'),'tableau de bord chiffres');
await bad('tableau');await p.screenshot({path:'/tmp/reine-cloud-tests/tab.png',fullPage:true});
await p.goto(B+'?o=compta');await p.screenshot({path:'/tmp/reine-cloud-tests/compta.png',fullPage:true});
console.log('erreurs JS:',errs.length);await b.close();})();
