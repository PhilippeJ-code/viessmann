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

include 'phar://' . __DIR__ . '/../../3rdparty/Viessmann-Api.phar/index.php';

use Viessmann\API\ViessmannAPI;
use Viessmann\API\ViessmannApiException;
use Viessmann\API\ViessmannFeature;
  
class viessmann extends eqLogic
{
    const PRESSURE_SUPPLY = "heating.sensors.pressure.supply";

    public function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
    
    // Accès au serveur Viessmann
    //
    public function getViessmann()
    {
        $userName = trim($this->getConfiguration('userName', ''));
        $password = trim($this->getConfiguration('password', ''));
        $installationId = trim($this->getConfiguration('installationId', ''));
        $gatewayId = trim($this->getConfiguration('gatewayId', ''));
        $deviceId = trim($this->getConfiguration('deviceId', '0'));
        $circuitId = trim($this->getConfiguration('circuitId', '0'));

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
            $features = $viessmannApi->getAvailableFeatures();
            if (strPos($features, ViessmannFeature::HEATING_GAS_CONSUMPTION_DHW.',') !== false) {
                $uniteGaz = trim($viessmannApi->getGenericFeaturePropertyAsJSON(ViessmannFeature::HEATING_GAS_CONSUMPTION_DHW, "unit"));
                if ($uniteGaz === '"cubicMeter"') {
                    $this->setConfiguration('uniteGaz', 'm3');
                } else {
                    $this->setConfiguration('uniteGaz', 'kWh');
                }
                $this->setConfiguration('facteurConversionGaz', 1)->save();
            }
        }

        return $viessmannApi;
    }

    // Rafraichir les données sur le site de Viessmann
    //
    public function rafraichir($viessmannApi)
    {
        $values = array("value", "slope", "shift", "status", "starts", "hours","active", "temperature", "day", "week", "month", "year", "unit", "lastService");
        
        $circuitId = trim($this->getConfiguration('circuitId', '0'));
        $logFeatures = $this->getConfiguration('logFeatures', '');
        $facteurConversionGaz = floatval($this->getConfiguration('facteurConversionGaz', 1));
        if ($facteurConversionGaz == 0) {
            $facteurConversionGaz = 1;
        }

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
        $features .= ',';

        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::CIRCULATION_PUMP).',') !== false) {
            $pumpStatus = $viessmannApi->getCirculationPumpStatus($circuitId);
        } else {
            $pumpStatus = '?';
        }
        $this->getCmd(null, 'pumpStatus')->event($pumpStatus);

        if (strPos($features, ViessmannFeature::HEATING_BOILER_SENSORS_TEMPERATURE_MAIN.',') !== false) {
            $boilerTemperature = $viessmannApi->getBoilerTemperature();
        } else {
            $boilerTemperature = 99;
        }
        $this->getCmd(null, 'boilerTemperature')->event($boilerTemperature);

        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::ACTIVE_OPERATING_MODE).',') !== false) {
            $activeMode = $viessmannApi->getActiveMode($circuitId);
        } else {
            $activeMode = '';
        }
        $this->getCmd(null, 'activeMode')->event($activeMode);

        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::ACTIVE_PROGRAM).',') !== false) {
            $activeProgram = $viessmannApi->getActiveProgram($circuitId);
        } else {
            $activeProgram = 'reduced';
        }
        $this->getCmd(null, 'activeProgram')->event($activeProgram);

        if (strPos($features, ViessmannFeature::HEATING_BURNER.',') !== false) {
            $isHeatingBurnerActive = $viessmannApi->isHeatingBurnerActive();
        } else {
            $isHeatingBurnerActive = 0;
        }
        $this->getCmd(null, 'isHeatingBurnerActive')->event($isHeatingBurnerActive);
        
        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::DHW_MODE).',') !== false) {
            $isDhwModeActive = $viessmannApi->isDhwModeActive($circuitId);
        } else {
            $isDhwModeActive = 0;
        }
        $this->getCmd(null, 'isDhwModeActive')->event($isDhwModeActive);
          
        if (strPos($features, ViessmannFeature::HEATING_SENSORS_TEMPERATURE_OUTSIDE.',') !== false) {
            $outsideTemperature = $viessmannApi->getOutsideTemperature();
        } else {
            $outsideTemperature = 99;
        }
        $this->getCmd(null, 'outsideTemperature')->event($outsideTemperature);

        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::SENSORS_TEMPERATURE_SUPPLY).',') !== false) {
            $supplyProgramTemperature = $viessmannApi->getSupplyTemperature($circuitId);
        } else {
            $supplyProgramTemperature = 99;
        }
        $this->getCmd(null, 'supplyProgramTemperature')->event($supplyProgramTemperature);

        if (strPos($features, ViessmannFeature::HEATING_DHW_TEMPERATURE.',') !== false) {
            $dhwTemperature = $viessmannApi->getDhwTemperature();
        } else {
            $dhwTemperature = 99;
        }
        $this->getCmd(null, 'dhwTemperature')->event($dhwTemperature);
          
        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::HEATING_CURVE).',') !== false) {
            $slope = $viessmannApi->getSlope($circuitId);
            $shift = $viessmannApi->getShift($circuitId);
        } else {
            $slope = 0;
            $shift = 0;
        }
            
        $this->getCmd(null, 'slope')->event($slope);
        $this->getCmd(null, 'shift')->event($shift);

        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::COMFORT_PROGRAM).',') !== false) {
            $comfortProgramTemperature = $viessmannApi->getComfortProgramTemperature($circuitId);
        } else {
            $comfortProgramTemperature = 3;
        }
        $this->getCmd(null, 'comfortProgramTemperature')->event($comfortProgramTemperature);

        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::NORMAL_PROGRAM).',') !== false) {
            $normalProgramTemperature = $viessmannApi->getNormalProgramTemperature($circuitId);
        } else {
            $normalProgramTemperature = 3;
        }
        $this->getCmd(null, 'normalProgramTemperature')->event($normalProgramTemperature);
          
        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::REDUCED_PROGRAM).',') !== false) {
            $reducedProgramTemperature = $viessmannApi->getReducedProgramTemperature($circuitId);
        } else {
            $reducedProgramTemperature = 3;
        }
        $this->getCmd(null, 'reducedProgramTemperature')->event($reducedProgramTemperature);
          
        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::ECO_PROGRAM).',') !== false) {
            $ecoProgramTemperature = $viessmannApi->getEcoProgramTemperature($circuitId);
        } else {
            $ecoProgramTemperature = 2;
        }
        $this->getCmd(null, 'ecoProgramTemperature')->event($ecoProgramTemperature);
          
        if ($activeProgram === 'comfort') {
            $this->getCmd(null, 'programTemperature')->event($comfortProgramTemperature);
        } elseif ($activeProgram === 'normal') {
            $this->getCmd(null, 'programTemperature')->event($normalProgramTemperature);
        } elseif ($activeProgram === 'eco') {
            $this->getCmd(null, 'programTemperature')->event($ecoProgramTemperature);
        } else {
            $this->getCmd(null, 'programTemperature')->event($reducedProgramTemperature);
        }
          
        if (strPos($features, ViessmannFeature::HEATING_DHW_SENSORS_TEMPERATURE_HOTWATERSTORAGE.',') !== false) {
            $hotWaterStorageTemperature = $viessmannApi->getHotWaterStorageTemperature();
        } else {
            $hotWaterStorageTemperature = 99;
        }
        $this->getCmd(null, 'hotWaterStorageTemperature')->event($hotWaterStorageTemperature);
          
        // Consommation électricité
        //
        if (strPos($features, ViessmannFeature::HEATING_POWER_CONSUMPTION.',') !== false) {
            $heatingPowerConsumptions = $viessmannApi->getHeatingPowerConsumption("day");
            $this->getCmd(null, 'heatingPowerConsumption')->event($heatingPowerConsumptions[0]);

            $conso = $heatingPowerConsumptions[0];
            $oldConso = $this->getCache('oldConsoPower', -1);
            if ($oldConso > $conso) {
                $dateVeille = time()-24*60*60;
                $dateVeille = date('Y-m-d 00:00:00', $dateVeille);
                $this->getCmd(null, 'heatingPowerHistorize')->event($heatingPowerConsumptions[1], $dateVeille);
            }
            $this->setCache('oldConsoPower', $conso);

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

        $totalDay = array();
        $totalWeek = array();
        $totalMonth = array();
        $totalYear = array();

        // Consommation gaz eau chaude
        //
        if (strPos($features, ViessmannFeature::HEATING_GAS_CONSUMPTION_DHW.',') !== false) {
            $dhwGazConsumptions = $viessmannApi->getDhwGasConsumption("day");
            $this->getCmd(null, 'dhwGazConsumption')->event($dhwGazConsumptions[0]*$facteurConversionGaz);

            $conso = $dhwGazConsumptions[0]*$facteurConversionGaz;
            $oldConso = $this->getCache('oldConsoDhw', -1);
            if ($oldConso > $conso) {
                $dateVeille = time()-24*60*60;
                $dateVeille = date('Y-m-d 00:00:00', $dateVeille);
                $this->getCmd(null, 'dhwGazHistorize')->event($dhwGazConsumptions[1]*$facteurConversionGaz, $dateVeille);
            }
            $this->setCache('oldConsoDhw', $conso);

            $day = '';
            foreach ($dhwGazConsumptions as $dhwGazConsumption) {
                if ($day !== '') {
                    $day = ',' . $day;
                }
                $day = $dhwGazConsumption*$facteurConversionGaz . $day;
                $totalDay[] = $dhwGazConsumption*$facteurConversionGaz;
            }
            $this->getCmd(null, 'dhwGazConsumptionDay')->event($day);

            $dhwGazConsumptions = $viessmannApi->getDhwGasConsumption("week");
            $week = '';
            foreach ($dhwGazConsumptions as $dhwGazConsumption) {
                if ($week !== '') {
                    $week = ',' . $week;
                }
                $week = $dhwGazConsumption*$facteurConversionGaz . $week;
                $totalWeek[] = $dhwGazConsumption*$facteurConversionGaz;
            }
            $this->getCmd(null, 'dhwGazConsumptionWeek')->event($week);

            $dhwGazConsumptions = $viessmannApi->getDhwGasConsumption("month");
            $month = '';
            foreach ($dhwGazConsumptions as $dhwGazConsumption) {
                if ($month !== '') {
                    $month = ',' . $month;
                }
                $month = $dhwGazConsumption*$facteurConversionGaz . $month;
                $totalMonth[] = $dhwGazConsumption*$facteurConversionGaz;
            }
            $this->getCmd(null, 'dhwGazConsumptionMonth')->event($month);

            $dhwGazConsumptions = $viessmannApi->getDhwGasConsumption("year");
            $year = '';
            foreach ($dhwGazConsumptions as $dhwGazConsumption) {
                if ($year !== '') {
                    $year = ',' . $year;
                }
                $year = $dhwGazConsumption*$facteurConversionGaz . $year;
                $totalYear[] = $dhwGazConsumption*$facteurConversionGaz;
            }
            $this->getCmd(null, 'dhwGazConsumptionYear')->event($year);
        }

        // Consommation gaz chauffage
        //
        if (strPos($features, ViessmannFeature::HEATING_GAS_CONSUMPTION_HEATING.',') !== false) {
            $heatingGazConsumptions = $viessmannApi->getHeatingGasConsumption("day");
            $this->getCmd(null, 'heatingGazConsumption')->event($heatingGazConsumptions[0]*$facteurConversionGaz);

            $conso = $heatingGazConsumptions[0]*$facteurConversionGaz;
            $oldConso = $this->getCache('oldConsoHeating', -1);
            if ($oldConso > $conso) {
                $dateVeille = time()-24*60*60;
                $dateVeille = date('Y-m-d 00:00:00', $dateVeille);
                $this->getCmd(null, 'heatingGazHistorize')->event($heatingGazConsumptions[1]*$facteurConversionGaz, $dateVeille);
            }
            $this->setCache('oldConsoHeating', $conso);

            $day = '';
            $n = 0;
            foreach ($heatingGazConsumptions as $heatingGazConsumption) {
                if ($day !== '') {
                    $day = ',' . $day;
                }
                $day = $heatingGazConsumption*$facteurConversionGaz . $day;
                $totalDay[$n] += $heatingGazConsumption*$facteurConversionGaz;
                $n++;
            }
            $this->getCmd(null, 'heatingGazConsumptionDay')->event($day);

            $day = '';
            foreach ($totalDay as $total) {
                if ($day !== '') {
                    $day = ',' . $day;
                }
                $day =  $total . $day;
            }
            $this->getCmd(null, 'totalGazConsumptionDay')->event($day);

            $heatingGazConsumptions = $viessmannApi->getHeatingGasConsumption("week");
            $week = '';
            $n = 0;
            foreach ($heatingGazConsumptions as $heatingGazConsumption) {
                if ($week !== '') {
                    $week = ',' . $week;
                }
                $week = $heatingGazConsumption*$facteurConversionGaz . $week;
                $totalWeek[$n] += $heatingGazConsumption*$facteurConversionGaz;
                $n++;
            }
            $this->getCmd(null, 'heatingGazConsumptionWeek')->event($week);

            $week = '';
            foreach ($totalWeek as $total) {
                if ($week !== '') {
                    $week = ',' . $week;
                }
                $week =  $total . $week;
            }
            $this->getCmd(null, 'totalGazConsumptionWeek')->event($week);

            $heatingGazConsumptions = $viessmannApi->getHeatingGasConsumption("month");
            $month = '';
            $n = 0;
            foreach ($heatingGazConsumptions as $heatingGazConsumption) {
                if ($month !== '') {
                    $month = ',' . $month;
                }
                $month = $heatingGazConsumption*$facteurConversionGaz . $month;
                $totalMonth[$n] += $heatingGazConsumption*$facteurConversionGaz;
                $n++;
            }
            $this->getCmd(null, 'heatingGazConsumptionMonth')->event($month);

            $month = '';
            foreach ($totalMonth as $total) {
                if ($month !== '') {
                    $month = ',' . $month;
                }
                $month =  $total . $month;
            }
            $this->getCmd(null, 'totalGazConsumptionMonth')->event($month);

            $heatingGazConsumptions = $viessmannApi->getHeatingGasConsumption("year");
            $year = '';
            $n = 0;
            foreach ($heatingGazConsumptions as $heatingGazConsumption) {
                if ($year !== '') {
                    $year = ',' . $year;
                }
                $year = $heatingGazConsumption*$facteurConversionGaz . $year;
                $totalYear[$n] += $heatingGazConsumption*$facteurConversionGaz;
                $n++;
            }
            $this->getCmd(null, 'heatingGazConsumptionYear')->event($year);

            $year = '';
            foreach ($totalYear as $total) {
                if ($year !== '') {
                    $year = ',' . $year;
                }
                $year =  $total . $year;
            }
            $this->getCmd(null, 'totalGazConsumptionYear')->event($year);
        }

        if (strPos($features, ViessmannFeature::HEATING_BURNER_STATISTICS.',') !== false) {
            $heatingBurnerHours = $viessmannApi->getHeatingBurnerStatistics("hours");
            $heatingBurnerStarts = $viessmannApi->getHeatingBurnerStatistics("starts");
        } else {
            $heatingBurnerHours = 0;
            $heatingBurnerStarts = 0;
        }
        $this->getCmd(null, 'heatingBurnerHours')->event($heatingBurnerHours);
        $this->getCmd(null, 'heatingBurnerStarts')->event($heatingBurnerStarts);
          
        if (strPos($features, ViessmannFeature::HEATING_BURNER_MODULATION.',') !== false) {
            $heatingBurnerModulation = $viessmannApi->getHeatingBurnerModulation();
        } else {
            $heatingBurnerModulation = 0;
        }
        $this->getCmd(null, 'heatingBurnerModulation')->event($heatingBurnerModulation);
          
        $dhwSchedule = '';
        if (strPos($features, ViessmannFeature::HEATING_DHW_SCHEDULE.',') !== false) {
            $dhwSchedule = $viessmannApi->getDhwSchedule();
            $json = json_decode($dhwSchedule, true);
          
            $dhwSchedule = '';

            $n = count($json['entries']['value']['mon']);
            for ($i=0; $i<$n; $i++) {
                $dhwSchedule .= 'n,';
                $dhwSchedule .= $json['entries']['value']['mon'][$i]['start'] . ',';
                $dhwSchedule .= $json['entries']['value']['mon'][$i]['end'];
                if ($i < $n-1) {
                    $dhwSchedule .= ',';
                }
            }
            $dhwSchedule .= ';';

            $n = count($json['entries']['value']['tue']);
            for ($i=0; $i<$n; $i++) {
                $dhwSchedule .= 'n,';
                $dhwSchedule .= $json['entries']['value']['tue'][$i]['start'] . ',';
                $dhwSchedule .= $json['entries']['value']['tue'][$i]['end'];
                if ($i < $n-1) {
                    $dhwSchedule .= ',';
                }
            }
            $dhwSchedule .= ';';

            $n = count($json['entries']['value']['wed']);
            for ($i=0; $i<$n; $i++) {
                $dhwSchedule .= 'n,';
                $dhwSchedule .= $json['entries']['value']['wed'][$i]['start'] . ',';
                $dhwSchedule .= $json['entries']['value']['wed'][$i]['end'];
                if ($i < $n-1) {
                    $dhwSchedule .= ',';
                }
            }
            $dhwSchedule .= ';';

            $n = count($json['entries']['value']['thu']);
            for ($i=0; $i<$n; $i++) {
                $dhwSchedule .= 'n,';
                $dhwSchedule .= $json['entries']['value']['thu'][$i]['start'] . ',';
                $dhwSchedule .= $json['entries']['value']['thu'][$i]['end'];
                if ($i < $n-1) {
                    $dhwSchedule .= ',';
                }
            }
            $dhwSchedule .= ';';

            $n = count($json['entries']['value']['fri']);
            for ($i=0; $i<$n; $i++) {
                $dhwSchedule .= 'n,';
                $dhwSchedule .= $json['entries']['value']['fri'][$i]['start'] . ',';
                $dhwSchedule .= $json['entries']['value']['fri'][$i]['end'];
                if ($i < $n-1) {
                    $dhwSchedule .= ',';
                }
            }
            $dhwSchedule .= ';';

            $n = count($json['entries']['value']['sat']);
            for ($i=0; $i<$n; $i++) {
                $dhwSchedule .= 'n,';
                $dhwSchedule .= $json['entries']['value']['sat'][$i]['start'] . ',';
                $dhwSchedule .= $json['entries']['value']['sat'][$i]['end'];
                if ($i < $n-1) {
                    $dhwSchedule .= ',';
                }
            }
            $dhwSchedule .= ';';

            $n = count($json['entries']['value']['sun']);
            for ($i=0; $i<$n; $i++) {
                $dhwSchedule .= 'n,';
                $dhwSchedule .= $json['entries']['value']['sun'][$i]['start'] . ',';
                $dhwSchedule .= $json['entries']['value']['sun'][$i]['end'];
                if ($i < $n-1) {
                    $dhwSchedule .= ',';
                }
            }
        }
        $this->getCmd(null, 'dhwSchedule')->event($dhwSchedule);
          
        $heatingSchedule = '';
        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::HEATING_SCHEDULE).',') !== false) {
            $heatingSchedule = $viessmannApi->getHeatingSchedule();

            $json = json_decode($heatingSchedule, true);

            $heatingSchedule = '';

            $n = count($json['entries']['value']['mon']);
            for ($i=0; $i<$n; $i++) {
                $heatingSchedule .= substr($json['entries']['value']['mon'][$i]['mode'], 0, 1) . ',';
                $heatingSchedule .= $json['entries']['value']['mon'][$i]['start'] . ',';
                $heatingSchedule .= $json['entries']['value']['mon'][$i]['end'];
                if ($i < $n-1) {
                    $heatingSchedule .= ',';
                }
            }
            $heatingSchedule .= ';';

            $n = count($json['entries']['value']['tue']);
            for ($i=0; $i<$n; $i++) {
                $heatingSchedule .= substr($json['entries']['value']['tue'][$i]['mode'], 0, 1) . ',';
                $heatingSchedule .= $json['entries']['value']['tue'][$i]['start'] . ',';
                $heatingSchedule .= $json['entries']['value']['tue'][$i]['end'];
                if ($i < $n-1) {
                    $heatingSchedule .= ',';
                }
            }
            $heatingSchedule .= ';';

            $n = count($json['entries']['value']['wed']);
            for ($i=0; $i<$n; $i++) {
                $heatingSchedule .= substr($json['entries']['value']['wed'][$i]['mode'], 0, 1) . ',';
                $heatingSchedule .= $json['entries']['value']['wed'][$i]['start'] . ',';
                $heatingSchedule .= $json['entries']['value']['wed'][$i]['end'];
                if ($i < $n-1) {
                    $heatingSchedule .= ',';
                }
            }
            $heatingSchedule .= ';';

            $n = count($json['entries']['value']['thu']);
            for ($i=0; $i<$n; $i++) {
                $heatingSchedule .= substr($json['entries']['value']['thu'][$i]['mode'], 0, 1) . ',';
                $heatingSchedule .= $json['entries']['value']['thu'][$i]['start'] . ',';
                $heatingSchedule .= $json['entries']['value']['thu'][$i]['end'];
                if ($i < $n-1) {
                    $heatingSchedule .= ',';
                }
            }
            $heatingSchedule .= ';';

            $n = count($json['entries']['value']['fri']);
            for ($i=0; $i<$n; $i++) {
                $heatingSchedule .= substr($json['entries']['value']['fri'][$i]['mode'], 0, 1) . ',';
                $heatingSchedule .= $json['entries']['value']['fri'][$i]['start'] . ',';
                $heatingSchedule .= $json['entries']['value']['fri'][$i]['end'];
                if ($i < $n-1) {
                    $heatingSchedule .= ',';
                }
            }
            $heatingSchedule .= ';';

            $n = count($json['entries']['value']['sat']);
            for ($i=0; $i<$n; $i++) {
                $heatingSchedule .= substr($json['entries']['value']['sat'][$i]['mode'], 0, 1) . ',';
                $heatingSchedule .= $json['entries']['value']['sat'][$i]['start'] . ',';
                $heatingSchedule .= $json['entries']['value']['sat'][$i]['end'];
                if ($i < $n-1) {
                    $heatingSchedule .= ',';
                }
            }
            $heatingSchedule .= ';';

            $n = count($json['entries']['value']['sun']);
            for ($i=0; $i<$n; $i++) {
                $heatingSchedule .= substr($json['entries']['value']['sun'][$i]['mode'], 0, 1) . ',';
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

        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::HEATING_FROSTPROTECTION).',') !== false) {
            $frostProtection = $viessmannApi->getFrostprotection($circuitId);
        } else {
            $frostProtection = 0;
        }
        $this->getCmd(null, 'frostProtection')->event($frostProtection);

        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::SENSORS_TEMPERATURE_ROOM).',') !== false) {
            $roomTemperature = $viessmannApi->getRoomTemperature($circuitId);
        } else {
            $roomTemperature = 99;
        }
        $this->getCmd(null, 'roomTemperature')->event($roomTemperature);

        if (strPos($features, self::PRESSURE_SUPPLY.',') !== false) {
            $pressureSupply = $viessmannApi->getGenericFeaturePropertyAsJSON(self::PRESSURE_SUPPLY);
        } else {
            $pressureSupply = 99;
        }
        $this->getCmd(null, 'pressureSupply')->event($pressureSupply);
          
        $nbr = 0;
        $erreurs = '';
        $erreurCourante = '';
          
        if (strPos($features, ViessmannFeature::HEATING_ERRORS_ACTIVE.',') !== false) {
            $errors = $viessmannApi->getProperties(ViessmannFeature::HEATING_ERRORS_ACTIVE);
            $json = json_decode($errors, true);
            $n = count($json['entries']['value']['new']);
            for ($i=0; $i<$n; $i++) {
                $timeStamp = substr($json['entries']['value']['new'][$i]['timestamp'], 0, 19);
                $timeStamp = str_replace('T', ' ', $timeStamp);
                $errorCode = $json['entries']['value']['new'][$i]['errorCode'];
                if ($nbr < 10) {
                    if ($nbr > 0) {
                        $erreurs .= ';';
                    }
                    $erreurs .= 'AN;' . $timeStamp . ';' . $errorCode;
                    if ( $erreurCourante == '' ) {
                        $erreurCourante = $errorCode;
                    }
                    $nbr++;
                }
            }
            $n = count($json['entries']['value']['current']);
            for ($i=0; $i<$n; $i++) {
                $timeStamp = substr($json['entries']['value']['current'][$i]['timestamp'], 0, 19);
                $timeStamp = str_replace('T', ' ', $timeStamp);
                $errorCode = $json['entries']['value']['current'][$i]['errorCode'];
                if ($nbr < 10) {
                    if ($nbr > 0) {
                        $erreurs .= ';';
                    }
                    $erreurs .= 'AC;' . $timeStamp . ';' . $errorCode;
                    $nbr++;
                }
            }
            $n = count($json['entries']['value']['gone']);
            for ($i=0; $i<$n; $i++) {
                $timeStamp = substr($json['entries']['value']['gone'][$i]['timestamp'], 0, 19);
                $timeStamp = str_replace('T', ' ', $timeStamp);
                $errorCode = $json['entries']['value']['gone'][$i]['errorCode'];
                if ($nbr < 10) {
                    if ($nbr > 0) {
                        $erreurs .= ';';
                    }
                    $erreurs .= 'AG;' . $timeStamp . ';' . $errorCode;
                    $nbr++;
                }
            }
        }
      
        if (strPos($features, ViessmannFeature::HEATING_ERRORS.',') !== false) {
            $errors = $viessmannApi->getProperties(ViessmannFeature::HEATING_ERRORS);
            $json = json_decode($errors, true);
            $n = count($json['entries']['value']['new']);
            for ($i=0; $i<$n; $i++) {
                $timeStamp = substr($json['entries']['value']['new'][$i]['timestamp'], 0, 19);
                $timeStamp = str_replace('T', ' ', $timeStamp);
                $errorCode = $json['entries']['value']['new'][$i]['errorCode'];
                if ($nbr < 10) {
                    if ($nbr > 0) {
                        $erreurs .= ';';
                    }
                    $erreurs .= 'EN;' . $timeStamp . ';' . $errorCode;
                    $nbr++;
                }
            }
            $n = count($json['entries']['value']['current']);
            for ($i=0; $i<$n; $i++) {
                $timeStamp = substr($json['entries']['value']['current'][$i]['timestamp'], 0, 19);
                $timeStamp = str_replace('T', ' ', $timeStamp);
                $errorCode = $json['entries']['value']['current'][$i]['errorCode'];
                if ($nbr < 10) {
                    if ($nbr > 0) {
                        $erreurs .= ';';
                    }
                    $erreurs .= 'EC;' . $timeStamp . ';' . $errorCode;
                    $nbr++;
                }
            }
            $n = count($json['entries']['value']['gone']);
            for ($i=0; $i<$n; $i++) {
                $timeStamp = substr($json['entries']['value']['gone'][$i]['timestamp'], 0, 19);
                $timeStamp = str_replace('T', ' ', $timeStamp);
                $errorCode = $json['entries']['value']['gone'][$i]['errorCode'];
                if ($nbr < 10) {
                    if ($nbr > 0) {
                        $erreurs .= ';';
                    }
                    $erreurs .= 'EG;' . $timeStamp . ';' . $errorCode;
                    $nbr++;
                }
            }
        }
          
        if (strPos($features, ViessmannFeature::HEATING_ERRORS_HISTORY.',') !== false) {
            $errors = $viessmannApi->getProperties(ViessmannFeature::HEATING_ERRORS_HISTORY);
            $json = json_decode($errors, true);
            $n = count($json['entries']['value']['new']);
            for ($i=0; $i<$n; $i++) {
                $timeStamp = substr($json['entries']['value']['new'][$i]['timestamp'], 0, 19);
                $timeStamp = str_replace('T', ' ', $timeStamp);
                $errorCode = $json['entries']['value']['new'][$i]['errorCode'];
                if ($nbr < 10) {
                    if ($nbr > 0) {
                        $erreurs .= ';';
                    }
                    $erreurs .= 'HN;' . $timeStamp . ';' . $errorCode;
                    $nbr++;
                }
            }
            $n = count($json['entries']['value']['current']);
            for ($i=0; $i<$n; $i++) {
                $timeStamp = substr($json['entries']['value']['current'][$i]['timestamp'], 0, 19);
                $timeStamp = str_replace('T', ' ', $timeStamp);
                $errorCode = $json['entries']['value']['current'][$i]['errorCode'];
                if ($nbr < 10) {
                    if ($nbr > 0) {
                        $erreurs .= ';';
                    }
                    $erreurs .= 'HC;' . $timeStamp . ';' . $errorCode;
                    $nbr++;
                }
            }
            $n = count($json['entries']['value']['gone']);
            for ($i=0; $i<$n; $i++) {
                $timeStamp = substr($json['entries']['value']['gone'][$i]['timestamp'], 0, 19);
                $timeStamp = str_replace('T', ' ', $timeStamp);
                $errorCode = $json['entries']['value']['gone'][$i]['errorCode'];
                if ($nbr < 10) {
                    if ($nbr > 0) {
                        $erreurs .= ';';
                    }
                    $erreurs .= 'HG;' . $timeStamp . ';' . $errorCode;
                    $nbr++;
                }
            }
        }
        $this->getCmd(null, 'errors')->event($erreurs);
        $this->getCmd(null, 'currentError')->event($erreurCourante);
      
        if (strPos($features, ViessmannFeature::HEATING_SERVICE_TIMEBASED.',') !== false) {
            $lastServiceDate = substr($viessmannApi->getLastServiceDate(), 0, 19);
            $lastServiceDate = str_replace('T', ' ', $lastServiceDate);
            $serviceInterval = $viessmannApi->getServiceInterval();
            $monthSinceService = $viessmannApi->getActiveMonthSinceService();
        } else {
            $lastServiceDate = '';
            $serviceInterval = 99;
            $monthSinceService = 99;
        }
        $this->getCmd(null, 'lastServiceDate')->event($lastServiceDate);
        $this->getCmd(null, 'serviceInterval')->event($serviceInterval);
        $this->getCmd(null, 'monthSinceService')->event($monthSinceService);

        if (strPos($features, ViessmannFeature::HEATING_DHW_ONETIMECHARGE.',') !== false) {
            $isOneTimeDhwCharge = $viessmannApi->isOneTimeDhwCharge();
        } else {
            $isOneTimeDhwCharge = 0;
        }
        $this->getCmd(null, 'isOneTimeDhwCharge')->event($isOneTimeDhwCharge);

        if (strPos($features, ViessmannFeature::HEATING_DHW_CHARGING.',') !== false) {
            $isDhwCharging = $viessmannApi->isDhwCharging();
        } else {
            $isDhwCharging = 0;
        }
        $this->getCmd(null, 'isDhwCharging')->event($isDhwCharging);

        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::HOLIDAY_PROGRAM).',') !== false) {
            $active = $viessmannApi->getGenericFeaturePropertyAsJSON($this->buildFeature($circuitId, ViessmannAPI::HOLIDAY_PROGRAM), 'active');
            $start = $viessmannApi->getGenericFeaturePropertyAsJSON($this->buildFeature($circuitId, ViessmannAPI::HOLIDAY_PROGRAM), 'start');
            $end = $viessmannApi->getGenericFeaturePropertyAsJSON($this->buildFeature($circuitId, ViessmannAPI::HOLIDAY_PROGRAM), 'end');

            $start = str_replace('"', '', $start);
            $end = str_replace('"', '', $end);

            if ($this->validateDate($start, 'Y-m-d') == true) {            
                $this->getCmd(null, 'isScheduleHolidayProgram')->event(1);
                $this->getCmd(null, 'startHoliday')->event($start);
                $this->getCmd(null, 'endHoliday')->event($end);
            } else {
                $this->getCmd(null, 'isScheduleHolidayProgram')->event(0);
            }
        }

        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::COMFORT_PROGRAM).',') !== false) {
            $active = $viessmannApi->getGenericFeaturePropertyAsJSON($this->buildFeature($circuitId, ViessmannAPI::COMFORT_PROGRAM), 'active');

            if ( $active == 'true' ) {
                $this->getCmd(null, 'isActivateComfortProgram')->event(1);
            } else {
                $this->getCmd(null, 'isActivateComfortProgram')->event(0);
            }
        }
                    
        if (strPos($features, $this->buildFeature($circuitId, ViessmannAPI::ECO_PROGRAM).',') !== false) {
            $active = $viessmannApi->getGenericFeaturePropertyAsJSON($this->buildFeature($circuitId, ViessmannAPI::ECO_PROGRAM), 'active');

            if ( $active == 'true' ) {
                $this->getCmd(null, 'isActivateEcoProgram')->event(1);
            } else {
                $this->getCmd(null, 'isActivateEcoProgram')->event(0);
            }

        }
                    
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
        unset($viessmannApi);
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
        unset($viessmannApi);
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
        unset($viessmannApi);
    }

    // Set Eco Program Temperature
    //
    public function setEcoProgramTemperature($temperature)
    {
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }
        
        $viessmannApi->setEcoProgramTemperature($temperature);
        unset($viessmannApi);
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
        unset($viessmannApi);
    }

    // Set Slope
    //
    public function setSlope($slope)
    {
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }

        $obj = $this->getCmd(null, 'shift');
        $shift = $obj->execCmd();
        
        $viessmannApi->setCurve($shift, round($slope, 1));
        unset($viessmannApi);
    }

    // Set Shift
    //
    public function setShift($shift)
    {
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }
        
        $obj = $this->getCmd(null, 'slope');
        $slope = $obj->execCmd();

        $viessmannApi->setCurve($shift, round($slope, 1));
        unset($viessmannApi);
    }

    // Start One Time Dhw Charge
    //
    public function startOneTimeDhwCharge()
    {
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }
        
        $viessmannApi->startOneTimeDhwCharge();
        unset($viessmannApi);

        $this->getCmd(null, 'isOneTimeDhwCharge')->event(1);
    }

    // Stop One Time Dhw Charge
    //
    public function stopOneTimeDhwCharge()
    {
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }
        
        $viessmannApi->stopOneTimeDhwCharge();
        unset($viessmannApi);

        $this->getCmd(null, 'isOneTimeDhwCharge')->event(0);
    }

    // Activate Comfort Program
    //
    public function activateComfortProgram()
    {
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }
        
        $viessmannApi->activateComfortProgram();
        unset($viessmannApi);
 
        $this->getCmd(null, 'isActivateComfortProgram')->event(1);
    }

    // deActivate Comfort Program
    //
    public function deActivateComfortProgram()
    {
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }
        
        $viessmannApi->deActivateComfortProgram();
        unset($viessmannApi);

        $this->getCmd(null, 'isActivateComfortProgram')->event(0);
    }

    // Activate Eco Program
    //
    public function activateEcoProgram()
    {
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }
        
        $viessmannApi->activateEcoProgram();
        unset($viessmannApi);

        $this->getCmd(null, 'isActivateEcoProgram')->event(1);
    }

    // deActivate Eco Program
    //
    public function deActivateEcoProgram()
    {
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }
        
        $viessmannApi->deActivateEcoProgram();
        unset($viessmannApi);

        $this->getCmd(null, 'isActivateEcoProgram')->event(0);
    }

    // Schedule Holiday Program
    //
    public function scheduleHolidayProgram()
    {
        $obj = $this->getCmd(null, 'startHoliday');
        $startHoliday = $obj->execCmd();
        if ($this->validateDate($startHoliday, 'Y-m-d') == false) {
            throw new Exception(__('Date de début invalide', __FILE__));
            return;
        }

        $obj = $this->getCmd(null, 'endHoliday');
        $endHoliday = $obj->execCmd();
        if ($this->validateDate($endHoliday, 'Y-m-d') == false) {
            throw new Exception(__('Date de fin invalide', __FILE__));
            return;
        }

        if ($startHoliday > $endHoliday) {
            throw new Exception(__('Date de début postérieure à la date de fin', __FILE__));
            return;
        }
    
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }

        $viessmannApi->scheduleHolidayProgram($startHoliday, $endHoliday);
        unset($viessmannApi);

        $this->getCmd(null, 'isScheduleHolidayProgram')->event(1);


    }

    // Unschedule Holiday Program
    //
    public function unscheduleHolidayProgram()
    {
        $viessmannApi = $this->getViessmann();
        if ($viessmannApi == null) {
            return;
        }
        
        $viessmannApi->unscheduleHolidayProgram();
        unset($viessmannApi);

        $this->getCmd(null, 'isScheduleHolidayProgram')->event(0);

    }

    public static function periodique()
    {
        $oldUserName = '';
        $oldPassword = '';

        $first = true;
        $tousPareils = true;
        foreach (self::byType('viessmann') as $viessmann) {
            if ($viessmann->getIsEnable() == 1) {
                $userName = trim($viessmann->getConfiguration('userName', ''));
                $password = trim($viessmann->getConfiguration('password', ''));
                if ($first == false) {
                    if (($userName != $oldUserName) || ($password != $oldPassword)) {
                        $tousPareils = false;
                    }
                }
                $oldUserName = $userName;
                $oldPassword = $password;
                $first = false;
            }
        }

        if ($tousPareils == true) {
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
            unset($viessmannApi);
        } else {
            $viessmann = null;
            foreach (self::byType('viessmann') as $viessmann) {
                if ($viessmann->getIsEnable() == 1) {
                    $viessmannApi = $viessmann->getViessmann();
                    if ($viessmannApi != null) {
                        $viessmann->rafraichir($viessmannApi);
                        unset($viessmannApi);
                    }
                }
            }
        }
    }

    public static function cron()
    {
        $maintenant = time();
        $minute = date("i", $maintenant);
        if (($minute % 2) == 0) {
            self::periodique();
        }
    }
    
    public static function cron5()
    {
        self::periodique();
    }
    
    public static function cron10()
    {
        self::periodique();
    }
    
    public static function cron15()
    {
        self::periodique();
    }
    
    public static function cron30()
    {
        self::periodique();
    }
    
    public static function cronHourly()
    {
        self::periodique();
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
        $obj->save();

        $objSlope = $this->getCmd(null, 'slope');
        if (!is_object($objSlope)) {
            $objSlope = new viessmannCmd();
            $objSlope->setName(__('Pente', __FILE__));
            $objSlope->setIsVisible(1);
            $objSlope->setIsHistorized(0);
        }
        $objSlope->setEqLogic_id($this->getId());
        $objSlope->setType('info');
        $objSlope->setSubType('numeric');
        $objSlope->setLogicalId('slope');
        $objSlope->setConfiguration('minValue', 0.2);
        $objSlope->setConfiguration('maxValue', 3.5);
        $objSlope->save();
  
        $objShift = $this->getCmd(null, 'shift');
        if (!is_object($objShift)) {
            $objShift = new viessmannCmd();
            $objShift->setName(__('Parallèle', __FILE__));
            $objShift->setIsVisible(1);
            $objShift->setIsHistorized(0);
        }
        $objShift->setEqLogic_id($this->getId());
        $objShift->setType('info');
        $objShift->setSubType('numeric');
        $objShift->setLogicalId('shift');
        $objShift->setConfiguration('minValue', -13);
        $objShift->setConfiguration('maxValue', 40);
        $objShift->save();

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
        $objReduced->save();
  
        $objEco = $this->getCmd(null, 'ecoProgramTemperature');
        if (!is_object($objEco)) {
            $objEco = new viessmannCmd();
            $objEco->setName(__('Température éco', __FILE__));
            $objEco->setUnite('°C');
            $objEco->setIsVisible(1);
            $objEco->setIsHistorized(0);
        }
        $objEco->setEqLogic_id($this->getId());
        $objEco->setType('info');
        $objEco->setSubType('numeric');
        $objEco->setLogicalId('ecoProgramTemperature');
        $objEco->setConfiguration('minValue', 2);
        $objEco->setConfiguration('maxValue', 37);
        $objEco->save();
  
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
        $obj->save();

        $obj = $this->getCmd(null, 'heatingGazConsumption');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Consommation gaz chauffage', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('numeric');
        $obj->setLogicalId('heatingGazConsumption');
        $obj->save();

        $obj = $this->getCmd(null, 'heatingGazConsumptionDay');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Consommation journalière gaz chauffage', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('heatingGazConsumptionDay');
        $obj->save();

        $obj = $this->getCmd(null, 'heatingGazConsumptionWeek');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Consommation hebdomadaire gaz chauffage', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('heatingGazConsumptionWeek');
        $obj->save();

        $obj = $this->getCmd(null, 'heatingGazConsumptionMonth');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Consommation mensuelle gaz chauffage', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('heatingGazConsumptionMonth');
        $obj->save();

        $obj = $this->getCmd(null, 'heatingGazConsumptionYear');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Consommation annuelle gaz chauffage', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('heatingGazConsumptionYear');
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
        $obj->save();

        $obj = $this->getCmd(null, 'ecoProgramSlider');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setUnite('°C');
            $obj->setName(__('Slider température éco', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('action');
        $obj->setSubType('slider');
        $obj->setLogicalId('ecoProgramSlider');
        $obj->setValue($objEco->getId());
        $obj->setConfiguration('minValue', 2);
        $obj->setConfiguration('maxValue', 37);
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
        $obj->save();

        $obj = $this->getCmd(null, 'pressureSupply');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Pression installation', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('numeric');
        $obj->setLogicalId('pressureSupply');
        $obj->save();

        $obj = $this->getCmd(null, 'totalGazConsumptionDay');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Consommation journalière gaz', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('totalGazConsumptionDay');
        $obj->save();

        $obj = $this->getCmd(null, 'totalGazConsumptionWeek');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Consommation hebdomadaire gaz', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('totalGazConsumptionWeek');
        $obj->save();

        $obj = $this->getCmd(null, 'totalGazConsumptionMonth');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Consommation mensuelle gaz', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('totalGazConsumptionMonth');
        $obj->save();

        $obj = $this->getCmd(null, 'totalGazConsumptionYear');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Consommation annuelle gaz', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('totalGazConsumptionYear');
        $obj->save();

        $obj = $this->getCmd(null, 'errors');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Erreurs', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('errors');
        $obj->save();

        $obj = $this->getCmd(null, 'currentError');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Erreur courante', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('currentError');
        $obj->save();

        $obj = $this->getCmd(null, 'lastServiceDate');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Date dernier entretien', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('lastServiceDate');
        $obj->save();

        $obj = $this->getCmd(null, 'serviceInterval');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Intervalle entretien', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('numeric');
        $obj->setLogicalId('serviceInterval');
        $obj->save();

        $obj = $this->getCmd(null, 'monthSinceService');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Mois entretien', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('numeric');
        $obj->setLogicalId('monthSinceService');
        $obj->save();

        $obj = $this->getCmd(null, 'slopeSlider');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Slider pente', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('action');
        $obj->setSubType('slider');
        $obj->setLogicalId('slopeSlider');
        $obj->setValue($objSlope->getId());
        $obj->setConfiguration('minValue', 0.2);
        $obj->setConfiguration('maxValue', 3.5);
        $optParam = $obj->getDisplay('parameters');
        if (!is_array($optParam)) {
            $optParam = array();
        }
        $optParam['step'] = 0.1;
        $obj->setDisplay('parameters', $optParam);
        $obj->save();

        $obj = $this->getCmd(null, 'shiftSlider');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Slider parallèle', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('action');
        $obj->setSubType('slider');
        $obj->setLogicalId('shiftSlider');
        $obj->setValue($objShift->getId());
        $obj->setConfiguration('minValue', -13);
        $obj->setConfiguration('maxValue', 40);
        $obj->save();

        $obj = $this->getCmd(null, 'heatingGazHistorize');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Historisation gaz chauffage', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(1);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('numeric');
        $obj->setLogicalId('heatingGazHistorize');
        $obj->save();

        $obj = $this->getCmd(null, 'dhwGazHistorize');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Historisation gaz eau chaude', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(1);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('numeric');
        $obj->setLogicalId('dhwGazHistorize');
        $obj->save();

        $obj = $this->getCmd(null, 'heatingPowerHistorize');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Historisation électricité', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(1);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('numeric');
        $obj->setLogicalId('heatingPowerHistorize');
        $obj->save();

        $obj = $this->getCmd(null, 'isOneTimeDhwCharge');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Forcer Eau chaude', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('binary');
        $obj->setLogicalId('isOneTimeDhwCharge');
        $obj->save();

        $obj = $this->getCmd(null, 'startOneTimeDhwCharge');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Activer eau chaude', __FILE__));
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setLogicalId('startOneTimeDhwCharge');
        $obj->setType('action');
        $obj->setSubType('other');
        $obj->save();

        $obj = $this->getCmd(null, 'stopOneTimeDhwCharge');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Désactiver eau chaude', __FILE__));
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setLogicalId('stopOneTimeDhwCharge');
        $obj->setType('action');
        $obj->setSubType('other');
        $obj->save();

        $obj = $this->getCmd(null, 'isDhwCharging');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Chauffage Eau chaude', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('binary');
        $obj->setLogicalId('isDhwCharging');
        $obj->save();

        $obj = $this->getCmd(null, 'activateComfortProgram');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Activer programme confort', __FILE__));
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setLogicalId('activateComfortProgram');
        $obj->setType('action');
        $obj->setSubType('other');
        $obj->save();

        $obj = $this->getCmd(null, 'deActivateComfortProgram');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Désactiver programme confort', __FILE__));
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setLogicalId('deActivateComfortProgram');
        $obj->setType('action');
        $obj->setSubType('other');
        $obj->save();

        $obj = $this->getCmd(null, 'activateEcoProgram');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Activer programme éco', __FILE__));
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setLogicalId('activateEcoProgram');
        $obj->setType('action');
        $obj->setSubType('other');
        $obj->save();

        $obj = $this->getCmd(null, 'deActivateEcoProgram');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Désactiver programme éco', __FILE__));
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setLogicalId('deActivateEcoProgram');
        $obj->setType('action');
        $obj->setSubType('other');
        $obj->save();

        $obj = $this->getCmd(null, 'startHoliday');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Date début', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('startHoliday');
        $obj->save();

        $obj = $this->getCmd(null, 'endHoliday');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Date fin', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('string');
        $obj->setLogicalId('endHoliday');
        $obj->save();

        $obj = $this->getCmd(null, 'startHolidayText');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Date Début texte', __FILE__));
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setLogicalId('startHolidayText');
        $obj->setType('action');
        $obj->setSubType('other');
        $obj->save();

        $obj = $this->getCmd(null, 'endHolidayText');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Date Fin texte', __FILE__));
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setLogicalId('endHolidayText');
        $obj->setType('action');
        $obj->setSubType('other');
        $obj->save();

        $obj = $this->getCmd(null, 'scheduleHolidayProgram');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Activer programme vacances', __FILE__));
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setLogicalId('scheduleHolidayProgram');
        $obj->setType('action');
        $obj->setSubType('other');
        $obj->save();

        $obj = $this->getCmd(null, 'unscheduleHolidayProgram');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Désactiver programme vacances', __FILE__));
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setLogicalId('unscheduleHolidayProgram');
        $obj->setType('action');
        $obj->setSubType('other');
        $obj->save();

        $obj = $this->getCmd(null, 'isScheduleHolidayProgram');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Programme vacances actif', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('binary');
        $obj->setLogicalId('isScheduleHolidayProgram');
        $obj->save();

        $obj = $this->getCmd(null, 'isActivateComfortProgram');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Programme comfort actif', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('binary');
        $obj->setLogicalId('isActivateComfortProgram');
        $obj->save();

        $obj = $this->getCmd(null, 'isActivateEcoProgram');
        if (!is_object($obj)) {
            $obj = new viessmannCmd();
            $obj->setName(__('Programme éco actif', __FILE__));
            $obj->setIsVisible(1);
            $obj->setIsHistorized(0);
        }
        $obj->setEqLogic_id($this->getId());
        $obj->setType('info');
        $obj->setSubType('binary');
        $obj->setLogicalId('isActivateEcoProgram');
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
        $uniteGaz = $this->getConfiguration('uniteGaz', 'm3');

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
          
        $obj = $this->getCmd(null, 'ecoProgramTemperature');
        $replace["#ecoProgramTemperature#"] = $obj->execCmd();
        $replace["#idEcoProgramTemperature#"] = $obj->getId();
        $replace["#minEco#"] = $obj->getConfiguration('minValue') + 1;
        $replace["#maxEco#"] = $obj->getConfiguration('maxValue');
        $replace["#stepEco#"] = 1;
          
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

        $obj = $this->getCmd(null, 'totalGazConsumptionDay');
        $replace["#totalGazConsumptionDay#"] = $obj->execCmd();
        $replace["#idTotalGazConsumptionDay#"] = $obj->getId();

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

        $obj = $this->getCmd(null, 'totalGazConsumptionWeek');
        $replace["#totalGazConsumptionWeek#"] = $obj->execCmd();
        $replace["#idTotalGazConsumptionWeek#"] = $obj->getId();

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

        $obj = $this->getCmd(null, 'totalGazConsumptionMonth');
        $replace["#totalGazConsumptionMonth#"] = $obj->execCmd();
        $replace["#idTotalGazConsumptionMonth#"] = $obj->getId();

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
                $mois = 11;
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

        $obj = $this->getCmd(null, 'totalGazConsumptionYear');
        $replace["#totalGazConsumptionYear#"] = $obj->execCmd();
        $replace["#idTotalGazConsumptionYear#"] = $obj->getId();

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
                $mois = 11;
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
        $replace["#minSlope#"] = $obj->getConfiguration('minValue');
        $replace["#maxSlope#"] = $obj->getConfiguration('maxValue');
        $replace["#stepSlope#"] = 0.1;

        $obj = $this->getCmd(null, 'shift');
        $replace["#shift#"] = $obj->execCmd();
        $replace["#idShift#"] = $obj->getId();
        $replace["#minShift#"] = $obj->getConfiguration('minValue');
        $replace["#maxShift#"] = $obj->getConfiguration('maxValue');
        $replace["#stepShift#"] = 1;

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

        $obj = $this->getCmd(null, 'pressureSupply');
        $replace["#pressureSupply#"] = $obj->execCmd();
        $replace["#idPressureSupply#"] = $obj->getId();

        $obj = $this->getCmd(null, 'comfortProgramSlider');
        $replace["#idComfortProgramSlider#"] = $obj->getId();
        $obj = $this->getCmd(null, 'normalProgramSlider');
        $replace["#idNormalProgramSlider#"] = $obj->getId();
        $obj = $this->getCmd(null, 'reducedProgramSlider');
        $replace["#idReducedProgramSlider#"] = $obj->getId();
        $obj = $this->getCmd(null, 'ecoProgramSlider');
        $replace["#idEcoProgramSlider#"] = $obj->getId();
        $obj = $this->getCmd(null, 'dhwSlider');
        $replace["#idDhwSlider#"] = $obj->getId();

        $obj = $this->getCmd(null, 'shiftSlider');
        $replace["#idShiftSlider#"] = $obj->getId();
        $obj = $this->getCmd(null, 'slopeSlider');
        $replace["#idSlopeSlider#"] = $obj->getId();

        $replace["#circuitName#"] = $circuitName;
        $replace["#displayGas#"] = $displayGas;
        $replace["#displayPower#"] = $displayPower;
        $replace["#uniteGaz#"] = $uniteGaz;

        $obj = $this->getCmd(null, 'errors');
        $replace["#errors#"] = $obj->execCmd();
        $replace["#idErrors#"] = $obj->getId();
          
        $obj = $this->getCmd(null, 'currentError');
        $replace["#currentError#"] = $obj->execCmd();
        $replace["#idCurrentError#"] = $obj->getId();
          
        $obj = $this->getCmd(null, 'lastServiceDate');
        $replace["#lastServiceDate#"] = $obj->execCmd();
        $replace["#idLastServiceDate#"] = $obj->getId();
          
        $obj = $this->getCmd(null, 'serviceInterval');
        $replace["#serviceInterval#"] = $obj->execCmd();
        $replace["#idServiceInterval#"] = $obj->getId();
          
        $obj = $this->getCmd(null, 'monthSinceService');
        $replace["#monthSinceService#"] = $obj->execCmd();
        $replace["#idMonthSinceService#"] = $obj->getId();
          
        $obj = $this->getCmd(null, 'isOneTimeDhwCharge');
        $replace["#isOneTimeDhwCharge#"] = $obj->execCmd();
        $replace["#idIsOneTimeDhwCharge#"] = $obj->getId();
          
        $obj = $this->getCmd(null, 'startOneTimeDhwCharge');
        $replace["#idStartOneTimeDhwCharge#"] = $obj->getId();

        $obj = $this->getCmd(null, 'stopOneTimeDhwCharge');
        $replace["#idStopOneTimeDhwCharge#"] = $obj->getId();

        $obj = $this->getCmd(null, 'isDhwCharging');
        $replace["#isDhwCharging#"] = $obj->execCmd();
        $replace["#idIsDhwCharging#"] = $obj->getId();
 
        $obj = $this->getCmd(null, 'activateComfortProgram');
        $replace["#idActivateComfortProgram#"] = $obj->getId();

        $obj = $this->getCmd(null, 'deActivateComfortProgram');
        $replace["#idDeActivateComfortProgram#"] = $obj->getId();

        $obj = $this->getCmd(null, 'activateEcoProgram');
        $replace["#idActivateEcoProgram#"] = $obj->getId();

        $obj = $this->getCmd(null, 'deActivateEcoProgram');
        $replace["#idDeActivateEcoProgram#"] = $obj->getId();

        $obj = $this->getCmd(null, 'isScheduleHolidayProgram');
        $replace["#isScheduleHolidayProgram#"] = $obj->execCmd();
        $replace["#idIsScheduleHolidayProgram#"] = $obj->getId();
 
        $obj = $this->getCmd(null, 'startHoliday');
        $replace["#startHoliday#"] = $obj->execCmd();
        $replace["#idStartHoliday#"] = $obj->getId();
 
        $obj = $this->getCmd(null, 'endHoliday');
        $replace["#endHoliday#"] = $obj->execCmd();
        $replace["#idEndHoliday#"] = $obj->getId();
 
        $obj = $this->getCmd(null, 'startHolidayText');
        $replace["#idStartHolidayText#"] = $obj->getId();
 
        $obj = $this->getCmd(null, 'endHolidayText');
        $replace["#idEndHolidayText#"] = $obj->getId();

        $obj = $this->getCmd(null, 'scheduleHolidayProgram');
        $replace["#idScheduleHolidayProgram#"] = $obj->getId();

        $obj = $this->getCmd(null, 'unscheduleHolidayProgram');
        $replace["#idUnscheduleHolidayProgram#"] = $obj->getId();

        $obj = $this->getCmd(null, 'isActivateComfortProgram');
        $replace["#isActivateComfortProgram#"] = $obj->execCmd();
        $replace["#idIsActivateComfortProgram#"] = $obj->getId();
 
        $obj = $this->getCmd(null, 'isActivateEcoProgram');
        $replace["#isActivateEcoProgram#"] = $obj->execCmd();
        $replace["#idIsActivateEcoProgram#"] = $obj->getId();
 
        return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'viessmann_view', 'viessmann')));
        
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
                unset($viessmannApi);
            }
        } elseif ($this->getLogicalId() == 'startOneTimeDhwCharge') {
            $eqlogic->startOneTimeDhwCharge();
        } elseif ($this->getLogicalId() == 'stopOneTimeDhwCharge') {
            $eqlogic->stopOneTimeDhwCharge();
        } elseif ($this->getLogicalId() == 'activateComfortProgram') {
            $eqlogic->activateComfortProgram();
        } elseif ($this->getLogicalId() == 'deActivateComfortProgram') {
            $eqlogic->deActivateComfortProgram();
        } elseif ($this->getLogicalId() == 'activateEcoProgram') {
            $eqlogic->activateEcoProgram();
        } elseif ($this->getLogicalId() == 'deActivateEcoProgram') {
            $eqlogic->deActivateEcoProgram();
        } elseif ($this->getLogicalId() == 'scheduleHolidayProgram') {
            $eqlogic->scheduleHolidayProgram();
        } elseif ($this->getLogicalId() == 'unscheduleHolidayProgram') {
            $eqlogic->unscheduleHolidayProgram();
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
        } elseif ($this->getLogicalId() == 'ecoProgramSlider') {
            if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                return;
            }
            $eqlogic->getCmd(null, 'ecoProgramTemperature')->event($_options['slider']);
            $eqlogic->setEcoProgramTemperature($_options['slider']);
        } elseif ($this->getLogicalId() == 'dhwSlider') {
            if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                return;
            }
            $eqlogic->getCmd(null, 'dhwTemperature')->event($_options['slider']);
            $eqlogic->setDhwTemperature($_options['slider']);
        } elseif ($this->getLogicalId() == 'shiftSlider') {
            if (!isset($_options['slider']) || $_options['slider'] == '' || !is_numeric(intval($_options['slider']))) {
                return;
            }
            $eqlogic->getCmd(null, 'shift')->event($_options['slider']);
            $eqlogic->setShift($_options['slider']);
        } elseif ($this->getLogicalId() == 'startHolidayText') {
            if (!isset($_options['text']) || $_options['text'] == '') {
                return;
            }
            $eqlogic->getCmd(null, 'startHoliday')->event($_options['text']);
        } elseif ($this->getLogicalId() == 'endHolidayText') {
            if (!isset($_options['text']) || $_options['text'] == '') {
                return;
            }
            $eqlogic->getCmd(null, 'endHoliday')->event($_options['text']);
        }
    }
}
