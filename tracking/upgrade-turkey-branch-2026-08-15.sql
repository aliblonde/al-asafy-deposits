-- Run once on an existing Treandy Logistics database.
ALTER TABLE shipments
MODIFY status ENUM('turkey_branch','dubai_branch','shipping_to_erbil','erbil_warehouse','delivered')
NOT NULL DEFAULT 'turkey_branch';
