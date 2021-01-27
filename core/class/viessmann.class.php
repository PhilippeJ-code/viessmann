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
  
  include 'phar://' . __DIR__ . '/../../3rdparty/Viessmann-Api-1.3.4.phar/index.php';
  
  class viessmann extends eqLogic
  {
      // Rafraichir les données sur le site de Viessmann
      //
      public function rafraichir()
      {
          $userName = $this->getConfiguration('userName', '');
          $password = $this->getConfiguration('password', '');
          $installationId = $this->getConfiguration('installationId', '');
          $gatewayId = $this->getConfiguration('gatewayId', '');

          if (($userName === '') || ($password === '')) {
              return;
          }

          $params = [
            "user" => $userName,
            "pwd" => $password,
            "installationId" => $installationId,
            "gatewayId" => $gatewayId,
            "deviceId" => "0",
            "circuitId" => "0"
          ];

          try {
              $viessmannApi = new ViessmannAPI($params);
          } catch (ViessmannApiException $e) {
              log::add('viessmann', 'error', $e->getMessage());
              return;
          }
          
          if ($installationId === '') {
              $installationId = $viessmannApi->getInstallationId();
              $gatewayId = $viessmannApi->getGatewayId();
              $this->setConfiguration('installationId', $installationId);
              $this->setConfiguration('gatewayId', $gatewayId)->save();
              log::add('viessmann', 'debug', 'Récupération id installation ' . $installationId);
              log::add('viessmann', 'debug', 'Récupération id gateway ' . $gatewayId);
          }

          $activeMode = $viessmannApi->getActiveMode();
          $this->getCmd(null, 'activeMode')->event($activeMode);
          $activeProgram = $viessmannApi->getActiveProgram();
          $this->getCmd(null, 'activeProgram')->event($activeProgram);
          $isHeatingBurnerActive = $viessmannApi->isHeatingBurnerActive();
          $this->getCmd(null, 'isHeatingBurnerActive')->event($isHeatingBurnerActive);
          $isDhwModeActive = $viessmannApi->isDhwModeActive();
          $this->getCmd(null, 'isDhwModeActive')->event($isDhwModeActive);
          
          $outsideTemperature = $viessmannApi->getOutsideTemperature();
          $this->getCmd(null, 'outsideTemperature')->event($outsideTemperature);

          $supplyProgramTemperature = $viessmannApi->getSupplyProgramTemperature();
          $this->getCmd(null, 'supplyProgramTemperature')->event($supplyProgramTemperature);

          $dhwTemperature = $viessmannApi->getDhwTemperature();
          $this->getCmd(null, 'dhwTemperature')->event($dhwTemperature);
          
          $slope = $viessmannApi->getSlope();
          $this->getCmd(null, 'slope')->event($slope);
          $shift = $viessmannApi->getShift();
          $this->getCmd(null, 'shift')->event($shift);

          $comfortProgramTemperature = $viessmannApi->getComfortProgramTemperature();
          $this->getCmd(null, 'comfortProgramTemperature')->event($comfortProgramTemperature);
          $normalProgramTemperature = $viessmannApi->getNormalProgramTemperature();
          $this->getCmd(null, 'normalProgramTemperature')->event($normalProgramTemperature);
          $reducedProgramTemperature = $viessmannApi->getReducedProgramTemperature();
          $this->getCmd(null, 'reducedProgramTemperature')->event($reducedProgramTemperature);
          if ($activeProgram === 'comfort') {
              $this->getCmd(null, 'programTemperature')->event($comfortProgramTemperature);
          } elseif ($activeProgram === 'normal') {
              $this->getCmd(null, 'programTemperature')->event($normalProgramTemperature);
          } else {
              $this->getCmd(null, 'programTemperature')->event($reducedProgramTemperature);
          }
          
          $hotWaterStorageTemperature = $viessmannApi->getHotWaterStorageTemperature();
          $this->getCmd(null, 'hotWaterStorageTemperature')->event($hotWaterStorageTemperature);
          
          // Consommation électricité
          //
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

          // Consommation gaz eau chaude
          //
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

          // Consommation gaz chauffage
          //
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

          $heatingBurnerHours = $viessmannApi->getHeatingBurnerStatistics("hours");
          $this->getCmd(null, 'heatingBurnerHours')->event($heatingBurnerHours);
          
          $heatingBurnerStarts = $viessmannApi->getHeatingBurnerStatistics("starts");
          $this->getCmd(null, 'heatingBurnerStarts')->event($heatingBurnerStarts);
          
          $heatingBurnerModulation = $viessmannApi->getHeatingBurnerModulation();
          $this->getCmd(null, 'heatingBurnerModulation')->event($heatingBurnerModulation);
          
          $dhwSchedule = $viessmannApi->getDhwSchedule();
          $json = json_decode($dhwSchedule, true);
          
          $dhwSchedule = '';
          $dhwSchedule .= $json['entries']['value']['mon'][0]['start'] . ',';
          $dhwSchedule .= $json['entries']['value']['mon'][0]['end'] . ',';
          $dhwSchedule .= $json['entries']['value']['tue'][0]['start'] . ',';
          $dhwSchedule .= $json['entries']['value']['tue'][0]['end'] . ',';
          $dhwSchedule .= $json['entries']['value']['wed'][0]['start'] . ',';
          $dhwSchedule .= $json['entries']['value']['wed'][0]['end'] . ',';
          $dhwSchedule .= $json['entries']['value']['thu'][0]['start'] . ',';
          $dhwSchedule .= $json['entries']['value']['thu'][0]['end'] . ',';
          $dhwSchedule .= $json['entries']['value']['fri'][0]['start'] . ',';
          $dhwSchedule .= $json['entries']['value']['fri'][0]['end'] . ',';
          $dhwSchedule .= $json['entries']['value']['sat'][0]['start'] . ',';
          $dhwSchedule .= $json['entries']['value']['sat'][0]['end'] . ',';
          $dhwSchedule .= $json['entries']['value']['sun'][0]['start'] . ',';
          $dhwSchedule .= $json['entries']['value']['sun'][0]['end'];
          $this->getCmd(null, 'dhwSchedule')->event($dhwSchedule);
          
          $heatingSchedule = $viessmannApi->getHeatingSchedule();
          $json = json_decode($heatingSchedule, true);
          
          $heatingSchedule = '';
          $heatingSchedule .= $json['entries']['value']['mon'][0]['start'] . ',';
          $heatingSchedule .= $json['entries']['value']['mon'][0]['end'] . ',';
          $heatingSchedule .= $json['entries']['value']['tue'][0]['start'] . ',';
          $heatingSchedule .= $json['entries']['value']['tue'][0]['end'] . ',';
          $heatingSchedule .= $json['entries']['value']['wed'][0]['start'] . ',';
          $heatingSchedule .= $json['entries']['value']['wed'][0]['end'] . ',';
          $heatingSchedule .= $json['entries']['value']['thu'][0]['start'] . ',';
          $heatingSchedule .= $json['entries']['value']['thu'][0]['end'] . ',';
          $heatingSchedule .= $json['entries']['value']['fri'][0]['start'] . ',';
          $heatingSchedule .= $json['entries']['value']['fri'][0]['end'] . ',';
          $heatingSchedule .= $json['entries']['value']['sat'][0]['start'] . ',';
          $heatingSchedule .= $json['entries']['value']['sat'][0]['end'] . ',';
          $heatingSchedule .= $json['entries']['value']['sun'][0]['start'] . ',';
          $heatingSchedule .= $json['entries']['value']['sun'][0]['end'];
          $this->getCmd(null, 'heatingSchedule')->event($heatingSchedule);
          
          $date = new DateTime();
          $date = $date->format('d-m-Y H:i:s');
          $this->getCmd(null, 'refreshDate')->event($date);

          $frostProtection = $viessmannApi->getFrostprotection();
          $this->getCmd(null, 'frostProtection')->event($frostProtection);          

          return;
      }

      // Set Normal Program Temperature
      //
      public function setNormalProgramTemperature($temperature)
      {
          $userName = $this->getConfiguration('userName');
          $password = $this->getConfiguration('password');
          $installationId = $this->getConfiguration('installationId', '');
          $gatewayId = $this->getConfiguration('gatewayId', '');

          $params = [
            "user" => $userName,
            "pwd" => $password,
            "installationId" => $installationId,
            "gatewayId" => $gatewayId,
            "deviceId" => "0",
            "circuitId" => "0"
          ];

          try {
              $viessmannApi = new ViessmannAPI($params);
          } catch (ViessmannApiException $e) {
              log::add('viessmann', 'error', $e->getMessage());
              return;
          }
          
          $viessmannApi->setNormalProgramTemperature($temperature);
      }

      // Set Comfort Program Temperature
      //
      public function setComfortProgramTemperature($temperature)
      {
          $userName = $this->getConfiguration('userName');
          $password = $this->getConfiguration('password');
          $installationId = $this->getConfiguration('installationId', '');
          $gatewayId = $this->getConfiguration('gatewayId', '');

          $params = [
            "user" => $userName,
            "pwd" => $password,
            "installationId" => $installationId,
            "gatewayId" => $gatewayId,
            "deviceId" => "0",
            "circuitId" => "0"
          ];

          try {
              $viessmannApi = new ViessmannAPI($params);
          } catch (ViessmannApiException $e) {
              log::add('viessmann', 'error', $e->getMessage());
              return;
          }
          
          $viessmannApi->setComfortProgramTemperature($temperature);
      }

      // Set Reduced Program Temperature
      //
      public function setReducedProgramTemperature($temperature)
      {
          $userName = $this->getConfiguration('userName');
          $password = $this->getConfiguration('password');
          $installationId = $this->getConfiguration('installationId', '');
          $gatewayId = $this->getConfiguration('gatewayId', '');

          $params = [
            "user" => $userName,
            "pwd" => $password,
            "installationId" => $installationId,
            "gatewayId" => $gatewayId,
            "deviceId" => "0",
            "circuitId" => "0"
          ];

          try {
              $viessmannApi = new ViessmannAPI($params);
          } catch (ViessmannApiException $e) {
              log::add('viessmann', 'error', $e->getMessage());
              return;
          }
          
          $viessmannApi->setReducedProgramTemperature($temperature);
      }

      public static function cronHourly()
      {
          foreach (self::byType('viessmann') as $viessmann) {
              if ($viessmann->getIsEnable() == 1) {
                  $cmd = $viessmann->getCmd(null, 'refresh');
                  if (!is_object($cmd)) {
                      continue;
                  }
                  $cmd->execCmd();
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
          
          $obj = $this->getCmd(null, 'dhwTemperature');
          if (!is_object($obj)) {
              $obj = new viessmannCmd();
              $obj->setName(__('Consigne eau chaude', __FILE__));
              $obj->setIsVisible(1);
              $obj->setIsHistorized(0);
          }
          $obj->setEqLogic_id($this->getId());
          $obj->setType('info');
          $obj->setSubType('numeric');
          $obj->setLogicalId('dhwTemperature');
          $obj->setOrder(15);
          $obj->save();

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
          $obj->setOrder(37);
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
          $obj->setOrder(38);
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
          $obj->setOrder(39);
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
          $obj->setOrder(40);
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
          
          $obj = $this->getCmd(null, 'normalProgramTemperature');
          $replace["#normalProgramTemperature#"] = $obj->execCmd();
          $replace["#idNormalProgramTemperature#"] = $obj->getId();
          
          $obj = $this->getCmd(null, 'reducedProgramTemperature');
          $replace["#reducedProgramTemperature#"] = $obj->execCmd();
          $replace["#idReducedProgramTemperature#"] = $obj->getId();
          
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
          $schedules = explode(",", $str);    
          
          if ( count($schedules) == 14 )
          {
            $replace["#dhwSchLunSta#"] = $schedules[0];
            $replace["#dhwSchLunEnd#"] = $schedules[1];
            $replace["#dhwSchMarSta#"] = $schedules[2];
            $replace["#dhwSchMarEnd#"] = $schedules[3];
            $replace["#dhwSchMerSta#"] = $schedules[4];
            $replace["#dhwSchMerEnd#"] = $schedules[5];
            $replace["#dhwSchJeuSta#"] = $schedules[6];
            $replace["#dhwSchJeuEnd#"] = $schedules[7];
            $replace["#dhwSchVenSta#"] = $schedules[8];
            $replace["#dhwSchVenEnd#"] = $schedules[9];
            $replace["#dhwSchSamSta#"] = $schedules[10];
            $replace["#dhwSchSamEnd#"] = $schedules[11];
            $replace["#dhwSchDimSta#"] = $schedules[12];
            $replace["#dhwSchDimEnd#"] = $schedules[13];
          }
          else{
            $replace["#dhwSchLunSta#"] = '00:00';
            $replace["#dhwSchLunEnd#"] = '00:00';
            $replace["#dhwSchMarSta#"] = '00:00';
            $replace["#dhwSchMarEnd#"] = '00:00';
            $replace["#dhwSchMerSta#"] = '00:00';
            $replace["#dhwSchMerEnd#"] = '00:00';
            $replace["#dhwSchJeuSta#"] = '00:00';
            $replace["#dhwSchJeuEnd#"] = '00:00';
            $replace["#dhwSchVenSta#"] = '00:00';
            $replace["#dhwSchVenEnd#"] = '00:00';
            $replace["#dhwSchSamSta#"] = '00:00';
            $replace["#dhwSchSamEnd#"] = '00:00';
            $replace["#dhwSchDimSta#"] = '00:00';
            $replace["#dhwSchDimEnd#"] = '00:00';
          }

          $obj = $this->getCmd(null, 'heatingSchedule');
          $str = $obj->execCmd();
          $schedules = explode(",", $str);    
          
          if ( count($schedules) == 14 )
          {
            $replace["#heaSchLunSta#"] = $schedules[0];
            $replace["#heaSchLunEnd#"] = $schedules[1];
            $replace["#heaSchMarSta#"] = $schedules[2];
            $replace["#heaSchMarEnd#"] = $schedules[3];
            $replace["#heaSchMerSta#"] = $schedules[4];
            $replace["#heaSchMerEnd#"] = $schedules[5];
            $replace["#heaSchJeuSta#"] = $schedules[6];
            $replace["#heaSchJeuEnd#"] = $schedules[7];
            $replace["#heaSchVenSta#"] = $schedules[8];
            $replace["#heaSchVenEnd#"] = $schedules[9];
            $replace["#heaSchSamSta#"] = $schedules[10];
            $replace["#heaSchSamEnd#"] = $schedules[11];
            $replace["#heaSchDimSta#"] = $schedules[12];
            $replace["#heaSchDimEnd#"] = $schedules[13];
          }
          else{
            $replace["#heaSchLunSta#"] = '00:00';
            $replace["#heaSchLunEnd#"] = '00:00';
            $replace["#heaSchMarSta#"] = '00:00';
            $replace["#heaSchMarEnd#"] = '00:00';
            $replace["#heaSchMerSta#"] = '00:00';
            $replace["#heaSchMerEnd#"] = '00:00';
            $replace["#heaSchJeuSta#"] = '00:00';
            $replace["#heaSchJeuEnd#"] = '00:00';
            $replace["#heaSchVenSta#"] = '00:00';
            $replace["#heaSchVenEnd#"] = '00:00';
            $replace["#heaSchSamSta#"] = '00:00';
            $replace["#heaSchSamEnd#"] = '00:00';
            $replace["#heaSchDimSta#"] = '00:00';
            $replace["#heaSchDimEnd#"] = '00:00';
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

          return template_replace($replace, getTemplate('core', $version, 'viessmann_view', 'viessmann'));
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
                $eqlogic->rafraichir();
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
            }
        }
    }
