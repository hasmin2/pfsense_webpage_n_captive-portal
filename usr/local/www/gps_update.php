<?
require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");
function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000){
  // convert from degrees to radians
  $latFrom = deg2rad($latitudeFrom);
  $lonFrom = deg2rad($longitudeFrom);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);
  $latDelta = $latTo - $latFrom;
  $lonDelta = $lonTo - $lonFrom;

  $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
  return $angle * $earthRadius;
}
global $config;
$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => 'http://192.168.209.210:8086/query?q=select%20*%20from%20vesselposition%20where%20time%20%3E%20now()%20-10m%20order%20by%20time%20desc&db=acustatus',
		CURLOPT_TIMEOUT => 1,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_CUSTOMREQUEST => GET,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json'
		)
	));
	$response = curl_exec($ch);
	curl_close($ch);

	$decoded = json_decode($response, true);
	$headingIdx = 0;
	$latIdx = 0;
	$lonIdx = 0;
	$latDirIdx = 0;
	$lonDirIdx = 0;
	$columncount= count($decoded['results'][0]['series'][0]['columns']);
	for ($i = 0; $i < $columncount; $i++){
		switch($decoded['results'][0]['series'][0]['columns'][$i]){
			case "Heading":
				$headingIdx = $i;
				break;
			case "Latitude":
				$latIdx = $i;
				break;
			case "Longitude":
				$lonIdx = $i;
				break;
			case "lat-direction":
				$latDirIdx = $i;
				break;
			case "lon-direction":
				$lonDirIdx = $i;
				break;
		}
	}
	$heading = $decoded['results'][0]['series'][0]['values'][0][$headingIdx];
	$lat = $decoded['results'][0]['series'][0]['values'][0][$latIdx];
	$lon = $decoded['results'][0]['series'][0]['values'][0][$lonIdx];
	$latDir = $decoded['results'][0]['series'][0]['values'][0][$latDirIdx];
	$lonDir = $decoded['results'][0]['series'][0]['values'][0][$lonDirIdx];

	$lat_last = $decoded['results'][0]['series'][0]['values'][1][$latIdx];
	$lon_last = $decoded['results'][0]['series'][0]['values'][1][$lonIdx];
	$latDir_last = $decoded['results'][0]['series'][0]['values'][1][$latDirIdx];
	$lonDir_last = $decoded['results'][0]['series'][0]['values'][1][$lonDirIdx];
	if($latDir == "S"){ $lat_current = $lat*-1; }
	else { $lat_current = $lat; }
	if($lonDir == "W"){ $lon_current = 360 - $lon; }
	else { $lon_current = $lon; }
	if($latDir_last == "S"){ $lat_last = $lat_last*-1; }
	if($lonDir_last == "W"){ $lon_last = 360-$lon_last; }

	$current_time= $decoded['results'][0]['series'][0]['values'][0][0];
	$last_time= $decoded['results'][0]['series'][0]['values'][1][0];
	$timegap= strtotime($current_time) - strtotime($last_time);
	$distance = haversineGreatCircleDistance($lat_current, $lon_current, $lat_last, $lon_last, 6371);
	$avrhrspeed= round($distance/$timegap*3600/1.852, 2);
	$time= date("His.00", strtotime($current_time));
	$date= date("ymd", strtotime($current_time));
	$current_gpsdata = "GPRMC,{$time},A,{$lat_current},{$latDir},{$lon_current},{$lonDir},{$avrhrspeed},{$heading},{$date},0.0,E,M";
	$gpsdata_Array = str_split($current_gpsdata);
	$checksum = ord($gpsdata_Array[0]);
	for($i = 1; $i<count($gpsdata_Array); $i++){
		$checksum ^= ord($gpsdata_Array[$i]);
	}
	$current_gpsdata="\$". $current_gpsdata ."*" . dechex($checksum);
	if($current_gpsdata!==$config['gpsdata']){
		$config['gpsdata']= $current_gpsdata;
		write_config("GPS_WRITE");
	}

?>