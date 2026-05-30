-- Copyright (C) 2024 DTS SARL
-- Table des lignes de détail (enfants de monobjet)

CREATE TABLE llx_monmodule_mondetail (
	rowid         INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_monobjet   INTEGER      NOT NULL,
	rang          INTEGER      NOT NULL DEFAULT 0,
	label         VARCHAR(255) NOT NULL,
	description   TEXT,
	qty           DOUBLE(24,8) NOT NULL DEFAULT 1,
	price         DOUBLE(24,8) DEFAULT NULL,
	total         DOUBLE(24,8) DEFAULT NULL,
	date_creation DATETIME     NOT NULL
) ENGINE=InnoDB;
