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
  // Code parrain sur la page de commande (réponse du serveur simulée)
  await p.route('**/code.php*', r => {
    const u = new URL(r.request().url());
    if (r.request().method() === 'POST') { return r.fulfill({ contentType: 'application/json', body: '{"ok":true}' }); }
    const c = (u.searchParams.get('code') || '').toUpperCase();
    const corps = c === 'AMIS2026' ? { valide: true, code: c, offre: 'socle', pourcentage: 20, prix_initial: 19.99, prix_remise: 15.99, gratuit: false }
      : c === 'OFFERT100' ? { valide: true, code: c, offre: 'socle', pourcentage: 100, prix_initial: 19.99, prix_remise: 0, gratuit: true } : { valide: false };
    r.fulfill({ contentType: 'application/json', body: JSON.stringify(corps) });
  });
  await p.goto(B + 'commander.html?offre=socle'); await p.click('#ouvrir-code'); await p.fill('#code', 'faux'); await p.click('#form-code button');
  await p.waitForFunction(() => document.getElementById('msg-code').textContent.includes('pas valide')); ok(true, 'code parrain : code faux refusé');
  await p.fill('#code', 'amis2026'); await p.click('#form-code button'); await p.waitForFunction(() => document.getElementById('msg-code').textContent.includes('−20'));
  ok((await p.textContent('#prix')).includes('15,99'), 'code parrain : prix remisé affiché'); ok((await p.textContent('#prix')).includes('19,99'), 'code parrain : ancien prix barré');
  await p.check('#accord'); ok(/^https:/.test(await p.getAttribute('#payer', 'href')), 'code parrain : lien de paiement libre utilisé');
  ok((await p.textContent('#aide')).includes('exactement') && (await p.textContent('#aide')).includes('15,99'), 'code parrain : consigne du montant exact');
  await p.goto(B + 'commander.html?offre=socle&code=OFFERT100'); await p.waitForFunction(() => document.getElementById('msg-code').textContent.includes('offerte'));
  await p.check('#accord'); ok((await p.getAttribute('#payer', 'href')).startsWith('mailto:'), 'code 100 % : pas de paiement, message prêt');
  ok((await p.textContent('#prix')).includes('Offert'), 'code 100 % : « Offert » affiché');
  await p.goto(B + 'commander.html?offre=surmesure'); ok(await p.isHidden('#zone-code'), 'sur mesure : pas de code parrain');
  await p.unroute('**/code.php*');
  ok(errs.length === 0, `aucune erreur JavaScript (${errs.length})`);
  await b.close(); process.exit(ko ? 1 : 0);
})();
