-- Copyright (C) 2024 DTS SARL
-- Module monmodule — table principale
-- Remplacer monmodule → nom_technique, monobjet → nom_objet

CREATE TABLE IF NOT EXISTS llx_monmodule_monobjet (
	rowid          integer      NOT NULL AUTO_INCREMENT PRIMARY KEY,
	ref            varchar(64)  NOT NULL,
	entity         integer      NOT NULL DEFAULT 1,
	label          varchar(255)          DEFAULT NULL,
	description    text                  DEFAULT NULL,
	fk_soc         integer               DEFAULT NULL,
	fk_user_creat  integer               DEFAULT NULL,
	fk_user_modif  integer               DEFAULT NULL,
	date_creation  datetime              DEFAULT NULL,
	tms            timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	date_valid     datetime              DEFAULT NULL,
	fk_user_valid  integer               DEFAULT NULL,
	status         smallint     NOT NULL DEFAULT 0,
	note_public    text                  DEFAULT NULL,
	note_private   text                  DEFAULT NULL,
	import_key     varchar(14)           DEFAULT NULL
) ENGINE=InnoDB;
