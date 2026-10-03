// Vérifie les pages publiques : boutons d'offres, pages de commande et de remerciement, aucune erreur JavaScript.
// Usage : node test_pages_publiques.js <port>   (le serveur doit servir le dossier site/)
const { chromium } = require('playwright');
const port = process.argv[2] || '8088';
const B = `http://127.0.0.1:${port}/`;
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p = await b.newPage({ viewport: { width: 1100, height: 900 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  let ko = 0; const ok = (c, m) => { if (!c) ko++; console.log((c ? 'OK   ' : 'ECHEC ') + m); };
  const liens = require('fs').readFileSync(__dirname + '/../../site/paiement.js', 'utf8');
  for (const o of ['socle', 'agent', 'cadrage', 'surmesure']) {
    await p.goto(B + 'commander.html?offre=' + o); await p.check('#accord');
    const href = await p.getAttribute('#payer', 'href');
    const lienOk = href.startsWith('https://') ? href : null;
    ok((await p.textContent('#titre-offre')).length > 0, `commander ${o} : titre affiché`);
    if (lienOk) { ok((await p.getAttribute('#payer', 'target')) === '_blank' && (await p.getAttribute('#payer', 'rel')).includes('noopener'), `commander ${o} : le paiement s'ouvre dans un nouvel onglet`); }
    ok(lienOk === null ? /""/.test(liens) : liens.includes(href), `commander ${o} : bouton ${lienOk ? 'relié au lien du site' : 'en attente de lien (e-mail)'}`);
  }
  await p.goto(B + 'merci.html?offre=socle'); ok((await p.textContent('#offre-ligne')).includes('socle'), 'merci : offre reconnue');
  await p.goto(B + 'merci.html?offre=<script>'); ok(await p.$eval('#offre-ligne', e => e.hidden), 'merci : offre inconnue masquée');
  await p.goto(B);
  const boutons = await p.$$eval('#offres a[href*="commander.html"]', a => a.length);
  ok(boutons === 4, `accueil : 4 boutons de commande (${boutons})`);
  await p.goto(B + 'payer.html');
  for (const k of ['cadrage', 'libre']) {
    ok((await p.getAttribute(`[data-lien=${k}]`, 'target')) === '_blank' && (await p.$eval(`[data-lien=${k}]`, e => !e.hidden)), `payer ${k} : nouvel onglet et bouton visible`);
  }
  ok(!liens.includes('9fe62e54'), 'plus aucun lien de test à 1 € dans paiement.js');
  for (const page of ['payer.html', 'cgv.html', 'mentions-legales.html', 'confidentialite.html']) {
    const r = await p.goto(B + page); ok(r.status() === 200, `${page} -> ${r.status()}`);
  }
  // Recherche de nom de domaine (réponse du serveur simulée)
  await p.route('**/domaine.php*', r => r.fulfill({ contentType: 'application/json', body: JSON.stringify({ nom: 'ma-boite', resultats: [
    { domaine: 'ma-boite.fr', etat: 'disponible' }, { domaine: 'ma-boite.com', etat: 'indisponible' }, { domaine: 'ma-boite.eu', etat: 'inconnu' }] }) }));
  await p.goto(B); await p.fill('#dom-q', 'Ma Boîte'); await p.click('#dom-form button');
  await p.waitForSelector('#dom-res li');
  ok((await p.$$('#dom-res li')).length === 3, 'domaine : trois résultats affichés');
  ok((await p.textContent('#dom-res')).includes('Déjà pris'), 'domaine : « Déjà pris » affiché');
  await p.click('#dom-res .reserver');
  ok((await p.inputValue('#message')).includes('ma-boite.fr'), 'domaine : « Réserver » préremplit le message');
  ok((await p.inputValue('#sujet')) === 'Hébergement / site web', 'domaine : sujet présélectionné');
  await p.unroute('**/domaine.php*');
  await p.route('**/domaine.php*', r => r.fulfill({ status: 500, body: 'x' }));
  await p.click('#dom-form button'); await p.waitForFunction(() => document.getElementById('dom-msg').textContent.includes('indisponible'));
  ok(true, 'domaine : message de repli si le service échoue');
  ok(errs.length === 0, `aucune erreur JavaScript (${errs.length})`);
  await b.close(); process.exit(ko ? 1 : 0);
})();
