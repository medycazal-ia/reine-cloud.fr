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
    ok(lienOk === null ? /""/.test(liens) : liens.includes(href), `commander ${o} : bouton ${lienOk ? 'relié au lien du site' : 'en attente de lien (e-mail)'}`);
  }
  await p.goto(B + 'merci.html?offre=socle'); ok((await p.textContent('#offre-ligne')).includes('socle'), 'merci : offre reconnue');
  await p.goto(B + 'merci.html?offre=<script>'); ok(await p.$eval('#offre-ligne', e => e.hidden), 'merci : offre inconnue masquée');
  await p.goto(B);
  const boutons = await p.$$eval('#offres a[href*="commander.html"]', a => a.length);
  ok(boutons === 4, `accueil : 4 boutons de commande (${boutons})`);
  for (const page of ['payer.html', 'cgv.html', 'mentions-legales.html', 'confidentialite.html']) {
    const r = await p.goto(B + page); ok(r.status() === 200, `${page} -> ${r.status()}`);
  }
  ok(errs.length === 0, `aucune erreur JavaScript (${errs.length})`);
  await b.close(); process.exit(ko ? 1 : 0);
})();
