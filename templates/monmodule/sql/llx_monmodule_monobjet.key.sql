-- Copyright (C) 2024 DTS SARL
-- Module monmodule — index

ALTER TABLE llx_monmodule_monobjet ADD UNIQUE INDEX uk_monmodule_monobjet_ref (ref, entity);
ALTER TABLE llx_monmodule_monobjet ADD INDEX idx_monmodule_monobjet_entity (entity);
ALTER TABLE llx_monmodule_monobjet ADD INDEX idx_monmodule_monobjet_fk_soc (fk_soc);
ALTER TABLE llx_monmodule_monobjet ADD INDEX idx_monmodule_monobjet_status (entity, status);

ALTER TABLE llx_monmodule_monobjet ADD CONSTRAINT fk_monmodule_monobjet_soc
	FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
