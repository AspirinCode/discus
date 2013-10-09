CREATE TABLE {project_prefix}docking_assays (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8_bin NOT NULL,
  `desc` text COLLATE utf8_bin NOT NULL,
  `docking_target_id` int(11) NOT NULL,
  `organism_id` int(11) NOT NULL,
  `biotarget_id` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `csv_file` blob,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_assays_attachments (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assay_id` int(11) NOT NULL,
  `name` varchar(250) COLLATE utf8_bin NOT NULL,
  `type` varchar(100) COLLATE utf8_bin NOT NULL,
  `file` longblob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `assay_id` (`assay_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_assays_biotargets (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8_bin NOT NULL,
  `desc` text COLLATE utf8_bin NOT NULL,
  `annotations` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_assays_data (
  `assay_id` int(11) NOT NULL,
  `mol_id` int(11) NOT NULL,
  `act_type` varchar(5) COLLATE utf8_bin NOT NULL,
  `act_operator` tinyint(4) NOT NULL,
  `act_value` float DEFAULT NULL,
  `act_value_lower` float DEFAULT NULL,
  `act_value_upper` float DEFAULT NULL,
  `comment` text COLLATE utf8_bin NOT NULL,
  KEY `assay_id` (`assay_id`),
  KEY `mol_id` (`mol_id`),
  KEY `act_value_lower` (`act_value_lower`),
  KEY `act_value_upper` (`act_value_upper`),
  KEY `act_value` (`act_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_assays_organisms (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8_bin NOT NULL,
  `desc` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_assays_types (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) COLLATE utf8_bin NOT NULL,
  `desc` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_conformations (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mol_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `mol2` longblob NOT NULL,
  `ligand_subset` int(11) NOT NULL,
  `import_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mol_id` (`mol_id`),
  KEY `target_id` (`target_id`),
  KEY `name` (`name`),
  KEY `ligand_db` (`ligand_subset`),
  KEY `import_id` (`import_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE {project_prefix}docking_conformations_import (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subset` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `filename` varchar(200) COLLATE utf8_bin NOT NULL,
  `file` longblob NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_conformations_interactions (
  `id` int(11) NOT NULL,
  `mol_id` int(11) NOT NULL,
  `hbond_num` tinyint(4) NOT NULL,
  `hbond_crude_num` tinyint(4) NOT NULL,
  `hbond_acceptor` varchar(250) DEFAULT NULL,
  `hbond_acceptor_crude` varchar(250) DEFAULT NULL,
  `hbond_donor` varchar(250) DEFAULT NULL,
  `hbond_donor_crude` varchar(250) DEFAULT NULL,
  `salt_bridges_num` tinyint(4) NOT NULL,
  `salt_bridges` varchar(250) DEFAULT NULL,
  `hydrophobic_contacts_num` tinyint(4) NOT NULL,
  `hydrophobic_contacts` varchar(250) DEFAULT NULL,
  `pi_stacking_num` tinyint(4) NOT NULL,
  `pi_stacking` varchar(250) DEFAULT NULL,
  `pi_cation_num` tinyint(4) NOT NULL,
  `pi_cation` varchar(250) DEFAULT NULL,
  `metal_coordination_num` tinyint(4) NOT NULL,
  `metal_coordination_crude_num` tinyint(4) NOT NULL,
  `metal_coordination` varchar(250) DEFAULT NULL,
  `metal_coordination_crude` varchar(250) DEFAULT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `hbond_num` (`hbond_num`),
  KEY `hydrophobic_contacts_num` (`hydrophobic_contacts_num`),
  KEY `pi_stacking_num` (`pi_stacking_num`),
  KEY `pi_cation_num` (`pi_cation_num`),
  KEY `metal_coordination_num` (`metal_coordination_num`),
  KEY `hbond_crude_num` (`hbond_crude_num`),
  KEY `metal_coordination_crude_num` (`metal_coordination_crude_num`),
  KEY `hbond_acceptor` (`hbond_acceptor`),
  KEY `hbond_acceptor_crude` (`hbond_acceptor_crude`),
  KEY `hbond_donor` (`hbond_donor`),
  KEY `hbond_donor_crude` (`hbond_donor_crude`),
  KEY `salt_bridges` (`salt_bridges`),
  KEY `hydrophobic_contacts` (`hydrophobic_contacts`),
  KEY `pi_stacking` (`pi_stacking`),
  KEY `pi_cation` (`pi_cation`),
  KEY `metal_coordination` (`metal_coordination`),
  KEY `metal_coordination_crude` (`metal_coordination_crude`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE {project_prefix}docking_conformations_postrm (
  `conf_id` int(11) NOT NULL,
  `mol2` longblob NOT NULL,
  UNIQUE KEY `conf_id` (`conf_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_conformations_properties (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_ligand_subset (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_bin NOT NULL,
  `description` text COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_ligand_subset_members (
  `ligand_subset_id` int(11) NOT NULL,
  `mol_id` int(11) NOT NULL,
  UNIQUE KEY `unique_mols` (`ligand_subset_id`,`mol_id`),
  KEY `mol_id` (`mol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_molecules (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8_bin NOT NULL,
  `raw_smiles` text COLLATE utf8_bin NOT NULL,
  `smiles` text COLLATE utf8_bin NOT NULL,
  `inchikey` varchar(29) COLLATE utf8_bin NOT NULL,
  `raw_mol2` blob NOT NULL,
  `mol2` blob NOT NULL,
  `fp2` blob NOT NULL,
  `obmol` blob NOT NULL,
  `sdf` blob NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `inchikey` (`inchikey`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_molecules_properties (
  `id` int(11) NOT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE {project_prefix}docking_properties (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `field` varchar(50) COLLATE utf8_bin NOT NULL,
  `name` varchar(50) COLLATE utf8_bin NOT NULL,
  `prefix` varchar(20) COLLATE utf8_bin NOT NULL,
  `description` varchar(250) COLLATE utf8_bin NOT NULL,
  `type` tinyint(4) NOT NULL,
  `order` smallint(6) NOT NULL,
  `sort_asc` tinyint(1) NOT NULL DEFAULT '1',
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `type` (`type`,`order`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_targets (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `name` varchar(200) COLLATE utf8_bin NOT NULL,
  `mol2` longtext COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_user_subset (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_bin NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE {project_prefix}docking_user_subset_members (
  `user_subset_id` int(11) NOT NULL,
  `conf_id` int(11) NOT NULL,
  UNIQUE KEY `user_subset_id` (`user_subset_id`,`conf_id`),
  KEY `conf_id` (`conf_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE {project_prefix}docking_assays_attachments
  ADD CONSTRAINT {project_prefix}docking_assays_attachments_ibfk_1 FOREIGN KEY (`assay_id`) REFERENCES {project_prefix}docking_assays (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE {project_prefix}docking_assays_data
  ADD CONSTRAINT {project_prefix}docking_assays_data_ibfk_1 FOREIGN KEY (`assay_id`) REFERENCES {project_prefix}docking_assays (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT {project_prefix}docking_assays_data_ibfk_2 FOREIGN KEY (`mol_id`) REFERENCES {project_prefix}docking_molecules (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE {project_prefix}docking_conformations
  ADD CONSTRAINT {project_prefix}docking_conformations_ibfk_1 FOREIGN KEY (`mol_id`) REFERENCES {project_prefix}docking_molecules (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT {project_prefix}docking_conformations_ibfk_2 FOREIGN KEY (`target_id`) REFERENCES {project_prefix}docking_targets (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT {project_prefix}docking_conformations_ibfk_3 FOREIGN KEY (`ligand_subset`) REFERENCES {project_prefix}docking_ligand_subset (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT {project_prefix}docking_conformations_ibfk_4 FOREIGN KEY (`import_id`) REFERENCES {project_prefix}docking_conformations_import (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE {project_prefix}docking_conformations_postrm
  ADD CONSTRAINT {project_prefix}docking_conformations_postrm_ibfk_1 FOREIGN KEY (`conf_id`) REFERENCES {project_prefix}docking_conformations (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE {project_prefix}docking_conformations_properties
  ADD CONSTRAINT {project_prefix}docking_conformations_properties_ibfk_1 FOREIGN KEY (`id`) REFERENCES {project_prefix}docking_conformations (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE {project_prefix}docking_ligand_subset_members
  ADD CONSTRAINT {project_prefix}docking_ligand_subset_members_ibfk_2 FOREIGN KEY (`mol_id`) REFERENCES {project_prefix}docking_molecules (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT {project_prefix}docking_ligand_subset_members_ibfk_3 FOREIGN KEY (`ligand_subset_id`) REFERENCES {project_prefix}docking_ligand_subset (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE {project_prefix}docking_molecules_properties
  ADD CONSTRAINT {project_prefix}docking_molecules_properties_ibfk_1 FOREIGN KEY (`id`) REFERENCES {project_prefix}docking_molecules (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE {project_prefix}docking_user_subset_members
  ADD CONSTRAINT {project_prefix}docking_user_subset_members_ibfk_1 FOREIGN KEY (`user_subset_id`) REFERENCES {project_prefix}docking_user_subset (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT {project_prefix}docking_user_subset_members_ibfk_2 FOREIGN KEY (`conf_id`) REFERENCES {project_prefix}docking_conformations (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
