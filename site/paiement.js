// Liens de paiement du site. Ne contient aucun secret : ces adresses sont publiques.
// Pour activer un bouton, collez entre les guillemets l'adresse (https://…) du lien
// de paiement créé chez le prestataire de paiement. Laissez "" pour le désactiver.
//
// ATTENTION — MODE TEST : les quatre boutons pointent tous vers le lien de test à 1 €.
// Remplacez chaque adresse par le vrai lien de l'offre correspondante avant d'accepter
// de vrais clients.
window.LIENS_PAIEMENT = {
  socle:   "https://checkout.revolut.com/pay/9fe62e54-83b6-48e8-a659-daa03a1f43db",   // Le socle (19,99 €)
  agent:   "https://checkout.revolut.com/pay/9fe62e54-83b6-48e8-a659-daa03a1f43db",   // L'agent standard (99 € par agent)
  cadrage: "https://checkout.revolut.com/pay/9fe62e54-83b6-48e8-a659-daa03a1f43db",   // Le cadrage (199 €)
  libre:   "https://checkout.revolut.com/pay/9fe62e54-83b6-48e8-a659-daa03a1f43db"    // Règlement d'une facture ou d'un devis (montant saisi par le client)
};
