-- Base de données de reine-cloud.fr (MySQL / MariaDB)
-- À coller dans phpMyAdmin, onglet « SQL », après avoir sélectionné la base.

CREATE TABLE IF NOT EXISTS demandes (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cree_le   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  nom       VARCHAR(100)  NOT NULL,
  email     VARCHAR(200)  NOT NULL,
  sujet     VARCHAR(100)  NOT NULL,
  message   TEXT          NOT NULL,
  statut    ENUM('nouvelle','traitee','archivee') NOT NULL DEFAULT 'nouvelle',
  INDEX idx_cree_le (cree_le),
  INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purge des demandes de plus de 3 ans (à lancer de temps en temps,
-- ou via une tâche Cron dans cPanel) :
-- DELETE FROM demandes WHERE cree_le < (NOW() - INTERVAL 3 YEAR);
