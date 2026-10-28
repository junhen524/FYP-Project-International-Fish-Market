<?php

class WeatherService {
    private static $weatherCache = [];
    private static $cacheTtl = 300;

    private static $weatherCodeMap = [
        0 => ['Clear sky', '☀️'],
        1 => ['Mainly clear', '🌤️'],
        2 => ['Partly cloudy', '⛅'],
        3 => ['Overcast', '☁️'],
        45 => ['Fog', '🌫️'],
        48 => ['Depositing rime fog', '🌫️'],
        51 => ['Light drizzle', '🌦️'],
        53 => ['Moderate drizzle', '🌦️'],
        55 => ['Dense drizzle', '🌧️'],
        61 => ['Slight rain', '🌦️'],
        63 => ['Moderate rain', '🌧️'],
        65 => ['Heavy rain', '⛈️'],
        80 => ['Slight rain showers', '🌦️'],
        81 => ['Moderate rain showers', '🌧️'],
        82 => ['Violent rain showers', '⛈️'],
        95 => ['Thunderstorm', '⛈️'],
    ];

    private static $ports = [];
    private static $portsLoaded = false;

    private static function loadPorts() {
        if (self::$portsLoaded) return;
        self::$portsLoaded = true;
        try {
            require_once __DIR__ . '/../helpers/functions.php';
            $rows = dbGetAll("SELECT code, name, latitude as lat, longitude as lon FROM ports WHERE status = 'active' ORDER BY id");
            foreach ($rows as $r) {
                $key = strtolower($r['code']);
                self::$ports[$key] = ['name' => $r['name'], 'lat' => $r['lat'], 'lon' => $r['lon']];
            }
        } catch (Exception $e) {
            self::$ports = [];
        }
    }

    public static function getPorts() {
        self::loadPorts();
        return self::$ports;
    }

    public static function getPortWeather($portKey = null) {
        self::loadPorts();
        if (empty(self::$ports)) {
            return ['port_name' => 'Unknown', 'temperature' => 'N/A', 'description' => 'No data', 'emoji' => '🌍', 'humidity' => null, 'wind_speed' => null];
        }
        if ($portKey === null || !isset(self::$ports[$portKey])) {
            $keys = array_keys(self::$ports);
            $portKey = $keys[0] ?? 'pkl';
        }
        $now = time();
        $cached = self::$weatherCache[$portKey] ?? null;
        if ($cached && ($now - $cached['timestamp']) < self::$cacheTtl) {
            return $cached['data'];
        }

        $port = self::$ports[$portKey];
        $url = "https://api.open-meteo.com/v1/forecast?latitude={$port['lat']}&longitude={$port['lon']}&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m&timezone=Asia/Kuala_Lumpur";

        try {
            $response = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 10, 'method' => 'GET'],
            ]));

            if ($response === false) {
                throw new \Exception('Failed to fetch weather');
            }

            $data = json_decode($response, true);
            $current = $data['current'] ?? [];
            $weatherCode = $current['weather_code'] ?? null;
            $weatherInfo = self::$weatherCodeMap[$weatherCode] ?? ['Unknown', '🌍'];

            $result = [
                'port_name' => $port['name'],
                'temperature' => $current['temperature_2m'] ?? null,
                'humidity' => $current['relative_humidity_2m'] ?? null,
                'wind_speed' => $current['wind_speed_10m'] ?? null,
                'description' => $weatherInfo[0],
                'emoji' => $weatherInfo[1],
            ];
        } catch (\Exception $e) {
            error_log("Weather fetch failed: " . $e->getMessage());
            $result = [
                'port_name' => $port['name'],
                'temperature' => 'N/A',
                'humidity' => null, 'wind_speed' => null,
                'description' => 'Service Offline',
                'emoji' => '⚠️',
            ];
        }

        self::$weatherCache[$portKey] = ['data' => $result, 'timestamp' => $now];
        return $result;
    }

    public static function getAllWeather() {
        self::loadPorts();
        $all = [];
        foreach (self::$ports as $key => $port) {
            $w = self::getPortWeather($key);
            $all[] = ['key' => $key, 'name' => $port['name'], 'temperature' => $w['temperature'], 'humidity' => $w['humidity'], 'wind_speed' => $w['wind_speed'], 'emoji' => $w['emoji'], 'description' => $w['description']];
        }
        return $all;
    }
}
