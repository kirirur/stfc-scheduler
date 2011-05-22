<?php
/*    
	This file is part of STFC.
	Copyright 2006-2007 by Michael Krauss (info@stfc2.de) and Tobias Gafner
		
	STFC is based on STGC,
	Copyright 2003-2007 by Florian Brede (florian_brede@hotmail.com) and Philipp Schmidt
	
    STFC is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 3 of the License, or
    (at your option) any later version.

    STFC is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


//########################################################################################
//########################################################################################

/* 23. May 2008
  @Author: Carolfi - Delogu
  @Action: carry on destruction to the galaxy
*/

define (BORG_SIGNATURE, 'We are the Borg. Lower your shields and surrender your ships.<br>We will add your biological and technological distinctiveness to our own.<br>Your culture will adapt to service us. Resistance is futile.');

define (BORG_SPHERE, 'Borg Sphere');

define (BORG_CUBE, 'Borg Cube');

define (BORG_TACT, 'Borg Tactical Cube');

define (BORG_RACE,'6'); // Well, this one should be defined in global.php among the other races */

define (BORG_QUADRANT, 2); // Default Borg belong to Delta quadrant

define (BORG_CYCLE, 1200); // One attack each how many tick?

define (BORG_CHANCE, 80); // Attack is not sistematic, leave a little chance

define (BORG_MINATTACK, 10); // Attack only players with at least n planets

define (BORG_BIGPLAYER, 12000); // Send a cube instead of spheres to player above this points

/* ######################################################################################## */
/* ######################################################################################## */
// Startconfig of Borg
class Borg extends NPC
{
	public function Execute($debug=0)
	{
		$starttime = ( microtime() + time() );

		// Read debug config
		if($debug_data = $this->db->queryrow('SELECT * FROM borg_debug LIMIT 0,1'))
		{
			if($debug_data['debug']==0 || $debug_data['debug']==1)
				$debug=$debug_data['debug'];
		}

		$game = new game();

		$this->sdl->log('<br><b>-------------------------------------------------------------</b><br>'.
			'<b>Starting Borg Bot Scheduler at '.date('d.m.y H:i:s', time()).'</b>', TICK_LOG_FILE_NPC);

		// Bot also enable the life we may need a few more
		$Environment = $this->db->queryrow('SELECT * FROM config LIMIT 0 , 1');
		$ACTUAL_TICK = $Environment['tick_id'];
		$STARDATE = $Environment['stardate'];
		$FUTURE_SHIP = $Environment['future_ship'];

		$this->sdl->start_job('SevenOfNine basic system', TICK_LOG_FILE_NPC);

		//Only with adoption Bot has an existence
		if($Environment)
		{
			$this->sdl->log("The conversation with SevenOfNine begins, oh, but I think that there is no possibility to talk with her",
				TICK_LOG_FILE_NPC);
			$Bot_exe=$this->db->query('SELECT * FROM borg_bot LIMIT 0,1');

			// Create BOT table if it doesn't exist
			if($Bot_exe === false)
			{
				$sql = 'CREATE TABLE `'.$this->db->login['database'].'`.`borg_bot` (
				            `id` INT( 2 ) NOT NULL AUTO_INCREMENT ,
				            `user_id` MEDIUMINT( 8 ) UNSIGNED NOT NULL DEFAULT \'0\',
				            `planet_id` SMALLINT( 5 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `ship_template1` INT( 10 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `ship_template2` INT( 10 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `user_tick` INT( 10 ) NOT NULL ,
				            `attack_quadrant` TINYINT( 3 ) UNSIGNED NOT NULL DEFAULT  \''.BORG_QUADRANT.'\',
				            `attacked_user1` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
				            `attacked_user2` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
				            `attacked_user3` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
				            `attacked_user4` MEDIUMINT( 8 ) UNSIGNED NOT NULL ,
				            `last_attacked` MEDIUMINT( 8 ) UNSIGNED NOT NULL DEFAULT  \'0\',
				            `wrath_size` MEDIUMINT( 8 ) UNSIGNED NOT NULL DEFAULT  \'30\',
				            PRIMARY KEY (  `id` )
				        ) ENGINE = MYISAM';

				if(!$this->db->query($sql))
				{
					$this->sdl->log('<b>Error:</b> cannot create borg_bot table - ABORTED', TICK_LOG_FILE_NPC);
					return;
				}
			}

			$num_bot=$this->db->num_rows($Bot_exe);
			if($num_bot < 1)
			{
				$sql = 'INSERT INTO borg_bot (user_id,user_tick,planet_id,ship_template1,ship_template2)
				        VALUES ("0","0","0","0","0")';
				if(!$this->db->query($sql))
				{
					$this->sdl->log('<b>Error:</b> Abort the program because of errors when creating the user', TICK_LOG_FILE_NPC);
					return;
				}
			}

			//So now we give the bot some data so that it is also Registered
			$this->bot = $this->db->queryrow('SELECT * FROM borg_bot LIMIT 0,1');

			//Check whether the bot already lives
			if($this->bot['user_id']==0)
			{
				$this->sdl->log('We need to create SevenOfNine', TICK_LOG_FILE_NPC);

				$sql = 'INSERT INTO user (user_active, user_name, user_loginname, user_password, user_email,
				                          user_auth_level, user_race, user_gfxpath, user_skinpath, user_registration_time,
				                          user_registration_ip, user_birthday, user_gender, plz, country,
				                          user_enable_sig,user_message_sig,
				                         user_signature)
				         VALUES (1, "Borg(NPG)", "BorgBot", "'.md5("borgcube").'", "borg@stfc.it",
				                 1, '.BORG_RACE.', "", "skin1/", '.time().',
				                 "127.0.0.1", "23.05.2008", "", 16162 , "Italia",
				                 1, "<br><br><p><b>We are the Borg, resistance is futile</b></p>",
				                 "'.BORG_SIGNATURE.'")';

				if(!$this->db->query($sql))
				{
					$this->sdl->log('<b>Error:</b> Bot: Could not create SevenOfNine', TICK_LOG_FILE_NPC);
				}
				else
				{
					$sql = 'Select * FROM user WHERE user_name="Borg(NPG)" and user_loginname="BorgBot" and user_auth_level=1';
					$Bot_data = $this->db->queryrow($sql);
					if(!$Bot_data['user_id'])
					{
						$this->sdl->log('<b>Error:</b> The variable $Bot_data has no content', TICK_LOG_FILE_NPC);
						//break;
					}
					$sql = 'UPDATE borg_bot SET user_id="'.$Bot_data['user_id'].'",user_tick="'.$ACTUAL_TICK.'" WHERE id="'.$this->bot['id'].'"';
					if(!$this->db->query($sql)) {
						$this->sdl->log('<b>Error:</b> Bot card: Could not change the card', TICK_LOG_FILE_NPC);
					}
					$this->bot = $this->db->queryrow('SELECT * FROM borg_bot');
				}
			} // end user bot creation

			//The bot should also have a body of what looks
			if($this->bot['planet_id']==0)
			{
				$this->sdl->log('<b>SevenOfNine needs new body</b>', TICK_LOG_FILE_NPC);

				while($this->bot['planet_id']==0 or $this->bot['planet_id']=='empty')
				{
					$this->sdl->log('Create new planet', TICK_LOG_FILE_NPC);
					$this->db->lock('starsystems_slots');
					$this->bot['planet_id']=create_planet($this->bot['user_id'], 'quadrant', BORG_QUADRANT);
					$this->db->unlock();
					if($this->bot['planet_id'] == 0)
					{
						$this->sdl->log('<b>Error:</b> Bot Planet id doesn\'t go', TICK_LOG_FILE_NPC);
						return;
					}

					$sql = 'UPDATE user SET user_points = "400",user_planets = "1",last_active = "5555555555",
					                        user_attack_protection = "'.($ACTUAL_TICK + 14400).'",
					                        user_capital = "'.$this->bot['planet_id'].'",
					                        active_planet = "'.$this->bot['planet_id'].'"
					        WHERE user_id = '.$this->bot['user_id'];

					if(!$this->db->query($sql)) {
						$this->sdl->log('<b>Error:</b> Bot body: Planet has not been created', TICK_LOG_FILE_NPC);
					}
					else
					{
						//Bot gets best values for his body, he should also look good
						$this->sdl->log('Give best values to the planet', TICK_LOG_FILE_NPC);
						$sql = 'UPDATE planets SET planet_points = 1200,building_1 = 9,building_2 = 15,building_3 = 15,
							building_4 = 15,building_5 = 16,building_6 = 9,building_7 = 15,building_8 = 9,
							building_9 = 9,building_10 = 35,building_11 = 9,building_12 = 15,building_13 = 35,
							unit_1 = 20000,unit_2 = 20000,unit_3 = 20000,unit_4 = 5000,unit_5 = 5000,unit_6=5000,
							planet_name = "Unimatrix Zero",
							research_1 = 15,research_2 = 15,research_3 = 15,research_4 = 15,research_5 = 9,
							workermine_1 = 1600,workermine_2 = 1600,workermine_3 = 1600,resource_4 = 4000
							WHERE planet_owner = '.$this->bot['user_id'].' and planet_id='.$this->bot['planet_id'];

						if(!$this->db->query($sql))
							$this->sdl->log('<b>Error:</b> Bot body: the body could not be improved', TICK_LOG_FILE_NPC);

						$sql = 'UPDATE borg_bot SET planet_id='.$this->bot['planet_id'].' WHERE user_id = '.$this->bot['user_id'];
						if(!$this->db->query($sql))
							$this->sdl->log('<b>Error:</b> Bot card: could not change planet info card', TICK_LOG_FILE_NPC);
					}
				} // end while
			} // end planet creation
			// Check ownership of the BOT's planet
			else {
				$sql = 'SELECT planet_owner FROM planets
					        WHERE planet_id = '.$this->bot['planet_id'];

				$botplanetowner = $this->db->queryrow($sql);
				// Owner are still BORG?
				if($botplanetowner['planet_owner'] != $this->bot['user_id'])
				{
					// The wrath of Borgs begin
					$this->sdl->log("SevenOfNine has lost her homeplanet, her wrath begins!",TICK_LOG_FILE_NPC);

					$this->sdl->log('The User '.$botplanetowner['planet_owner'].' will have a bad day', TICK_LOG_FILE_NPC);

					// Choose a target
					$sql='SELECT p.planet_owner,p.planet_name,p.planet_id,u.user_points FROM (planets p)
					      INNER JOIN (user u) ON u.user_id = p.planet_owner
					      WHERE p.planet_owner ='.$botplanetowner['planet_owner'].' LIMIT 0 , 1';
					$target=$this->db->queryrow($sql);

					// Check if a fleet is already on fly
					$sql = 'SELECT `fleet_id`, `move_id`, `planet_id` FROM `ship_fleets`
					        WHERE `user_id` = '.$this->bot['user_id'].' AND `fleet_name` = "'.$target['planet_name'].'"
					        LIMIT 0,1';
					$fleet = $this->db->queryrow($sql);

					// If the fleet does not exists
					if(empty($fleet['fleet_id'])) {
						// Create a new fleet
						$fleet_id = $this->CreateFleet($target['planet_name'],$this->bot['ship_template2'],
							$this->bot['wrath_size']);

						// Increase wrath size
						$sql = 'UPDATE borg_bot SET wrath_size = wrath_size + 30';
						if(!$this->db->query($sql))
							$this->sdl->log('<b>Error:</b> cannot increase Borg wrath', TICK_LOG_FILE_NPC);

						// Send it to the planet
						$this->SendBorgFleet($ACTUAL_TICK,$fleet_id, $target['planet_id']);
					}
					// If the fleet exists but it is not moving and it is not at planet
					else if($fleet['planet_id'] != $target['planet_id'] && $target['move_id'] == 0) {
						// Send it to the planet
						$this->SendBorgFleet($ACTUAL_TICK,$fleet['fleet_id'], $target['planet_id']);
					}

					// Now think to reconquer the homeplanet
					$sql='SELECT p.planet_owner,p.planet_name,p.planet_id,u.user_points FROM (planets p)
					      INNER JOIN (user u) ON u.user_id = p.planet_owner
					      WHERE p.planet_id ='.$this->bot['planet_id'];
					$target=$this->db->queryrow($sql);

					// Check if a fleet is already on fly
					$sql = 'SELECT `fleet_id`, `move_id`, `planet_id` FROM `ship_fleets`
					        WHERE `user_id` = '.$this->bot['user_id'].' AND `fleet_name` = "Borg Wrath"
					        LIMIT 0,1';
					$fleet = $this->db->queryrow($sql);

					// If the fleet does not exists
					if(empty($fleet['fleet_id'])) {
						// Create a new fleet
						$fleet_id = $this->CreateFleet("Borg Wrath",$this->bot['ship_template2'],30);

						//$sql='SELECT planet_id FROM planets
						//      WHERE planet_owner ='.$this->bot['user_id'].' LIMIT 0 , 1';
						//$start=$this->db->queryrow($sql);

						// Fix the origin since the bot has lost is home planet
						//$sql = 'UPDATE ships_fleets SET planet_id = '.$start['planet_id'].' WHERE fleet_id = '.$fleet_id;
						//if(!$this->db->query($sql))
						//	$this->sdl->log('<b>Error:</b> Cannot update Borg wrath fleet data', TICK_LOG_FILE_NPC);

						// Send it to the planet
						$this->SendBorgFleet($ACTUAL_TICK,$fleet_id, $this->bot['planet_id']);
					}
					// If the fleet exists but it is not moving and it is not at planet
					else if($fleet['planet_id'] != $this->bot['planet_id'] && $target['move_id'] == 0) {

						// Borg are tired?
						if($this->bot['wrath_num'] <= 1)
						{
							// Send it to the planet
							$this->SendBorgFleet($ACTUAL_TICK,$fleet['fleet_id'], $this->bot['planet_id']);

							// Increase wrath num
							$sql = 'UPDATE borg_bot SET wrath_num = wrath_num + 1';
							if(!$this->db->query($sql))
								$this->sdl->log('<b>Error:</b> cannot increase wrath num', TICK_LOG_FILE_NPC);
						}
					}
				}
				else
				{
					$this->sdl->log("SevenOfNine hasn't lost her homeplanet.",TICK_LOG_FILE_NPC);

					// Set wrath size at default value
					$sql = 'UPDATE borg_bot SET wrath_size = 30';
					if(!$this->db->query($sql))
						$this->sdl->log('<b>Error:</b> cannot restore Borg wrath', TICK_LOG_FILE_NPC);
				}
			}

			//Bot shows whether the ship already has templates
			$reload=0;
			if($this->bot['ship_template1']==0)
			{
				/**
				 * Brief comments of SECOND prototype of Borg Sphere:
				 *
				 * Light weapons: 600
				 * Heavy weapons: 600
				 * Planetary weapons: 50
				 * Hull: 700
				 * Shield: 700
				 *
				 * Reaction: 30
				 * Readiness: 30
				 * Agility: 30
				 * Experience: 20
				 * Warp: 10 (Borg has transwarp engines)
				 *
				 * Sensors: 20
				 * Camouflage: 0
				 * Energy available: 200
				 * Energy used: 200
				 *
				 * Resources needed for construction:
				 *
				 * Metal: 50000
				 * Minerals: 50000
				 * Dilithium: 50000
				 * Workers: 500
				 * Technicians: 100
				 * Physicians: 10
				 *
				 * Minimum crew:
				 * 
				 * Drone simple: 100
				 * Assault drone: 25
				 * Elite drone: 25
				 * Commander drone: 5
				 *
				 * Maximum crew:
				 *
				 * Drone simple: 300
				 * Assault drone: 70
				 * Elite drone: 50
				 * Commander drone: 10
				 */ 
				$reload++;
				$sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
				                                    component_1, component_2, component_3, component_4, component_5,
				                                    component_6, component_7, component_8, component_9, component_10,
				                                    value_1, value_2, value_3, value_4, value_5,
				                                    value_6, value_7, value_8, value_9, value_10,
				                                    value_11, value_12, value_13, value_14, value_15,
				                                    resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
				                                    min_unit_1, min_unit_2, min_unit_3, min_unit_4,
				                                    max_unit_1, max_unit_2, max_unit_3, max_unit_4,
				                                    buildtime)
				         VALUES ("'.$this->bot['user_id'].'","'.time().'","'.BORG_SPHERE.'","Exploration ship","'.BORG_RACE.'",6,2,
				                 -1,-1,-1,-1,-1,
				                 -1,-1,-1,-1,-1,
				                 "600","600","50","700","700",
				                 "30","30","30","20","10",
				                 "20","0","200","200","0",
				                 "50000","50000","50000","500","100","10",
				                 "100","25","25","5",
				                 "300","70","50",10,
				                 0)';

				if(!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Bot ShipsTemps: template 1 was not saved', TICK_LOG_FILE_NPC);
			}
			if($this->bot['ship_template2']==0)
			{
				/**
				 * Brief comments of SECOND prototype of Borg Cube:
				 *
				 * Light weapons: 6000
				 * Heavy weapons: 6000
				 * Planetary weapons: 400
				 * Hull: 20000
				 * Shield: 7000
				 *
				 * Reaction: 60
				 * Readiness: 60
				 * Agility: 10
				 * Experience: 60
				 * Warp: 10 (Borg has transwarp engines)
				 *
				 * Sensors: 40
				 * Camouflage: 0
				 * Energy available: 2000
				 * Energy used: 2000
				 *
				 * Resources needed for construction:
				 *
				 * Metal: 500000
				 * Minerals: 500000
				 * Dilithium: 500000
				 * Workers: 50000
				 * Technicians: 10000
				 * Physicians: 1000
				 *
				 * Minimum crew:
				 * 
				 * Drone simple: 10000
				 * Assault drone: 2500
				 * Elite drone: 2500
				 * Commander drone: 500
				 *
				 * Maximum crew:
				 *
				 * Drone simple: 30000
				 * Assault drone: 7000
				 * Elite drone: 5000
				 * Commander drone: 1000
				 */
				$reload++;
				$sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
				                                    component_1, component_2, component_3, component_4, component_5,
				                                    component_6, component_7, component_8, component_9, component_10,
				                                    value_1, value_2, value_3, value_4, value_5,
				                                    value_6, value_7, value_8, value_9, value_10,
				                                    value_11, value_12, value_13, value_14, value_15,
				                                    resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
				                                    min_unit_1, min_unit_2, min_unit_3, min_unit_4,
				                                    max_unit_1, max_unit_2, max_unit_3, max_unit_4,
				                                    buildtime)
				        VALUES ("'.$this->bot['user_id'].'","'.time().'","'.BORG_CUBE.'","Assimilation ship","'.BORG_RACE.'",11,3,
				                -1,-1,-1,-1,-1,
				                -1,-1,-1,-1,-1,
				                "6000","6000","400","20000","7000",
				                "60","60","10","60","10",
				                "40","0","2000","2000","0",
				                "500000","500000","500000","50000","10000","1000",
				                "10000","2500","2500","500",
				                "30000","7000","5000","1000",
				                0)';

				if(!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Bot ShipsTemps: template 2 was not saved', TICK_LOG_FILE_NPC);
			}

			if($this->bot['ship_template3']==0)
			{
				/**
				 * Brief comments of THIRD prototype of Borg Cube:
				 *
				 * Light weapons: 30000
				 * Heavy weapons: 30000
				 * Planetary weapons: 400
				 * Torpedoes: 800
				 * ROF: 16
				 * Hull: 100000
				 * Shield: 60000
				 *
				 * Reaction: 68
				 * Readiness: 68
				 * Agility: 10
				 * Experience: 60
				 * Warp: 10 (Borg has transwarp engines)
				 *
				 * Sensors: 40
				 * Camouflage: 0
				 * Energy available: 8000
				 * Energy used: 8000
				 *
				 * Resources needed for construction:
				 *
				 * Metal: 500000
				 * Minerals: 500000
				 * Dilithium: 500000
				 * Workers: 50000
				 * Technicians: 250
				 * Physicians: 250
				 *
				 * Minimum crew:
				 * 
				 * Drone simple: 10000
				 * Assault drone: 2500
				 * Elite drone: 2500
				 * Commander drone: 500
				 *
				 * Maximum crew:
				 *
				 * Drone simple: 90000
				 * Assault drone: 21000
				 * Elite drone: 15000
				 * Commander drone: 5000
				 */
				$reload++;
				$sql = 'INSERT INTO ship_templates (owner, timestamp, name, description, race, ship_torso, ship_class,
				                                    component_1, component_2, component_3, component_4, component_5,
				                                    component_6, component_7, component_8, component_9, component_10,
				                                    value_1, value_2, value_3, value_4, value_5,
				                                    value_6, value_7, value_8, value_9, value_10,
				                                    value_11, value_12, value_13, value_14, value_15,
				                                    resource_1, resource_2, resource_3, resource_4, unit_5, unit_6,
				                                    min_unit_1, min_unit_2, min_unit_3, min_unit_4,
				                                    max_unit_1, max_unit_2, max_unit_3, max_unit_4,
				                                    buildtime)
				        VALUES ("'.$this->bot['user_id'].'","'.time().'","'.BORG_TACT.'","Combat Ship","'.BORG_RACE.'",11,3,
				                -1,-1,-1,-1,-1,
				                -1,-1,-1,-1,-1,
				                "30000","30000","400","100000","70000",
				                "68","68","10","60","10",
				                "40","0","8000","8000","0",
				                "500000","500000","500000","50000","250","250",
				                "10000","2500","2500","500",
				                "90000","21000","15000","5000",
				                0)';

				if(!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Bot ShipsTemps: template 3 was not saved', TICK_LOG_FILE_NPC);
			}

			if($reload>0)
			{
				$this->sdl->log('Ship templates built', TICK_LOG_FILE_NPC);
				$Bot_temps=$this->db->query('SELECT id FROM ship_templates s WHERE owner='.$this->bot['user_id']);
				$zaehler_temps=0;
				$Bot_neu = array();

				$tempID = $this->db->fetchrow($Bot_temps);
				$Bot_neu[0]=$tempID['id'];
				$tempID = $this->db->fetchrow($Bot_temps);
				$Bot_neu[1]=$tempID['id'];
				$tempID = $this->db->fetchrow($Bot_temps);
				$Bot_neu[2]=$tempID['id'];


				$sql = 'UPDATE borg_bot SET ship_template1 = '.$Bot_neu[0].',ship_template2 = '.$Bot_neu[1].',ship_template3 = '.$Bot_neu[2].' WHERE user_id = '.$this->bot['user_id'];
				if(!$this->db->query($sql))
					$this->sdl->log('<b>Error:</b> Bot ShipsTemps: could not save the template id', TICK_LOG_FILE_NPC);
			}
		}else{
			$this->sdl->log('<b>Error:</b> No access to environment table!', TICK_LOG_FILE_NPC);
			return;
		}
		$this->sdl->finish_job('SevenOfNine basic system', TICK_LOG_FILE_NPC);
		// ########################################################################################
		// ########################################################################################
		//PW des Bot änderns - nix angreifen
		$this->ChangePassword();
		// ########################################################################################
		// ########################################################################################
		// Messages answer
		$messages=array('Resistance is futile.','Resistance is futile.','La resistenza &egrave; inutile.');
		$titles=array('<b>We are Borg</b>','<b>We are Borg</b>','<b>Noi siamo i Borg</b>');

		$this->ReplyToUser($titles,$messages);
		// ########################################################################################
		// ########################################################################################
		//Sensors monitoring and user warning
		$messages=array('Resistance is futile.','Resistance is futile.','La resistenza &egrave; inutile.');
		$titles=array('<b>We are Borg</b>','<b>We are Borg</b>','<b>Noi siamo i Borg</b>');

		//$this->CheckSensors($ACTUAL_TICK,$titles,$messages);

		/**
		 * 13/11/08 - AC: Stop sending ONLY messages to nasty players! ^^
		 */
		$this->sdl->start_job('Sensors monitor', TICK_LOG_FILE_NPC);
		$msgs_number=0;
		$sql='SELECT * FROM `scheduler_shipmovement`
		      WHERE user_id>9 AND 
		            move_status=0 AND
		            move_exec_started!=1 AND
		            move_finish>'.$ACTUAL_TICK.' AND
		            dest="'.$this->bot['planet_id'].'"';
		$attackers=$this->db->query($sql);
		while($attacker = $this->db->fetchrow($attackers))
		{
			$this->sdl->log('The User '.$attacker['user_id'].' is trying to attack bot planet', TICK_LOG_FILE_NPC);
			$send_message = false;

			// Choose a target
			$sql='SELECT p.planet_owner,p.planet_name,p.planet_id,u.user_points FROM (planets p)
			      INNER JOIN (user u) ON u.user_id = p.planet_owner
			      WHERE p.planet_owner ='.$attacker['user_id'].' LIMIT 0 , 1';
			$target=$this->db->queryrow($sql);

			// Check if a fleet is already on fly
			$sql = 'SELECT `fleet_id`, `move_id`, `planet_id` FROM `ship_fleets`
			        WHERE `user_id` = '.$this->bot['user_id'].' AND `fleet_name` = "'.$target['planet_name'].'"
			        LIMIT 0,1';
			$fleet = $this->db->queryrow($sql);

			// If the fleet does not exists
			if(empty($fleet['fleet_id'])) {
				// Create a new fleet
				if($target['user_points'] > BORG_BIGPLAYER)
					$fleet_id = $this->CreateFleet($target['planet_name'],$this->bot['ship_template2'],1);
				else
					$fleet_id = $this->CreateFleet($target['planet_name'],$this->bot['ship_template1'],3);

				// Send it to the planet
				$this->SendBorgFleet($ACTUAL_TICK,$fleet_id, $target['planet_id']);
				$send_message = true;
			}
			// If the fleet exists but it is not moving and it is not at planet
			else if($fleet['planet_id'] != $target['planet_id'] && $target['move_id'] == 0) {
				// Send it to the planet
				$send_message = $this->SendBorgFleet($ACTUAL_TICK,$fleet['fleet_id'], $target['planet_id']);
			}

			if($send_message) {
				$msgs_number++;

				// Recover language of the sender
				$sql = 'SELECT language FROM user WHERE user_id='.$attacker['user_id'];
				if(!($language = $this->db->queryrow($sql)))
					$this->sdl->log('<b>Error:</b> Cannot read user language!',
						TICK_LOG_FILE_NPC);

				switch($language['language'])
				{
					case 'GER':
						$text=$messages[1];
						$title=$titles[1];
					break;
					case 'ITA':
						$text=$messages[2];
						$title=$titles[2];
					break;
					default:
						$text=$messages[0];
						$title=$titles[0];
					break;
				}

				$this->MessageUser($this->bot['user_id'],$attacker['user_id'],$title,
					str_replace("<TARGETPLANET>",$target['planet_name'],$text));
			}
		}
		$this->sdl->log('Number of "messages" sent:'.$msgs_number, TICK_LOG_FILE_NPC);
		$this->sdl->finish_job('Sensors monitor', TICK_LOG_FILE_NPC);

		// ########################################################################################
		// ########################################################################################
		//Ships creation
		$this->sdl->start_job('Creating Unimatrix Zero Fleet', TICK_LOG_FILE_NPC);
		// Fleet Exist?
		$sql = 'SELECT COUNT(*) AS cnt FROM ship_fleets WHERE fleet_name = "Unimatrix Zero" AND user_id = '.$this->bot['user_id'];
		$f_c = $this->db->queryrow($sql);
		if($f_c['cnt'] == false)
		{
			$sql = 'INSERT INTO ship_fleets (fleet_name, user_id, planet_id, alert_phase, move_id, n_ships)
				VALUES ("Unimatrix Zero", '.$this->bot['user_id'].', '.$this->bot['planet_id'].', '.ALERT_PHASE_RED.', 0, 1)';
			if(!$this->db->query($sql))
				$this->sdl->log('<b>Error:</b> Could not insert new fleet data', TICK_LOG_FILE_NPC);
			$fleet_id = $this->db->insert_id();

			if(!$fleet_id) $this->sdl->log('Error - '.$fleet_id.' = empty', TICK_LOG_FILE_NPC);

			$sql= 'SELECT * FROM `ship_templates` WHERE `id` = '.$this->bot['ship_template3'];
			if(($stpl = $this->db->queryrow($sql)) === false)
				$this->sdl->log('<b>Error:</b> Could not query ship template data - '.$sql, TICK_LOG_FILE_NPC);

			$sql= 'INSERT INTO ships (fleet_id, user_id, template_id, experience, hitpoints, construction_time,
			                          rof, torp, unit_1, unit_2, unit_3, unit_4)
			       VALUES ('.$fleet_id.', '.$this->bot['user_id'].', '.$this->bot['ship_template3'].', '.$stpl['value_9'].',
			               '.$stpl['value_5'].', '.time().', '.$stpl['rof'].', '.$stpl['max_torp'].', 
			               '.$stpl['max_unit_1'].', '.$stpl['max_unit_2'].',
			               '.$stpl['max_unit_3'].', '.$stpl['max_unit_4'].')';
			if(!$this->db->query($sql)) {
					$this->sdl->log('<b>Error:</b> Could not insert new ships #'.$i.' data', TICK_LOG_FILE_NPC);
				}
			$this->sdl->log('Unimatrix Zero Fleet has been created!!!', TICK_LOG_FILE_NPC);	
		}
		
		$this->sdl->finish_job('Creating Unimatrix Zero Fleet', TICK_LOG_FILE_NPC);
		// ########################################################################################
		// ########################################################################################
		//Fleets's crew creation

		// Actually put simply the troops aboard
		$sql = 'UPDATE ship_fleets SET unit_1 = (3000*n_ships), unit_2 = (2000*n_ships), unit_3= (1250*n_ships), unit_4 = (500*n_ships) 
		        WHERE fleet_name LIKE "%Fleet Node%" AND user_id = '.$this->bot['user_id'];
		if(!$this->db->query($sql))
			$this->sdl->log('<b>Warning:</b> cannot update Borg Nodes Fleet crew!', TICK_LOG_FILE_NPC);
		// ########################################################################################
		// ########################################################################################
		// Send ships
		/*
		$this->sdl->start_job('Assimilate planets', TICK_LOG_FILE_NPC);

		// First of all check how much time has been elapsed from the previous attack
		if($ACTUAL_TICK > ($this->bot['user_tick'] + BORG_CYCLE)) {
			$sql = 'UPDATE borg_bot SET user_tick="'.$ACTUAL_TICK.'" WHERE id="'.$this->bot['id'].'"';
			if(!$this->db->query($sql))
				$this->sdl->log('<b>Warning:</b> cannot update bot user_tick!', TICK_LOG_FILE_NPC);

			// Give the user a chance not to be attacked
			if(rand(0,100) <= BORG_CHANCE) {
				// Select BORG home planet
				$sql = 'SELECT s.system_global_x, s.system_global_y
				        FROM (planets p)
				        INNER JOIN (starsystems s) ON s.system_id = p.system_id
				        WHERE p.planet_id = '.$this->bot['planet_id'];

				$unimtx0 = $this->db->queryrow($sql);

				// Filter last four attacked users
				$filter = '';
				if($this->bot['last_attacked'] > 0 && $this->bot['last_attacked'] < 4)
				{
					$skip_id = array();
					for($i = 0; $i < $this->bot['last_attacked']; ++$i) {
						$skip_id[$i] = $this->bot['attacked_user'.($i+1)];
					}
					$filter = 'u.user_id NOT IN ('.implode(',', $skip_id).') AND';
				}
				// Reset attacked counter
				else if($this->bot['last_attacked'] >= 4)
					$this->bot['last_attacked'] = 0;

				// Now select the target...
				$sql = 'SELECT p.planet_id, s.system_global_x, s.system_global_y,
				               u.user_points, u.user_id
				        FROM (planets p)
				        INNER JOIN (starsystems s) ON s.system_id = p.system_id
				        INNER JOIN (user u) ON u.user_id = p.planet_owner
				        WHERE u.user_planets > '.BORG_MINATTACK.' AND
				              u.user_vacation_end < '.$ACTUAL_TICK.' AND
				              p.planet_owner <> '.$this->bot['user_id'].' AND
				              '.$filter.'
				              CEIL(p.sector_id / 81) = '.$this->bot['attack_quadrant'].'
				              ORDER BY p.planet_id ASC';

				$targets = $this->db->query($sql);

				// Select the nearest planet
				$min_distance = 10000000;
				while($target = $this->db->fetchrow($targets)) {
					$distance = get_distance(
						array($unimtx0['system_global_x'], $unimtx0['system_global_y']),
						array($target['system_global_x'], $target['system_global_y'])
					);
					if($distance < $min_distance)
					{
						$min_distance = $distance;
						$chosen_target = $target;
					}
				}

				$this->sdl->log('Chosen target is planet: <b>'.$chosen_target['planet_id'].'</b> of user: <b>'.$chosen_target['user_id'].'</b> at distance: <b>'.$min_distance.'</b> from BOT planet',TICK_LOG_FILE_NPC);

				// Select appropriate fleet
				if($chosen_target['user_points'] > BORG_BIGPLAYER)
					$sql = 'SELECT `fleet_id` FROM `ship_fleets`
					        WHERE `fleet_name`="Borg cube" AND `user_id` = '.$this->bot['user_id'].' AND `planet_id` <> 0
					        LIMIT 0,1';
				else
					$sql = 'SELECT `fleet_id` FROM `ship_fleets`
					        WHERE `fleet_name`="Borg spheres" AND `user_id` = '.$this->bot['user_id'].' AND `planet_id` <> 0
					        LIMIT 0,1';

				$fleet = $this->db->queryrow($sql);

				if(!empty($fleet['fleet_id']) && !empty($chosen_target['planet_id'])) {
					$this->SendBorgFleet($ACTUAL_TICK,$fleet['fleet_id'],$chosen_target['planet_id']);
					$this->sdl->log('Borg fleet #'.$fleet['fleet_id'].' sent to planet #'.$chosen_target['planet_id'],
						TICK_LOG_FILE_NPC);

					// Next time attack another quadrant
					$this->bot['attack_quadrant']++;
					if($this->bot['attack_quadrant'] > 4)
						$this->bot['attack_quadrant'] = 1;

					$sql = 'UPDATE borg_bot SET
					               attack_quadrant = '.$this->bot['attack_quadrant'].',
					               attacked_user'.($this->bot['last_attacked']+1).' = '.$chosen_target['user_id'].',
					               last_attacked = last_attacked + 1
					        WHERE id="'.$this->bot['id'].'"';
					if(!$this->db->query($sql))
						$this->sdl->log('<b>Warning:</b> cannot update bot attack_quadrant!',
							TICK_LOG_FILE_NPC);
				}
				else {
					$this->sdl->log('No fleets or no targets available!', TICK_LOG_FILE_NPC);
				}
			}
			else
				$this->sdl->log('Today the galaxy is safe!', TICK_LOG_FILE_NPC);
		}

		$this->sdl->finish_job('Assimilate planets', TICK_LOG_FILE_NPC);
		*/
		// ########################################################################################
		// ########################################################################################
		// Create defences for BOT planets

		$this->sdl->start_job('Create Borg defences on assimilated planets', TICK_LOG_FILE_NPC);

		// We need many infos here, for StartBuild() function
		$sql = 'SELECT * FROM planets WHERE npc_last_action < '.$ACTUAL_TICK.' AND planet_owner = '.BORG_USERID.' ORDER BY npc_last_action ASC LIMIT 0, 3';

		$planets = $this->db->query($sql);

		// Select each planet
		while($planet = $this->db->fetchrow($planets)) 
		{
			if($planet['building_6'] < 9) {
				$res = $this->StartBuild($ACTUAL_TICK,5,$planet);
				if($res == BUILD_ERR_ENERGY)
					$res = $this->StartBuild($ACTUAL_TICK,4,$planet);
			}
			else
			{
				switch($planet['planet_type'])
				{
				case "a":
				case "b":
				case "c":
				case "d":
				case "h":
				case "i":
				case "j":
				case "k":
				case "l":
					break;
				case "e":
				case "f":
				case "g":
				case "m":
				case "n":
				case "y":
					if($planet['unittrain_actual'] == 0)
					{
						$sql = 'UPDATE planets SET	unittrainid_1 = 3, unittrainid_2 = 2,
													unittrainnumber_1 = 2, unittrainnumber_2 = 1,
													unittrainnumberleft_1 = 2, unittrainnumberleft_2 = 1,
													unittrainendless_1 = 1, unittrainendless_2 = 1,
													unittrain_actual = 1, unittrainid_nexttime = '.($ACTUAL_TICK + 2).'
								WHERE planet_id = '.$planet['planet_id'];
						$this->db->query($sql);
					}
					break;
				}
			}

			// Build some orbital guns
			if($planet['building_10'] < (15 + $planet['research_3'])) {
				$res = $this->StartBuild($ACTUAL_TICK,9,$planet);
				if($res == BUILD_ERR_ENERGY)
					$res = $this->StartBuild($ACTUAL_TICK,4,$planet);
			}
			if($planet['building_13'] < (15 + $planet['research_3'])) {
				$res = $this->StartBuild($ACTUAL_TICK,12,$planet);
				if($res == BUILD_ERR_ENERGY)
					$res = $this->StartBuild($ACTUAL_TICK,4,$planet);
			}



			// Computing Fleet Strenght
			if($planet['planet_type'] == 'm' || $planet['planet_type'] == 'n' || $planet['planet_type'] == 'y' || $planet['planet_type'] == 'e' || $planet['planet_type'] == 'f' || $planet['planet_type'] == 'g')
				$n_ships = 3;
			else
				$n_ships = 1;

			$sql = 'SELECT `fleet_id`, `move_id`, `planet_id`, `npc_last_action` FROM `ship_fleets`
			        WHERE `user_id` = '.$this->bot['user_id'].' AND `fleet_name` = "Fleet Node#'.$planet['planet_id'].'"
			        LIMIT 0,1';
			$fleet = $this->db->queryrow($sql);

			// If the fleet does not exists
			if(empty($fleet['fleet_id'])) {
				// Create a new fleet
				$fleet_id = $this->CreateFleet('Fleet Node#'.$planet['planet_id'],$this->bot['ship_template2'],$n_ships,$planet['planet_id']);

				// Update alarm status & Home Planet & Embark Troops
				$sql = 'UPDATE ship_fleets SET alert_phase = '.ALERT_PHASE_RED.', homebase = '.$planet['planet_id'].', 
						unit_1 = (5000*n_ships), unit_2 = (3000*n_ships), unit_3 = (1500*n_ships), unit_4 = (500*n_ships) 
						WHERE fleet_id = '.$fleet_id;
				if(!$this->db->query($sql))
					$this->sdl->log('<b>Warning:</b> cannot update fleet alarm status to RED!', TICK_LOG_FILE_NPC);

			}
			// If the fleet exists
			else 
			{
				if($fleet['move_id'] == 0)
				{
					// Repair&Rearm Fleet
					$this->RestoreFleetLosses('Fleet Node#'.$planet['planet_id'], $this->bot['ship_template2'], $n_ships);
					$fleet_name = 'Fleet Node#'.$planet['planet_id'];
					$this->RepairFleet($fleet_name);
					// If it is not at planet, send it to the planet
					if($fleet['planet_id'] != $planet['planet_id']) {
						$this->SendBorgFleet($ACTUAL_TICK,$fleet['fleet_id'], $planet['planet_id'],11);
					}
				}
			}
			// Updating npc_last_action
			$this->db->query('UPDATE planets SET npc_last_action = '.($ACTUAL_TICK + 240).' WHERE planet_id = '.$planet['planet_id']);
		}

		$this->sdl->finish_job('Create Borg defences on assimilated planets', TICK_LOG_FILE_NPC);
		// ########################################################################################
		// ########################################################################################
		// Settlers Assimilation Program(tm)!!!

		$this->sdl->start_job('Settlers Assimilation Program - BETA -', TICK_LOG_FILE_NPC);

		$sql = 'SELECT count(*) as user_planets FROM planets WHERE planet_owner = '.BORG_USERID;

		if(($res = $this->db->queryrow($sql)) === false)
		{
			$this->sdl->log('<b>Error:</b> Bot: Could not read Borg data', TICK_LOG_FILE_NPC);
		}
		elseif($res['user_planets'] < 101)
		{ // Begin Settlers Assimilation Program Main Loop

			$this->sdl->log('DEBUG: Borg Planet Count: '.$res['user_planets'], TICK_LOG_FILE_NPC );

			$sql='SELECT * FROM ship_fleets WHERE npc_last_action < '.$ACTUAL_TICK.' AND user_id = '.BORG_USERID.' AND move_id = 0 AND fleet_name LIKE "%Fleet Node%" ORDER BY npc_last_action ASC LIMIT 0, 1';

			if(($setpoint = $this->db->query($sql)) === false)
			{
				$this->sdl->log('<b>Error:</b> Bot: Could not read planets DB', TICK_LOG_FILE_NPC);
			}
			else
			{
				while($fleet_to_serve = $this->db->fetchrow($setpoint))
				{
					$this->sdl->log('DEBUG: Is now acting fleet '.$fleet_to_serve['fleet_id'].': '.$fleet_to_serve['fleet_name'], TICK_LOG_FILE_NPC );

					$sql='SELECT planet_id FROM planets WHERE planet_owner = '.INDEPENDENT_USERID.' AND planet_type IN ("m", "n", "y") AND planet_attack_type = 0 ORDER BY planet_owned_date ASC LIMIT 0, 1';
					$primary_target = $this->db->queryrow($sql);
					if( (!isset($primary_target['planet_id'])) || (empty($primary_target['planet_id'])) ) 
					{
						$this->sdl->log('DEBUG: No primary target available, will check secondary', TICK_LOG_FILE_NPC );
						$primary_target['planet_id'] = 0;
						$sql='SELECT planet_id FROM planets WHERE planet_owner = '.INDEPENDENT_USERID.' AND planet_type NOT IN ("m", "n", "y") AND planet_attack_type = 0 ORDER BY planet_owned_date ASC LIMIT 0, 1';
						$secondary_target = $this->db->queryrow($sql);
						if( (!isset($secondary_target['planet_id'])) || (empty($secondary_target['planet_id'])) )
						{
							$this->sdl->log('DEBUG: No secondary target available, planet skip action', TICK_LOG_FILE_NPC );
							$secondary_target['planet_id'] = 0;
						}
					}
					$this->sdl->log('DEBUG: Target selection ended', TICK_LOG_FILE_NPC );
					if(!empty($primary_target['planet_id'])) $this->sdl->log('DEBUG: Primary target is: '.$primary_target['planet_id'], TICK_LOG_FILE_NPC );
					if(!empty($secondary_target['planet_id'])) $this->sdl->log('DEBUG: Secondary target is: '.$secondary_target['planet_id'], TICK_LOG_FILE_NPC );

					if(!empty($secondary_target['planet_id']))
					{
						// Borg attacks a secondary target
						$this->sdl->log('DEBUG: Attacking secondary target!', TICK_LOG_FILE_NPC );
						$this->SendBorgFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $secondary_target['planet_id']);
						$this->db->query('UPDATE planets SET planet_owned_date = '.time().' WHERE planet_id = '.$secondary_target['planet_id']);
					}
					elseif(!empty($primary_target['planet_id']))
					{
						// Borg attacks a primary target
						$this->sdl->log('DEBUG: Attacking primary target!!!', TICK_LOG_FILE_NPC );
						$this->SendBorgFleet($ACTUAL_TICK, $fleet_to_serve['fleet_id'], $primary_target['planet_id']);
						$this->db->query('UPDATE planets SET planet_owned_date = '.time().' WHERE planet_id = '.$primary_target['planet_id']);
					}
					else
					{
						$this->sdl->log('DEBUG: No target available, Borg Fleet skipping action.', TICK_LOG_FILE_NPC );
					}
				}
			} // End Settlers Assimilation Program Main Loop
		}

		$this->sdl->finish_job('Settlers Assimilation Program - BETA -', TICK_LOG_FILE_NPC);

		// ########################################################################################
		// ########################################################################################
		// PLAYERS Assimilation Program(tm)!!!!!!

		$this->sdl->start_job('PLAYERS Assimilation Program - BETA -', TICK_LOG_FILE_NPC);


		$sql = 'SELECT bt.* FROM borg_target bt LEFT JOIN user u ON bt.user_id = u.user_id WHERE u.user_vacation_end < '.$ACTUAL_TICK.' ORDER BY last_check ASC, threat_level DESC, planets_taken DESC, under_attack ASC LIMIT 0,1';
		$primary_target = $this->db->queryrow($sql);
		if(isset($primary_target['user_id']) && !empty($primary_target['user_id']))
		{
			// Checking existing attacks on the way
			$sql = 'SELECT COUNT(*) as live_attack FROM scheduler_shipmovement sm INNER JOIN planets p ON sm.dest = p.planet_id
			        WHERE sm.user_id = '.BORG_USERID.' AND
			              action_code = 46 AND
			              move_status = 0 AND
			              move_exec_started = 0 AND
			              p.planet_owner = '.$primary_target['user_id'];
			$res0 = $this->db->queryrow($sql);
			$live_attack = $res0['live_attack'];

			// Computing the target threat level!!!!
			$sql = 'SELECT count(*) as class2_ships FROM ships LEFT JOIN ship_templates ON ships.template_id = ship_templates.id WHERE ship_class = 2 AND ships.user_id = '.$primary_target['user_id'];
			$res1 = $this->db->queryrow($sql);
			$sql = 'SELECT count(*) as class3_ships FROM ships LEFT JOIN ship_templates ON ships.template_id = ship_templates.id WHERE ship_class = 3 AND ships.user_id = '.$primary_target['user_id'];
			$res2 = $this->db->queryrow($sql);
			$sql = 'SELECT count(*) as prom_ships FROM ships WHERE template_id = '.$FUTURE_SHIP.' AND ships.user_id = '.$primary_target['user_id'];
			$res3 = $this->db->queryrow($sql);
			$bad_factor = round((25*$primary_target['planets_taken'] + 0.1*$res1['class2_ships'] + 0.3*$res2['class3_ships'] + 2.7*$res3['prom_ships']),3);
			$this->sdl->log('<b>DEBUG:</b> USER '.$primary_target['user_id'].' got '.$bad_factor.' as bad_factor', TICK_LOG_FILE_NPC);

			$good_factor = round((70*$primary_target['planets_back'] + 9*$primary_target['under_attack']),3) ;
			$this->sdl->log('<b>DEBUG:</b> USER '.$primary_target['user_id'].' got '.$good_factor.' as good_factor', TICK_LOG_FILE_NPC);

			$threat_level = $bad_factor - $good_factor;

			$new_attack_count = $old_attack_count = $primary_target['under_attack'];

			$max_attack = 0;

			if($threat_level > 1400.0)
			{
				$sql = 'SELECT * FROM ship_fleets WHERE npc_last_action < '.($ACTUAL_TICK + 480).' AND user_id = '.BORG_USERID.' AND move_id = 0 AND fleet_name LIKE "%Fleet Node%" ORDER BY npc_last_action ASC LIMIT 0, 3';
				$attack_fleet_query = $this->db->query($sql);
				$sort_string = ' ORDER BY planet_points DESC';
				$max_attack = 3;
			}
			else if($threat_level > 950.0)
			{
				$sql = 'SELECT * FROM ship_fleets WHERE npc_last_action < '.($ACTUAL_TICK + 240).' AND user_id = '.BORG_USERID.' AND move_id = 0 AND fleet_name LIKE "%Fleet Node%" ORDER BY npc_last_action ASC LIMIT 0, 3';
				$attack_fleet_query = $this->db->query($sql);
				$sort_string = ' ORDER BY planet_points ASC LIMIT 0,40';
				$max_attack = 3;
			}
			else if($threat_level > 450.0)
			{
				$sql = 'SELECT * FROM ship_fleets WHERE npc_last_action < '.($ACTUAL_TICK + 80).' AND user_id = '.BORG_USERID.' AND move_id = 0 AND fleet_name LIKE "%Fleet Node%" ORDER BY npc_last_action ASC LIMIT 0, 2';
				$attack_fleet_query = $this->db->query($sql);
				$sort_string = ' ORDER BY planet_points ASC LIMIT 0,20';
				$max_attack = 2;
			}
			else if($threat_level > 200.0)
			{
				$sql = 'SELECT * FROM ship_fleets WHERE npc_last_action < '.$ACTUAL_TICK.' AND n_ships = 1 AND user_id = '.BORG_USERID.' AND move_id = 0 AND fleet_name LIKE "%Fleet Node%" ORDER BY npc_last_action ASC LIMIT 0, 1';
				$attack_fleet_query = $this->db->query($sql);
				$sort_string = ' AND p.planet_type NOT IN("m", "n") AND planet_points < 451 ORDER BY planet_points ASC LIMIT 0,5';
				$max_attack = 1;
			}

			$this->sdl->log('<b>DEBUG:</b> USER '.$primary_target['user_id'].' has '.$live_attack.' live attacks at the moment', TICK_LOG_FILE_NPC);
			// Attacking Sequence starts here!
			while(($attack_fleet_data = $this->db->fetchrow($attack_fleet_query)) && ($live_attack < $max_attack))
			{
				// Locate the fleet actual position
				$sql = 'SELECT s.system_global_x, s.system_global_y FROM (planets p) INNER JOIN (starsystems s) ON s.system_id = p.system_id WHERE p.planet_id = '.$attack_fleet_data['planet_id'];

				$fleetpos = $this->db->queryrow($sql);

				$sql = 'SELECT p.planet_id, p.planet_attack_type, s.system_global_x, s.system_global_y FROM (planets p) INNER JOIN (starsystems s) ON s.system_id = p.system_id WHERE p.planet_owner = '.$primary_target['user_id'].$sort_string;

				$target_query = $this->db->query($sql);

				// Select the nearest target planet to the fleet
				$min_distance = 10000000;
				while($target_item = $this->db->fetchrow($target_query)) {

					$distance = get_distance(
						array($fleetpos['system_global_x'], $fleetpos['system_global_y']),
						array($target_item['system_global_x'], $target_item['system_global_y'])
					);
					if($distance < $min_distance)
					{
						$min_distance = $distance;
						$chosen_target = $target_item;
					}
				}

				//We have a target? If we do, let's send the fleet
				if(isset($chosen_target['planet_id']) && !empty($chosen_target['planet_id']) && ($chosen_target['planet_attack_type'] != 46))
				{
					$this->SendBorgFleet($ACTUAL_TICK, $attack_fleet_data['fleet_id'], $chosen_target['planet_id']);
					$live_attack++;
					$new_attack_count++;
					$this->sdl->log('BORG Attack!!! ->'.$primary_target['user_id'].' on planet '.$chosen_target['planet_id'], TICK_LOG_FILE_NPC);
				}	
			}

			if($new_attack_count > $old_attack_count) $attack_count = $new_attack_count; else $attack_count = $old_attack_count;

			$sql = 'UPDATE borg_target SET threat_level = '.$threat_level.', last_check = '.$ACTUAL_TICK.', under_attack = '.$attack_count.' WHERE user_id = '.$primary_target['user_id'];
			$this->db->query($sql);
			$this->sdl->log('<b>DEBUG:</b> USER '.$primary_target['user_id'].' got '.$threat_level.' as threat_level', TICK_LOG_FILE_NPC);
		}

		$this->sdl->finish_job('PLAYERS Assimilation Program - BETA -', TICK_LOG_FILE_NPC);

		// ########################################################################################
		// ########################################################################################



		$this->sdl->log('<b>Finished Scheduler in <font color=#009900>'.round((microtime()+time())-$starttime, 4).' secs</font><br>Executed Queries: <font color=#ff0000>'.$this->db->i_query.'</font></b>', TICK_LOG_FILE_NPC);
	}

	function SendBorgFleet($ACTUAL_TICK,$fleet_id,$dest,$action = 46) {

		if($action == 0) $action = 11;

		$sql = 'SELECT f.fleet_id, f.user_id, f.n_ships, f.planet_id AS start,
		           s1.system_id AS start_system_id, s1.system_global_x AS start_x, s1.system_global_y AS start_y,
		           s2.system_id AS dest_system_id, s2.system_global_x AS dest_x, s2.system_global_y AS dest_y
		    FROM (ship_fleets f)
		    INNER JOIN (planets p1) ON p1.planet_id = f.planet_id
		    INNER JOIN (starsystems s1) ON s1.system_id = p1.system_id
		    INNER JOIN (planets p2) ON p2.planet_id = '.$dest.'
		    INNER JOIN (starsystems s2) ON s2.system_id = p2.system_id
		    WHERE f.fleet_id = '.$fleet_id;

		if(($fleet = $this->db->queryrow($sql)) === false) {
			$this->sdl->log('Could not query fleet '.$fleet_id.' data',TICK_LOG_FILE_NPC);
			return false;
		}

		if(empty($fleet['fleet_id'])) {
			$this->sdl->log('Borg fleet for mission does not exist, already moving?',TICK_LOG_FILE_NPC);
			return false;
		}

		if($fleet['start_system_id'] == $fleet['dest_system_id']) {
			$distance = $velocity = 0;
			$min_time = 6;
		}
		else {
			$distance = get_distance(array($fleet['start_x'], $fleet['start_y']), array($fleet['dest_x'], $fleet['dest_y']));
			$velocity = warpf(10);
			$min_time = ceil( ( ($distance / $velocity) / TICK_DURATION ) );
		}

		if($min_time < 1) $min_time = 1;

		$sql = 'INSERT INTO scheduler_shipmovement (user_id, move_status, move_exec_started, start, dest, total_distance, remaining_distance, tick_speed, move_begin, move_finish, n_ships, action_code, action_data)
		         VALUES ('.$fleet['user_id'].', 0, 0, '.$fleet['start'].', '.$dest.', '.$distance.', '.$distance.', '.($velocity * TICK_DURATION).', '.$ACTUAL_TICK.', '.($ACTUAL_TICK + $min_time).', '.$fleet['n_ships'].', '.$action.', "")';

		if(!$this->db->query($sql)) {
			$this->sdl->log('Could not insert new movement data',TICK_LOG_FILE_NPC);
			return false;
		}

		$new_move_id = $this->db->insert_id();

		if(empty($new_move_id)) {
			$this->sdl->log('Could not send Borg fleet $new_move_id = empty',TICK_LOG_FILE_NPC);
			return false;
		}

		if($action == 46)
			$sql = 'UPDATE ship_fleets SET planet_id = 0, move_id = '.$new_move_id.', npc_last_action = '.($ACTUAL_TICK + $min_time + 1440).' WHERE fleet_id = '.$fleet['fleet_id'];
		else
			$sql = 'UPDATE ship_fleets SET planet_id = 0, move_id = '.$new_move_id.' WHERE fleet_id = '.$fleet['fleet_id'];

		if(!$this->db->query($sql)) {
			$this->sdl->log('Could not update Borg fleet data',TICK_LOG_FILE_NPC);
			return false;
		}

		return true;
	}

}


?>
