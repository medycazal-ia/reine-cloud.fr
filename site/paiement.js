// Liens de paiement du site. Ne contient aucun secret : ces adresses sont publiques.
// Pour activer un bouton, collez entre les guillemets l'adresse (https://…) du lien
// de paiement créé chez le prestataire de paiement. Laissez "" pour le désactiver.
//
// ATTENTION : le socle utilise son vrai lien. L'agent, le cadrage et le règlement libre pointent encore
// vers le lien de TEST à 1 € : remplacez chaque adresse par le vrai lien avant d'accepter de vrais clients.
window.LIENS_PAIEMENT = {
  socle:   "https://checkout.revolut.com/pay/9723ca4b-e730-4c82-a96d-fdd51083f966",   // Le socle (19,99 €) — lien réel
  agent:   "https://checkout.revolut.com/pay/9fe62e54-83b6-48e8-a659-daa03a1f43db",   // L'agent standard (99 € par agent)
  cadrage: "https://checkout.revolut.com/pay/9fe62e54-83b6-48e8-a659-daa03a1f43db",   // Le cadrage (199 €)
  libre:   "https://checkout.revolut.com/pay/9fe62e54-83b6-48e8-a659-daa03a1f43db"    // Règlement d'une facture ou d'un devis (montant saisi par le client)
};
