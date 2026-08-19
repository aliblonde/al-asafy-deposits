-- Add address and notes columns to investors table
ALTER TABLE `investors`
  ADD COLUMN `address` VARCHAR(255) NOT NULL DEFAULT '' AFTER `city`,
  ADD COLUMN `notes`   TEXT         NULL               AFTER `address`;
