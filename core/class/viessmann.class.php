<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

  require_once __DIR__  . '/../../../../core/php/core.inc.php';

  use Viessmann\API\ViessmannAPI;
  use Viessmann\API\ViessmannApiException;
  use Viessmann\API\ViessmannFeature;
  
  include 'phar://' . __DIR__ . '/../../3rdparty/Viessmann-Api-1.4.0.phar/index.php';
  
  class viessmann extends eqLogic
  {
      // Accès au serveur Viessmann
      //
      public function getViessmann()
      {
          $userName = $this->getConfiguration('userName', '');
          $password = $this->getConfiguration('password', '');
          $installationId = $this->getConfiguration('installationId', '');
          $gatewayId = $this->getConfiguration('gatewayId', '');
          $deviceId = $this->getConfiguration('deviceId', '0');
          $circuitId = $this->getConfiguration('circuitId', '0');

          if (($userName === '') || ($password === '')) {
              return null;
          }

          $params = [
          "user" => $userName,
          "pwd" => $password,
          "installationId" => $installationId,
          "gatewayId" => $gatewayId,
          "deviceId" => $deviceId,
          "circuitId" => $circuitId
        ];

          try {
              $viessmannApi = new ViessmannAPI($params);
          } catch (ViessmannApiException $e) {
              log::add('viessmann', 'error', $e->getMessage());
              return null;
          }
                
          if ($installationId === '') {
              $installationId = $viessmannApi->getInstallationId();
              $gatewayId = $viessmannApi->getGatewayId();
              $this->setConfiguration('installationId', $installationId);
              $this->setConfiguration('gatewayId', $gatewayId)->save();
              log::add('viessmann', 'debug', 'Récupération id installation ' . $installationId);
              log::add('viessmann', 'debug', 'Récupération id gateway ' . $gatewayId);
          }

          return $viessmannApi;
      }

      // Rafraichir les données sur le site de Viessmann
      //
      public function rafraichir($viessmannApi)
      {
          $values = array("value", "slope", "shift", "status", "starts", "hours","active", "temperature", "day", "week", "month", "year");
        
          $circuitId = $this->getConfiguration('circuitId', '0');
          $logFeatures = $this->getConfiguration('logFeatures', '');

          $features = $viessmannApi->getAvailableFeatures();

          if ($logFeatures === 'Oui') {
              $listeFeatures = explode(',', $features);
          
              foreach ($listeFeatures as $feature) {
                  $feature = trim($feature);
                  foreach ($values as $value) {
                      try {
                          $valeur = $viessmannApi->getGenericFeaturePropertyAsJSON($feature, $value);
                          log::add('viessmann', 'debug', $feature . '::' .$value . ' --> ' . $valeur);
                      } catch (Exception $e) {
                      }
                  }
              }

              $this->setConfiguration('logFeatures', 'Non')->save();
          }

          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::CIRCULATION_PUMP)) != false) {
              $pumpStatus = $viessmannApi->getCirculationPumpStatus();
          } else {
              $pumpStatus = '?';
          }
          $this->getCmd(null, 'pumpStatus')->event($pumpStatus);

          if (strPos($features, ViessmannFeature::HEATING_BOILER_SENSORS_TEMPERATURE_MAIN) != false) {
              $boilerTemperature = $viessmannApi->getBoilerTemperature();
          } else {
              $boilerTemperature = 99;
          }
          $this->getCmd(null, 'boilerTemperature')->event($boilerTemperature);

          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::ACTIVE_OPERATING_MODE)) != false) {
              $activeMode = $viessmannApi->getActiveMode();
          } else {
              $activeMode = '';
          }
          $this->getCmd(null, 'activeMode')->event($activeMode);

          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::ACTIVE_PROGRAM)) != false) {
              $activeProgram = $viessmannApi->getActiveProgram();
          } else {
              $activeProgram = 'reduced';
          }
          $this->getCmd(null, 'activeProgram')->event($activeProgram);

          if (strPos($features, ViessmannFeature::HEATING_BURNER) != false) {
              $isHeatingBurnerActive = $viessmannApi->isHeatingBurnerActive();
          } else {
              $isHeatingBurnerActive = 0;
          }
          $this->getCmd(null, 'isHeatingBurnerActive')->event($isHeatingBurnerActive);
        
          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::DHW_MODE)) != false) {
              $isDhwModeActive = $viessmannApi->isDhwModeActive();
          } else {
              $isDhwModeActive = 0;
          }
          $this->getCmd(null, 'isDhwModeActive')->event($isDhwModeActive);
          
          if (strPos($features, ViessmannFeature::HEATING_SENSORS_TEMPERATURE_OUTSIDE) != false) {
              $outsideTemperature = $viessmannApi->getOutsideTemperature();
          } else {
              $outsideTemperature = 99;
          }
          $this->getCmd(null, 'outsideTemperature')->event($outsideTemperature);

          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::SENSORS_TEMPERATURE_SUPPLY)) != false) {
              $supplyProgramTemperature = $viessmannApi->getSupplyTemperature();
          } else {
              $supplyProgramTemperature = 99;
          }
          $this->getCmd(null, 'supplyProgramTemperature')->event($supplyProgramTemperature);

          if (strPos($features, ViessmannFeature::HEATING_DHW_TEMPERATURE) != false) {
              $dhwTemperature = $viessmannApi->getDhwTemperature();
          } else {
              $dhwTemperature = 99;
          }
          $this->getCmd(null, 'dhwTemperature')->event($dhwTemperature);
          
          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::HEATING_CURVE)) != false) {
              $slope = $viessmannApi->getSlope();
              $shift = $viessmannApi->getShift();
          } else {
              $slope = 0;
              $shift = 0;
          }
            
          $this->getCmd(null, 'slope')->event($slope);
          $this->getCmd(null, 'shift')->event($shift);

          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::COMFORT_PROGRAM)) != false) {
              $comfortProgramTemperature = $viessmannApi->getComfortProgramTemperature();
          } else {
              $comfortProgramTemperature = 0;
          }
          $this->getCmd(null, 'comfortProgramTemperature')->event($comfortProgramTemperature);

          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::NORMAL_PROGRAM)) != false) {
              $normalProgramTemperature = $viessmannApi->getNormalProgramTemperature();
          } else {
              $normalProgramTemperature = 0;
          }
          $this->getCmd(null, 'normalProgramTemperature')->event($normalProgramTemperature);
          
          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::REDUCED_PROGRAM)) != false) {
              $reducedProgramTemperature = $viessmannApi->getReducedProgramTemperature();
          } else {
              $reducedProgramTemperature = 0;
          }
          $this->getCmd(null, 'reducedProgramTemperature')->event($reducedProgramTemperature);
          
          if ($activeProgram === 'comfort') {
              $this->getCmd(null, 'programTemperature')->event($comfortProgramTemperature);
          } elseif ($activeProgram === 'normal') {
              $this->getCmd(null, 'programTemperature')->event($normalProgramTemperature);
          } else {
              $this->getCmd(null, 'programTemperature')->event($reducedProgramTemperature);
          }
          
          if (strPos($features, ViessmannFeature::HEATING_DHW_SENSORS_TEMPERATURE_HOTWATERSTORAGE) != false) {
              $hotWaterStorageTemperature = $viessmannApi->getHotWaterStorageTemperature();
          } else {
              $hotWaterStorageTemperature = 99;
          }
          $this->getCmd(null, 'hotWaterStorageTemperature')->event($hotWaterStorageTemperature);
          
          // Consommation électricité
          //

          if (strPos($features, ViessmannFeature::HEATING_POWER_CONSUMPTION) != false) {
              $heatingPowerConsumptions = $viessmannApi->getHeatingPowerConsumption("day");
              $this->getCmd(null, 'heatingPowerConsumption')->event($heatingPowerConsumptions[0]);
              $day = '';
              foreach ($heatingPowerConsumptions as $heatingPowerConsumption) {
                  if ($day !== '') {
                      $day = ',' . $day;
                  }
                  $day = $heatingPowerConsumption . $day;
              }
              $this->getCmd(null, 'heatingPowerConsumptionDay')->event($day);

              $heatingPowerConsumptions = $viessmannApi->getHeatingPowerConsumption("week");
              $week = '';
              foreach ($heatingPowerConsumptions as $heatingPowerConsumption) {
                  if ($week !== '') {
                      $week = ',' . $week;
                  }
                  $week = $heatingPowerConsumption . $week;
              }
              $this->getCmd(null, 'heatingPowerConsumptionWeek')->event($week);

              $heatingPowerConsumptions = $viessmannApi->getHeatingPowerConsumption("month");
              $month = '';
              foreach ($heatingPowerConsumptions as $heatingPowerConsumption) {
                  if ($month !== '') {
                      $month = ',' . $month;
                  }
                  $month = $heatingPowerConsumption . $month;
              }
              $this->getCmd(null, 'heatingPowerConsumptionMonth')->event($month);

              $heatingPowerConsumptions = $viessmannApi->getHeatingPowerConsumption("year");
              $year = '';
              foreach ($heatingPowerConsumptions as $heatingPowerConsumption) {
                  if ($year !== '') {
                      $year = ',' . $year;
                  }
                  $year = $heatingPowerConsumption . $year;
              }
              $this->getCmd(null, 'heatingPowerConsumptionYear')->event($year);
          }

          // Consommation gaz eau chaude
          //
          if (strPos($features, ViessmannFeature::HEATING_GAS_CONSUMPTION_DHW) != false) {
              $dhwGazConsumptions = $viessmannApi->getDhwGasConsumption("day");
              $this->getCmd(null, 'dhwGazConsumption')->event($dhwGazConsumptions[0]);
              $day = '';
              foreach ($dhwGazConsumptions as $dhwGazConsumption) {
                  if ($day !== '') {
                      $day = ',' . $day;
                  }
                  $day = $dhwGazConsumption . $day;
              }
              $this->getCmd(null, 'dhwGazConsumptionDay')->event($day);

              $dhwGazConsumptions = $viessmannApi->getDhwGasConsumption("week");
              $week = '';
              foreach ($dhwGazConsumptions as $dhwGazConsumption) {
                  if ($week !== '') {
                      $week = ',' . $week;
                  }
                  $week = $dhwGazConsumption . $week;
              }
              $this->getCmd(null, 'dhwGazConsumptionWeek')->event($week);

              $dhwGazConsumptions = $viessmannApi->getDhwGasConsumption("month");
              $month = '';
              foreach ($dhwGazConsumptions as $dhwGazConsumption) {
                  if ($month !== '') {
                      $month = ',' . $month;
                  }
                  $month = $dhwGazConsumption . $month;
              }
              $this->getCmd(null, 'dhwGazConsumptionMonth')->event($month);

              $dhwGazConsumptions = $viessmannApi->getDhwGasConsumption("year");
              $year = '';
              foreach ($dhwGazConsumptions as $dhwGazConsumption) {
                  if ($year !== '') {
                      $year = ',' . $year;
                  }
                  $year = $dhwGazConsumption . $year;
              }
              $this->getCmd(null, 'dhwGazConsumptionYear')->event($year);
          }

          // Consommation gaz chauffage
          //
          if (strPos($features, ViessmannFeature::HEATING_GAS_CONSUMPTION_HEATING) != false) {
              $heatingGazConsumptions = $viessmannApi->getHeatingGasConsumption("day");
              $this->getCmd(null, 'heatingGazConsumption')->event($heatingGazConsumptions[0]);
              $day = '';
              foreach ($heatingGazConsumptions as $heatingGazConsumption) {
                  if ($day !== '') {
                      $day = ',' . $day;
                  }
                  $day = $heatingGazConsumption . $day;
              }
              $this->getCmd(null, 'heatingGazConsumptionDay')->event($day);

              $heatingGazConsumptions = $viessmannApi->getHeatingGasConsumption("week");
              $week = '';
              foreach ($heatingGazConsumptions as $heatingGazConsumption) {
                  if ($week !== '') {
                      $week = ',' . $week;
                  }
                  $week = $heatingGazConsumption . $week;
              }
              $this->getCmd(null, 'heatingGazConsumptionWeek')->event($week);

              $heatingGazConsumptions = $viessmannApi->getHeatingGasConsumption("month");
              $month = '';
              foreach ($heatingGazConsumptions as $heatingGazConsumption) {
                  if ($month !== '') {
                      $month = ',' . $month;
                  }
                  $month = $heatingGazConsumption . $month;
              }
              $this->getCmd(null, 'heatingGazConsumptionMonth')->event($month);

              $heatingGazConsumptions = $viessmannApi->getHeatingGasConsumption("year");
              $year = '';
              foreach ($heatingGazConsumptions as $heatingGazConsumption) {
                  if ($year !== '') {
                      $year = ',' . $year;
                  }
                  $year = $heatingGazConsumption . $year;
              }
              $this->getCmd(null, 'heatingGazConsumptionYear')->event($year);
          }

          if (strPos($features, ViessmannFeature::HEATING_BURNER_STATISTICS) != false) {
              $heatingBurnerHours = $viessmannApi->getHeatingBurnerStatistics("hours");
              $heatingBurnerStarts = $viessmannApi->getHeatingBurnerStatistics("starts");
          } else {
              $heatingBurnerHours = 0;
              $heatingBurnerStarts = 0;
          }
          $this->getCmd(null, 'heatingBurnerHours')->event($heatingBurnerHours);
          $this->getCmd(null, 'heatingBurnerStarts')->event($heatingBurnerStarts);
          
          if (strPos($features, ViessmannFeature::HEATING_BURNER_MODULATION) != false) {
              $heatingBurnerModulation = $viessmannApi->getHeatingBurnerModulation();
          } else {
              $heatingBurnerModulation = 0;
          }
          $this->getCmd(null, 'heatingBurnerModulation')->event($heatingBurnerModulation);
          
          $dhwSchedule = '';
          if (strPos($features, ViessmannFeature::HEATING_DHW_SCHEDULE) != false) {
              $dhwSchedule = $viessmannApi->getDhwSchedule();
              $json = json_decode($dhwSchedule, true);
          
              $dhwSchedule = '';

              $n = count($json['entries']['value']['mon']);
              for ($i=0; $i<$n; $i++) {
                  $dhwSchedule .= $json['entries']['value']['mon'][$i]['start'] . ',';
                  $dhwSchedule .= $json['entries']['value']['mon'][$i]['end'];
                  if ($i < $n-1) {
                      $dhwSchedule .= ',';
                  }
              }
              $dhwSchedule .= ';';

              $n = count($json['entries']['value']['tue']);
              for ($i=0; $i<$n; $i++) {
                  $dhwSchedule .= $json['entries']['value']['tue'][$i]['start'] . ',';
                  $dhwSchedule .= $json['entries']['value']['tue'][$i]['end'];
                  if ($i < $n-1) {
                      $dhwSchedule .= ',';
                  }
              }
              $dhwSchedule .= ';';

              $n = count($json['entries']['value']['wed']);
              for ($i=0; $i<$n; $i++) {
                  $dhwSchedule .= $json['entries']['value']['wed'][$i]['start'] . ',';
                  $dhwSchedule .= $json['entries']['value']['wed'][$i]['end'];
                  if ($i < $n-1) {
                      $dhwSchedule .= ',';
                  }
              }
              $dhwSchedule .= ';';

              $n = count($json['entries']['value']['thu']);
              for ($i=0; $i<$n; $i++) {
                  $dhwSchedule .= $json['entries']['value']['thu'][$i]['start'] . ',';
                  $dhwSchedule .= $json['entries']['value']['thu'][$i]['end'];
                  if ($i < $n-1) {
                      $dhwSchedule .= ',';
                  }
              }
              $dhwSchedule .= ';';

              $n = count($json['entries']['value']['fri']);
              for ($i=0; $i<$n; $i++) {
                  $dhwSchedule .= $json['entries']['value']['fri'][$i]['start'] . ',';
                  $dhwSchedule .= $json['entries']['value']['fri'][$i]['end'];
                  if ($i < $n-1) {
                      $dhwSchedule .= ',';
                  }
              }
              $dhwSchedule .= ';';

              $n = count($json['entries']['value']['sat']);
              for ($i=0; $i<$n; $i++) {
                  $dhwSchedule .= $json['entries']['value']['sat'][$i]['start'] . ',';
                  $dhwSchedule .= $json['entries']['value']['sat'][$i]['end'];
                  if ($i < $n-1) {
                      $dhwSchedule .= ',';
                  }
              }
              $dhwSchedule .= ';';

              $n = count($json['entries']['value']['sun']);
              for ($i=0; $i<$n; $i++) {
                  $dhwSchedule .= $json['entries']['value']['sun'][$i]['start'] . ',';
                  $dhwSchedule .= $json['entries']['value']['sun'][$i]['end'];
                  if ($i < $n-1) {
                      $dhwSchedule .= ',';
                  }
              }
          }
          $this->getCmd(null, 'dhwSchedule')->event($dhwSchedule);
          
          $heatingSchedule = '';
          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::HEATING_SCHEDULE)) != false) {
              $heatingSchedule = $viessmannApi->getHeatingSchedule();
              $json = json_decode($heatingSchedule, true);

              $heatingSchedule = '';

              $n = count($json['entries']['value']['mon']);
              for ($i=0; $i<$n; $i++) {
                  $heatingSchedule .= $json['entries']['value']['mon'][$i]['start'] . ',';
                  $heatingSchedule .= $json['entries']['value']['mon'][$i]['end'];
                  if ($i < $n-1) {
                      $heatingSchedule .= ',';
                  }
              }
              $heatingSchedule .= ';';

              $n = count($json['entries']['value']['tue']);
              for ($i=0; $i<$n; $i++) {
                  $heatingSchedule .= $json['entries']['value']['tue'][$i]['start'] . ',';
                  $heatingSchedule .= $json['entries']['value']['tue'][$i]['end'];
                  if ($i < $n-1) {
                      $heatingSchedule .= ',';
                  }
              }
              $heatingSchedule .= ';';

              $n = count($json['entries']['value']['wed']);
              for ($i=0; $i<$n; $i++) {
                  $heatingSchedule .= $json['entries']['value']['wed'][$i]['start'] . ',';
                  $heatingSchedule .= $json['entries']['value']['wed'][$i]['end'];
                  if ($i < $n-1) {
                      $heatingSchedule .= ',';
                  }
              }
              $heatingSchedule .= ';';

              $n = count($json['entries']['value']['thu']);
              for ($i=0; $i<$n; $i++) {
                  $heatingSchedule .= $json['entries']['value']['thu'][$i]['start'] . ',';
                  $heatingSchedule .= $json['entries']['value']['thu'][$i]['end'];
                  if ($i < $n-1) {
                      $heatingSchedule .= ',';
                  }
              }
              $heatingSchedule .= ';';

              $n = count($json['entries']['value']['fri']);
              for ($i=0; $i<$n; $i++) {
                  $heatingSchedule .= $json['entries']['value']['fri'][$i]['start'] . ',';
                  $heatingSchedule .= $json['entries']['value']['fri'][$i]['end'];
                  if ($i < $n-1) {
                      $heatingSchedule .= ',';
                  }
              }
              $heatingSchedule .= ';';

              $n = count($json['entries']['value']['sat']);
              for ($i=0; $i<$n; $i++) {
                  $heatingSchedule .= $json['entries']['value']['sat'][$i]['start'] . ',';
                  $heatingSchedule .= $json['entries']['value']['sat'][$i]['end'];
                  if ($i < $n-1) {
                      $heatingSchedule .= ',';
                  }
              }
              $heatingSchedule .= ';';

              $n = count($json['entries']['value']['sun']);
              for ($i=0; $i<$n; $i++) {
                  $heatingSchedule .= $json['entries']['value']['sun'][$i]['start'] . ',';
                  $heatingSchedule .= $json['entries']['value']['sun'][$i]['end'];
                  if ($i < $n-1) {
                      $heatingSchedule .= ',';
                  }
              }
          }
          $this->getCmd(null, 'heatingSchedule')->event($heatingSchedule);
          
          $date = new DateTime();
          $date = $date->format('d-m-Y H:i:s');
          $this->getCmd(null, 'refreshDate')->event($date);

          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::HEATING_FROSTPROTECTION)) != false) {
              $frostProtection = $viessmannApi->getFrostprotection();
          } else {
              $frostProtection = 0;
          }
          $this->getCmd(null, 'frostProtection')->event($frostProtection);

          if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::SENSORS_TEMPERATURE_ROOM)) != false) {
              $roomTemperature = $viessmannApi->getRoomTemperature();
          } else {
              $roomTemperature = 99;
          }
          $this->getCmd(null, 'roomTemperature')->event($roomTemperature);

          return;
      }

      // Set Normal Program Temperature
      //
      public function setNormalProgramTemperature($temperature)
      {
          $viessmannApi = $this->getViessmann();
          if ($viessmannApi == null) {
              return;
          }
          
          $viessmannApi->setNormalProgramTemperature($temperature);
      }

      // Set Comfort Program Temperature
      //
      public function setComfortProgramTemperature($temperature)
      {
          $viessmannApi = $this->getViessmann();
          if ($viessmannApi == null) {
              return;
          }
        
          $viessmannApi->setComfortProgramTemperature($temperature);
      }

      // Set Reduced Program Temperature
      //
      public function setReducedProgramTemperature($temperature)
      {
          $viessmannApi = $this->getViessmann();
          if ($viessmannApi == null) {
              return;
          }
        
          $viessmannApi->setReducedProgramTemperature($temperature);
      }

      // Set Dhw Temperature
      //
      public function setDhwTemperature($temperature)
      {
          $viessmannApi = $this->getViessmann();
          if ($viessmannApi == null) {
              return;
          }
        
          $viessmannApi->setDhwTemperature($temperature);
      }

      public static function cronHourly()
      {
          $viessmann = null;
          $first = true;

          foreach (self::byType('viessmann') as $viessmann) {
              if ($viessmann->getIsEnable() == 1) {
                  if ($first == true) {
                      $viessmannApi = $viessmann->getViessmann();
                      $first = false;
                  }

                  if ($viessmannApi != null) {
                      $viessmann->rafraichir($viessmannApi);
                  }
              }
          }
      }
    
      // Fonction exécutée automatiquement avant la création de l'équipement
      //
      public function preInsert()
      {
      }

      // Fonction exécutée automatiquement après la création de l'équipement
      //
      public function postInsert()
      {
      }

      // Fonction exécutée automatiquement avant la mise à jour de l'équipement
      //
      public function preUpdate()
      {
      }

      // Fonction exécutée automatiquement après la mise à jour de l'équipement
      //
      public function postUpdate()
      {
      }

      // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
      //
      public function preSave()
      {
      }

      // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
      //
      public function postSave()
      {
          $obj = $this->getCmd(null, 'refresh');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Rafraichir', __FILE__));
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setLogicalId('refresh');
          $obj->setType('action');
          $obj->setSubType('other');
          $obj->setOrder(1);
          $obj->save();

          $obj = $this->getCmd(null, 'activeMode');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Mode activé', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('activeMode');
          $obj->setOrder(2);
          $obj->save();

          $obj = $this->getCmd(null, 'activeProgram');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Programme activé', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('activeProgram');
          $obj->setOrder(3);
          $obj->save();

          $obj = $this->getCmd(null, 'isHeatingBurnerActive');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Bruleur activé', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('binary');
          $obj->setLogicalId('isHeatingBurnerActive');
          $obj->setOrder(4);
          $obj->save();

          $obj = $this->getCmd(null, 'isDhwModeActive');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Eau chaude activée', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('binary');
          $obj->setLogicalId('isDhwModeActive');
          $obj->setOrder(5);
          $obj->save();

          $obj = $this->getCmd(null, 'outsideTemperature');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Température extérieure', __FILE__));
              $obj->setUnite('°C');
              $obj->setIsVisible(1);
              $obj->setIsHistorized(1);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('outsideTemperature');
          $obj->setOrder(6);
          $obj->save();
  
          $obj = $this->getCmd(null, 'supplyProgramTemperature');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Température de départ', __FILE__));
              $obj->setUnite('°C');
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('supplyProgramTemperature');
          $obj->setOrder(7);
          $obj->save();

          $obj = $this->getCmd(null, 'slope');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Pente', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('slope');
          $obj->setOrder(8);
          $obj->save();
  
          $obj = $this->getCmd(null, 'shift');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Parallèle', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('shift');
          $obj->setOrder(9);
          $obj->save();

          $objComfort = $this->getCmd(null, 'comfortProgramTemperature');
          if (!is_object($objComfort)) {
              $objComfort = new viessmannCmd();
              $objComfort->setName(__('Température de confort', __FILE__));
              $objComfort->setUnite('°C');
              $objComfort->setIsVisible(1);
              $objComfort->setIsHistorized(0);
          }
          $objComfort->setEqLogic_id($this->getId());
          $objComfort->setType('info');
          $objComfort->setSubType('numeric');
          $objComfort->setLogicalId('comfortProgramTemperature');
          $objComfort->setConfiguration('minValue', 3);
          $objComfort->setConfiguration('maxValue', 37);
          $objComfort->setOrder(10);
          $objComfort->save();
  
          $objNormal = $this->getCmd(null, 'normalProgramTemperature');
          if (!is_object($objNormal)) {
              $objNormal = new viessmannCmd();
              $objNormal->setName(__('Température normale', __FILE__));
              $objNormal->setUnite('°C');
              $objNormal->setIsVisible(1);
              $objNormal->setIsHistorized(0);
          }
          $objNormal->setEqLogic_id($this->getId());
          $objNormal->setType('info');
          $objNormal->setSubType('numeric');
          $objNormal->setLogicalId('normalProgramTemperature');
          $objNormal->setConfiguration('minValue', 3);
          $objNormal->setConfiguration('maxValue', 37);
          $objNormal->setOrder(11);
          $objNormal->save();
  
          $objReduced = $this->getCmd(null, 'reducedProgramTemperature');
          if (!is_object($objReduced)) {
              $objReduced = new viessmannCmd();
              $objReduced->setName(__('Température réduite', __FILE__));
              $objReduced->setUnite('°C');
              $objReduced->setIsVisible(1);
              $objReduced->setIsHistorized(0);
          }
          $objReduced->setEqLogic_id($this->getId());
          $objReduced->setType('info');
          $objReduced->setSubType('numeric');
          $objReduced->setLogicalId('reducedProgramTemperature');
          $objReduced->setConfiguration('minValue', 3);
          $objReduced->setConfiguration('maxValue', 37);
          $objReduced->setOrder(12);
          $objReduced->save();
  
          $obj = $this->getCmd(null, 'programTemperature');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consigne radiateurs', __FILE__));
              $obj->setUnite('°C');
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('programTemperature');
          $obj->setOrder(13);
          $obj->save();
  
          $obj = $this->getCmd(null, 'hotWaterStorageTemperature');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Température eau chaude', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('hotWaterStorageTemperature');
          $obj->setOrder(14);
          $obj->save();
          
          $objDhw = $this->getCmd(null, 'dhwTemperature');
          if (!is_object($objDhw)) {
              $objDhw = new viessmannCmd();
              $objDhw->setName(__('Consigne eau chaude', __FILE__));
              $objDhw->setIsVisible(1);
              $objDhw->setIsHistorized(0);
          }
          $objDhw->setEqLogic_id($this->getId());
          $objDhw->setType('info');
          $objDhw->setSubType('numeric');
          $objDhw->setLogicalId('dhwTemperature');
          $objDhw->setConfiguration('minValue', 10);
          $objDhw->setConfiguration('maxValue', 60);
          $objDhw->setOrder(15);
          $objDhw->save();

          $obj = $this->getCmd(null, 'heatingBurnerHours');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Heures fonctionnement brûleur', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('heatingBurnerHours');
          $obj->setOrder(16);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingBurnerStarts');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Démarrages du brûleur', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('heatingBurnerStarts');
          $obj->setOrder(17);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingBurnerModulation');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Modulation de puissance', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('heatingBurnerModulation');
          $obj->setOrder(18);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingPowerConsumption');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation électrique', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('heatingPowerConsumption');
          $obj->setOrder(19);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingPowerConsumptionDay');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation journalière électrique', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('heatingPowerConsumptionDay');
          $obj->setOrder(20);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingPowerConsumptionWeek');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation hebdomadaire électrique', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('heatingPowerConsumptionWeek');
          $obj->setOrder(21);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingPowerConsumptionMonth');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation mensuelle électrique', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('heatingPowerConsumptionMonth');
          $obj->setOrder(22);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingPowerConsumptionYear');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation annuelle électrique', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('heatingPowerConsumptionYear');
          $obj->setOrder(23);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingGazConsumption');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation gaz radiateurs', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('heatingGazConsumption');
          $obj->setOrder(24);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingGazConsumptionDay');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation journalière gaz radiateurs', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('heatingGazConsumptionDay');
          $obj->setOrder(25);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingGazConsumptionWeek');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation hebdomadaire gaz radiateurs', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('heatingGazConsumptionWeek');
          $obj->setOrder(26);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingGazConsumptionMonth');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation mensuelle gaz radiateurs', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('heatingGazConsumptionMonth');
          $obj->setOrder(27);
          $obj->save();

          $obj = $this->getCmd(null, 'heatingGazConsumptionYear');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation annuelle gaz radiateurs', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('heatingGazConsumptionYear');
          $obj->setOrder(28);
          $obj->save();

          $obj = $this->getCmd(null, 'dhwGazConsumption');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation gaz eau chaude', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('dhwGazConsumption');
          $obj->setOrder(29);
          $obj->save();

          $obj = $this->getCmd(null, 'dhwGazConsumptionDay');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation journalière gaz eau chaude', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('dhwGazConsumptionDay');
          $obj->setOrder(30);
          $obj->save();
          
          $obj = $this->getCmd(null, 'dhwGazConsumptionWeek');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation hebdomadaire gaz eau chaude', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('dhwGazConsumptionWeek');
          $obj->setOrder(31);
          $obj->save();
          
          $obj = $this->getCmd(null, 'dhwGazConsumptionMonth');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation mensuelle gaz eau chaude', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('dhwGazConsumptionMonth');
          $obj->setOrder(32);
          $obj->save();
          
          $obj = $this->getCmd(null, 'dhwGazConsumptionYear');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consommation annuelle gaz eau chaude', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('dhwGazConsumptionYear');
          $obj->setOrder(33);
          $obj->save();

          $obj = $this->getCmd(null, 'comfortProgramSlider');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setUnite('°C');
              $obj->setName(__('Slider température confort', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('action');
          $obj->setSubType('slider');
          $obj->setLogicalId('comfortProgramSlider');
          $obj->setValue($objComfort->getId());
          $obj->setConfiguration('minValue', 3);
          $obj->setConfiguration('maxValue', 37);
          $obj->setOrder(34);
          $obj->save();

          $obj = $this->getCmd(null, 'normalProgramSlider');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setUnite('°C');
              $obj->setName(__('Slider température normale', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('action');
          $obj->setSubType('slider');
          $obj->setLogicalId('normalProgramSlider');
          $obj->setValue($objNormal->getId());
          $obj->setConfiguration('minValue', 3);
          $obj->setConfiguration('maxValue', 37);
          $obj->setOrder(35);
          $obj->save();

          $obj = $this->getCmd(null, 'reducedProgramSlider');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setUnite('°C');
              $obj->setName(__('Slider température réduite', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('action');
          $obj->setSubType('slider');
          $obj->setLogicalId('reducedProgramSlider');
          $obj->setValue($objReduced->getId());
          $obj->setConfiguration('minValue', 3);
          $obj->setConfiguration('maxValue', 37);
          $obj->setOrder(36);
          $obj->save();

          $obj = $this->getCmd(null, 'dhwSlider');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setUnite('°C');
              $obj->setName(__('Slider consigne eau chaude ', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('action');
          $obj->setSubType('slider');
          $obj->setLogicalId('dhwSlider');
          $obj->setValue($objDhw->getId());
          $obj->setConfiguration('minValue', 10);
          $obj->setConfiguration('maxValue', 60);
          $obj->setOrder(37);
          $obj->save();

          $obj = $this->getCmd(null, 'dhwSchedule');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Programmation eau chaude', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('dhwSchedule');
          $obj->setOrder(38);
          $obj->save();
          
          $obj = $this->getCmd(null, 'heatingSchedule');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Programmation chauffage', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('heatingSchedule');
          $obj->setOrder(39);
          $obj->save();

          $obj = $this->getCmd(null, 'refreshDate');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Date rafraichissement', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('refreshDate');
          $obj->setOrder(40);
          $obj->save();

          $obj = $this->getCmd(null, 'frostProtection');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Protection gel', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('frostProtection');
          $obj->setOrder(41);
          $obj->save();

          $obj = $this->getCmd(null, 'roomTemperature');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Température pièce', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('roomTemperature');
          $obj->setOrder(42);
          $obj->save();

          $obj = $this->getCmd(null, 'boilerTemperature');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Température eau radiateur', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('boilerTemperature');
          $obj->setOrder(43);
          $obj->save();

          $obj = $this->getCmd(null, 'pumpStatus');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Status circulateur', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('string');
          $obj->setLogicalId('pumpStatus');
          $obj->setOrder(44);
          $obj->save();
      }

      // Fonction exécutée automatiquement avant la suppression de l'équipement
      //
      public function preRemove()
      {
      }

      // Fonction exécutée automatiquement après la suppression de l'équipement
      //
      public function postRemove()
      {
      }

      // Permet de modifier l'affichage du widget (également utilisable par les commandes)
      //
      
      public function toHtml($_version = 'dashboard')
      {
          $isWidgetPlugin = $this->getConfiguration('isWidgetPlugin');
          $displayGas = $this->getConfiguration('displayGas', '1');
          $displayPower = $this->getConfiguration('displayPower', '1');
          $circuitName = $this->getConfiguration('circuitName', 'Radiateurs');

          if (!$isWidgetPlugin) {
              return eqLogic::toHtml($_version);
          }

          $replace = $this->preToHtml($_version);
          if (!is_array($replace)) {
              return $replace;
          }
          $version = jeedom::versionAlias($_version);
 
          $obj = $this->getCmd(null, 'isHeatingBurnerActive');
          $replace["#isHeatingBurnerActive#"] = $obj->execCmd();
          $replace["#idIsHeatingBurnerActive#"] = $obj->getId();

          $obj = $this->getCmd(null, 'isDhwModeActive');
          $replace["#isDhwModeActive#"] = $obj->execCmd();
          $replace["#idIsDhwModeActive#"] = $obj->getId();

          $obj = $this->getCmd(null, 'outsideTemperature');
          $replace["#outsideTemperature#"] = $obj->execCmd();
          $replace["#idOutsideTemperature#"] = $obj->getId();
          
          $obj = $this->getCmd(null, 'programTemperature');
          $replace["#programTemperature#"] = $obj->execCmd();
          $replace["#idProgramTemperature#"] = $obj->getId();
          
          $obj = $this->getCmd(null, 'comfortProgramTemperature');
          $replace["#comfortProgramTemperature#"] = $obj->execCmd();
          $replace["#idComfortProgramTemperature#"] = $obj->getId();
          $replace["#minComfort#"] = $obj->getConfiguration('minValue');
          $replace["#maxComfort#"] = $obj->getConfiguration('maxValue');
          $replace["#stepComfort#"] = 1;
          
          $obj = $this->getCmd(null, 'normalProgramTemperature');
          $replace["#normalProgramTemperature#"] = $obj->execCmd();
          $replace["#idNormalProgramTemperature#"] = $obj->getId();
          $replace["#minNormal#"] = $obj->getConfiguration('minValue');
          $replace["#maxNormal#"] = $obj->getConfiguration('maxValue');
          $replace["#stepNormal#"] = 1;
          
          $obj = $this->getCmd(null, 'reducedProgramTemperature');
          $replace["#reducedProgramTemperature#"] = $obj->execCmd();
          $replace["#idReducedProgramTemperature#"] = $obj->getId();
          $replace["#minReduced#"] = $obj->getConfiguration('minValue');
          $replace["#maxReduced#"] = $obj->getConfiguration('maxValue');
          $replace["#stepReduced#"] = 1;
          
          $obj = $this->getCmd(null, 'programTemperature');
          $replace["#programTemperature#"] = $obj->execCmd();
          $replace["#idProgramTemperature#"] = $obj->getId();
          
          $obj = $this->getCmd(null, 'activeMode');
          $replace["#activeMode#"] = $obj->execCmd();
          $replace["#idActiveMode#"] = $obj->getId();

          $obj = $this->getCmd(null, 'activeProgram');
          $replace["#activeProgram#"] = $obj->execCmd();
          $replace["#idActiveProgram#"] = $obj->getId();

          $obj = $this->getCmd(null, 'dhwTemperature');
          $replace["#dhwTemperature#"] = $obj->execCmd();
          $replace["#idDhwTemperature#"] = $obj->getId();
          $replace["#minDhw#"] = $obj->getConfiguration('minValue');
          $replace["#maxDhw#"] = $obj->getConfiguration('maxValue');
          $replace["#stepDhw#"] = 1;

          $obj = $this->getCmd(null, 'hotWaterStorageTemperature');
          $replace["#hotWaterStorageTemperature#"] = $obj->execCmd();
          $replace["#idHotWaterStorageTemperature#"] = $obj->getId();
          
          $obj = $this->getCmd(null, 'heatingPowerConsumption');
          $replace["#heatingPowerConsumption#"] = $obj->execCmd();
          $replace["#idHeatingPowerConsumption#"] = $obj->getId();
          
          $obj = $this->getCmd(null, 'dhwGazConsumption');
          $replace["#dhwGazConsumption#"] = $obj->execCmd();
          $replace["#idDhwGazConsumption#"] = $obj->getId();
          
          $obj = $this->getCmd(null, 'heatingGazConsumption');
          $replace["#heatingGazConsumption#"] = $obj->execCmd();
          $replace["#idHeatingGazConsumption#"] = $obj->getId();

          $obj = $this->getCmd(null, 'supplyProgramTemperature');
          $replace["#supplyProgramTemperature#"] = $obj->execCmd();
          $replace["#idSupplyProgramTemperature#"] = $obj->getId();
                  
          $obj = $this->getCmd(null, 'heatingGazConsumptionDay');
          $str = $obj->execCmd();
          $replace["#heatingGazConsumptionDay#"] = $str;
          $replace["#idHeatingGazConsumptionDay#"] = $obj->getId();
 
          $obj = $this->getCmd(null, 'dhwGazConsumptionDay');
          $replace["#dhwGazConsumptionDay#"] = $obj->execCmd();
          $replace["#idDhwGazConsumptionDay#"] = $obj->getId();

          $jours = array("Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim");
          
          $maintenant = time();
          $jour = date("N", $maintenant) - 1;
          $joursSemaine = '';
          $n = substr_count($str, ",") + 1;

          for ($i=0; $i<$n; $i++) {
              if ($joursSemaine !== '') {
                  $joursSemaine = ',' . $joursSemaine;
              }
              $joursSemaine = "'" . $jours[$jour] . "'" . $joursSemaine;
              $jour--;
              if ($jour < 0) {
                  $jour = 6;
              }
          }
          $replace["#joursSemaine#"] = $joursSemaine;
                   
          $obj = $this->getCmd(null, 'heatingGazConsumptionWeek');
          $str = $obj->execCmd();
          $replace["#heatingGazConsumptionWeek#"] = $str;
          $replace["#idHeatingGazConsumptionWeek#"] = $obj->getId();

          $obj = $this->getCmd(null, 'dhwGazConsumptionWeek');
          $replace["#dhwGazConsumptionWeek#"] = $obj->execCmd();
          $replace["#idDhwGazConsumptionWeek#"] = $obj->getId();

          $maintenant = time();
          $semaine = date("W", $maintenant);
          $semaines = '';
          $n = substr_count($str, ",") + 1;

          for ($i=0; $i<$n; $i++) {
              if ($semaines !== '') {
                  $semaines = ',' . $semaines;
              }
              $semaines = "'" . $semaine . "'" . $semaines;
              $maintenant -= 7*24*60*60;
              $semaine = date("W", $maintenant);
          }
          $replace["#semaines#"] = $semaines;

          $obj = $this->getCmd(null, 'heatingGazConsumptionMonth');
          $str = $obj->execCmd();
          $replace["#heatingGazConsumptionMonth#"] = $str;
          $replace["#idHeatingGazConsumptionMonth#"] = $obj->getId();

          $obj = $this->getCmd(null, 'dhwGazConsumptionMonth');
          $replace["#dhwGazConsumptionMonth#"] = $obj->execCmd();
          $replace["#idDhwGazConsumptionMonth#"] = $obj->getId();

          $libMois = array("Janv", "Févr", "Mars", "Avr", "Mai", "Juin", "Juil", "Août", "Sept", "Oct", "Nov", "Déc");
          
          $maintenant = time();
          $mois = date("m", $maintenant)-1;
          $moisS = '';
          $n = substr_count($str, ",") + 1;

          for ($i=0; $i<$n; $i++) {
              if ($moisS !== '') {
                  $moisS = ',' . $moisS;
              }
              $moisS = "'" . $libMois[$mois] . "'" . $moisS;
              $mois--;
              if ($mois < 0) {
                  $mois = 12;
              }
          }
          $replace["#moisS#"] = $moisS;

          $obj = $this->getCmd(null, 'heatingGazConsumptionYear');
          $str = $obj->execCmd();
          $replace["#heatingGazConsumptionYear#"] = $str;
          $replace["#idHeatingGazConsumptionYear#"] = $obj->getId();

          $obj = $this->getCmd(null, 'dhwGazConsumptionYear');
          $replace["#dhwGazConsumptionYear#"] = $obj->execCmd();
          $replace["#idDhwGazConsumptionYear#"] = $obj->getId();

          $maintenant = time();
          $annee = date("Y", $maintenant);
          $annees = '';
          $n = substr_count($str, ",") + 1;

          for ($i=0; $i<$n; $i++) {
              if ($annees !== '') {
                  $annees = ',' . $annees;
              }
              $annees = "'" . $annee . "'" . $annees;
              $annee--;
          }
          $replace["#annees#"] = $annees;
          
          $obj = $this->getCmd(null, 'heatingPowerConsumptionDay');
          $str = $obj->execCmd();
          $replace["#heatingPowerConsumptionDay#"] = $str;
          $replace["#idHeatingPowerConsumptionDay#"] = $obj->getId();
        
          $maintenant = time();
          $jour = date("N", $maintenant) - 1;
          $joursSemaine = '';
          $n = substr_count($str, ",") + 1;

          for ($i=0; $i<$n; $i++) {
              if ($joursSemaine !== '') {
                  $joursSemaine = ',' . $joursSemaine;
              }
              $joursSemaine = "'" . $jours[$jour] . "'" . $joursSemaine;
              $jour--;
              if ($jour < 0) {
                  $jour = 6;
              }
          }
          $replace["#elec_joursSemaine#"] = $joursSemaine;
         
          $obj = $this->getCmd(null, 'heatingPowerConsumptionWeek');
          $str = $obj->execCmd();
          $replace["#heatingPowerConsumptionWeek#"] = $str;
          $replace["#idHeatingPowerConsumptionWeek#"] = $obj->getId();

          $maintenant = time();
          $semaine = date("W", $maintenant);
          $semaines = '';
          $n = substr_count($str, ",") + 1;

          for ($i=0; $i<$n; $i++) {
              if ($semaines !== '') {
                  $semaines = ',' . $semaines;
              }
              $semaines = "'" . $semaine . "'" . $semaines;
              $maintenant -= 7*24*60*60;
              $semaine = date("W", $maintenant);
          }
          $replace["#elec_semaines#"] = $semaines;

          $obj = $this->getCmd(null, 'heatingPowerConsumptionMonth');
          $str = $obj->execCmd();
          $replace["#heatingPowerConsumptionMonth#"] = $str;
          $replace["#idHeatingPowerConsumptionMonth#"] = $obj->getId();

          $maintenant = time();
          $mois = date("m", $maintenant)-1;
          $moisS = '';
          $n = substr_count($str, ",") + 1;

          for ($i=0; $i<$n; $i++) {
              if ($moisS !== '') {
                  $moisS = ',' . $moisS;
              }
              $moisS = "'" . $libMois[$mois] . "'" . $moisS;
              $mois--;
              if ($mois < 0) {
                  $mois = 12;
              }
          }
          $replace["#elec_moisS#"] = $moisS;

          $obj = $this->getCmd(null, 'heatingPowerConsumptionYear');
          $str = $obj->execCmd();
          $replace["#heatingPowerConsumptionYear#"] = $str;
          $replace["#idHeatingPowerConsumptionYear#"] = $obj->getId();

          $maintenant = time();
          $annee = date("Y", $maintenant);
          $annees = '';
          $n = substr_count($str, ",") + 1;

          for ($i=0; $i<$n; $i++) {
              if ($annees !== '') {
                  $annees = ',' . $annees;
              }
              $annees = "'" . $annee . "'" . $annees;
              $annee--;
          }
          $replace["#elec_annees#"] = $annees;

          $obj = $this->getCmd(null, 'dhwSchedule');
          $str = $obj->execCmd();
          $schedules = explode(";", $str);
          
          if (count($schedules) == 7) {
              $replace["#dhwSchLun#"] = $schedules[0];
              $replace["#dhwSchMar#"] = $schedules[1];
              $replace["#dhwSchMer#"] = $schedules[2];
              $replace["#dhwSchJeu#"] = $schedules[3];
              $replace["#dhwSchVen#"] = $schedules[4];
              $replace["#dhwSchSam#"] = $schedules[5];
              $replace["#dhwSchDim#"] = $schedules[6];
          } else {
              $replace["#dhwSchLun#"] = '';
              $replace["#dhwSchMar#"] = '';
              $replace["#dhwSchMer#"] = '';
              $replace["#dhwSchJeu#"] = '';
              $replace["#dhwSchVen#"] = '';
              $replace["#dhwSchSam#"] = '';
              $replace["#dhwSchDim#"] = '';
          }

          $obj = $this->getCmd(null, 'heatingSchedule');
          $str = $obj->execCmd();
          $schedules = explode(";", $str);
          
          if (count($schedules) == 7) {
              $replace["#heaSchLun#"] = $schedules[0];
              $replace["#heaSchMar#"] = $schedules[1];
              $replace["#heaSchMer#"] = $schedules[2];
              $replace["#heaSchJeu#"] = $schedules[3];
              $replace["#heaSchVen#"] = $schedules[4];
              $replace["#heaSchSam#"] = $schedules[5];
              $replace["#heaSchDim#"] = $schedules[6];
          } else {
              $replace["#heaSchLunSta#"] = '';
              $replace["#heaSchMarSta#"] = '';
              $replace["#heaSchMerSta#"] = '';
              $replace["#heaSchJeuSta#"] = '';
              $replace["#heaSchVenSta#"] = '';
              $replace["#heaSchSamSta#"] = '';
              $replace["#heaSchDimSta#"] = '';
          }

          $obj = $this->getCmd(null, 'refresh');
          $replace["#idRefresh#"] = $obj->getId();

          $obj = $this->getCmd(null, 'refreshDate');
          $replace["#refreshDate#"] = $obj->execCmd();
          $replace["#idRefreshDate#"] = $obj->getId();
          
          $obj = $this->getCmd(null, 'heatingBurnerHours');
          $replace["#heatingBurnerHours#"] = $obj->execCmd();
          $replace["#idHeatingBurnerHours#"] = $obj->getId();
          
          $obj = $this->getCmd(null, 'heatingBurnerStarts');
          $replace["#heatingBurnerStarts#"] = $obj->execCmd();
          $replace["#idHeatingBurnerStarts#"] = $obj->getId();

          $obj = $this->getCmd(null, 'heatingBurnerModulation');
          $replace["#heatingBurnerModulation#"] = $obj->execCmd();
          $replace["#idHeatingBurnerModulation#"] = $obj->getId();

          $obj = $this->getCmd(null, 'slope');
          $replace["#slope#"] = $obj->execCmd();
          $replace["#idSlope#"] = $obj->getId();

          $obj = $this->getCmd(null, 'shift');
          $replace["#shift#"] = $obj->execCmd();
          $replace["#idShift#"] = $obj->getId();

          $obj = $this->getCmd(null, 'frostProtection');
          $replace["#frostProtection#"] = $obj->execCmd();
          $replace["#idFrostProtection#"] = $obj->getId();

          $obj = $this->getCmd(null, 'roomTemperature');
          $replace["#roomTemperature#"] = $obj->execCmd();
          $replace["#idRoomTemperature#"] = $obj->getId();

          $obj = $this->getCmd(null, 'boilerTemperature');
          $replace["#boilerTemperature#"] = $obj->execCmd();
          $replace["#idBoilerTemperature#"] = $obj->getId();

          $obj = $this->getCmd(null, 'pumpStatus');
          $replace["#pumpStatus#"] = $obj->execCmd();
          $replace["#idPumpStatus#"] = $obj->getId();

          $obj = $this->getCmd(null, 'comfortProgramSlider');
          $replace["#idComfortProgramSlider#"] = $obj->getId();
          $obj = $this->getCmd(null, 'normalProgramSlider');
          $replace["#idNormalProgramSlider#"] = $obj->getId();
          $obj = $this->getCmd(null, 'reducedProgramSlider');
          $replace["#idReducedProgramSlider#"] = $obj->getId();
          $obj = $this->getCmd(null, 'dhwSlider');
          $replace["#idDhwSlider#"] = $obj->getId();

          $replace["#circuitName#"] = $circuitName;
          $replace["#displayGas#"] = $displayGas;
          $replace["#displayPower#"] = $displayPower;

          return template_replace($replace, getTemplate('core', $version, 'viessmann_view', 'viessmann'));
      }

      private function buildFeature($circuitId, $feature)
      {
          return "heating.circuits" . "." . $circuitId . "." . $feature;
      }
  }
    
    class viessmannCmd extends cmd
    {
        // Exécution d'une commande
        //
        public function execute($_options = array())
        {
            $eqlogic = $this->getEqLogic();
            if ($this->getLogicalId() == 'refresh') {
                $viessmannApi = $eqlogic->getViessmann();
                if ($viessmannApi !== null) {
                    $eqlogic->rafraichir($viessmannApi);
                }
            } elseif ($this->getLogicalId() == 'comfortProgramSlider') {
                if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                    return;
                }
                $eqlogic->getCmd(null, 'comfortProgramTemperature')->event($_options['slider']);
                $eqlogic->setComfortProgramTemperature($_options['slider']);
            } elseif ($this->getLogicalId() == 'normalProgramSlider') {
                if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                    return;
                }
                $eqlogic->getCmd(null, 'normalProgramTemperature')->event($_options['slider']);
                $eqlogic->setNormalProgramTemperature($_options['slider']);
            } elseif ($this->getLogicalId() == 'reducedProgramSlider') {
                if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                    return;
                }
                $eqlogic->getCmd(null, 'reducedProgramTemperature')->event($_options['slider']);
                $eqlogic->setReducedProgramTemperature($_options['slider']);
            } elseif ($this->getLogicalId() == 'dhwSlider') {
                if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                    return;
                }
                $eqlogic->getCmd(null, 'dhwTemperature')->event($_options['slider']);
                $eqlogic->setDhwTemperature($_options['slider']);
            }
        }
    }
