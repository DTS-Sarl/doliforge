-- Copyright (C) 2024 DTS SARL
-- Index et contraintes pour mondetail

ALTER TABLE llx_monmodule_mondetail ADD INDEX idx_mondetail_fk_monobjet (fk_monobjet);
ALTER TABLE llx_monmodule_mondetail ADD INDEX idx_mondetail_rang (fk_monobjet, rang);
ALTER TABLE llx_monmodule_mondetail ADD CONSTRAINT fk_mondetail_monobjet FOREIGN KEY (fk_monobjet) REFERENCES llx_monmodule_monobjet(rowid) ON DELETE CASCADE;
