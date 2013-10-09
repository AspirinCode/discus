#/usr/bin/python
"""
DiSCuS - Database System for Compound Selection
Copyright (C) 2012-2013  Maciej Wojcikowski <maciek@wojcikowski.pl>

This file is part of DiSCuS.

DiSCuS is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

DiSCuS is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with DiSCuS.  If not, see <http://www.gnu.org/licenses/>.
"""

import numpy as np
import MySQLdb as mysql
from math import ceil
from openbabel import OBAtomAtomIter
from scipy.spatial.distance import cdist as distance
#from sklearn.metrics.pairwise import euclidean_distances as distance
import pybel
from optparse import OptionParser


from time import time
import sys

import re
# auto import settings from config.php
config = {}
for line in open('config.php'):
	m = re.search(r"\$CONFIG\[[\'\"]{1}([\w\d]+)[\'\"]{1}\]\s+=\s+[\'\"]{1}([\w+\d/]+)[\'\"]{1}", line)
	if m is not None:
		match = m.groups()
		config[match[0]] = match[1]

mysql_host 	= config['db_host']
mysql_user 	= config['db_user']
mysql_pass 	= config['db_pass']
mysql_database	= config['db_name']

# define cutoffs
hbond_cutoff = 3.5
hbond_tolerance = 30
salt_bridge_cutoff = 4
hydrophobe_cutoff = 4
pi_cutoff = 5
pi_cation_cutoff = 4
pi_tolerance = 30

#parse options
parser = OptionParser()
parser.add_option("-p", "--project", type="int", dest="project_id")
parser.add_option("-s", "--subset", type="int", dest="subset_ids", action="append")
parser.add_option("-t", "--target", type="int", dest="target_ids", action="append")
(options, args) = parser.parse_args()

project_id = options.project_id
subset_ids = options.subset_ids
target_ids = options.target_ids
#project_id = 1
#target_ids = [2,3,4]

# check if all we need is set
if not project_id or len(target_ids) < 1:
	sys.exit('Some arguments needed!')

if subset_ids and len(subset_ids) > 0:
	subset_sql = ' AND ligand_subset IN (%s)' % ','.join(str(sid) for sid in subset_ids)
else:
	subset_sql = ''

def angle(v1, v2):
	""" Return angle between two vectors in degrees """
	v1 = np.array(v1).flatten()
	v2 = np.array(v2).flatten()
	return np.degrees(np.arccos(np.dot(v1, v2)/(np.linalg.norm(v1)* np.linalg.norm(v2))))

if __name__ == "__main__":
	
	pybel.ob.obErrorLog.StopLogging()
	
	conn = mysql.connect(mysql_host, mysql_user, mysql_pass)
	c = conn.cursor(mysql.cursors.DictCursor)
	
	
	for target_id in target_ids:
		print "Starting target #" + str(target_id)
		#get target mol2
		c.execute("SELECT mol2 FROM `" + mysql_database + "`.`project_%i_docking_targets` WHERE id = %i LIMIT 1;" % (project_id, target_id))
		target_mol2 = c.fetchone()
		protein = pybel.readstring("mol2", target_mol2['mol2'])
		#protein.addh()
	
		# search for hydrophobic aa and H donors and acceptors in protein
		protein_acceptors = []
		protein_acceptors_res = []
		protein_acceptors_nbr = []
		protein_donors = []
		protein_donors_res = []
		protein_donors_nbr = []
		protein_salt_plus = []
		protein_salt_plus_res = []
		protein_salt_minus = []
		protein_salt_minus_res = []

		protein_hydrophobe = []
		protein_hydrophobe_res = []
		
		protein_metal = []
		protein_metal_res = []
	
		for residue in protein.residues:
	#		if residue.OBResidue.GetAminoAcidProperty(8): # get hydrophobic aa
			for atom in residue:
				# get 
				if residue.OBResidue.GetAminoAcidProperty(8) and atom.atomicnum == 6:
					protein_hydrophobe.append(atom.coords)
					protein_hydrophobe_res.append(residue.name[:3]+str(residue.OBResidue.GetNum()))
				if atom.OBAtom.IsHbondDonor():
					protein_donors.append(atom.coords)
					protein_donors_res.append(residue.name[:3]+str(residue.OBResidue.GetNum()))
					nbr = []
					for nbr_atom in [pybel.Atom(x) for x in OBAtomAtomIter(atom.OBAtom)]:
						if not nbr_atom.OBAtom.IsHbondDonorH():
							nbr.append(nbr_atom.coords)
					protein_donors_nbr.append(nbr)
					if atom.type in ['N3+', 'N2+', 'Ng+']: # types from mol2 and Chimera, ref: http://www.cgl.ucsf.edu/chimera/docs/UsersGuide/idatm.html
						protein_salt_plus.append(atom.coords)
						protein_salt_plus_res.append(residue.name[:3]+str(residue.OBResidue.GetNum()))
				if atom.OBAtom.IsHbondAcceptor():
					protein_acceptors.append(atom.coords)
					protein_acceptors_res.append(residue.name[:3]+str(residue.OBResidue.GetNum()))
					nbr = []
					for nbr_atom in [pybel.Atom(x) for x in OBAtomAtomIter(atom.OBAtom)]:
						if not nbr_atom.OBAtom.IsHbondDonorH():
							nbr.append(nbr_atom.coords)
					protein_acceptors_nbr.append(nbr)
					if atom.type in ['O3-', '02-' 'O-']: # types from mol2 and Chimera, ref: http://www.cgl.ucsf.edu/chimera/docs/UsersGuide/idatm.html
						protein_salt_minus.append(atom.coords)
						protein_salt_minus_res.append(residue.name[:3]+str(residue.OBResidue.GetNum()))
				if atom.OBAtom.IsMetal():
					protein_metal.append(atom.coords)
					protein_metal_res.append(residue.name[:3]+str(residue.OBResidue.GetNum()))
					
		# search for pi's in protein
		protein_pi = []
		protein_pi_vec = []
		protein_pi_res =[]
		for ring in protein.sssr:
			#if ring.IsAromatic():
			residue = protein.atoms[ring._path[0]-1].OBAtom.GetResidue()
			if residue.GetAminoAcidProperty(3): # get aromatic aa
				ring_coords = []
				for atom_idx in ring._path:
					ring_coords.append(protein.atoms[atom_idx-1].coords)
				ring_coords = np.array(ring_coords)
				middle = np.mean(np.array(ring_coords), axis=0)
				# get mean perpendicular vector to the ring
				ring_vector = []
				for i in range(len(ring_coords)):
					ring_vector.append(np.cross([ring_coords[i] - ring_coords[i-1]],[ring_coords[i-1] - ring_coords[i-2]]))
				protein_pi.append(middle)
				protein_pi_vec.append(middle + np.mean(ring_vector, axis=0))
				protein_pi_res.append(residue.GetName()[:3]+str(residue.GetNum()))
		
		protein_pi = np.array(protein_pi)
		protein_pi_vec = np.array(protein_pi_vec)

		#sys.exit()
		
		# create np.arrays once
		protein_donors = np.array(protein_donors)
		protein_acceptors = np.array(protein_acceptors)
		protein_salt_plus = np.array(protein_salt_plus)
		protein_salt_minus = np.array(protein_salt_minus)
		protein_hydrophobe = np.array(protein_hydrophobe)
		protein_metal = np.array(protein_metal)
		
		c.execute("SELECT count(id) as c FROM `" + mysql_database + "`.`project_%i_docking_conformations` WHERE `target_id` = %i %s;" % (project_id, target_id, subset_sql))
		num = c.fetchone()['c']

		chunk = 1000
		n = 0 # counter for time statistics
		for chunk_n in range(int(ceil(float(num)/chunk))):
			sql = []
			#conn.ping()
			c.execute("SELECT id, mol_id, UNCOMPRESS(mol2) as mol2 FROM `" + mysql_database + "`.`project_%i_docking_conformations` WHERE `target_id` = %i AND mol2 != '' %s ORDER BY id LIMIT %i, %i;" % (project_id, target_id, subset_sql, chunk_n*chunk, chunk))
			start = time()
			for ligand_row in c.fetchall():			
				ligand = pybel.readstring('mol2', ligand_row['mol2'])
				#ligand.addh()
			
				# find hydrophobic atoms, H donors and acceptors in ligand
				ligand_acceptors = []
				ligand_acceptors_nbr = []
				ligand_donors = []
				ligand_donors_nbr = []
				
				hbond_donor = []
				hbond_donor_crude = []
				hbond_acceptor = []
				hbond_acceptor_crude = []
			
				ligand_salt_plus = []
				ligand_salt_minus = []
				salt_bridges = []
			
				ligand_hydrophobe = []
				hydrophobic_contacts = []
				
				ligand_metal = []
			
				for atom in ligand:
					if atom.atomicnum == 6:
						hydrophobe = True
						for nbr_atom in OBAtomAtomIter(atom.OBAtom):
							if nbr_atom.GetAtomicNum() != 6 and nbr_atom.GetAtomicNum() != 1:
								hydrophobe = False
								break
						if hydrophobe:
							ligand_hydrophobe.append(atom.coords)
					if atom.OBAtom.IsHbondDonor():
						ligand_donors.append(atom.coords)
						nbr = []
						for nbr_atom in [pybel.Atom(x) for x in OBAtomAtomIter(atom.OBAtom)]:
							if not nbr_atom.OBAtom.IsHbondDonorH():
								nbr.append(nbr_atom.coords)
						ligand_donors_nbr.append(nbr)
								
						if atom.type in ['N3+', 'N2+', 'Ng+']: # types from mol2 and Chimera, ref: http://www.cgl.ucsf.edu/chimera/docs/UsersGuide/idatm.html
							ligand_salt_plus.append(atom.coords)
					
					if atom.OBAtom.IsHbondAcceptor():
						ligand_acceptors.append(atom.coords)
						nbr = []
						for nbr_atom in [pybel.Atom(x) for x in OBAtomAtomIter(atom.OBAtom)]:
							if not nbr_atom.OBAtom.IsHbondDonorH():
								nbr.append(nbr_atom.coords)
						ligand_acceptors_nbr.append(nbr)
						if atom.type in ['O3-', '02-' 'O-']: # types from mol2 and Chimera, ref: http://www.cgl.ucsf.edu/chimera/docs/UsersGuide/idatm.html
							ligand_salt_minus.append(atom.coords)
					if atom.OBAtom.IsMetal():
						ligand_metal.append(atom.coords)
				
				ligand_donors = np.array(ligand_donors)
				ligand_acceptors = np.array(ligand_acceptors)
				ligand_salt_plus = np.array(ligand_salt_plus)
				ligand_salt_minus = np.array(ligand_salt_minus)
				ligand_hydrophobe = np.array(ligand_hydrophobe)
				ligand_metal = np.array(ligand_metal)
				
				# detect hbonds
				if len(ligand_donors) > 0:
					for res, lig in np.argwhere(distance(protein_acceptors, ligand_donors) < hbond_cutoff):
						hbond = True # assume that ther is hbond, than check angles
						for dn in protein_acceptors_nbr[res]:
							for an in ligand_donors_nbr[lig]:
								v_da = ligand_donors[lig] - protein_acceptors[res]
								v_ad = protein_acceptors[res] - ligand_donors[lig]
								v_dn = dn - protein_acceptors[res]
								v_an = an - ligand_donors[lig]
								# check if hbond should be discarded
								if angle(v_an, v_ad) < 120/len(protein_acceptors_nbr[res]) - hbond_tolerance or angle(v_da, v_dn) < 120/len(ligand_donors_nbr[lig]) - hbond_tolerance:
									hbond = False
									break
							if not hbond:
								break
						if hbond:
							hbond_acceptor.append(protein_acceptors_res[res])
						else:
							hbond_acceptor_crude.append(protein_acceptors_res[res])
					
				if len(ligand_acceptors) > 0:
					for res, lig in np.argwhere(distance(protein_donors, ligand_acceptors) < hbond_cutoff):
						hbond = True # assume that ther is hbond, than check angles
						for dn in protein_donors_nbr[res]:
							for an in ligand_acceptors_nbr[lig]:
								v_da = ligand_acceptors[lig] - protein_donors[res]
								v_ad = protein_donors[res] - ligand_acceptors[lig]
								v_dn = dn - protein_donors[res]
								v_an = an - ligand_acceptors[lig]
								# check if hbond should be discarded
								if angle(v_an, v_ad) < 120/len(ligand_acceptors_nbr[lig]) - hbond_tolerance or angle(v_da, v_dn) < 120/len(protein_donors_nbr[res]) - hbond_tolerance:
									hbond = False
									break
							if not hbond:
								break
						if hbond:
							hbond_donor.append(protein_donors_res[res])
						else:
							hbond_donor_crude.append(protein_donors_res[res])
			
				# detect salt bridges
				if len(ligand_salt_plus) > 0 and len(protein_salt_minus):
					for res, lig in np.argwhere(distance(protein_salt_minus, ligand_salt_plus) < salt_bridge_cutoff):
						salt_bridges.append(protein_salt_minus_res[res])
				if len(ligand_salt_minus) > 0 and len(protein_salt_plus):
					for res, lig in np.argwhere(distance(protein_salt_plus, ligand_salt_minus) < salt_bridge_cutoff):
						salt_bridges.append(protein_salt_plus_res[res])
			
				# detect hydrophobic contacts
				if len(ligand_hydrophobe) > 0:
					for res, lig in np.argwhere(distance(protein_hydrophobe, ligand_hydrophobe) < hydrophobe_cutoff):
						if protein_hydrophobe_res[res] not in hydrophobic_contacts:
							hydrophobic_contacts.append(protein_hydrophobe_res[res])			
			
				# find aromating rings in ligand
				ligand_pi = []
				ligand_pi_vec = []
				pi_stacking = []
				pi_cation = []
				metal_coordination = []
				metal_coordination_crude = []
				
				for ring in ligand.sssr:
					if ring.IsAromatic():
						ring_coords = []
						for atom_idx in ring._path:
							ring_coords.append(ligand.atoms[atom_idx-1].coords)
						ring_coords = np.array(ring_coords)
						middle = np.mean(np.array(ring_coords), axis=0)
						# get mean perpendicular vector to the ring
						ring_vector = []
						for i in range(len(ring_coords)):
							ring_vector.append(np.cross([ring_coords[i] - ring_coords[i-1]],[ring_coords[i-1] - ring_coords[i-2]]))
						ligand_pi.append(middle)
						ligand_pi_vec.append(middle + np.mean(ring_vector, axis=0))
				
				ligand_pi = np.array(ligand_pi)
			
				# detect pi-pi interactions
				if len(ligand_pi) > 0 and len(protein_pi) > 0:
					for res, lig in np.argwhere(distance(protein_pi, ligand_pi) < pi_cutoff):
		                                v_prot = protein_pi_vec[res] - protein_pi[res]
		                                v_centers = ligand_pi[lig] - protein_pi[res]
		                                v_lig = ligand_pi_vec[lig] - ligand_pi[lig]
		                                # check angles for face to face, and edge to face
		                                if (angle(v_prot, v_centers) < pi_tolerance or angle(v_prot, v_centers) > 180 - pi_tolerance) and (angle(v_lig, v_prot) < pi_tolerance or angle(v_lig, v_prot) > 180 - pi_tolerance or np.abs(angle(v_lig, v_prot) - 90) < pi_tolerance):
		                                	if protein_pi_res[res] not in pi_stacking:
								pi_stacking.append(protein_pi_res[res])
		                               
				# detect cation-pi
				if len(ligand_pi) > 0:
					for res, lig in np.argwhere(distance(protein_salt_plus, ligand_pi) < pi_cation_cutoff):
						v_pi = ligand_pi_vec[lig] - ligand_pi[lig]
						v_cat = protein_salt_plus[res] - ligand_pi[lig]
						if angle(v_pi, v_cat) < pi_tolerance or angle(v_pi, v_cat) > 180 - pi_tolerance:
				                        if protein_salt_plus_res[res] not in pi_cation:
								pi_cation.append(protein_salt_plus_res[res])
				if len(ligand_salt_plus) > 0:
					for res, lig in np.argwhere(distance(protein_pi, ligand_salt_plus) < pi_cation_cutoff):
						v_pi = protein_pi_vec[res] - protein_pi[res]
						v_cat = ligand_salt_plus[lig] - protein_pi[res]
		                        	if angle(v_pi, v_cat) < pi_tolerance or angle(v_pi, v_cat) > 180 - pi_tolerance:	
				                	if protein_pi_res[res] not in pi_cation:
								pi_cation.append(protein_pi_res[res])
				# get metal - acceptor interactions		
				if len(ligand_metal) > 0:
					for res, lig in np.argwhere(distance(protein_acceptors, ligand_metal) < hbond_cutoff):
						coord = True # assume that ther is hbond, than check angles
						for dn in protein_acceptor_nbr[res]:
							v_am = protein_acceptors[res] - ligand_metal[lig]
							v_an = an - ligand_metal[lig]
							# check if hbond should be discarded
							if angle(v_am, v_an) < 120/len(protein_acceptors_nbr[res]) - hbond_tolerance:
								coord = False
								break
								
						if coord:
							metal_coordination.append(protein_acceptor_res[res])
						else:
							metal_coordination_crude.append(protein_acceptor_res[res])
				if len(protein_metal) > 0 and len(ligand_acceptors) > 0:
					for res, lig in np.argwhere(distance(protein_metal, ligand_acceptors) < hbond_cutoff):
						coord = True # assume that ther is hbond, than check angles
						for an in ligand_acceptors_nbr[lig]:
							v_am = ligand_acceptors[lig] - protein_metal[res]
							v_an = an - protein_metal[res]
							# check if hbond should be discarded
							if angle(v_am, v_an) < 120/len(ligand_acceptors_nbr[lig]) - hbond_tolerance:
								coord = False
								break
								
						if coord:
							metal_coordination.append(protein_metal_res[res])
						else:
							metal_coordination_crude.append(protein_metal_res[res])
				# get metal - pi interactions
				if len(ligand_pi) > 0 and len(protein_metal) > 0:
					for res, lig in np.argwhere(distance(protein_metal, ligand_pi) < pi_cation_cutoff):
						v_pi = ligand_pi_vec[lig] - ligand_pi[lig]
						v_cat = protein_metal[res] - ligand_pi[lig]
						if angle(v_pi, v_cat) < pi_tolerance or angle(v_pi, v_cat) > 180 - pi_tolerance:
							metal_coordination.append(protein_metal_res[res])
						else:
							metal_coordination_crude.append(protein_metal_res[res])
				if len(ligand_metal) > 0 and len(protein_pi) > 0:
					for res, lig in np.argwhere(distance(protein_pi, ligand_metal) < pi_cation_cutoff):
						v_pi = protein_pi_vec[res] - protein_pi[res]
						v_cat = ligand_metal[lig] - protein_pi[res]
		                        	if angle(v_pi, v_cat) < pi_tolerance or angle(v_pi, v_cat) > 180 - pi_tolerance:
							metal_coordination.append(protein_pi_res[res])
						else:
							metal_coordination_crude.append(protein_pi_res[res])

				# submit data
				if len(hbond_donor) > 0 or len(hbond_acceptor) > 0 or len(salt_bridges) > 0 or len(hydrophobic_contacts) > 0 or len(pi_stacking) > 0:
					sql.append("('%i', '%i', '%i', '%i', '%s', '%s', '%s', '%s', '%i', '%s', '%i', '%s', '%i', '%s', '%i', '%s', '%i', '%i', '%s', '%s')" % (ligand_row['id'], ligand_row['mol_id'], len(hbond_donor)+len(hbond_acceptor), len(hbond_donor)+len(hbond_acceptor)+len(hbond_donor_crude)+len(hbond_acceptor_crude), '|'.join(hbond_donor), '|'.join(hbond_donor_crude), '|'.join(hbond_acceptor), '|'.join(hbond_acceptor_crude), len(salt_bridges), '|'.join(salt_bridges), len(hydrophobic_contacts), '|'.join(hydrophobic_contacts), len(pi_stacking), '|'.join(pi_stacking), len(pi_cation), '|'.join(pi_cation), len(metal_coordination), len(metal_coordination)+len(metal_coordination_crude), '|'.join(metal_coordination), '|'.join(metal_coordination_crude)))	
		
			conn.ping()
			if len(sql) > 0:
				c.execute("INSERT DELAYED INTO `" + mysql_database + "`.`project_" + str(project_id) + "_docking_conformations_interactions` (`id`, `mol_id`, `hbond_num`, `hbond_crude_num`, `hbond_donor`, `hbond_donor_crude`, `hbond_acceptor`, `hbond_acceptor_crude`, `salt_bridges_num`, `salt_bridges`, `hydrophobic_contacts_num`, `hydrophobic_contacts`, `pi_stacking_num`, `pi_stacking`, `pi_cation_num`, `pi_cation`, `metal_coordination_num`, `metal_coordination_crude_num`, `metal_coordination`, `metal_coordination_crude`) VALUES %s ON DUPLICATE KEY UPDATE `hbond_num` = VALUES(`hbond_num`), `hbond_crude_num` = VALUES(`hbond_crude_num`), `hbond_donor` = VALUES(`hbond_donor`), `hbond_donor_crude` = VALUES(`hbond_donor_crude`), `hbond_acceptor` = VALUES(`hbond_acceptor`), `hbond_acceptor_crude` = VALUES(`hbond_acceptor_crude`), `salt_bridges_num` = VALUES(`salt_bridges_num`), `salt_bridges` = VALUES(`salt_bridges`), `hydrophobic_contacts_num` = VALUES(`hydrophobic_contacts_num`), `hydrophobic_contacts`= VALUES(`hydrophobic_contacts`), `pi_stacking_num` = VALUES(`pi_stacking_num`), `pi_stacking` = VALUES(`pi_stacking`), `pi_cation_num` = VALUES(`pi_cation_num`), `pi_cation` = VALUES(`pi_cation`), `metal_coordination_num` = VALUES(`metal_coordination_num`), `metal_coordination_crude_num` = VALUES(`metal_coordination_crude_num`), `metal_coordination` = VALUES(`metal_coordination`), `metal_coordination_crude` = VALUES(`metal_coordination_crude`);" % (','.join(sql)))
			print "Chunk #%i	Progress: %i/%i	Speed: %.3f" % (chunk_n, (chunk_n+1)*chunk, num, chunk/(time() - start))
