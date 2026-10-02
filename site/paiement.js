// Liens de paiement du site. Ne contient aucun secret : ces adresses sont publiques.
// Pour activer un bouton, collez entre les guillemets l'adresse (https://…) du lien de
// paiement créé chez le prestataire de paiement. Laissez "" pour le désactiver.
//
// ATTENTION : le « règlement libre » pointe encore vers le lien de TEST à 1 € ; remplacez-le
// par le vrai lien avant de l'utiliser avec de vrais clients.
window.LIENS_PAIEMENT = {
  socle:     "https://checkout.revolut.com/pay/9723ca4b-e730-4c82-a96d-fdd51083f966",   // Le socle (19,99 €)
  agent:     "https://checkout.revolut.com/pay/e378f0a9-10db-4f94-8de5-f4d60e288904",   // L'agent standard (99 € par agent)
  cadrage:   "https://checkout.revolut.com/pay/1d7507b4-e171-4c52-9485-d0d6e431e1cb",   // Le cadrage (199 €)
  surmesure: "https://checkout.revolut.com/pay/69690293-5807-4690-bba1-ec8ab45da22c",   // Le sur mesure
  libre:     "https://checkout.revolut.com/pay/9fe62e54-83b6-48e8-a659-daa03a1f43db"    // Règlement d'une facture ou d'un devis (lien de TEST à 1 €)
};
